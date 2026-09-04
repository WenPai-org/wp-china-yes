<?php
/**
 * Multisite merge: defaults ← network ← (optional) site overrides.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Config\Schema;

require_once __DIR__ . '/wp-option-stubs.php';

/**
 * Read-order tests. Does not load WordPress.
 */
class MultisiteReadOrderTest extends TestCase {

	/**
	 * Captured logger calls.
	 *
	 * @var array<int, array{0: string, 1: string, 2: array<string, mixed>}>
	 */
	private $logs = array();

	/**
	 * Enable the multisite stub and reset option bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		OptionStore::reset();
		OptionStore::$multisite = true;
		$this->logs             = array();
	}

	/**
	 * With no stored options, network defaults apply (including allow_site_override).
	 */
	public function test_defaults_when_network_option_empty() {
		$repo = $this->repo();
		$this->assertSame( 'cravatar_cn', $repo->get( 'connectivity.avatar' ) );
		$this->assertTrue( $repo->get( 'allow_site_override' ) );
		$this->assertFalse( $repo->get( 'modules.windfonts' ) );
	}

	/**
	 * Network option overlays defaults.
	 */
	public function test_network_overlays_defaults() {
		update_site_option(
			Schema::NETWORK_SETTINGS,
			array(
				'schema_version'      => 1,
				'connectivity'        => array( 'avatar' => 'cravatar_global' ),
				'allow_site_override' => true,
			)
		);
		$repo = $this->repo();
		$this->assertSame( 'cravatar_global', $repo->get( 'connectivity.avatar' ) );
		$this->assertSame( 'auto', $repo->get( 'connectivity.wordpress_org' ) );
		$this->assertTrue( $repo->get( 'modules.notice_control' ) );
	}

	/**
	 * Site overrides win when allow_site_override is true.
	 */
	public function test_site_overrides_when_allowed() {
		update_site_option(
			Schema::NETWORK_SETTINGS,
			array(
				'schema_version'      => 1,
				'connectivity'        => array(
					'avatar'        => 'cravatar_global',
					'wordpress_org' => 'off',
				),
				'modules'             => array( 'windfonts' => false ),
				'allow_site_override' => true,
			)
		);
		update_option(
			Schema::SITE_OVERRIDES,
			array(
				'schema_version' => 1,
				'connectivity'   => array( 'avatar' => 'off' ),
				'modules'        => array( 'windfonts' => true ),
			)
		);
		$repo = $this->repo();
		$this->assertSame( 'off', $repo->get( 'connectivity.avatar' ) );
		$this->assertSame( 'off', $repo->get( 'connectivity.wordpress_org' ) );
		$this->assertTrue( $repo->get( 'modules.windfonts' ) );
	}

	/**
	 * Existing overrides are ignored when allow_site_override is false.
	 */
	public function test_overrides_ignored_when_disallowed() {
		update_site_option(
			Schema::NETWORK_SETTINGS,
			array(
				'schema_version'      => 1,
				'connectivity'        => array( 'avatar' => 'cravatar_global' ),
				'allow_site_override' => false,
			)
		);
		update_option(
			Schema::SITE_OVERRIDES,
			array(
				'schema_version' => 1,
				'connectivity'   => array( 'avatar' => 'off' ),
			)
		);
		$repo = $this->repo();
		$this->assertSame( 'cravatar_global', $repo->get( 'connectivity.avatar' ) );
	}

	/**
	 * Set() of connectivity/modules writes site overrides, not the network option.
	 */
	public function test_set_writes_overrides_when_allowed() {
		update_site_option(
			Schema::NETWORK_SETTINGS,
			array(
				'schema_version'      => 1,
				'allow_site_override' => true,
			)
		);
		$repo = $this->repo();
		$this->assertTrue( $repo->set( 'modules.windfonts', true ) );
		$this->assertTrue( $repo->get( 'modules.windfonts' ) );
		$overrides = get_option( Schema::SITE_OVERRIDES );
		$this->assertIsArray( $overrides );
		$this->assertTrue( $overrides['modules']['windfonts'] );
		$network = get_site_option( Schema::NETWORK_SETTINGS );
		$this->assertIsArray( $network );
		$this->assertTrue( empty( $network['modules']['windfonts'] ) );
	}

