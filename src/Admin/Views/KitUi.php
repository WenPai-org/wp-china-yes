<?php
/**
 * Shared UI primitives, shell builder, and form controls for WPCY admin views.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin\Views;

use WenPai\ChinaYes\Admin\WhiteLabel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared server-rendered UI helpers for KitRenderer view modules.
 */
final class KitUi {

	/**
	 * Monochrome leaf SVG brand mark.
	 */
	public static function leaf_mark(): string {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/></svg>';
	}

	/**
	 * Build an admin page URL for top-level WPCY menu pages.
	 *
	 * @param string                $slug    Admin page slug.
	 * @param bool                  $network Whether this is a network admin page.
	 * @param array<string, string> $extra   Optional query args.
	 * @return string
	 */
	public static function page_url( string $slug, bool $network = false, array $extra = array() ): string {
		$args = array_merge( array( 'page' => $slug ), $extra );
		if ( $network && function_exists( 'network_admin_url' ) && function_exists( 'add_query_arg' ) ) {
			$script = 'admin';
			return (string) add_query_arg( $args, network_admin_url( $script . '.php' ) );
		}
		if ( class_exists( '\Wenpai_Admin_Menu', false ) && function_exists( 'admin_url' ) && function_exists( 'add_query_arg' ) ) {
			return (string) \Wenpai_Admin_Menu::url( $slug, $extra );
		}
		$q = '?page=' . rawurlencode( $slug );
		foreach ( $extra as $k => $v ) {
			$q .= '&' . rawurlencode( (string) $k ) . '=' . rawurlencode( (string) $v );
		}
		return $q;
	}

	/**
	 * Resolve companion plugin admin URL without hardcoding admin entry filenames at call sites.
	 *
	 * Guards against double-prefixing when `$relative` is already a full `http(s)://` admin URL.
	 *
	 * @param string $relative Relative wp-admin path or full URL.
	 * @return string
	 */
	public static function wp_admin_link( string $relative ): string {
		if ( 0 === strpos( $relative, 'https://' ) || 0 === strpos( $relative, 'http://' ) ) {
			return $relative;
		}
		return function_exists( 'admin_url' ) ? (string) admin_url( $relative ) : $relative;
	}

	/**
	 * Render kit SVG icon when available.
	 *
	 * @param string $name Icon name.
	 * @return string
	 */
	public static function kit_icon( string $name ): string {
		if ( class_exists( '\Wenpai_Admin_Icons', false ) ) {
			return (string) \Wenpai_Admin_Icons::svg( $name );
		}
		return '';
	}

	/**
	 * Read effective WordPress.org mirror mode from settings.
	 *
	 * @param array<string, mixed> $conn Connectivity slice.
	 * @return string
	 */
	public static function conn_mirror( array $conn ): string {
		return (string) ( $conn['wordpress_org'] ?? $conn['wporg_mirror'] ?? 'auto' );
	}

	/**
	 * Read effective public assets scope from settings.
	 *
	 * @param array<string, mixed> $settings Full settings snapshot.
	 * @param array<string, mixed> $conn     Connectivity slice.
	 * @return string
	 */
	public static function conn_scope( array $settings, array $conn ): string {
		if ( isset( $conn['public_assets'] ) && is_array( $conn['public_assets'] ) && isset( $conn['public_assets']['scope'] ) ) {
			return (string) $conn['public_assets']['scope'];
		}
		if ( isset( $settings['public_assets'] ) && is_array( $settings['public_assets'] ) && isset( $settings['public_assets']['scope'] ) ) {
			return (string) $settings['public_assets']['scope'];
		}
		return 'both';
	}

	/**
	 * Whether this site is currently bound to a WenPai Cloud Passport (`wpcy_site_identity`).
	 */
	public static function is_site_bound(): bool {
		if ( ! function_exists( 'get_option' ) ) {
			return false;
		}
		$identity = get_option( 'wpcy_site_identity', array() );
		return is_array( $identity )
			&& isset( $identity['binding'] )
			&& is_array( $identity['binding'] )
			&& 'bound' === (string) ( $identity['binding']['status'] ?? '' );
	}

