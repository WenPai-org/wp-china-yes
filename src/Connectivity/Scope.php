<?php
/**
 * Admin vs frontend for connectivity rewrites. Not Core\Scope (site vs network).
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Connectivity;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Request side used by PublicAssets and Avatar. wordpress_org does not read this.
 */
final class Scope {

	/**
	 * Admin request (wp-admin, admin-ajax, authenticated REST from /wp-admin, WP-CLI).
	 *
	 * @since 4.0.0
	 */
	public const ADMIN = 'admin';

	/**
	 * Frontend request (theme, unauthenticated REST, WP-Cron).
	 *
	 * @since 4.0.0
	 */
	public const FRONTEND = 'frontend';

	/**
	 * Current request side. Order is frozen: CLI, cron, is_admin, REST+nonce, else frontend.
	 *
	 * Cron is checked before is_admin() so a cron spawned from wp-admin stays frontend.
	 *
	 * @since 4.0.0
	 */
	public static function current(): string {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return self::ADMIN;
		}

		if ( ( defined( 'DOING_CRON' ) && constant( 'DOING_CRON' ) )
			|| ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() )
		) {
			return self::FRONTEND;
		}

		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return self::ADMIN;
		}

		$doing_rest = ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			|| ( function_exists( 'wp_doing_rest' ) && wp_doing_rest() );

		if ( $doing_rest && self::rest_from_wp_admin() ) {
			return self::ADMIN;
		}

		return self::FRONTEND;
	}

	/**
	 * REST with a valid wp_rest nonce and a /wp-admin referer is an admin request.
	 */
	private static function rest_from_wp_admin(): bool {
		$nonce = '';
		if ( isset( $_SERVER['HTTP_X_WP_NONCE'] ) && is_string( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) );
		}

		if ( '' === $nonce ) {
			return false;
		}

		if ( ! function_exists( 'wp_verify_nonce' ) || ! wp_verify_nonce( (string) $nonce, 'wp_rest' ) ) {
			return false;
		}

		$referer = function_exists( 'wp_get_referer' ) ? (string) wp_get_referer() : '';

		return false !== strpos( $referer, '/wp-admin' );
	}
}
