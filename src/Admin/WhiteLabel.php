<?php
/**
 * Suite-wide white-label configuration resolver & `wenpai_plugin_display` hook provider.
 *
 * Implements the cross-plugin display-name contract (`docs/design/wenpai-admin-ui/display-names.md`):
 * WPCY registers `wenpai_plugin_display` once so every WenPai plugin (WPSlug, WPSSS, WPMind,
 * WPAvatar, WPFonts, WPComments, WPCC, WPICP, WPBridge, WPStatic, WPPhoto, and WPCY itself)
 * inherits white-label brand name, help URL, feedback URL, and docs URL without touching
 * plugin headers, textdomains, option prefixes, REST namespaces, or Update URIs.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin;

use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Services\Entitlements\EntitlementsModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves white-label state and registers suite-wide display filters.
 */
final class WhiteLabel {

	/**
	 * WenPai ecosystem plugin directory prefixes eligible for `all_plugins` author display override.
	 *
	 * @var list<string>
	 */
	private const SUITE_PREFIXES = array(
		'wp-china-yes/',
		'wpbridge/',
		'wpstatic/',
		'wpavatar/',
		'wpfonts/',
		'wpphoto/',
		'wpsss/',
		'wpmind/',
		'wpicp/',
		'wpslug/',
		'wp-chinese-converter/',
		'wpcomments/',
	);

	/**
	 * Register suite-wide white-label hooks once.
	 *
	 * @param Repository $repository Settings repository.
	 */
	public static function register( Repository $repository ): void {
		if ( ! function_exists( 'add_filter' ) ) {
			return;
		}

		add_filter(
			'wenpai_plugin_display',
			static function ( $display, $plugin_slug = '' ) use ( $repository ) {
				unset( $plugin_slug );
				if ( ! is_array( $display ) ) {
					$display = array();
				}
				return self::filter_display( $display, $repository->all() );
			},
			20,
			2
		);

		// Legacy bridge for WPSlug before it migrates to `wenpai_plugin_display`.
		add_filter(
			'wpslug_brand_name',
			static function ( $brand ) use ( $repository ) {
				$wl = self::resolve( $repository->all() );
				if ( $wl['enabled'] && '' !== $wl['brand_name'] ) {
					return $wl['brand_name'];
				}
				return $brand;
			},
			20
		);

		// Display-only author override on plugins.php (never alters plugin file headers or Update URI).
		add_filter(
			'all_plugins',
			static function ( $plugins ) use ( $repository ) {
				if ( ! is_array( $plugins ) ) {
					return $plugins;
				}
				$wl = self::resolve( $repository->all() );
				if ( ! $wl['enabled'] || '' === $wl['brand_name'] ) {
					return $plugins;
				}
				foreach ( $plugins as $file => $data ) {
					if ( ! is_string( $file ) || ! is_array( $data ) ) {
						continue;
					}
					if ( ! self::is_suite_plugin( $file ) ) {
						continue;
					}
					$plugins[ $file ]['Author']     = $wl['brand_name'];
					$plugins[ $file ]['AuthorName'] = $wl['brand_name'];
					if ( '' !== $wl['docs_url'] ) {
						$plugins[ $file ]['AuthorURI'] = $wl['docs_url'];
						$plugins[ $file ]['PluginURI'] = $wl['docs_url'];
					}
				}
				return $plugins;
			},
			20
		);
	}

	/**
	 * Apply white-label overrides to a `wenpai_plugin_display` array.
	 * Unfilled keys remain at each plugin's own defaults.
	 *
	 * @param array<string, string> $display  Default display map from the calling plugin.
	 * @param array<string, mixed>  $settings Settings snapshot.
	 * @return array<string, string>
	 */
	public static function filter_display( array $display, array $settings ): array {
		$wl = self::resolve( $settings );
		if ( ! $wl['enabled'] ) {
			return $display;
		}
		if ( '' !== $wl['brand_name'] ) {
			$display['brand'] = $wl['brand_name'];
		}
		if ( '' !== $wl['help_url'] ) {
			$display['help_url'] = $wl['help_url'];
		}
		if ( '' !== $wl['feedback_url'] ) {
			$display['feedback_url'] = $wl['feedback_url'];
		}
		if ( '' !== $wl['docs_url'] ) {
			$display['docs_url'] = $wl['docs_url'];
		}
		if ( '' !== $wl['news_feed_url'] ) {
			$display['news_feed_url'] = $wl['news_feed_url'];
		}
		return $display;
	}

	/**
	 * Paid entitlement service slugs that unlock suite-wide white-label.
	 *
	 * @var list<string>
	 */
	private const ENTITLEMENT_SLUGS = array( 'oem', 'whitelabel' );

	/**
	 * Entitlement statuses considered active.
	 *
	 * @var list<string>
	 */
	private const ACTIVE_STATUSES = array( 'active', 'ok', 'granted' );