	/**
	 * Build `Wenpai_Admin_Shell` args.
	 *
	 * @param string               $current  Active tab id.
	 * @param string               $version  Plugin version string.
	 * @param array<string, mixed> $links    External links.
	 * @param bool                 $network  Network admin context.
	 * @param array<string, mixed> $settings Settings snapshot (for white-label).
	 * @return array<string, mixed>
	 */
	public static function shell_args( string $current, string $version, array $links, bool $network, array $settings = array() ): array {
		$admin_url = static function ( string $slug, array $extra = array() ) use ( $network ): string {
			return self::page_url( $slug, $network, $extra );
		};

		$wl = WhiteLabel::resolve( $settings );

		if ( $network ) {
			$tabs = array(
				array(
					'id'        => 'settings',
					'label'     => __( '设置', 'wp-china-yes' ),
					'url'       => $admin_url( 'wpcy-connect' ),
					'icon_html' => self::kit_icon( 'settings' ),
				),
			);
		} else {
			$tabs = array(
				array(
					'id'        => 'overview',
					'label'     => __( '概览', 'wp-china-yes' ),
					'url'       => $admin_url( 'wpcy' ),
					'icon_html' => self::kit_icon( 'overview' ),
				),
				array(
					'id'        => 'settings',
					'label'     => __( '设置', 'wp-china-yes' ),
					'url'       => $admin_url( 'wpcy-connect' ),
					'icon_html' => self::kit_icon( 'settings' ),
				),
				array(
					'id'        => 'features',
					'label'     => __( '功能', 'wp-china-yes' ),
					'url'       => $admin_url( 'wpcy-features' ),
					'icon_html' => self::kit_icon( 'equalizer' ),
				),
				array(
					'id'        => 'services',
					'label'     => __( '服务', 'wp-china-yes' ),
					'url'       => $admin_url( 'wpcy-services' ),
					'icon_html' => self::kit_icon( 'sparkle' ),
				),
			);
			if ( ! ( ! empty( $wl['enabled'] ) && ! empty( $wl['hide_links'] ) ) ) {
				$tabs[] = array(
					'id'        => 'licenses',
					'label'     => __( '授权', 'wp-china-yes' ),
					'url'       => $admin_url( 'wpcy-licenses' ),
					'icon_html' => self::kit_icon( 'shield-check' ),
				);
			}
			$tabs[] = array(
				'id'        => 'diagnose',
				'label'     => __( '诊断', 'wp-china-yes' ),
				'url'       => $admin_url( 'wpcy-diagnose' ),
				'icon_html' => self::kit_icon( 'tools' ),
			);
		}

		$support_url = isset( $links['help'] ) ? (string) $links['help'] : 'https://wpcy.com/go/support';
		$defaults    = array(
			'brand'        => __( '文派叶子', 'wp-china-yes' ),
			'plugin_name'  => __( '文派叶子', 'wp-china-yes' ),
			'help_url'     => $network ? $support_url : $admin_url( 'wpcy', array( 'view' => 'help' ) ),
			'feedback_url' => $support_url,
			'docs_url'     => $support_url,
		);
		$display     = function_exists( 'apply_filters' )
			? apply_filters( 'wenpai_plugin_display', $defaults, 'wp-china-yes' )
			: WhiteLabel::filter_display( $defaults, $settings );
		if ( ! is_array( $display ) ) {
			$display = $defaults;
		}
		$brand_name   = ! empty( $display['brand'] ) ? (string) $display['brand'] : $defaults['brand'];
		$help_url     = ! empty( $display['help_url'] ) ? (string) $display['help_url'] : $defaults['help_url'];
		$feedback_url = ! empty( $display['feedback_url'] ) ? (string) $display['feedback_url'] : $defaults['feedback_url'];
		$docs_url     = ! empty( $display['docs_url'] ) ? (string) $display['docs_url'] : $defaults['docs_url'];

		$mark_html = self::leaf_mark();
		if ( $wl['enabled'] && $wl['hide_logo'] ) {
			$mark_html = '';
		} elseif ( $wl['enabled'] && '' !== $wl['logo_url'] ) {
			$mark_html = '<img class="wpcy-mark-img" src="' . esc_url( $wl['logo_url'] ) . '" alt="' . esc_attr( $brand_name ) . '" width="22" height="22">';
		}

		$args = array(
			'slug'       => 'wpcy',
			'name'       => $brand_name,
			'mark_html'  => $mark_html,
			'home_url'   => $admin_url( $network ? 'wpcy-connect' : 'wpcy' ),
			'current'    => $current,
			'tabs'       => $tabs,
			'version'    => $brand_name . ' ' . $version,
			'main_class' => 'wenpai-measure-wide',
		);

		$show_links = ! ( $wl['enabled'] && $wl['hide_links'] ) || '' !== $wl['help_url'] || '' !== $wl['feedback_url'] || '' !== $wl['docs_url'];
		if ( $show_links ) {
			if ( ! ( $wl['enabled'] && $wl['hide_links'] && '' === $wl['help_url'] ) ) {
				$args['help_url']       = $help_url;
				$args['help_label']     = __( '帮助', 'wp-china-yes' );
				$args['help_icon_html'] = self::kit_icon( 'help' );
			}
			if ( ! ( $wl['enabled'] && $wl['hide_links'] && '' === $wl['feedback_url'] ) ) {
				$args['feedback_url']       = $feedback_url;
				$args['feedback_label']     = __( '反馈', 'wp-china-yes' );
				$args['feedback_icon_html'] = self::kit_icon( 'feedback' );
			}
			if ( ! ( $wl['enabled'] && $wl['hide_links'] && '' === $wl['docs_url'] ) ) {
				$args['doc_url']   = $docs_url;
				$args['doc_label'] = __( '文档与支持', 'wp-china-yes' );
			}
		}

		return $args;
	}

