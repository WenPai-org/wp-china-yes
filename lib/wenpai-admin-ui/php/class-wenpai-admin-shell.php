<?php
/**
 * Settings-plugin chrome: top bar, tabs, wrap, footer.
 * Strings are passed in by the product plugin (no kit text domain).
 *
 * Markup matches prototypes: .wenpai-top > .wenpai-wrap > .wenpai-head,
 * then main.wenpai-main.wenpai-wrap, footer.foot.
 *
 * @package Wenpai_Admin_UI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wenpai_Admin_Shell', false ) ) {
	/**
	 * Echoes markup. Product fills the inner pages.
	 */
	class Wenpai_Admin_Shell {

		/**
		 * Default Cyberforums prefilled topic endpoint.
		 *
		 * @var string
		 */
		const DEFAULT_FEEDBACK_BASE_URL = 'https://meta.cyberforums.com/new-topic';

		/**
		 * Default Cyberforums category ID (2 = WenPai.org / 文派开源).
		 *
		 * @var int
		 */
		const DEFAULT_FEEDBACK_CATEGORY_ID = 2;

		/**
		 * Echo the opening chrome: top bar, brand, tabs and main wrapper.
		 *
		 * @param array $args {
		 *     Shell arguments.
		 *
		 *     @type string $slug            Product slug (wpslug / wpsss). Printed as
		 *                                   data-wenpai-product on .wenpai-app (0.3.0+).
		 *     @type string $name            Product display name (already translated).
		 *     @type string $mark_html       Product SVG for .wenpai-mark.
		 *     @type string $home_url        Brand link. Defaults to first tab url.
		 *     @type string $current         Current tab id.
		 *     @type array  $tabs            array of array{ id, label, url, icon_html? }.
		 *     @type string $head_extra      Optional HTML after tabs (wizard exit). Caller escapes.
		 *     @type string $head_right_html Optional HTML at the start of .wenpai-head-right, before help. Caller escapes.
		 *     @type string $help_url        Optional.
		 *     @type string $help_label      Optional, already translated.
		 *     @type string $help_icon_html  Optional SVG, caller escapes.
		 *     @type string $feedback_url    Optional.
		 *     @type string $feedback_label  Optional.
		 *     @type string $feedback_icon_html Optional SVG.
		 *     @type string|array $feedback  Optional footer feedback link: URL string or feedback_url()
		 *                                   config array (plus optional 'label'). Rendered by close().
		 *     @type bool   $whitelabel      Optional explicit white-label flag.
		 *     @type string $version         Plugin version string.
		 *     @type string $doc_url         Footer doc link.
		 *     @type string $doc_label       Footer doc label.
		 *     @type array  $links           Footer links: array of array{ url, label } (0.2.5+). An
		 *                                   entry with 'text' and no url is plain text (escaped).
		 *     @type string $main_class      Extra classes on main.wenpai-main (0.2.5+), e.g. is-narrow.
		 *     @type string $new_tab_label   Screen-reader hint after the feedback and footer links,
		 *                                   which open in a new tab (0.3.0+). Default 「（在新标签页打开）」;
		 *                                   '' prints none. Pass it to close() too for the footer.
		 * }
		 */
		public static function open( $args ) {
			$name    = isset( $args['name'] ) ? $args['name'] : '';
			$mark    = isset( $args['mark_html'] ) ? $args['mark_html'] : '';
			$current = isset( $args['current'] ) ? $args['current'] : '';
			$tabs    = isset( $args['tabs'] ) && is_array( $args['tabs'] ) ? $args['tabs'] : array();
			$home    = isset( $args['home_url'] ) ? $args['home_url'] : '';
			if ( '' === $home && isset( $tabs[0]['url'] ) ) {
				$home = $tabs[0]['url'];
			}

			$slug = isset( $args['slug'] ) && is_string( $args['slug'] ) ? sanitize_key( $args['slug'] ) : '';
			echo '<div class="wenpai-app"' . ( '' !== $slug ? ' data-wenpai-product="' . esc_attr( $slug ) . '"' : '' ) . '>';
			echo '<div class="wenpai-top"><div class="wenpai-wrap">';
			echo '<div class="wenpai-head">';
			echo '<a class="wenpai-brand" href="' . esc_url( $home ) . '">';
			echo '<span class="wenpai-mark">' . $mark . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- product SVG.
			echo '<span class="wenpai-name">' . esc_html( $name ) . '</span>';
			echo '</a>';
			if ( array() !== $tabs ) {
				echo '<nav class="wenpai-tabs" aria-label="' . esc_attr( $name ) . '">';
				foreach ( $tabs as $tab ) {
					$id    = isset( $tab['id'] ) ? $tab['id'] : '';
					$label = isset( $tab['label'] ) ? $tab['label'] : '';
					$url   = isset( $tab['url'] ) ? $tab['url'] : '';
					$icon  = isset( $tab['icon_html'] ) ? $tab['icon_html'] : '';
					$cls   = 'wenpai-tab';
					if ( $id === $current ) {
						$cls .= ' is-active';
					}
					echo '<a class="' . esc_attr( $cls ) . '" href="' . esc_url( $url ) . '">';
					echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- product SVG.
					echo esc_html( $label );
					echo '</a>';
				}
				echo '</nav>';
			}
			if ( ! empty( $args['head_extra'] ) ) {
				echo $args['head_extra']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller HTML.
			}
			echo '<div class="wenpai-head-right">';
			if ( ! empty( $args['head_right_html'] ) ) {
				echo $args['head_right_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller HTML.
			}
			if ( ! empty( $args['help_url'] ) ) {
				$hl  = isset( $args['help_label'] ) ? $args['help_label'] : '帮助';
				$hcl = 'btn btn-ghost wenpai-head-action';
				if ( 'help' === $current ) {
					$hcl .= ' is-active';
				}
				$hicon = isset( $args['help_icon_html'] ) ? $args['help_icon_html'] : '';
				echo '<a class="' . esc_attr( $hcl ) . '" href="' . esc_url( $args['help_url'] ) . '">';
				echo $hicon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- product SVG.
				echo '<span class="wenpai-head-label">' . esc_html( $hl ) . '</span>';
				echo '</a>';
			}
			if ( ! empty( $args['feedback_url'] ) ) {
				$furl = (string) $args['feedback_url'];
				if ( self::is_default_feedback_host( $furl ) ) {
					$wl = self::whitelabel_state( $args );
					if ( $wl['active'] ) {
						$furl = ( '' !== $wl['override_url'] && ! self::is_default_feedback_host( $wl['override_url'] ) )
							? self::sanitize_http_url( $wl['override_url'] )
							: '';
					}
				}
				if ( '' !== $furl ) {
					$fl    = isset( $args['feedback_label'] ) ? $args['feedback_label'] : '反馈';
					$ficon = isset( $args['feedback_icon_html'] ) ? $args['feedback_icon_html'] : '';
					echo '<a class="btn btn-ghost wenpai-head-action" href="' . esc_url( $furl ) . '" target="_blank" rel="noopener noreferrer">';
					echo $ficon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- product SVG.
					echo '<span class="wenpai-head-label">' . esc_html( $fl ) . '</span>';
					echo self::new_tab_hint( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in new_tab_hint().
					echo '</a>';
				}
			}
			echo '</div>';
			echo '</div></div></div>';
			$mc = isset( $args['main_class'] ) ? ' ' . $args['main_class'] : '';
			echo '<main class="' . esc_attr( 'wenpai-main wenpai-wrap' . $mc ) . '">';
		}

		/**
		 * Close main + footer.
		 *
		 * @param array $args Same as open(); uses version / doc_url / doc_label / links / new_tab_label.
		 */
		public static function close( $args = array() ) {
			$ver = isset( $args['version'] ) ? $args['version'] : '';
			$doc = isset( $args['doc_url'] ) ? $args['doc_url'] : '';
			$dl  = isset( $args['doc_label'] ) ? $args['doc_label'] : '';

			echo '<footer class="foot">';
			if ( '' !== $ver ) {
				echo '<span>' . esc_html( $ver ) . '</span>';
			}
			$links = isset( $args['links'] ) && is_array( $args['links'] ) ? $args['links'] : array();
			if ( '' !== $doc ) {
				array_unshift(
					$links,
					array(
						'url'   => $doc,
						'label' => $dl,
					)
				);
			}
			if ( isset( $args['feedback'] ) ) {
				$fb = self::resolve_feedback( $args['feedback'], $args );
				if ( '' !== $fb['url'] ) {
					$links[] = array(
						'url'   => $fb['url'],
						'label' => $fb['label'],
					);
				}
			}
			if ( array() !== $links ) {
				echo '<span class="r">';
				foreach ( $links as $link ) {
					if ( empty( $link['url'] ) ) {
						if ( isset( $link['text'] ) && is_scalar( $link['text'] ) && '' !== (string) $link['text'] ) {
							echo '<span>' . esc_html( (string) $link['text'] ) . '</span>';
						}
						continue;
					}
					echo '<a href="' . esc_url( $link['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( isset( $link['label'] ) ? $link['label'] : '' ) . self::new_tab_hint( $args ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in new_tab_hint().
				}
				echo '</span>';
			}
			echo '</footer>';
			echo '</main>';
			echo '</div>';
		}

		/**
		 * Page heading inside main.
		 *
		 * @param string $title           Already translated.
		 * @param string $lede            Already translated. Optional.
		 * @param string $aside           Optional HTML for .mode (escaped by caller).
		 * @param string $lede_extra_html Optional inline HTML appended inside .wenpai-lede (e.g. drawer_toggle_html(); escaped by caller).
		 */
		public static function page_head( $title, $lede = '', $aside = '', $lede_extra_html = '' ) {
			echo '<div class="wenpai-page-head">';
			echo '<div>';
			echo '<h1 class="wenpai-h1">' . esc_html( $title ) . '</h1>';
			if ( '' !== $lede || '' !== $lede_extra_html ) {
				echo '<p class="wenpai-lede">';
				if ( '' !== $lede ) {
					echo esc_html( $lede );
				}
				if ( '' !== $lede_extra_html ) {
					echo $lede_extra_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller HTML (e.g. Wenpai_Admin_UI::drawer_toggle_html).
				}
				echo '</p>';
			}
			echo '</div>';
			if ( '' !== $aside ) {
				echo '<div class="mode">' . $aside . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller HTML.
			}
			echo '</div>';
		}

		/**
		 * Drill-down subpage heading with back breadcrumb link above the page head (kit 0.12.0).
		 *
		 * @param array $args {
		 *     Subpage heading arguments.
		 *
		 *     @type string $title      Subpage h1 title (already translated).
		 *     @type string $back_url   Parent tab/directory URL.
		 *     @type string $back_label Back link label (e.g. 「返回功能列表」).
		 *     @type string $lede       Optional subtitle.
		 *     @type string $aside_html Optional HTML for .mode (pill, switch, or secondary action; caller escapes).
		 * }
		 */
		public static function subpage_head( $args = array() ) {
			$title      = isset( $args['title'] ) ? (string) $args['title'] : '';
			$back_url   = isset( $args['back_url'] ) ? (string) $args['back_url'] : '';
			$back_label = isset( $args['back_label'] ) ? (string) $args['back_label'] : '';
			$lede       = isset( $args['lede'] ) ? (string) $args['lede'] : '';
			$aside_html = isset( $args['aside_html'] ) ? (string) $args['aside_html'] : '';
			$arrow      = class_exists( 'Wenpai_Admin_Icons', false ) ? Wenpai_Admin_Icons::svg( 'arrow-left' ) : '';

			echo '<div class="wenpai-subhead">';
			if ( '' !== $back_url && '' !== $back_label ) {
				echo '<a class="wenpai-back" href="' . esc_url( $back_url ) . '">';
				echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Wenpai_Admin_Icons::svg() safe SVG.
				echo '<span>' . esc_html( $back_label ) . '</span>';
				echo '</a>';
			}
			self::page_head( $title, $lede, $aside_html );
			echo '</div>';
		}

		/**
		 * Build a standard prefilled Cyberforums feedback URL with a sanitized runtime summary.
		 *
		 * Only includes non-sensitive environment facts (plugin name/version, WordPress version,
		 * PHP major.minor.release, multisite boolean) plus optional caller-provided extra_context.
		 * Never collects site domain, IP, email, keys, or user PII.
		 *
		 * In white-label mode (explicit whitelabel flag, WPCY_WHITELABEL* constants, or
		 * wenpai_plugin_display filter), returns '' when no custom base_url or feedback_url
		 * override is supplied so default meta.cyberforums.com links are never exposed.
		 *
		 * @param array $args {
		 *     Feedback URL arguments.
		 *
		 *     @type int          $category_id    Cyberforums category ID. Default 2 (文派开源).
		 *     @type array|string $tags           Topic tag(s). Default array( 'bug' ).
		 *     @type string       $plugin_name    Optional plugin display name.
		 *     @type string       $plugin_version Optional plugin version string.
		 *     @type string       $title          Optional prefilled topic title.
		 *     @type string|array $extra_context  Optional extra non-sensitive context appended to body.
		 *     @type string       $base_url       Optional endpoint base URL (defaults to
		 *                                        https://meta.cyberforums.com/new-topic; white-label overridable).
		 *     @type string       $feedback_url   Optional explicit full custom feedback URL override.
		 *     @type string       $slug           Optional plugin slug for wenpai_plugin_display filter.
		 *     @type bool         $whitelabel     Optional explicit white-label flag.
		 * }
		 * @return string Escaped http(s) URL via esc_url(), or '' when suppressed/invalid.
		 */
		public static function feedback_url( $args = array() ) {
			if ( ! is_array( $args ) ) {
				$args = array();
			}
			$raw = self::build_feedback_url_raw( $args );
			return '' === $raw ? '' : esc_url( $raw, array( 'http', 'https' ) );
		}

		/**
		 * Resolve an optional feedback slot (URL string or feedback_url() config array) with white-label protection.
		 *
		 * Internal helper shared by Wenpai_Admin_Shell::close() and Wenpai_Admin_UI::help_html().
		 * Returns a raw validated http(s) URL so the caller escapes it once with esc_url().
		 *
		 * @param string|array $feedback URL string or feedback_url() args (plus optional 'label').
		 * @param array        $context  Optional surrounding shell/help args (slug, whitelabel, feedback_label, feedback_url).
		 * @return array{url: string, label: string}
		 */
		public static function resolve_feedback( $feedback, $context = array() ) {
			$empty   = array(
				'url'   => '',
				'label' => '',
			);
			$context = is_array( $context ) ? $context : array();
			$default = isset( $context['feedback_label'] ) && '' !== trim( (string) $context['feedback_label'] )
				? trim( (string) $context['feedback_label'] )
				: '反馈';

			if ( is_string( $feedback ) ) {
				$url = self::sanitize_http_url( $feedback );
				if ( '' === $url ) {
					return $empty;
				}
				if ( self::is_default_feedback_host( $url ) ) {
					$wl = self::whitelabel_state( $context );
					if ( $wl['active'] ) {
						$override = self::resolve_whitelabel_override_url( $context, $wl );
						if ( '' === $override ) {
							return $empty;
						}
						$url = $override;
					}
				}
				return array(
					'url'   => $url,
					'label' => $default,
				);
			}

			if ( ! is_array( $feedback ) || array() === $feedback ) {
				return $empty;
			}

			$cfg = $feedback;
			if ( ! isset( $cfg['slug'] ) && isset( $context['slug'] ) ) {
				$cfg['slug'] = $context['slug'];
			}
			if ( ! isset( $cfg['whitelabel'] ) && isset( $context['whitelabel'] ) ) {
				$cfg['whitelabel'] = $context['whitelabel'];
			}
			$url = self::build_feedback_url_raw( $cfg, $context );
			if ( '' === $url ) {
				return $empty;
			}
			$label = isset( $feedback['label'] ) && '' !== trim( (string) $feedback['label'] )
				? trim( (string) $feedback['label'] )
				: $default;

			return array(
				'url'   => $url,
				'label' => $label,
			);
		}

		/**
		 * Build a raw (non-HTML-entity-escaped) prefilled feedback URL.
		 *
		 * @param array $args    feedback_url() args.
		 * @param array $context Optional caller context for white-label fallback.
		 * @return string Raw http(s) URL, or '' when suppressed/invalid.
		 */
		private static function build_feedback_url_raw( array $args, array $context = array() ) {
			$wl = self::whitelabel_state( $args );

			$has_explicit = isset( $args['feedback_url'] ) && is_string( $args['feedback_url'] ) && '' !== trim( $args['feedback_url'] );
			if ( $has_explicit ) {
				$explicit = self::sanitize_http_url( $args['feedback_url'] );
				if ( '' === $explicit ) {
					return '';
				}
				if ( $wl['active'] && self::is_default_feedback_host( $explicit ) ) {
					return self::resolve_whitelabel_override_url( $context, $wl );
				}
				return $explicit;
			}

			$has_custom_base = isset( $args['base_url'] ) && is_string( $args['base_url'] ) && '' !== trim( $args['base_url'] );
			$base_url        = $has_custom_base
				? self::sanitize_http_url( $args['base_url'] )
				: self::DEFAULT_FEEDBACK_BASE_URL;
			if ( '' === $base_url ) {
				return '';
			}

			if ( $wl['active'] && self::is_default_feedback_host( $base_url ) ) {
				return self::resolve_whitelabel_override_url( $context, $wl );
			}

			$category_id = self::DEFAULT_FEEDBACK_CATEGORY_ID;
			if ( isset( $args['category_id'] ) && is_numeric( $args['category_id'] ) && 0 < (int) $args['category_id'] ) {
				$category_id = (int) $args['category_id'];
			}

			$query = array(
				'category_id' => (string) $category_id,
			);

			$tags = self::normalize_feedback_tags( $args );
			if ( '' !== $tags ) {
				$query['tags'] = $tags;
			}

			if ( isset( $args['title'] ) && is_scalar( $args['title'] ) ) {
				$title = self::clean_single_line( (string) $args['title'] );
				if ( '' !== $title ) {
					$query['title'] = $title;
				}
			}

			$query['body'] = self::build_feedback_body( $args );

			$pairs = array();
			foreach ( $query as $key => $value ) {
				$pairs[] = rawurlencode( $key ) . '=' . rawurlencode( $value );
			}
			$sep = false === strpos( $base_url, '?' ) ? '?' : '&';
			return $base_url . $sep . implode( '&', $pairs );
		}

		/**
		 * Normalize feedback tags into a comma-separated string.
		 *
		 * @param array $args feedback_url() args.
		 * @return string
		 */
		private static function normalize_feedback_tags( array $args ) {
			if ( ! array_key_exists( 'tags', $args ) || null === $args['tags'] ) {
				return 'bug';
			}
			$raw = $args['tags'];
			if ( is_string( $raw ) ) {
				$raw = explode( ',', $raw );
			} elseif ( ! is_array( $raw ) ) {
				return '';
			}
			$clean = array();
			foreach ( $raw as $tag ) {
				if ( ! is_scalar( $tag ) ) {
					continue;
				}
				$tag = self::clean_single_line( (string) $tag );
				if ( '' !== $tag ) {
					$clean[] = $tag;
				}
			}
			return implode( ',', array_values( array_unique( $clean ) ) );
		}

		/**
		 * Build sanitized runtime environment body text (zero PII / domain / IP / keys).
		 *
		 * @param array $args feedback_url() args.
		 * @return string
		 */
		private static function build_feedback_body( array $args ) {
			$plugin_name    = isset( $args['plugin_name'] ) && is_scalar( $args['plugin_name'] )
				? self::clean_single_line( (string) $args['plugin_name'] )
				: '';
			$plugin_version = isset( $args['plugin_version'] ) && is_scalar( $args['plugin_version'] )
				? self::clean_single_line( (string) $args['plugin_version'] )
				: '';

			$env_lines = array();
			$plugin    = trim( $plugin_name . ( '' !== $plugin_version ? ' ' . $plugin_version : '' ) );
			if ( '' !== $plugin ) {
				$env_lines[] = '- 插件：' . $plugin;
			}
			$env_lines[] = '- WordPress：' . self::safe_wp_version();
			$env_lines[] = '- PHP：' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;
			$multisite   = function_exists( 'is_multisite' ) && is_multisite();
			$env_lines[] = '- 多站点：' . ( $multisite ? '是' : '否' );

			$body = "### 运行环境\n" . implode( "\n", $env_lines );

			$extra = self::normalize_extra_context( isset( $args['extra_context'] ) ? $args['extra_context'] : '' );
			if ( '' !== $extra ) {
				$body .= "\n\n### 补充信息\n" . $extra;
			}
			return $body;
		}

		/**
		 * Format optional extra_context (string or array) safely as plain text.
		 *
		 * @param mixed $extra Caller-provided context.
		 * @return string
		 */
		private static function normalize_extra_context( $extra ) {
			if ( is_string( $extra ) ) {
				$text = str_replace( array( "\r\n", "\r" ), "\n", $extra );
				$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text );
				return trim( (string) $text );
			}
			if ( ! is_array( $extra ) ) {
				return '';
			}
			$lines = array();
			foreach ( $extra as $key => $val ) {
				if ( ! is_scalar( $val ) ) {
					continue;
				}
				$clean_val = self::clean_single_line( (string) $val );
				if ( '' === $clean_val ) {
					continue;
				}
				if ( is_string( $key ) && '' !== trim( $key ) ) {
					$lines[] = '- ' . self::clean_single_line( $key ) . '：' . $clean_val;
				} else {
					$lines[] = '- ' . $clean_val;
				}
			}
			return implode( "\n", $lines );
		}

		/**
		 * Sanitized WordPress version string without reading site metadata.
		 *
		 * @return string
		 */
		private static function safe_wp_version() {
			global $wp_version;
			$ver = '';
			if ( isset( $wp_version ) && is_string( $wp_version ) ) {
				$ver = $wp_version;
			} elseif ( function_exists( 'get_bloginfo' ) ) {
				$ver = (string) get_bloginfo( 'version' );
			}
			$ver = (string) preg_replace( '/[^0-9A-Za-z._-]/', '', trim( $ver ) );
			return '' !== $ver ? $ver : 'unknown';
		}

		/**
		 * Detect active white-label state and any configured custom feedback URL override.
		 *
		 * @param array $args Args that may include 'whitelabel' and 'slug'.
		 * @return array{active: bool, override_url: string}
		 */
		private static function whitelabel_state( array $args ) {
			$active       = ! empty( $args['whitelabel'] );
			$override_url = '';

			if ( defined( 'WPCY_WHITELABEL' ) && (bool) constant( 'WPCY_WHITELABEL' ) ) {
				$active = true;
			}
			if ( defined( 'WPCY_WHITELABEL_HIDE_LINKS' ) && (bool) constant( 'WPCY_WHITELABEL_HIDE_LINKS' ) ) {
				$active = true;
			}
			if ( defined( 'WPCY_WHITELABEL_FEEDBACK_URL' ) && is_scalar( constant( 'WPCY_WHITELABEL_FEEDBACK_URL' ) ) ) {
				$const_url = trim( (string) constant( 'WPCY_WHITELABEL_FEEDBACK_URL' ) );
				if ( '' !== $const_url ) {
					$override_url = $const_url;
				}
			}

			if ( function_exists( 'apply_filters' ) ) {
				$slug     = isset( $args['slug'] ) && is_string( $args['slug'] ) ? sanitize_key( $args['slug'] ) : '';
				$defaults = array(
					'brand'         => '',
					'plugin_name'   => '',
					'help_url'      => '',
					'feedback_url'  => '',
					'docs_url'      => '',
					'news_feed_url' => '',
					'whitelabel'    => false,
					'hide_links'    => false,
				);
				$display  = apply_filters( 'wenpai_plugin_display', $defaults, $slug ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Shared suite-wide display contract defined in docs/design/wenpai-admin-ui/display-names.md.
				if ( is_array( $display ) ) {
					if ( ! empty( $display['whitelabel'] ) || ! empty( $display['hide_links'] ) ) {
						$active = true;
					}
					foreach ( array( 'brand', 'plugin_name', 'help_url', 'docs_url', 'news_feed_url' ) as $key ) {
						if ( isset( $display[ $key ] ) && is_string( $display[ $key ] ) && '' !== trim( $display[ $key ] ) ) {
							$active = true;
							break;
						}
					}
					if ( isset( $display['feedback_url'] ) && is_string( $display['feedback_url'] ) && '' !== trim( $display['feedback_url'] ) ) {
						$active = true;
						if ( '' === $override_url ) {
							$override_url = trim( $display['feedback_url'] );
						}
					}
				}
			}

			return array(
				'active'       => $active,
				'override_url' => $override_url,
			);
		}

		/**
		 * Resolve a non-default custom feedback URL when white-label mode is active.
		 *
		 * @param array $context Caller context that may include 'feedback_url'.
		 * @param array $wl      Result from whitelabel_state().
		 * @return string Validated non-default http(s) URL, or ''.
		 */
		private static function resolve_whitelabel_override_url( array $context, array $wl ) {
			$candidates = array();
			if ( isset( $context['feedback_url'] ) && is_string( $context['feedback_url'] ) ) {
				$candidates[] = $context['feedback_url'];
			}
			if ( isset( $wl['override_url'] ) && is_string( $wl['override_url'] ) ) {
				$candidates[] = $wl['override_url'];
			}
			foreach ( $candidates as $candidate ) {
				$clean = self::sanitize_http_url( $candidate );
				if ( '' !== $clean && ! self::is_default_feedback_host( $clean ) ) {
					return $clean;
				}
			}
			return '';
		}

		/**
		 * Validate and normalize an http(s) URL string.
		 *
		 * @param string $url Raw URL.
		 * @return string Clean URL or ''.
		 */
		private static function sanitize_http_url( $url ) {
			$url = trim( (string) $url );
			if ( '' === $url || ! preg_match( '#^https?://[^/\s]+#i', $url ) ) {
				return '';
			}
			if ( function_exists( 'esc_url_raw' ) ) {
				$url = esc_url_raw( $url, array( 'http', 'https' ) );
			}
			return preg_match( '#^https?://[^/\s]+#i', $url ) ? $url : '';
		}

		/**
		 * Whether a URL points to the default WenPai Cyberforums host.
		 *
		 * @param string $url URL to inspect.
		 * @return bool
		 */
		private static function is_default_feedback_host( $url ) {
			if ( ! preg_match( '~^https?://(?:[^@/?#]+@)?(\[[^\]]+\]|[^/:?#]+)~i', (string) $url, $m ) ) {
				return false;
			}
			$host = strtolower( rtrim( $m[1], '.' ) );
			return 'meta.cyberforums.com' === $host || '.meta.cyberforums.com' === substr( $host, -21 );
		}

		/**
		 * Strip control characters and collapse newlines for single-line text.
		 *
		 * @param string $value Input string.
		 * @return string
		 */
		private static function clean_single_line( $value ) {
			$value = preg_replace( '/[\x00-\x1F\x7F]+/', ' ', $value );
			return trim( (string) $value );
		}

		/**
		 * Screen-reader hint after a link that opens in a new tab (0.3.0+).
		 *
		 * @param array $args open() / close() args; new_tab_label overrides the text.
		 * @return string Escaped HTML, or '' when new_tab_label is ''.
		 */
		private static function new_tab_hint( $args ) {
			$label = isset( $args['new_tab_label'] ) ? (string) $args['new_tab_label'] : '（在新标签页打开）';
			return '' === $label ? '' : '<span class="screen-reader-text">' . esc_html( $label ) . '</span>';
		}
	}
}
