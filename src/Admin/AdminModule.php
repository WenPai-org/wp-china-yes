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
use WenPai\ChinaYes\Diagnostics\RouteGroups;
use WenPai\ChinaYes\Rest\DocumentWriter;

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
		add_action( 'init', array( $this, 'register_user_meta' ) );
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
			__( '设置', 'wp-china-yes' ),
			__( '设置', 'wp-china-yes' ),
			$cap,
			'wpcy-connect',
			array( $this, 'render' )
		);

		add_submenu_page(
			self::SLUG,
			__( '服务', 'wp-china-yes' ),
			__( '服务', 'wp-china-yes' ),
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
			wp_die( esc_html__( '暂时无法打开该页面，请确认你有管理权限。', 'wp-china-yes' ), 403 );
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
	 * Bootstrap object: nonce, REST root, capabilities, settings, links, providers.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
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
			'settings'      => DocumentWriter::present_legacy_avatar( $this->repository->all() ),
			'pluginVersion' => defined( 'CHINA_YES_VERSION' ) ? (string) CHINA_YES_VERSION : '',
			'siteContext'   => $this->site_context(),
			'links'         => self::links(),
			'providers'     => self::providers(),
		);
	}

	/**
	 * Placeholder URLs for shell / eco block. Coordinator to confirm.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public static function links(): array {
		return array(
			'help'      => 'https://wpcy.com/docs/',
			'feedback'  => 'https://wpcy.com/feedback/',
			'changelog' => 'https://wpcy.com/changelog/',
			'site'      => 'https://wpcy.com/',
			'brands'    => array(
				'wenpai_org'  => 'https://wenpai.org/',
				'admincdn'    => 'https://admincdn.com/',
				'cravatar'    => 'https://cravatar.com/',
				'windfonts'   => 'https://windfonts.com/',
				'weixiaoduo'  => 'https://weixiaoduo.com/',
				'wenpai_open' => 'https://wenpai.org/',
			),
		);
	}

	/**
	 * Group id → brand original, from Diagnostics\RouteGroups.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, string>
	 */
	public static function providers(): array {
		$out = array();
		foreach ( RouteGroups::all() as $group ) {
			$out[ $group['id'] ] = $group['provider'];
		}

		return $out;
	}

	/**
	 * User meta for Hero collapse (OV-10).
	 *
	 * @since 4.0.0
	 */
	public function register_user_meta(): void {
		if ( ! function_exists( 'register_meta' ) ) {
			return;
		}

		register_meta(
			'user',
			'wpcy_overview_hero_collapsed',
			array(
				'type'              => 'boolean',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => false,
				'auth_callback'     => static function ( $allowed, $meta_key, $object_id ) {
					unset( $allowed, $meta_key );

					return current_user_can( 'edit_user', (int) $object_id );
				},
				'sanitize_callback' => static function ( $value ) {
					return (bool) $value;
				},
			)
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
	 * RemixIcon leaf (prototype .wpcy-mark path) as a data-URI SVG.
	 *
	 * @since 4.0.0
	 */
	private function menu_icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"><path d="M20.998 3V5C20.998 14.6274 15.6255 19 8.99805 19L5.24077 18.9999C5.0786 19.912 4.99805 20.907 4.99805 22H2.99805C2.99805 20.6373 3.11376 19.3997 3.34381 18.2682C3.1133 16.9741 2.99805 15.2176 2.99805 13C2.99805 7.47715 7.4752 3 12.998 3C14.998 3 16.998 4 20.998 3ZM12.998 5C8.57977 5 4.99805 8.58172 4.99805 13C4.99805 13.3624 5.00125 13.7111 5.00759 14.0459C6.26198 12.0684 8.09902 10.5048 10.5019 9.13176L11.4942 10.8682C8.6393 12.4996 6.74554 14.3535 5.77329 16.9998L8.99805 17C15.0132 17 18.8692 13.0269 18.9949 5.38766C17.6229 5.52113 16.3481 5.436 14.7754 5.20009C13.6243 5.02742 13.3988 5 12.998 5Z"/></svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- menu icon data URI, not obfuscation.
	}
}
