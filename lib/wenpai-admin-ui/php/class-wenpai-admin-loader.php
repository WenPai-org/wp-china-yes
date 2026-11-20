<?php
/**
 * Enqueue the winning kit CSS/JS. Handle is always wenpai-admin-ui.
 *
 * @package Wenpai_Admin_UI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wenpai_Admin_Loader', false ) ) {
	/**
	 * Asset loader. Call after wenpai_admin_ui_boot().
	 */
	class Wenpai_Admin_Loader {

		const STYLE_HANDLE  = 'wenpai-admin-ui';
		const SCRIPT_HANDLE = 'wenpai-admin-ui';
		const TOKENS_HANDLE = 'wenpai-admin-ui-tokens';

		/**
		 * Extra admin body classes requested through enqueue( array( 'body' => ... ) ).
		 *
		 * @var string[]
		 */
		private static $body_classes = array();

		/**
		 * Register and enqueue the kit stylesheet and script.
		 *
		 * @param array $args {
		 *     Optional. Enqueue arguments.
		 *
		 *     @type string $body Optional body class for the product's own CSS, e.g.
		 *                        wpsss-admin. Added to the admin body (0.3.0+); a
		 *                        class the product already adds is not duplicated.
		 *     @type bool   $full Whether to load the full-page shell CSS (default true).
		 *                        Adds body.wenpai-ui-page.
		 * }
		 */
		public static function enqueue( $args = array() ) {
			if ( ! function_exists( 'wp_register_style' ) ) {
				return;
			}

			$dir = function_exists( 'wenpai_admin_ui_winner_dir' ) ? wenpai_admin_ui_winner_dir() : '';
			if ( '' === $dir ) {
				return;
			}

			$ver = isset( $GLOBALS['wenpai_admin_ui_winner']['version'] )
				? (string) $GLOBALS['wenpai_admin_ui_winner']['version']
				: '0.0.1';

			$tok_file = $dir . '/css/wenpai-tokens.css';
			$css_file = $dir . '/css/wenpai-admin.css';
			$wp_file  = $dir . '/css/wenpai-admin-wp.css';
			$js_file  = $dir . '/js/wenpai-admin.js';
			$ref      = $dir . '/VERSION';

			// Tokens first: wenpai-admin.css reads every color and size from them.
			$deps = array();
			if ( is_readable( $tok_file ) ) {
				wp_register_style(
					self::TOKENS_HANDLE,
					plugins_url( 'css/wenpai-tokens.css', $ref ),
					array(),
					$ver
				);
				$deps[] = self::TOKENS_HANDLE;
			}
			wp_register_style(
				self::STYLE_HANDLE,
				plugins_url( 'css/wenpai-admin.css', $ref ),
				$deps,
				$ver
			);
			wp_register_style(
				self::STYLE_HANDLE . '-wp',
				plugins_url( 'css/wenpai-admin-wp.css', $ref ),
				array( self::STYLE_HANDLE ),
				$ver
			);

			if ( is_readable( $css_file ) ) {
				wp_enqueue_style( self::STYLE_HANDLE );
			}

			$full = ! isset( $args['full'] ) || $args['full'];
			if ( $full && is_readable( $wp_file ) ) {
				wp_enqueue_style( self::STYLE_HANDLE . '-wp' );
				add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
			}
			if ( ! empty( $args['body'] ) && is_string( $args['body'] ) ) {
				$extra = sanitize_html_class( $args['body'] );
				if ( '' !== $extra && ! in_array( $extra, self::$body_classes, true ) ) {
					self::$body_classes[] = $extra;
				}
				if ( false === has_filter( 'admin_body_class', array( __CLASS__, 'extra_body_class' ) ) ) {
					add_filter( 'admin_body_class', array( __CLASS__, 'extra_body_class' ) );
				}
			}

			if ( is_readable( $js_file ) && function_exists( 'wp_register_script' ) ) {
				wp_register_script(
					self::SCRIPT_HANDLE,
					plugins_url( 'js/wenpai-admin.js', $ref ),
					array(),
					$ver,
					true
				);
				wp_enqueue_script( self::SCRIPT_HANDLE );
			}
		}

		/**
		 * Full-page shell chrome. Product still adds body.{slug}-admin for its own CSS.
		 *
		 * @param string $classes Space-separated body classes.
		 * @return string
		 */
		public static function body_class( $classes ) {
			if ( false === strpos( ' ' . $classes . ' ', ' wenpai-ui-page ' ) ) {
				$classes .= ' wenpai-ui-page';
			}
			return $classes;
		}

		/**
		 * Body classes passed as enqueue( array( 'body' => ... ) ).
		 *
		 * @param string $classes Space-separated body classes.
		 * @return string
		 */
		public static function extra_body_class( $classes ) {
			foreach ( self::$body_classes as $c ) {
				if ( false === strpos( ' ' . $classes . ' ', ' ' . $c . ' ' ) ) {
					$classes .= ' ' . $c;
				}
			}
			return $classes;
		}
	}
}
