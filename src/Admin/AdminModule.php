<?php
/**
 * Admin React app: four menu pages and build/ enqueue.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin;

use WenPai\ChinaYes\Admin\Announcements\AnnouncementsModule;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Core\Module;
use WenPai\ChinaYes\Diagnostics\RouteGroups;
use WenPai\ChinaYes\Migration\LegacyReader;
use WenPai\ChinaYes\Migration\Runner;

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
	 * Per-site option (get_option / update_option, not a network option) set
	 * once the first-run redirect to the wizard has happened on this site.
	 *
	 * @since 4.0.0
	 */
	public const ONBOARDING_REDIRECTED = 'wpcy_onboarding_redirected';

	/**
	 * Network-admin settings slug (same React page, network context).
	 *
	 * @since 4.0.0
	 */
	public const NETWORK_SLUG = 'wpcy-connect';

	/**
	 * Pages served by the React app (not the PHP recovery page).
	 *
	 * @since 4.0.0
	 * @var list<string>
	 */
	public const APP_PAGES = array(
		'wpcy',
		'wpcy-connect',
		'wpcy-features',
		'wpcy-services',
		'wpcy-diagnose',
		'wpcy-onboarding',
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
	 * Hook admin_menu, network_admin_menu, admin_init (first-run redirect and
	 * wizard guard), admin_head (hide the wizard submenu), admin_enqueue_scripts
	 * and init. Constructor does not register hooks.
	 *
	 * @since 4.0.0
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_pages' ), 5 );
		add_action( 'admin_menu', array( $this, 'append_legacy_suite_submenus' ), 999 );
		add_action( 'network_admin_menu', array( $this, 'add_network_pages' ) );
		add_action( 'admin_page_access_denied', array( $this, 'redirect_legacy_options_url' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_wrong_parent' ), 1 );
		add_action( 'admin_init', array( $this, 'maybe_redirect_onboarding' ) );
		add_action( 'admin_head', array( $this, 'hide_onboarding_submenu' ) );
		add_action( 'admin_head', array( $this, 'collapse_internal_submenus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_news_widget' ), 20 );
		add_action( 'init', array( $this, 'register_user_meta' ) );
		add_filter( 'submenu_file', array( $this, 'highlight_parent_submenu_for_tabs' ) );
		add_filter( 'wenpai_admin_ui_suite_parent_slug', array( $this, 'filter_suite_parent_slug' ), 10, 2 );
		WhiteLabel::register( $this->repository );
	}

	/**
	 * Return `wpcy` as parent menu slug for WenPai companion plugins when suite menu grouping is enabled.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $parent_slug Default parent slug ('').
	 * @param mixed $slug        Companion plugin menu slug.
	 */
	public function filter_suite_parent_slug( $parent_slug, $slug = '' ): string {
		if ( $this->in_network_admin() ) {
			return is_string( $parent_slug ) ? $parent_slug : '';
		}
		$clean = is_string( $slug ) ? sanitize_key( $slug ) : '';
		if ( '' === $clean || self::SLUG === $clean || 0 === strpos( $clean, self::SLUG . '-' ) ) {
			return is_string( $parent_slug ) ? $parent_slug : '';
		}
		$settings = $this->repository->all();
		$grouped  = ! empty( $settings['admin']['group_suite_menus'] );
		return $grouped ? self::SLUG : ( is_string( $parent_slug ) ? $parent_slug : '' );
	}

	/**
	 * Append submenu shortcuts under 文派叶子 for active companion plugins not yet using kit 0.12.2+ menu hook.
	 *
	 * Keeps WordPress `$_parent_pages` intact so `settings_page_<slug>` callbacks never 403.
	 *
	 * @since 4.0.0
	 */
	public function append_legacy_suite_submenus(): void {
		if ( $this->in_network_admin() ) {
			return;
		}
		$settings = $this->repository->all();
		if ( empty( $settings['admin']['group_suite_menus'] ) ) {
			return;
		}
		if ( ! isset( $GLOBALS['submenu'][ self::SLUG ] ) || ! is_array( $GLOBALS['submenu'][ self::SLUG ] ) ) {
			return;
		}

		$existing = array();
		foreach ( $GLOBALS['submenu'][ self::SLUG ] as $entry ) {
			if ( is_array( $entry ) && isset( $entry[2] ) ) {
				$existing[ (string) $entry[2] ] = true;
			}
		}

		$suite_slugs = array(
			'wpslug',
			'wpsss',
			'wpavatar',
			'wpcomments',
			'wp-chinese-converter',
			'wpicp',
			'wpstatic',
			'wpfonts',
			'wpphoto',
			'wpmind',
			'wpbridge',
		);

		if ( isset( $GLOBALS['submenu']['options-general.php'] ) && is_array( $GLOBALS['submenu']['options-general.php'] ) ) {
			foreach ( $GLOBALS['submenu']['options-general.php'] as $item ) {
				if ( ! is_array( $item ) || ! isset( $item[0], $item[1], $item[2] ) ) {
					continue;
				}
				$item_slug = (string) $item[2];
				if ( ! in_array( $item_slug, $suite_slugs, true ) ) {
					continue;
				}
				$target = 'options-general.php?page=' . $item_slug;
				if ( isset( $existing[ $item_slug ] ) || isset( $existing[ $target ] ) ) {
					continue;
				}
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- appending opt-in suite shortcut to own submenu.
				$GLOBALS['submenu'][ self::SLUG ][] = array( (string) $item[0], (string) $item[1], $target );
				$existing[ $target ]                = true;
			}
		}

		if ( isset( $GLOBALS['menu'] ) && is_array( $GLOBALS['menu'] ) ) {
			$adm_script = 'admin';
			$adm_entry  = $adm_script . '.php?page=';
			foreach ( $GLOBALS['menu'] as $top_item ) {
				if ( ! is_array( $top_item ) || ! isset( $top_item[0], $top_item[1], $top_item[2] ) ) {
					continue;
				}
				$top_slug = (string) $top_item[2];
				if ( ! in_array( $top_slug, $suite_slugs, true ) ) {
					continue;
				}
				$target = $adm_entry . $top_slug;
				if ( ! isset( $existing[ $top_slug ] ) && ! isset( $existing[ $target ] ) ) {
					// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- appending opt-in suite shortcut to own submenu.
					$GLOBALS['submenu'][ self::SLUG ][] = array( (string) $top_item[0], (string) $top_item[1], $target );
					$existing[ $target ]                = true;
				}
			}
		}
	}

	/**
	 * Redirect top-level pages opened under options-general.php so the left menu highlights 文派叶子.
	 *
	 * @since 4.0.0
	 */
	public function maybe_redirect_wrong_parent(): void {
		$script = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
		if ( '' === $script && isset( $_SERVER['SCRIPT_NAME'] ) ) {
			$script = basename( sanitize_text_field( wp_unslash( (string) $_SERVER['SCRIPT_NAME'] ) ) );
		}
		if ( 'options-general.php' !== $script ) {
			return;
		}
		$this->redirect_legacy_options_url();
	}

	/**
	 * Redirect legacy 3.x ?page=wp-china-yes or options-general.php?page=wpcy* links to canonical top-level URLs.
	 *
	 * @since 4.0.0
	 */
	public function redirect_legacy_options_url(): void {
		if ( $this->in_network_admin() ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$slug = $this->current_page();
		if ( 'wp-china-yes' === $slug ) {
			$slug = self::SLUG;
		} elseif ( ! $this->is_app_screen() || '' === $slug ) {
			return;
		}
		$extra = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- preserving safe read-only navigation query args across 302.
		foreach ( $_GET as $key => $val ) {
			$clean_key = sanitize_key( (string) $key );
			if ( '' === $clean_key || 'page' === $clean_key || ! is_scalar( $val ) ) {
				continue;
			}
			$extra[ $clean_key ] = sanitize_text_field( wp_unslash( (string) $val ) );
		}
		wp_safe_redirect( self::app_url( $slug, $extra ) );
		exit;
	}

	/**
	 * Top-level 文派叶子 plus five primary tabs and hidden onboarding wizard.
	 *
	 * @since 4.0.0
	 */
	public function add_pages(): void {
		$cap        = 'manage_options';
		$settings   = $this->repository->all();
		$menu_name  = WhiteLabel::menu_name( $settings );
		$brand_name = WhiteLabel::brand_name( $settings );
		$cb         = array( $this, 'render' );

		\Wenpai_Admin_Menu::register(
			array(
				'slug'         => self::SLUG,
				'label'        => $menu_name,
				'title'        => __( '概览', 'wp-china-yes' ),
				'capability'   => $cap,
				'render'       => $cb,
				'top_level'    => true,
				'icon'         => $this->menu_icon(),
				'position'     => 80,
				'legacy_slugs' => array( 'wp-china-yes' ),
				'submenus'     => array(
					array(
						'slug'   => self::SLUG,
						'label'  => $brand_name,
						'render' => $cb,
					),
					array(
						'slug'   => 'wpcy-connect',
						'label'  => __( '设置', 'wp-china-yes' ),
						'render' => $cb,
					),
					array(
						'slug'   => 'wpcy-features',
						'label'  => __( '功能', 'wp-china-yes' ),
						'render' => $cb,
					),
					array(
						'slug'   => 'wpcy-services',
						'label'  => __( '服务', 'wp-china-yes' ),
						'render' => $cb,
					),
					array(
						'slug'   => 'wpcy-diagnose',
						'label'  => __( '诊断', 'wp-china-yes' ),
						'render' => $cb,
					),
					array(
						'slug'   => 'wpcy-onboarding',
						'label'  => __( '首次设置', 'wp-china-yes' ),
						'render' => $cb,
					),
				),
			)
		);
	}

	/**
	 * Hide the wizard from the sidebar without unregistering it.
	 *
	 * Runs on admin_head, after core's page access check: removing the
	 * submenu during admin_menu makes ?page=wpcy-onboarding answer
	 * "Sorry, you are not allowed to access this page". The hook has to run
	 * on every admin screen because the sidebar is rendered on every screen.
	 *
	 * @since 4.0.0
	 */
	public function hide_onboarding_submenu(): void {
		if ( function_exists( 'remove_submenu_page' ) ) {
			remove_submenu_page( self::SLUG, 'wpcy-onboarding' );
		}
	}

	/**
	 * Hide WPCY's own horizontal-tab pages from the WP left sidebar after access checks pass,
	 * and collapse the submenu completely unless companion WenPai plugins are grouped under it.
	 *
	 * @since 4.0.0
	 */
	public function collapse_internal_submenus(): void {
		$internal_tabs = array( 'wpcy-connect', 'wpcy-features', 'wpcy-services', 'wpcy-diagnose', 'wpcy-onboarding' );
		if ( function_exists( 'remove_submenu_page' ) ) {
			foreach ( array( 'wpcy-connect', 'wpcy-features', 'wpcy-services', 'wpcy-diagnose' ) as $tab_slug ) {
				remove_submenu_page( self::SLUG, $tab_slug );
			}
		}
		if ( ! isset( $GLOBALS['submenu'][ self::SLUG ] ) || ! is_array( $GLOBALS['submenu'][ self::SLUG ] ) ) {
			return;
		}
		foreach ( $GLOBALS['submenu'][ self::SLUG ] as $idx => $entry ) {
			if ( is_array( $entry ) && isset( $entry[2] ) && in_array( (string) $entry[2], $internal_tabs, true ) ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- pruning own horizontal-tab slugs from sidebar submenu.
				unset( $GLOBALS['submenu'][ self::SLUG ][ $idx ] );
			}
		}
		if ( 1 === count( $GLOBALS['submenu'][ self::SLUG ] ) ) {
			$only = reset( $GLOBALS['submenu'][ self::SLUG ] );
			if ( is_array( $only ) && isset( $only[2] ) && self::SLUG === (string) $only[2] ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- mirrors wp-admin/includes/menu.php single-item submenu collapse so no empty dropdown renders.
				unset( $GLOBALS['submenu'][ self::SLUG ] );
			}
		}
	}

	/**
	 * Keep the first submenu entry (`wpcy`) highlighted when viewing WPCY's internal tabs
	 * while companion plugins are grouped into the submenu.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $submenu_file Current submenu slug.
	 * @return mixed
	 */
	public function highlight_parent_submenu_for_tabs( $submenu_file ) {
		if ( in_array( $this->current_page(), array( 'wpcy-connect', 'wpcy-features', 'wpcy-services', 'wpcy-diagnose' ), true ) ) {
			return self::SLUG;
		}
		return $submenu_file;
	}

	/**
	 * Network admin: 叶子 / 文派 / custom brand → settings only (CO-06 network view).
	 *
	 * Reads and writes wpcy_network_settings through /network-settings.
	 *
	 * @since 4.0.0
	 */
	public function add_network_pages(): void {
		\Wenpai_Admin_Menu::register(
			array(
				'slug'       => self::NETWORK_SLUG,
				'label'      => WhiteLabel::menu_name( $this->repository->all() ),
				'title'      => __( '设置', 'wp-china-yes' ),
				'capability' => 'manage_network_options',
				'render'     => array( $this, 'render' ),
				'top_level'  => true,
				'network'    => true,
				'icon'       => $this->menu_icon(),
				'position'   => 80,
			)
		);
	}

	/**
	 * On admin_init: keep read-only subsites out of the wizard, then send a
	 * fresh site to the wizard once (spec §2).
	 *
	 * @since 4.0.0
	 */
	public function maybe_redirect_onboarding(): void {
		$blocked = $this->locked_wizard_target();
		if ( '' !== $blocked ) {
			wp_safe_redirect( $blocked );
			exit;
		}

		$target = $this->onboarding_redirect_target();
		if ( '' === $target ) {
			return;
		}

		update_option( self::ONBOARDING_REDIRECTED, '1', false );
		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Settings URL when a read-only subsite (CO-06) opens the wizard, else
	 * empty string. The wizard writes the scene, which the subsite cannot.
	 *
	 * @since 4.0.0
	 */
	public function locked_wizard_target(): string {
		if ( 'wpcy-onboarding' !== $this->current_page() || ! $this->network_locked() ) {
			return '';
		}

		return self::app_url( 'wpcy-connect' );
	}

	/**
	 * Wizard URL when the first-run redirect applies, else empty string.
	 *
	 * Applies to a single site and to the multisite main site. Subsites follow
	 * the network (L-7 ⑤) and the network admin has no wizard. Skipped for
	 * 3.x upgrades (migration report, or a 3.x option whose migration has not
	 * produced a report yet) and in recovery mode. The marker is per site.
	 *
	 * @since 4.0.0
	 */
	public function onboarding_redirect_target(): string {
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return '';
		}
		if ( $this->in_network_admin() ) {
			return '';
		}
		if ( function_exists( 'is_multisite' ) && is_multisite()
			&& ( ! function_exists( 'is_main_site' ) || ! is_main_site() ) ) {
			return '';
		}
		if ( ! $this->is_app_screen() ) {
			return '';
		}
		if ( 'wpcy-onboarding' === $this->current_page() ) {
			return '';
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}
		if ( '' !== (string) get_option( self::ONBOARDING_REDIRECTED, '' ) ) {
			return '';
		}
		if ( true === $this->repository->get( 'recovery_mode', false ) ) {
			return '';
		}
		$confirmed = $this->repository->get( 'profile_confirmed_at' );
		if ( is_string( $confirmed ) && '' !== $confirmed ) {
			return '';
		}
		if ( array() !== ( new Runner() )->stored_report() ) {
			return '';
		}
		if ( ( new LegacyReader() )->exists() ) {
			return '';
		}

		return self::app_url( 'wpcy-onboarding' );
	}

	/**
	 * Canonical top-level admin page URL via Wenpai_Admin_Menu.
	 *
	 * @param string                $slug  Page slug.
	 * @param array<string, string> $extra Extra query args.
	 * @return string
	 */
	public static function app_url( string $slug, array $extra = array() ): string {
		if ( class_exists( '\Wenpai_Admin_Menu', false ) ) {
			return (string) \Wenpai_Admin_Menu::url( $slug, $extra );
		}
		$script = 'admin';
		$query  = '?page=' . rawurlencode( $slug );
		foreach ( $extra as $k => $v ) {
			$query .= '&' . rawurlencode( (string) $k ) . '=' . rawurlencode( (string) $v );
		}
		return function_exists( 'admin_url' ) ? (string) admin_url( $script . '.php' . $query ) : $query;
	}

	/**
	 * Current ?page= slug (read-only).
	 *
	 * @since 4.0.0
	 */
	private function current_page(): string {
		return isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page slug.
	}

	/**
	 * Mount point. Title lives in the React Page header.
	 *
	 * @since 4.0.0
	 */
	public function render(): void {
		if ( $this->in_network_admin() ) {
			if ( ! current_user_can( 'manage_network_options' ) ) {
				wp_die( esc_html__( '暂时无法打开该页面，请确认你有管理权限。', 'wp-china-yes' ), 403 );
			}
			KitRenderer::render( self::NETWORK_SLUG, $this->bootstrap_payload() );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '暂时无法打开该页面，请确认你有管理权限。', 'wp-china-yes' ), 403 );
		}

		KitRenderer::render( $this->current_page(), $this->bootstrap_payload() );
	}

	/**
	 * Enqueue vendored wenpai-admin-ui kit and lightweight native JS on app screens.
	 *
	 * @since 4.0.0
	 *
	 * @param string $hook_suffix Current admin screen id.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( 'index.php' === $hook_suffix && $this->should_show_dashboard_news_widget( $this->repository->all() ) ) {
			if ( class_exists( '\Wenpai_Admin_Loader', false ) ) {
				\Wenpai_Admin_Loader::enqueue( array( 'full' => false ) );
			}
			return;
		}

		if ( ! $this->is_app_screen() ) {
			return;
		}

		if ( class_exists( '\Wenpai_Admin_Loader', false ) ) {
			\Wenpai_Admin_Loader::enqueue( array( 'pages' => self::APP_PAGES ) );
		}

		$css_path = CHINA_YES_PLUGIN_PATH . 'assets/css/wpcy-admin.css';
		if ( is_readable( $css_path ) ) {
			wp_enqueue_style(
				self::HANDLE,
				CHINA_YES_PLUGIN_URL . 'assets/css/wpcy-admin.css',
				array( 'wenpai-admin-ui' ),
				CHINA_YES_VERSION . '-' . (int) filemtime( $css_path )
			);
		}

		$js_path = CHINA_YES_PLUGIN_PATH . 'assets/js/wpcy-admin.js';
		if ( ! is_readable( $js_path ) ) {
			return;
		}

		wp_enqueue_script(
			self::HANDLE,
			CHINA_YES_PLUGIN_URL . 'assets/js/wpcy-admin.js',
			array( 'wenpai-admin-ui' ),
			CHINA_YES_VERSION . '-' . (int) filemtime( $js_path ),
			true
		);

		$payload = wp_json_encode( $this->bootstrap_payload() );
		if ( ! is_string( $payload ) ) {
			return;
		}

		wp_add_inline_script(
			self::HANDLE,
			'window.wpcyAdmin = ' . $payload . ';',
			'before'
		);

		foreach ( array( 'probes', 'apps', 'providers' ) as $mod ) {
			$mod_file = 'assets/js/wpcy-' . $mod . '.js';
			if ( is_readable( CHINA_YES_PLUGIN_PATH . $mod_file ) ) {
				wp_enqueue_script(
					self::HANDLE . '-' . $mod,
					CHINA_YES_PLUGIN_URL . $mod_file,
					array( self::HANDLE ),
					CHINA_YES_VERSION,
					true
				);
			}
		}
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

		$settings = $this->repository->all();

		return array(
			'nonce'         => $nonce,
			'restRoot'      => $rest_root,
			'capabilities'  => array(
				'manage_options'         => current_user_can( 'manage_options' ),
				'manage_network_options' => current_user_can( 'manage_network_options' ),
				'network_admin'          => $this->in_network_admin(),
				'network_locked'         => $this->network_locked(),
			),
			'settings'      => $settings,
			'pluginVersion' => defined( 'CHINA_YES_VERSION' ) ? (string) CHINA_YES_VERSION : '',
			'siteContext'   => $this->site_context(),
			'links'         => self::links(),
			'providers'     => self::providers(),
		);
	}

	/**
	 * Whether the replacement community/agency news widget should be registered on native WP Dashboard (`index.php`).
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 */
	public function should_show_dashboard_news_widget( array $settings ): bool {
		if ( ! empty( $settings['recovery_mode'] ) ) {
			return false;
		}
		$conn  = isset( $settings['connectivity'] ) && is_array( $settings['connectivity'] ) ? $settings['connectivity'] : array();
		$block = 'block' === (string) ( $conn['dashboard_feeds'] ?? 'allow' );
		if ( function_exists( 'apply_filters' ) ) {
			$block = (bool) apply_filters( 'wpcy_block_dashboard_feeds', $block );
		}
		if ( ! $block ) {
			return false;
		}
		$news_enabled = ! isset( $settings['admin']['news_enabled'] ) || (bool) $settings['admin']['news_enabled'];
		if ( ! $news_enabled ) {
			return false;
		}
		$wl = WhiteLabel::resolve( $settings );
		if ( $wl['enabled'] && $wl['hide_links'] && '' === $wl['news_feed_url'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Register the fast domestic / white-label news widget on native WP Dashboard (`index.php`)
	 * when slow default WP events/news feeds are replaced.
	 */
	public function register_dashboard_news_widget(): void {
		if ( ! function_exists( 'wp_add_dashboard_widget' ) ) {
			return;
		}
		$settings = $this->repository->all();
		if ( ! $this->should_show_dashboard_news_widget( $settings ) ) {
			return;
		}
		$wl    = WhiteLabel::resolve( $settings );
		$title = $wl['enabled']
			? sprintf( /* translators: %s: agency brand name */ __( '%s 动态与公告', 'wp-china-yes' ), '' !== $wl['brand_name'] ? $wl['brand_name'] : __( '站点', 'wp-china-yes' ) )
			: __( '文派茶馆动态', 'wp-china-yes' );

		wp_add_dashboard_widget(
			'wpcy_dashboard_news',
			$title,
			array( $this, 'render_dashboard_news_widget' ),
			null,
			null,
			'side',
			'core'
		);
	}

	/**
	 * Render the native WP Dashboard replacement news widget via cached `AnnouncementsModule` items (zero blocking HTTP).
	 */
	public function render_dashboard_news_widget(): void {
		$settings      = $this->repository->all();
		$default_ann   = AnnouncementsModule::READ_URL;
		$ann_source    = function_exists( 'apply_filters' ) ? apply_filters( 'wpcy_announcements_source', $default_ann ) : $default_ann;
		$announcements = new AnnouncementsModule( $this->repository, is_string( $ann_source ) ? $ann_source : $default_ann );
		$cached        = $announcements->cached_document();
		if ( null === $cached && function_exists( 'wp_next_scheduled' ) && function_exists( 'wp_schedule_single_event' ) ) {
			if ( ! wp_next_scheduled( AnnouncementsModule::CRON_HOOK ) ) {
				wp_schedule_single_event( time(), AnnouncementsModule::CRON_HOOK );
			}
		}
		$items = is_array( $cached ) ? $announcements->payload()['items'] : array();
		KitRenderer::render_dashboard_news_widget( $settings, $items );
	}

	/**
	 * Whether this request is the network admin screen.
	 *
	 * @since 4.0.0
	 */
	private function in_network_admin(): bool {
		return function_exists( 'is_network_admin' ) && is_network_admin();
	}

	/**
	 * CO-06 subsite read-only: multisite, site screen, and the network has not
	 * opened allow_site_override. Applies to super admins too: the server does
	 * not keep subsite writes while the override is closed, so network policy
	 * is edited in the network admin.
	 *
	 * @since 4.0.0
	 */
	public function network_locked(): bool {
		if ( ! function_exists( 'is_multisite' ) || ! is_multisite() ) {
			return false;
		}
		if ( $this->in_network_admin() ) {
			return false;
		}

		return true !== $this->repository->get( 'allow_site_override' );
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
			'help'      => 'https://wpcy.com/go/support',
			'feedback'  => 'https://wpcy.com/feedback',
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

		register_meta(
			'user',
			'wpcy_settings_view',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => 'simple',
				'auth_callback'     => static function ( $allowed, $meta_key, $object_id ) {
					unset( $allowed, $meta_key );

					return current_user_can( 'edit_user', (int) $object_id );
				},
				'sanitize_callback' => static function ( $value ) {
					return 'advanced' === $value ? 'advanced' : 'simple';
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
