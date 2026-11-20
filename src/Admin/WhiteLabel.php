<?php
/**
 * Effective white-label configuration resolver (settings + wp-config.php constants).
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves brand name, logo visibility, external-link hiding, and constant lock state.
 */
final class WhiteLabel {

	/**
	 * Resolve effective white-label state from settings snapshot and optional PHP constants.
	 *
	 * Supported wp-config.php constants:
	 * - WPCY_WHITELABEL            (bool)   Force-enable and lock white-label in admin UI.
	 * - WPCY_WHITELABEL_NAME       (string) Override brand name.
	 * - WPCY_WHITELABEL_LOGO       (string) Override logo image URL.
	 * - WPCY_WHITELABEL_HIDE_LOGO  (bool)   Hide default brand mark.
	 * - WPCY_WHITELABEL_HIDE_LINKS (bool)   Hide Help/Feedback, footer links, and uninstalled extension promos.
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 * @return array{enabled: bool, locked: bool, brand_name: string, logo_url: string, hide_logo: bool, hide_links: bool}
	 */
	public static function resolve( array $settings ): array {
		$raw = isset( $settings['admin']['whitelabel'] ) && is_array( $settings['admin']['whitelabel'] )
			? $settings['admin']['whitelabel']
			: array();

		$locked  = defined( 'WPCY_WHITELABEL' ) && (bool) constant( 'WPCY_WHITELABEL' );
		$enabled = $locked || ! empty( $raw['enabled'] );

		$brand_name = isset( $raw['brand_name'] ) && is_string( $raw['brand_name'] ) ? trim( $raw['brand_name'] ) : '';
		if ( defined( 'WPCY_WHITELABEL_NAME' ) && is_string( constant( 'WPCY_WHITELABEL_NAME' ) ) ) {
			$brand_name = trim( (string) constant( 'WPCY_WHITELABEL_NAME' ) );
		}

		$logo_url = isset( $raw['logo_url'] ) && is_string( $raw['logo_url'] ) ? trim( $raw['logo_url'] ) : '';
		if ( defined( 'WPCY_WHITELABEL_LOGO' ) && is_string( constant( 'WPCY_WHITELABEL_LOGO' ) ) ) {
			$logo_url = trim( (string) constant( 'WPCY_WHITELABEL_LOGO' ) );
		}

		$hide_logo = ! empty( $raw['hide_logo'] );
		if ( defined( 'WPCY_WHITELABEL_HIDE_LOGO' ) ) {
			$hide_logo = (bool) constant( 'WPCY_WHITELABEL_HIDE_LOGO' );
		}

		$hide_links = ! empty( $raw['hide_links'] );
		if ( defined( 'WPCY_WHITELABEL_HIDE_LINKS' ) ) {
			$hide_links = (bool) constant( 'WPCY_WHITELABEL_HIDE_LINKS' );
		} elseif ( $locked ) {
			$hide_links = true;
		}

		return array(
			'enabled'    => $enabled,
			'locked'     => $locked,
			'brand_name' => $brand_name,
			'logo_url'   => $logo_url,
			'hide_logo'  => $hide_logo,
			'hide_links' => $hide_links,
		);
	}

	/**
	 * Effective brand display name (falls back to 文派叶子 when white-label is off or empty).
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 */
	public static function brand_name( array $settings ): string {
		$wl = self::resolve( $settings );
		if ( $wl['enabled'] && '' !== $wl['brand_name'] ) {
			return $wl['brand_name'];
		}
		return __( '文派叶子', 'wp-china-yes' );
	}
}
