<?php
/**
 * Mirror manifest routing, template expansion, and client fallback assembly.
 *
 * @package WenPai\ChinaYes
 * @since   4.1.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Connectivity\PublicAssets;

use WenPai\ChinaYes\Connectivity\MirrorHealth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure routing and template helpers for signed mirror manifests.
 */
final class ManifestRouter {

	/**
	 * Overseas origin hosts that must never appear ahead of domestic mirrors in clientFallbacks.
	 *
	 * @var list<string>
	 */
	private const OVERSEAS_ORIGIN_HOSTS = array(
		'cdn.jsdelivr.net',
		'cdnjs.cloudflare.com',
		'fonts.googleapis.com',
		'fonts.gstatic.com',
		'ajax.googleapis.com',
		's.w.org',
		'unpkg.com',
		'code.jquery.com',
	);

	/**
	 * Resolve a public asset URL using an active signed manifest fused with MirrorHealth.
	 *
	 * @param array<string, mixed> $manifest      Active signed manifest.
	 * @param string               $origin_url    Original asset URL.
	 * @param string               $asset_key     AssetMap key.
	 * @param MirrorHealth         $health        Mirror health probe evaluator.
	 * @param string|null          $canonical_url Optional normalized URL for template parameter extraction.
	 */
	public static function resolve_url(
		array $manifest,
		string $origin_url,
		string $asset_key,
		MirrorHealth $health,
		?string $canonical_url = null
	): ?string {
		if ( ! empty( $manifest['killSwitch'] ) ) {
			return $origin_url;
		}

		$cls = Manifest::ASSET_KEY_TO_CLASS[ $asset_key ] ?? '';
		if ( '' === $cls || empty( $manifest['classes'][ $cls ] ) || ! is_array( $manifest['classes'][ $cls ] ) ) {
			return null;
		}

		$tpl_source = null !== $canonical_url && '' !== $canonical_url ? $canonical_url : $origin_url;
		$entry      = $manifest['classes'][ $cls ];
		$state      = self::effective_class_state( $entry, $health );
		$primary    = (string) ( $entry['primary'] ?? '' );

		if ( 'wpstatic' === $cls ) {
			return self::resolve_wpstatic_url( $origin_url, $tpl_source, $entry, $state, $health );
		}

		$had_template_match = false;
		if ( 'ok' === $state && '' !== $primary ) {
			$expanded = self::expand_template( $primary, $tpl_source );
			if ( null !== $expanded ) {
				return $expanded;
			}
		}

		foreach ( self::domestic_fallbacks( $entry ) as $template ) {
			$cand = self::expand_template( $template, $tpl_source );
			if ( null === $cand ) {
				continue;
			}
			$had_template_match = true;
			$host               = self::extract_host( $cand );
			if ( '' !== $host && $health->is_healthy( $host ) ) {
				return $cand;
			}
		}

		if ( ! $had_template_match ) {
			return null;
		}

		return $origin_url;
	}

	/**
	 * Build sanitized clientFallbacks config map for BrowserFallbackLoader (W6).
	 *
	 * @param array<string, mixed> $manifest Active signed manifest.
	 * @return array{killSwitch: bool, backupHost: string|null, fallbacks: array<string, list<string>>}
	 */
	public static function client_fallback_config( array $manifest ): array {
		$fallbacks = array();
		if ( ! empty( $manifest['classes'] ) && is_array( $manifest['classes'] ) ) {
			foreach ( $manifest['classes'] as $cls => $entry ) {
				if ( 'wpstatic' === $cls || ! is_array( $entry ) ) {
					continue;
				}
				$list = self::domestic_fallbacks( $entry );
				if ( array() !== $list ) {
					$fallbacks[ (string) $cls ] = $list;
				}
			}
		}

		return array(
			'killSwitch' => ! empty( $manifest['killSwitch'] ),
			'backupHost' => isset( $manifest['backupHost'] ) && is_string( $manifest['backupHost'] ) ? $manifest['backupHost'] : null,
			'fallbacks'  => $fallbacks,
		);
	}

	/**
	 * Expand `{pkg}`, `{ver}`, `{file}`, `{lib}`, `{path}` template against origin URL.
	 *
	 * @param string $template   Manifest template URL.
	 * @param string $origin_url Original asset URL.
	 */
	public static function expand_template( string $template, string $origin_url ): ?string {
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $origin_url ) : parse_url( $origin_url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- unit bootstrap may not load WordPress.
		if ( ! is_array( $parts ) ) {
			return null;
		}
		$host     = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
		$raw_path = (string) ( $parts['path'] ?? '/' );
		$query    = isset( $parts['query'] ) && '' !== $parts['query'] ? '?' . $parts['query'] : '';
		$path     = ltrim( $raw_path, '/' ) . $query;

		$pkg  = '';
		$lib  = '';
		$ver  = '';
		$file = '';
		if ( 1 === preg_match( '/^\/npm\/(@?[^@]+)@([^\/]+)\/(.+)$/', $raw_path, $m ) ) {
			$pkg  = $m[1];
			$ver  = $m[2];
			$file = $m[3];
		} elseif ( 1 === preg_match( '/^\/ajax\/libs\/([^\/]+)\/([^\/]+)\/(.+)$/', $raw_path, $m ) ) {
			$lib  = $m[1];
			$ver  = $m[2];
			$file = $m[3];
		} elseif ( 'cdnjs.admincdn.com' === $host && 1 === preg_match( '/^\/([^\/]+)\/([^\/]+)\/(.+)$/', $raw_path, $m ) ) {
			$lib  = $m[1];
			$ver  = $m[2];
			$file = $m[3];
		} elseif ( in_array( $host, array( 'unpkg.com', 'unpkg.admincdn.com' ), true ) && 1 === preg_match( '/^\/(@[^\/]+\/[^@\/]+|[^@\/]+)@([^\/]+)\/(.+)$/', $raw_path, $m ) ) {
			$pkg  = $m[1];
			$ver  = $m[2];
			$file = $m[3];
		}

		$out = strtr(
			$template,
			array(
				'{pkg}'  => $pkg,
				'{lib}'  => $lib,
				'{ver}'  => $ver,
				'{file}' => $file,
				'{path}' => $path,
			)
		);

		if ( false !== strpos( $out, '{' ) || false !== strpos( $out, '//files' ) || '' === $out ) {
			return null;
		}
		if ( '' === $pkg && false !== strpos( $template, '{pkg}' ) ) {
			return null;
		}
		if ( '' === $lib && false !== strpos( $template, '{lib}' ) ) {
			return null;
		}
		if ( '' === $ver && false !== strpos( $template, '{ver}' ) ) {
			return null;
		}
		if ( '' === $file && false !== strpos( $template, '{file}' ) ) {
			return null;
		}

		return $out;
	}