	/**
	 * Whether this site holds an active paid white-label entitlement (or constant lock).
	 *
	 * Reads the 1h fresh / 72h stale `EntitlementsModule` cache so transient network blips
	 * never revert a licensed client site back to default branding.
	 */
	public static function is_entitled(): bool {
		if ( defined( 'WPCY_WHITELABEL' ) && (bool) constant( 'WPCY_WHITELABEL' ) ) {
			return true;
		}
		if ( function_exists( 'apply_filters' ) && (bool) apply_filters( 'wpcy_whitelabel_entitled', false ) ) {
			return true;
		}
		if ( ! function_exists( 'get_transient' ) ) {
			return false;
		}

		$items = get_transient( EntitlementsModule::TRANSIENT_FRESH );
		if ( ! is_array( $items ) ) {
			$items = get_transient( EntitlementsModule::TRANSIENT_STALE );
		}
		if ( ! is_array( $items ) ) {
			return false;
		}

		foreach ( $items as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$service = isset( $row['service'] ) && is_string( $row['service'] ) ? strtolower( trim( $row['service'] ) ) : '';
			$status  = isset( $row['status'] ) && is_string( $row['status'] ) ? strtolower( trim( $row['status'] ) ) : '';
			if ( in_array( $service, self::ENTITLEMENT_SLUGS, true ) && in_array( $status, self::ACTIVE_STATUSES, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve effective white-label state from settings snapshot and optional PHP constants.
	 *
	 * Supported wp-config.php constants:
	 * - WPCY_WHITELABEL              (bool)   Force-enable and lock white-label in admin UI.
	 * - WPCY_WHITELABEL_NAME         (string) Override suite brand name.
	 * - WPCY_WHITELABEL_HELP_URL     (string) Override suite help URL.
	 * - WPCY_WHITELABEL_FEEDBACK_URL (string) Override suite feedback URL.
	 * - WPCY_WHITELABEL_DOCS_URL     (string) Override suite docs URL.
	 * - WPCY_WHITELABEL_LOGO         (string) Override logo image URL.
	 * - WPCY_WHITELABEL_HIDE_LOGO    (bool)   Hide default brand mark.
	 * - WPCY_WHITELABEL_HIDE_LINKS   (bool)   Hide uninstalled extension promos & default WenPai links.
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 * @return array{entitled: bool, enabled: bool, locked: bool, brand_name: string, help_url: string, feedback_url: string, docs_url: string, news_feed_url: string, logo_url: string, hide_logo: bool, hide_links: bool}
	 */
	public static function resolve( array $settings ): array {
		$raw = isset( $settings['admin']['whitelabel'] ) && is_array( $settings['admin']['whitelabel'] )
			? $settings['admin']['whitelabel']
			: array();

		$entitled = self::is_entitled();
		$locked   = defined( 'WPCY_WHITELABEL' ) && (bool) constant( 'WPCY_WHITELABEL' );
		$enabled  = $entitled && ( $locked || ! empty( $raw['enabled'] ) );

		$brand_name    = self::resolve_string( $raw, 'brand_name', 'WPCY_WHITELABEL_NAME' );
		$help_url      = self::resolve_string( $raw, 'help_url', 'WPCY_WHITELABEL_HELP_URL' );
		$feedback_url  = self::resolve_string( $raw, 'feedback_url', 'WPCY_WHITELABEL_FEEDBACK_URL' );
		$docs_url      = self::resolve_string( $raw, 'docs_url', 'WPCY_WHITELABEL_DOCS_URL' );
		$news_feed_url = self::resolve_string( $raw, 'news_feed_url', 'WPCY_WHITELABEL_NEWS_URL' );
		$logo_url      = self::resolve_string( $raw, 'logo_url', 'WPCY_WHITELABEL_LOGO' );

		$hide_logo = ! empty( $raw['hide_logo'] );
		if ( defined( 'WPCY_WHITELABEL_HIDE_LOGO' ) ) {
			$hide_logo = (bool) constant( 'WPCY_WHITELABEL_HIDE_LOGO' );
		}

		$hide_links = ! empty( $raw['hide_links'] );
		if ( defined( 'WPCY_WHITELABEL_HIDE_LINKS' ) ) {
			$hide_links = (bool) constant( 'WPCY_WHITELABEL_HIDE_LINKS' );
		} elseif ( $locked ) {
			$hide_links = true;
		}

		return array(
			'entitled'      => $entitled,
			'enabled'       => $enabled,
			'locked'        => $locked,
			'brand_name'    => $brand_name,
			'help_url'      => $help_url,
			'feedback_url'  => $feedback_url,
			'docs_url'      => $docs_url,
			'news_feed_url' => $news_feed_url,
			'logo_url'      => $logo_url,
			'hide_logo'     => $hide_logo,
			'hide_links'    => $hide_links,
		);
	}

	/**
	 * Effective brand display name (falls back to 文派叶子 when white-label is off or empty).
	 *
	 * @param array<string, mixed> $settings Settings snapshot.
	 */
	public static function brand_name( array $settings ): string {
		$wl = self::resolve( $settings );
		if ( $wl['enabled'] && '' !== $wl['brand_name'] ) {
			return $wl['brand_name'];
		}
		return __( '文派叶子', 'wp-china-yes' );
	}

	/**
	 * Read a trimmed string from settings with optional wp-config.php constant override.
	 *
	 * @param array<string, mixed> $raw      Settings array.
	 * @param string               $key      Settings key.
	 * @param string               $constant Constant name.
	 */
	private static function resolve_string( array $raw, string $key, string $constant ): string {
		$val = isset( $raw[ $key ] ) && is_string( $raw[ $key ] ) ? trim( $raw[ $key ] ) : '';
		if ( defined( $constant ) && is_string( constant( $constant ) ) ) {
			$val = trim( (string) constant( $constant ) );
		}
		return $val;
	}

	/**
	 * Check whether a plugin basename belongs to the WenPai plugin suite.
	 *
	 * @param string $plugin_file Plugin basename (dir/file.php).
	 */
	private static function is_suite_plugin( string $plugin_file ): bool {
		foreach ( self::SUITE_PREFIXES as $prefix ) {
			if ( 0 === strpos( $plugin_file, $prefix ) ) {
				return true;
			}
		}
		return false;
	}
}
