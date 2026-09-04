<?php
/**
 * Whitelist rewrite of public asset URLs; keep origin URL on node failure.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Connectivity\PublicAssets;

use WenPai\ChinaYes\Connectivity\MirrorHealth;
use WenPai\ChinaYes\Core\ConditionalModule;
use WenPai\ChinaYes\Core\Config;
use WenPai\ChinaYes\Core\Environment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module id connectivity.public_assets.
 */
final class PublicAssetsModule implements ConditionalModule {

	/**
	 * Config read model.
	 *
	 * @var Config
	 */
	private Config $config;

	/**
	 * Whitelist table.
	 *
	 * @var AssetMap
	 */
	private AssetMap $map;

	/**
	 * Node health.
	 *
	 * @var MirrorHealth
	 */
	private MirrorHealth $health;

	/**
	 * Optional entitlement gate. Null means apply_filters default true
	 * (no entitlements client yet: unbound still rewrites).
	 *
	 * Callable is not a valid PHP 7.4 property type.
	 *
	 * @var callable|null
	 */
	private $entitlement_allows;

	/**
	 * Constructor. Does not register hooks.
	 *
	 * @since 4.0.0
	 *
	 * @param Config        $config             Config read model.
	 * @param AssetMap      $map                Whitelist table.
	 * @param MirrorHealth  $health             Node health.
	 * @param callable|null $entitlement_allows `fn(): bool`; false keeps origin URLs.
	 */
	public function __construct( Config $config, AssetMap $map, MirrorHealth $health, ?callable $entitlement_allows = null ) {
		$this->config             = $config;
		$this->map                = $map;
		$this->health             = $health;
		$this->entitlement_allows = $entitlement_allows;
	}

	/**
	 * Module id.
	 *
	 * @since 4.0.0
	 */
	public function id(): string {
		return 'connectivity.public_assets';
	}

	/**
	 * No module dependencies.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function dependencies(): array {
		return array();
	}

	/**
	 * Loader src runs on admin and frontend.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function contexts(): array {
		return array( Environment::ADMIN, Environment::FRONTEND );
	}

	/**
	 * Empty public_assets, recovery_mode, or rewrite-disallowed scene → off.
	 *
	 * @since 4.0.0
	 *
	 * @param Config      $config      Config read model.
	 * @param Environment $environment Current request scene.
	 */
	public function enabled( Config $config, Environment $environment ): bool {
		if ( $config->get( 'recovery_mode' ) ) {
			return false;
		}

		if ( ! $environment->allowsUrlRewrite() ) {
			return false;
		}

		$assets = $config->get( 'connectivity.public_assets', array() );

		return is_array( $assets ) && array() !== $assets;
	}

	/**
	 * Register loader-src filters. Emoji settings only when emoji is enabled
	 * and jsd.admincdn.com is healthy.
	 *
	 * @since 4.0.0
	 */
	public function register(): void {
		add_filter( 'style_loader_src', array( $this, 'rewrite' ), 999 );
		add_filter( 'script_loader_src', array( $this, 'rewrite' ), 999 );

		if ( ! $this->emoji_enabled() || ! $this->health->is_healthy( 'jsd.admincdn.com' ) ) {
			return;
		}

		add_filter( 'emoji_url', array( $this, 'rewrite' ), 999 );
		add_filter( 'emoji_svg_url', array( $this, 'rewrite' ), 999 );
		$this->register_emoji_settings();
	}

	/**
	 * Rewrite a single URL. Off-whitelist, down node, or exhausted quota → origin.
	 *
	 * @since 4.0.0
	 *
	 * @param string $src Original URL.
	 */
	public function rewrite( string $src ): string {
		$enabled = $this->config->get( 'connectivity.public_assets', array() );
		if ( ! is_array( $enabled ) ) {
			return $src;
		}

		$mapped = $this->map->replace_if_whitelisted( $src, $enabled );
		if ( $mapped === $src ) {
			return $src;
		}

		if ( ! $this->health->is_healthy( MirrorHealth::host_of( $mapped ) ) ) {
			return $src;
		}

		if ( ! $this->entitlement_allows() ) {
			return $src;
		}

		return $mapped;
	}

	/**
	 * Whether emoji is in the enabled set.
	 *
	 * @since 4.0.0
	 */
	private function emoji_enabled(): bool {
		$enabled = $this->config->get( 'connectivity.public_assets', array() );

		return is_array( $enabled ) && in_array( 'emoji', $enabled, true );
	}

	/**
	 * Limited-free adminCDN: exhausted / denied → keep origin. Default allow.
	 *
	 * @since 4.0.0
	 */
	private function entitlement_allows(): bool {
		if ( is_callable( $this->entitlement_allows ) ) {
			return (bool) call_user_func( $this->entitlement_allows );
		}

		if ( function_exists( 'apply_filters' ) ) {
			return (bool) apply_filters( 'wpcy_entitlement_allows', true, 'admincdn' );
		}

		return true;
	}

	/**
	 * Port of Acceleration::prepare_emoji_replacements settings injection.
	 *
	 * @since 4.0.0
	 */
	private function register_emoji_settings(): void {
		if ( function_exists( 'remove_action' ) ) {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_action( 'admin_print_styles', 'print_emoji_styles' );
		}

		if ( function_exists( 'remove_filter' ) ) {
			remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
			remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
			remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		}

		if ( function_exists( 'add_action' ) ) {
			add_action( 'wp_head', array( $this, 'print_emoji_settings' ), 1 );
		}
	}

	/**
	 * Print versioned Twemoji contract used by 3.x.
	 *
	 * @since 4.0.0
	 */
	public function print_emoji_settings(): void {
		$concat = function_exists( 'includes_url' )
			? includes_url( 'js/wp-emoji-release.min.js' )
			: '';
		$concat = function_exists( 'esc_url' ) ? esc_url( $concat ) : $concat;

		echo '<script>window._wpemojiSettings={"baseUrl":"https://jsd.admincdn.com/npm/@twemoji/api@15.0.3/dist/72x72/","ext":".png","svgUrl":"https://jsd.admincdn.com/npm/@twemoji/api@15.0.3/dist/svg/","svgExt":".svg","source":{"concatemoji":"' . $concat . '"}};'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- concat already passed through esc_url when available.
		echo '</script>' . "\n";
	}
}
