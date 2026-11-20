<?php
/**
 * Product plugins require this file only, at the top level of the plugin
 * main file, not inside a hook, for example with require_once of
 * __DIR__ . "/lib/wenpai-admin-ui/php/load.php".
 *
 * Each vendor copy registers itself. plugins_loaded (priority 1) then
 * requires the highest VERSION. A copy registered after that is ignored;
 * with WP_DEBUG on, _doing_it_wrong names the plugin that registered late.
 * wenpai_admin_ui_info() tells which copy is active. Do not require the
 * class-*.php files from the product plugin — that would declare classes
 * from a losing copy.
 *
 * A copy older than 0.2.5 (e.g. 0.2.3, shipped by wpsss) defines boot() with
 * only three class files. Whichever load.php is required first wins the
 * function names, so its boot() loads the winning copy but skips Settings and
 * Menu. wenpai_admin_ui_complete() (plugins_loaded priority 2) loads whatever
 * is missing; the old copy cannot define it.
 *
 * @package Wenpai_Admin_UI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wenpai_admin_ui_owner' ) ) {
	/**
	 * Best guess of the plugin (or theme) that ships a kit copy, from its path.
	 *
	 * @param string $dir Kit copy directory.
	 * @return string Plugin folder (e.g. wpslug), mu-plugins/{name}, theme:{name}, or the path.
	 */
	function wenpai_admin_ui_owner( $dir ) {
		$dir   = str_replace( '\\', '/', (string) $dir );
		$roots = array();
		if ( defined( 'WP_PLUGIN_DIR' ) ) {
			$roots[''] = WP_PLUGIN_DIR;
		}
		if ( defined( 'WPMU_PLUGIN_DIR' ) ) {
			$roots['mu-plugins/'] = WPMU_PLUGIN_DIR;
		}
		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$roots['theme:'] = WP_CONTENT_DIR . '/themes';
		}
		foreach ( $roots as $prefix => $root ) {
			$root = rtrim( str_replace( '\\', '/', (string) $root ), '/' ) . '/';
			if ( 0 === strpos( $dir, $root ) ) {
				$rest = explode( '/', substr( $dir, strlen( $root ) ) );
				return $prefix . $rest[0];
			}
		}
		return $dir;
	}
}

if ( ! function_exists( 'wenpai_admin_ui_register' ) ) {
	/**
	 * Register this kit copy as a boot candidate.
	 *
	 * After boot (plugins_loaded priority 1) a copy can no longer win: it is
	 * recorded under 'late' in wenpai_admin_ui_info() and, with WP_DEBUG on,
	 * reported with _doing_it_wrong naming the plugin.
	 *
	 * @param string $version SemVer from VERSION file.
	 * @param string $dir     Absolute path to this kit copy (parent of php/).
	 */
	function wenpai_admin_ui_register( $version, $dir ) {
		if ( ! isset( $GLOBALS['wenpai_admin_ui_candidates'] ) || ! is_array( $GLOBALS['wenpai_admin_ui_candidates'] ) ) {
			$GLOBALS['wenpai_admin_ui_candidates'] = array();
		}
		$entry           = array(
			'version' => (string) $version,
			'dir'     => rtrim( (string) $dir, '/\\' ),
		);
		$entry['plugin'] = wenpai_admin_ui_owner( $entry['dir'] );

		// The same copy required twice (require instead of require_once) is not a second candidate.
		foreach ( $GLOBALS['wenpai_admin_ui_candidates'] as $known ) {
			if ( $known['dir'] === $entry['dir'] ) {
				return;
			}
		}

		if ( empty( $GLOBALS['wenpai_admin_ui_booted'] ) ) {
			$GLOBALS['wenpai_admin_ui_candidates'][] = $entry;
			return;
		}

		$GLOBALS['wenpai_admin_ui_late'][] = $entry;
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( '_doing_it_wrong' ) ) {
			$win = isset( $GLOBALS['wenpai_admin_ui_winner'] ) ? $GLOBALS['wenpai_admin_ui_winner'] : array(
				'version' => '?',
				'plugin'  => '?',
			);
			$msg = sprintf(
				'wenpai-admin-ui %1$s from 「%2$s」 was registered after plugins_loaded priority 1 and is ignored; %3$s from 「%4$s」 is active. Require lib/wenpai-admin-ui/php/load.php at the top level of the plugin main file, not inside a hook.',
				$entry['version'],
				$entry['plugin'],
				isset( $win['version'] ) ? $win['version'] : '?',
				isset( $win['plugin'] ) ? $win['plugin'] : '?'
			);
			_doing_it_wrong( 'wenpai_admin_ui_register', $msg, '0.3.0' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- developer log text built from version strings and plugin folder names, no HTML; core escapes it when displaying.
		}
	}
}

