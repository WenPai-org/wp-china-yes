<?php
/**
 * GET / PUT wpcy/v1/settings
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
		$body = self::body( $request );
		if ( is_array( $body ) ) {
			unset( $body['profile_confirmed_at'] );
		}

		$before  = $this->writer->stored_site_document();
		$had_key = is_array( $body ) && array_key_exists( 'profile', $body );
		$from    = isset( $before['profile'] ) && is_string( $before['profile'] ) ? $before['profile'] : 'domestic';
		$profile = $had_key && is_string( $body['profile'] ) ? $body['profile'] : $from;

		$result = $this->writer->put_site( $body );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( $had_key ) {
			$this->writer->repository()->set( 'profile_confirmed_at', RestError::now() );
			$stamp = $before['profile_confirmed_at'] ?? null;
			$first = ! is_string( $stamp ) || '' === $stamp;
			if ( $profile !== $from || $first ) {
				$this->record_profile_set( $profile );
			}
		}

		return RestError::ok( $this->writer->site_document() );
	}

	/**
	 * Record profile_set when the stored profile changes or is first confirmed.
	 *
	 * @since 4.0.0
	 *
	 * @param string $profile New profile enum.
	 */
	private function record_profile_set( string $profile ): void {
		if ( ! function_exists( 'do_action' ) ) {
			return;
		}

		$labels = array(
			'domestic'    => __( '国内站', 'wp-china-yes' ),
			'crossborder' => __( '跨境 / 外贸站', 'wp-china-yes' ),
			'mixed'       => __( '混合站', 'wp-china-yes' ),
		);

		do_action(
			'wpcy_events_record',
			'profile_set',
			array(
				'profile'       => $profile,
				'profile_label' => isset( $labels[ $profile ] ) ? $labels[ $profile ] : $profile,
			)
		);
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
