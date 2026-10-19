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
	 * In-request fallback when transients are unavailable: user|screen => claimed.
	 *
	 * @var array<string, true>
	 */
	private array $heartbeat_claimed = array();

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
		add_action( 'heartbeat_received', array( $this, 'on_heartbeat_received' ), 10, 0 );
		add_action( 'load-index.php', array( $this, 'on_load_index' ) );
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

	/**
	 * Lower-bound estimate: editor interval 15s → 60s saves 3 heartbeats per minute.
	 *
	 * At most one increment per user_id + screen every 60 seconds (transient,
	 * else a request-static flag). Not a precise count of skipped admin-ajax
	 * calls. Prefer undercount to inflation.
	 *
	 * @since 4.0.0
	 */
	public function on_heartbeat_received(): void {
		if ( ! $this->is_editor_screen() || ! function_exists( 'do_action' ) ) {
			return;
		}
		if ( ! $this->claim_heartbeat_minute() ) {
			return;
		}
		do_action( 'wpcy_stats_increment', 'heartbeat_saved', 3 );
	}

	/**
	 * Claim the once-per-minute slot for this user + screen.
	 *
	 * @since 4.0.0
	 */
	private function claim_heartbeat_minute(): bool {
		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$screen  = $this->heartbeat_screen_key();
		$key     = 'wpcy_hb_saved_' . md5( (string) $user_id . '|' . $screen );

		if ( function_exists( 'get_transient' ) && function_exists( 'set_transient' ) ) {
			if ( false !== get_transient( $key ) ) {
				return false;
			}
			set_transient( $key, 1, 60 );
			return true;
		}

		if ( isset( $this->heartbeat_claimed[ $key ] ) ) {
			return false;
		}
		$this->heartbeat_claimed[ $key ] = true;
		return true;
	}

	/**
	 * Screen id used as the heartbeat throttle key.
	 *
	 * @since 4.0.0
	 */
	private function heartbeat_screen_key(): string {
		if ( isset( $GLOBALS['pagenow'] ) && is_string( $GLOBALS['pagenow'] ) && '' !== $GLOBALS['pagenow'] ) {
			return $GLOBALS['pagenow'];
		}
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( is_object( $screen ) ) {
				$base = (string) $screen->base;
				if ( '' !== $base ) {
					return $base;
				}
			}
		}
		return $this->hook_suffix;
	}

	/**
	 * Lower-bound estimate: dashboard Heartbeat off = 1 saved tick per page load.
	 *
	 * @since 4.0.0
	 */
	public function on_load_index(): void {
		if ( ! function_exists( 'do_action' ) ) {
			return;
		}
		do_action( 'wpcy_stats_increment', 'heartbeat_saved', 1 );
	}
}