if ( ! function_exists( 'wenpai_admin_ui_boot' ) ) {
	/**
	 * Order kit copies: highest VERSION first. Two copies with the same
	 * version (e.g. two 0.3.0-dev snapshots) are ordered by directory path,
	 * so the winner does not depend on plugin load order. Snapshots are not
	 * compared by content or mtime: ship a real version before release.
	 *
	 * @param array $a Candidate ( version, dir ).
	 * @param array $b Candidate ( version, dir ).
	 * @return int
	 */
	function wenpai_admin_ui_compare( $a, $b ) {
		$c = version_compare( $b['version'], $a['version'] );
		return 0 !== $c ? $c : strcmp( $a['dir'], $b['dir'] );
	}

	/**
	 * Load the newest candidate. Safe to call more than once.
	 */
	function wenpai_admin_ui_boot() {
		if ( ! empty( $GLOBALS['wenpai_admin_ui_booted'] ) ) {
			return;
		}

		$candidates = isset( $GLOBALS['wenpai_admin_ui_candidates'] ) && is_array( $GLOBALS['wenpai_admin_ui_candidates'] )
			? $GLOBALS['wenpai_admin_ui_candidates']
			: array();

		if ( array() === $candidates ) {
			return;
		}

		usort( $candidates, 'wenpai_admin_ui_compare' );

		$winner                            = $candidates[0];
		$GLOBALS['wenpai_admin_ui_winner'] = $winner;
		$GLOBALS['wenpai_admin_ui_booted'] = true;

		$dir   = $winner['dir'] . '/php/';
		$files = array(
			'class-wenpai-admin-loader.php',
			'class-wenpai-admin-shell.php',
			'class-wenpai-admin-icons.php',
			'class-wenpai-admin-menu.php',
			'class-wenpai-admin-ui.php',
			'class-wenpai-admin-settings.php',
			'class-wenpai-admin-action.php',
			'class-wenpai-admin-capability.php',
			'class-wenpai-admin-tools.php',
			'class-wenpai-admin-collection.php',
		);
		foreach ( $files as $file ) {
			$path = $dir . $file;
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
	}
}

if ( ! function_exists( 'wenpai_admin_ui_info' ) ) {
	/**
	 * Which kit copy is active, for debugging and support.
	 *
	 * @return array{booted: bool, version: string, dir: string, plugin: string, candidates: array, late: array}
	 *         version/dir/plugin are '' before boot. Each candidate / late entry is
	 *         array{version, dir, plugin}.
	 */
	function wenpai_admin_ui_info() {
		$win = isset( $GLOBALS['wenpai_admin_ui_winner'] ) && is_array( $GLOBALS['wenpai_admin_ui_winner'] ) ? $GLOBALS['wenpai_admin_ui_winner'] : array();
		return array(
			'booted'     => ! empty( $GLOBALS['wenpai_admin_ui_booted'] ),
			'version'    => isset( $win['version'] ) ? (string) $win['version'] : '',
			'dir'        => isset( $win['dir'] ) ? (string) $win['dir'] : '',
			'plugin'     => isset( $win['plugin'] ) ? (string) $win['plugin'] : '',
			'candidates' => isset( $GLOBALS['wenpai_admin_ui_candidates'] ) && is_array( $GLOBALS['wenpai_admin_ui_candidates'] ) ? array_values( $GLOBALS['wenpai_admin_ui_candidates'] ) : array(),
			'late'       => isset( $GLOBALS['wenpai_admin_ui_late'] ) && is_array( $GLOBALS['wenpai_admin_ui_late'] ) ? array_values( $GLOBALS['wenpai_admin_ui_late'] ) : array(),
		);
	}
}

if ( ! function_exists( 'wenpai_admin_ui_winner_dir' ) ) {
	/**
	 * Absolute directory of the winning kit copy.
	 *
	 * @return string Empty before boot.
	 */
	function wenpai_admin_ui_winner_dir() {
		if ( empty( $GLOBALS['wenpai_admin_ui_winner']['dir'] ) ) {
			return '';
		}
		return (string) $GLOBALS['wenpai_admin_ui_winner']['dir'];
	}
}

wenpai_admin_ui_register(
	trim( (string) file_get_contents( dirname( __DIR__ ) . '/VERSION' ) ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local VERSION file shipped with the kit, not a remote URL; runs before WP_Filesystem is available.
	dirname( __DIR__ )
);

if ( ! function_exists( 'wenpai_admin_ui_complete' ) ) {
	/**
	 * Load class files a pre-0.2.5 boot() leaves out (0.3.0+).
	 *
	 * An older copy's boot() only requires Loader, Shell and Icons, and its
	 * function names win when that copy is required first (network-activated
	 * plugins load before regular ones). This runs right after boot and
	 * requires the rest from the winning copy, so a 0.3.0+ winner still gets
	 * Settings, Menu, UI, Action, Capability, Tools and Collection.
	 */
	function wenpai_admin_ui_complete() {
		if ( empty( $GLOBALS['wenpai_admin_ui_winner']['dir'] ) ) {
			return;
		}
		$dir = $GLOBALS['wenpai_admin_ui_winner']['dir'] . '/php/';
		// Glob, not a hard-coded list: this function body may come from an older
		// copy that was required first, so it must load whatever the winner ships.
		foreach ( (array) glob( $dir . 'class-wenpai-admin-*.php' ) as $path ) {
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
	}
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'plugins_loaded', 'wenpai_admin_ui_boot', 1 );
	add_action( 'plugins_loaded', 'wenpai_admin_ui_complete', 2 );
}
