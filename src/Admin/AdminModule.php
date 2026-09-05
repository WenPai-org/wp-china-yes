<?php
/**
 * Admin React app: four menu pages and build/ enqueue.
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
 * Registers 文派叶子 menu pages and loads the compiled admin app.
 *
 * Recovery (?page=wpcy-recovery) stays on RecoveryPage, not this module.
 */
final class AdminModule implements Module {

	/**
	 * Overview / parent menu slug.
	 *
	 * @since 4.0.0
	 */
	public const SLUG = 'wpcy';

	/**
	 * Script and style handle.
	 *
	 * @since 4.0.0
	 */
	public const HANDLE = 'wpcy-admin';

	/**
	 * Pages served by the React app (not the PHP recovery page).
	 *
	 * @since 4.0.0
	 * @var list<string>
	 */
	public const APP_PAGES = array(
		'wpcy',
		'wpcy-connect',
		'wpcy-services',
		'wpcy-diagnose',
	);

	/**
	 * Settings snapshot for the bootstrap payload.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Constructor. Does not register hooks.
	 *
	 * @since 4.0.0
	 *
	 * @param Repository $repository Settings access.
	 */
	public function __construct( Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Module id.
	 *
	 * @since 4.0.0
	 */
	public function id(): string {
		return 'admin';
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
	 * Hook admin_menu and admin_enqueue_scripts. Constructor does not register hooks.
	 *
	 * @since 4.0.0
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Top-level 文派叶子 plus four submenu items.
	 *
	 * @since 4.0.0
	 */
	public function add_pages(): void {
		$cap = 'manage_options';

		add_menu_page(
			__( '概览', 'wp-china-yes' ),
			__( '文派叶子', 'wp-china-yes' ),
			$cap,
			self::SLUG,
			array( $this, 'render' ),
			$this->menu_icon(),
			80
		);

		add_submenu_page(
			self::SLUG,
			__( '概览', 'wp-china-yes' ),
			__( '概览', 'wp-china-yes' ),
			$cap,
			self::SLUG,
			array( $this, 'render' )
		);

		add_submenu_page(
			self::SLUG,
			__( '连接优化', 'wp-china-yes' ),
			__( '连接优化', 'wp-china-yes' ),
			$cap,
			'wpcy-connect',
			array( $this, 'render' )
		);

		add_submenu_page(
			self::SLUG,
			__( '文派服务', 'wp-china-yes' ),
			__( '文派服务', 'wp-china-yes' ),
			$cap,
			'wpcy-services',
			array( $this, 'render' )
		);

		add_submenu_page(
			self::SLUG,
			__( '诊断', 'wp-china-yes' ),
			__( '诊断', 'wp-china-yes' ),
			$cap,
			'wpcy-diagnose',
			array( $this, 'render' )
		);
	}

	/**
	 * Mount point. Title lives in the React Page header.
	 *
	 * @since 4.0.0
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'wp-china-yes' ), 403 );
		}

		echo '<div class="wrap wpcy-admin-wrap"><div id="wpcy-admin-root"></div></div>';
	}

	/**
	 * Enqueue build/ only on the four React pages.
	 *
	 * Inline bootstrap is limited to nonce, REST root, capabilities, settings snapshot.
	 *
	 * @since 4.0.0
	 *
	 * @param string $hook_suffix Current admin screen id.
	 */
	public function enqueue( string $hook_suffix ): void {
		unset( $hook_suffix );

		if ( ! $this->is_app_screen() ) {
			return;
		}

		$asset_file = CHINA_YES_PLUGIN_PATH . 'build/index.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;
		if ( ! is_array( $asset ) ) {
			return;
		}

		$dependencies = isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] )
			? $asset['dependencies']
			: array();
		$version      = isset( $asset['version'] ) ? (string) $asset['version'] : CHINA_YES_VERSION;

		$script_deps = array();
		foreach ( $dependencies as $dependency ) {
			if ( ! is_string( $dependency ) ) {
				continue;
			}
			if ( false !== strpos( $dependency, '.css' ) ) {
				continue;
			}
			$script_deps[] = $dependency;
		}

		wp_enqueue_script(
			self::HANDLE,
			CHINA_YES_PLUGIN_URL . 'build/index.js',
			$script_deps,
			$version,
			true
		);

		$style_file = CHINA_YES_PLUGIN_PATH . 'build/style-index.css';
		if ( ! is_readable( $style_file ) ) {
			$style_file = CHINA_YES_PLUGIN_PATH . 'build/index.css';
		}
		if ( is_readable( $style_file ) ) {
			$style_deps = array( 'wp-components' );
			if ( in_array( 'wp-commands', $script_deps, true ) ) {
				$style_deps[] = 'wp-commands';
			}
			wp_enqueue_style(
				self::HANDLE,
				CHINA_YES_PLUGIN_URL . 'build/' . basename( $style_file ),
				$style_deps,
				$version
			);
		}

		$payload = wp_json_encode( $this->bootstrap_payload() );
		if ( ! is_string( $payload ) ) {
			return;
		}

		wp_add_inline_script(
			self::HANDLE,
			'window.wpcyAdmin = ' . $payload . ';',
			'before'
		);
	}

	/**
	 * Whether the current request is one of the four React pages.
	 *
	 * @since 4.0.0
	 */
	public function is_app_screen(): bool {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page slug, capability checked at render.

		return in_array( $page, self::APP_PAGES, true );
	}

	/**
	 * Bootstrap object: nonce, REST root, capabilities, settings snapshot only.
	 *
	 * @since 4.0.0
	 *
	 * @return array{nonce: string, restRoot: string, capabilities: array<string, bool>, settings: array<string, mixed>, pluginVersion: string, siteContext: array<string, mixed>}
	 */
	public function bootstrap_payload(): array {
		$nonce = '';
		if ( function_exists( 'wp_create_nonce' ) ) {
			$nonce = (string) wp_create_nonce( 'wp_rest' );
		}

		$rest_root = '';
		if ( function_exists( 'rest_url' ) ) {
			$rest_root = esc_url_raw( rest_url() );
		}

		return array(
			'nonce'         => $nonce,
			'restRoot'      => $rest_root,
			'capabilities'  => array(
				'manage_options'         => current_user_can( 'manage_options' ),
				'manage_network_options' => current_user_can( 'manage_network_options' ),
			),
			'settings'      => $this->repository->all(),
			'pluginVersion' => defined( 'CHINA_YES_VERSION' ) ? (string) CHINA_YES_VERSION : '',
			'siteContext'   => $this->site_context(),
		);
	}

	/**
	 * Site context for host bridge init. No roles, no email.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	private function site_context(): array {
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
	 * Phosphor-style leaf as a data-URI SVG (no icon font).
	 *
	 * @since 4.0.0
	 */
	private function menu_icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none"><path stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" d="M3 17s6-2 7.3-12c7.3 1.3 8.7 7.3 8.7 10.7C11 15.7 3 17 3 17z"/><path stroke="white" stroke-width="1.5" stroke-linecap="round" d="M3 17c5.3-3.3 8-8 7.3-12"/></svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- menu icon data URI, not obfuscation.
	}
}
