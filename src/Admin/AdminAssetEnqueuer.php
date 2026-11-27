<?php
/**
 * Admin screen asset enqueuer (kit + modular WPCY admin scripts).
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues wenpai-admin-ui kit and modular progressive-enhancement scripts in dependency order.
 */
final class AdminAssetEnqueuer {

	/**
	 * Core admin script modules loaded in order before the `wpcy-admin` entrypoint.
	 *
	 * @var list<string>
	 */
	private const CORE_MODULES = array(
		'core',
		'overview',
		'settings',
		'diagnose',
		'wizard',
	);

	/**
	 * Feature scripts loaded after `wpcy-admin`.
	 *
	 * @var list<string>
	 */
	private const FEATURE_MODULES = array(
		'probes',
		'apps',
		'providers',
		'manifest-ui',
	);

	/**
	 * Enqueue vendored kit and modular native JS on app screens (or dashboard news widget).
	 *
	 * @param string               $hook_suffix   Current admin screen id.
	 * @param bool                 $is_app_screen Whether current screen is one of AdminModule::APP_PAGES.
	 * @param bool                 $show_widget   Whether dashboard news widget is active on index.php.
	 * @param array<string, mixed> $bootstrap     Bootstrap payload for `window.wpcyAdmin`.
	 */
	public static function enqueue(
		string $hook_suffix,
		bool $is_app_screen,
		bool $show_widget,
		array $bootstrap
	): void {
		if ( 'index.php' === $hook_suffix && $show_widget ) {
			if ( class_exists( '\Wenpai_Admin_Loader', false ) ) {
				\Wenpai_Admin_Loader::enqueue( array( 'full' => false ) );
			}
			return;
		}

		if ( ! $is_app_screen ) {
			return;
		}

		if ( class_exists( '\Wenpai_Admin_Loader', false ) ) {
			\Wenpai_Admin_Loader::enqueue( array( 'pages' => AdminModule::APP_PAGES ) );
		}

		$css_path = CHINA_YES_PLUGIN_PATH . 'assets/css/wpcy-admin.css';
		if ( is_readable( $css_path ) ) {
			wp_enqueue_style(
				AdminModule::HANDLE,
				CHINA_YES_PLUGIN_URL . 'assets/css/wpcy-admin.css',
				array( 'wenpai-admin-ui' ),
				CHINA_YES_VERSION . '-' . (int) filemtime( $css_path )
			);
		}

		$entry_path = CHINA_YES_PLUGIN_PATH . 'assets/js/wpcy-admin.js';
		if ( ! is_readable( $entry_path ) ) {
			return;
		}

		$payload     = wp_json_encode( $bootstrap );
		$entry_deps  = array( 'wenpai-admin-ui' );
		$core_handle = AdminModule::HANDLE . '-core';
		$inline_sent = false;

		foreach ( self::CORE_MODULES as $sub ) {
			$sub_rel  = 'assets/js/wpcy-admin-' . $sub . '.js';
			$sub_path = CHINA_YES_PLUGIN_PATH . $sub_rel;
			if ( ! is_readable( $sub_path ) ) {
				continue;
			}
			$handle = AdminModule::HANDLE . '-' . $sub;
			$deps   = 'core' === $sub ? array( 'wenpai-admin-ui' ) : array( $core_handle );
			wp_enqueue_script(
				$handle,
				CHINA_YES_PLUGIN_URL . $sub_rel,
				$deps,
				CHINA_YES_VERSION . '-' . (int) filemtime( $sub_path ),
				true
			);
			if ( 'core' === $sub && is_string( $payload ) ) {
				wp_add_inline_script(
					$handle,
					'window.wpcyAdmin = ' . $payload . ';',
					'before'
				);
				$inline_sent = true;
			}
			$entry_deps[] = $handle;
		}

		wp_enqueue_script(
			AdminModule::HANDLE,
			CHINA_YES_PLUGIN_URL . 'assets/js/wpcy-admin.js',
			$entry_deps,
			CHINA_YES_VERSION . '-' . (int) filemtime( $entry_path ),
			true
		);

		if ( ! $inline_sent && is_string( $payload ) ) {
			wp_add_inline_script(
				AdminModule::HANDLE,
				'window.wpcyAdmin = ' . $payload . ';',
				'before'
			);
		}

		if ( ! is_string( $payload ) ) {
			return;
		}

		foreach ( self::FEATURE_MODULES as $mod ) {
			$mod_file = 'assets/js/wpcy-' . $mod . '.js';
			if ( is_readable( CHINA_YES_PLUGIN_PATH . $mod_file ) ) {
				wp_enqueue_script(
					AdminModule::HANDLE . '-' . $mod,
					CHINA_YES_PLUGIN_URL . $mod_file,
					array( AdminModule::HANDLE ),
					CHINA_YES_VERSION,
					true
				);
			}
		}
	}
}
