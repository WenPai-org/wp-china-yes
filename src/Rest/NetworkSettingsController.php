<?php
/**
 * GET / PUT wpcy/v1/network-settings
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Rest;

use WenPai\ChinaYes\Config\Schema;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Network option. Permission is manage_network_options.
 */
final class NetworkSettingsController {

	/**
	 * Merge / persist helper.
	 *
	 * @var DocumentWriter
	 */
	private DocumentWriter $writer;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param DocumentWriter $writer Merge / persist helper.
	 */
	public function __construct( DocumentWriter $writer ) {
		$this->writer = $writer;
	}

	/**
	 * Full wpcy_network_settings object.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function get_item( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return RestError::ok( $this->writer->network_document() );
	}

	/**
	 * Validate, persist, return the full network object.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( WP_REST_Request $request ) {
		$result = $this->writer->put(
			Schema::NETWORK_SETTINGS,
			$this->writer->network_document(),
			SettingsController::body( $request )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return RestError::ok( $this->writer->network_document() );
	}
}
