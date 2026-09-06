<?php
/**
 * Fill update_plugins transient from WC AM for exact-matched installed products.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Providers;

use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Core\Logger;
use WenPai\ChinaYes\Core\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module id `providers`. Hooks only when not in recovery_mode.
 *
 * Does not guess slugs from titles and does not run AutoMatcher.
 */
final class UpdateBridge implements Module {

	/**
	 * Settings (recovery_mode).
	 *
	 * @var Repository
	 */
	private Repository $config;

	/**
	 * Provider operations.
	 *
	 * @var ProviderService
	 */
	private ProviderService $service;

	/**
	 * Plugin-list stand-in.
	 *
	 * @var callable
	 */
	private $get_plugins;

	/**
	 * Constructor. Does not register hooks.
	 *
	 * @since 4.0.0
	 *
	 * @param Repository|null      $config      Settings.
	 * @param ProviderService|null $service     Provider API.
	 * @param callable|null        $get_plugins Defaults to get_plugins().
	 * @param Logger|null          $logger      Failure sink.
	 */
	public function __construct( $config = null, $service = null, $get_plugins = null, $logger = null ) {
		$this->config      = $config instanceof Repository ? $config : new Repository( $logger );
		$this->service     = $service instanceof ProviderService ? $service : new ProviderService( null, null, null, $get_plugins, $logger, $this->config );
		$this->get_plugins = null !== $get_plugins ? $get_plugins : static function () {
			return function_exists( 'get_plugins' ) ? get_plugins() : array();
		};
	}

	/**
	 * Module id.
	 *
	 * @since 4.0.0
	 */
	public function id(): string {
		return 'providers';
	}

	/**
	 * Every scene: update checks run in admin, cron, and REST.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function contexts(): array {
		return Environment::CONTEXTS;
	}

	/**
	 * No graph edges.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function dependencies(): array {
		return array();
	}

	/**
	 * Hook the update transient. Skip entirely in recovery_mode.
	 *
	 * @since 4.0.0
	 */
	public function register(): void {
		if ( $this->in_recovery() ) {
			return;
		}
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject' ), 20, 1 );
	}

	/**
	 * Fill response[] for update_managed products of connected providers.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $transient Site transient.
	 * @return mixed
	 */
	public function inject( $transient ) {
		if ( $this->in_recovery() ) {
			return $transient;
		}
		if ( ! is_object( $transient ) ) {
			$transient = (object) array(
				'response' => array(),
			);
		}
		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}

		$plugins = call_user_func( $this->get_plugins );
		$plugins = is_array( $plugins ) ? $plugins : array();

		foreach ( Presets::ids() as $id ) {
			if ( ! $this->service->is_connected( $id ) ) {
				continue;
			}
			$list = $this->service->products( $id );
			if ( is_wp_error( $list ) ) {
				continue;
			}
			$rows = $list['products'];
			foreach ( $rows as $row ) {
				if ( empty( $row['update_managed'] ) ) {
					continue;
				}
				$slug = isset( $row['slug'] ) && is_string( $row['slug'] ) ? $row['slug'] : '';
				$file = $this->plugin_file( $slug, $plugins );
				if ( '' === $file ) {
					continue;
				}
				$version = '';
				if ( isset( $plugins[ $file ]['Version'] ) ) {
					$version = (string) $plugins[ $file ]['Version'];
				}
				$data    = $this->service->fetch_update(
					$id,
					(string) $row['product_id'],
					$slug,
					$file,
					$version
				);
				$package = $this->package_url( $data );
				$new     = $this->new_version( $data );
				if ( '' === $package && '' === $new ) {
					continue;
				}
				$transient->response[ $file ] = (object) array(
					'id'          => $file,
					'slug'        => $slug,
					'plugin'      => $file,
					'new_version' => $new,
					'package'     => $package,
				);
			}
		}

		return $transient;
	}

	/**
	 * Whether recovery_mode is on.
	 */
	private function in_recovery(): bool {
		return true === $this->config->get( 'recovery_mode', false );
	}

	/**
	 * Plugin basename whose directory equals $slug.
	 *
	 * @param string               $slug    Directory.
	 * @param array<string, mixed> $plugins get_plugins() map.
	 */
	private function plugin_file( string $slug, array $plugins ): string {
		if ( '' === $slug ) {
			return '';
		}
		foreach ( $plugins as $file => $data ) {
			unset( $data );
			$file = (string) $file;
			$dir  = dirname( $file );
			if ( $dir === $slug ) {
				return $file;
			}
		}

		return '';
	}

	/**
	 * Package URL from a WC AM update payload.
	 *
	 * @param array<string, mixed> $data Payload.
	 */
	private function package_url( array $data ): string {
		foreach ( array( 'package', 'package_url', 'download_link' ) as $key ) {
			if ( isset( $data[ $key ] ) && is_string( $data[ $key ] ) && '' !== $data[ $key ] ) {
				return $data[ $key ];
			}
		}
		if ( isset( $data['package'] ) && is_array( $data['package'] ) && isset( $data['package']['url'] ) ) {
			return (string) $data['package']['url'];
		}

		return '';
	}

	/**
	 * New version from a WC AM update payload.
	 *
	 * @param array<string, mixed> $data Payload.
	 */
	private function new_version( array $data ): string {
		foreach ( array( 'new_version', 'version', 'new_version_number' ) as $key ) {
			if ( isset( $data[ $key ] ) && ( is_string( $data[ $key ] ) || is_numeric( $data[ $key ] ) ) ) {
				return (string) $data[ $key ];
			}
		}

		return '';
	}
}
