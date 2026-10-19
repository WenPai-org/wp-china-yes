<?php
/**
 * Drop WP news/events widgets and short-circuit dashboard event feeds.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Connectivity\DashboardFeeds;

use WenPai\ChinaYes\Core\ConditionalModule;
use WenPai\ChinaYes\Core\Config;
use WenPai\ChinaYes\Core\Environment;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module id connectivity.dashboard_feeds. Does not block payment/shipping hosts.
 */
final class DashboardFeedsModule implements ConditionalModule {

	/**
	 * Config read model.
	 *
	 * @var Config
	 */
	private Config $config;

	/**
	 * Constructor. Does not register hooks.
	 *
	 * @since 4.0.0
	 *
	 * @param Config $config Config read model.
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * Module id.
	 *
	 * @since 4.0.0
	 */
	public function id(): string {
		return 'connectivity.dashboard_feeds';
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
	 * Admin screens only.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function contexts(): array {
		return array( Environment::ADMIN );
	}

	/**
	 * Recovery mode or dashboard_feeds=allow (after filter) → do not hook.
	 *
	 * @since 4.0.0
	 *
	 * @param Config      $config      Config read model.
	 * @param Environment $environment Current request scene.
	 */
	public function enabled( Config $config, Environment $environment ): bool {
		unset( $environment );
		if ( $this->config->get( 'recovery_mode' ) || $config->get( 'recovery_mode' ) ) {
			return false;
		}

		$block = 'block' === $this->config->get( 'connectivity.dashboard_feeds', 'allow' );
		if ( function_exists( 'apply_filters' ) ) {
			$block = (bool) apply_filters( 'wpcy_block_dashboard_feeds', $block );
		}

		return $block;
	}

	/**
	 * Register widget removal, empty feed URLs, and events short-circuit.
	 *
	 * @since 4.0.0
	 */
	public function register(): void {
		add_action( 'wp_dashboard_setup', array( $this, 'on_dashboard_setup' ) );
		add_filter( 'dashboard_primary_feed', array( $this, 'empty_feed' ) );
		add_filter( 'dashboard_secondary_feed', array( $this, 'empty_feed' ) );
		add_filter( 'pre_http_request', array( $this, 'filter_pre_http_request' ), 10, 3 );
	}

	/**
	 * Remove the WP news/events meta box from both contexts.
	 *
	 * @since 4.0.0
	 */
	public function on_dashboard_setup(): void {
		if ( function_exists( 'remove_meta_box' ) ) {
			remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
			remove_meta_box( 'dashboard_primary', 'dashboard', 'normal' );
		}
	}

	/**
	 * Empty string for dashboard_*_feed filters.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $value Prior feed URL.
	 */
	public function empty_feed( $value ): string {
		unset( $value );
		return '';
	}

	/**
	 * Short-circuit api.wordpress.org/events/* only. Other .org paths are untouched.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed                $preempt Prior short-circuit.
	 * @param array<string, mixed> $args    Request args.
	 * @param mixed                $url     Request URL.
	 * @return mixed
	 */
	public function filter_pre_http_request( $preempt, $args, $url ) {
		unset( $args );
		if ( ! is_string( $url ) || ! $this->is_events_url( $url ) ) {
			return $preempt;
		}

		$this->count_block();
		return new WP_Error( 'wpcy_dashboard_feed_blocked', 'wpcy_dashboard_feed_blocked' );
	}

	/**
	 * One blocked dashboard-feed request.
	 *
	 * @since 4.0.0
	 */
	private function count_block(): void {
		if ( ! function_exists( 'do_action' ) ) {
			return;
		}
		do_action( 'wpcy_stats_increment', 'dashboard_feeds_blocked', 1 );
	}

	/**
	 * Host api.wordpress.org and path starting with /events/.
	 *
	 * @param string $url Request URL.
	 */
	private function is_events_url( string $url ): bool {
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- unit bootstrap has no WordPress.
		if ( ! is_array( $parts ) ) {
			return false;
		}
		$host = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
		$path = isset( $parts['path'] ) ? (string) $parts['path'] : '';

		return 'api.wordpress.org' === $host && 0 === strpos( $path, '/events/' );
	}
}
