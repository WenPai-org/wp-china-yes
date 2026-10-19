<?php
/**
 * GET /entitlements — cached quota list, never 5xx.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Rest;

use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Core\Logger;
use WenPai\ChinaYes\Services\Entitlements\EntitlementsModule;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the cached entitlements copy. Unreachable with no cache is an empty list.
 */
final class EntitlementsController {

	/**
	 * Fetch + cache.
	 *
	 * @var EntitlementsModule
	 */
	private EntitlementsModule $module;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param EntitlementsModule|null $module     Cache. Null builds the default.
	 * @param Repository|null         $repository Identity access when $module is omitted.
	 * @param Logger|null             $logger     Failure sink.
	 */
	public function __construct( $module = null, $repository = null, $logger = null ) {
		if ( $module instanceof EntitlementsModule ) {
			$this->module = $module;
			return;
		}

		$repo         = $repository instanceof Repository ? $repository : new Repository( $logger );
		$this->module = new EntitlementsModule( $repo, $logger );
	}

	/**
	 * Register GET /entitlements on wpcy/v1.
	 *
	 * @since 4.0.0
	 */
	public function register_routes(): void {
		register_rest_route(
			RestModule::NAMESPACE,
			'/entitlements',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( Permissions::class, 'manage_options_read' ),
			)
		);
	}

	/**
	 * GET /entitlements. Cached copy; empty list when unreachable with no cache.
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
