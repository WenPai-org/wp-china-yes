<?php
/**
 * Filter order: L0 then L1 then noise then L2. Later layers cannot override earlier ones.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Privacy;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Repository as ConfigRepository;
use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Privacy\DataResidency\DataResidencyModule;
use WenPai\ChinaYes\Privacy\DataResidency\Ruleset;
use WenPai\ChinaYes\Privacy\SiteBlocklist\SiteBlocklistModule;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WP_Error;

require_once dirname( __DIR__ ) . '/Config/wp-option-stubs.php';
require_once __DIR__ . '/wp-http-stubs.php';

/**
 * Same URL through L0 / L1 / noise / L2.
 */
class LayerOrderTest extends TestCase {

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		OptionStore::reset();
		$GLOBALS['wpcy_privacy_remote_urls'] = array();
		$GLOBALS['wpcy_privacy_remote_args'] = array();
	}

	/**
	 * L0 allow beats L2 even when the host is on the site list.
	 */
	public function test_l0_allow_overrides_l2() {
		$this->seed_l2( array( 'api.wenpai.net' ) );
		$l2 = $this->l2();
		$l1 = $this->l1( true );

		$url = 'https://api.wenpai.net/v1';
		$out = $l1->filter_l0( false, array(), $url );
		$out = $l1->filter_pre_http_request( $out, array(), $url );
		$out = $l1->filter_noise_block( $out, array(), $url );
		$out = $l2->filter_pre_http_request( $out, array(), $url );

		$this->assertFalse( $out );
		$this->assertSame( array(), $GLOBALS['wpcy_privacy_remote_urls'] );
		$this->assertArrayNotHasKey( 'api.wenpai.net', $l1->log() );
	}

	/**
	 * L1 A reroute beats L2.
	 */
	public function test_l1_reroute_overrides_l2() {
		$this->seed_l2( array( 'tracking.woocommerce.com' ) );
		$l2 = $this->l2();
		$l1 = $this->l1( true );

		$url = 'https://tracking.woocommerce.com/v1/track';
		$out = $l1->filter_l0( false, array(), $url );
		$out = $l1->filter_pre_http_request( $out, array(), $url );
		$out = $l1->filter_noise_block( $out, array(), $url );
		$out = $l2->filter_pre_http_request( $out, array(), $url );

		$this->assertIsArray( $out );
		$this->assertSame(
			array( 'https://updates.wenpai.net/ingest/woo-tracker' ),
			$GLOBALS['wpcy_privacy_remote_urls']
		);
	}

	/**
	 * L1 C ignore is not a terminal allow; L2 may still block.
	 */
	public function test_l1_c_ignore_then_l2_may_block() {
		$this->seed_l2( array( 'payments.example.com' ) );
		$l2 = $this->l2();
		$l1 = $this->l1( false );

		$url = 'https://payments.example.com/charge';
		$out = $l1->filter_l0( false, array(), $url );
		$out = $l1->filter_pre_http_request( $out, array(), $url );
		$out = $l1->filter_noise_block( $out, array(), $url );
		$out = $l2->filter_pre_http_request( $out, array(), $url );

		$this->assertInstanceOf( WP_Error::class, $out );
		$this->assertSame( 'wpcy_site_blocklist_blocked', $out->get_error_code() );
	}

	/**
	 * Noise pack does not block wenpai.net (L0).
	 */
	public function test_noise_does_not_block_wenpai_net() {
		$path = $this->write_ruleset_with_noise( 'api.wenpai.net' );
		try {
			$ruleset = new Ruleset( $path, null, false );
			$config  = new ConfigRepository();
			$l1      = new DataResidencyModule( $ruleset, false, $config );

			$out = $l1->filter_l0( false, array(), 'https://api.wenpai.net/v1' );
			$out = $l1->filter_pre_http_request( $out, array(), 'https://api.wenpai.net/v1' );
			$out = $l1->filter_noise_block( $out, array(), 'https://api.wenpai.net/v1' );

			$this->assertFalse( $out );
		} finally {
			unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- temp ruleset.
		}
	}

	/**
	 * Seed L2 hosts on wpcy_settings.
	 *
	 * @param array<int, string> $hosts Hosts.
	 */
	private function seed_l2( array $hosts ): void {
		$rows = array();
		foreach ( $hosts as $host ) {
			$rows[] = array(
				'host'  => $host,
				'match' => 'exact',
				'note'  => '',
			);
		}
		OptionStore::$options[ Schema::SETTINGS ] = array(
			'schema_version' => 2,
			'modules'        => array(
				'site_blocklist' => array(
					'enabled' => true,
					'hosts'   => $rows,
				),
				'noise_block'    => array(
					'enabled' => true,
				),
			),
		);
	}

	/**
	 * L2 module.
	 */
	private function l2(): SiteBlocklistModule {
		return new SiteBlocklistModule( new ConfigRepository(), null, $this->unsigned_ruleset() );
	}

	/**
	 * L1 module.
	 *
	 * @param bool $ingest Ingest probe.
	 */
	private function l1( bool $ingest ): DataResidencyModule {
		return new DataResidencyModule( $this->unsigned_ruleset(), $ingest, new ConfigRepository() );
	}

	/**
	 * Unsigned baseline.
	 */
	private function unsigned_ruleset(): Ruleset {
		return new Ruleset( null, null, false );
	}

	/**
	 * Unsigned ruleset with one noise row.
	 *
	 * @param string $host Noise host.
	 */
	private function write_ruleset_with_noise( string $host ): string {
		$payload = array(
			'issued_at'       => '2026-09-06T00:00:00Z',
			'ruleset_version' => 1,
			'tiers'           => array(
				'C' => array(
					array(
						'action' => 'ignore',
						'host'   => '*',
					),
				),
			),
			'protected_hosts' => array(),
			'noise_block'     => array(
				array(
					'host'  => $host,
					'match' => 'exact',
				),
			),
		);
		$tmp     = tempnam( sys_get_temp_dir(), 'wpcy-lo-' );
		$this->assertNotFalse( $tmp );
		file_put_contents( $tmp, json_encode( $payload ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents,WordPress.WP.AlternativeFunctions.json_encode_json_encode -- temp unsigned JSON.
		return $tmp;
	}
}
