<?php
/**
 * Entitlements fetch and cache (1h fresh, 72h stale fallback).
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Services\Entitlements;

use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Core\Logger;
use WenPai\ChinaYes\Core\Module;
use WenPai\ChinaYes\Rest\EntitlementsController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 1h fresh transient, 72h stale fallback. Empty list when unbound or unreachable.
 */
final class EntitlementsModule implements Module {

	/**
	 * Fresh cache (1h).
	 *
	 * @since 4.0.0
	 */
	public const TRANSIENT_FRESH = 'wpcy_entitlements';

	/**
	 * Stale fallback (72h). Used only when the server is unreachable.
	 *
	 * @since 4.0.0
	 */
	public const TRANSIENT_STALE = 'wpcy_entitlements_stale';

	/**
	 * Negative backoff transient set after a failed guarded refresh.
	 *
	 * @since 4.0.0
	 */
	public const TRANSIENT_BACKOFF = 'wpcy_entitlements_backoff';

	/**
	 * Fresh TTL in seconds.
	 *
	 * @since 4.0.0
	 */
	public const TTL_FRESH = 3600;

	/**
	 * Stale TTL in seconds (72 hours).
	 *
	 * @since 4.0.0
	 */
	public const TTL_STALE = 259200;

	/**
	 * Short negative backoff TTL in seconds (5 minutes) so a down upstream
	 * never stalls repeated admin loads.
	 *
	 * @since 4.0.0
	 */
	public const TTL_BACKOFF = 300;

	/**
	 * Restricted-layer service slugs. Baseline modules do not consult this filter.
	 *
	 * @since 4.0.0
	 * @var list<string>
	 */
	public const RESTRICTED_SERVICES = array(
		'windfonts',
		'wpmirror-packages',
		'motusnap',
	);

	/**
	 * Settings / identity access.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * HTTP client.
	 *
	 * @var Client
	 */
	private Client $client;

	/**
	 * Transient reader. Defaults to get_transient.
	 *
	 * Callable is not a valid PHP 7.4 property type.
	 *
	 * @var callable
	 */
	private $get_transient;

	/**
	 * Transient writer. Defaults to set_transient.
	 *
	 * Callable is not a valid PHP 7.4 property type.
	 *
	 * @var callable
	 */
	private $set_transient;

	/**
	 * Constructor. Does not register hooks and does not send HTTP.
	 *
	 * @since 4.0.0
	 *
	 * @param Repository    $repository    Identity access.
	 * @param Logger|null   $logger        Failure sink.
	 * @param Client|null   $client        HTTP client. Null builds the default.
	 * @param callable|null $get_transient Transient reader.
	 * @param callable|null $set_transient Transient writer.
	 */
	public function __construct( Repository $repository, $logger = null, $client = null, $get_transient = null, $set_transient = null ) {
		$this->repository    = $repository;
		$log                 = $logger instanceof Logger ? $logger : null;
		$this->client        = $client instanceof Client ? $client : new Client( $log );
		$this->get_transient = null !== $get_transient ? $get_transient : 'get_transient';
		$this->set_transient = null !== $set_transient ? $set_transient : 'set_transient';
	}

	/**
	 * Module id.
	 *
	 * @since 4.0.0
	 */
	public function id(): string {
		return 'services.entitlements';
	}

	/**
	 * Consulted from REST, cron, and admin.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function contexts(): array {
		return Environment::CONTEXTS;
	}

	/**
	 * Binding identity is read via Repository; no register-order edge.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function dependencies(): array {
		return array();
	}

	/**
	 * Hook REST. Constructor does not register hooks and does not fetch.
	 *
	 * @since 4.0.0
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'wpcy_site_bound', array( $this, 'on_site_bound' ) );
		add_action( 'wpcy_entitlements_maybe_refresh', array( $this, 'on_maybe_refresh' ) );
		add_filter( 'wpcy_entitlement_allows', array( $this, 'filter_allows' ), 10, 2 );
		add_filter( 'wenpai_site_entitlements', array( $this, 'filter_suite_entitlements' ) );
		add_filter( 'wenpai_has_entitlement', array( $this, 'filter_has_entitlement' ), 10, 2 );
	}

	/**
	 * Action wrapper for guarded cold-cache refresh (`wpcy_entitlements_maybe_refresh`).
	 *
	 * @since 4.0.0
	 */
	public function on_maybe_refresh(): void {
		$this->maybe_refresh_cold_cache();
	}

