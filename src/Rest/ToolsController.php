<?php
/**
 * Local high-frequency maintenance toolbox endpoints:
 * - POST /wpcy/v1/tools/clear-transients
 * - POST /wpcy/v1/tools/maintenance
 * - GET  /wpcy/v1/tools/snapshot
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Rest;

use WenPai\ChinaYes\Admin\MaintenanceModule;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Diagnostics\Checker;
use WenPai\ChinaYes\Diagnostics\ConflictDetector;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves built-in zero-dependency site tools wired into Overview's 5x2 Quick Tools grid.
 */
final class ToolsController {

	/**
	 * Settings repository.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Diagnostics checker for cached server probes.
	 *
	 * @var Checker
	 */
	private Checker $checker;

	/**
	 * Constructor.
	 *
	 * @param Repository $repository Settings repository.
	 * @param Checker    $checker    Diagnostics checker.
	 */
	public function __construct( Repository $repository, Checker $checker ) {
		$this->repository = $repository;
		$this->checker    = $checker;
	}

	/**
	 * Delete expired transients from `$wpdb->options` (and `$wpdb->sitemeta` on multisite) and flush object cache.
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function clear_transients( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		$deleted = 0;

		if ( isset( $GLOBALS['wpdb'] ) && is_object( $GLOBALS['wpdb'] ) && method_exists( $GLOBALS['wpdb'], 'query' ) && method_exists( $GLOBALS['wpdb'], 'prepare' ) ) {
			$wpdb = $GLOBALS['wpdb'];
			$now  = time();
			if ( ! empty( $wpdb->options ) && is_string( $wpdb->options ) ) {
				$t_prefix = $wpdb->esc_like( '_transient_timeout_' ) . '%';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded cleanup of expired transient rows only.
				$res = $wpdb->query(
					$wpdb->prepare(
						"DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
						 WHERE a.option_name LIKE %s
						   AND a.option_name NOT LIKE %s
						   AND b.option_name = CONCAT( '_transient_', SUBSTRING( a.option_name, 20 ) )
						   AND CAST( a.option_value AS UNSIGNED ) < %d",
						$t_prefix,
						$wpdb->esc_like( '_transient_timeout_wpcy_' ) . '%',
						$now
					)
				);
				if ( is_int( $res ) && $res > 0 ) {
					$deleted += (int) floor( $res / 2 );
				}
			}

			if ( function_exists( 'is_multisite' ) && is_multisite() && ! empty( $wpdb->sitemeta ) && is_string( $wpdb->sitemeta ) ) {
				$st_prefix = $wpdb->esc_like( '_site_transient_timeout_' ) . '%';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded cleanup of expired site transient rows only.
				$sres = $wpdb->query(
					$wpdb->prepare(
						"DELETE a, b FROM {$wpdb->sitemeta} a, {$wpdb->sitemeta} b
						 WHERE a.meta_key LIKE %s
						   AND b.meta_key = CONCAT( '_site_transient_', SUBSTRING( a.meta_key, 25 ) )
						   AND CAST( a.meta_value AS UNSIGNED ) < %d",
						$st_prefix,
						$now
					)
				);
				if ( is_int( $sres ) && $sres > 0 ) {
					$deleted += (int) floor( $sres / 2 );
				}
			}
		}

		$flushed = false;
		if ( function_exists( 'wp_cache_flush' ) ) {
			$flushed = (bool) wp_cache_flush();
		}

		return RestError::ok(
			array(
				'deleted' => $deleted,
				'flushed' => $flushed,
			)
		);
	}

	/**
	 * Toggle safe frontend maintenance mode (`wpcy_maintenance_mode`).
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function set_maintenance( WP_REST_Request $request ): WP_REST_Response {
		$params  = $request->get_json_params();
		$enabled = isset( $params['enabled'] )
			? (bool) $params['enabled']
			: ! MaintenanceModule::is_enabled();

		MaintenanceModule::set_enabled( $enabled );

		return RestError::ok(
			array(
				'enabled' => MaintenanceModule::is_enabled(),
			)
		);
	}

	/**
	 * Collect a privacy-sanitized WordPress & WPCY environment snapshot for one-click clipboard copy.
	 *
	 * Contains zero secrets, passwords, license keys, or emails.
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function get_snapshot( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		$wp_ver    = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'version' ) : 'unknown';
		$php_ver   = PHP_VERSION;
		$mem_limit = (string) ini_get( 'memory_limit' );
		$locale    = function_exists( 'get_locale' ) ? (string) get_locale() : 'zh_CN';
		$theme     = function_exists( 'get_stylesheet' ) ? (string) get_stylesheet() : '';
		$multisite = function_exists( 'is_multisite' ) && is_multisite();
		$wpcy_ver  = defined( 'CHINA_YES_VERSION' ) ? (string) CHINA_YES_VERSION : '4.0.0';
		$profile   = (string) $this->repository->get( 'profile', 'domestic' );
		$recovery  = true === $this->repository->get( 'recovery_mode', false );
		$maint     = MaintenanceModule::is_enabled();

		$active_plugins = array();
		if ( function_exists( 'get_option' ) ) {
			$raw = get_option( 'active_plugins', array() );
			if ( is_array( $raw ) ) {
				foreach ( $raw as $p ) {
					if ( is_string( $p ) && '' !== $p ) {
						$active_plugins[] = $p;
					}
				}
			}
		}

		$scan          = ConflictDetector::scan();
		$synergy_names = array();
		foreach ( $scan['synergies'] as $syn ) {
			$synergy_names[] = $syn['slug'];
		}
		$conflict_names = array();
		foreach ( $scan['conflicts'] as $conf ) {
			$conflict_names[] = $conf['slug'];
		}

		$probe_lines = array();
		foreach ( $this->checker->latest() as $row ) {
			$ms            = null !== $row['latency_ms'] ? ( $row['latency_ms'] . 'ms' ) : '-';
			$probe_lines[] = $row['target'] . '=' . $row['result'] . '(' . $ms . ')';
		}

		$lines = array(
			'=== WenPai Leaf Environment Snapshot ===',
			'WPCY: ' . $wpcy_ver . ' | Profile: ' . $profile . ( $recovery ? ' [RECOVERY]' : '' ) . ( $maint ? ' [MAINTENANCE]' : '' ),
			'WordPress: ' . $wp_ver . ( $multisite ? ' (Multisite)' : '' ) . ' | Locale: ' . $locale . ' | Theme: ' . $theme,
			'PHP: ' . $php_ver . ' | Memory Limit: ' . $mem_limit . ' | Active Plugins: ' . count( $active_plugins ),
			'Companions: ' . ( array() !== $synergy_names ? implode( ', ', $synergy_names ) : 'none' ),
			'Conflicts: ' . ( array() !== $conflict_names ? implode( ', ', $conflict_names ) : 'none' ),
			'Probes: ' . ( array() !== $probe_lines ? implode( '; ', $probe_lines ) : 'none' ),
		);

		return RestError::ok(
			array(
				'wp_version'     => $wp_ver,
				'php_version'    => $php_ver,
				'memory_limit'   => $mem_limit,
				'wpcy_version'   => $wpcy_ver,
				'profile'        => $profile,
				'active_plugins' => count( $active_plugins ),
				'companions'     => $synergy_names,
				'conflicts'      => $conflict_names,
				'maintenance'    => $maint,
				'text'           => implode( "\n", $lines ),
			)
		);
	}
}
