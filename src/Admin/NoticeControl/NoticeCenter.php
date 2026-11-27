<?php
/**
 * WordPress admin notification center drawer aligned with WP Feature Notifications
 * and Core "Declared Intent" principles.
 *
 * Operational alerts (core updates, security, Site Health, errors, settings feedback)
 * stay visible inline; non-critical third-party notices fold cleanly into a top-bar
 * drawer with live count badge and one-click restore.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin\NoticeControl;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Admin Bar notification center entry, drawer shell, and enqueues its assets.
 */
final class NoticeCenter {

	/**
	 * Asset handle prefix.
	 *
	 * @since 4.0.0
	 */
	public const HANDLE = 'wpcy-notice-center';

	/**
	 * Screens where inline notices represent critical operational streams and are never folded.
	 *
	 * @var list<string>
	 */
	public const EXCLUDED_SCREENS = array(
		'update-core.php',
		'update.php',
		'site-health.php',
		'upgrade.php',
	);

	/**
	 * Detect whether WordPress Core or the official WP Feature Notifications plugin
	 * already provides a native notification hub so WPCY yields gracefully.
	 *
	 * @since 4.0.0
	 */
	public static function has_native_notification_center(): bool {
		$detected = function_exists( 'wp_notifications_register_channel' )
			|| class_exists( 'WP_Notifications', false )
			|| class_exists( 'WordPress\\FeatureNotifications\\Notifications', false )
			|| defined( 'WP_FEATURE_NOTIFICATIONS_VERSION' );

		if ( function_exists( 'apply_filters' ) ) {
			$detected = (bool) apply_filters( 'wpcy_notice_center_native_detected', $detected );
		}

		return $detected;
	}

