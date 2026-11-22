<?php
/**
 * Safe, non-destructive frontend maintenance mode guard (`wpcy_maintenance_mode`).
 *
 * Returns HTTP 503 via WordPress core `wp_die()` to unauthenticated frontend visitors
 * while always keeping wp-admin, login, REST API, WP-CLI, cron, and administrators
 * fully accessible so site owners can never lock themselves out.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin;

use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Core\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend maintenance mode responder controlled from the Overview Quick Tools grid.
 */
final class MaintenanceModule implements Module {

	/**
	 * Per-site option key storing `'1'` when maintenance mode is active.
	 *
	 * @since 4.0.0
	 */
	public const OPTION = 'wpcy_maintenance_mode';

	/**
	 * Settings repository (checked for recovery mode bypass).
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Constructor. Does not register hooks.
	 *
	 * @param Repository $repository Settings repository.
	 */
	public function __construct( Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Module identifier.
	 */
	public function id(): string {
		return 'admin.maintenance';
	}

	/**
	 * Frontend only (never interferes with admin, REST, cron, or CLI).
	 *
	 * @return list<string>
	 */
	public function contexts(): array {
		return array( Environment::FRONTEND );
	}

	/**
	 * No module dependencies.
	 *
	 * @return list<string>
	 */
	public function dependencies(): array {
		return array();
	}

	/**
	 * Hook `template_redirect` at priority 1.
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'maybe_render_maintenance' ), 1 );
	}

	/**
	 * Whether maintenance mode is currently enabled for this site.
	 */
	public static function is_enabled(): bool {
		if ( ! function_exists( 'get_option' ) ) {
			return false;
		}
		return '1' === (string) get_option( self::OPTION, '0' );
	}

	/**
	 * Enable or disable maintenance mode.
	 *
	 * @param bool $enabled Target state.
	 */
	public static function set_enabled( bool $enabled ): bool {
		if ( ! function_exists( 'update_option' ) ) {
			return false;
		}
		update_option( self::OPTION, $enabled ? '1' : '0', false );
		return true;
	}

	/**
	 * Whether the current frontend request should receive the 503 maintenance notice.
	 */
	public function should_block_request(): bool {
		if ( ! self::is_enabled() ) {
			return false;
		}
		if ( true === $this->repository->get( 'recovery_mode', false ) ) {
			return false;
		}
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return false;
		}
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return false;
		}
		if ( function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Emit HTTP 503 + Retry-After and WordPress native maintenance screen.
	 */
	public function maybe_render_maintenance(): void {
		if ( ! $this->should_block_request() ) {
			return;
		}
		if ( ! headers_sent() ) {
			if ( function_exists( 'status_header' ) ) {
				status_header( 503 );
			}
			header( 'Retry-After: 600' );
		}
		$site_name = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '';
		$title     = '' !== $site_name
			? sprintf( /* translators: %s: site title */ __( '%s · 站点维护中', 'wp-china-yes' ), $site_name )
			: __( '站点维护中', 'wp-china-yes' );
		$message   = '<h1>' . esc_html__( '站点正在例行维护', 'wp-china-yes' ) . '</h1>'
			. '<p>' . esc_html__( '我们正在进行短暂升级与配置调整，预计很快恢复访问，请稍后刷新页面。', 'wp-china-yes' ) . '</p>';

		if ( function_exists( 'wp_die' ) ) {
			wp_die(
				wp_kses_post( $message ),
				esc_html( $title ),
				array( 'response' => 503 )
			);
		}
	}
}