	/**
	 * Warm entitlements cache immediately after site binding completes.
	 *
	 * Non-fatal: upstream errors or throwables never interrupt the binding response.
	 *
	 * @since 4.0.0
	 */
	public function on_site_bound(): void {
		$this->clear_backoff();
		if ( ! $this->can_attempt_refresh() ) {
			return;
		}
		try {
			$fetched = $this->refresh();
			if ( null === $fetched ) {
				$this->set_backoff();
			}
		} catch ( \Throwable $e ) {
			$this->set_backoff();
		}
	}

	/**
	 * Run a single guarded refresh when the site is bound and fresh cache is missing.
	 *
	 * Skips when fresh cache is warm, backoff is active, site is unbound, or outbound is off.
	 * Writes a short negative-backoff transient on failure so repeated admin loads stay fast.
	 *
	 * @since 4.0.0
	 *
	 * @return bool True when fresh cache was populated, false when skipped or failed.
	 */
	public function maybe_refresh_cold_cache(): bool {
		if ( is_array( $this->read_list( self::TRANSIENT_FRESH ) ) ) {
			return false;
		}
		if ( $this->in_backoff() || ! $this->can_attempt_refresh() ) {
			return false;
		}
		try {
			$fetched = $this->refresh();
			if ( is_array( $fetched ) ) {
				return true;
			}
			$this->set_backoff();
			return false;
		} catch ( \Throwable $e ) {
			$this->set_backoff();
			return false;
		}
	}

	/**
	 * Cached suite-wide entitlement list (1h fresh + 72h offline fallback, render-safe without remote HTTP).
	 *
	 * @since 4.0.0
	 *
	 * @return list<array<string, mixed>>
	 */
	public function cached_items(): array {
		$fresh = $this->read_list( self::TRANSIENT_FRESH );
		if ( is_array( $fresh ) ) {
			return $fresh;
		}
		$stale = $this->read_list( self::TRANSIENT_STALE );
		if ( is_array( $stale ) ) {
			return $stale;
		}
		return array();
	}

	/**
	 * Cached optional Mind Credits snapshot (1h fresh + 72h offline fallback, render-safe without remote HTTP).
	 *
	 * @since 4.0.0
	 *
	 * @return array{credits_balance: int, credits_limit_month: int, credits_used_month: int}|null
	 */
	public function cached_mind() {
		$fresh = $this->read_mind( self::TRANSIENT_FRESH );
		if ( is_array( $fresh ) ) {
			return $fresh;
		}
		$stale = $this->read_mind( self::TRANSIENT_STALE );
		if ( is_array( $stale ) ) {
			return $stale;
		}
		return null;
	}

	/**
	 * Expose cached entitlement rows (plus optional `mind` credits summary) across the WenPai plugin suite.
	 *
	 * Preserves 0-indexed entitlement rows while attaching string key `'mind'` when present so
	 * WPMind reads `credits_balance`, `credits_limit_month`, and `credits_used_month` directly.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $items Default items from caller.
	 * @return array<int|string, mixed>
	 */
	public function filter_suite_entitlements( $items = array() ): array {
		unset( $items );
		$out  = $this->cached_items();
		$mind = $this->cached_mind();
		if ( is_array( $mind ) ) {
			$out['mind'] = $mind;
		}
		return $out;
	}