	/**
	 * Whether the notice center drawer should activate on the current admin screen.
	 *
	 * @param string $hook_suffix Optional current admin page filename (e.g. `plugins.php`).
	 */
	public static function should_activate_on_screen( string $hook_suffix = '' ): bool {
		if ( self::has_native_notification_center() ) {
			return false;
		}
		if ( function_exists( 'is_admin' ) && ! is_admin() ) {
			return false;
		}
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return false;
		}
		if ( function_exists( 'current_user_can' ) && ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		if ( '' !== $hook_suffix && in_array( $hook_suffix, self::EXCLUDED_SCREENS, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Register the notification center bell entry in the WordPress Admin Bar.
	 *
	 * Hidden by default until client JS collects >= 1 foldable third-party notice,
	 * keeping the toolbar clean when a page has zero noise.
	 *
	 * @param mixed $wp_admin_bar WordPress admin bar instance (`WP_Admin_Bar`).
	 */
	public function add_admin_bar_node( $wp_admin_bar ): void {
		if ( ! is_object( $wp_admin_bar ) || ! method_exists( $wp_admin_bar, 'add_node' ) ) {
			return;
		}
		$pagenow = isset( $GLOBALS['pagenow'] ) && is_string( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : '';
		if ( ! self::should_activate_on_screen( $pagenow ) ) {
			return;
		}

		$title = '<span class="wpcy-nc-bar-trigger wenpai-native" aria-label="' . esc_attr__( '后台通知收纳中心', 'wp-china-yes' ) . '">'
			. '<span class="dashicons dashicons-bell wpcy-nc-bar-icon" aria-hidden="true"></span>'
			. '<span class="wpcy-nc-bar-label">' . esc_html__( '通知', 'wp-china-yes' ) . '</span>'
			. '<span class="wpcy-nc-bar-badge" data-wpcy-nc-count>0</span>'
			. '</span>';

		$wp_admin_bar->add_node(
			array(
				'id'     => 'wpcy-notice-center',
				'parent' => 'top-secondary',
				'title'  => $title,
				'href'   => '#wpcy-notice-drawer',
				'meta'   => array(
					'class' => 'wpcy-nc-bar-item is-empty',
					'title' => __( '查看已收纳的插件通知', 'wp-china-yes' ),
				),
			)
		);
	}

	/**
	 * Enqueue notice center styles and script across wp-admin when enabled.
	 *
	 * @param string $hook_suffix Current admin screen filename.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! self::should_activate_on_screen( $hook_suffix ) ) {
			return;
		}
		if ( ! defined( 'CHINA_YES_PLUGIN_PATH' ) || ! defined( 'CHINA_YES_PLUGIN_URL' ) ) {
			return;
		}

		$css_path = CHINA_YES_PLUGIN_PATH . 'assets/css/wpcy-notice-center.css';
		$js_path  = CHINA_YES_PLUGIN_PATH . 'assets/js/wpcy-notice-center.js';
		$version  = defined( 'CHINA_YES_VERSION' ) ? (string) CHINA_YES_VERSION : '4.0.0';

		$tokens_path = CHINA_YES_PLUGIN_PATH . 'lib/wenpai-admin-ui/css/wenpai-tokens.css';
		$css_deps    = array();
		if ( is_readable( $tokens_path ) && function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style(
				'wenpai-admin-tokens',
				CHINA_YES_PLUGIN_URL . 'lib/wenpai-admin-ui/css/wenpai-tokens.css',
				array(),
				$version . '-' . (int) filemtime( $tokens_path )
			);
			$css_deps[] = 'wenpai-admin-tokens';
		}

		if ( is_readable( $css_path ) && function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style(
				self::HANDLE,
				CHINA_YES_PLUGIN_URL . 'assets/css/wpcy-notice-center.css',
				$css_deps,
				$version . '-' . (int) filemtime( $css_path )
			);
		}

		if ( is_readable( $js_path ) && function_exists( 'wp_enqueue_script' ) ) {
			wp_enqueue_script(
				self::HANDLE,
				CHINA_YES_PLUGIN_URL . 'assets/js/wpcy-notice-center.js',
				array( 'common' ),
				$version . '-' . (int) filemtime( $js_path ),
				true
			);
		}
	}

	/**
	 * Render the accessible slide-over drawer container in admin_footer.
	 */
	public function render_drawer(): void {
		$pagenow = isset( $GLOBALS['pagenow'] ) && is_string( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : '';
		if ( ! self::should_activate_on_screen( $pagenow ) ) {
			return;
		}
		?>
		<div id="wpcy-notice-drawer" class="wpcy-nc-drawer wenpai-native" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="wpcy-nc-title">
			<div class="wpcy-nc-backdrop" data-wpcy-nc-close></div>
			<div class="wpcy-nc-panel">
				<header class="wpcy-nc-head">
					<div class="wpcy-nc-head-main">
						<h2 id="wpcy-nc-title" class="wpcy-nc-title"><?php esc_html_e( '通知收纳中心', 'wp-china-yes' ); ?></h2>
						<span class="wpcy-nc-sub" data-wpcy-nc-summary><?php esc_html_e( '已收纳非关键插件提示，系统与安全告警保留在页面顶部', 'wp-china-yes' ); ?></span>
					</div>
					<div class="wpcy-nc-actions">
						<button type="button" class="wpcy-nc-btn" data-wpcy-nc-restore><?php esc_html_e( '放回页顶', 'wp-china-yes' ); ?></button>
						<button type="button" class="wpcy-nc-close" data-wpcy-nc-close aria-label="<?php esc_attr_e( '关闭通知抽屉', 'wp-china-yes' ); ?>">&times;</button>
					</div>
				</header>
				<div class="wpcy-nc-body" data-wpcy-nc-list></div>
				<div class="wpcy-nc-empty" data-wpcy-nc-empty hidden>
					<?php esc_html_e( '当前页面没有需要收纳的插件提示。', 'wp-china-yes' ); ?>
				</div>
			</div>
		</div>
		<?php
	}
}
