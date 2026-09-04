<?php
/**
 * Shared mirror host parsing and per-host health cache.
 *
 * Port of Service/MirrorHealth.php host_of / is_healthy / probe_targets /
 * unhealthy_hosts for M1-05 PublicAssets reuse. Does not restore
 * public.admincdn.com.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Connectivity;

/**
 * Host extraction and down-list for public-library mirrors.
 */
final class MirrorHealth {

	/**
	 * Transient key prefix for per-host state.
	 *
	 * @var string
	 */
	public const STATE_PREFIX = 'wpcy_mirror_state_';

	/**
	 * Reader: function( string $key ): mixed
	 *
	 * Callable is not a valid PHP 7.4 property type.
	 *
	 * @var callable
	 */
	private $get_transient;

	/**
	 * Writer: function( string $key, mixed $value, int $ttl ): bool
	 *
	 * Callable is not a valid PHP 7.4 property type.
	 *
	 * @var callable
	 */
	private $set_transient;

	/**
	 * Host => healthy overrides consulted before transients (unit tests, forced states).
	 *
	 * @var array<string, bool>
	 */
	private array $overrides = array();

	/**
	 * Wire optional transient accessors, or pass a host => healthy map for tests.
	 *
	 * @param callable|array<string,bool>|null $get_transient Transient reader, or a host => bool override map.
	 * @param callable|null                    $set_transient Transient writer. Defaults to set_transient().
	 */
	public function __construct( $get_transient = null, $set_transient = null ) {
		if ( is_array( $get_transient ) ) {
			$this->overrides = $get_transient;
			$get_transient   = null;
		}
		$this->get_transient = null !== $get_transient ? $get_transient : 'get_transient';
		$this->set_transient = null !== $set_transient ? $set_transient : 'set_transient';
	}

	/**
	 * Host name from a replacement target (bare host, path, or URL).
	 *
	 * @param string $target Replacement target.
	 */
	public static function host_of( string $target ): string {
		$stripped = preg_replace( '#^[a-z]+://#i', '', trim( $target ) );
		$host     = strtok( (string) $stripped, '/' );

		return ( false === $host ) ? '' : $host;
	}

	/**
	 * Canonical probe paths keyed by mirror host.
	 *
	 * Paths match the URLs the plugin actually emits, not upstream CDN
	 * conventions and not the document root.
	 *
	 * @return array<string, string> Host => path.
	 */
	public static function probe_targets(): array {
		$wp_version = isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : '6.8';

		$targets = array(
			'cdnjs.admincdn.com'       => '/jquery/3.7.1/jquery.min.js',
			'jsd.admincdn.com'         => '/npm/jquery@3.7.1/dist/jquery.min.js',
			'googleajax.admincdn.com'  => '/ajax/libs/jquery/3.7.1/jquery.min.js',
			'googlefonts.admincdn.com' => '/css2?family=Roboto:wght@400',
			'wpstatic.admincdn.com'    => '/' . $wp_version . '/wp-admin/css/common.min.css',
			'ts.wenpai.net'            => '/wp-content/themes/twentytwentyfour/screenshot.png',
		);

		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'wpcy_mirror_probe_targets', $targets );
			if ( function_exists( 'apply_filters_deprecated' ) ) {
				$filtered = apply_filters_deprecated(
					'wp_china_yes_mirror_probe_targets',
					array( $filtered ),
					'4.0.0',
					'wpcy_mirror_probe_targets'
				);
			}
			if ( is_array( $filtered ) ) {
				return $filtered;
			}
		}

		return $targets;
	}

	/**
	 * Whether $host is currently treated as healthy.
	 *
	 * Unknown is healthy so a missing cache does not drop acceleration.
	 *
	 * @param string $host Mirror host, e.g. jsd.admincdn.com.
	 */
	public function is_healthy( string $host ): bool {
		if ( array_key_exists( $host, $this->overrides ) ) {
			return (bool) $this->overrides[ $host ];
		}

		if ( '' === $host ) {
			return true;
		}

		if ( 'get_transient' === $this->get_transient && ! function_exists( 'get_transient' ) ) {
			return true;
		}

		$state = ( $this->get_transient )( self::STATE_PREFIX . md5( $host ) );

		return 'down' !== $state;
	}

	/**
	 * Hosts currently marked down, for diagnostics notices.
	 *
	 * @return list<string>
	 */
	public function unhealthy_hosts(): array {
		$down = array();

		foreach ( array_keys( self::probe_targets() ) as $host ) {
			if ( ! $this->is_healthy( $host ) ) {
				$down[] = $host;
			}
		}

		return $down;
	}

	/**
	 * Record a host state. Used by tests and later PublicAssets probes.
	 *
	 * @param string $host  Mirror host.
	 * @param string $state 'up' or 'down'.
	 * @param int    $ttl   Cache lifetime in seconds.
	 */
	public function remember( string $host, string $state, int $ttl ): void {
		( $this->set_transient )( self::STATE_PREFIX . md5( $host ), $state, $ttl );
	}
}
