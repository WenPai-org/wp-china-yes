<?php
/**
 * Curated built-in CSS rules for persistent third-party promotional banners in wp-admin.
 *
 * Strictly protects WordPress core updates, security alerts, Site Health, and generic notice classes.
 * Zero remote rule fetching, zero per-request DB writes.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin\NoticeControl;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Built-in promotional banner selector registry and safety sanitizer.
 */
final class PromoRules {

	/**
	 * WordPress generic notice tokens that must never be hidden globally.
	 *
	 * @var list<string>
	 */
	public const GENERIC_CLASSES = array(
		'notice',
		'updated',
		'error',
		'update-nag',
		'is-dismissible',
		'inline',
		'settings-error',
		'notice-error',
		'notice-warning',
		'notice-info',
		'notice-success',
	);

	/**
	 * Core / operational class or id tokens that must never be targeted.
	 *
	 * Checked against extracted CSS class/id tokens (not raw substring of plugin prefixes),
	 * so plugin-specific classes like `.wpforms-review-notice` remain valid while
	 * `.update-nag`, `#update-core`, or `.site-health-notice` are rejected.
	 *
	 * @var list<string>
	 */
	public const PROTECTED_TOKEN_PREFIXES = array(
		'update-nag',
		'update-core',
		'core-update',
		'site-health',
		'health-check',
		'wp-site-health',
		'wp-core-ui',
		'security-alert',
		'setting-error',
		'settings-error',
	);

	/**
	 * Curated built-in selectors for well-known persistent third-party promotional banners.
	 *
	 * Kept intentionally lean and focused on high-confidence upsell/review banners that often
	 * render outside standard `admin_notices` or stay pinned in plugin sidebars.
	 *
	 * @return list<array{id: string, plugin: string, selector: string, label: string}>
	 */
	public static function builtin_rules(): array {
		return array(
			array(
				'id'       => 'woocommerce-connect-promo',
				'plugin'   => 'woocommerce',
				'selector' => '.wc-connect-banner',
				'label'    => 'WooCommerce 扩展推广横幅',
			),
			array(
				'id'       => 'yoast-license-promo',
				'plugin'   => 'wordpress-seo',
				'selector' => '.yst-license-notice',
				'label'    => 'Yoast SEO 高级版推销条',
			),
			array(
				'id'       => 'yoast-sidebar-upsell',
				'plugin'   => 'wordpress-seo',
				'selector' => '.yoast_premium_upsell',
				'label'    => 'Yoast SEO 侧栏升级广告',
			),
			array(
				'id'       => 'yoast-section-upsell',
				'plugin'   => 'wordpress-seo',
				'selector' => '.yoast-settings-section-upsell',
				'label'    => 'Yoast SEO 设置区推广卡',
			),
			array(
				'id'       => 'elementor-extended-promo',
				'plugin'   => 'elementor',
				'selector' => '.e-notice--extended',
				'label'    => 'Elementor Pro 扩展推广条',
			),
			array(
				'id'       => 'elementor-go-pro-banner',
				'plugin'   => 'elementor',
				'selector' => '.e-admin-top-bar__secondary-area .e-admin-top-bar__bar-button--go-pro',
				'label'    => 'Elementor 顶栏升级按钮',
			),
			array(
				'id'       => 'wpforms-review-promo',
				'plugin'   => 'wpforms-lite',
				'selector' => '.wpforms-review-notice',
				'label'    => 'WPForms 评分推广条',
			),
			array(
				'id'       => 'wpforms-upgrade-bar',
				'plugin'   => 'wpforms-lite',
				'selector' => '#wpforms-header-temp',
				'label'    => 'WPForms 顶部升级横幅',
			),
			array(
				'id'       => 'rank-math-review-promo',
				'plugin'   => 'seo-by-rank-math',
				'selector' => '.rank-math-review-notice',
				'label'    => 'Rank Math 评分与升级提示',
			),
			array(
				'id'       => 'aioseo-review-promo',
				'plugin'   => 'all-in-one-seo-pack',
				'selector' => '.aioseo-review-plugin-cta',
				'label'    => 'All in One SEO 评分推广条',
			),
			array(
				'id'       => 'monsterinsights-review-promo',
				'plugin'   => 'google-analytics-for-wordpress',
				'selector' => '.monsterinsights-notice-review',
				'label'    => 'MonsterInsights 评分与升级提示',
			),
			array(
				'id'       => 'optinmonster-review-promo',
				'plugin'   => 'optinmonster',
				'selector' => '.om-review-notice',
				'label'    => 'OptinMonster 评分推广条',
			),
			array(
				'id'       => 'wp-mail-smtp-pro-banner',
				'plugin'   => 'wp-mail-smtp',
				'selector' => '#wp-mail-smtp-pro-banner',
				'label'    => 'WP Mail SMTP 顶部升级横幅',
			),
			array(
				'id'       => 'wp-mail-smtp-review',
				'plugin'   => 'wp-mail-smtp',
				'selector' => '.wp-mail-smtp-review-notice',
				'label'    => 'WP Mail SMTP 评分推广条',
			),
			array(
				'id'       => 'smush-pro-upsell',
				'plugin'   => 'wp-smushit',
				'selector' => '#smush-box-pro-features',
				'label'    => 'Smush 高级版功能广告盒',
			),
			array(
				'id'       => 'wpmudev-cross-sell',
				'plugin'   => 'wpmudev',
				'selector' => '#sui-cross-sell-footer',
				'label'    => 'WPMU DEV 交叉推广页脚',
			),
			array(
				'id'       => 'metaslider-optin',
				'plugin'   => 'ml-slider',
				'selector' => '#metaslider-optin-notice',
				'label'    => 'MetaSlider 推广订阅条',
			),
			array(
				'id'       => 'extendify-announcement',
				'plugin'   => 'extendify',
				'selector' => '#extendifysdk_announcement',
				'label'    => 'Extendify 推广横幅',
			),
			array(
				'id'       => 'jetpack-jitm-upgrade',
				'plugin'   => 'jetpack',
				'selector' => '.jitm-banner.is-upgrade-premium',
				'label'    => 'Jetpack 付费升级横幅',
			),
			array(
				'id'       => 'freemius-promo',
				'plugin'   => 'freemius',
				'selector' => '.fs-notice.promotion',
				'label'    => 'Freemius 通用促销横幅',
			),
			array(
				'id'       => 'neve-upsell',
				'plugin'   => 'neve',
				'selector' => '.neve-notice-upsell',
				'label'    => 'Neve 主题升级横幅',
			),
			array(
				'id'       => 'wp-rocket-imagify-upsell',
				'plugin'   => 'wp-rocket',
				'selector' => '.rocket-promo-banner',
				'label'    => 'WP Rocket 促销横幅',
			),
		);
	}

