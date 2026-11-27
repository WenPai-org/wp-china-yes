<?php
/**
 * Admin notice governance: Notification Center drawer + built-in promotional banner hiding.
 *
 * Replaces the legacy dual empty-source remote rule modules with a zero-overhead local
 * implementation aligned with WordPress Notification Center / Declared Intent principles.
 * Core updates, security alerts, and Site Health notices are never folded or hidden.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin\NoticeControl;

use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Core\ConditionalModule;
use WenPai\ChinaYes\Core\Config;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Rest\Permissions;
use WenPai\ChinaYes\Rest\RestError;
use WenPai\ChinaYes\Rest\RestModule;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module id `admin.notice_control`.
 *
 * Controls both:
 * - `modules.notice_control`: Admin Bar notification center drawer for non-critical notices.
 * - `admin.hide_promo`: Built-in CSS hiding for persistent third-party promotional banners.
 */
final class NoticeControlModule implements ConditionalModule {

	/**
	 * Settings access.
	 *
	 * @var Repository
	 */
	private Repository $config;

	/**
	 * Notification center drawer controller.
	 *
	 * @var NoticeCenter
	 */
	private NoticeCenter $center;

	/**
	 * Constructor. Does not register hooks.
	 *
	 * @since 4.0.0
	 *
	 * @param Repository        $config Settings access.
	 * @param NoticeCenter|null $center Optional notification center instance.
	 */
	public function __construct( Repository $config, ?NoticeCenter $center = null ) {
		$this->config = $config;
		$this->center = $center instanceof NoticeCenter ? $center : new NoticeCenter();
	}

	/**
	 * Module id.
	 *
	 * @since 4.0.0
	 */
	public function id(): string {
		return 'admin.notice_control';
	}

	/**
	 * Admin plus REST (status route).
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function contexts(): array {
		return array( Environment::ADMIN, Environment::REST );
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
	 * Active whenever not in recovery mode and at least one governance switch is on.
	 *
	 * @since 4.0.0
	 *
	 * @param Config      $config      Config read model.
	 * @param Environment $environment Current request scene.
	 */
	public function enabled( Config $config, Environment $environment ): bool {
		unset( $environment );

		if ( true === $config->get( 'recovery_mode', false ) ) {
			return false;
		}

		return $this->drawer_enabled( $config ) || $this->hide_promo_enabled( $config );
	}

	/**
	 * Whether the notification center drawer is enabled.
	 *
	 * @param Config|null $config Optional config override.
	 */
	public function drawer_enabled( ?Config $config = null ): bool {
		$source = $config instanceof Config ? $config : $this->config;
		return true === $source->get( 'modules.notice_control', false );
	}

	/**
	 * Whether built-in promotional banner hiding is enabled.
	 *
	 * @param Config|null $config Optional config override.
	 */
	public function hide_promo_enabled( ?Config $config = null ): bool {
		$source = $config instanceof Config ? $config : $this->config;
		return true === $source->get( 'admin.hide_promo', true );
	}

	/**
	 * Hook REST status route, notification center drawer, and promo banner stylesheet.
	 *
	 * @since 4.0.0
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		if ( $this->drawer_enabled() ) {
			add_action( 'admin_bar_menu', array( $this->center, 'add_admin_bar_node' ), 999 );
			add_action( 'admin_enqueue_scripts', array( $this->center, 'enqueue_assets' ) );
			add_action( 'admin_footer', array( $this->center, 'render_drawer' ) );
		}

		if ( $this->hide_promo_enabled() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_promo_hide_css' ), 20 );
		}
	}

	/**
	 * Register GET /wpcy/v1/notice-control/hidden for diagnostics.
	 *
	 * @since 4.0.0
	 */
	public function register_routes(): void {
		register_rest_route(
			RestModule::NAMESPACE,
			'/notice-control/hidden',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_status' ),
				'permission_callback' => array( Permissions::class, 'manage_options_read' ),
			)
		);
	}

	/**
	 * Return governance status and built-in promo rule metadata.
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function rest_status( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return RestError::ok( $this->diagnostics() );
	}

	/**
	 * Structured diagnostics snapshot for admin/REST consumers.
	 *
	 * @return array{
	 *     drawer_enabled: bool,
	 *     hide_promo_enabled: bool,
	 *     native_hub_detected: bool,
	 *     builtin_promo_count: int,
	 *     mode: string,
	 *     items: list<array{id: string, plugin: string, label: string}>
	 * }
	 */
	public function diagnostics(): array {
		$items = array();
		foreach ( PromoRules::builtin_rules() as $rule ) {
			$items[] = array(
				'id'     => $rule['id'],
				'plugin' => $rule['plugin'],
				'label'  => $rule['label'],
			);
		}

		$native = NoticeCenter::has_native_notification_center();
		$mode   = $native ? 'yield_to_native' : ( $this->drawer_enabled() ? 'drawer' : 'off' );

		return array(
			'drawer_enabled'      => $this->drawer_enabled(),
			'hide_promo_enabled'  => $this->hide_promo_enabled(),
			'native_hub_detected' => $native,
			'builtin_promo_count' => count( PromoRules::active_selectors() ),
			'mode'                => $mode,
			'items'               => $items,
		);
	}

	/**
	 * Attach curated promo-hiding CSS cleanly onto the core `wp-admin` stylesheet handle.
	 */
	public function enqueue_promo_hide_css(): void {
		if ( ! $this->hide_promo_enabled() ) {
			return;
		}
		if ( function_exists( 'is_admin' ) && ! is_admin() ) {
			return;
		}

		$css = PromoRules::build_css();
		if ( '' === $css ) {
			return;
		}

		if ( function_exists( 'wp_add_inline_style' ) ) {
			wp_add_inline_style( 'wp-admin', $css );
		}
	}
}
