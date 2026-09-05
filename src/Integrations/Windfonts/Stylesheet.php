<?php
/**
 * Windfonts CSS URL builder and head markup. Port of Service\Fonts.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Integrations\Windfonts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds app.windfonts.com/api/css URLs and prints link tags without crossorigin.
 */
final class Stylesheet {

	/**
	 * 3.x CSS API base. Smoke asserts family= and subset= on this host.
	 *
	 * @since 4.0.0
	 */
	public const CSS_BASE = 'https://app.windfonts.com/api/css';

	/**
	 * Preconnect host from Service\Fonts::load_windfonts.
	 *
	 * @since 4.0.0
	 */
	public const PRECONNECT = 'https://cn.windfonts.com';

	/**
	 * 3.x license mark. Must stay in the output.
	 *
	 * @since 4.0.0
	 */
	public const LICENSE_COMMENT = '<!-- 此中文网页字体由文风字体（Windfonts）免费提供，您可以自由引用，请务必保留此授权许可标注 https://wenfeng.org/license -->';

	/**
	 * Character-set subsets accepted by the current CSS API.
	 *
	 * @since 4.0.0
	 * @var list<string>
	 */
	public const SUBSETS = array( 'en', 'zh', 'zh-common', 'full' );

	/**
	 * Build the stylesheet URL. Port of Fonts::build_font_css_url.
	 *
	 * Invalid subsets are omitted (3.x). Missing subset defaults to full.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $font One integrations.windfonts.fonts item.
	 */
	public function css_url( array $font ): string {
		$params = array();
		if ( isset( $font['family'] ) && is_string( $font['family'] ) && '' !== $font['family'] ) {
			$params['family'] = $font['family'];
		}

		$subset = isset( $font['subset'] ) ? (string) $font['subset'] : 'full';
		if ( in_array( $subset, self::SUBSETS, true ) ) {
			$params['subset'] = $subset;
		}

		if ( ! empty( $font['lang'] ) && is_string( $font['lang'] ) ) {
			$params['lang'] = $font['lang'];
		}

		if ( function_exists( 'add_query_arg' ) ) {
			return (string) add_query_arg( $params, self::CSS_BASE );
		}

		return self::CSS_BASE . '?' . http_build_query( $params, '', '&' );
	}

	/**
	 * Family name for CSS font-family. Port of Fonts::extract_font_family_name.
	 *
	 * @since 4.0.0
	 *
	 * @param string $family_param family query value, possibly with :wght@… suffix.
	 */
	public function family_name( string $family_param ): string {
		$colon = strpos( $family_param, ':' );
		if ( false !== $colon ) {
			return substr( $family_param, 0, $colon );
		}

		return $family_param;
	}

	/**
	 * Head HTML: preconnect, license comment, one stylesheet+style per enabled font.
	 *
	 * No crossorigin attribute (provider has no ACAO). Port of Fonts::load_windfonts.
	 *
	 * @since 4.0.0
	 *
	 * @param array<int, mixed> $fonts integrations.windfonts.fonts list.
	 */
	public function render( array $fonts ): string {
		$preconnect = function_exists( 'esc_url' ) ? esc_url( self::PRECONNECT ) : self::PRECONNECT;
		$html       = '        <link rel="preconnect" href="' . $preconnect . '">' . "\n";
		$html      .= '        ' . self::LICENSE_COMMENT . "\n";

		$loaded = array();
		foreach ( $fonts as $font ) {
			if ( ! is_array( $font ) ) {
				continue;
			}
			if ( empty( $font['enable'] ) ) {
				continue;
			}
			if ( empty( $font['family'] ) || ! is_string( $font['family'] ) ) {
				continue;
			}

			$css_url = $this->css_url( $font );
			if ( in_array( $css_url, $loaded, true ) ) {
				continue;
			}

			$href        = function_exists( 'esc_url' ) ? esc_url( $css_url ) : $css_url;
			$font_family = $this->family_name( $font['family'] );
			$selector    = isset( $font['selector'] ) ? (string) $font['selector'] : '';
			$selector    = $this->escape_selector( $selector );
			$style       = isset( $font['style'] ) ? (string) $font['style'] : 'normal';
			$weight      = isset( $font['weight'] ) ? (string) $font['weight'] : '400';
			$link        = sprintf(
				"            <link rel=\"stylesheet\" type=\"text/css\" href=\"%s\">\n            <style>\n            %s {\n                font-style: %s;\n                font-weight: %s;\n                font-family: '%s',sans-serif!important;\n            }\n            </style>\n", // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- 3.x smoke requires a raw <link rel="stylesheet"> without crossorigin; wp_enqueue_style cannot express that.
				$href,
				$selector,
				$style,
				$weight,
				$font_family
			);
			$html       .= $link;
			$loaded[]    = $css_url;
		}

		return $html;
	}

	/**
	 * Strip tags and CSS-breaking characters from a selector.
	 *
	 * @since 4.0.0
	 *
	 * @param string $selector Raw selector from settings.
	 */
	private function escape_selector( string $selector ): string {
		$selector = htmlspecialchars_decode( $selector );
		if ( function_exists( 'wp_strip_all_tags' ) ) {
			$selector = wp_strip_all_tags( $selector );
		} else {
			$stripped = preg_replace( '/<[^>]*>/', '', $selector );
			$selector = is_string( $stripped ) ? $stripped : '';
		}

		return str_replace( array( '{', '}', '<', '>', '"', "'" ), '', $selector );
	}
}