	/**
	 * Resolve active sanitized CSS selectors (built-in rules + optional filter extensions).
	 *
	 * @return list<string>
	 */
	public static function active_selectors(): array {
		$raw = array();
		foreach ( self::builtin_rules() as $rule ) {
			$raw[] = $rule['selector'];
		}

		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'wpcy_promo_banner_selectors', $raw );
			if ( is_array( $filtered ) ) {
				$raw = $filtered;
			}
		}

		$selectors = array();
		foreach ( $raw as $candidate ) {
			if ( ! is_string( $candidate ) ) {
				continue;
			}
			$clean = self::sanitize_selector( $candidate );
			if ( '' !== $clean ) {
				$selectors[] = $clean;
			}
		}

		return array_values( array_unique( $selectors ) );
	}

	/**
	 * Build a single CSS rule string hiding all active promotional selectors.
	 */
	public static function build_css(): string {
		$selectors = self::active_selectors();
		if ( array() === $selectors ) {
			return '';
		}

		return implode( ',', $selectors ) . '{display:none!important;}';
	}

	/**
	 * Validate and sanitize a CSS selector against injection and core-notice red lines.
	 *
	 * @param string $selector Raw CSS selector.
	 */
	public static function sanitize_selector( string $selector ): string {
		$selector = trim( $selector );
		if ( '' === $selector || strlen( $selector ) > 200 ) {
			return '';
		}

		$first = $selector[0];
		if ( '.' !== $first && '#' !== $first ) {
			return '';
		}

		// Disallow universal selector, comma chaining (must be one selector per entry), or CSS escapes/braces.
		if ( false !== strpos( $selector, '*' ) || false !== strpos( $selector, ',' ) ) {
			return '';
		}

		if ( 1 !== preg_match( '/^[A-Za-z0-9_\-.#\[\]="\'\s>+~:()]+$/', $selector ) ) {
			return '';
		}

		if ( self::is_protected_selector( $selector ) ) {
			return '';
		}

		return $selector;
	}

	/**
	 * Whether a selector targets a WordPress core update, security, Site Health, or generic notice.
	 *
	 * @param string $selector CSS selector.
	 */
	public static function is_protected_selector( string $selector ): bool {
		$normalized = strtolower( str_replace( '_', '-', $selector ) );

		$count = preg_match_all( '/[.#]([a-z0-9-]+)/', $normalized, $matches );
		if ( ! is_int( $count ) || $count < 1 ) {
			return true;
		}

		$tokens           = $matches[1];
		$has_specific_tok = false;

		foreach ( $tokens as $token ) {
			foreach ( self::PROTECTED_TOKEN_PREFIXES as $prefix ) {
				if ( $token === $prefix || 0 === strpos( $token, $prefix . '-' ) ) {
					return true;
				}
			}

			if ( 'core' === $token || 'security' === $token ) {
				return true;
			}

			if ( ! in_array( $token, self::GENERIC_CLASSES, true ) && 0 !== strpos( $token, 'notice-' ) ) {
				$has_specific_tok = true;
			}
		}

		// Reject selectors made purely of generic WordPress classes (e.g. `.notice.notice-warning`).
		return ! $has_specific_tok;
	}
}