	/**
	 * Check whether an active entitlement exists in the 1h fresh / 72h stale suite cache.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $active  Default flag from caller.
	 * @param mixed $service Target service slug (e.g. 'oem', 'mind', 'wpmind-pro', 'windfonts').
	 */
	public function filter_has_entitlement( $active, $service = '' ): bool {
		if ( (bool) $active ) {
			return true;
		}
		$target = is_string( $service ) ? strtolower( trim( $service ) ) : '';
		if ( '' === $target ) {
			return false;
		}
		if ( 'mind' === $target ) {
			$mind = $this->cached_mind();
			if ( is_array( $mind ) && $mind['credits_balance'] > 0 ) {
				return true;
			}
		}
		foreach ( $this->cached_items() as $row ) {
			$slug   = isset( $row['service'] ) && is_string( $row['service'] ) ? strtolower( trim( $row['service'] ) ) : '';
			$status = isset( $row['status'] ) && is_string( $row['status'] ) ? strtolower( trim( $row['status'] ) ) : '';
			if ( $slug === $target && in_array( $status, array( 'active', 'ok', 'granted' ), true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Register GET /entitlements on wpcy/v1.
	 *
	 * @since 4.0.0
	 */
	public function register_routes(): void {
		$controller = new EntitlementsController( $this );
		$controller->register_routes();
	}

	/**
	 * Cached copy for GET /entitlements. Never a 5xx body.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function snapshot(): array {
		$items = $this->items();
		$out   = array(
			'entitlements' => $items,
		);
		$mind  = $this->cached_mind();
		if ( is_array( $mind ) ) {
			$out['mind'] = $mind;
		}
		return $out;
	}

	/**
	 * Entitlement rows. Fresh cache, else fetch, else 72h stale, else empty.
	 *
	 * @since 4.0.0
	 *
	 * @return list<array<string, mixed>>
	 */
	public function items(): array {
		$fresh = $this->read_list( self::TRANSIENT_FRESH );
		if ( is_array( $fresh ) ) {
			return $fresh;
		}

		$fetched = $this->refresh();
		if ( is_array( $fetched ) ) {
			return $fetched;
		}

		$stale = $this->read_list( self::TRANSIENT_STALE );
		if ( is_array( $stale ) ) {
			return $stale;
		}

		return array();
	}

	/**
	 * Degrade hooks for other modules.
	 *
	 * @since 4.0.0
	 */
	public function degrade(): Degrade {
		return new Degrade( $this );
	}

	/**
	 * `wpcy_entitlement_allows` for restricted services. Upstream fallback → false.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $allowed Default allow flag.
	 * @param mixed $service Service slug.
	 */
	public function filter_allows( $allowed, $service = '' ): bool {
		$slug = is_string( $service ) ? $service : '';
		if ( ! in_array( $slug, self::RESTRICTED_SERVICES, true ) ) {
			return (bool) $allowed;
		}

		if ( $this->degrade()->shouldUseUpstream( $slug ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Contact the license server when outbound is allowed and the site is bound.
	 *
	 * @return list<array<string, mixed>>|null Null on failure (caller may use stale).
	 */
	private function refresh() {
		$identity = $this->repository->get_identity();
		$binding  = isset( $identity['binding'] ) && is_array( $identity['binding'] ) ? $identity['binding'] : array();
		$status   = isset( $binding['status'] ) && is_string( $binding['status'] ) ? $binding['status'] : '';
		if ( 'bound' !== $status ) {
			return null;
		}

		$hash = $this->site_hash();
		if ( '' === $hash ) {
			return null;
		}

		if ( ! $this->client->outbound_allowed() ) {
			return null;
		}

		$credential = $this->repository->get_credential();
		$envelope   = $this->client->fetch_envelope( $hash, is_string( $credential ) ? $credential : '' );
		if ( is_wp_error( $envelope ) ) {
			return null;
		}

		$this->store( $envelope['entitlements'], $envelope['mind'] );
		return $envelope['entitlements'];
	}

	/**
	 * Persist fresh (1h) and stale (72h) copies.
	 *
	 * @param list<array<string, mixed>>                                                          $items Normalized rows.
	 * @param array{credits_balance: int, credits_limit_month: int, credits_used_month: int}|null $mind  Optional Mind credits snapshot.
	 */
	private function store( array $items, $mind = null ): void {
		$payload = array(
			'fetched_at'   => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'entitlements' => $items,
		);
		if ( is_array( $mind ) ) {
			$payload['mind'] = $mind;
		}
		call_user_func( $this->set_transient, self::TRANSIENT_FRESH, $payload, self::TTL_FRESH );
		call_user_func( $this->set_transient, self::TRANSIENT_STALE, $payload, self::TTL_STALE );
		$this->clear_backoff();
	}

	/**
	 * Whether preconditions for an outbound refresh are satisfied (bound + hash + outbound allowed).
	 */
	private function can_attempt_refresh(): bool {
		$identity = $this->repository->get_identity();
		$binding  = isset( $identity['binding'] ) && is_array( $identity['binding'] ) ? $identity['binding'] : array();
		$status   = isset( $binding['status'] ) && is_string( $binding['status'] ) ? $binding['status'] : '';
		if ( 'bound' !== $status || '' === $this->site_hash() ) {
			return false;
		}

		return $this->client->outbound_allowed();
	}

	/**
	 * Whether a recent failed guarded refresh placed us in short negative backoff.
	 */
	private function in_backoff(): bool {
		return ! empty( call_user_func( $this->get_transient, self::TRANSIENT_BACKOFF ) );
	}

	/**
	 * Record a short negative-backoff transient after an upstream refresh failure.
	 */
	private function set_backoff(): void {
		call_user_func( $this->set_transient, self::TRANSIENT_BACKOFF, 1, self::TTL_BACKOFF );
	}

	/**
	 * Clear any active negative-backoff transient.
	 */
	private function clear_backoff(): void {
		if ( 'get_transient' === $this->get_transient && function_exists( 'delete_transient' ) ) {
			delete_transient( self::TRANSIENT_BACKOFF );
			return;
		}
		call_user_func( $this->set_transient, self::TRANSIENT_BACKOFF, 0, 1 );
	}

	/**
	 * Mind credits snapshot from a transient, or null when absent / malformed.
	 *
	 * @param string $key Transient name.
	 * @return array{credits_balance: int, credits_limit_month: int, credits_used_month: int}|null
	 */
	private function read_mind( string $key ) {
		$stored = call_user_func( $this->get_transient, $key );
		if ( ! is_array( $stored ) || ! isset( $stored['mind'] ) || ! is_array( $stored['mind'] ) ) {
			return null;
		}
		$raw = $stored['mind'];
		if ( ! isset( $raw['credits_balance'] ) || ! is_numeric( $raw['credits_balance'] ) ) {
			return null;
		}

		return array(
			'credits_balance'     => max( 0, (int) $raw['credits_balance'] ),
			'credits_limit_month' => isset( $raw['credits_limit_month'] ) && is_numeric( $raw['credits_limit_month'] )
				? max( 0, (int) $raw['credits_limit_month'] )
				: 0,
			'credits_used_month'  => isset( $raw['credits_used_month'] ) && is_numeric( $raw['credits_used_month'] )
				? max( 0, (int) $raw['credits_used_month'] )
				: 0,
		);
	}

	/**
	 * Entitlements list from a transient, or null when missing / malformed.
	 *
	 * @param string $key Transient name.
	 * @return list<array<string, mixed>>|null
	 */
	private function read_list( string $key ) {
		$stored = call_user_func( $this->get_transient, $key );
		if ( ! is_array( $stored ) ) {
			return null;
		}
		$source = isset( $stored['entitlements'] ) && is_array( $stored['entitlements'] )
			? $stored['entitlements']
			: ( array_key_exists( 'entitlements', $stored ) ? null : $stored );
		if ( ! is_array( $source ) ) {
			return null;
		}

		$items = array();
		foreach ( $source as $row ) {
			if ( is_array( $row ) ) {
				$items[] = $row;
			}
		}

		return $items;
	}

	/**
	 * Bound site_hash or empty.
	 */
	private function site_hash(): string {
		$identity = $this->repository->get_identity();
		$binding  = isset( $identity['binding'] ) && is_array( $identity['binding'] ) ? $identity['binding'] : array();
		$hash     = isset( $binding['site_hash'] ) && is_string( $binding['site_hash'] ) ? $binding['site_hash'] : '';

		return Client::sanitize_hash( $hash );
	}
}
