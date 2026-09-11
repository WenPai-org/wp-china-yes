<?php
/**
 * Server-rendered recovery page. No JavaScript.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin;

use WenPai\ChinaYes\Rest\RecoveryActions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hidden menu item ?page=wpcy-recovery. Forms POST; they do not call REST.
 */
final class RecoveryPage {

	/**
	 * Query page slug.
	 *
	 * @since 4.0.0
	 */
	public const SLUG = 'wpcy-recovery';

	/**
	 * POST field for the chosen action.
	 *
	 * @since 4.0.0
	 */
	public const ACTION_FIELD = 'wpcy_recovery_action';

	/**
	 * Nonce field name. Each form uses a distinct nonce action.
	 *
	 * @since 4.0.0
	 */
	public const NONCE_FIELD = 'wpcy_recovery_nonce';

	/**
	 * Shared recovery actions.
	 *
	 * @var RecoveryActions
	 */
	private RecoveryActions $actions;

	/**
	 * Constructor. Does not register hooks.
	 *
	 * @since 4.0.0
	 *
	 * @param RecoveryActions $actions Shared recovery actions.
	 */
	public function __construct( RecoveryActions $actions ) {
		$this->actions = $actions;
	}

	/**
	 * Hook admin_menu and admin_init. Constructor does not register hooks.
	 *
	 * @since 4.0.0
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'handle_post' ) );
		add_action( 'admin_head', array( $this, 'print_styles' ) );
	}

	/**
	 * Hidden submenu: parent options.php is not a visible menu, so the item
	 * is unlisted. Direct URL ?page=wpcy-recovery still works.
	 *
	 * Empty string / null parents leave get_admin_page_title() empty and
	 * add_submenu_page( null, … ) deprecates strip_tags(null) on PHP 8.2.
	 *
	 * @since 4.0.0
	 */
	public function add_page(): void {
		$title = __( '文派叶子 · 恢复模式', 'wp-china-yes' );

		add_submenu_page(
			'options.php',
			$title,
			$title,
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Form POST: capability + per-button nonce. Does not call REST.
	 *
	 * @since 4.0.0
	 */
	public function handle_post(): void {
		if ( ! isset( $_POST[ self::ACTION_FIELD ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked per action below.
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '暂时无法打开恢复页，请确认你有管理权限。', 'wp-china-yes' ), 403 );
		}

		$action = sanitize_key( wp_unslash( (string) $_POST[ self::ACTION_FIELD ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer immediately below.
		check_admin_referer( $this->nonce_action( $action ), self::NONCE_FIELD );

		$result = $this->actions->apply( $action );
		if ( true !== $result ) {
			return;
		}

		if ( function_exists( 'wp_safe_redirect' ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
		}

		exit;
	}

	/**
	 * Inline styles used by this page only (RC-01–05). ≤ 120 lines.
	 *
	 * @since 4.0.0
	 */
	public function print_styles(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page slug.
		if ( self::SLUG !== $page ) {
			return;
		}

		echo '<style id="wpcy-recovery-css">
.wpcy-rec{max-width:560px;margin:44px auto 64px;font-family:-apple-system,"Noto Sans","Helvetica Neue",Helvetica,Arial,"PingFang SC","Hiragino Sans GB","Noto Sans CJK SC","Source Han Sans SC","Microsoft YaHei",sans-serif;color:#1e1e1e;font-size:14px;line-height:22px;-webkit-font-smoothing:antialiased}
.wpcy-rec .card{background:#fff;border:1px solid #e5e5e5;border-radius:12px;padding:24px 28px}
.wpcy-rec .card-head{display:flex;align-items:center;gap:14px;margin-bottom:18px}
.wpcy-rec .tile{width:36px;height:36px;border-radius:10px;display:grid;place-items:center;flex:none;background:#fbf3dc;color:#9a7208}
.wpcy-rec .card-title{font-size:16px;line-height:24px;font-weight:600;margin:0}
.wpcy-rec .card-sub{color:#646970;font-size:13px;line-height:20px;margin:0}
.wpcy-rec .rows>div{display:flex;align-items:flex-start;gap:14px;padding:18px 0;border-top:1px solid #f0f0f1}
.wpcy-rec .rows>div:first-child{border-top:0;padding-top:0}
.wpcy-rec .t{font-weight:500;font-size:14px}
.wpcy-rec .d{color:#646970;font-size:13px;line-height:20px;margin-top:2px;max-width:46ch}
.wpcy-rec .r{margin-left:auto;margin-top:2px}
.wpcy-rec .btn{display:inline-flex;align-items:center;justify-content:center;height:36px;padding:0 16px;border-radius:6px;font:inherit;font-weight:500;cursor:pointer;border:1px solid transparent;white-space:nowrap;font-size:14px;text-decoration:none}
.wpcy-rec .btn-secondary{background:#fff;color:#1e1e1e;border-color:#c3c4c7}
.wpcy-rec .btn-danger{background:#fff;color:#d63638;border-color:#d63638}
.wpcy-rec .btn-ghost{background:transparent;color:#3858e9;border:0;padding:0;height:auto}
.wpcy-rec .card-foot{display:flex;justify-content:flex-start;gap:8px;margin-top:20px;padding-top:18px;border-top:1px solid #f0f0f1}
.wpcy-rec .notice{display:flex;gap:10px;align-items:center;padding:12px 16px;border-radius:10px;font-size:14px;background:#edfaef;color:#007017;margin:0 0 16px}
#wpfooter{display:none}
</style>';
	}

	/**
	 * Native wrap markup. No scripts enqueued.
	 *
	 * @since 4.0.0
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '暂时无法打开恢复页，请确认你有管理权限。', 'wp-china-yes' ), 403 );
		}

		$in_recovery = (bool) $this->actions->settings()['recovery_mode'];
		$overview    = $this->overview_url();

		echo '<div class="wpcy-rec">';
		echo '<article class="card">';
		echo '<div class="card-head"><div class="tile" aria-hidden="true">';
		echo '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L20.2169 2.82598C20.6745 2.92766 21 3.33347 21 3.80217V13.7889C21 15.795 19.9974 17.6684 18.3282 18.7812L12 23L5.6718 18.7812C4.00261 17.6684 3 15.795 3 13.7889V3.80217C3 3.33347 3.32553 2.92766 3.78307 2.82598L12 1ZM12 3.04879L5 4.60434V13.7889C5 15.1263 5.6684 16.3752 6.7812 17.1171L12 20.5963L17.2188 17.1171C18.3316 16.3752 19 15.1263 19 13.7889V4.60434L12 3.04879Z"/></svg>';
		echo '</div><div><h1 class="card-title">' . esc_html__( '文派叶子 · 恢复模式', 'wp-china-yes' ) . '</h1>';
		echo '<p class="card-sub">' . esc_html__( '此页不依赖 JavaScript，后台样式错乱或站点无法访问时也能打开', 'wp-china-yes' ) . '</p></div></div>';

		if ( $in_recovery ) {
			echo '<div class="notice"><span>' . esc_html__( '恢复模式已开启', 'wp-china-yes' ) . '</span></div>';
		} else {
			echo '<p style="margin:0 0 4px">' . esc_html__( '先试第一项；不够再用第二项。两项都不会删除你的设置，随时可在设置里重新开启。', 'wp-china-yes' ) . '</p>';
			echo '<div class="rows recover">';
			echo '<div><div><div class="t">' . esc_html__( '只关闭 URL 改写', 'wp-china-yes' ) . '</div>';
			echo '<div class="d">' . esc_html__( '页面里的资源地址回到原始来源；心跳节流、仪表盘屏蔽等其它功能保留。后台样式错乱时通常这一步就够。', 'wp-china-yes' ) . '</div></div>';
			$this->action_form( RecoveryActions::DISABLE_REWRITES, __( '关闭 URL 改写', 'wp-china-yes' ), 'btn btn-secondary r' );
			echo '</div>';
			echo '<div><div><div class="t">' . esc_html__( '停用全部模块', 'wp-china-yes' ) . '</div>';
			echo '<div class="d">' . esc_html__( '相当于停用本插件，但保留全部设置与迁移记录。站点仍无法访问时用这一步。', 'wp-china-yes' ) . '</div></div>';
			$this->action_form( RecoveryActions::DISABLE_MODULES, __( '停用全部模块', 'wp-china-yes' ), 'btn btn-danger r' );
			echo '</div></div>';
		}

		echo '<div class="card-foot">';
		if ( $in_recovery ) {
			$this->action_form( RecoveryActions::EXIT, __( '退出恢复模式', 'wp-china-yes' ), 'btn btn-secondary' );
		}
		echo '<a class="btn btn-ghost" href="' . esc_url( $overview ) . '">' . esc_html__( '返回概览', 'wp-china-yes' ) . '</a>';
		echo '</div>';
		echo '</article></div>';
	}

	/**
	 * Nonce action for one recovery button.
	 *
	 * @since 4.0.0
	 *
	 * @param string $action disable_rewrites|disable_modules|exit.
	 */
	public function nonce_action( string $action ): string {
		return 'wpcy_recovery_' . $action;
	}

	/**
	 * One POST form, one nonce, one submit button.
	 *
	 * @since 4.0.0
	 *
	 * @param string $action       Recovery action.
	 * @param string $label        Button label (already translated).
	 * @param string $button_class CSS classes.
	 */
	private function action_form( string $action, string $label, string $button_class ): void {
		$in_row = false !== strpos( $button_class, ' r' );
		echo $in_row ? '<form method="post" action="" class="r">' : '<form method="post" action="">';
		wp_nonce_field( $this->nonce_action( $action ), self::NONCE_FIELD );
		echo '<input type="hidden" name="' . esc_attr( self::ACTION_FIELD ) . '" value="' . esc_attr( $action ) . '" />';
		echo '<button type="submit" class="' . esc_attr( $button_class ) . '">' . esc_html( $label ) . '</button>';
		echo '</form>';
	}

	/**
	 * Overview menu URL. Page is registered in M1-08; the slug is stable.
	 *
	 * @since 4.0.0
	 */
	private function overview_url(): string {
		if ( function_exists( 'admin_url' ) ) {
			return admin_url( 'admin.php?page=wpcy' );
		}

		return 'admin.php?page=wpcy';
	}
}
