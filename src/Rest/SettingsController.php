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
	 * UTC ISO 8601 clock for profile_confirmed_at.
	 *
	 * @var \Closure(): string
	 */
	private \Closure $clock;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param DocumentWriter            $writer Merge / persist helper.
	 * @param (callable(): string)|null $clock  Timestamp source; defaults to RestError::now(). Tests pass a fixed clock.
	 */
	public function __construct( DocumentWriter $writer, ?callable $clock = null ) {
		$this->writer = $writer;
		$this->clock  = \Closure::fromCallable( $clock ?? array( RestError::class, 'now' ) );
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

		// Not set()'s return value: update_option() also returns false when the
		// document is unchanged (same-second timestamp after a profile switch).
		$repository = $this->writer->repository();
		$written    = $had_key && ! $repository->is_locked_path( 'profile_confirmed_at' );
		if ( $had_key ) {
			$repository->set( 'profile_confirmed_at', ( $this->clock )() );
		}
		if ( $this->should_record_profile_set( $written, $profile, $from, $before['profile_confirmed_at'] ?? null ) ) {
			$this->record_profile_set( $profile );
		}

		return RestError::ok( $this->writer->site_document() );
	}

	/**
	 * Whether this PUT records a profile_set event.
	 *
	 * Only when profile_confirmed_at is written (a network-locked site
	 * writes nothing), and then only when the profile changed or this is
	 * the first confirmation (no stored timestamp before the PUT). An echo
	 * of an unchanged, already confirmed profile records nothing.
	 *
	 * @since 4.0.0
	 *
	 * @param bool   $written Whether profile_confirmed_at is written (not locked).
	 * @param string $profile Profile after the PUT.
	 * @param string $from    Stored profile before the PUT.
	 * @param mixed  $stamp   Stored profile_confirmed_at before the PUT.
	 */
	private function should_record_profile_set( bool $written, string $profile, string $from, $stamp ): bool {
		if ( ! $written ) {
			return false;
		}
		if ( $profile !== $from ) {
			return true;
		}

		// First confirmation of the unchanged profile still records one event
		// (onboarding confirms the default profile this way).
		return ! is_string( $stamp ) || '' === $stamp;
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
			'crossborder' => __( '跨境 · 外贸站', 'wp-china-yes' ),
			'inbound'     => __( '内贸 · 进中国站', 'wp-china-yes' ),
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
