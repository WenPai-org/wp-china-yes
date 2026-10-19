<?php
/**
 * REST permission callbacks: capability + write nonce.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Rest;

use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Capability checks: manage_options / manage_network_options; writes need X-WP-Nonce.
 */
final class Permissions {

	/**
	 * GET /settings and diagnostics: manage_options.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return true|WP_Error
	 */
	public static function manage_options_read( WP_REST_Request $request ) {
		unset( $request );
		if ( ! current_user_can( 'manage_options' ) ) {
			return RestError::forbidden();
		}

		return true;
	}

	/**
	 * PUT /settings and POST /recovery /diagnostics/run.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return true|WP_Error
	 */
	public static function manage_options_write( WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return RestError::forbidden();
		}
		if ( ! self::nonce_ok( $request ) ) {
			return RestError::forbidden();
		}

		return true;
	}

	/**
	 * GET /network-settings: manage_network_options.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return true|WP_Error
	 */
	public static function manage_network_read( WP_REST_Request $request ) {
		unset( $request );
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return RestError::forbidden();
		}

		return true;
	}

	/**
	 * PUT /network-settings.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return true|WP_Error
	 */
	public static function manage_network_write( WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return RestError::forbidden();
		}
		if ( ! self::nonce_ok( $request ) ) {
			return RestError::forbidden();
		}

		return true;
	}

	/**
	 * WordPress REST nonce in X-WP-Nonce.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public static function nonce_ok( WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! is_string( $nonce ) || '' === $nonce ) {
			return false;
		}

		return false !== wp_verify_nonce( $nonce, 'wp_rest' );
	}
}
