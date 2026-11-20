<?php
/**
 * Admin-post actions: capability, nonce, confirm, redirect, one-shot notice.
 *
 * @package Wenpai_Admin_UI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wenpai_Admin_Action', false ) ) {

	/**
	 * Register and handle admin-post_{action} endpoints.
	 */
	class Wenpai_Admin_Action {

		const TOKEN_LEN     = 12;
		const TRANSIENT_TTL = 60;

		/**
		 * Registered actions: action => args.
		 *
		 * @var array<string, array>
		 */
		private static $actions = array();

		/**
		 * Default user-facing strings (overridable per register() via 'strings').
		 *
		 * @return array<string, string>
		 */
		public static function default_strings() {
			return array(
				'action_denied'  => '暂时无法完成操作，请刷新页面后再试。',
				'action_failed'  => '暂时无法完成操作，请稍后再试。',
				'confirm_needed' => '暂时无法完成操作，请先勾选确认。',
			);
		}

		/**
		 * Register an admin-post action.
		 *
		 * Call before admin_init (plugins_loaded ≥3 or init). Kit hooks
		 * admin_post_{action} (and admin_post_nopriv is never used).
		 *
		 * @param array $args {
		 *     Action registration arguments.
		 *     @type string   $action     Required. admin_post_{action} and nonce action.
		 *     @type callable $callback   Required. Receives array( 'item' => int|null ).
		 *     @type string   $capability Default manage_options.
		 *     @type string   $confirm    Optional POST key that must be '1'.
		 *     @type string   $method     'post' (default) or 'get'.
		 *     @type bool     $item       True: require positive int item; fold into nonce.
		 *     @type array    $strings    Override default_strings keys.
		 * }
		 */
		public static function register( array $args ) {
			$args   = wp_parse_args(
				$args,
				array(
					'action'     => '',
					'callback'   => null,
					'capability' => 'manage_options',
					'confirm'    => '',
					'method'     => 'post',
					'item'       => false,
					'strings'    => array(),
				)
			);
			$action = sanitize_key( (string) $args['action'] );
			if ( '' === $action || ! is_callable( $args['callback'] ) ) {
				if ( function_exists( '_doing_it_wrong' ) ) {
					_doing_it_wrong( __METHOD__, 'action and callable callback are required.', '0.3.5' );
				}
				return;
			}
			$method = strtolower( (string) $args['method'] );
			if ( ! in_array( $method, array( 'post', 'get' ), true ) ) {
				$method = 'post';
			}
			self::$actions[ $action ] = array(
				'callback'   => $args['callback'],
				'capability' => (string) $args['capability'],
				'confirm'    => (string) $args['confirm'],
				'method'     => $method,
				'item'       => ! empty( $args['item'] ),
				'strings'    => is_array( $args['strings'] ) ? $args['strings'] : array(),
			);
			$hook                     = 'admin_post_' . $action;
			if ( function_exists( 'add_action' ) ) {
				add_action( $hook, array( __CLASS__, 'handle' ) );
			}
		}

		/**
		 * Registered action args, or null.
		 *
		 * @param string $action Action name.
		 * @return array|null
		 */
		public static function get( $action ) {
			$action = sanitize_key( (string) $action );
			return isset( self::$actions[ $action ] ) ? self::$actions[ $action ] : null;
		}

		/**
		 * Echo hidden fields for a POST form (action, _wpnonce, _wp_http_referer).
		 *
		 * @param string $action Registered action name.
		 */
		public static function fields( $action ) {
			echo self::fields_html( $action ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fields_html escapes.
		}

		/**
		 * Return hidden fields HTML for a POST form.
		 *
		 * @param string $action Registered action name.
		 * @return string
		 */
		public static function fields_html( $action ) {
			$action = sanitize_key( (string) $action );
			$html   = '<input type="hidden" name="action" value="' . esc_attr( $action ) . '" />';
			if ( function_exists( 'wp_nonce_field' ) ) {
				$html .= wp_nonce_field( $action, '_wpnonce', true, false );
			} elseif ( function_exists( 'wp_create_nonce' ) ) {
				$html .= '<input type="hidden" id="_wpnonce" name="_wpnonce" value="' . esc_attr( wp_create_nonce( $action ) ) . '" />';
				$html .= '<input type="hidden" name="_wp_http_referer" value="" />';
			}
			return $html;
		}

		/**
		 * Build a GET admin-post URL for an item action. Only for method=get.
		 *
		 * @param string $action Action name.
		 * @param array  $args {
		 *     URL arguments.
		 *     @type int   $item Positive item id when registered with item=>true.
		 *     @type array $args Extra query args.
		 * }
		 * @return string Escaped with esc_url_raw, or '' if not allowed.
		 */
		public static function url( $action, array $args = array() ) {
			$action = sanitize_key( (string) $action );
			$reg    = self::get( $action );
			if ( null === $reg || 'get' !== $reg['method'] ) {
				if ( function_exists( '_doing_it_wrong' ) ) {
					_doing_it_wrong( __METHOD__, 'url() is only for method=get actions.', '0.3.5' );
				}
				return '';
			}
			$item = isset( $args['item'] ) ? absint( $args['item'] ) : 0;
			if ( $reg['item'] && $item < 1 ) {
				return '';
			}
			$nonce_action = $reg['item'] ? $action . '_' . $item : $action;
			$query        = array(
				'action'   => $action,
				'_wpnonce' => function_exists( 'wp_create_nonce' ) ? wp_create_nonce( $nonce_action ) : '',
			);
			if ( $reg['item'] ) {
				$query['item'] = $item;
			}
			if ( ! empty( $args['args'] ) && is_array( $args['args'] ) ) {
				foreach ( $args['args'] as $k => $v ) {
					$query[ sanitize_key( (string) $k ) ] = $v;
				}
			}
			$base = function_exists( 'admin_url' ) ? admin_url( 'admin-post.php' ) : 'admin-post.php';
			$url  = add_query_arg( $query, $base );
			return function_exists( 'esc_url_raw' ) ? esc_url_raw( $url ) : $url;
		}

		/**
		 * Handle an admin_post_{action} entry using the request action name.
		 */
		public static function handle() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- action name only; nonce checked below.
			$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) ) : '';
			$reg    = self::get( $action );
			if ( null === $reg ) {
				return;
			}
			$strings = array_merge( self::default_strings(), $reg['strings'] );

			$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
			$want   = 'get' === $reg['method'] ? 'GET' : 'POST';
			if ( $method !== $want ) {
				self::deny( $strings['action_denied'] );
			}

			if ( ! current_user_can( $reg['capability'] ) ) {
				self::deny( $strings['action_denied'] );
			}

			$item = null;
			if ( $reg['item'] ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked next.
				$item = isset( $_REQUEST['item'] ) ? absint( wp_unslash( $_REQUEST['item'] ) ) : 0;
				if ( $item < 1 ) {
					self::deny( $strings['action_denied'] );
				}
			}

			$nonce_action = $reg['item'] ? $action . '_' . $item : $action;
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_verify_nonce.
			$nonce = isset( $_REQUEST['_wpnonce'] ) ? wp_unslash( $_REQUEST['_wpnonce'] ) : '';
			if ( ! $nonce || ! wp_verify_nonce( $nonce, $nonce_action ) ) {
				self::deny( $strings['action_denied'] );
			}

			if ( '' !== $reg['confirm'] ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified.
				$confirm_value = isset( $_POST[ $reg['confirm'] ] ) && is_scalar( $_POST[ $reg['confirm'] ] )
					? sanitize_text_field( wp_unslash( $_POST[ $reg['confirm'] ] ) )
					: '';
				$ok            = '1' === $confirm_value;
				if ( ! $ok ) {
					self::finish(
						array(
							'level'   => 'bad',
							'message' => $strings['confirm_needed'],
						),
						null
					);
					return;
				}
			}

			$request = array( 'item' => $item );
			try {
				$result = call_user_func( $reg['callback'], $request );
			} catch ( Exception $e ) {
				$result = array(
					'level'   => 'bad',
					'message' => $strings['action_failed'],
				);
			} catch ( Throwable $e ) { // phpcs:ignore PHPCompatibility.Exception.NonThrowableCatch -- PHP 7+.
				$result = array(
					'level'   => 'bad',
					'message' => $strings['action_failed'],
				);
			}

			if ( null === $result ) {
				// Callback already output and exit (download).
				return;
			}

			self::finish( $result, $strings );
		}

		/**
		 * Normalize callback result, store transient, redirect.
		 *
		 * @param mixed      $result  Callback return.
		 * @param array|null $strings Fallback strings (action_failed).
		 */
		private static function finish( $result, $strings ) {
			if ( null === $strings ) {
				$strings = self::default_strings();
			}
			$payload  = self::normalize_result( $result, $strings );
			$redirect = isset( $payload['redirect'] ) ? $payload['redirect'] : null;
			unset( $payload['redirect'] );

			$token = self::store_result( $payload );
			$to    = self::resolve_redirect( $redirect );
			if ( '' !== $token ) {
				$to = add_query_arg( array( 'wenpai_action' => $token ), $to );
			}
			self::redirect_to( $to );
		}

		/**
		 * Normalize a callback result into a level, message, and optional redirect.
		 *
		 * @param mixed $result  Callback return.
		 * @param array $strings Strings with action_failed.
		 * @return array{level:string,message:string,redirect?:string}
		 */
		private static function normalize_result( $result, array $strings ) {
			if ( ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) || $result instanceof WP_Error ) {
				$msg = $result->get_error_message();
				return array(
					'level'   => 'bad',
					'message' => '' !== (string) $msg ? (string) $msg : $strings['action_failed'],
				);
			}
			if ( is_array( $result ) ) {
				$level = isset( $result['level'] ) ? (string) $result['level'] : '';
				if ( ! in_array( $level, array( 'ok', 'warn', 'bad', 'info' ), true ) ) {
					if ( function_exists( '_doing_it_wrong' ) ) {
						_doing_it_wrong( 'Wenpai_Admin_Action::callback', 'Invalid level; expected ok|warn|bad|info.', '0.3.5' );
					}
					return array(
						'level'   => 'bad',
						'message' => $strings['action_failed'],
					);
				}
				$msg = isset( $result['message'] ) ? (string) $result['message'] : '';
				$out = array(
					'level'   => $level,
					'message' => $msg,
				);
				if ( ! empty( $result['redirect'] ) && is_string( $result['redirect'] ) ) {
					$out['redirect'] = $result['redirect'];
				}
				return $out;
			}
			if ( function_exists( '_doing_it_wrong' ) ) {
				_doing_it_wrong( 'Wenpai_Admin_Action::callback', 'Invalid return; expected array|WP_Error|null.', '0.3.5' );
			}
			return array(
				'level'   => 'bad',
				'message' => $strings['action_failed'],
			);
		}

		/**
		 * Store a one-shot result payload for the current user.
		 *
		 * @param array $payload level+message.
		 * @return string Token or ''.
		 */
		private static function store_result( array $payload ) {
			$user  = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
			$token = self::make_token();
			$key   = 'wenpai_action_' . $user . '_' . $token;
			if ( function_exists( 'set_transient' ) ) {
				set_transient( $key, $payload, self::TRANSIENT_TTL );
			}
			return $token;
		}

		/**
		 * Generate a random one-shot token.
		 *
		 * @return string 12-char [a-z0-9].
		 */
		private static function make_token() {
			$alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
			$out      = '';
			$max      = strlen( $alphabet ) - 1;
			for ( $i = 0; $i < self::TOKEN_LEN; $i++ ) {
				if ( function_exists( 'wp_rand' ) ) {
					$out .= $alphabet[ wp_rand( 0, $max ) ];
				} else {
					$out .= $alphabet[ random_int( 0, $max ) ];
				}
			}
			return $out;
		}

		/**
		 * Resolve and validate a callback, referer, or admin redirect.
		 *
		 * @param string|null $redirect Optional callback redirect.
		 * @return string
		 */
		private static function resolve_redirect( $redirect ) {
			if ( is_string( $redirect ) && '' !== $redirect ) {
				$validated = function_exists( 'wp_validate_redirect' )
					? wp_validate_redirect( $redirect, false )
					: $redirect;
				if ( is_string( $validated ) && '' !== $validated ) {
					return $validated;
				}
			}
			$ref = function_exists( 'wp_get_referer' ) ? wp_get_referer() : false;
			if ( is_string( $ref ) && '' !== $ref ) {
				$validated = function_exists( 'wp_validate_redirect' )
					? wp_validate_redirect( $ref, false )
					: $ref;
				if ( is_string( $validated ) && '' !== $validated ) {
					return $validated;
				}
			}
			return function_exists( 'admin_url' ) ? admin_url() : '/wp-admin/';
		}


		/**
		 * Redirect then exit. Tests may define WENPAI_ACTION_TEST_NO_EXIT to throw instead.
		 *
		 * @param string $to URL.
		 * @throws Wenpai_Test_Redirect When test mode intercepts the redirect.
		 */
		private static function redirect_to( $to ) {
			if ( function_exists( 'wp_safe_redirect' ) ) {
				wp_safe_redirect( $to );
			}
			if ( defined( 'WENPAI_ACTION_TEST_NO_EXIT' ) && WENPAI_ACTION_TEST_NO_EXIT ) {
				throw new Wenpai_Test_Redirect( $to ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- test exception carries the validated redirect, not HTML output.
			}
			exit;
		}

		/**
		 * Deny an action with a generic 403 response.
		 *
		 * @param string $message Die message.
		 */
		private static function deny( $message ) {
			if ( function_exists( 'wp_die' ) ) {
				wp_die( esc_html( $message ), '', array( 'response' => 403 ) );
			}
			exit;
		}

		/**
		 * One-shot notice from ?wenpai_action=token. Empty when missing/foreign/expired.
		 * Single notice path: UI::notice_html; fade only for ok.
		 *
		 * @return string
		 */
		public static function notice_html() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- one-shot read token.
			if ( empty( $_GET['wenpai_action'] ) || ! is_scalar( $_GET['wenpai_action'] ) ) {
				return '';
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$token = preg_replace( '/[^a-z0-9]/', '', strtolower( sanitize_text_field( wp_unslash( $_GET['wenpai_action'] ) ) ) );
			if ( strlen( $token ) !== self::TOKEN_LEN ) {
				return '';
			}
			$user = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
			$key  = 'wenpai_action_' . $user . '_' . $token;
			$data = function_exists( 'get_transient' ) ? get_transient( $key ) : false;
			if ( function_exists( 'delete_transient' ) ) {
				delete_transient( $key );
			}
			if ( ! is_array( $data ) || empty( $data['message'] ) ) {
				return '';
			}
			$level = isset( $data['level'] ) ? (string) $data['level'] : 'info';
			if ( ! in_array( $level, array( 'ok', 'warn', 'bad', 'info' ), true ) ) {
				$level = 'info';
			}
			if ( ! class_exists( 'Wenpai_Admin_UI', false ) ) {
				return '';
			}
			return Wenpai_Admin_UI::notice_html(
				array(
					'text'  => (string) $data['message'],
					'level' => $level,
					'fade'  => ( 'ok' === $level ),
				)
			);
		}

		/**
		 * Reset registry (tests only).
		 */
		public static function reset_for_tests() {
			self::$actions = array();
		}
	}
}
