<?php
/**
 * Dotted-path get/set, unknown keys, defaults, identity, export.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Defaults;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Config\Schema;

require_once __DIR__ . '/wp-option-stubs.php';

/**
 * Repository unit tests. Does not load WordPress.
 */
class RepositoryTest extends TestCase {

	/**
	 * Captured logger calls.
	 *
	 * @var array<int, array{0: string, 1: string, 2: array<string, mixed>}>
	 */
	private $logs = array();

	/**
	 * Reset option bags and captured logs.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		OptionStore::reset();
		Repository::reset_warning_dedupe();
		$this->logs = array();
	}

	/**
	 * Empty store returns the defaults table.
	 */
	public function test_get_returns_defaults() {
		$repo = $this->repo();
		$this->assertSame( 'cravatar_cn', $repo->get( 'connectivity.avatar' ) );
		$this->assertSame( 'auto', $repo->get( 'connectivity.wordpress_org' ) );
		$this->assertSame( Schema::PUBLIC_ASSETS, $repo->get( 'connectivity.public_assets.items' ) );
		$this->assertSame( 'both', $repo->get( 'connectivity.public_assets.scope' ) );
		$this->assertTrue( $repo->get( 'connectivity.admin_locale_follow' ) );
		$this->assertSame(
			array(
				'enabled'         => 'off',
				'mirrored_base'   => '',
				'native_api_base' => '',
			),
			$repo->get( 'connectivity.icon_photos' )
		);
		$this->assertSame( 'domestic', $repo->get( 'profile' ) );
		$this->assertNull( $repo->get( 'admin_assets' ) );
		$this->assertTrue( $repo->get( 'admin.hide_promo' ) );
		$this->assertTrue( $repo->get( 'modules.notice_control' ) );
		$this->assertFalse( $repo->get( 'modules.windfonts' ) );
		$this->assertTrue( $repo->get( 'modules.site_blocklist.enabled' ) );
		$this->assertSame( array(), $repo->get( 'modules.site_blocklist.hosts' ) );
		$this->assertTrue( $repo->get( 'modules.noise_block.enabled' ) );
		$this->assertTrue( $repo->get( 'diagnostics.scheduled_checks' ) );
		$this->assertSame( 1, $repo->get( 'data_residency.ruleset_version' ) );
		$this->assertNull( $repo->get( 'announcements' ) );
		$this->assertSame( array(), $repo->get( 'apps.disabled' ) );
		$this->assertFalse( $repo->get( 'recovery_mode' ) );
		$this->assertSame( 2, $repo->get( 'schema_version' ) );
		$this->assertSame( Defaults::settings(), $repo->all() );
	}

	/**
	 * Stored site option overlays defaults at the dotted path.
	 */
	public function test_get_dotted_path_from_stored_option() {
		update_option(
			Schema::SETTINGS,
			array(
				'schema_version' => 2,
				'connectivity'   => array(
					'wordpress_org' => 'off',
					'avatar'        => 'off',
				),
				'modules'        => array( 'windfonts' => true ),
			)
		);
		$repo = $this->repo();
		$this->assertSame( 'off', $repo->get( 'connectivity.avatar' ) );
		$this->assertSame( 'off', $repo->get( 'connectivity.wordpress_org' ) );
		$this->assertTrue( $repo->get( 'modules.windfonts' ) );
		$this->assertTrue( $repo->get( 'modules.notice_control' ) );
		$this->assertSame( Schema::PUBLIC_ASSETS, $repo->get( 'connectivity.public_assets.items' ) );
	}

	/**
	 * Set() writes through Validator into wpcy_settings on a single site.
	 */
	public function test_set_dotted_path() {
		$repo = $this->repo();
		$this->assertTrue( $repo->set( 'modules.windfonts', true ) );
		$this->assertTrue( $repo->get( 'modules.windfonts' ) );
		$stored = get_option( Schema::SETTINGS );
		$this->assertIsArray( $stored );
		$this->assertTrue( $stored['modules']['windfonts'] );
		$this->assertArrayNotHasKey( 'wp_china_yes', OptionStore::$options );
	}

	/**
	 * Unknown paths are dropped, logged as warning, and do not throw.
	 */
	public function test_unknown_path_discarded_with_warning() {
		$repo = $this->repo();
		$this->assertNull( $repo->get( 'telemetry.enabled', null ) );
		$this->assertFalse( $repo->set( 'telemetry.enabled', false ) );
		$this->assertFalse( $repo->set( 'not.a.path', 1 ) );
		$this->assertGreaterThanOrEqual( 3, count( $this->logs ) );
		foreach ( $this->logs as $entry ) {
			$this->assertSame( 'warning', $entry[0] );
			$this->assertArrayNotHasKey( 'credential', $entry[2] );
		}
	}