	/**
	 * Shared scene title & description pairs for Settings and Onboarding Wizard.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function scene_descriptions(): array {
		return array(
			'domestic'    => array(
				__( '国内站', 'wp-china-yes' ),
				__( '服务器与访客均在大陆，前后台全量启用国内加速。', 'wp-china-yes' ),
			),
			'crossborder' => array(
				__( '跨境站', 'wp-china-yes' ),
				__( '海外访客保持直连保障支付顺畅，仅加速国内运营后台。', 'wp-china-yes' ),
			),
			'mixed'       => array(
				__( '混合站', 'wp-china-yes' ),
				__( '兼顾海内外访客，默认加速更新与后台，可按需细分线路。', 'wp-china-yes' ),
			),
		);
	}

	/**
	 * Localized label for site profile.
	 *
	 * @param string $profile Profile slug.
	 * @return string
	 */
	public static function profile_label( string $profile ): string {
		$map = array(
			'domestic'    => __( '国内站', 'wp-china-yes' ),
			'crossborder' => __( '跨境站', 'wp-china-yes' ),
			'inbound'     => __( '跨境入境站', 'wp-china-yes' ),
			'mixed'       => __( '混合站', 'wp-china-yes' ),
		);
		return $map[ $profile ] ?? $profile;
	}

	/**
	 * Toggle switch HTML using kit switch markup (instant save).
	 *
	 * @param string $name     Input name.
	 * @param bool   $checked  Whether checked.
	 * @param bool   $disabled Whether disabled.
	 * @return string
	 */
	public static function toggle_control( string $name, bool $checked, bool $disabled ): string {
		return self::switch_control( $name, $checked, $disabled, array( 'data-wpcy-instant' => '1' ) );
	}

	/**
	 * Generic kit pill switch HTML with optional data attributes.
	 *
	 * @param string                $name       Input name.
	 * @param bool                  $checked    Whether checked.
	 * @param bool                  $disabled   Whether disabled.
	 * @param array<string, string> $attrs      Extra input attributes.
	 * @param bool                  $show_label Whether to render visible label text instead of screen-reader text.
	 * @return string
	 */
	public static function switch_control( string $name, bool $checked, bool $disabled, array $attrs = array(), bool $show_label = true ): string {
		$dis   = $disabled ? ' disabled' : '';
		$chk   = $checked ? ' checked' : '';
		$txt   = $checked ? __( '已开启', 'wp-china-yes' ) : __( '未开启', 'wp-china-yes' );
		$extra = '';
		foreach ( $attrs as $k => $v ) {
			$extra .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
		}
		$label = $show_label
			? '<span class="wenpai-switch__label">' . esc_html( $txt ) . '</span>'
			: '<span class="screen-reader-text">' . esc_html( $txt ) . '</span>';
		return '<span class="wenpai-switch">'
			. '<input type="checkbox" class="wenpai-switch__input" name="' . esc_attr( $name ) . '" value="1"' . $extra . $chk . $dis . '>'
			. '<span class="wenpai-switch__track" aria-hidden="true"></span>'
			. $label
			. '</span>';
	}

	/**
	 * Two-column text/url input field row (`.wenpai-field.field.wenpai-field--row`).
	 *
	 * @param string $name        Input name.
	 * @param string $type        Input type (`text` or `url`).
	 * @param string $label       Field label.
	 * @param string $desc        Field description.
	 * @param string $value       Current value.
	 * @param string $placeholder Placeholder text.
	 * @param int    $max_length  Max length.
	 * @param bool   $disabled    Whether disabled.
	 * @return string
	 */
	public static function field_row_input( string $name, string $type, string $label, string $desc, string $value, string $placeholder, int $max_length, bool $disabled ): string {
		$id  = 'wpcy-f-' . $name;
		$dis = $disabled ? ' disabled' : '';
		$cls = 'wenpai-field field wenpai-field--row' . ( $disabled ? ' is-disabled' : '' );
		$h   = '<div class="' . $cls . '">';
		$h  .= '<div class="field-label"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></div>';
		$h  .= '<div class="field-ctl"><input id="' . esc_attr( $id ) . '" type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" maxlength="' . (int) $max_length . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . $dis . '>';
		if ( '' !== $desc ) {
			$h .= '<p class="wenpai-field__desc">' . esc_html( $desc ) . '</p>';
		}
		$h .= '</div></div>';
		return $h;
	}

