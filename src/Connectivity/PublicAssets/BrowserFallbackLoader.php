<?php
/**
 * Frontend <2KB browser fallback SDK injector (W6).
 *
 * @package WenPai\ChinaYes
 * @since   4.1.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Connectivity\PublicAssets;

use WenPai\ChinaYes\Core\Config;
use WenPai\ChinaYes\Core\Environment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Injects the self-hosted <2KB admincdn fallback SDK into frontend <head>.
 */
final class BrowserFallbackLoader {

	/**
	 * Configuration reader.
	 *
	 * @var Config
	 */
	private Config $config;

	/**
	 * Signed mirror manifest manager.
	 *
	 * @var Manifest
	 */
	private Manifest $manifest;

	/**
	 * Plugin root directory path.
	 *
	 * @var string
	 */
	private string $plugin_root;

	/**
	 * Constructor.
	 *
	 * @param Config      $config      Configuration reader.
	 * @param Manifest    $manifest    Signed manifest manager.
	 * @param string|null $plugin_root Plugin root directory path.
	 */
	public function __construct( Config $config, Manifest $manifest, ?string $plugin_root = null ) {
		$this->config      = $config;
		$this->manifest    = $manifest;
		$this->plugin_root = null !== $plugin_root ? rtrim( $plugin_root, '/' ) : dirname( __DIR__, 3 );
	}

	/**
	 * Register wp_head hook on frontend when enabled.
	 *
	 * @param Environment $env Current runtime environment.
	 */
	public function register( Environment $env ): void {
		if ( Environment::FRONTEND !== $env->context() ) {
			return;
		}
		if ( function_exists( 'add_action' ) ) {
			add_action( 'wp_head', array( $this, 'render' ), 1 );
		}
	}

	/**
	 * Whether browser fallback SDK injection is currently permitted.
	 *
	 * Suppressed on recovery_mode, killSwitch === true, toggle off, or off scope.
	 *
	 * @param int|null $now Optional epoch override for manifest LKG check.
	 */
	public function is_active( ?int $now = null ): bool {
		if ( (bool) $this->config->get( 'recovery_mode', false ) ) {
			return false;
		}
		if ( ! (bool) $this->config->get( 'connectivity.browser_fallback_loader', true ) ) {
			return false;
		}
		$pa = $this->config->get( 'connectivity.public_assets', array() );
		if ( ! is_array( $pa ) || 'off' === ( $pa['scope'] ?? 'frontend' ) || empty( $pa['items'] ) ) {
			return false;
		}
		if ( $this->manifest->is_kill_switch( $now ) ) {
			return false;
		}
		return is_file( $this->bundle_path() );
	}

	/**
	 * Build client fallback configuration merged from signed manifest and static domestic AssetMap.
	 *
	 * @param int|null $now Optional epoch override.
	 * @return array{fallbacks: array<string, list<string>>, backupHost: string|null, rum: string}
	 */
	public function client_config( ?int $now = null ): array {
		$mf_cfg    = $this->manifest->client_fallback_config( $now );
		$fallbacks = $mf_cfg['fallbacks'];

		if ( array() === $fallbacks ) {
			$fallbacks = AssetMap::browser_fallback_defaults();
		}

		$rum = '';
		if ( (bool) $this->config->get( 'diagnostics.client_probe_enabled', true ) && function_exists( 'apply_filters' ) ) {
			$rum = (string) apply_filters( 'wpcy_browser_fallback_rum_url', '' );
		}

		return array(
			'fallbacks'  => $fallbacks,
			'backupHost' => $mf_cfg['backupHost'],
			'rum'        => $rum,
		);
	}

	/**
	 * Render the script tag string (empty when suppressed).
	 *
	 * @param int|null $now Optional epoch override.
	 */
	public function render_tag( ?int $now = null ): string {
		if ( ! $this->is_active( $now ) ) {
			return '';
		}

		$cfg     = $this->client_config( $now );
		$fb_json = function_exists( 'wp_json_encode' )
			? wp_json_encode( $cfg['fallbacks'], JSON_HEX_TAG | JSON_HEX_AMP )
			: json_encode( $cfg['fallbacks'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit bootstrap has no WordPress.
		$fb_json = is_string( $fb_json ) ? $fb_json : '{}';
		$sdk_url = $this->bundle_url();
		$esc_url = function_exists( 'esc_url' ) ? esc_url( $sdk_url ) : htmlspecialchars( $sdk_url, ENT_QUOTES, 'UTF-8' );
		$esc_fb  = function_exists( 'esc_attr' ) ? esc_attr( $fb_json ) : htmlspecialchars( $fb_json, ENT_QUOTES, 'UTF-8' );
		$attrs   = ' src="' . $esc_url . '" data-fallbacks="' . $esc_fb . '"';

		if ( is_string( $cfg['backupHost'] ) && '' !== $cfg['backupHost'] ) {
			$esc_bk = function_exists( 'esc_attr' ) ? esc_attr( $cfg['backupHost'] ) : htmlspecialchars( $cfg['backupHost'], ENT_QUOTES, 'UTF-8' );
			$attrs .= ' data-backup-host="' . $esc_bk . '"';
		}
		if ( '' !== $cfg['rum'] ) {
			$esc_rum = function_exists( 'esc_attr' ) ? esc_attr( $cfg['rum'] ) : htmlspecialchars( $cfg['rum'], ENT_QUOTES, 'UTF-8' );
			$attrs  .= ' data-rum="' . $esc_rum . '"';
		}

		return '<script id="wpcy-admincdn-loader"' . $attrs . "></script>\n";
	}

	/**
	 * Echo the loader script tag during wp_head.
	 */
	public function render(): void {
		$tag = $this->render_tag();
		if ( '' !== $tag ) {
			echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- URL and attributes are escaped inside render_tag().
		}
	}

	/**
	 * Resolve filesystem path to bundled minified SDK.
	 */
	private function bundle_path(): string {
		return $this->plugin_root . '/assets/js/admincdn-loader.min.js';
	}

	/**
	 * Resolve public URL to bundled minified SDK.
	 */
	private function bundle_url(): string {
		$rel = 'assets/js/admincdn-loader.min.js';
		if ( function_exists( 'plugins_url' ) ) {
			$base = defined( 'WPCY_PLUGIN_FILE' ) ? WPCY_PLUGIN_FILE : $this->plugin_root . '/wp-china-yes.php';
			return (string) plugins_url( $rel, $base );
		}
		return '/' . $rel;
	}
}
