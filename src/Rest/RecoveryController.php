<?php
/**
 * POST wpcy/v1/recovery
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Body { action: disable_rewrites|disable_modules|exit }.
 */
final class RecoveryController {

	/**
	 * Shared with the PHP recovery page.
	 *
	 * @var RecoveryActions
	 */
	private RecoveryActions $actions;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param RecoveryActions $actions Shared recovery actions.
	 */
	public function __construct( RecoveryActions $actions ) {
		$this->actions = $actions;
	}

	/**
	 * Apply one action and return the settings document.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( WP_REST_Request $request ) {
		$action = sanitize_key( (string) $request->get_param( 'action' ) );
		if ( '' === $action ) {
			$body = SettingsController::body( $request );
			if ( is_array( $body ) && isset( $body['action'] ) ) {
				$action = sanitize_key( (string) $body['action'] );
			}
		}
		$result = $this->actions->apply( $action );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return RestError::ok( $this->actions->settings() );
	}
}
