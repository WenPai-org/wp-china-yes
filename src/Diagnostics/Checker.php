<?php
/**
 * Connection probes for WordPress.org mirrors, public-library nodes, and avatar lines.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Diagnostics;

use WenPai\ChinaYes\Connectivity\MirrorHealth;
use WenPai\ChinaYes\Connectivity\WordPressOrg\Origins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the frozen diagnostics result objects consumed by REST, CLI, and Site Health.
 */
final class Checker {

	/**
	 * Transient that holds the last run.
	 *
	 * @since 4.0.0
	 */
	public const STORE_KEY = 'wpcy_diagnostics_results';

	/**
	 * Result: domestic target reached.
	 *
	 * @since 4.0.0
	 */
	public const RESULT_OK = 'ok';

	/**
	 * Result: domestic failed, original upstream reached.
	 *
	 * @since 4.0.0
	 */
	public const RESULT_FALLBACK = 'fallback';

	/**
	 * Result: neither domestic nor upstream reached.
	 *
	 * @since 4.0.0
	 */
	public const RESULT_DOWN = 'down';

	/**
	 * HTTP GET: function( string $url, array $args ): mixed
	 *
	 * @var callable
	 */
	private $http_get;

	/**
	 * Store reader: function( string $key ): mixed
	 *
	 * @var callable
	 */
	private $get_store;

	/**
	 * Store writer: function( string $key, mixed $value, int $ttl ): bool
	 *
	 * @var callable
	 */
	private $set_store;

	/**
	 * Optional config. Null uses built-in probe defaults.
	 *
	 * Accepts Core\Config or Repository (M1-05b implements Config).
	 *
	 * @var object|null
	 */
	private $config;

	/**
	 * Clock: function(): string UTC ISO 8601.
	 *
	 * @var callable
	 */
	private $now;

	/**
	 * Wire HTTP, store, config, and clock. Constructor does not probe.
	 *
	 * @since 4.0.0
	 *
	 * @param callable|null $http_get  Defaults to wp_remote_get().
	 * @param callable|null $get_store Defaults to get_transient().
	 * @param callable|null $set_store Defaults to set_transient().
	 * @param object|null   $config    Optional dotted-path config (Config or Repository).
	 * @param callable|null $now       Defaults to gmdate UTC Z.
	 */
	public function __construct( $http_get = null, $get_store = null, $set_store = null, $config = null, $now = null ) {
		$this->http_get  = null !== $http_get ? $http_get : 'wp_remote_get';
		$this->get_store = null !== $get_store ? $get_store : 'get_transient';
		$this->set_store = null !== $set_store ? $set_store : 'set_transient';
		$this->config    = ( is_object( $config ) && method_exists( $config, 'get' ) ) ? $config : null;
		$this->now       = null !== $now ? $now : static function () {
			return gmdate( 'Y-m-d\TH:i:s\Z' );
		};
	}

	/**
	 * Probe every target, persist, and return the result objects.
	 *
	 * @since 4.0.0
	 *
	 * @return list<array{target: string, result: string, latency_ms: int|null, checked_at: string, suggestion: string|null}>
	 */
	public function run(): array {
		$checked_at = (string) ( $this->now )();
		$results    = array();

		foreach ( $this->targets() as $spec ) {
			$results[] = $this->probe_target( $spec, $checked_at );
		}

		( $this->set_store )( self::STORE_KEY, $results, DAY_IN_SECONDS );

		return $results;
	}

	/**
	 * Last stored results. Empty when no run has succeeded in writing.
	 *
	 * @since 4.0.0
	 *
	 * @return list<array{target: string, result: string, latency_ms: int|null, checked_at: string, suggestion: string|null}>
	 */
	public function latest(): array {
		$stored = ( $this->get_store )( self::STORE_KEY );
		if ( ! is_array( $stored ) ) {
			return array();
		}

		$out = array();
		foreach ( $stored as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$normalized = $this->normalize( $row );
			if ( null !== $normalized ) {
				$out[] = $normalized;
			}
		}

		return $out;
	}

