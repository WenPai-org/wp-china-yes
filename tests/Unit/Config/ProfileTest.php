<?php
/**
 * D2 default matrix and profile-switch overlay.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Defaults;
use WenPai\ChinaYes\Config\Profile;

/**
 * 8 paths × 3 profiles; switch leaves notice_control alone.
 */
class ProfileTest extends TestCase {

	/**
	 * Five public-asset tokens.
	 *
	 * @var list<string>
	 */
	private const FIVE = array( 'google_fonts', 'google_ajax', 'cdnjs', 'jsdelivr', 'emoji' );

	/**
	 * Domestic column matches Defaults::settings() connectivity keys.
	 */
	public function test_domestic_matches_defaults() {
		$defaults = Defaults::settings();
		$row      = Profile::apply_defaults( 'domestic' );

		$this->assertSame( $defaults['connectivity']['wordpress_org'], $row['connectivity']['wordpress_org'] );
		$this->assertSame( $defaults['connectivity']['public_assets'], $row['connectivity']['public_assets'] );
		$this->assertSame( $defaults['connectivity']['avatar'], $row['connectivity']['avatar'] );
		$this->assertSame( $defaults['connectivity']['heartbeat'], $row['connectivity']['heartbeat'] );
		$this->assertSame( $defaults['connectivity']['dashboard_feeds'], $row['connectivity']['dashboard_feeds'] );
		$this->assertSame( $defaults['modules']['windfonts'], $row['modules']['windfonts'] );
		$this->assertArrayNotHasKey( 'admin_assets', $row );
		$this->assertArrayNotHasKey( 'admin_assets', $defaults );
	}

	/**
	 * Three-profile matrix, every cell.
	 *
	 * @dataProvider matrix_provider
	 *
	 * @param string $profile Profile.
	 * @param string $path    Dotted path under apply_defaults().
	 * @param mixed  $expect  Expected value.
	 */
	public function test_matrix_cell( string $profile, string $path, $expect ) {
		$row   = Profile::apply_defaults( $profile );
		$value = $row;
		foreach ( explode( '.', $path ) as $segment ) {
			$this->assertIsArray( $value );
			$this->assertArrayHasKey( $segment, $value );
			$value = $value[ $segment ];
		}
		$this->assertSame( $expect, $value, $profile . ' ' . $path );
	}

	/**
	 * 8 rows × 3 columns.
	 *
	 * @return array<string, array{0: string, 1: string, 2: mixed}>
	 */
	public function matrix_provider(): array {
		$five = self::FIVE;
		$out  = array();
		foreach (
			array(
				'wordpress_org'       => array( 'auto', 'off', 'auto' ),
				'public_assets.items' => array( $five, $five, $five ),
				'public_assets.scope' => array( 'both', 'admin', 'admin' ),
				'avatar'              => array( 'cravatar_cn', 'cravatar_cn', 'cravatar_cn' ),
				'modules.windfonts'   => array( false, false, false ),
				'heartbeat'           => array( 'off', 'on', 'on' ),
				'dashboard_feeds'     => array( 'allow', 'block', 'block' ),
			) as $key => $values
		) {
			$paths                        = array(
				'wordpress_org'       => 'connectivity.wordpress_org',
				'public_assets.items' => 'connectivity.public_assets.items',
				'public_assets.scope' => 'connectivity.public_assets.scope',
				'avatar'              => 'connectivity.avatar',
				'modules.windfonts'   => 'modules.windfonts',
				'heartbeat'           => 'connectivity.heartbeat',
				'dashboard_feeds'     => 'connectivity.dashboard_feeds',
			);
			$path                         = $paths[ $key ];
			$out[ $key . ' domestic' ]    = array( 'domestic', $path, $values[0] );
			$out[ $key . ' crossborder' ] = array( 'crossborder', $path, $values[1] );
			$out[ $key . ' mixed' ]       = array( 'mixed', $path, $values[2] );
		}

		return $out;
	}

	/**
	 * Switching profile resets D2 keys and leaves notice / diagnostics / residency.
	 */
	public function test_switch_does_not_touch_notice_control() {
		$settings                                      = Defaults::settings();
		$settings['modules']['notice_control']         = false;
		$settings['announcements']['dismissed']        = array( 'a1' );
		$settings['diagnostics']['scheduled_checks']   = false;
		$settings['recovery_mode']                     = true;
		$settings['data_residency']['ruleset_version'] = 9;
		$settings['apps']['disabled']                  = array( 'x' );
		$settings['connectivity']['wordpress_org']     = 'auto';

		$out = Profile::apply_to( $settings, 'crossborder' );

		$this->assertSame( 'crossborder', $out['profile'] );
		$this->assertSame( 'off', $out['connectivity']['wordpress_org'] );
		$this->assertSame( 'admin', $out['connectivity']['public_assets']['scope'] );
		$this->assertSame( 'cravatar_cn', $out['connectivity']['avatar'] );
		$this->assertSame( 'on', $out['connectivity']['heartbeat'] );
		$this->assertSame( 'block', $out['connectivity']['dashboard_feeds'] );
		$this->assertArrayNotHasKey( 'admin_assets', $out );
		$this->assertFalse( $out['modules']['windfonts'] );
		$this->assertFalse( $out['modules']['notice_control'] );
		$this->assertSame( array( 'a1' ), $out['announcements']['dismissed'] );
		$this->assertFalse( $out['diagnostics']['scheduled_checks'] );
		$this->assertTrue( $out['recovery_mode'] );
		$this->assertSame( 9, $out['data_residency']['ruleset_version'] );
		$this->assertSame( array( 'x' ), $out['apps']['disabled'] );
		$this->assertTrue( $out['connectivity']['admin_locale_follow'] );
	}

	/**
	 * Profile switch does not reset admin_locale_follow (all scenes).
	 */
	public function test_switch_leaves_admin_locale_follow() {
		$settings                                        = Defaults::settings();
		$settings['connectivity']['admin_locale_follow'] = false;

		$out = Profile::apply_to( $settings, 'crossborder' );

		$this->assertFalse( $out['connectivity']['admin_locale_follow'] );
	}

	/**
	 * Inbound: frontend-only public assets; avatar still cravatar_cn site-wide.
	 */
	public function test_inbound_defaults() {
		$row = Profile::apply_defaults( 'inbound' );

		$this->assertSame( 'off', $row['connectivity']['wordpress_org'] );
		$this->assertSame( 'frontend', $row['connectivity']['public_assets']['scope'] );
		$this->assertSame( 'cravatar_cn', $row['connectivity']['avatar'] );
		$this->assertSame( 'off', $row['connectivity']['heartbeat'] );
		$this->assertSame( 'allow', $row['connectivity']['dashboard_feeds'] );
		$this->assertArrayNotHasKey( 'admin_assets', $row );
	}
}
