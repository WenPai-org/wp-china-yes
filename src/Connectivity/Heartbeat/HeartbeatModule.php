<?php
/**
 * Dashboard Heartbeat off; editor interval 60s when connectivity.heartbeat=on.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Connectivity\Heartbeat;

use WenPai\ChinaYes\Core\ConditionalModule;
use WenPai\ChinaYes\Core\Config;
use WenPai\ChinaYes\Core\Environment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module id connectivity.heartbeat. Does not touch frontend Heartbeat.
 */
final class HeartbeatModule implements ConditionalModule {

	/**
	 * Config read model.
	 *
	 * @var Config
	 */
	private Config $config;

	/**
	 * Last admin_enqueue_scripts hook suffix.
	 *
	 * @var string
	 */
	private string $hook_suffix = '';

	/**
	 * Constructor. Does not register hooks.
	 *
	 * @since 4.0.0
	 *
	 * @param Config $config Config read model.
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * Module id.
	 *
	 * @since 4.0.0
	 */
	public function id(): string {
		return 'connectivity.heartbeat';
	}

	/**
	 * No module dependencies.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function dependencies(): array {
		return array();
	}

	/**
	 * Admin screens only.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function contexts(): array {
		return array( Environment::ADMIN );
	}

	/**
	 * Recovery mode or heartbeat=off (after filter) → do not hook.
	 *
	 * @since 4.0.0
	 *
	 * @param Config      $config      Config read model.
	 * @param Environment $environment Current request scene.
	 */
	public function enabled( Config $config, Environment $environment ): bool {
		unset( $environment );
		if ( $this->config->get( 'recovery_mode' ) || $config->get( 'recovery_mode' ) ) {
			return false;
		}

		$on = 'on' === $this->config->get( 'connectivity.heartbeat', 'off' );
		if ( function_exists( 'apply_filters' ) ) {
			$on = (bool) apply_filters( 'wpcy_heartbeat_throttle', $on );
		}

		return $on;
	}

	/**
	 * Register dashboard and editor Heartbeat hooks.
	 *
	 * @since 4.0.0
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'on_admin_enqueue_scripts' ) );
		add_filter( 'heartbeat_settings', array( $this, 'filter_heartbeat_settings' ) );
	}

	/**
	 * Drop Heartbeat on the dashboard. Remember hook_suffix for the editor filter.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $hook_suffix Current admin page.
	 */
	public function on_admin_enqueue_scripts( $hook_suffix ): void {
		$this->hook_suffix = is_string( $hook_suffix ) ? $hook_suffix : '';
		if ( 'index.php' === $this->hook_suffix && function_exists( 'wp_deregister_script' ) ) {
			wp_deregister_script( 'heartbeat' );
		}
	}

	/**
	 * Editor screens (classic and block) use a 60-second interval.
	 *
	 * Reads $GLOBALS['pagenow'] because heartbeat_settings often runs during
	 * wp_default_scripts, before admin_enqueue_scripts sets hook_suffix.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $settings Heartbeat settings.
	 * @return mixed
	 */
	public function filter_heartbeat_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return $settings;
		}

		if ( $this->is_editor_screen() ) {
			$settings['interval'] = 60;
		}

		return $settings;
	}

	/**
	 * Whether the current admin screen is the post editor.
	 *
	 * @since 4.0.0
	 */
	private function is_editor_screen(): bool {
		$pages = array( 'post.php', 'post-new.php' );

		if ( isset( $GLOBALS['pagenow'] ) && is_string( $GLOBALS['pagenow'] )
			&& in_array( $GLOBALS['pagenow'], $pages, true )
		) {
			return true;
		}

		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( is_object( $screen ) && in_array( (string) $screen->base, $pages, true ) ) {
				return true;
			}
		}

		return in_array( $this->hook_suffix, $pages, true );
	}
}
