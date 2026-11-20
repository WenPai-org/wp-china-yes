<?php
/**
 * Cross-plugin capability probe and referral for WenPai sibling products.
 *
 * Use with field type `status` (read-only) and `choice` overlays (hint +
 * disabled). Products pass the sibling's plugin file or callables plus their
 * own copy; the kit only shapes three states (available, installed but not
 * ready, missing) and the permission-aware next step (install, activate,
 * settings). Referral parts can be switched off site-wide, see enabled(),
 * and each user sees them only with install_plugins and until they click
 * 「不再提示」 for that slot (product-guidance.md P-7), see referral().
 *
 * @package Wenpai_Admin_UI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wenpai_Admin_Capability', false ) ) {

	/**
	 * Sibling-product referral helper (e.g. WPSlug → WenPai XinSi).
	 */
	class Wenpai_Admin_Capability {

		/**
		 * Default action labels. Products override per call with `labels`.
		 *
		 * @var array<string, string>
		 */
		const LABELS = array(
			'install'  => '安装',
			'activate' => '启用',
			'settings' => '打开设置',
			'learn'    => '了解更多',
			'dismiss'  => '不再提示',
		);

		/**
		 * User meta holding the dismissed referral slots: array( slot => time ).
		 */
		const DISMISS_META = 'wenpai_admin_ui_dismissed';

		/**
		 * The admin-post action and nonce action prefix of 「不再提示」.
		 */
		const DISMISS_ACTION = 'wenpai_admin_ui_dismiss';

		/**
		 * Default 「不再提示」 failure messages by process_dismiss() code. Override or
		 * translate them with the wenpai_admin_ui_dismiss_messages filter (array $messages).
		 *
		 * @var array<string, string>
		 */
		const MESSAGES = array(
			'nonce' => '链接已过期，请刷新页面后再试。',
			'cap'   => '你没有权限关闭这条推荐。',
			'store' => '暂时无法保存，请稍后再试。',
		);

		/**
		 * Failure messages after the wenpai_admin_ui_dismiss_messages filter.
		 *
		 * @return array<string, string>
		 */
		public static function messages() {
			$m = self::MESSAGES;
			if ( function_exists( 'apply_filters' ) ) {
				$m = array_merge( $m, array_map( 'strval', (array) apply_filters( 'wenpai_admin_ui_dismiss_messages', $m ) ) );
			}
			return $m;
		}

		/**
		 * Whether referral parts (recommend hint, install link, missing-state
		 * copy) are shown. Factual states of installed siblings are always shown.
		 *
		 * Off with define( 'WENPAI_ADMIN_UI_REFERRALS', false ) in wp-config.php,
		 * or the wenpai_admin_ui_referrals filter (bool $on, array $args).
		 *
		 * @param array $args Same args as probe(); passed to the filter.
		 * @return bool
		 */
		public static function enabled( array $args = array() ) {
			$on = ! ( defined( 'WENPAI_ADMIN_UI_REFERRALS' ) && ! WENPAI_ADMIN_UI_REFERRALS );
			if ( function_exists( 'apply_filters' ) ) {
				$on = (bool) apply_filters( 'wenpai_admin_ui_referrals', $on, $args );
			}
			return $on;
		}

		/**
		 * Whether the current user sees referral parts for $args (P-7): referrals
		 * are on, the user can install plugins, and has not dismissed the slot.
		 * Editors and authors get facts only, as with referrals off.
		 *
		 * @param array $args probe() args plus optional slot.
		 * @return bool
		 */
		public static function referral( array $args = array() ) {
			return self::enabled( $args ) && self::can( 'install_plugins' ) && ! self::dismissed( self::slot( $args ) );
		}

		/**
		 * Referral slot key: $args['slot'] (product + position, e.g.
		 * 'wpslug-mind-pinyin'), else the sibling's plugin directory, else
		 * 'referral'. Lowercase [a-z0-9_-], at most 64 chars.
		 *
		 * @param array $args probe() args plus optional slot.
		 * @return string
		 */
		public static function slot( array $args ) {
			$slot = isset( $args['slot'] ) ? self::slot_key( $args['slot'] ) : '';
			if ( '' === $slot && ! empty( $args['plugin'] ) ) {
				$parts = explode( '/', (string) $args['plugin'] );
				$slot  = self::slot_key( $parts[0] );
			}
			return '' === $slot ? 'referral' : $slot;
		}

		/**
		 * Whether the current user dismissed this slot.
		 *
		 * @param string $slot Slot key.
		 * @return bool
		 */
		public static function dismissed( $slot ) {
			$slot = self::slot_key( $slot );
			$list = self::dismissed_list();
			return '' !== $slot && isset( $list[ $slot ] );
		}

		/**
		 * Remember 「不再提示」 for the current user (user meta, every browser and device).
		 *
		 * @param string $slot Slot key.
		 * @return bool False when there is no logged-in user or the slot is empty.
		 */
		public static function dismiss( $slot ) {
			$slot = self::slot_key( $slot );
			$uid  = self::user_id();
			if ( '' === $slot || ! $uid || ! function_exists( 'update_user_meta' ) ) {
				return false;
			}
			$list = self::dismissed_list();
			if ( isset( $list[ $slot ] ) ) {
				return true;
			}
			$list[ $slot ] = time();
			return false !== update_user_meta( $uid, self::DISMISS_META, $list );
		}

		/**
		 * The no-JS 「不再提示」 URL: admin-post.php with the slot and a nonce.
		 * Kit JS posts to the same URL with wenpai_js=1.
		 *
		 * @param string $slot Slot key.
		 * @return string '' outside WordPress.
		 */
		public static function dismiss_url( $slot ) {
			$slot = self::slot_key( $slot );
			if ( '' === $slot || ! function_exists( 'wp_nonce_url' ) || ! function_exists( 'admin_url' ) ) {
				return '';
			}
			return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::DISMISS_ACTION . '&slot=' . rawurlencode( $slot ) ), self::DISMISS_ACTION . '|' . $slot );
		}

		/**
		 * Check and store one 「不再提示」 request. handle_dismiss() wraps it.
		 *
		 * @param array $req Unslashed request: slot, _wpnonce.
		 * @return array{ok: bool, code: string, slot: string} code is '' | nonce | cap | store.
		 */
		public static function process_dismiss( array $req ) {
			$slot  = isset( $req['slot'] ) && is_string( $req['slot'] ) ? self::slot_key( $req['slot'] ) : '';
			$nonce = isset( $req['_wpnonce'] ) && is_string( $req['_wpnonce'] ) ? $req['_wpnonce'] : '';
			$code  = '';
			if ( '' === $slot || ! function_exists( 'wp_verify_nonce' ) || ! wp_verify_nonce( $nonce, self::DISMISS_ACTION . '|' . $slot ) ) {
				$code = 'nonce';
			} elseif ( ! self::can( 'install_plugins' ) ) {
				$code = 'cap';
			} elseif ( ! self::dismiss( $slot ) ) {
				$code = 'store';
			}
			return array(
				'ok'   => '' === $code,
				'code' => $code,
				'slot' => $slot,
			);
		}

		/**
		 * The admin_post_wenpai_admin_ui_dismiss handler. With wenpai_js=1
		 * (kit JS) it answers JSON; without JS it redirects back to the referring
		 * admin page (wp_safe_redirect). Bad nonce or no install_plugins: 403;
		 * the user meta write failing: 500. Messages: messages().
		 */
		public static function handle_dismiss() {
			$req = array();
			foreach ( array( 'slot', '_wpnonce', 'wenpai_js' ) as $k ) {
				if ( isset( $_REQUEST[ $k ] ) && is_string( $_REQUEST[ $k ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- process_dismiss() verifies the nonce.
					$req[ $k ] = sanitize_text_field( wp_unslash( $_REQUEST[ $k ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- process_dismiss() verifies the nonce.
				}
			}
			$js  = ! empty( $req['wenpai_js'] );
			$res = self::process_dismiss( $req );
			if ( ! $res['ok'] ) {
				$all    = self::messages();
				$msg    = isset( $all[ $res['code'] ] ) ? $all[ $res['code'] ] : $all['nonce'];
				$status = 'store' === $res['code'] ? 500 : 403;
				if ( $js ) {
					wp_send_json_error(
						array(
							'message' => $msg,
							'code'    => $res['code'],
						),
						$status
					);
				}
				wp_die(
					esc_html( $msg ),
					'',
					array(
						'response'  => (int) $status,
						'back_link' => true,
					)
				);
			}
			if ( $js ) {
				wp_send_json_success( array( 'slot' => $res['slot'] ) );
			}
			$to = function_exists( 'wp_get_referer' ) ? wp_get_referer() : false;
			wp_safe_redirect( $to ? $to : admin_url() );
			exit;
		}

		/**
		 * Resolve the sibling's state.
		 *
		 * @param array $args {
		 *     Probe arguments.
		 *
		 *     @type string        $plugin    Sibling plugin file relative to the plugins dir (wpmind/wpmind.php). Fills installed / active.
		 *     @type callable|bool $installed Sibling present (files or class).
		 *     @type callable|bool $active    Sibling plugin active.
		 *     @type callable|bool $available Sibling ready to use (active and configured).
		 * }
		 * @return array{state: string, installed: bool, active: bool, available: bool} state is available|installed|missing.
		 */
		public static function probe( array $args ) {
			$plugin    = isset( $args['plugin'] ) ? (string) $args['plugin'] : '';
			$installed = array_key_exists( 'installed', $args ) ? self::flag( $args['installed'] ) : self::plugin_installed( $plugin );
			$active    = array_key_exists( 'active', $args ) ? self::flag( $args['active'] ) : self::plugin_active( $plugin );
			$available = array_key_exists( 'available', $args ) ? self::flag( $args['available'] ) : $active;
			if ( $available ) {
				$active = true;
			}
			if ( $active ) {
				$installed = true;
			}
			if ( $available ) {
				$state = 'available';
			} elseif ( $installed ) {
				$state = 'installed';
			} else {
				$state = 'missing';
			}
			return array(
				'state'     => $state,
				'installed' => $installed,
				'active'    => $active,
				'available' => $available,
			);
		}

		/**
		 * Next step for the current user, as a status link, or null.
		 *
		 * When missing, only for referral() users (install_plugins, not
		 * dismissed, referrals on): install_url, else learn_url in a new tab;
		 * everyone else gets null (P-7). Installed but inactive: a nonce
		 * activate link when the user can activate plugins. Active (ready or
		 * not): settings_url when the user can manage options.
		 *
		 * @param array $args probe() args plus install_url, learn_url, settings_url, labels.
		 * @return array{url: string, label: string, target?: string}|null
		 */
		public static function action( array $args ) {
			$probe  = self::probe( $args );
			$labels = array_merge( self::LABELS, isset( $args['labels'] ) ? (array) $args['labels'] : array() );
			if ( 'missing' === $probe['state'] ) {
				if ( ! self::referral( $args ) ) {
					return null;
				}
				if ( ! empty( $args['install_url'] ) ) {
					return array(
						'url'   => (string) $args['install_url'],
						'label' => $labels['install'],
					);
				}
				if ( ! empty( $args['learn_url'] ) ) {
					return array(
						'url'    => (string) $args['learn_url'],
						'label'  => $labels['learn'],
						'target' => '_blank',
					);
				}
				return null;
			}
			$plugin = isset( $args['plugin'] ) ? (string) $args['plugin'] : '';
			if ( ! $probe['active'] && '' !== $plugin && self::can( 'activate_plugin', $plugin ) && function_exists( 'wp_nonce_url' ) ) {
				$base = function_exists( 'self_admin_url' ) ? self_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' );
				return array(
					'url'   => wp_nonce_url( $base . '?action=activate&plugin=' . rawurlencode( $plugin ), 'activate-plugin_' . $plugin ),
					'label' => $labels['activate'],
				);
			}
			if ( $probe['active'] && ! empty( $args['settings_url'] ) && self::can( 'manage_options' ) ) {
				return array(
					'url'   => (string) $args['settings_url'],
					'label' => $labels['settings'],
				);
			}
			return null;
		}

		/**
		 * Status payload for field type `status`.
		 *
		 * Each state's array may set level/text/desc/link; a missing link is
		 * filled from action(). Without referral() (referrals off, no
		 * install_plugins, or dismissed) the missing state keeps only its pill
		 * text (a fact, no pitch): no desc, no link. With a pitch it carries
		 * dismiss: array( url, label, slot ) for the 「不再提示」 control.
		 * Installed and active but not ready uses setup_status when given,
		 * else installed_status.
		 *
		 * @param array $args probe() / action() args plus available_status,
		 *                    installed_status, setup_status, missing_status.
		 * @return array{level: string, text: string, desc: string[], link?: array, dismiss?: array}
		 */
		public static function status( array $args ) {
			$probe = self::probe( $args );
			$key   = $probe['state'] . '_status';
			if ( 'installed' === $probe['state'] && $probe['active'] && isset( $args['setup_status'] ) ) {
				$key = 'setup_status';
			}
			$st  = isset( $args[ $key ] ) && is_array( $args[ $key ] ) ? $args[ $key ] : array();
			$out = array(
				'level' => isset( $st['level'] ) ? (string) $st['level'] : ( 'available' === $probe['state'] ? 'ok' : ( 'installed' === $probe['state'] ? 'warn' : 'mute' ) ),
				'text'  => isset( $st['text'] ) ? (string) $st['text'] : '',
				'desc'  => isset( $st['desc'] ) ? array_values( (array) $st['desc'] ) : array(),
			);
			if ( 'missing' === $probe['state'] && ! self::referral( $args ) ) {
				$out['desc'] = array();
				return $out;
			}
			$link = isset( $st['link'] ) && is_array( $st['link'] ) ? $st['link'] : self::action( $args );
			if ( $link ) {
				$out['link'] = $link;
			}
			if ( 'missing' === $probe['state'] && ( $out['desc'] || $link ) ) {
				$dismiss = self::dismiss_link( $args );
				if ( $dismiss ) {
					$out['dismiss'] = $dismiss;
				}
			}
			return $out;
		}

		/**
		 * Choice-card overlay for a capability-gated option.
		 *
		 * When missing: disabled, with hint_missing (e.g. 推荐) for referral()
		 * users only; with referrals off it is hidden instead (the product
		 * drops the choice). After 「不再提示」 or without install_plugins the
		 * choice stays (P-1), only the hint goes. installed / available:
		 * enabled, product copy owns the label; runtime fallback stays with the
		 * product. dismiss is set with the hint (same slot as status()).
		 *
		 * @param array $args probe() args plus optional hint_missing, slot.
		 * @return array{disabled: bool, hint: string, hidden: bool, dismiss: array|null}
		 */
		public static function choice_gate( array $args ) {
			$probe   = self::probe( $args );
			$missing = 'missing' === $probe['state'];
			$on      = self::enabled( $args );
			$hint    = $missing && isset( $args['hint_missing'] ) && self::referral( $args ) ? (string) $args['hint_missing'] : '';
			return array(
				'disabled' => $missing,
				'hint'     => $hint,
				'hidden'   => $missing && ! $on,
				'dismiss'  => '' !== $hint ? self::dismiss_link( $args ) : null,
			);
		}

		/**
		 * The dismiss part of status() / choice_gate(), or null outside WordPress
		 * or without a logged-in user. context is $args['product'] (the
		 * recommended product's name, for the control's accessible name), or ''.
		 *
		 * @param array $args probe() args plus optional slot, labels, product.
		 * @return array{url: string, label: string, slot: string, context: string}|null
		 */
		private static function dismiss_link( array $args ) {
			$slot = self::slot( $args );
			$url  = self::user_id() ? self::dismiss_url( $slot ) : '';
			if ( '' === $url ) {
				return null;
			}
			$labels = array_merge( self::LABELS, isset( $args['labels'] ) ? (array) $args['labels'] : array() );
			return array(
				'url'     => $url,
				'label'   => (string) $labels['dismiss'],
				'slot'    => $slot,
				'context' => isset( $args['product'] ) && is_scalar( $args['product'] ) ? (string) $args['product'] : '',
			);
		}

		/**
		 * Dismissed slots of the current user.
		 *
		 * @return array<string, int>
		 */
		private static function dismissed_list() {
			$uid = self::user_id();
			if ( ! $uid || ! function_exists( 'get_user_meta' ) ) {
				return array();
			}
			$list = get_user_meta( $uid, self::DISMISS_META, true );
			return is_array( $list ) ? $list : array();
		}

		/**
		 * Current user id, 0 outside WordPress.
		 *
		 * @return int
		 */
		private static function user_id() {
			return function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		}

		/**
		 * Normalize a slot key.
		 *
		 * @param mixed $slot Raw slot.
		 * @return string
		 */
		private static function slot_key( $slot ) {
			return is_scalar( $slot ) ? substr( (string) preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $slot ) ), 0, 64 ) : '';
		}

		/**
		 * Whether the plugin file exists in the plugins dir.
		 *
		 * @param string $plugin Plugin file relative to the plugins dir.
		 * @return bool
		 */
		private static function plugin_installed( $plugin ) {
			if ( '' === $plugin || ! defined( 'WP_PLUGIN_DIR' ) || false !== strpos( $plugin, '..' ) ) {
				return false;
			}
			return is_file( WP_PLUGIN_DIR . '/' . $plugin );
		}

		/**
		 * Whether the plugin is active on this site or network-wide.
		 *
		 * @param string $plugin Plugin file relative to the plugins dir.
		 * @return bool
		 */
		private static function plugin_active( $plugin ) {
			if ( '' === $plugin ) {
				return false;
			}
			if ( function_exists( 'is_plugin_active' ) ) {
				return is_plugin_active( $plugin );
			}
			if ( in_array( $plugin, (array) get_option( 'active_plugins', array() ), true ) ) {
				return true;
			}
			if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_site_option' ) ) {
				return array_key_exists( $plugin, (array) get_site_option( 'active_sitewide_plugins', array() ) );
			}
			return false;
		}

		/**
		 * Current user capability check, false outside WordPress.
		 *
		 * @param string $cap  Capability.
		 * @param mixed  ...$a Extra args for meta caps.
		 * @return bool
		 */
		private static function can( $cap, ...$a ) {
			return function_exists( 'current_user_can' ) && current_user_can( $cap, ...$a );
		}

		/**
		 * Bool from a flag or a callable.
		 *
		 * @param callable|bool $v Flag or callable returning bool.
		 * @return bool
		 */
		private static function flag( $v ) {
			if ( is_callable( $v ) ) {
				return (bool) call_user_func( $v );
			}
			return (bool) $v;
		}
	}

	if ( function_exists( 'add_action' ) ) {
		add_action( 'admin_post_' . Wenpai_Admin_Capability::DISMISS_ACTION, array( 'Wenpai_Admin_Capability', 'handle_dismiss' ) );
	}
}
