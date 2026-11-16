<?php
/**
 * Service-catalog fetch and cache. Default does not contact production.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Services\Catalog;

use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Core\Logger;
use WenPai\ChinaYes\Core\Module;
use WenPai\ChinaYes\Rest\CatalogController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 1h fresh transient, 72h stale fallback. Empty catalog when unbound or unreachable.
 */
final class CatalogModule implements Module {

	/**
	 * Fresh cache (1h).
	 *
	 * @since 4.0.0
	 */
	public const TRANSIENT_FRESH = 'wpcy_catalog';

	/**
	 * Stale fallback (72h). Used only when the server is unreachable.
	 *
	 * @since 4.0.0
	 */
	public const TRANSIENT_STALE = 'wpcy_catalog_stale';

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
	 * Settings / identity access.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * HTTP client.
	 *
	 * @var CatalogClient
	 */
	private CatalogClient $client;

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
	 * @param Repository         $repository    Identity access.
	 * @param Logger|null        $logger        Failure sink.
	 * @param CatalogClient|null $client      HTTP client. Null builds the default.
	 * @param callable|null      $get_transient Transient reader.
	 * @param callable|null      $set_transient Transient writer.
	 */
	public function __construct( Repository $repository, $logger = null, $client = null, $get_transient = null, $set_transient = null ) {
		$this->repository    = $repository;
		$log                 = $logger instanceof Logger ? $logger : null;
		$this->client        = $client instanceof CatalogClient ? $client : new CatalogClient( $log );
		$this->get_transient = null !== $get_transient ? $get_transient : 'get_transient';
		$this->set_transient = null !== $set_transient ? $set_transient : 'set_transient';
	}

	/**
	 * Module id.
	 *
	 * @since 4.0.0
	 */
	public function id(): string {
		return 'services.catalog';
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
	}

	/**
	 * Register GET /catalog on wpcy/v1.
	 *
	 * @since 4.0.0
	 */
	public function register_routes(): void {
		$controller = new CatalogController( $this );
		$controller->register_routes();
	}

	/**
	 * Cached copy for GET /catalog. Never a 5xx body.
	 *
	 * @since 4.0.0
	 *
	 * @return array{catalog_version: string, items: list<array<string, mixed>>, stale: bool, fetched_at: string}
	 */
	public function snapshot(): array {
		$payload = $this->payload();
		return array(
			'catalog_version' => $payload['catalog_version'],
			'items'           => $payload['items'],
			'stale'           => $this->using_stale(),
			'fetched_at'      => $payload['fetched_at'],
		);
	}

	/**
	 * Catalog payload. Fresh cache, else fetch, else 72h stale, else empty.
	 *
	 * @since 4.0.0
	 *
	 * @return array{catalog_version: string, items: list<array<string, mixed>>, fetched_at: string}
	 */
	public function payload(): array {
		$fresh = $this->read_payload( self::TRANSIENT_FRESH );
		if ( is_array( $fresh ) ) {
			return $fresh;
		}

		$fetched = $this->refresh();
		if ( is_array( $fetched ) ) {
			return $fetched;
		}

		$stale = $this->read_payload( self::TRANSIENT_STALE );
		if ( is_array( $stale ) ) {
			return $stale;
		}

		return array(
			'catalog_version' => '',
			'items'           => array(),
			'fetched_at'      => '',
		);
	}

	/**
	 * Whether the snapshot currently served came from the stale fallback.
	 *
	 * @since 4.0.0
	 */
	private function using_stale(): bool {
		$fresh = $this->read_payload( self::TRANSIENT_FRESH );
		return ! is_array( $fresh );
	}

	/**
	 * Contact the license server when outbound is allowed and the site is bound.
	 *
	 * @return array{catalog_version: string, items: list<array<string, mixed>>, fetched_at: string}|null Null on failure (caller may use stale).
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

		$payload = array(
			'catalog_version' => $result['catalog_version'],
			'items'           => $result['items'],
			'fetched_at'      => gmdate( 'Y-m-d\TH:i:s\Z' ),
		);
		call_user_func( $this->set_transient, self::TRANSIENT_FRESH, $payload, self::TTL_FRESH );
		call_user_func( $this->set_transient, self::TRANSIENT_STALE, $payload, self::TTL_STALE );

		return $payload;
	}

	/**
	 * Catalog payload from a transient, or null when missing / malformed.
	 *
	 * @param string $key Transient name.
	 * @return array<string, mixed>|null
	 */
	private function read_payload( string $key ) {
		$stored = call_user_func( $this->get_transient, $key );
		if ( ! is_array( $stored ) || ! isset( $stored['items'] ) || ! is_array( $stored['items'] ) ) {
			return null;
		}

		return array(
			'catalog_version' => isset( $stored['catalog_version'] ) && is_string( $stored['catalog_version'] ) ? $stored['catalog_version'] : '',
			'items'           => $stored['items'],
			'fetched_at'      => isset( $stored['fetched_at'] ) && is_string( $stored['fetched_at'] ) ? $stored['fetched_at'] : '',
		);
	}

	/**
	 * Bound site_hash or empty.
	 */
	private function site_hash(): string {
		$identity = $this->repository->get_identity();
		$binding  = isset( $identity['binding'] ) && is_array( $identity['binding'] ) ? $identity['binding'] : array();
		$hash     = isset( $binding['site_hash'] ) && is_string( $binding['site_hash'] ) ? $binding['site_hash'] : '';

		return CatalogClient::sanitize_hash( $hash );
	}
}
