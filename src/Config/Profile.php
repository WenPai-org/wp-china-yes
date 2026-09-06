<?php
/**
 * Site-profile default matrix (ADR-004 D2). Switching resets connectivity keys.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * D2 matrix. Values are frozen; do not invent a fourth profile.
 */
final class Profile {

	/**
	 * Five public-asset tokens used by every profile column.
	 *
	 * @since 4.0.0
	 *
	 * @var list<string>
	 */
	public const ITEMS = array(
		'google_fonts',
		'google_ajax',
		'cdnjs',
		'jsdelivr',
		'emoji',
	);

	/**
	 * Connectivity defaults for one profile. Does not include notice /
	 * announcements / diagnostics / recovery / telemetry / data_residency.
	 *
	 * @since 4.0.0
	 *
	 * @param string $profile domestic|crossborder|mixed.
	 * @return array<string, mixed>
	 */
	public static function apply_defaults( string $profile ): array {
		$matrix = self::matrix();
		if ( ! isset( $matrix[ $profile ] ) ) {
			$profile = 'domestic';
		}

		return $matrix[ $profile ];
	}

	/**
	 * Overlay D2 connectivity keys onto an existing settings document.
	 *
	 * Leaves notice_control, announcements, diagnostics, recovery_mode,
	 * data_residency, and apps untouched.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $settings Current document.
	 * @param string               $profile  Target profile.
	 * @return array<string, mixed>
	 */
	public static function apply_to( array $settings, string $profile ): array {
		$defaults = self::apply_defaults( $profile );

		$settings['profile']      = $profile;
		$settings['admin_assets'] = $defaults['admin_assets'];

		if ( ! isset( $settings['connectivity'] ) || ! is_array( $settings['connectivity'] ) ) {
			$settings['connectivity'] = array();
		}
		$settings['connectivity']['wordpress_org']   = $defaults['connectivity']['wordpress_org'];
		$settings['connectivity']['public_assets']   = $defaults['connectivity']['public_assets'];
		$settings['connectivity']['avatar']          = $defaults['connectivity']['avatar'];
		$settings['connectivity']['heartbeat']       = $defaults['connectivity']['heartbeat'];
		$settings['connectivity']['dashboard_feeds'] = $defaults['connectivity']['dashboard_feeds'];

		if ( ! isset( $settings['modules'] ) || ! is_array( $settings['modules'] ) ) {
			$settings['modules'] = array();
		}
		$settings['modules']['windfonts'] = $defaults['modules']['windfonts'];

		return $settings;
	}

	/**
	 * D2 default matrix. Copied from config-schema.md; do not change cells.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function matrix(): array {
		$five = self::ITEMS;

		return array(
			'domestic'    => array(
				'admin_assets' => 'off',
				'connectivity' => array(
					'wordpress_org'   => 'auto',
					'public_assets'   => array(
						'items' => $five,
						'scope' => 'both',
					),
					'avatar'          => array(
						'admin'    => 'cravatar_cn',
						'frontend' => 'cravatar_cn',
					),
					'heartbeat'       => 'off',
					'dashboard_feeds' => 'allow',
				),
				'modules'      => array(
					'windfonts' => false,
				),
			),
			'crossborder' => array(
				'admin_assets' => 'on',
				'connectivity' => array(
					'wordpress_org'   => 'off',
					'public_assets'   => array(
						'items' => $five,
						'scope' => 'admin',
					),
					'avatar'          => array(
						'admin'    => 'cravatar_cn',
						'frontend' => 'off',
					),
					'heartbeat'       => 'on',
					'dashboard_feeds' => 'block',
				),
				'modules'      => array(
					'windfonts' => false,
				),
			),
			'mixed'       => array(
				'admin_assets' => 'on',
				'connectivity' => array(
					'wordpress_org'   => 'auto',
					'public_assets'   => array(
						'items' => $five,
						'scope' => 'admin',
					),
					'avatar'          => array(
						'admin'    => 'cravatar_cn',
						'frontend' => 'cravatar_global',
					),
					'heartbeat'       => 'on',
					'dashboard_feeds' => 'block',
				),
				'modules'      => array(
					'windfonts' => false,
				),
			),
		);
	}
}
