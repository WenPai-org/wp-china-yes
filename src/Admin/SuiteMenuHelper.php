<?php
/**
 * Suite submenu grouping, sidebar pruning, and legacy URL rescue helpers.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin;

use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Diagnostics\ConflictDetector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles companion suite submenu grouping, sidebar collapse, and canonical page redirects.
 */
final class SuiteMenuHelper {

	/**
	 * Legacy companion plugin slugs eligible for opt-in submenu grouping under 文派叶子.
	 *
	 * @var list<string>
	 */
	private const SUITE_SLUGS = array(
		'wpslug',
		'wpsss',
		'wpavatar',
		'wpavatar-settings',
		'wpcomments',
		'wpcomments-settings',
		'wp-chinese-converter',
		'wpicp',
		'wpicp_license_settings',
		'wpstatic',
		'wpfonts',
		'wpphoto',
		'wpmind',
		'wpbridge',
	);

	/**
	 * Return `wpcy` as parent menu slug for WenPai companion plugins when suite menu grouping is enabled.
	 *
	 * @param Repository $repository  Settings access.
	 * @param bool       $in_network  Whether inside network admin.
	 * @param mixed      $parent_slug Default parent slug ('').
	 * @param mixed      $slug        Companion plugin menu slug.
	 */
	public static function filter_suite_parent_slug( Repository $repository, bool $in_network, $parent_slug, $slug = '' ): string {
		if ( $in_network ) {
			return is_string( $parent_slug ) ? $parent_slug : '';
		}
		$clean = is_string( $slug ) ? sanitize_key( $slug ) : '';
		if ( '' === $clean || AdminModule::SLUG === $clean || 0 === strpos( $clean, AdminModule::SLUG . '-' ) ) {
			return is_string( $parent_slug ) ? $parent_slug : '';
		}
		$settings = $repository->all();
		$grouped  = ! empty( $settings['admin']['group_suite_menus'] );
		return $grouped ? AdminModule::SLUG : ( is_string( $parent_slug ) ? $parent_slug : '' );
	}

	/**
	 * Append submenu shortcuts under 文派叶子 for active companion plugins not yet using kit 0.12.2+ menu hook.
	 *
	 * @param Repository $repository Settings access.
	 * @param bool       $in_network Whether inside network admin.
	 */
	public static function append_legacy_suite_submenus( Repository $repository, bool $in_network ): void {
		if ( $in_network ) {
			return;
		}
		$settings = $repository->all();
		if ( empty( $settings['admin']['group_suite_menus'] ) ) {
			return;
		}
		if ( ! isset( $GLOBALS['submenu'][ AdminModule::SLUG ] ) || ! is_array( $GLOBALS['submenu'][ AdminModule::SLUG ] ) ) {
			return;
		}

		$existing = array();
		foreach ( $GLOBALS['submenu'][ AdminModule::SLUG ] as $entry ) {
			if ( is_array( $entry ) && isset( $entry[2] ) ) {
				$existing[ (string) $entry[2] ] = true;
			}
		}

		if ( isset( $GLOBALS['submenu']['options-general.php'] ) && is_array( $GLOBALS['submenu']['options-general.php'] ) ) {
			foreach ( $GLOBALS['submenu']['options-general.php'] as $item ) {
				if ( ! is_array( $item ) || ! isset( $item[0], $item[1], $item[2] ) ) {
					continue;
				}
				$item_slug = (string) $item[2];
				if ( ! in_array( $item_slug, self::SUITE_SLUGS, true ) ) {
					continue;
				}
				$target = 'options-general.php?page=' . $item_slug;
				if ( isset( $existing[ $item_slug ] ) || isset( $existing[ $target ] ) ) {
					continue;
				}
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- appending opt-in suite shortcut to own submenu.
				$GLOBALS['submenu'][ AdminModule::SLUG ][] = array( (string) $item[0], (string) $item[1], $target );
				$existing[ $target ]                       = true;
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
				if ( ! in_array( $top_slug, self::SUITE_SLUGS, true ) ) {
					continue;
				}
				$target = $adm_entry . $top_slug;
				if ( ! isset( $existing[ $top_slug ] ) && ! isset( $existing[ $target ] ) ) {
					// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- appending opt-in suite shortcut to own submenu.
					$GLOBALS['submenu'][ AdminModule::SLUG ][] = array( (string) $top_item[0], (string) $top_item[1], $target );
					$existing[ $target ]                       = true;
				}
			}
		}
	}

