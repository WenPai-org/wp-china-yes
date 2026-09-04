<?php
/**
 * REST wpcy/v1/apps*
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Rest;

use WenPai\ChinaYes\Apps\CachedEntitlements;
use WenPai\ChinaYes\Apps\DataStore;
use WenPai\ChinaYes\Apps\EntitlementsClient;
use WenPai\ChinaYes\Apps\Registry;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Apps routes. WordPress capability is manage_options; manifest permissions are extra.
 *
 * Does not emit wpcy_apps_origin_mismatch (bridge, M2-05). Constant is on Registry.
 */
final class AppsController {

	/**
	 * App id path pattern.
	 *
	 * @since 4.0.0
	 */
	public const ID_PATTERN = '[a-zA-Z0-9_.-]+';

	/**
	 * Verified manifests.
	 *
	 * @var Registry
	 */
	private Registry $registry;

	/**
	 * Per-app data.
	 *
	 * @var DataStore
	 */
	private DataStore $store;

	/**
	 * Entitlements. Default is the cached Services\\Entitlements client.
	 *
	 * @var EntitlementsClient
	 */
	private EntitlementsClient $entitlements;

	/**
	 * Site context. Array or callable. Callable is not a PHP 7.4 property type.
	 *
	 * @var array<string, mixed>|callable|null
	 */
	private $context;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param Registry                           $registry     Verified list.
	 * @param DataStore                          $store        Data table.
	 * @param EntitlementsClient|null            $entitlements Entitlements. Null uses CachedEntitlements.
	 * @param array<string, mixed>|callable|null $context      Site context override.
	 */
	public function __construct( Registry $registry, DataStore $store, $entitlements = null, $context = null ) {
		$this->registry     = $registry;
		$this->store        = $store;
		$this->entitlements = $entitlements instanceof EntitlementsClient ? $entitlements : new CachedEntitlements();
		$this->context      = $context;
	}

	/**
	 * Register /apps* on wpcy/v1. Does not register other namespaces.
	 *
	 * @since 4.0.0
	 */
	public function register_routes(): void {
		$ns = RestModule::NAMESPACE;
		$id = '(?P<id>' . self::ID_PATTERN . ')';

		register_rest_route(
			$ns,
			'/apps',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_apps' ),
				'permission_callback' => array( Permissions::class, 'manage_options_read' ),
			)
		);

