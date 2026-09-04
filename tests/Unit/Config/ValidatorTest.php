<?php
/**
 * Field-by-field checks against docs/specs/config-schema.md.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Config\Validator;

/**
 * Validator: enum / required / additionalProperties: false / unknown keys.
 */
class ValidatorTest extends TestCase {

	/**
	 * Subject under test.
	 *
	 * @var Validator
	 */
	private $validator;

	/**
	 * Create a fresh Validator.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->validator = new Validator();
	}

	/**
	 * Empty input is filled with required keys and schema defaults.
	 */
	public function test_settings_fills_required_defaults() {
		$out = $this->validator->sanitize( array(), Schema::SETTINGS );

		$this->assertSame( 1, $out['schema_version'] );
		$this->assertSame( 'auto', $out['connectivity']['wordpress_org'] );
		$this->assertSame( Schema::PUBLIC_ASSETS, $out['connectivity']['public_assets'] );
		$this->assertSame( 'cravatar_cn', $out['connectivity']['avatar'] );
		$this->assertTrue( $out['modules']['notice_control'] );
		$this->assertFalse( $out['modules']['windfonts'] );
		$this->assertTrue( $out['diagnostics']['scheduled_checks'] );
		$this->assertSame( 1, $out['data_residency']['ruleset_version'] );
		$this->assertSame( array(), $out['announcements']['dismissed'] );
		$this->assertSame( array(), $out['apps']['disabled'] );
		$this->assertFalse( $out['recovery_mode'] );
		foreach ( Schema::settings()['required'] as $key ) {
			$this->assertArrayHasKey( $key, $out );
		}
	}

	/**
	 * Schema_version is integer const 1.
	 */
	public function test_schema_version_const() {
		$out = $this->validator->sanitize( array( 'schema_version' => 2 ), Schema::SETTINGS );
		$this->assertSame( 1, $out['schema_version'] );
		$this->assertNotEmpty( $this->validator->warnings() );
	}

	/**
	 * Wordpress_org enum is auto|off.
	 */
	public function test_wordpress_org_enum() {
		$ok = $this->validator->sanitize(
			array( 'connectivity' => array( 'wordpress_org' => 'off' ) ),
			Schema::SETTINGS
		);
		$this->assertSame( 'off', $ok['connectivity']['wordpress_org'] );

		$bad = $this->validator->sanitize(
			array( 'connectivity' => array( 'wordpress_org' => 'mirror' ) ),
			Schema::SETTINGS
		);
		$this->assertSame( 'auto', $bad['connectivity']['wordpress_org'] );
	}

	/**
	 * Public_assets is a unique subset; unknown strings are dropped.
	 */
	public function test_public_assets_subset_drops_unknown() {
		$out = $this->validator->sanitize(
			array(
				'connectivity' => array(
					'public_assets' => array( 'google_fonts', 'not-a-cdn', 'emoji', 'google_fonts' ),
				),
			),
			Schema::SETTINGS
		);
		$this->assertSame( array( 'google_fonts', 'emoji' ), $out['connectivity']['public_assets'] );
		$this->assertTrue( $this->has_warning_path( 'connectivity.public_assets' ) );
	}

	/**
	 * Avatar enum includes weavatar (spec; task book omitted it).
	 */
	public function test_avatar_enum() {
		foreach ( Schema::AVATAR as $mode ) {
			$out = $this->validator->sanitize(
				array( 'connectivity' => array( 'avatar' => $mode ) ),
				Schema::SETTINGS
			);
			$this->assertSame( $mode, $out['connectivity']['avatar'] );
		}
		$bad = $this->validator->sanitize(
			array( 'connectivity' => array( 'avatar' => 'gravatar' ) ),
			Schema::SETTINGS
		);
		$this->assertSame( 'cravatar_cn', $bad['connectivity']['avatar'] );
	}

	/**
	 * Modules notice_control and windfonts are booleans.
	 */
	public function test_modules_booleans() {
		$out = $this->validator->sanitize(
			array(
				'modules' => array(
					'notice_control' => false,
					'windfonts'      => true,
				),
			),
			Schema::SETTINGS
		);
		$this->assertFalse( $out['modules']['notice_control'] );
		$this->assertTrue( $out['modules']['windfonts'] );

		$bad = $this->validator->sanitize(
			array( 'modules' => array( 'notice_control' => 'yes' ) ),
			Schema::SETTINGS
		);
		$this->assertTrue( $bad['modules']['notice_control'] );
	}

