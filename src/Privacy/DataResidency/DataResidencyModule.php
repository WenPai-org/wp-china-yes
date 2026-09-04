<?php
/**
 * Outbound host table: record and ignore now; reroute when ingest is ready.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Privacy\DataResidency;

use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Core\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Matches pre_http_request against the signed ruleset.
 *
 * Users cannot add hosts. B-tier stores host / data_class / count / last_seen only.
 */
final class DataResidencyModule implements Module {

	/**
	 * Option that holds the B-tier host log. Not a user setting.
	 *
	 * @since 4.0.0
	 */
	public const LOG_OPTION = 'wpcy_residency_log';

	/**
	 * Signed host table.
	 *
	 * @var Ruleset
	 */
	private Ruleset $ruleset;

	/**
	 * Whether the cloud-bridge ingest health probe currently succeeds.
	 *
	 * M1-09: always false. Health URL and interval are 待定（M0）.
	 * PHP 7.4 has no union property types.
	 *
	 * @var bool|callable
	 */
	private $ingest_ready;

	/**
	 * In-memory B-tier log keyed by host.
	 *
	 * @var array<string, array{host: string, data_class: string, count: int, last_seen: string}>
	 */
	private array $log = array();

	/**
	 * Create the module. Constructor does not register hooks.
	 *
	 * @since 4.0.0
	 *
	 * @param Ruleset|null  $ruleset      Host table. Null loads the shipped baseline.
	 * @param bool|callable $ingest_ready Probe. False in this task.
	 */
	public function __construct( $ruleset = null, $ingest_ready = false ) {
		$this->ruleset      = $ruleset instanceof Ruleset ? $ruleset : new Ruleset();
		$this->ingest_ready = $ingest_ready;
		$this->log          = $this->load_log();
	}

	/**
	 * Module id.
	 *
	 * @since 4.0.0
	 */
	public function id(): string {
		return 'privacy.data_residency';
	}

	/**
	 * HTTP leaves from every scene.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function contexts(): array {
		return Environment::CONTEXTS;
	}

	/**
	 * Hook pre_http_request.
	 *
	 * @since 4.0.0
	 */
	public function register(): void {
		add_filter( 'pre_http_request', array( $this, 'filter_pre_http_request' ), 10, 3 );
	}

	/**
	 * Match $url and apply record / ignore / reroute.
	 *
	 * Reroute with enabled_when=ingest_ready does not rewrite when the probe is false.
	 * That miss does not fall back to record and does not copy-then-forward.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed                $preempt Short-circuit value from earlier filters.
	 * @param array<string, mixed> $args    Request arguments.
	 * @param string               $url     Request URL.
	 * @return mixed
	 */
	public function filter_pre_http_request( $preempt, $args, $url ) {
		unset( $args );
		if ( '' === $url ) {
			return $preempt;
		}

		$rule = $this->ruleset->match( $url );
		if ( ! is_array( $rule ) ) {
			return $preempt;
		}

		$action = isset( $rule['action'] ) && is_string( $rule['action'] ) ? $rule['action'] : '';

		if ( 'record' === $action ) {
			$this->record( $url, $rule );
			return $preempt;
		}

		if ( 'ignore' === $action ) {
			return $preempt;
		}

		if ( 'reroute' === $action ) {
			if ( $this->reroute_enabled( $rule ) ) {
				return $this->reroute( $preempt, $url, $rule );
			}
			return $preempt;
		}

		return $preempt;
	}

	/**
	 * B-tier log: host / data_class / count / last_seen only.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, array{host: string, data_class: string, count: int, last_seen: string}>
	 */
	public function log(): array {
		return $this->log;
	}

	/**
	 * Loaded ruleset.
	 *
	 * @since 4.0.0
	 */
	public function ruleset(): Ruleset {
		return $this->ruleset;
	}

	/**
	 * Whether a reroute rule may rewrite the URL.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $rule Matched rule.
	 */
	public function reroute_enabled( array $rule ): bool {
		$when = isset( $rule['enabled_when'] ) && is_string( $rule['enabled_when'] )
			? $rule['enabled_when']
			: 'ingest_ready';

		if ( 'always' === $when ) {
			return $this->target_is_usable( $rule );
		}

		if ( 'ingest_ready' === $when ) {
			return $this->is_ingest_ready() && $this->target_is_usable( $rule );
		}

		return false;
	}

	/**
	 * Append a B-tier hit. Never stores URL, query string, or body.
	 *
	 * @param string               $url  Request URL (parsed, not stored).
	 * @param array<string, mixed> $rule Matched rule.
	 */
	private function record( string $url, array $rule ): void {
		$host = $this->request_host( $url );
		if ( '' === $host ) {
			return;
		}

		$data_class = isset( $rule['data_class'] ) && is_string( $rule['data_class'] )
			? $rule['data_class']
			: '';

		$now = gmdate( 'Y-m-d\\TH:i:s\\Z' );
		if ( ! isset( $this->log[ $host ] ) ) {
			$this->log[ $host ] = array(
				'host'       => $host,
				'data_class' => $data_class,
				'count'      => 0,
				'last_seen'  => $now,
			);
		}

		++$this->log[ $host ]['count'];
		$this->log[ $host ]['last_seen']  = $now;
		$this->log[ $host ]['data_class'] = $data_class;

		$this->persist_log();
	}

