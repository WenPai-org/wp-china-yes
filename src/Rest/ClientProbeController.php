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
		'googlefonts.admincdn.com',
		'cn.cravatar.com',
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
		if ( ! $this->probe_enabled() ) {
			return RestError::ok(
				array(
					'checked_at' => null,
					'probes'     => array(),
				)
			);
		}
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
		if ( ! $this->probe_enabled() ) {
			return RestError::ok(
				array(
					'checked_at' => null,
					'probes'     => array(),
				)
			);
		}

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
			'probes'     => $this->project_probes( $probes ),
		);
	}

	/**
	 * Drop extra keys so GET matches {target,result,latency_ms} plus optional sanitized RUM fields.
	 *
	 * @param array<int|string, mixed> $probes Stored rows.
	 * @return list<array<string, mixed>>
	 */
	private function project_probes( array $probes ): array {
		$out = array();
		foreach ( $probes as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$target = isset( $row['target'] ) && is_string( $row['target'] ) ? $row['target'] : '';
			$result = isset( $row['result'] ) && is_string( $row['result'] ) ? $row['result'] : '';
			if ( '' === $target || ! in_array( $result, array( 'ok', 'down' ), true ) ) {
				continue;
			}
			$latency = $row['latency_ms'] ?? null;
			if ( null !== $latency && ( ! is_int( $latency ) || $latency < 1 ) ) {
				$latency = null;
			}
			$item  = array(
				'target'     => $target,
				'result'     => $result,
				'latency_ms' => $latency,
			);
			$out[] = self::append_rum_fields( $item, $row );
		}

		return $out;
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
	 * @return array<string, mixed>|null
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

		$item = array(
			'target'     => $host,
			'result'     => $result,
			'latency_ms' => $latency,
		);

		return self::append_rum_fields( $item, $row );
	}

	/**
	 * Append optional sanitized RUM fields (province, isp, switched_fallback, tao) when present.
	 *
	 * @param array<string, mixed> $item Base probe fields.
	 * @param array<string, mixed> $row  Source probe row.
	 * @return array<string, mixed>
	 */
	private static function append_rum_fields( array $item, array $row ): array {
		foreach ( array( 'province', 'isp' ) as $str_key ) {
			if ( isset( $row[ $str_key ] ) && is_string( $row[ $str_key ] ) ) {
				$clean = trim( (string) preg_replace( '/[\x00-\x1f\x7f<>]/', '', $row[ $str_key ] ) );
				if ( '' !== $clean ) {
					$item[ $str_key ] = self::truncate_utf8( $clean, 32 );
				}
			}
		}
		foreach ( array( 'switched_fallback', 'tao' ) as $bool_key ) {
			if ( isset( $row[ $bool_key ] ) && is_bool( $row[ $bool_key ] ) ) {
				$item[ $bool_key ] = $row[ $bool_key ];
			}
		}
		return $item;
	}

	/**
	 * Truncate a UTF-8 string safely to a maximum character count.
	 *
	 * @param string $value Input string.
	 * @param int    $limit Max UTF-8 characters.
	 */
	private static function truncate_utf8( string $value, int $limit ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $limit, 'UTF-8' );
		}
		$cut = preg_replace( '/^(.{0,' . $limit . '}).*/su', '$1', $value );
		return is_string( $cut ) ? $cut : '';
	}

	/**
	 * Whether browser probe collection/reporting is enabled.
	 */
	private function probe_enabled(): bool {
		return (bool) $this->repository->get( 'diagnostics.client_probe_enabled', true );
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
