<?php
/**
 * Suite interconnect & conflict endpoints:
 * - GET  /wpcy/v1/diagnostics/conflicts
 * - POST /wpcy/v1/suite/provision
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Rest;

use WenPai\ChinaYes\Diagnostics\ConflictDetector;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes companion synergy/conflict scans and allowlisted WenPai companion installation/activation.
 */
final class SuiteController {

	/**
	 * Permission check for provisioning companion plugins: requires `activate_plugins` + valid write nonce.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return true|WP_Error
	 */
	public static function permission_provision( WP_REST_Request $request ) {
		$base = Permissions::manage_options_write( $request );
		if ( true !== $base ) {
			return $base;
		}
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return RestError::forbidden();
		}
		return true;
	}

	/**
	 * Return live companion synergy and third-party conflict scan.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function get_conflicts( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return RestError::ok( ConflictDetector::scan() );
	}

	/**
	 * Install and/or activate selected allowlisted WenPai companion plugins.
	 *
	 * Never accepts arbitrary third-party slugs; every requested slug must exist in
	 * `ConflictDetector::COMPANIONS`.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function provision( WP_REST_Request $request ): WP_REST_Response {
		$params    = $request->get_json_params();
		$raw_slugs = isset( $params['slugs'] ) && is_array( $params['slugs'] ) ? $params['slugs'] : array();
		$slugs     = array();
		foreach ( $raw_slugs as $raw ) {
			if ( ! is_string( $raw ) ) {
				continue;
			}
			$slug = sanitize_key( $raw );
			if ( '' !== $slug && isset( ConflictDetector::COMPANIONS[ $slug ] ) ) {
				$slugs[ $slug ] = true;
			}
		}

		$results = array();
		foreach ( array_keys( $slugs ) as $slug ) {
			$results[] = $this->provision_one( $slug, ConflictDetector::COMPANIONS[ $slug ] );
		}

		return RestError::ok(
			array(
				'results' => $results,
			)
		);
	}

	/**
	 * Provision a single allowlisted WenPai companion plugin.
	 *
	 * @param string                                                                                    $slug Companion slug.
	 * @param array{file: string, const: string, name: string, top: bool, url: string, synergy: string} $meta Companion metadata.
	 * @return array{slug: string, name: string, status: string, url: string}
	 */
	private function provision_one( string $slug, array $meta ): array {
		if ( ConflictDetector::is_companion_active( $slug ) ) {
			return array(
				'slug'   => $slug,
				'name'   => $meta['name'],
				'status' => 'active',
				'url'    => $meta['url'],
			);
		}

		if ( defined( 'ABSPATH' ) ) {
			$plugin_inc = ABSPATH . 'wp-admin/includes/plugin.php';
			if ( ! function_exists( 'get_plugins' ) && is_readable( $plugin_inc ) ) {
				require_once $plugin_inc;
			}
		}

		$installed_file = $this->find_installed_file( $slug, $meta['file'] );

		if ( '' === $installed_file && function_exists( 'current_user_can' ) && current_user_can( 'install_plugins' ) ) {
			$installed_file = $this->attempt_core_install( $slug, $meta['file'] );
		}

		if ( '' !== $installed_file && function_exists( 'activate_plugin' ) ) {
			$act = activate_plugin( $installed_file, '', false, true );
			if ( ! ( $act instanceof WP_Error ) ) {
				return array(
					'slug'   => $slug,
					'name'   => $meta['name'],
					'status' => 'activated',
					'url'    => $meta['url'],
				);
			}
		}

		return array(
			'slug'   => $slug,
			'name'   => $meta['name'],
			'status' => 'manual',
			'url'    => $meta['url'],
		);
	}

	/**
	 * Locate an installed plugin basename matching `$default_file` or `$slug/*`.
	 *
	 * @param string $slug         Directory slug.
	 * @param string $default_file Expected relative file.
	 * @return string Empty string when not installed.
	 */
	private function find_installed_file( string $slug, string $default_file ): string {
		if ( ! function_exists( 'get_plugins' ) ) {
			return '';
		}
		if ( function_exists( 'wp_clean_plugins_cache' ) ) {
			wp_clean_plugins_cache( false );
		}
		$all = get_plugins();
		if ( isset( $all[ $default_file ] ) ) {
			return $default_file;
		}
		foreach ( array_keys( $all ) as $file ) {
			$file_str = (string) $file;
			if ( 0 === strpos( $file_str, $slug . '/' ) ) {
				return $file_str;
			}
		}
		return '';
	}

	/**
	 * Attempt silent download & unpack via WordPress core `Plugin_Upgrader` (accelerated by WPCY mirror).
	 *
	 * @param string $slug         Plugin slug.
	 * @param string $default_file Expected relative file.
	 * @return string Installed plugin basename, or `''` when unavailable.
	 */
	private function attempt_core_install( string $slug, string $default_file ): string {
		if ( ! defined( 'ABSPATH' ) ) {
			return '';
		}
		foreach ( array( 'plugin-install.php', 'file.php', 'misc.php', 'class-wp-upgrader.php' ) as $inc ) {
			$path = ABSPATH . 'wp-admin/includes/' . $inc;
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
		if ( ! function_exists( 'plugins_api' ) || ! class_exists( '\Plugin_Upgrader' ) || ! class_exists( '\Automatic_Upgrader_Skin' ) ) {
			return '';
		}

		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $slug,
				'fields' => array(
					'sections' => false,
				),
			)
		);
		if ( $api instanceof WP_Error || ! is_object( $api ) || empty( $api->download_link ) || ! is_string( $api->download_link ) ) {
			return '';
		}

		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$res      = $upgrader->install( $api->download_link );
		if ( true !== $res ) {
			return '';
		}

		return $this->find_installed_file( $slug, $default_file );
	}
}