	/**
	 * Unknown keys in a stored option are stripped on read.
	 */
	public function test_unknown_stored_keys_stripped() {
		update_option(
			Schema::SETTINGS,
			array(
				'schema_version' => 2,
				'telemetry'      => true,
				'recovery_mode'  => true,
			)
		);
		$repo = $this->repo();
		$all  = $repo->all();
		$this->assertArrayNotHasKey( 'telemetry', $all );
		$this->assertTrue( $all['recovery_mode'] );
		$this->assertNotEmpty( $this->logs );
	}

	/**
	 * Defaults::get() matches the summary table.
	 */
	public function test_defaults_table() {
		$this->assertSame( 2, Defaults::get( 'schema_version' ) );
		$this->assertSame( 'auto', Defaults::get( 'connectivity.wordpress_org' ) );
		$this->assertSame( Schema::PUBLIC_ASSETS, Defaults::get( 'connectivity.public_assets.items' ) );
		$this->assertSame( 'cravatar_cn', Defaults::get( 'connectivity.avatar' ) );
		$this->assertTrue( Defaults::get( 'connectivity.admin_locale_follow' ) );
		$this->assertSame( 'off', Defaults::get( 'connectivity.icon_photos.enabled' ) );
		$this->assertSame( '', Defaults::get( 'connectivity.icon_photos.mirrored_base' ) );
		$this->assertSame( 'domestic', Defaults::get( 'profile' ) );
		$this->assertNull( Defaults::get( 'admin_assets' ) );
		$this->assertTrue( Defaults::get( 'modules.notice_control' ) );
		$this->assertFalse( Defaults::get( 'modules.windfonts' ) );
		$this->assertTrue( Defaults::get( 'diagnostics.scheduled_checks' ) );
		$this->assertSame( 1, Defaults::get( 'data_residency.ruleset_version' ) );
		$this->assertArrayNotHasKey( 'announcements', Defaults::settings() );
		$this->assertArrayNotHasKey( 'announcements', Defaults::network_settings() );
		$this->assertSame( array(), Defaults::get( 'apps.disabled' ) );
		$this->assertFalse( Defaults::get( 'recovery_mode' ) );
		$this->assertSame( array(), Defaults::get( 'integrations.windfonts.fonts' ) );
		$this->assertNull( Defaults::get( 'allow_site_override' ) );
		$this->assertTrue( Defaults::network_settings()['allow_site_override'] );
	}