	/**
	 * Set() of connectivity/modules is refused when allow_site_override is false.
	 */
	public function test_set_override_refused_when_disallowed() {
		update_site_option(
			Schema::NETWORK_SETTINGS,
			array(
				'schema_version'      => 1,
				'allow_site_override' => false,
			)
		);
		$repo = $this->repo();
		$this->assertFalse( $repo->set( 'modules.windfonts', true ) );
		$this->assertFalse( $repo->get( 'modules.windfonts' ) );
		$this->assertFalse( get_option( Schema::SITE_OVERRIDES, false ) );
		$this->assertNotEmpty( $this->logs );
	}

	/**
	 * Non-override keys on multisite write the network option.
	 */
	public function test_set_network_key() {
		$repo = $this->repo();
		$this->assertTrue( $repo->set( 'diagnostics.scheduled_checks', false ) );
		$this->assertFalse( $repo->get( 'diagnostics.scheduled_checks' ) );
		$network = get_site_option( Schema::NETWORK_SETTINGS );
		$this->assertFalse( $network['diagnostics']['scheduled_checks'] );
		$this->assertArrayNotHasKey( Schema::SETTINGS, OptionStore::$options );
	}

	/**
	 * Site recovery_mode writes wpcy_site_overrides and leaves the network option false.
	 */
	public function test_site_recovery_mode_does_not_write_network_option() {
		update_site_option(
			Schema::NETWORK_SETTINGS,
			array(
				'schema_version'      => 1,
				'allow_site_override' => true,
				'recovery_mode'       => false,
			)
		);
		$repo = $this->repo();
		$this->assertTrue( $repo->set( 'recovery_mode', true ) );
		$this->assertTrue( $repo->get( 'recovery_mode' ) );
		$overrides = get_option( Schema::SITE_OVERRIDES );
		$this->assertIsArray( $overrides );
		$this->assertTrue( $overrides['recovery_mode'] );
		$network = get_site_option( Schema::NETWORK_SETTINGS );
		$this->assertFalse( $network['recovery_mode'] );
		$this->assertArrayNotHasKey( Schema::SETTINGS, OptionStore::$options );
	}

	/**
	 * Recovery_mode remains site-scoped when allow_site_override is false.
	 */
	public function test_site_recovery_mode_writes_when_overrides_disallowed() {
		update_site_option(
			Schema::NETWORK_SETTINGS,
			array(
				'schema_version'      => 1,
				'allow_site_override' => false,
				'recovery_mode'       => false,
			)
		);
		$repo = $this->repo();
		$this->assertTrue( $repo->set( 'recovery_mode', true ) );
		$this->assertTrue( $repo->get( 'recovery_mode' ) );
		$overrides = get_option( Schema::SITE_OVERRIDES );
		$this->assertIsArray( $overrides );
		$this->assertTrue( $overrides['recovery_mode'] );
		$network = get_site_option( Schema::NETWORK_SETTINGS );
		$this->assertFalse( $network['recovery_mode'] );
	}

	/**
	 * Partial override of connectivity does not wipe sibling keys.
	 */
	public function test_partial_connectivity_override() {
		update_site_option(
			Schema::NETWORK_SETTINGS,
			array(
				'schema_version'      => 1,
				'connectivity'        => array(
					'wordpress_org' => 'off',
					'avatar'        => 'weavatar',
				),
				'allow_site_override' => true,
			)
		);
		update_option(
			Schema::SITE_OVERRIDES,
			array(
				'connectivity' => array( 'avatar' => 'off' ),
			)
		);
		$repo = $this->repo();
		$this->assertSame( 'off', $repo->get( 'connectivity.avatar' ) );
		$this->assertSame( 'off', $repo->get( 'connectivity.wordpress_org' ) );
		$this->assertSame( Schema::PUBLIC_ASSETS, $repo->get( 'connectivity.public_assets' ) );
	}

	/**
	 * Repository wired to the in-test logger.
	 *
	 * @return Repository
	 */
	private function repo(): Repository {
		$logs = &$this->logs;
		return new Repository(
			static function ( string $level, string $message, array $context = array() ) use ( &$logs ) {
				$logs[] = array( $level, $message, $context );
			}
		);
	}
}
