<?php
/**
 * L2 site blocklist: reject L0 on save, skip at runtime, 20-cap, suffix, enabled gate.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Privacy;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Repository as ConfigRepository;
use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Privacy\DataResidency\Ruleset;
use WenPai\ChinaYes\Privacy\SiteBlocklist\Repository;
use WenPai\ChinaYes\Privacy\SiteBlocklist\SiteBlocklistModule;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WP_Error;

require_once dirname( __DIR__ ) . '/Config/wp-option-stubs.php';
require_once __DIR__ . '/wp-http-stubs.php';

/**
 * SiteBlocklist save and runtime behaviour.
 */
class SiteBlocklistTest extends TestCase {

	/**
	 * Reset option bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		OptionStore::reset();
	}

	/**
	 * Saving api.wenpai.net / wpcy.com fails with 文派服务不可拦截; option unchanged.
	 */
	public function test_save_rejects_protected_hosts() {
		$config = new ConfigRepository();
		$list   = new Repository( $config, $this->unsigned_ruleset() );

		foreach ( array( 'api.wenpai.net', 'wpcy.com' ) as $host ) {
			$before = isset( OptionStore::$options[ Schema::SETTINGS ] ) ? OptionStore::$options[ Schema::SETTINGS ] : null;
			$result = $list->save(
				array(
					'enabled' => true,
					'hosts'   => array(
						array(
							'host'  => $host,
							'match' => 'exact',
						),
					),
				)
			);
			$this->assertInstanceOf( WP_Error::class, $result );
			$this->assertSame( 'wpcy_blocklist_protected_host', $result->get_error_code() );
			$this->assertStringContainsString( '文派服务不可拦截', $result->get_error_message() );
			$after = isset( OptionStore::$options[ Schema::SETTINGS ] ) ? OptionStore::$options[ Schema::SETTINGS ] : null;
			$this->assertSame( $before, $after );
		}
	}

	/**
	 * Stored L0 rows are ignored at runtime: pre_http_request does not return Error.
	 */
	public function test_runtime_ignores_protected_rows() {
		OptionStore::$options[ Schema::SETTINGS ] = array(
			'schema_version' => 2,
			'modules'        => array(
				'site_blocklist' => array(
					'enabled' => true,
					'hosts'   => array(
						array(
							'host'  => 'api.wenpai.net',
							'match' => 'exact',
							'note'  => '',
						),
						array(
							'host'  => 'tracker.example.com',
							'match' => 'exact',
							'note'  => '',
						),
					),
				),
			),
		);

		$module = new SiteBlocklistModule( new ConfigRepository(), null, $this->unsigned_ruleset() );
		$l0     = $module->filter_pre_http_request( false, array(), 'https://api.wenpai.net/v1' );
		$this->assertFalse( $l0 );

		$blocked = $module->filter_pre_http_request( false, array(), 'https://tracker.example.com/v1' );
		$this->assertInstanceOf( WP_Error::class, $blocked );
		$this->assertSame( 'wpcy_site_blocklist_blocked', $blocked->get_error_code() );
	}

	/**
	 * Twenty-one rows return wpcy_invalid_schema 400 and are not written.
	 */
	public function test_max_twenty_hosts_rejected() {
		$hosts = array();
		for ( $i = 1; $i <= 21; $i++ ) {
			$hosts[] = array(
				'host'  => sprintf( 'host-%02d.example.com', $i ),
				'match' => 'exact',
			);
		}

		$list   = new Repository( new ConfigRepository(), $this->unsigned_ruleset() );
		$before = isset( OptionStore::$options[ Schema::SETTINGS ] ) ? OptionStore::$options[ Schema::SETTINGS ] : null;
		$result = $list->save(
			array(
				'enabled' => true,
				'hosts'   => $hosts,
			)
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_invalid_schema', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
		$after = isset( OptionStore::$options[ Schema::SETTINGS ] ) ? OptionStore::$options[ Schema::SETTINGS ] : null;
		$this->assertSame( $before, $after );
	}

	/**
	 * Suffix matches a.example.com and not notexample.com.
	 */
	public function test_suffix_matches_subdomain_not_lookalike() {
		OptionStore::$options[ Schema::SETTINGS ] = array(
			'schema_version' => 2,
			'modules'        => array(
				'site_blocklist' => array(
					'enabled' => true,
					'hosts'   => array(
						array(
							'host'  => 'example.com',
							'match' => 'suffix',
							'note'  => '',
						),
					),
				),
			),
		);

		$module = new SiteBlocklistModule( new ConfigRepository(), null, $this->unsigned_ruleset() );
		$hit    = $module->filter_pre_http_request( false, array(), 'https://a.example.com/x' );
		$this->assertInstanceOf( WP_Error::class, $hit );

		$apex = $module->filter_pre_http_request( false, array(), 'https://example.com/x' );
		$this->assertInstanceOf( WP_Error::class, $apex );

		$miss = $module->filter_pre_http_request( false, array(), 'https://notexample.com/x' );
		$this->assertFalse( $miss );
	}

	/**
	 * Disabled module does not hook.
	 */
	public function test_enabled_false_does_not_register() {
		$config = new ConfigRepository();
		$module = new SiteBlocklistModule( $config, null, $this->unsigned_ruleset() );
		$env    = Environment::detect();

		$this->assertTrue( $module->enabled( $config, $env ) );

		OptionStore::$options[ Schema::SETTINGS ] = array(
			'schema_version' => 2,
			'modules'        => array(
				'site_blocklist' => array(
					'enabled' => false,
					'hosts'   => array(),
				),
			),
		);
		$config                                   = new ConfigRepository();
		$module                                   = new SiteBlocklistModule( $config, null, $this->unsigned_ruleset() );
		$this->assertFalse( $module->enabled( $config, $env ) );
	}

	/**
	 * Unsigned baseline.
	 */
	private function unsigned_ruleset(): Ruleset {
		return new Ruleset( null, null, false );
	}
}