	/**
	 * Diagnostics scheduled_checks is bool, default true.
	 */
	public function test_diagnostics_scheduled_checks() {
		$out = $this->validator->sanitize(
			array( 'diagnostics' => array( 'scheduled_checks' => false ) ),
			Schema::SETTINGS
		);
		$this->assertFalse( $out['diagnostics']['scheduled_checks'] );
	}

	/**
	 * Data_residency ruleset_version is integer >= 1.
	 */
	public function test_ruleset_version_minimum() {
		$out = $this->validator->sanitize(
			array( 'data_residency' => array( 'ruleset_version' => 0 ) ),
			Schema::SETTINGS
		);
		$this->assertSame( 1, $out['data_residency']['ruleset_version'] );
	}

	/**
	 * Announcements dismissed is string[] max 100, item length 1–128.
	 */
	public function test_announcements_dismissed() {
		$ids = array();
		for ( $i = 0; $i < 101; $i++ ) {
			$ids[] = 'id-' . $i;
		}
		$out = $this->validator->sanitize(
			array(
				'announcements' => array(
					'dismissed' => array_merge( $ids, array( '', str_repeat( 'a', 129 ) ) ),
				),
			),
			Schema::SETTINGS
		);
		$this->assertCount( 100, $out['announcements']['dismissed'] );
		$this->assertNotContains( '', $out['announcements']['dismissed'] );
	}

	/**
	 * Apps disabled is string[] with item length 1–64.
	 */
	public function test_apps_disabled() {
		$out = $this->validator->sanitize(
			array( 'apps' => array( 'disabled' => array( 'motusnap', '', str_repeat( 'x', 65 ) ) ) ),
			Schema::SETTINGS
		);
		$this->assertSame( array( 'motusnap' ), $out['apps']['disabled'] );
	}

	/**
	 * Recovery_mode is bool, default false.
	 */
	public function test_recovery_mode() {
		$out = $this->validator->sanitize( array( 'recovery_mode' => true ), Schema::SETTINGS );
		$this->assertTrue( $out['recovery_mode'] );
	}

	/**
	 * Unknown top-level keys are dropped (no telemetry).
	 */
	public function test_unknown_keys_discarded() {
		$out = $this->validator->sanitize(
			array(
				'telemetry'     => false,
				'recovery_mode' => true,
				'not_a_field'   => 1,
			),
			Schema::SETTINGS
		);
		$this->assertArrayNotHasKey( 'telemetry', $out );
		$this->assertArrayNotHasKey( 'not_a_field', $out );
		$this->assertTrue( $out['recovery_mode'] );
		$this->assertTrue( $this->has_warning_path( 'telemetry' ) );
		$this->assertTrue( $this->has_warning_path( 'not_a_field' ) );
	}

	/**
	 * Nested additionalProperties: false.
	 */
	public function test_nested_unknown_keys_discarded() {
		$out = $this->validator->sanitize(
			array(
				'modules' => array(
					'notice_control' => true,
					'adblock'        => true,
				),
			),
			Schema::SETTINGS
		);
		$this->assertArrayNotHasKey( 'adblock', $out['modules'] );
		$this->assertTrue( $this->has_warning_path( 'modules.adblock' ) );
	}

	/**
	 * Network settings add allow_site_override, default true.
	 */
	public function test_network_allow_site_override() {
		$out = $this->validator->sanitize( array(), Schema::NETWORK_SETTINGS );
		$this->assertTrue( $out['allow_site_override'] );
		$out = $this->validator->sanitize( array( 'allow_site_override' => false ), Schema::NETWORK_SETTINGS );
		$this->assertFalse( $out['allow_site_override'] );
	}

	/**
	 * Site overrides keep schema_version, connectivity, modules, recovery_mode.
	 */
	public function test_site_overrides_only_allowed_keys() {
		$out = $this->validator->sanitize(
			array(
				'connectivity'  => array( 'avatar' => 'off' ),
				'recovery_mode' => true,
				'diagnostics'   => array( 'scheduled_checks' => false ),
			),
			Schema::SITE_OVERRIDES
		);
		$this->assertArrayHasKey( 'connectivity', $out );
		$this->assertSame( 'off', $out['connectivity']['avatar'] );
		$this->assertTrue( $out['recovery_mode'] );
		$this->assertArrayNotHasKey( 'diagnostics', $out );
		$this->assertArrayNotHasKey( 'wordpress_org', $out['connectivity'] );
	}