	/**
	 * Fuse class state, optional region override, and MirrorHealth.
	 *
	 * @param array<string, mixed> $entry  Class manifest entry.
	 * @param MirrorHealth         $health Mirror health evaluator.
	 */
	public static function effective_class_state( array $entry, MirrorHealth $health ): string {
		$state  = (string) ( $entry['state'] ?? 'ok' );
		$region = function_exists( 'apply_filters' ) ? (string) apply_filters( 'wpcy_mirror_manifest_region', '' ) : '';
		if ( '' !== $region && ! empty( $entry['regions'][ $region ] ) && is_string( $entry['regions'][ $region ] ) ) {
			$state = $entry['regions'][ $region ];
		}
		$primary_host = self::extract_host( (string) ( $entry['primary'] ?? '' ) );
		if ( '' !== $primary_host && ! $health->is_healthy( $primary_host ) ) {
			return 'down';
		}
		return $state;
	}

	/**
	 * Filter domestic clientFallbacks, rejecting overseas origins.
	 *
	 * @param array<string, mixed> $entry Class manifest entry.
	 * @return list<string>
	 */
	public static function domestic_fallbacks( array $entry ): array {
		$raw = isset( $entry['clientFallbacks'] ) && is_array( $entry['clientFallbacks'] ) ? $entry['clientFallbacks'] : array();
		$out = array();
		foreach ( $raw as $cand ) {
			if ( ! is_string( $cand ) || '' === $cand ) {
				continue;
			}
			$host = self::extract_host( $cand );
			if ( '' === $host || in_array( $host, self::OVERSEAS_ORIGIN_HOSTS, true ) ) {
				continue;
			}
			$out[] = $cand;
		}
		return $out;
	}

	/**
	 * Extract lowercase host from URL.
	 *
	 * @param string $url URL.
	 */
	public static function extract_host( string $url ): string {
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- unit bootstrap may not load WordPress.
		return is_array( $parts ) && ! empty( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
	}

	/**
	 * Resolve a wpstatic URL strictly within owned hosts (admincdn primary -> owned backup domains -> origin).
	 *
	 * Hard redline: wpstatic must NEVER route to s.w.org or any third-party mirror.
	 *
	 * @param string               $origin_url Original asset URL.
	 * @param string               $tpl_source Normalized URL for template expansion.
	 * @param array<string, mixed> $entry      Manifest wpstatic class entry.
	 * @param string               $state      Effective class state.
	 * @param MirrorHealth         $health     Mirror health evaluator.
	 */
	private static function resolve_wpstatic_url(
		string $origin_url,
		string $tpl_source,
		array $entry,
		string $state,
		MirrorHealth $health
	): string {
		$primary = (string) ( $entry['primary'] ?? '' );
		if ( 'wpstatic.admincdn.com' !== self::extract_host( $primary ) ) {
			return $origin_url;
		}

		if ( 'ok' === $state && $health->is_healthy( 'wpstatic.admincdn.com' ) ) {
			$expanded = self::expand_template( $primary, $tpl_source );
			if ( null !== $expanded && 'wpstatic.admincdn.com' === self::extract_host( $expanded ) ) {
				return $expanded;
			}
			return $origin_url;
		}

		$owned_hosts = AssetMap::WPSTATIC_BACKUP_HOSTS;
		$candidates  = array();
		$seen_hosts  = array();

		$raw_fallbacks = isset( $entry['clientFallbacks'] ) && is_array( $entry['clientFallbacks'] )
			? $entry['clientFallbacks']
			: array();
		foreach ( $raw_fallbacks as $tpl ) {
			if ( ! is_string( $tpl ) || '' === $tpl ) {
				continue;
			}
			$host = self::extract_host( $tpl );
			if ( in_array( $host, $owned_hosts, true ) && ! isset( $seen_hosts[ $host ] ) ) {
				$candidates[]        = $tpl;
				$seen_hosts[ $host ] = true;
			}
		}

		foreach ( $owned_hosts as $owned_host ) {
			if ( ! isset( $seen_hosts[ $owned_host ] ) ) {
				$candidates[]              = 'https://' . $owned_host . '/{path}';
				$seen_hosts[ $owned_host ] = true;
			}
		}

		foreach ( $candidates as $tpl ) {
			$host = self::extract_host( $tpl );
			if ( ! in_array( $host, $owned_hosts, true ) || ! $health->is_healthy( $host ) ) {
				continue;
			}
			$expanded = self::expand_template( $tpl, $tpl_source );
			if ( null !== $expanded && in_array( self::extract_host( $expanded ), $owned_hosts, true ) ) {
				return $expanded;
			}
		}

		return $origin_url;
	}
}
