<?php
/**
 * GET / PUT wpcy/v1/settings
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
 * Site settings. Credential lives on identity, not this document.
 */
final class SettingsController {

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
	 * Full wpcy_settings object.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function get_item( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return RestError::ok( $this->writer->site_document() );
	}

	/**
	 * Validate, persist, return the full object.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( WP_REST_Request $request ) {
		$result = $this->writer->put(
			Schema::SETTINGS,
			$this->writer->site_document(),
			self::body( $request )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return RestError::ok( $this->writer->site_document() );
	}

	/**
	 * JSON body, then form params. Not the query string alone.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return mixed
	 */
	public static function body( WP_REST_Request $request ) {
		$json = $request->get_json_params();
		if ( array() !== $json ) {
			return $json;
		}

		return $request->get_params();
	}
}
