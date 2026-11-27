<?php
/**
 * Server-rendered admin screens facade using wenpai-admin-ui kit (Route A).
 *
 * Delegates rendering to cohesive domain view classes under `WenPai\ChinaYes\Admin\Views`.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin;

use WenPai\ChinaYes\Admin\Views\DiagnoseView;
use WenPai\ChinaYes\Admin\Views\FeaturesView;
use WenPai\ChinaYes\Admin\Views\KitUi;
use WenPai\ChinaYes\Admin\Views\LicensesView;
use WenPai\ChinaYes\Admin\Views\OverviewSuggestions;
use WenPai\ChinaYes\Admin\Views\OverviewView;
use WenPai\ChinaYes\Admin\Views\ServicesView;
use WenPai\ChinaYes\Admin\Views\SettingsView;
use WenPai\ChinaYes\Admin\Views\WizardView;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Facade for rendering the WPCY 4.0 admin screens, dashboard widget, and onboarding wizard.
 */
final class KitRenderer {

	/**
	 * Render one admin screen.
	 *
	 * @param string               $page    Current `?page=` slug.
	 * @param array<string, mixed> $payload Bootstrap payload from `AdminModule::bootstrap_payload()`.
	 * @return void
	 */
	public static function render( string $page, array $payload ): void {
		if ( ! class_exists( '\Wenpai_Admin_Shell', false ) || ! class_exists( '\Wenpai_Admin_UI', false ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( '文派后台组件库尚未加载，请检查 lib/wenpai-admin-ui 目录是否完整。', 'wp-china-yes' ) . '</p></div>';
			return;
		}

		$settings = isset( $payload['settings'] ) && is_array( $payload['settings'] ) ? $payload['settings'] : array();
		$caps     = isset( $payload['capabilities'] ) && is_array( $payload['capabilities'] ) ? $payload['capabilities'] : array();
		$links    = isset( $payload['links'] ) && is_array( $payload['links'] ) ? $payload['links'] : array();
		$version  = isset( $payload['pluginVersion'] ) ? (string) $payload['pluginVersion'] : '4.0.0';
		$locked   = ! empty( $caps['network_locked'] );
		$network  = ! empty( $caps['network_admin'] );

		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( (string) $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view switch.
		if ( ( 'wpcy-onboarding' === $page || 'wizard' === $view ) && ! $locked && ! $network ) {
			WizardView::render( $settings, $links, $version );
			return;
		}

		$tab_map = array(
			'wpcy'          => 'overview',
			'wpcy-connect'  => 'settings',
			'wpcy-features' => 'features',
			'wpcy-services' => 'services',
			'wpcy-licenses' => 'licenses',
			'wpcy-diagnose' => 'diagnose',
		);
		$current = $network ? 'settings' : ( 'help' === $view ? 'help' : ( $tab_map[ $page ] ?? 'overview' ) );
		$shell   = KitUi::shell_args( $current, $version, $links, $network, $settings );

		\Wenpai_Admin_Shell::open( $shell );

		if ( 'help' === $current ) {
			DiagnoseView::render_help( $links );
		} elseif ( 'settings' === $current ) {
			SettingsView::render( $settings, $locked, $network );
		} elseif ( 'features' === $current ) {
			FeaturesView::render( $settings, $locked );
		} elseif ( 'services' === $current ) {
			ServicesView::render( $settings, $locked );
		} elseif ( 'licenses' === $current ) {
			LicensesView::render( $settings, $locked );
		} elseif ( 'diagnose' === $current ) {
			DiagnoseView::render( $settings, $locked );
		} else {
			OverviewView::render( $settings );
		}

		\Wenpai_Admin_Shell::close( $shell );
	}

	/**
	 * Compute active, un-dismissed actionable site suggestions merged into `近期运行记录`.
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 * @return list<array<string, string>>
	 */
	public static function active_suggestions( array $settings ): array {
		return OverviewSuggestions::active_suggestions( $settings );
	}

	/**
	 * Render the replacement fast domestic / agency news widget inside native WP Dashboard (`wp-admin/index.php`).
	 *
	 * @param array<string, mixed>              $settings Settings snapshot.
	 * @param list<array<string, string|mixed>> $items    Cached undismissed items from `AnnouncementsModule`.
	 */
	public static function render_dashboard_news_widget( array $settings, array $items ): void {
		OverviewSuggestions::render_dashboard_news_widget( $settings, $items );
	}
}
