<?php
/**
 * REST namespace wpcy/v1. Later tasks hang extra routes on this module.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Rest;

use WenPai\ChinaYes\Admin\RecoveryPage;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Core\Module;
use WenPai\ChinaYes\Diagnostics\Checker;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers settings, network-settings, diagnostics, recovery, and binding.
 */
final class RestModule implements Module {

	/**
	 * REST namespace.
	 *
	 * @since 4.0.0
	 */
	public const NAMESPACE = 'wpcy/v1';

	/**
	 * Settings access.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Diagnostics probes.
	 *
	 * @var Checker
	 */
	private Checker $checker;

	/**
	 * Hidden recovery page. Null constructs the default.
	 *
	 * @var RecoveryPage|null
	 */
	private $recovery_page;

	/**
	 * Constructor. Does not register hooks.
	 *
	 * @since 4.0.0
	 *
	 * @param Repository        $repository    Settings access.
	 * @param Checker|null      $checker       Probe runner.
	 * @param RecoveryPage|null $recovery_page Hidden admin page.
	 */
	public function __construct( Repository $repository, $checker = null, $recovery_page = null ) {
		$this->repository    = $repository;
		$this->checker       = $checker instanceof Checker ? $checker : new Checker( null, null, null, $repository );
		$this->recovery_page = $recovery_page instanceof RecoveryPage ? $recovery_page : null;
	}

	/**
	 * Module id.
	 *
	 * @since 4.0.0
	 */
	public function id(): string {
		return 'rest';
	}

	/**
	 * Every scene: rest_api_init runs after plugins_loaded, before REST_REQUEST.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function contexts(): array {
		return Environment::CONTEXTS;
	}

	/**
	 * No module graph edges.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function dependencies(): array {
		return array();
	}

	/**
	 * Hook rest_api_init and the recovery page. Constructor does not register hooks.
	 *
	 * @since 4.0.0
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'attach_request_id' ), 10, 3 );
		$this->recovery_page()->register();
	}

	/**
	 * Register this task's routes on wpcy/v1.
	 *
	 * @since 4.0.0
	 */
	public function register_routes(): void {
		$writer      = new DocumentWriter( $this->repository );
		$settings    = new SettingsController( $writer );
		$network     = new NetworkSettingsController( $writer );
		$diagnostics = new DiagnosticsController( $this->checker );
		$recovery    = new RecoveryController( new RecoveryActions( $this->repository ) );
		$binding     = new BindingController( $this->repository );

		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $settings, 'get_item' ),
					'permission_callback' => array( Permissions::class, 'manage_options_read' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $settings, 'update_item' ),
					'permission_callback' => array( Permissions::class, 'manage_options_write' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/network-settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $network, 'get_item' ),
					'permission_callback' => array( Permissions::class, 'manage_network_read' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $network, 'update_item' ),
					'permission_callback' => array( Permissions::class, 'manage_network_write' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/diagnostics',
			array(
				'methods'             => 'GET',
				'callback'            => array( $diagnostics, 'get_item' ),
				'permission_callback' => array( Permissions::class, 'manage_options_read' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/diagnostics/run',
			array(
				'methods'             => 'POST',
				'callback'            => array( $diagnostics, 'run' ),
				'permission_callback' => array( Permissions::class, 'manage_options_write' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/recovery',
			array(
				'methods'             => 'POST',
				'callback'            => array( $recovery, 'update_item' ),
				'permission_callback' => array( Permissions::class, 'manage_options_write' ),
				'args'                => array(
					'action' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/binding',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $binding, 'get_item' ),
					'permission_callback' => array( Permissions::class, 'manage_options_read' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $binding, 'delete_item' ),
					'permission_callback' => array( Permissions::class, 'manage_options_write' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/binding/start',
			array(
				'methods'             => 'POST',
				'callback'            => array( $binding, 'start' ),
				'permission_callback' => array( Permissions::class, 'manage_options_write' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/binding/challenge',
			array(
				'methods'             => 'GET',
				'callback'            => array( $binding, 'challenge' ),
				'permission_callback' => array( BindingController::class, 'public_read' ),
				'args'                => array(
					'id' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Add X-WPCY-Request-Id on wpcy/v1 responses, including errors.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed           $response Response.
	 * @param mixed           $server   Server.
	 * @param WP_REST_Request $request  Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return mixed
	 */
	public function attach_request_id( $response, $server, WP_REST_Request $request ) {
		unset( $server );
		$route = (string) $request->get_route();
		if ( 0 !== strpos( $route, '/' . self::NAMESPACE ) ) {
			return $response;
		}

		if ( is_object( $response ) && method_exists( $response, 'header' ) ) {
			$response->header( RestError::HEADER, RestError::request_id() );
		}

		return $response;
	}

	/**
	 * Hidden recovery page.
	 *
	 * @since 4.0.0
	 */
	public function recovery_page(): RecoveryPage {
		if ( ! $this->recovery_page instanceof RecoveryPage ) {
			$this->recovery_page = new RecoveryPage( new RecoveryActions( $this->repository ) );
		}

		return $this->recovery_page;
	}
}