	/**
	 * Two-column select field row (`.field`).
	 *
	 * @param string                $name     Input name.
	 * @param string                $label    Field label.
	 * @param string                $desc     Field description.
	 * @param array<string, string> $options  Value => label map.
	 * @param string                $current  Current value.
	 * @param bool                  $disabled Whether disabled.
	 * @return string
	 */
	public static function field_row_select( string $name, string $label, string $desc, array $options, string $current, bool $disabled ): string {
		$id  = 'wpcy-f-' . $name;
		$dis = $disabled ? ' disabled' : '';
		$cls = 'wenpai-field field wenpai-field--row' . ( $disabled ? ' is-disabled' : '' );
		$h   = '<div class="' . $cls . '">';
		$h  .= '<div class="field-label"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></div>';
		$h  .= '<div class="field-ctl"><select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $dis . '>';
		foreach ( $options as $val => $text ) {
			$sel = $current === $val ? ' selected' : '';
			$h  .= '<option value="' . esc_attr( $val ) . '"' . $sel . '>' . esc_html( $text ) . '</option>';
		}
		$h .= '</select>';
		if ( '' !== $desc ) {
			$h .= '<p class="wenpai-field__desc">' . esc_html( $desc ) . '</p>';
		}
		$h .= '</div></div>';
		return $h;
	}

	/**
	 * Two-column checkbox field row (`.field`).
	 *
	 * @param string $name     Input name.
	 * @param string $label    Field label.
	 * @param string $desc     Checkbox label text.
	 * @param bool   $checked  Whether checked.
	 * @param bool   $disabled Whether disabled.
	 * @return string
	 */
	public static function field_row_checkbox( string $name, string $label, string $desc, bool $checked, bool $disabled ): string {
		$id  = 'wpcy-f-' . $name;
		$dis = $disabled ? ' disabled' : '';
		$chk = $checked ? ' checked' : '';
		$cls = 'wenpai-field field wenpai-field--row' . ( $disabled ? ' is-disabled' : '' );
		$h   = '<div class="' . $cls . '">';
		$h  .= '<div class="field-label"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></div>';
		$h  .= '<div class="field-ctl"><label class="opt"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . $chk . $dis . '><span>' . esc_html( $desc ) . '</span></label></div></div>';
		return $h;
	}

	/**
	 * Initial 7-day SVG chart frame using kit CSS tokens (updated by JS once `/stats` loads).
	 */
	public static function empty_trend_svg(): string {
		$labels  = array( '6天前', '5天前', '4天前', '3天前', '前天', '昨天', '今天' );
		$circles = '';
		$texts   = '';
		for ( $i = 0; $i < 7; $i++ ) {
			$x        = (int) round( 44 + $i * ( ( 612 - 44 ) / 6 ) );
			$circles .= '<circle cx="' . $x . '" cy="148" r="3.5" fill="var(--wenpai-accent)"/>';
			$texts   .= '<text x="' . $x . '" y="168" text-anchor="middle">' . esc_html( $labels[ $i ] ) . '</text>';
		}
		return '<svg data-wenpai-art viewBox="0 0 640 180" width="100%" height="180" role="img" aria-label="' . esc_attr__( '近 7 日加速趋势', 'wp-china-yes' ) . '">'
			. '<g stroke="var(--wenpai-line-soft)" stroke-width="1">'
			. '<line x1="44" y1="26" x2="612" y2="26"/><line x1="44" y1="87" x2="612" y2="87"/><line x1="44" y1="148" x2="612" y2="148"/>'
			. '</g>'
			. '<polyline fill="none" stroke="var(--wenpai-accent)" stroke-width="2" points="44,148 612,148"/>'
			. $circles
			. '<g fill="var(--wenpai-ink-3)" font-size="12">'
			. '<text x="36" y="30" text-anchor="end">10</text>'
			. '<text x="36" y="91" text-anchor="end">5</text>'
			. '<text x="36" y="152" text-anchor="end">0</text>'
			. $texts
			. '</g></svg>';
	}
}