	/**
	 * Hide WPCY's own horizontal-tab pages from the WP left sidebar after access checks pass,
	 * and collapse the submenu completely unless companion WenPai plugins are grouped under it.
	 */
	public static function collapse_internal_submenus(): void {
		$internal_tabs = array( 'wpcy-connect', 'wpcy-features', 'wpcy-services', 'wpcy-licenses', 'wpcy-diagnose', 'wpcy-onboarding' );
		if ( function_exists( 'remove_submenu_page' ) ) {
			foreach ( array( 'wpcy-connect', 'wpcy-features', 'wpcy-services', 'wpcy-licenses', 'wpcy-diagnose' ) as $tab_slug ) {
				remove_submenu_page( AdminModule::SLUG, $tab_slug );
			}
		}
		if ( ! isset( $GLOBALS['submenu'][ AdminModule::SLUG ] ) || ! is_array( $GLOBALS['submenu'][ AdminModule::SLUG ] ) ) {
			return;
		}
		foreach ( $GLOBALS['submenu'][ AdminModule::SLUG ] as $idx => $entry ) {
			if ( is_array( $entry ) && isset( $entry[2] ) && in_array( (string) $entry[2], $internal_tabs, true ) ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- pruning own horizontal-tab slugs from sidebar submenu.
				unset( $GLOBALS['submenu'][ AdminModule::SLUG ][ $idx ] );
			}
		}
		if ( 1 === count( $GLOBALS['submenu'][ AdminModule::SLUG ] ) ) {
			$only = reset( $GLOBALS['submenu'][ AdminModule::SLUG ] );
			if ( is_array( $only ) && isset( $only[2] ) && AdminModule::SLUG === (string) $only[2] ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- mirrors wp-admin/includes/menu.php single-item submenu collapse so no empty dropdown renders.
				unset( $GLOBALS['submenu'][ AdminModule::SLUG ] );
			}
		}
	}

	/**
	 * Redirect top-level pages opened under options-general.php or companion alias slugs.
	 *
	 * @param string $slug          Current ?page= slug.
	 * @param bool   $is_app_screen Whether current page is one of AdminModule::APP_PAGES.
	 * @param bool   $in_network    Whether inside network admin.
	 */
	public static function maybe_redirect_wrong_parent( string $slug, bool $is_app_screen, bool $in_network ): void {
		$script = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
		if ( '' === $script && isset( $_SERVER['SCRIPT_NAME'] ) ) {
			$script = basename( sanitize_text_field( wp_unslash( (string) $_SERVER['SCRIPT_NAME'] ) ) );
		}
		if ( 'options-general.php' === $script && ( 'wp-china-yes' === $slug || $is_app_screen ) ) {
			self::redirect_legacy_options_url( $slug, $is_app_screen, $in_network );
			return;
		}
		self::rescue_companion_page_url( $slug, $is_app_screen, $in_network, $script );
	}

	/**
	 * Redirect legacy 3.x ?page=wp-china-yes, options-general.php?page=wpcy* links, or companion alias URLs.
	 *
	 * @param string $slug          Current ?page= slug.
	 * @param bool   $is_app_screen Whether current page is one of AdminModule::APP_PAGES.
	 * @param bool   $in_network    Whether inside network admin.
	 */
	public static function redirect_legacy_options_url( string $slug, bool $is_app_screen, bool $in_network ): void {
		if ( $in_network || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( 'wp-china-yes' === $slug ) {
			$slug = AdminModule::SLUG;
		} elseif ( ! $is_app_screen || '' === $slug ) {
			self::rescue_companion_page_url( $slug, $is_app_screen, $in_network, '' );
			return;
		}
		$extra = self::preserved_query_args();
		wp_safe_redirect( AdminModule::app_url( $slug, $extra ) );
		exit;
	}

	/**
	 * Redirect a companion plugin settings request when opened with an alias slug or wrong parent script.
	 *
	 * @param string $slug           Current ?page= slug.
	 * @param bool   $is_app_screen  Whether current page is one of AdminModule::APP_PAGES.
	 * @param bool   $in_network     Whether inside network admin.
	 * @param string $current_script Current `pagenow` basename, or `''` on access denied.
	 */
	private static function rescue_companion_page_url( string $slug, bool $is_app_screen, bool $in_network, string $current_script ): void {
		if ( $in_network || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( '' === $slug || $is_app_screen ) {
			return;
		}
		$live_url = ConflictDetector::resolve_live_url_for_page_slug( $slug );
		if ( '' === $live_url ) {
			return;
		}
		if ( '' !== $current_script ) {
			$parts       = function_exists( 'wp_parse_url' ) ? wp_parse_url( $live_url ) : parse_url( $live_url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- fallback for standalone unit tests.
			$target_file = is_array( $parts ) && isset( $parts['path'] ) ? basename( (string) $parts['path'] ) : '';
			$query_vars  = array();
			if ( is_array( $parts ) && isset( $parts['query'] ) ) {
				parse_str( (string) $parts['query'], $query_vars );
			}
			$target_page = isset( $query_vars['page'] ) && is_string( $query_vars['page'] ) ? $query_vars['page'] : '';
			if ( $target_file === $current_script && $target_page === $slug ) {
				return;
			}
		}
		$extra = self::preserved_query_args();
		if ( array() !== $extra && function_exists( 'add_query_arg' ) ) {
			$live_url = (string) add_query_arg( $extra, $live_url );
		}
		wp_safe_redirect( $live_url );
		exit;
	}

	/**
	 * Collect safe scalar query parameters (excluding `page`) to preserve across canonical 302 redirects.
	 *
	 * @return array<string, string>
	 */
	private static function preserved_query_args(): array {
		$extra = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- preserving safe read-only navigation query args across 302.
		foreach ( $_GET as $key => $val ) {
			$clean_key = sanitize_key( (string) $key );
			if ( '' === $clean_key || 'page' === $clean_key || ! is_scalar( $val ) ) {
				continue;
			}
			$extra[ $clean_key ] = sanitize_text_field( wp_unslash( (string) $val ) );
		}
		return $extra;
	}
}