	/**
	 * Envelope used by REST GET /diagnostics (targets only).
	 *
	 * @since 4.0.0
	 *
	 * @return array{targets: list<array{target: string, result: string, latency_ms: int|null, checked_at: string, suggestion: string|null}>}
	 */
	public function snapshot(): array {
		return array(
			'targets' => $this->latest(),
		);
	}

	/**
	 * Probe plan: WordPress.org mirrors, public-library nodes, avatar line.
	 *
	 * @since 4.0.0
	 *
	 * @return list<array{target: string, url: string, upstream_url: string}>
	 */
	public function targets(): array {
		$plan = array(
			array(
				'target'       => 'api.wenpai.net',
				'url'          => Origins::API_ORIGIN . '/core/version-check/1.7/',
				'upstream_url' => 'https://api.wordpress.org/core/version-check/1.7/',
			),
			array(
				'target'       => 'downloads.wenpai.net',
				'url'          => Origins::PACKAGE_ORIGIN . Origins::PROBE_PATH,
				'upstream_url' => 'https://downloads.wordpress.org' . Origins::PROBE_PATH,
			),
		);

		$probes = MirrorHealth::probe_targets();
		$public = array(
			'cdnjs.admincdn.com'       => 'https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js',
			'jsd.admincdn.com'         => 'https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js',
			'googleajax.admincdn.com'  => 'https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js',
			'googlefonts.admincdn.com' => 'https://fonts.googleapis.com/css2?family=Roboto:wght@400',
		);

		foreach ( $public as $host => $upstream ) {
			$path   = isset( $probes[ $host ] ) ? $probes[ $host ] : '/';
			$plan[] = array(
				'target'       => $host,
				'url'          => 'https://' . $host . $path,
				'upstream_url' => $upstream,
			);
		}

		$avatar = $this->avatar_target();
		if ( null !== $avatar ) {
			$plan[] = $avatar;
		}

		return $plan;
	}

	/**
	 * Probe domestic URL, then upstream on failure. Never reports ok on remote failure.
	 *
	 * @param array{target: string, url: string, upstream_url: string} $spec       Probe spec.
	 * @param string                                                   $checked_at UTC ISO 8601.
	 * @return array{target: string, result: string, latency_ms: int|null, checked_at: string, suggestion: string|null}
	 */
	private function probe_target( array $spec, string $checked_at ): array {
		$domestic = $this->hit( $spec['url'] );

		if ( $domestic['ok'] ) {
			return $this->row( $spec['target'], self::RESULT_OK, $domestic['latency_ms'], $checked_at, null );
		}

		$upstream = $this->hit( $spec['upstream_url'] );

		if ( $upstream['ok'] ) {
			return $this->row(
				$spec['target'],
				self::RESULT_FALLBACK,
				$domestic['latency_ms'],
				$checked_at,
				__( '国内镜像不可用，已回原始上游。', 'wp-china-yes' )
			);
		}

		return $this->row(
			$spec['target'],
			self::RESULT_DOWN,
			$domestic['latency_ms'],
			$checked_at,
			__( '目标不可达，请稍后重试或检查网络。', 'wp-china-yes' )
		);
	}

	/**
	 * One HTTP GET. Returns ok=false on transport error or non-success status.
	 *
	 * @param string $url Absolute URL.
	 * @return array{ok: bool, latency_ms: int}
	 */
	private function hit( string $url ): array {
		$start = microtime( true );
		$args  = array(
			'timeout'     => 5,
			'sslverify'   => true,
			'redirection' => 2,
			'headers'     => array( 'Range' => 'bytes=0-0' ),
		);

		$response = ( $this->http_get )( $url, $args );
		$latency  = (int) max( 0, round( ( microtime( true ) - $start ) * 1000 ) );

		if ( $this->is_error( $response ) || ! is_array( $response ) ) {
			return array(
				'ok'         => false,
				'latency_ms' => $latency,
			);
		}

		$code = $this->response_code( $response );

		return array(
			'ok'         => $code >= 200 && $code < 400,
			'latency_ms' => $latency,
		);
	}

