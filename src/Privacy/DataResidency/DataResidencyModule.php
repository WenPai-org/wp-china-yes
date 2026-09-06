<?php
/**
 * Outbound host table: record and ignore now; reroute when ingest is ready.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Privacy\DataResidency;

use WenPai\ChinaYes\Core\Config;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Core\Module;
use WP_Error;

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
	 * Optional config for profile gate (scheme A + insurance). Null → domestic.
	 *
	 * @var Config|null
	 */
	private $config;

	/**
	 * In-memory B-tier log keyed by host.
	 *
	 * @var array<string, array{host: string, data_class: string, count: int, last_seen: string}>
	 */
	private array $log = array();

	/**
	 * Noise rows skipped this request (L0 / .org / CDN). Not persisted.
	 *
	 * @var list<array{host: string, match: string, reason: string}>
	 */
	private array $noise_skipped = array();

	/**
	 * Create the module. Constructor does not register hooks.
	 *
	 * @since 4.0.0
	 *
	 * @param Ruleset|null  $ruleset      Host table. Null loads the shipped baseline.
	 * @param bool|callable $ingest_ready Probe. False in this task.
	 * @param Config|null   $config       Effective settings. Null treats profile as domestic.
	 */
	public function __construct( $ruleset = null, $ingest_ready = false, $config = null ) {
		$this->ruleset      = $ruleset instanceof Ruleset ? $ruleset : new Ruleset();
		$this->ingest_ready = $ingest_ready;
		$this->config       = $config instanceof Config ? $config : null;
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
	 * Hook pre_http_request: L0 at 5, L1 at 10, noise at 12.
	 *
	 * @since 4.0.0
	 */
	public function register(): void {
		add_filter( 'pre_http_request', array( $this, 'filter_l0' ), 5, 3 );
		add_filter( 'pre_http_request', array( $this, 'filter_pre_http_request' ), 10, 3 );
		add_filter( 'pre_http_request', array( $this, 'filter_noise_block' ), 12, 3 );
	}

	/**
	 * L0: protected hosts always leave. No reroute, no record.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed                $preempt Short-circuit value from earlier filters.
	 * @param array<string, mixed> $args    Request arguments.
	 * @param string               $url     Request URL.
	 * @return mixed
	 */
	public function filter_l0( $preempt, $args, $url ) {
		unset( $args );
		if ( '' === $url ) {
			return $preempt;
		}
		if ( $this->ruleset->is_protected( $this->request_host( $url ) ) ) {
			return $preempt;
		}

		return $preempt;
	}

	/**
	 * Match $url and apply record / ignore / reroute.
	 *
	 * Reroute with enabled_when=ingest_ready does not rewrite when the probe is false.
	 * That miss does not fall back to record and does not copy-then-forward.
	 * L0 hosts skip A/B entirely.
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

		if ( $this->ruleset->is_protected( $this->request_host( $url ) ) ) {
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
	 * Noise pack between L1 (10) and L2 (15). Does not override L0 or L1 A/B.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed                $preempt Short-circuit value from earlier filters.
	 * @param array<string, mixed> $args    Request arguments.
	 * @param string               $url     Request URL.
	 * @return mixed
	 */
	public function filter_noise_block( $preempt, $args, $url ) {
		unset( $args );
		if ( false !== $preempt ) {
			return $preempt;
		}
		if ( '' === $url || ! $this->noise_enabled() ) {
			return $preempt;
		}

		$host = $this->request_host( $url );
		if ( '' === $host || $this->ruleset->is_protected( $host ) ) {
			return $preempt;
		}

		if ( $this->l1_claimed( $url ) ) {
			return $preempt;
		}

		$hit = $this->ruleset->noise_match( $host );
		if ( ! is_array( $hit ) ) {
			return $preempt;
		}

		$skipped = isset( $hit['skipped'] ) && is_string( $hit['skipped'] ) ? $hit['skipped'] : '';
		if ( '' !== $skipped ) {
			$hit_host              = isset( $hit['host'] ) && is_string( $hit['host'] ) ? $hit['host'] : $host;
			$hit_match             = isset( $hit['match'] ) && is_string( $hit['match'] ) ? $hit['match'] : 'exact';
			$this->noise_skipped[] = array(
				'host'   => $hit_host,
				'match'  => $hit_match,
				'reason' => $skipped,
			);
			return $preempt;
		}

		if ( function_exists( 'do_action' ) ) {
			do_action( 'wpcy_stats_increment', 'outbound_blocked', 1 );
		}
		return new WP_Error( 'wpcy_noise_block_blocked', 'wpcy_noise_block_blocked' );
	}

	/**
	 * Noise rows ignored at runtime (not persisted).
	 *
	 * @since 4.0.0
	 *
	 * @return list<array{host: string, match: string, reason: string}>
	 */
	public function noise_skipped(): array {
		return $this->noise_skipped;
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
		if ( 'domestic' !== $this->current_profile() ) {
			return false;
		}

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
	 * Effective profile. Missing config → domestic (upgrade / unit default).
	 *
	 * Does not query geo. Scheme A + insurance: user-declared domestic wins.
	 */
	private function current_profile(): string {
		if ( ! $this->config instanceof Config ) {
			return 'domestic';
		}
		$profile = $this->config->get( 'profile', 'domestic' );

		return in_array( $profile, array( 'domestic', 'crossborder', 'mixed' ), true )
			? $profile
			: 'domestic';
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
	 * Whether L1 A/B already claimed this URL (reroute or record). C ignore does not claim.
	 *
	 * @param string $url Request URL.
	 */
	public function l1_claimed( string $url ): bool {
		$rule = $this->ruleset->match( $url );
		if ( ! is_array( $rule ) ) {
			return false;
		}
		$action = isset( $rule['action'] ) && is_string( $rule['action'] ) ? $rule['action'] : '';

		return in_array( $action, array( 'reroute', 'record' ), true );
	}

	/**
	 * User switch for the signed noise pack. Off in recovery_mode.
	 *
	 * @since 4.0.0
	 */
	public function noise_enabled(): bool {
		if ( ! $this->config instanceof Config ) {
			return true;
		}
		if ( true === $this->config->get( 'recovery_mode', false ) ) {
			return false;
		}

		return true === $this->config->get( 'modules.noise_block.enabled', true );
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
