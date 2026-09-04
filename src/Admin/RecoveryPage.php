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
	}

	/**
	 * Hidden submenu: parent null so the item is not listed.
	 *
	 * Direct URL ?page=wpcy-recovery still works.
	 *
	 * @since 4.0.0
	 */
	public function add_page(): void {
		$title = __( '文派叶子 · 恢复模式', 'wp-china-yes' );

		$parent = null; // Hidden parent (ADR-002 / M1-11). WP stubs type this as string.

		add_submenu_page(
			$parent, // @phpstan-ignore argument.type
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
			wp_die( esc_html__( 'Forbidden.', 'wp-china-yes' ), 403 );
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
	 * Native wrap markup. No scripts enqueued.
	 *
	 * @since 4.0.0
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'wp-china-yes' ), 403 );
		}

		$in_recovery = (bool) $this->actions->settings()['recovery_mode'];
		$overview    = $this->overview_url();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( '文派叶子 · 恢复模式', 'wp-china-yes' ) . '</h1>';

		if ( $in_recovery ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( '恢复模式已开启', 'wp-china-yes' ) . '</p></div>';
			$this->action_form( RecoveryActions::EXIT, __( '退出恢复模式', 'wp-china-yes' ), 'button-secondary' );
		}

		echo '<p>' . esc_html__( '如果后台样式错乱或站点无法访问，可在此一键停用所有 URL 改写与模块。此页不依赖 JavaScript。', 'wp-china-yes' ) . '</p>';

		$this->action_form( RecoveryActions::DISABLE_REWRITES, __( '关闭全部 URL 改写', 'wp-china-yes' ), 'button-primary' );
		$this->action_form( RecoveryActions::DISABLE_MODULES, __( '停用全部模块', 'wp-china-yes' ), 'button-secondary' );

		echo '<p><a href="' . esc_url( $overview ) . '">' . esc_html__( '返回概览', 'wp-china-yes' ) . '</a></p>';
		echo '</div>';
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
	 * @param string $button_class button-primary or button-secondary.
	 */
	private function action_form( string $action, string $label, string $button_class ): void {
		echo '<form method="post" action="">';
		wp_nonce_field( $this->nonce_action( $action ), self::NONCE_FIELD );
		echo '<input type="hidden" name="' . esc_attr( self::ACTION_FIELD ) . '" value="' . esc_attr( $action ) . '" />';
		echo '<p><button type="submit" class="button ' . esc_attr( $button_class ) . '">' . esc_html( $label ) . '</button></p>';
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