	/**
	 * Frozen result object.
	 *
	 * @param string      $target      Human-readable host.
	 * @param string      $result      ok|fallback|down.
	 * @param int|null    $latency_ms  Probe milliseconds.
	 * @param string      $checked_at  UTC ISO 8601.
	 * @param string|null $suggestion  Only when not ok.
	 * @return array{target: string, result: string, latency_ms: int|null, checked_at: string, suggestion: string|null}
	 */
	private function row( string $target, string $result, $latency_ms, string $checked_at, $suggestion ): array {
		return array(
			'target'     => $target,
			'result'     => $result,
			'latency_ms' => $latency_ms,
			'checked_at' => $checked_at,
			'suggestion' => $suggestion,
		);
	}

	/**
	 * Drop malformed stored rows. Do not invent ok.
	 *
	 * @param array<string, mixed> $row Stored row.
	 * @return array{target: string, result: string, latency_ms: int|null, checked_at: string, suggestion: string|null}|null
	 */
	private function normalize( array $row ) {
		if ( ! isset( $row['target'], $row['result'], $row['checked_at'] ) ) {
			return null;
		}

		$result = (string) $row['result'];
		if ( ! in_array( $result, array( self::RESULT_OK, self::RESULT_FALLBACK, self::RESULT_DOWN ), true ) ) {
			return null;
		}

		$latency = $row['latency_ms'] ?? null;
		if ( null !== $latency ) {
			$latency = (int) $latency;
		}

		$suggestion = $row['suggestion'] ?? null;
		if ( self::RESULT_OK === $result ) {
			$suggestion = null;
		} elseif ( null !== $suggestion ) {
			$suggestion = (string) $suggestion;
		}

		return $this->row(
			(string) $row['target'],
			$result,
			$latency,
			(string) $row['checked_at'],
			$suggestion
		);
	}

	/**
	 * Avatar probe for the configured line, or null when off.
	 *
	 * @return array{target: string, url: string, upstream_url: string}|null
	 */
	private function avatar_target() {
		$mode = 'cravatar_cn';
		if ( is_object( $this->config ) && method_exists( $this->config, 'get' ) ) {
			$mode = (string) $this->config->get( 'connectivity.avatar', 'cravatar_cn' );
		}

		$host = '';
		if ( 'cravatar_cn' === $mode ) {
			$host = 'cn.cravatar.com';
		} elseif ( 'cravatar_global' === $mode ) {
			$host = 'en.cravatar.com';
		} elseif ( 'weavatar' === $mode ) {
			$host = 'weavatar.com';
		}

		if ( '' === $host ) {
			return null;
		}

		return array(
			'target'       => $host,
			'url'          => 'https://' . $host . '/avatar/',
			'upstream_url' => 'https://secure.gravatar.com/avatar/',
		);
	}

	/**
	 * Whether $response is a transport failure.
	 *
	 * @param mixed $response HTTP result.
	 */
	private function is_error( $response ): bool {
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $response ) ) {
			return true;
		}

		return $response instanceof \WP_Error;
	}

	/**
	 * Status code from a canned `{code}` array or a WP HTTP response.
	 *
	 * @param array<string, mixed> $response Response.
	 */
	private function response_code( array $response ): int {
		if ( isset( $response['code'] ) ) {
			return (int) $response['code'];
		}

		if ( function_exists( 'wp_remote_retrieve_response_code' ) ) {
			return (int) wp_remote_retrieve_response_code( $response );
		}

		if ( isset( $response['response']['code'] ) ) {
			return (int) $response['response']['code'];
		}

		return 0;
	}
}