	/**
	 * Site identity binding.status enum and required keys.
	 */
	public function test_site_identity() {
		$out = $this->validator->sanitize( array(), Schema::SITE_IDENTITY );
		$this->assertSame( 1, $out['schema_version'] );
		$this->assertSame( 'unbound', $out['binding']['status'] );
		$this->assertNull( $out['binding']['credential'] );

		$out = $this->validator->sanitize(
			array(
				'site_uuid' => 'not-a-uuid',
				'binding'   => array( 'status' => 'bound' ),
			),
			Schema::SITE_IDENTITY
		);
		$this->assertSame( '', $out['site_uuid'] );
		$this->assertSame( 'bound', $out['binding']['status'] );

		$out = $this->validator->sanitize(
			array( 'binding' => array( 'status' => 'unknown' ) ),
			Schema::SITE_IDENTITY
		);
		$this->assertSame( 'unbound', $out['binding']['status'] );
	}

	/**
	 * Migration backup required keys (structure only; this task does not migrate).
	 */
	public function test_migration_backup_schema() {
		$out = $this->validator->sanitize(
			array(
				'from_version'   => '3.9.3',
				'migrated_at'    => '2026-09-04T00:00:00Z',
				'legacy_hash'    => 'abc',
				'ignored_fields' => array( 'telemetry' ),
				'secret'         => 'nope',
			),
			Schema::MIGRATION_BACKUP
		);
		$this->assertSame( 1, $out['schema_version'] );
		$this->assertSame( '3.9.3', $out['from_version'] );
		$this->assertSame( array( 'telemetry' ), $out['ignored_fields'] );
		$this->assertArrayNotHasKey( 'secret', $out );
	}

	/**
	 * Integrations windfonts fonts items (spec; not in the task-book field list).
	 */
	public function test_integrations_windfonts_fonts() {
		$out = $this->validator->sanitize(
			array(
				'integrations' => array(
					'windfonts' => array(
						'fonts' => array(
							array(
								'family'   => 'lxgw-wenkai',
								'subset'   => 'zh',
								'selector' => 'body',
								'enable'   => false,
							),
							array(
								'family'   => 'Bad Family',
								'selector' => 'h1',
							),
						),
					),
				),
			),
			Schema::SETTINGS
		);
		$this->assertCount( 1, $out['integrations']['windfonts']['fonts'] );
		$this->assertSame( 'lxgw-wenkai', $out['integrations']['windfonts']['fonts'][0]['family'] );
		$this->assertSame( 'zh', $out['integrations']['windfonts']['fonts'][0]['subset'] );
	}

	/**
	 * Five option keys are registered; no extras.
	 */
	public function test_schema_option_names() {
		$this->assertTrue( Schema::is_known_option( Schema::SETTINGS ) );
		$this->assertTrue( Schema::is_known_option( Schema::NETWORK_SETTINGS ) );
		$this->assertTrue( Schema::is_known_option( Schema::SITE_OVERRIDES ) );
		$this->assertTrue( Schema::is_known_option( Schema::SITE_IDENTITY ) );
		$this->assertTrue( Schema::is_known_option( Schema::MIGRATION_BACKUP ) );
		$this->assertFalse( Schema::is_known_option( 'wp_china_yes' ) );
		$this->assertFalse( Schema::is_known_option( 'wpcy_v4_settings' ) );
		$settings = Schema::settings();
		$this->assertArrayHasKey( 'integrations', $settings['properties'] );
		$this->assertArrayNotHasKey( 'telemetry', $settings['properties'] );
	}

	/**
	 * Non-object input is treated as empty.
	 */
	public function test_non_object_input() {
		$out = $this->validator->sanitize( 'nope', Schema::SETTINGS );
		$this->assertIsArray( $out );
		$this->assertSame( 1, $out['schema_version'] );
	}

	/**
	 * Whether a warning was recorded for $path or a child of it.
	 *
	 * @param string $path Warning path.
	 * @return bool
	 */
	private function has_warning_path( string $path ): bool {
		foreach ( $this->validator->warnings() as $warning ) {
			if ( $warning['path'] === $path || 0 === strpos( $warning['path'], $path ) ) {
				return true;
			}
		}
		return false;
	}
}
