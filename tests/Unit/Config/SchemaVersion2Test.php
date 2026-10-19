<?php
/**
 * Upgrade_1_to_2: v1 shapes become v2; already-v2 documents stay put.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Config\SchemaMigrator;

require_once __DIR__ . '/wp-option-stubs.php';

/**
 * Schema v2 upgrade steps from config-schema.md.
 */
class SchemaVersion2Test extends TestCase {

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
	 * Const is 2. Identity and backup stay 1.
	 */
	public function test_schema_version_const_is_two() {
		$this->assertSame( 2, Schema::VERSION );
		$this->assertSame( 1, Schema::IDENTITY_VERSION );
		$this->assertSame( 1, Schema::BACKUP_VERSION );
		$this->assertSame( 1, Schema::site_identity()['properties']['schema_version']['const'] );
		$this->assertSame( 1, Schema::migration_backup()['properties']['schema_version']['const'] );
	}

	/**
	 * Array public_assets becomes {items, scope=both}; items kept, not refilled.
	 */
	public function test_public_assets_array_becomes_object() {
		$out = SchemaMigrator::upgrade_1_to_2(
			array(
				'schema_version' => 1,
				'connectivity'   => array(
					'public_assets' => array( 'google_fonts', 'emoji' ),
					'avatar'        => 'cravatar_cn',
				),
			)
		);

		$this->assertSame( 2, $out['schema_version'] );
		$this->assertSame( array( 'google_fonts', 'emoji' ), $out['connectivity']['public_assets']['items'] );
		$this->assertSame( 'both', $out['connectivity']['public_assets']['scope'] );
	}

	/**
	 * String avatar stays a single enum.
	 */
	public function test_avatar_string_stays_single() {
		$out = SchemaMigrator::upgrade_1_to_2(
			array(
				'schema_version' => 1,
				'connectivity'   => array(
					'avatar' => 'cravatar_global',
				),
			)
		);

		$this->assertSame( 'cravatar_global', $out['connectivity']['avatar'] );
	}

	/**
	 * Stored v1 weavatar string becomes cravatar_cn.
	 */
	public function test_weavatar_string_becomes_cravatar_cn() {
		$out = SchemaMigrator::upgrade_1_to_2(
			array(
				'schema_version' => 1,
				'connectivity'   => array(
					'avatar' => 'weavatar',
				),
			)
		);

		$this->assertSame( 'cravatar_cn', $out['connectivity']['avatar'] );
	}

	/**
	 * Split v2 avatar object collapses to one live line.
	 */
	public function test_split_avatar_object_collapses() {
		$out = SchemaMigrator::upgrade_1_to_2(
			array(
				'schema_version' => 2,
				'connectivity'   => array(
					'avatar' => array(
						'admin'    => 'cravatar_cn',
						'frontend' => 'off',
					),
				),
			)
		);

		$this->assertSame( 'cravatar_cn', $out['connectivity']['avatar'] );
		$this->assertArrayNotHasKey( 'admin_assets', $out );
	}

	/**
	 * Missing profile / heartbeat / dashboard_feeds / client_probe_url fill domestic defaults.
	 */
	public function test_missing_keys_fill_domestic_defaults() {
		$out = SchemaMigrator::upgrade_1_to_2(
			array(
				'schema_version' => 1,
				'connectivity'   => array(
					'wordpress_org' => 'auto',
					'public_assets' => array(),
					'avatar'        => 'off',
				),
			)
		);

		$this->assertSame( 'domestic', $out['profile'] );
		$this->assertArrayNotHasKey( 'admin_assets', $out );
		$this->assertSame( 'off', $out['connectivity']['heartbeat'] );
		$this->assertSame( 'allow', $out['connectivity']['dashboard_feeds'] );
		$this->assertSame( '', $out['diagnostics']['client_probe_url'] );
	}

	/**
	 * Already-v2 documents with a string avatar stay put aside from dropped admin_assets.
	 */
	public function test_upgrade_is_idempotent() {
		$v2 = array(
			'schema_version' => 2,
			'profile'        => 'crossborder',
			'connectivity'   => array(
				'public_assets' => array(
					'items' => array( 'cdnjs' ),
					'scope' => 'admin',
				),
				'avatar'        => 'cravatar_cn',
				'heartbeat'     => 'on',
			),
		);

		$this->assertSame( $v2, SchemaMigrator::upgrade_1_to_2( $v2 ) );
		$this->assertSame( $v2, SchemaMigrator::upgrade_1_to_2( SchemaMigrator::upgrade_1_to_2( $v2 ) ) );
	}

	/**
	 * A v1 document upgraded twice yields the same v2 object.
	 */
	public function test_upgrade_v1_twice_is_idempotent() {
		$v1 = array(
			'schema_version' => 1,
			'connectivity'   => array(
				'wordpress_org' => 'off',
				'public_assets' => array( 'jsdelivr' ),
				'avatar'        => 'off',
			),
		);

		$once  = SchemaMigrator::upgrade_1_to_2( $v1 );
		$twice = SchemaMigrator::upgrade_1_to_2( $once );

		$this->assertSame( 2, $once['schema_version'] );
		$this->assertSame( $once, $twice );
		$this->assertSame( array( 'jsdelivr' ), $once['connectivity']['public_assets']['items'] );
		$this->assertSame( 'both', $once['connectivity']['public_assets']['scope'] );
		$this->assertSame( 'off', $once['connectivity']['avatar'] );
	}

	/**
	 * Repository read of a stored v1 option writes v2 back.
	 */
	public function test_repository_upgrades_and_persists() {
		update_option(
			Schema::SETTINGS,
			array(
				'schema_version' => 1,
				'connectivity'   => array(
					'wordpress_org' => 'off',
					'public_assets' => array( 'jsdelivr' ),
					'avatar'        => 'off',
				),
			)
		);

		$repo = new Repository();
		$this->assertSame( 2, $repo->get( 'schema_version' ) );
		$this->assertSame( 'domestic', $repo->get( 'profile' ) );
		$this->assertSame( array( 'jsdelivr' ), $repo->get( 'connectivity.public_assets.items' ) );
		$this->assertSame( 'both', $repo->get( 'connectivity.public_assets.scope' ) );
		$this->assertSame( 'off', $repo->get( 'connectivity.avatar' ) );
		$this->assertNull( $repo->get( 'admin_assets' ) );

		$stored = get_option( Schema::SETTINGS );
		$this->assertIsArray( $stored );
		$this->assertSame( 2, $stored['schema_version'] );
	}
}
