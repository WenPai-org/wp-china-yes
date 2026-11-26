<?php
/**
 * Whitelist of public-library URL replacements (3.x Acceleration tables).
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Connectivity\PublicAssets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Search/replace pairs keyed by connectivity.public_assets enum values.
 */
final class AssetMap {

	/**
	 * Replacement table. Keys are schema enum values.
	 *
	 * Jsdelivr also absorbs 3.x admincdn_dev (unpkg / jquery / vue / datatables).
	 * public.admincdn.com and site wp-content paths are intentionally absent.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, array<string, string>> Feature => (search => replace).
	 */
	public static function table(): array {
		return array(
			'google_fonts' => array(
				'fonts.googleapis.com' => 'googlefonts.admincdn.com',
			),
			'google_ajax'  => array(
				'ajax.googleapis.com' => 'googleajax.admincdn.com',
			),
			'cdnjs'        => array(
				'cdnjs.cloudflare.com/ajax/libs' => 'cdnjs.admincdn.com',
			),
			'jsdelivr'     => array(
				'cdn.jsdelivr.net'        => 'jsd.admincdn.com',
				'maxcdn.bootstrapcdn.com' => 'jsd.admincdn.com',
				'unpkg.com/react'         => 'jsd.admincdn.com/npm/react',
				'code.jquery.com'         => 'jsd.admincdn.com/npm/jquery',
				'unpkg.com/vue'           => 'jsd.admincdn.com/npm/vue',
				'cdn.datatables.net'      => 'jsd.admincdn.com/npm/datatables.net',
				'unpkg.com/tailwindcss'   => 'jsd.admincdn.com/npm/tailwindcss',
			),
			'emoji'        => array(
				's.w.org/images/core/emoji' => 'jsd.admincdn.com/npm/@twemoji/api/dist',
			),
		);
	}

	/**
	 * Domestic fallback replacement table consulted when primary admincdn nodes are unhealthy.
	 *
	 * Uses path-compatible Mainland China mirrors verified against upstream SHA.
	 * wpstatic.admincdn.com is intentionally excluded and never falls back to third-party mirrors.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, array<string, string>> Feature => (search => replace).
	 */
	public static function fallback_table(): array {
		$table = array(
			'google_fonts' => array(
				'fonts.googleapis.com' => 'fonts.googleapis.cn',
			),
			'google_ajax'  => array(
				'ajax.googleapis.com/ajax/libs' => 's4.zstatic.net/ajax/libs',
			),
			'cdnjs'        => array(
				'cdnjs.cloudflare.com/ajax/libs' => 's4.zstatic.net/ajax/libs',
			),
			'jsdelivr'     => array(
				'cdn.jsdelivr.net'        => 'cdn.jsdmirror.com',
				'maxcdn.bootstrapcdn.com' => 'cdn.jsdmirror.com',
				'unpkg.com/react'         => 's4.zstatic.net/npm/react',
				'code.jquery.com'         => 's4.zstatic.net/npm/jquery',
				'unpkg.com/vue'           => 's4.zstatic.net/npm/vue',
				'cdn.datatables.net'      => 's4.zstatic.net/npm/datatables.net',
				'unpkg.com/tailwindcss'   => 's4.zstatic.net/npm/tailwindcss',
			),
			'emoji'        => array(
				's.w.org/images/core/emoji' => 's4.zstatic.net/npm/@twemoji/api/dist',
			),
		);

		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'wpcy_public_assets_fallback_table', $table );
			if ( is_array( $filtered ) ) {
				return $filtered;
			}
		}

		return $table;
	}

	/**
	 * Replace $src using only enabled whitelist keys. Unknown keys are ignored.
	 *
	 * @since 4.0.0
	 *
	 * @param string   $src     Original URL.
	 * @param string[] $enabled Subset of schema enum values.
	 */
	public function replace_if_whitelisted( string $src, array $enabled ): string {
		return $this->apply_table( $src, $enabled, self::table() );
	}

	/**
	 * Replace $src using domestic fallback mirrors for enabled whitelist keys.
	 *
	 * @since 4.0.0
	 *
	 * @param string   $src     Original URL.
	 * @param string[] $enabled Subset of schema enum values.
	 */
	public function replace_with_fallback( string $src, array $enabled ): string {
		return $this->apply_table( $src, $enabled, self::fallback_table() );
	}

	/**
	 * Find the enabled whitelist key matching $src, or empty string if unmatched.
	 *
	 * @since 4.1.0
	 *
	 * @param string   $src     Original URL.
	 * @param string[] $enabled Subset of schema enum values.
	 */
	public function match_key( string $src, array $enabled ): string {
		$table = self::table();

		foreach ( $enabled as $key ) {
			if ( ! isset( $table[ $key ] ) ) {
				continue;
			}
			foreach ( $table[ $key ] as $search => $unused ) {
				unset( $unused );
				if ( false !== strpos( $src, $search ) ) {
					return $key;
				}
			}
		}

		return '';
	}

	/**
	 * Apply a feature-keyed search/replace table to $src for enabled keys.
	 *
	 * @param string                               $src     Original URL.
	 * @param string[]                             $enabled Subset of schema enum values.
	 * @param array<string, array<string, string>> $table   Feature => (search => replace).
	 */
	private function apply_table( string $src, array $enabled, array $table ): string {
		$pairs = array();

		foreach ( $enabled as $key ) {
			if ( isset( $table[ $key ] ) ) {
				foreach ( $table[ $key ] as $search => $replace ) {
					$pairs[ $search ] = $replace;
				}
			}
		}

		if ( array() === $pairs ) {
			return $src;
		}

		return str_replace( array_keys( $pairs ), array_values( $pairs ), $src );
	}

	/**
	 * Every replacement target string (may include a path prefix).
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function targets(): array {
		$targets = array();

		foreach ( self::table() as $pairs ) {
			foreach ( $pairs as $replace ) {
				$targets[] = $replace;
			}
		}

		return $targets;
	}
}