	/**
	 * Reroute branch: issue one request to target, original host is not contacted.
	 *
	 * Unreachable in M1-09 because ingest_ready is false. Kept so M3 can flip the probe.
	 *
	 * @param mixed                $preempt Prior short-circuit.
	 * @param string               $url     Original URL.
	 * @param array<string, mixed> $rule    Matched rule.
	 * @return mixed
	 */
	private function reroute( $preempt, string $url, array $rule ) {
		$target = isset( $rule['target'] ) && is_string( $rule['target'] ) ? $rule['target'] : '';
		if ( '' === $target ) {
			return $preempt;
		}

		$rewritten = $this->rewrite_url( $url, $target );
		if ( $rewritten === $url ) {
			return $preempt;
		}

		if ( ! function_exists( 'wp_remote_request' ) ) {
			return $preempt;
		}

		remove_filter( 'pre_http_request', array( $this, 'filter_pre_http_request' ), 10 );
		$response = wp_remote_request(
			$rewritten,
			array(
				'timeout'   => 10,
				'sslverify' => true,
			)
		);
		add_filter( 'pre_http_request', array( $this, 'filter_pre_http_request' ), 10, 3 );

		return $response;
	}

	/**
	 * Build the rewritten URL. Path from the original is kept when the target has none.
	 *
	 * @param string $url    Original URL.
	 * @param string $target Absolute HTTPS target.
	 */
	private function rewrite_url( string $url, string $target ): string {
		$target_parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $target ) : parse_url( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- unit bootstrap has no WordPress.
		if ( ! is_array( $target_parts ) || empty( $target_parts['host'] ) ) {
			return $url;
		}

		$orig        = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- unit bootstrap has no WordPress.
		$target_path = isset( $target_parts['path'] ) ? (string) $target_parts['path'] : '';
		$orig_path   = is_array( $orig ) && isset( $orig['path'] ) ? (string) $orig['path'] : '/';
		$path        = ( '' !== $target_path && '/' !== $target_path ) ? $target_path : $orig_path;

		$scheme = isset( $target_parts['scheme'] ) ? $target_parts['scheme'] : 'https';
		$host   = $target_parts['host'];
		$port   = isset( $target_parts['port'] ) ? ':' . $target_parts['port'] : '';

		return $scheme . '://' . $host . $port . $path;
	}

	/**
	 * Probe result. Default false until the cloud-bridge health contract exists.
	 */
	private function is_ingest_ready(): bool {
		if ( is_callable( $this->ingest_ready ) ) {
			return (bool) call_user_func( $this->ingest_ready );
		}
		return (bool) $this->ingest_ready;
	}

	/**
	 * Reroute needs a non-empty HTTPS target.
	 *
	 * @param array<string, mixed> $rule Rule.
	 */
	private function target_is_usable( array $rule ): bool {
		$target = isset( $rule['target'] ) && is_string( $rule['target'] ) ? $rule['target'] : '';
		return 0 === strpos( $target, 'https://' );
	}

	/**
	 * Host only. Query string is discarded.
	 *
	 * @param string $url Request URL.
	 */
	private function request_host( string $url ): string {
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- unit bootstrap has no WordPress.
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}
		return strtolower( (string) $parts['host'] );
	}

	/**
	 * Load the persisted B-tier log.
	 *
	 * @return array<string, array{host: string, data_class: string, count: int, last_seen: string}>
	 */
	private function load_log(): array {
		if ( ! function_exists( 'get_option' ) ) {
			return array();
		}
		$stored = get_option( self::LOG_OPTION, array() );
		return is_array( $stored ) ? $this->sanitize_log( $stored ) : array();
	}

	/**
	 * Persist the B-tier log. Never writes URL or body.
	 */
	private function persist_log(): void {
		if ( function_exists( 'update_option' ) ) {
			update_option( self::LOG_OPTION, $this->sanitize_log( $this->log ), false );
		}
	}

	/**
	 * Keep only the four allowed fields.
	 *
	 * @param array<mixed> $raw Raw log.
	 * @return array<string, array{host: string, data_class: string, count: int, last_seen: string}>
	 */
	private function sanitize_log( array $raw ): array {
		$clean = array();
		foreach ( $raw as $key => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$host = isset( $row['host'] ) && is_string( $row['host'] ) ? strtolower( $row['host'] ) : '';
			if ( '' === $host ) {
				continue;
			}
			$clean[ $host ] = array(
				'host'       => $host,
				'data_class' => isset( $row['data_class'] ) && is_string( $row['data_class'] ) ? $row['data_class'] : '',
				'count'      => isset( $row['count'] ) ? (int) $row['count'] : 0,
				'last_seen'  => isset( $row['last_seen'] ) && is_string( $row['last_seen'] ) ? $row['last_seen'] : '',
			);
			unset( $key );
		}
		return $clean;
	}
}
