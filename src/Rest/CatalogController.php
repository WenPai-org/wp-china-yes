<?php
/**
 * GET /catalog — cached service catalog, never 5xx.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Rest;

use WenPai\ChinaYes\Services\Catalog\CatalogModule;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the cached catalog copy. Unreachable with no cache is an empty list.
 */
final class CatalogController {

	/**
	 * Fetch + cache.
	 *
	 * @var CatalogModule
	 */
	private CatalogModule $module;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param CatalogModule|null $module Cache. Null builds the default.
	 */
	public function __construct( $module = null ) {
		$this->module = $module instanceof CatalogModule ? $module : new CatalogModule( new \WenPai\ChinaYes\Config\Repository() );
	}

	/**
	 * Register GET /catalog on wpcy/v1.
	 *
	 * @since 4.0.0
	 */
	public function register_routes(): void {
		register_rest_route(
			RestModule::NAMESPACE,
			'/catalog',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( Permissions::class, 'manage_options_read' ),
			)
		);
	}

	/**
	 * GET /catalog. Cached copy; empty list when unreachable with no cache.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function get_item( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return RestError::ok( $this->module->snapshot() );
	}
}
