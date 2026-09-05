<?php
/**
 * Entitlements fetch and cache. Default does not contact production.
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
	 * Restricted-layer service slugs. Baseline modules do not consult this filter.
	 *
	 * @since 4.0.0
	 * @var list<string>
	 */
	public const RESTRICTED_SERVICES = array(
		'windfonts',
		'admincdn',
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
		add_filter( 'wpcy_entitlement_allows', array( $this, 'filter_allows' ), 10, 2 );
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
	 * @return array{entitlements: list<array<string, mixed>>}
	 */
	public function snapshot(): array {
		return array(
			'entitlements' => $this->items(),
		);
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

		$credential = $this->repository->get_credential();
		$result     = $this->client->fetch( $hash, is_string( $credential ) ? $credential : '' );
		if ( is_wp_error( $result ) ) {
			return null;
		}

		$this->store( $result );
		return $result;
	}

	/**
	 * Persist fresh (1h) and stale (72h) copies.
	 *
	 * @param list<array<string, mixed>> $items Normalized rows.
	 */
	private function store( array $items ): void {
		$payload = array(
			'fetched_at'   => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'entitlements' => $items,
		);
		call_user_func( $this->set_transient, self::TRANSIENT_FRESH, $payload, self::TTL_FRESH );
		call_user_func( $this->set_transient, self::TRANSIENT_STALE, $payload, self::TTL_STALE );
	}

	/**
	 * Entitlements list from a transient, or null when missing / malformed.
	 *
	 * @param string $key Transient name.
	 * @return list<array<string, mixed>>|null
	 */
	private function read_list( string $key ) {
		$stored = call_user_func( $this->get_transient, $key );
		if ( ! is_array( $stored ) || ! isset( $stored['entitlements'] ) || ! is_array( $stored['entitlements'] ) ) {
			return null;
		}

		$items = array();
		foreach ( $stored['entitlements'] as $row ) {
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
