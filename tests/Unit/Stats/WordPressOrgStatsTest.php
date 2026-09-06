<?php
/**
 * WordPressOrg rewrite: mirror_fallbacks on failure, downloads only for
 * version-check and zip 2xx.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Stats;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Connectivity\MirrorHealth;
use WenPai\ChinaYes\Connectivity\WordPressOrg\MirrorProbe;
use WenPai\ChinaYes\Connectivity\WordPressOrg\Origins;
use WenPai\ChinaYes\Connectivity\WordPressOrg\WordPressOrgModule;
use WenPai\ChinaYes\Stats\Counters;
use WenPai\ChinaYes\Stats\Events;
use WenPai\ChinaYes\Stats\StatsModule;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WenPai\ChinaYes\Tests\Unit\Connectivity\MapConfig;
use WenPai\ChinaYes\Tests\Unit\Rest\RestStore;
use WP_Error;

require_once __DIR__ . '/wp-stats-stubs.php';

/**
 * Production path for mirror_fallbacks and honest download counters.
 */
class WordPressOrgStatsTest extends TestCase {

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		OptionStore::reset();
		RestStore::reset();
	}

	/**
	 * Rewrite failure increments mirror_fallbacks once; a second failure
	 * inside MirrorHealth TTL stays 1.
	 */
	public function test_rewrite_failure_increments_mirror_fallbacks_once_per_ttl() {
		$counters = $this->counters();
		$this->register_stats( $counters );
		$module = $this->module(
			static function () {
				return new WP_Error( 'http_request_failed', 'timeout' );
			}
		);

		$module->filter_wordpress_org( false, array(), 'https://api.wordpress.org/core/version-check/1.7/' );
		$counters->flush();
		$stored = OptionStore::$options[ Counters::OPTION ];
		$this->assertSame( 1, $stored['buckets']['2026-09-06']['mirror_fallbacks'] );

		$module->filter_wordpress_org( false, array(), 'https://api.wordpress.org/core/version-check/1.7/' );
		$counters->flush();
		$stored = OptionStore::$options[ Counters::OPTION ];
		$this->assertSame( 1, $stored['buckets']['2026-09-06']['mirror_fallbacks'] );
	}

	/**
	 * Version-check 2xx counts as a mirror download.
	 */
	public function test_version_check_2xx_counts_download() {
		$counters = $this->counters();
		$this->register_stats( $counters );
		$module = $this->module(
			static function () {
				return array(
					'code'    => 200,
					'headers' => array( 'content-length' => '12' ),
					'body'    => '{"offers":[]}',
				);
			}
		);

		$module->filter_wordpress_org( false, array(), 'https://api.wordpress.org/core/version-check/1.7/' );
		$counters->flush();
		$stored = OptionStore::$options[ Counters::OPTION ];
		$this->assertSame( 1, $stored['buckets']['2026-09-06']['mirror_downloads'] );
		$this->assertSame( 12, $stored['buckets']['2026-09-06']['mirror_bytes_saved'] );
	}

	/**
	 * Install zip 2xx on downloads.wenpai.net counts.
	 */
	public function test_package_zip_2xx_counts_download() {
		$counters = $this->counters();
		$this->register_stats( $counters );
		$module = $this->module(
			static function () {
				return array(
					'code'    => 200,
					'headers' => array( 'content-length' => '2048' ),
					'body'    => str_repeat( 'z', 2048 ),
				);
			}
		);

		$module->filter_wordpress_org( false, array(), 'https://downloads.wordpress.org/plugin/classic-editor.1.6.3.zip' );
		$counters->flush();
		$stored = OptionStore::$options[ Counters::OPTION ];
		$this->assertSame( 1, $stored['buckets']['2026-09-06']['mirror_downloads'] );
		$this->assertSame( 2048, $stored['buckets']['2026-09-06']['mirror_bytes_saved'] );
	}

	/**
	 * Plugin-info API 2xx is rewritten but not counted.
	 */
	public function test_plugin_info_2xx_does_not_count_download() {
		$counters = $this->counters();
		$this->register_stats( $counters );
		$module = $this->module(
			static function () {
				return array(
					'code' => 200,
					'body' => '{"name":"classic-editor"}',
				);
			}
		);

		$module->filter_wordpress_org( false, array(), 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information' );
		$counters->flush();
		$this->assertArrayNotHasKey( Counters::OPTION, OptionStore::$options );
	}

	/**
	 * Frozen-clock counters.
	 */
	private function counters(): Counters {
		return new Counters(
			new MapConfig( array() ),
			null,
			static function () {
				return '2026-09-06T12:00:00Z';
			}
		);
	}

	/**
	 * Hook StatsModule so do_action reaches Counters.
	 *
	 * @param Counters $counters Daily counters.
	 */
	private function register_stats( Counters $counters ): void {
		$module = new StatsModule( $counters, new Events( new MapConfig( array() ) ) );
		$module->register();
	}

	/**
	 * WordPressOrg module with a canned HTTP result and a shared health cache.
	 *
	 * @param callable $request HTTP request.
	 */
	private function module( callable $request ): WordPressOrgModule {
		$transients = array( Origins::STATE_KEY => 'up' );
		$probe      = new MirrorProbe(
			static function () {
				return array();
			},
			static function ( $key ) use ( &$transients ) {
				return $transients[ $key ] ?? false;
			},
			static function ( $key, $value, $ttl = 0 ) use ( &$transients ) {
				unset( $ttl );
				$transients[ $key ] = $value;
				return true;
			}
		);
		$health     = new MirrorHealth(
			static function ( $key ) use ( &$transients ) {
				return $transients[ $key ] ?? false;
			},
			static function ( $key, $value, $ttl = 0 ) use ( &$transients ) {
				unset( $ttl );
				$transients[ $key ] = $value;
				return true;
			}
		);

		return new WordPressOrgModule(
			$probe,
			$request,
			static function () {
				return true;
			},
			$health
		);
	}
}
