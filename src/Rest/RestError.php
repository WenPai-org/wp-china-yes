<?php
/**
 * REST namespace wpcy/v1 error shape and request-id header helper.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Rest;

use WP_Error;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST errors: { code, message, data.status, data.request_id }. Prefix wpcy_.
 */
final class RestError {

	/**
	 * Request-id header name.
	 *
	 * @since 4.0.0
	 */
	public const HEADER = 'X-WPCY-Request-Id';

	/**
	 * Current request id for this PHP request.
	 *
	 * @var string
	 */
	private static $current = '';

	/**
	 * Id for this request. Stable until reset().
	 *
	 * @since 4.0.0
	 */
	public static function request_id(): string {
		if ( '' === self::$current ) {
			self::$current = self::make_id();
		}

		return self::$current;
	}

	/**
	 * Clear the cached id (unit tests).
	 *
	 * @since 4.0.0
	 */
	public static function reset(): void {
		self::$current = '';
	}

	/**
	 * Attach X-WPCY-Request-Id to a REST response.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $data   Response body.
	 * @param int   $status HTTP status.
	 * @return WP_REST_Response
	 */
	public static function ok( $data, int $status = 200 ): WP_REST_Response {
		$response = new WP_REST_Response( $data, $status );
		$response->header( self::HEADER, self::request_id() );
		return $response;
	}

	/**
	 * Capability missing or nonce invalid.
	 *
	 * @since 4.0.0
	 *
	 * @return WP_Error
	 */
	public static function forbidden(): WP_Error {
		return self::make(
			'wpcy_forbidden',
			__( 'You are not allowed to access this resource.', 'wp-china-yes' ),
			403
		);
	}

	/**
	 * PUT body failed config schema (not merely an unknown key).
	 *
	 * @since 4.0.0
	 *
	 * @return WP_Error
	 */
	public static function invalid_schema(): WP_Error {
		return self::make(
			'wpcy_invalid_schema',
			__( 'The request body does not match the settings schema.', 'wp-china-yes' ),
			400
		);
	}

	/**
	 * /recovery action is not one of the three enum values.
	 *
	 * @since 4.0.0
	 *
	 * @return WP_Error
	 */
	public static function unknown_action(): WP_Error {
		return self::make(
			'wpcy_recovery_unknown_action',
			__( 'Unknown recovery action.', 'wp-china-yes' ),
			400
		);
	}

	/**
	 * WP_Error with the frozen data shape.
	 *
	 * @since 4.0.0
	 *
	 * @param string $code    Error code, wpcy_ prefix.
	 * @param string $message Human-readable message.
	 * @param int    $status  HTTP status.
	 * @return WP_Error
	 */
	public static function make( string $code, string $message, int $status ): WP_Error {
		return new WP_Error(
			$code,
			$message,
			array(
				'status'     => $status,
				'request_id' => self::request_id(),
			)
		);
	}

	/**
	 * UTC ISO 8601 timestamp.
	 *
	 * @since 4.0.0
	 */
	public static function now(): string {
		return gmdate( 'Y-m-d\TH:i:s\Z' );
	}

	/**
	 * New request id. UUID when WordPress is loaded.
	 *
	 * @since 4.0.0
	 */
	private static function make_id(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}

		return bin2hex( random_bytes( 16 ) );
	}
}
