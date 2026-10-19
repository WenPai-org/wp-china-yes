<?php
/**
 * GET /providers and /providers/{id}/products, POST connect/test, DELETE /providers/{id}.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Rest;

use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Core\Logger;
use WenPai\ChinaYes\Providers\Presets;
use WenPai\ChinaYes\Providers\ProviderService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provider REST. Responses never include license_key, full email, or instance.
 */
final class ProvidersController {

	/**
	 * Provider operations.
	 *
	 * @var ProviderService
	 */
	private ProviderService $service;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param ProviderService|null $service    Operations. Null builds the default.
	 * @param Repository|null      $repository Identity access when $service is omitted.
	 * @param Logger|null          $logger     Failure sink.
	 */
	public function __construct( $service = null, $repository = null, $logger = null ) {
		$this->service = $service instanceof ProviderService
			? $service
			: new ProviderService( null, null, null, null, $logger, $repository );
	}

	/**
	 * Register the five /providers* routes on wpcy/v1.
	 *
	 * @since 4.0.0
	 */
	public function register_routes(): void {
		register_rest_route(
			RestModule::NAMESPACE,
			'/providers',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( Permissions::class, 'manage_options_read' ),
			)
		);

		register_rest_route(
			RestModule::NAMESPACE,
			'/providers/(?P<id>[a-z0-9\-]+)/connect',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'connect' ),
				'permission_callback' => array( Permissions::class, 'manage_options_write' ),
			)
		);

		register_rest_route(
			RestModule::NAMESPACE,
			'/providers/(?P<id>[a-z0-9\-]+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( Permissions::class, 'manage_options_write' ),
			)
		);

		register_rest_route(
			RestModule::NAMESPACE,
			'/providers/(?P<id>[a-z0-9\-]+)/test',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'test_item' ),
				'permission_callback' => array( Permissions::class, 'manage_options_write' ),
			)
		);

		register_rest_route(
			RestModule::NAMESPACE,
			'/providers/(?P<id>[a-z0-9\-]+)/products',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_products' ),
				'permission_callback' => array( Permissions::class, 'manage_options_read' ),
			)
		);
	}

	/**
	 * GET /providers.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function get_items( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return RestError::ok( $this->service->list() );
	}

	/**
	 * POST /providers/{id}/connect.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function connect( WP_REST_Request $request ) {
		$id = $this->id( $request );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$body = SettingsController::body( $request );
		if ( ! is_array( $body ) ) {
			$body = array();
		}
		$email = isset( $body['email'] ) && is_string( $body['email'] ) ? $body['email'] : '';
		$key   = isset( $body['license_key'] ) && is_string( $body['license_key'] ) ? $body['license_key'] : '';

		return $this->respond( $this->service->connect( $id, $email, $key ) );
	}

	/**
	 * DELETE /providers/{id}.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( WP_REST_Request $request ) {
		$id = $this->id( $request );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		return $this->respond( $this->service->disconnect( $id ) );
	}

	/**
	 * POST /providers/{id}/test.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function test_item( WP_REST_Request $request ) {
		$id = $this->id( $request );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		return $this->respond( $this->service->test( $id ) );
	}

	/**
	 * GET /providers/{id}/products.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_products( WP_REST_Request $request ) {
		$id = $this->id( $request );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		return $this->respond( $this->service->products( $id ) );
	}

	/**
	 * Enumerate {id}. Unknown → 404 wpcy_provider_unknown.
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return string|WP_Error
	 */
	private function id( WP_REST_Request $request ) {
		$id = (string) $request->get_param( 'id' );
		if ( null === Presets::get( $id ) ) {
			return RestError::make(
				'wpcy_provider_unknown',
				__( '暂时无法找到该供应商。', 'wp-china-yes' ),
				404
			);
		}

		return $id;
	}

	/**
	 * Wrap a service result as REST.
	 *
	 * @param array<string, mixed>|WP_Error $result Service result.
	 * @return WP_REST_Response|WP_Error
	 */
	private function respond( $result ) {
		if ( is_wp_error( $result ) ) {
			$data   = $result->get_error_data();
			$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 500;
			return RestError::make(
				(string) $result->get_error_code(),
				(string) $result->get_error_message(),
				$status
			);
		}

		return RestError::ok( $result );
	}
}