	/**
	 * Identity generates a stable UUID and stores credential encrypted.
	 */
	public function test_identity_uuid_and_encrypted_credential() {
		$repo     = $this->repo();
		$identity = $repo->get_identity();
		$this->assertSame( 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', $identity['site_uuid'] );
		$this->assertSame( 'unbound', $identity['binding']['status'] );

		$this->assertTrue( $repo->set_credential( 'plain-secret' ) );
		$stored = get_option( Schema::SITE_IDENTITY );
		$this->assertIsArray( $stored );
		$cipher = $stored['binding']['credential'];
		$this->assertIsString( $cipher );
		$this->assertNotSame( 'plain-secret', $cipher );
		$this->assertSame( 'plain-secret', $repo->get_credential() );

		$exported = $repo->export();
		$this->assertArrayNotHasKey( 'credential', $exported['identity']['binding'] );
		$this->assertSame( 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', $exported['identity']['site_uuid'] );
		$this->assertArrayHasKey( 'settings', $exported );
	}

	/**
	 * Has() is true for schema paths on the effective document, false otherwise.
	 */
	public function test_has_known_and_unknown_paths() {
		$repo = $this->repo();
		$this->assertTrue( $repo->has( 'connectivity.avatar' ) );
		$this->assertFalse( $repo->has( 'nope.nope' ) );
	}

	/**
	 * A present null leaf is distinct from a missing key.
	 */
	public function test_get_present_null_is_not_fallback() {
		$tree = array(
			'x' => null,
		);
		$this->assertTrue( Repository::path_exists( $tree, 'x' ) );
		$this->assertNull( Repository::path_get( $tree, 'x' ) );
		$this->assertFalse( Repository::path_exists( $tree, 'missing' ) );

		$identity = $this->repo()->get_identity();
		$this->assertArrayHasKey( 'credential', $identity['binding'] );
		$this->assertNull( $identity['binding']['credential'] );
	}

	/**
	 * Invalid set() values are sanitized rather than stored raw.
	 */
	public function test_set_invalid_enum_falls_back() {
		$repo = $this->repo();
		$this->assertTrue( $repo->set( 'connectivity.avatar', 'gravatar' ) );
		$this->assertSame( 'cravatar_cn', $repo->get( 'connectivity.avatar' ) );
	}

	/**
	 * A stored 4.0 document that still carries the removed announcements root
	 * (dismissals now live in wpcy_announcements_dismissed) reads fine: the root
	 * is dropped with an unknown-key warning only, and the next save clears it.
	 */
	public function test_legacy_announcements_root_is_ignored_and_cleared_on_save() {
		$legacy                                   = Defaults::settings();
		$legacy['announcements']                  = array( 'dismissed' => array( 'a1' ) );
		OptionStore::$options[ Schema::SETTINGS ] = $legacy;

		$repo = $this->repo();
		$this->assertSame( Defaults::settings(), $repo->all() );
		$this->assertNull( $repo->get( 'announcements.dismissed' ) );
		$this->assertFalse( $repo->set( 'announcements.dismissed', array( 'a2' ) ) );

		$reasons = array();
		foreach ( $this->logs as $log ) {
			if ( isset( $log[2]['path'] ) && 'announcements' === $log[2]['path'] ) {
				$reasons[] = $log[2]['reason'];
			}
		}
		$this->assertNotEmpty( $reasons );
		$this->assertSame( array( 'Unknown key discarded.' ), array_values( array_unique( $reasons ) ) );

		$this->assertTrue( $repo->set( 'connectivity.heartbeat', 'on' ) );
		$this->assertArrayNotHasKey( 'announcements', OptionStore::$options[ Schema::SETTINGS ] );
	}

	/**
	 * One request reads the same stored document many times (and through more
	 * than one Repository); each unknown-key warning is logged once, and
	 * distinct paths are still logged separately.
	 */
	public function test_validator_warning_is_logged_once_per_request() {
		$legacy                                   = Defaults::settings();
		$legacy['announcements']                  = array( 'dismissed' => array( 'a1' ) );
		$legacy['wpcy_bogus_root']                = 'x';
		OptionStore::$options[ Schema::SETTINGS ] = $legacy;

		$repo = $this->repo();
		$repo->all();
		$repo->all();
		$repo->get( 'profile' );
		$this->repo()->all();

		$this->assertSame( 1, $this->warning_count( 'announcements' ) );
		$this->assertSame( 1, $this->warning_count( 'wpcy_bogus_root' ) );
	}

	/**
	 * After reset_warning_dedupe() the same warning is logged again.
	 */
	public function test_reset_warning_dedupe_logs_again() {
		$legacy                                   = Defaults::settings();
		$legacy['announcements']                  = array( 'dismissed' => array( 'a1' ) );
		OptionStore::$options[ Schema::SETTINGS ] = $legacy;

		$repo = $this->repo();
		$repo->all();
		$repo->all();
		$this->assertSame( 1, $this->warning_count( 'announcements' ) );

		Repository::reset_warning_dedupe();
		$repo->all();
		$this->assertSame( 2, $this->warning_count( 'announcements' ) );
	}

	/**
	 * Multisite: a network document with the removed root reads fine and loses it on save.
	 */
	public function test_legacy_announcements_root_in_network_document_is_cleared_on_save() {
		OptionStore::$multisite  = true;
		$legacy                  = Defaults::network_settings();
		$legacy['announcements'] = array( 'dismissed' => array( 'a1' ) );
		OptionStore::$site_options[ Schema::NETWORK_SETTINGS ] = $legacy;

		$repo = $this->repo();
		$this->assertNull( $repo->get( 'announcements' ) );
		$this->assertTrue( $repo->save_option( Schema::NETWORK_SETTINGS, $legacy ) );
		$this->assertSame( Defaults::network_settings(), OptionStore::$site_options[ Schema::NETWORK_SETTINGS ] );
	}

	/**
	 * Logged Validator warnings for one dotted path.
	 *
	 * @param string $path Dotted path.
	 * @return int
	 */
	private function warning_count( string $path ): int {
		$count = 0;
		foreach ( $this->logs as $log ) {
			if ( isset( $log[2]['path'] ) && $path === $log[2]['path'] && isset( $log[2]['reason'] ) ) {
				++$count;
			}
		}
		return $count;
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