		register_rest_route(
			$ns,
			'/apps/' . $id . '/context',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_context' ),
				'permission_callback' => array( Permissions::class, 'manage_options_read' ),
			)
		);

		register_rest_route(
			$ns,
			'/apps/' . $id . '/data',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_data' ),
				'permission_callback' => array( Permissions::class, 'manage_options_read' ),
			)
		);

		register_rest_route(
			$ns,
			'/apps/' . $id . '/data/(?P<key>.+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_data' ),
					'permission_callback' => array( Permissions::class, 'manage_options_read' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'put_data' ),
					'permission_callback' => array( Permissions::class, 'manage_options_write' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_data' ),
					'permission_callback' => array( Permissions::class, 'manage_options_write' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/apps/' . $id . '/entitlement',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_entitlement' ),
				'permission_callback' => array( Permissions::class, 'manage_options_read' ),
			)
		);

		register_rest_route(
			$ns,
			'/apps/' . $id . '/go',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'open_go' ),
				'permission_callback' => array( Permissions::class, 'manage_options_write' ),
			)
		);
	}

	/**
	 * GET /apps
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function list_apps( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		$out = array();
		foreach ( $this->registry->all() as $manifest ) {
			$row                       = $manifest;
			$row['entitlement_status'] = $this->entitlement_summary( $manifest );
			unset( $row['signature'] );
			$out[] = $row;
		}
		return RestError::ok( $out );
	}

	/**
	 * GET /apps/{id}/context
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_context( WP_REST_Request $request ) {
		$gate = $this->gate( $request, 'site:read' );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		return RestError::ok( $this->site_context() );
	}

	/**
	 * GET /apps/{id}/data
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_data( WP_REST_Request $request ) {
		$gate = $this->gate( $request, 'data:read' );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$id = $this->app_id( $request );
		return RestError::ok( $this->store->list_keys( $id ) );
	}

	/**
	 * GET /apps/{id}/data/{key}
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_data( WP_REST_Request $request ) {
		$gate = $this->gate( $request, 'data:read' );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$key = $this->data_key( $request );
		if ( is_wp_error( $key ) ) {
			return $key;
		}
		$id = $this->app_id( $request );
		if ( ! $this->store->has( $id, $key ) ) {
			return RestError::make(
				'wpcy_apps_unknown_app',
				__( 'The requested data key was not found for this app.', 'wp-china-yes' ),
				404
			);
		}
		return RestError::ok(
			array(
				'key'   => $key,
				'value' => $this->store->get( $id, $key ),
			)
		);
	}

	/**
	 * PUT /apps/{id}/data/{key}
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function put_data( WP_REST_Request $request ) {
		if ( ! $this->registry->has( $this->app_id( $request ) ) ) {
			return RestError::make(
				'wpcy_apps_unknown_app',
				__( 'Unknown app.', 'wp-china-yes' ),
				404
			);
		}
		$key = $this->data_key( $request );
		if ( is_wp_error( $key ) ) {
			return $key;
		}

		$raw = $this->raw_body( $request );
		if ( strlen( $raw ) > DataStore::MAX_BYTES ) {
			return RestError::make(
				'wpcy_apps_payload_too_large',
				__( 'The request body exceeds 64KB.', 'wp-china-yes' ),
				413
			);
		}

		$gate = $this->gate( $request, 'data:write' );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$value  = $this->put_value( $request );
		$result = $this->store->put( $this->app_id( $request ), $key, $value, strlen( $raw ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return RestError::ok(
			array(
				'key'   => $key,
				'value' => $this->store->get( $this->app_id( $request ), $key ),
			)
		);
	}

	/**
	 * DELETE /apps/{id}/data/{key}
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_data( WP_REST_Request $request ) {
		$gate = $this->gate( $request, 'data:delete' );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$key = $this->data_key( $request );
		if ( is_wp_error( $key ) ) {
			return $key;
		}
		$this->store->delete( $this->app_id( $request ), $key );
		return RestError::ok( array( 'deleted' => true ) );
	}

	/**
	 * GET /apps/{id}/entitlement
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_entitlement( WP_REST_Request $request ) {
		$gate = $this->gate( $request, 'entitlement:read' );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$manifest = $this->registry->get( $this->app_id( $request ) );
		$denied   = $this->entitlement_denied( is_array( $manifest ) ? $manifest : array(), false );
		if ( is_wp_error( $denied ) ) {
			return $denied;
		}
		$summary = $this->entitlement_summary( is_array( $manifest ) ? $manifest : array() );
		return RestError::ok( $summary );
	}

	/**
	 * POST /apps/{id}/go
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function open_go( WP_REST_Request $request ) {
		$gate = $this->gate( $request, 'go:open' );
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$manifest = $this->registry->get( $this->app_id( $request ) );
		$slug     = is_array( $manifest ) && isset( $manifest['go_service'] ) && is_string( $manifest['go_service'] )
			? $manifest['go_service']
			: $this->app_id( $request );
		$id       = $this->app_id( $request );
		$url      = 'https://wpcy.com/go/' . rawurlencode( $slug );
		$query    = 'utm_source=wpcy-plugin&utm_medium=app&utm_campaign=' . rawurlencode( $id );
		return RestError::ok( array( 'url' => $url . '?' . $query ) );
	}

	/**
	 * App exists and lists $permission.
	 *
	 * @param WP_REST_Request $request    Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @param string          $permission Manifest permission.
	 * @return true|WP_Error
	 */
	private function gate( WP_REST_Request $request, string $permission ) {
		$id = $this->app_id( $request );
		if ( ! $this->registry->has( $id ) ) {
			return RestError::make(
				'wpcy_apps_unknown_app',
				__( 'Unknown app.', 'wp-china-yes' ),
				404
			);
		}
		if ( ! $this->registry->allows( $id, $permission ) ) {
			return RestError::make(
				'wpcy_apps_forbidden_permission',
				__( 'This app is not allowed to use that permission.', 'wp-china-yes' ),
				403
			);
		}

		$manifest = $this->registry->get( $id );
		$needs    = in_array( $permission, array( 'data:write', 'data:delete' ), true );
		$denied   = $this->entitlement_denied( is_array( $manifest ) ? $manifest : array(), $needs );
		if ( is_wp_error( $denied ) ) {
			return $denied;
		}

		return true;
	}

	/**
	 * Paid without a row, or exhausted when the request spends quota.
	 *
	 * @param array<string, mixed> $manifest Manifest.
	 * @param bool                 $needs_quota Whether this request spends quota.
	 * @return WP_Error|null
	 */
	private function entitlement_denied( array $manifest, bool $needs_quota ) {
		$tier = isset( $manifest['tier'] ) && is_string( $manifest['tier'] ) ? $manifest['tier'] : 'free';
		$eid  = isset( $manifest['entitlement'] ) && is_string( $manifest['entitlement'] ) ? $manifest['entitlement'] : '';

		if ( 'free' === $tier ) {
			return null;
		}

		$row = '' !== $eid ? $this->entitlements->get( $eid ) : null;
		if ( ! is_array( $row ) ) {
			if ( 'paid' === $tier ) {
				return RestError::make(
					'wpcy_apps_entitlement_required',
					__( 'This app requires an entitlement.', 'wp-china-yes' ),
					403
				);
			}
			return null;
		}

		$status = isset( $row['status'] ) && is_string( $row['status'] ) ? $row['status'] : '';
		if ( $needs_quota && 'exhausted' === $status ) {
			return RestError::make(
				'wpcy_apps_quota_exceeded',
				__( 'The entitlement quota is exhausted.', 'wp-china-yes' ),
				403
			);
		}

		return null;
	}

	/**
	 * Entitlement summary for list and GET /entitlement.
	 *
	 * @param array<string, mixed> $manifest Manifest.
	 * @return array<string, mixed>
	 */
	private function entitlement_summary( array $manifest ): array {
		$tier = isset( $manifest['tier'] ) && is_string( $manifest['tier'] ) ? $manifest['tier'] : 'free';
		$eid  = isset( $manifest['entitlement'] ) && is_string( $manifest['entitlement'] ) ? $manifest['entitlement'] : '';
		if ( 'free' === $tier || '' === $eid ) {
			return array(
				'status' => 'active',
				'quota'  => array(
					'limit'     => null,
					'used'      => null,
					'period'    => null,
					'resets_at' => null,
				),
			);
		}

		$row = $this->entitlements->get( $eid );
		if ( ! is_array( $row ) ) {
			return array(
				'status' => 'expired',
				'quota'  => array(
					'limit'     => null,
					'used'      => null,
					'period'    => null,
					'resets_at' => null,
				),
			);
		}

		$status = isset( $row['status'] ) && is_string( $row['status'] ) ? $row['status'] : 'exhausted';
		$quota  = isset( $row['quota'] ) && is_array( $row['quota'] ) ? $row['quota'] : array();
		return array(
			'status' => $status,
			'quota'  => array(
				'limit'     => $quota['limit'] ?? null,
				'used'      => $quota['used'] ?? null,
				'period'    => $quota['period'] ?? null,
				'resets_at' => $quota['resets_at'] ?? null,
			),
		);
	}

	/**
	 * Site context. No roles, no email.
	 *
	 * @return array<string, mixed>
	 */
	private function site_context(): array {
		if ( is_callable( $this->context ) ) {
			$provided = call_user_func( $this->context );
			return is_array( $provided ) ? $provided : array();
		}
		if ( is_array( $this->context ) ) {
			return $this->context;
		}

		$plugins = array();
		if ( function_exists( 'get_option' ) ) {
			$stored = get_option( 'active_plugins', array() );
			if ( is_array( $stored ) ) {
				foreach ( $stored as $slug ) {
					if ( is_string( $slug ) && '' !== $slug ) {
						$plugins[] = $slug;
					}
				}
			}
		}

		return array(
			'site_url'       => function_exists( 'site_url' ) ? site_url() : '',
			'wp_version'     => function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'version' ) : '',
			'locale'         => function_exists( 'get_locale' ) ? get_locale() : 'en_US',
			'is_multisite'   => function_exists( 'is_multisite' ) ? is_multisite() : false,
			'user_can'       => array(
				'manage_options' => function_exists( 'current_user_can' ) ? current_user_can( 'manage_options' ) : false,
			),
			'active_plugins' => $plugins,
		);
	}

	/**
	 * App id from the route.
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	private function app_id( WP_REST_Request $request ): string {
		$id = $request->get_param( 'id' );
		return is_string( $id ) ? $id : '';
	}

	/**
	 * Validated data key, or wpcy_apps_key_invalid.
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return string|WP_Error
	 */
	private function data_key( WP_REST_Request $request ) {
		$key = $request->get_param( 'key' );
		$key = is_string( $key ) ? $key : '';
		if ( ! DataStore::key_valid( $key ) ) {
			return RestError::make(
				'wpcy_apps_key_invalid',
				__( 'The data key is invalid.', 'wp-china-yes' ),
				400
			);
		}
		return $key;
	}

	/**
	 * Raw HTTP body when the request exposes it.
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	private function raw_body( WP_REST_Request $request ): string {
		$body = $request->get_body();
		if ( '' !== $body ) {
			return $body;
		}
		$json = $request->get_json_params();
		if ( array() === $json ) {
			return '';
		}
		$encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( $json ) : json_encode( $json ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit bootstrap has no wp_json_encode.
		return is_string( $encoded ) ? $encoded : '';
	}

	/**
	 * PUT JSON value. Route params are not stored.
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return mixed
	 */
	private function put_value( WP_REST_Request $request ) {
		$json = $request->get_json_params();
		if ( array() !== $json ) {
			if ( array_key_exists( 'value', $json ) ) {
				return $json['value'];
			}
			return $json;
		}
		if ( null !== $request->get_param( 'value' ) ) {
			return $request->get_param( 'value' );
		}
		return null;
	}
}
