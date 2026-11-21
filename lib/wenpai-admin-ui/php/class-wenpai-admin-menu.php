<?php
/**
 * Menu registration and canonical page URLs.
 *
 * Lightweight utility plugins register under Settings (`add_options_page`);
 * multi-module hub plugins pass `'top_level' => true` (`add_menu_page` +
 * optional `submenus`), and `url()` / `maybe_redirect_canonical()` keep
 * `admin.php` vs `options-general.php` URLs consistent.
 *
 * @package Wenpai_Admin_UI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wenpai_Admin_Menu', false ) ) {
	/**
	 * Thin wrapper over add_options_page / add_menu_page so plugins cannot drift.
	 */
	class Wenpai_Admin_Menu {

		/**
		 * Page slugs registered through register(), for asset scoping (kit 0.9.2).
		 *
		 * @var array<string, true>
		 */
		private static $pages = array();

		/**
		 * Top-level hub slugs and their submenu slugs mapped to required capability (kit 0.12.0).
		 *
		 * @var array<string, string>
		 */
		private static $top_pages = array();

		/**
		 * Legacy 3.x page slugs mapped to canonical 4.0 slug (kit 0.12.0).
		 *
		 * @var array<string, string>
		 */
		private static $legacy_aliases = array();

		/**
		 * Registered WordPress hook suffixes per slug (kit 0.12.2).
		 *
		 * @var array<string, string>
		 */
		private static $hooks = array();

		/**
		 * Whether canonical redirect hooks have been attached.
		 *
		 * @var bool
		 */
		private static $redirect_hooked = false;

		/**
		 * Register a plugin admin menu (Settings submenu by default, or top-level hub when top_level is true).
		 *
		 * @param array $args {
		 *     Page arguments.
		 *
		 *     @type string     $slug         Page slug (wpslug / wpsss / wpcy).
		 *     @type string     $label        Menu label, already translated.
		 *     @type string     $title        Optional browser title. Defaults to label.
		 *     @type string     $capability   Defaults to manage_options.
		 *     @type callable   $render       Page callback.
		 *     @type bool       $top_level    True for multi-module hub top-level menu (0.12.0+).
		 *     @type bool       $network      True when registering in network_admin_menu (0.12.0+).
		 *     @type string     $icon         Data-URI SVG or dashicon for top-level menu.
		 *     @type int|float  $position     Optional menu position for top-level menu.
		 *     @type array      $submenus     Optional submenu items for top-level hub:
		 *                                    array of array{ slug, label, title?, capability?, render?, parent? }.
		 *     @type string[]   $legacy_slugs Optional legacy slugs redirected to $slug (0.12.0+).
		 * }
		 * @return string|false Hook suffix.
		 */
		public static function register( $args ) {
			$slug  = isset( $args['slug'] ) ? sanitize_key( $args['slug'] ) : '';
			$label = isset( $args['label'] ) ? $args['label'] : $slug;
			$title = isset( $args['title'] ) ? $args['title'] : $label;
			$cap   = isset( $args['capability'] ) ? $args['capability'] : 'manage_options';
			$cb    = isset( $args['render'] ) ? $args['render'] : '__return_null';
			if ( '' === $slug ) {
				return false;
			}
			self::$pages[ $slug ] = true;

			if ( ! empty( $args['legacy_slugs'] ) && is_array( $args['legacy_slugs'] ) ) {
				foreach ( $args['legacy_slugs'] as $old ) {
					$old_key = sanitize_key( (string) $old );
					if ( '' !== $old_key && $old_key !== $slug ) {
						self::$legacy_aliases[ $old_key ] = $slug;
					}
				}
				self::ensure_redirect_hooks();
			}

			if ( ! empty( $args['network'] ) ) {
				if ( ! empty( $args['top_level'] ) && function_exists( 'add_menu_page' ) ) {
					$icon = isset( $args['icon'] ) ? (string) $args['icon'] : '';
					$pos  = isset( $args['position'] ) ? $args['position'] : null;
					return add_menu_page( $title, $label, $cap, $slug, $cb, $icon, $pos );
				}
				if ( function_exists( 'add_submenu_page' ) ) {
					return add_submenu_page( 'settings.php', $title, $label, $cap, $slug, $cb );
				}
				return false;
			}

			if ( ! empty( $args['top_level'] ) ) {
				self::$top_pages[ $slug ] = $cap;
				self::ensure_redirect_hooks();
				$icon = isset( $args['icon'] ) ? (string) $args['icon'] : '';
				$pos  = isset( $args['position'] ) ? $args['position'] : null;
				$hook = function_exists( 'add_menu_page' ) ? add_menu_page( $title, $label, $cap, $slug, $cb, $icon, $pos ) : false;
				if ( is_string( $hook ) && '' !== $hook ) {
					self::$hooks[ $slug ] = $hook;
				}
				if ( ! empty( $args['submenus'] ) && is_array( $args['submenus'] ) && function_exists( 'add_submenu_page' ) ) {
					foreach ( $args['submenus'] as $sub ) {
						if ( ! is_array( $sub ) || empty( $sub['slug'] ) ) {
							continue;
						}
						$sub_slug  = sanitize_key( (string) $sub['slug'] );
						$sub_label = isset( $sub['label'] ) ? (string) $sub['label'] : $sub_slug;
						$sub_title = isset( $sub['title'] ) ? (string) $sub['title'] : $sub_label;
						$sub_cap   = isset( $sub['capability'] ) ? (string) $sub['capability'] : $cap;
						$sub_cb    = isset( $sub['render'] ) ? $sub['render'] : $cb;
						$parent    = array_key_exists( 'parent', $sub ) ? $sub['parent'] : $slug;
						if ( '' === $sub_slug ) {
							continue;
						}
						self::$pages[ $sub_slug ]     = true;
						self::$top_pages[ $sub_slug ] = $sub_cap;
						$sub_hook                     = add_submenu_page( $parent, $sub_title, $sub_label, $sub_cap, $sub_slug, $sub_cb );
						if ( is_string( $sub_hook ) && '' !== $sub_hook ) {
							self::$hooks[ $sub_slug ] = $sub_hook;
						}
					}
				}
				return $hook;
			}

			$suite_parent = '';
			if ( function_exists( 'apply_filters' ) ) {
				$suite_parent = sanitize_key( (string) apply_filters( 'wenpai_admin_ui_suite_parent_slug', '', $slug, $args ) );
			}
			if ( '' !== $suite_parent && $suite_parent !== $slug && function_exists( 'add_submenu_page' ) ) {
				self::$top_pages[ $slug ] = $cap;
				self::ensure_redirect_hooks();
				$hook = add_submenu_page( $suite_parent, $title, $label, $cap, $slug, $cb );
				if ( is_string( $hook ) && '' !== $hook ) {
					self::$hooks[ $slug ] = $hook;
				}
				return $hook;
			}

			$hook = add_options_page( $title, $label, $cap, $slug, $cb );
			if ( is_string( $hook ) && '' !== $hook ) {
				self::$hooks[ $slug ] = $hook;
			}
			return $hook;
		}

		/**
		 * Whether a `page` query arg belongs to a kit-registered plugin page.
		 * Submenu pages share the `{slug}-` / `{slug}_` prefix convention.
		 *
		 * @param string $page `$_GET['page']` value.
		 * @return bool
		 */
		public static function owns_page( $page ) {
			$page = sanitize_key( (string) $page );
			if ( '' === $page ) {
				return false;
			}
			foreach ( array_keys( self::$pages ) as $slug ) {
				if ( $page === $slug || 0 === strpos( $page, $slug . '-' ) || 0 === strpos( $page, $slug . '_' ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Whether a page slug belongs to a top-level hub menu (`admin.php`) rather than Settings (`options-general.php`).
		 *
		 * @param string $slug Page slug.
		 * @return bool
		 */
		public static function is_top_level( $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' === $slug ) {
				return false;
			}
			if ( isset( self::$top_pages[ $slug ] ) ) {
				return true;
			}
			foreach ( array_keys( self::$top_pages ) as $top ) {
				if ( 0 === strpos( $slug, $top . '-' ) || 0 === strpos( $slug, $top . '_' ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Canonical admin URL for a registered plugin page.
		 *
		 * Uses `admin.php` for top-level hub pages and `options-general.php` for Settings submenus.
		 *
		 * @param string $slug Page slug.
		 * @param array  $args Extra query args (module, service, view, section, etc.).
		 * @return string
		 */
		public static function url( $slug, $args = array() ) {
			$clean = sanitize_key( (string) $slug );
			$base  = self::is_top_level( $clean ) ? 'admin.php' : 'options-general.php';
			$query = array_merge( array( 'page' => $clean ), (array) $args );
			return add_query_arg( $query, admin_url( $base ) );
		}

		/**
		 * Hook suffix WordPress assigns to the page.
		 *
		 * @param string $slug Page slug.
		 * @return string
		 */
		public static function hook( $slug ) {
			$clean = sanitize_key( (string) $slug );
			if ( isset( self::$hooks[ $clean ] ) && '' !== self::$hooks[ $clean ] ) {
				return self::$hooks[ $clean ];
			}
			$pref = self::is_top_level( $clean ) ? 'toplevel_page_' : 'settings_page_';
			return $pref . $clean;
		}

		/**
		 * Redirect top-level hub pages opened via `options-general.php?page=...` or legacy 3.x slugs to `admin.php?page=...`.
		 */
		public static function maybe_redirect_canonical() {
			if ( function_exists( 'is_network_admin' ) && is_network_admin() ) {
				return;
			}
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing check.
			if ( '' === $page ) {
				return;
			}

			$target = '';
			if ( isset( self::$legacy_aliases[ $page ] ) ) {
				$target = self::$legacy_aliases[ $page ];
			} else {
				$script = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
				if ( '' === $script && isset( $_SERVER['SCRIPT_NAME'] ) ) {
					$script = basename( sanitize_text_field( wp_unslash( (string) $_SERVER['SCRIPT_NAME'] ) ) );
				}
				if ( 'options-general.php' === $script && self::is_top_level( $page ) ) {
					$target = $page;
				}
			}

			if ( '' === $target ) {
				return;
			}

			$cap = isset( self::$top_pages[ $target ] ) ? self::$top_pages[ $target ] : 'manage_options';
			if ( function_exists( 'current_user_can' ) && ! current_user_can( $cap ) ) {
				return;
			}

			$extra = array();
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- preserving safe read-only navigation query args across canonical 302.
			foreach ( $_GET as $key => $val ) {
				$clean_key = sanitize_key( (string) $key );
				if ( '' === $clean_key || 'page' === $clean_key || ! is_scalar( $val ) ) {
					continue;
				}
				$extra[ $clean_key ] = sanitize_text_field( wp_unslash( (string) $val ) );
			}

			if ( function_exists( 'wp_safe_redirect' ) ) {
				wp_safe_redirect( self::url( $target, $extra ) );
				exit;
			}
		}

		/**
		 * Reset static state between unit tests.
		 *
		 * @internal
		 */
		public static function reset_for_tests() {
			self::$pages           = array();
			self::$top_pages       = array();
			self::$legacy_aliases  = array();
			self::$hooks           = array();
			self::$redirect_hooked = false;
		}

		/**
		 * Attach canonical redirect listeners once.
		 */
		private static function ensure_redirect_hooks() {
			if ( self::$redirect_hooked || ! function_exists( 'add_action' ) ) {
				return;
			}
			self::$redirect_hooked = true;
			add_action( 'admin_init', array( __CLASS__, 'maybe_redirect_canonical' ), 1 );
			add_action( 'admin_page_access_denied', array( __CLASS__, 'maybe_redirect_canonical' ), 1 );
		}
	}
}
