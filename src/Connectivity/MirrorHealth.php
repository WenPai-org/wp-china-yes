<?php
/**
 * Mirror node health. Unknown hosts are treated as healthy.
 *
 * Ported from Service/MirrorHealth.php (host_of, is_healthy, probe_targets).
 * Probing itself stays in 3.x / M1-06; this class only reads cached state.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Connectivity;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Health lookup for URL rewrite targets.
 */
final class MirrorHealth {

	public const STATE_PREFIX = 'wpcy_mirror_state_';

	/**
	 * Optional host => healthy overrides for tests.
	 *
	 * @var array<string, bool>
	 */
	private $states;

	/**
	 * Create a health reader.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, bool> $states Host overrides; missing hosts fall through to transients / unknown=healthy.
	 */
	public function __construct( array $states = array() ) {
		$this->states = $states;
	}

	/**
	 * Host from a replacement target (bare host, host/path, or URL).
	 *
	 * @since 4.0.0
	 *
	 * @param string $target Replacement target.
	 */
	public static function host_of( string $target ): string {
		$stripped = preg_replace( '#^[a-z]+://#i', '', trim( $target ) );
		$host     = strtok( is_string( $stripped ) ? $stripped : '', '/' );

		return ( false === $host ) ? '' : $host;
	}

	/**
	 * Whether $host may be used as a rewrite target.
	 *
	 * Unknown (no override, no transient, or transient not `down`) is healthy,
	 * matching 3.x Service\MirrorHealth::is_healthy().
	 *
	 * @since 4.0.0
	 *
	 * @param string $host Hostname, e.g. jsd.admincdn.com.
	 */
	public function is_healthy( string $host ): bool {
		if ( array_key_exists( $host, $this->states ) ) {
			return $this->states[ $host ];
		}

		if ( '' === $host ) {
			return true;
		}

		if ( ! function_exists( 'get_transient' ) ) {
			return true;
		}

		$state = get_transient( self::STATE_PREFIX . md5( $host ) );

		return 'down' !== $state;
	}

	/**
	 * Canonical probe paths. Must match URLs the plugin actually emits.
	 *
	 * Wpstatic / ts are listed for host-parse tests and later diagnostics.
	 * PublicAssets does not rewrite to them.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, string> host => path
	 */
	public static function probe_targets(): array {
		$wp_version = isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : '6.8';

		return array(
			'cdnjs.admincdn.com'       => '/jquery/3.7.1/jquery.min.js',
			'jsd.admincdn.com'         => '/npm/jquery@3.7.1/dist/jquery.min.js',
			'googleajax.admincdn.com'  => '/ajax/libs/jquery/3.7.1/jquery.min.js',
			'googlefonts.admincdn.com' => '/css2?family=Roboto:wght@400',
			'wpstatic.admincdn.com'    => '/' . $wp_version . '/wp-admin/css/common.min.css',
			'ts.wenpai.net'            => '/wp-content/themes/twentytwentyfour/screenshot.png',
		);
	}

	/**
	 * Hosts currently marked down among probe_targets().
	 *
	 * @since 4.0.0
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
}
