<?php
/**
 * Page and object cache purge adapters triggered on manifest state transitions.
 *
 * @package WenPai\ChinaYes
 * @since   4.1.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Connectivity\PublicAssets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Purges supported WordPress page/object caches on mirror failover or kill-switch flips.
 */
final class CachePurger {

	/**
	 * Flush available page and object cache adapters.
	 *
	 * @since 4.1.0
	 *
	 * @param string $reason Transition reason slug (e.g. 'kill_switch', 'class_down:npm').
	 * @return list<string> Adapter identifiers executed.
	 */
	public function purge( string $reason = '' ): array {
		$purged = array();

		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
			$purged[] = 'object_cache';
		}

		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
			$purged[] = 'wp_rocket';
		}

		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
			$purged[] = 'w3_total_cache';
		}

		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
			$purged[] = 'wp_super_cache';
		}

		if ( function_exists( 'has_action' ) && has_action( 'litespeed_purge_all' ) && function_exists( 'do_action' ) ) {
			do_action( 'litespeed_purge_all', $reason );
			$purged[] = 'litespeed_cache';
		}

		if ( function_exists( 'rt_nginx_helper_purge_all' ) ) {
			rt_nginx_helper_purge_all();
			$purged[] = 'nginx_helper';
		} elseif ( function_exists( 'has_action' ) && has_action( 'rt_nginx_helper_purge_all' ) && function_exists( 'do_action' ) ) {
			do_action( 'rt_nginx_helper_purge_all' );
			$purged[] = 'nginx_helper';
		}

		if ( function_exists( 'do_action' ) ) {
			do_action( 'wpcy_manifest_cache_purged', $reason, $purged );
		}

		return $purged;
	}
}
