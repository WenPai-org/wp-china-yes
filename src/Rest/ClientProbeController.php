<?php
/**
 * GET / POST /wpcy/v1/diagnostics/client-probe
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Rest;

use WenPai\ChinaYes\Config\Repository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores the latest browser probe summary. Does not fetch the URLs (no SSRF).
 */
final class ClientProbeController {

	/**
	 * Option that holds the latest summary. autoload=no. Not a settings field.
	 *
	 * @since 4.0.0
	 */
	public const OPTION = 'wpcy_diagnostics_client_probe';

	/**
	 * Built-in allow-list hosts from rest-api.md.
	 *
	 * @var list<string>
	 */
	private const HOSTS = array(
		'fonts.googleapis.com',
		'secure.gravatar.com',
		'www.gravatar.com',
		'gravatar.com',
	);

	/**
	 * Settings access for diagnostics.client_probe_url.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param Repository $repository Settings access.
	 */
	public function __construct( Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Latest summary, or empty envelope.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function get_item( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return RestError::ok( $this->stored() );
	}

	/**
	 * Overwrite the stored summary. Invalid host → 400, storage unchanged.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( WP_REST_Request $request ) {
		$body = SettingsController::body( $request );
		if ( ! is_array( $body ) || ! isset( $body['probes'] ) || ! is_array( $body['probes'] ) ) {
			return RestError::invalid_schema();
		}

		$probes = $body['probes'];
		if ( array() === $probes || count( $probes ) > 8 || ! $this->is_list( $probes ) ) {
			return RestError::invalid_schema();
		}

		$allowed = $this->allowed_hosts();
		$out     = array();
		foreach ( $probes as $row ) {
			$parsed = $this->parse_probe( $row, $allowed );
			if ( null === $parsed ) {
				return RestError::invalid_schema();
			}
			$out[] = $parsed;
		}

		$document = array(
			'checked_at' => RestError::now(),
			'probes'     => $out,
		);

		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION, $document, false );
		}

		return RestError::ok( $document );
	}

	/**
	 * Stored summary or the empty envelope.
	 *
	 * @return array{checked_at: string|null, probes: list<array<string, mixed>>}
	 */
	private function stored(): array {
		$raw = function_exists( 'get_option' ) ? get_option( self::OPTION, array() ) : array();
		if ( ! is_array( $raw ) || array() === $raw ) {
			return array(
				'checked_at' => null,
				'probes'     => array(),
			);
		}

		$checked = isset( $raw['checked_at'] ) && is_string( $raw['checked_at'] ) ? $raw['checked_at'] : null;
		$probes  = isset( $raw['probes'] ) && is_array( $raw['probes'] ) ? $raw['probes'] : array();

		return array(
			'checked_at' => $checked,
			'probes'     => $probes,
		);
	}

	/**
	 * Allow-list plus optional diagnostics.client_probe_url host.
	 *
	 * @return list<string>
	 */
	private function allowed_hosts(): array {
		$hosts = self::HOSTS;
		$extra = $this->repository->get( 'diagnostics.client_probe_url', '' );
		if ( is_string( $extra ) && '' !== $extra && 0 === stripos( $extra, 'https:' ) ) {
			$host = $this->host_of( $extra );
			if ( '' !== $host && ! in_array( $host, $hosts, true ) ) {
				$hosts[] = $host;
			}
		}

		return $hosts;
	}

	/**
	 * Validate one probe row. Null when schema or host fails.
	 *
	 * @param mixed              $row     Incoming row.
	 * @param array<int, string> $allowed Allow-list hosts.
	 * @return array{target: string, result: string, latency_ms: int|null}|null
	 */
	private function parse_probe( $row, array $allowed ) {
		if ( ! is_array( $row ) ) {
			return null;
		}
		$url = isset( $row['url'] ) && is_string( $row['url'] ) ? $row['url'] : '';
		if ( '' === $url || 0 !== stripos( $url, 'https:' ) ) {
			return null;
		}
		$host = $this->host_of( $url );
		if ( '' === $host || ! in_array( $host, $allowed, true ) ) {
			return null;
		}

		$result = isset( $row['result'] ) && is_string( $row['result'] ) ? $row['result'] : '';
		if ( ! in_array( $result, array( 'ok', 'down' ), true ) ) {
			return null;
		}

		$latency = $row['latency_ms'] ?? null;
		if ( null !== $latency ) {
			if ( ! is_int( $latency ) || $latency < 1 ) {
				return null;
			}
		}

		return array(
			'target'     => $host,
			'result'     => $result,
			'latency_ms' => $latency,
		);
	}

	/**
	 * Lowercase host from a URL.
	 *
	 * @param string $url Absolute URL.
	 */
	private function host_of( string $url ): string {
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- unit bootstrap has no WordPress.
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}

		return strtolower( $parts['host'] );
	}

	/**
	 * Whether $value is a JSON array (0-based list).
	 *
	 * @param array<int|string, mixed> $value Candidate.
	 */
	private function is_list( array $value ): bool {
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
