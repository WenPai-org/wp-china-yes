<?php
/**
 * Provider connect / disconnect / test / products. Uses Store, never raw secure options.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Providers;

use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Core\Logger;
use WenPai\ChinaYes\Rest\RestError;
use WenPai\ChinaYes\Services\SiteBinding\SiteBindingModule;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site-level provider operations. Binding gate: status must be bound to connect.
 */
final class ProviderService {

	/**
	 * Public + secret store.
	 *
	 * @var Store
	 */
	private Store $store;

	/**
	 * Binding snapshot source.
	 *
	 * @var SiteBindingModule
	 */
	private SiteBindingModule $binding;

	/**
	 * HTTP factory: function( string $api_url ): WcAmClient.
	 *
	 * @var callable
	 */
	private $client_factory;

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
	 * @param Store|null             $store          Option store.
	 * @param SiteBindingModule|null $binding        Binding snapshot.
	 * @param callable|null          $client_factory `fn(string $api_url): WcAmClient`.
	 * @param callable|null          $get_plugins    Defaults to get_plugins().
	 * @param Logger|null            $logger         Failure sink.
	 * @param Repository|null        $repository     Identity access when $binding is omitted.
	 */
	public function __construct( $store = null, $binding = null, $client_factory = null, $get_plugins = null, $logger = null, $repository = null ) {
		$this->store          = $store instanceof Store ? $store : new Store();
		$this->binding        = $binding instanceof SiteBindingModule
			? $binding
			: new SiteBindingModule( $repository instanceof Repository ? $repository : new Repository( $logger ), $logger );
		$log                  = $logger instanceof Logger ? $logger : null;
		$this->client_factory = null !== $client_factory
			? $client_factory
			: static function ( string $api_url ) use ( $log ) {
				return new WcAmClient( $api_url, null, $log );
			};
		$this->get_plugins    = null !== $get_plugins ? $get_plugins : static function () {
			return function_exists( 'get_plugins' ) ? get_plugins() : array();
		};
	}

	/**
	 * GET /providers envelope.
	 *
	 * @since 4.0.0
	 *
	 * @return array{binding_status: string, providers: list<array<string, mixed>>}
	 */
	public function list(): array {
		$out = array();
		foreach ( Presets::ids() as $id ) {
			$out[] = $this->public_item( $id );
		}

		return array(
			'binding_status' => $this->binding_status(),
			'providers'      => $out,
		);
	}

	/**
	 * Connect with email + license key.
	 *
	 * @since 4.0.0
	 *
	 * @param string $id          Provider id.
	 * @param string $email       Purchase email. Not stored in plaintext.
	 * @param string $license_key License key.
	 * @return array<string, mixed>|WP_Error
	 */
	public function connect( string $id, string $email, string $license_key ) {
		$preset = Presets::get( $id );
		if ( null === $preset ) {
			return $this->unknown();
		}
		if ( 'bound' !== $this->binding_status() ) {
			return RestError::make(
				'wpcy_provider_binding_required',
				__( '暂时无法连接供应商，请先绑定本站。', 'wp-china-yes' ),
				403
			);
		}
		if ( 'coming_soon' === $preset['status'] ) {
			return RestError::make(
				'wpcy_provider_coming_soon',
				__( '文派集市即将开放，现在还不能连接。', 'wp-china-yes' ),
				400
			);
		}
		$email       = trim( $email );
		$license_key = trim( $license_key );
		if ( '' === $license_key || ! $this->email_ok( $email ) ) {
			return RestError::invalid_schema();
		}

		$now      = gmdate( 'Y-m-d\TH:i:s\Z' );
		$instance = $this->store->peek_instance( $id );
		$client   = $this->client( $preset['api_url'] );
		$result   = $client->activate( $license_key, $instance );

		if ( 'unreachable' === $result['kind'] ) {
			$this->store->put_item(
				$id,
				array(
					'last_checked_at' => $now,
				)
			);
			return RestError::make(
				'wpcy_provider_unreachable',
				__( '暂时无法连接薇晓朵商城，请稍后重试。', 'wp-china-yes' ),
				503
			);
		}

		if ( 'invalid' === $result['kind'] ) {
			$this->store->put_item(
				$id,
				array(
					'connection'      => 'invalid',
					'last_checked_at' => $now,
				)
			);
			return $this->public_item( $id );
		}

		if ( ! $this->store->put_license_key( $id, $license_key ) ) {
			return RestError::make(
				'wpcy_provider_unreachable',
				__( '暂时无法连接薇晓朵商城，请稍后重试。', 'wp-china-yes' ),
				503
			);
		}
		$this->store->put_instance( $id, $instance );

		$fields = $this->store->email_fields( $email );
		$this->store->put_item(
			$id,
			array(
				'connection'      => 'connected',
				'email_masked'    => $fields['email_masked'],
				'email_hash'      => $fields['email_hash'],
				'connected_at'    => $now,
				'last_checked_at' => $now,
			)
		);

		$products = $this->refresh_products( $id, $license_key, $instance, $client );
		if ( is_int( $products ) ) {
			$this->store->put_item( $id, array( 'product_count' => $products ) );
		}

		return $this->public_item( $id );
	}

	/**
	 * Disconnect. Remote deactivate is best-effort; local keys always go.
	 *
	 * @since 4.0.0
	 *
	 * @param string $id Provider id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function disconnect( string $id ) {
		$preset = Presets::get( $id );
		if ( null === $preset ) {
			return $this->unknown();
		}

		$key      = $this->store->license_key( $id );
		$instance = $this->read_instance( $id );
		if ( is_string( $key ) && '' !== $key && '' !== $preset['api_url'] && '' !== $instance ) {
			$this->client( $preset['api_url'] )->deactivate( $key, $instance );
		}

		$this->store->disconnect( $id );
		return $this->public_item( $id );
	}

	/**
	 * Live status check.
	 *
	 * @since 4.0.0
	 *
	 * @param string $id Provider id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function test( string $id ) {
		$preset = Presets::get( $id );
		if ( null === $preset ) {
			return $this->unknown();
		}

		$key = $this->store->license_key( $id );
		if ( ! is_string( $key ) || '' === $key ) {
			return RestError::make(
				'wpcy_provider_not_connected',
				__( '暂时无法测试连接，请先连接该供应商。', 'wp-china-yes' ),
				400
			);
		}

		$now      = gmdate( 'Y-m-d\TH:i:s\Z' );
		$instance = $this->store->instance( $id );
		$result   = $this->client( $preset['api_url'] )->status( $key, $instance );
		$kind     = $result['kind'];
		if ( 'ok' === $kind ) {
			$connection = 'connected';
		} elseif ( 'invalid' === $kind ) {
			$connection = 'invalid';
		} else {
			$connection = 'unreachable';
		}

		$this->store->put_item(
			$id,
			array(
				'connection'      => $connection,
				'last_checked_at' => $now,
			)
		);

		if ( 'unreachable' === $kind ) {
			return RestError::make(
				'wpcy_provider_unreachable',
				__( '暂时无法连接薇晓朵商城，请稍后重试。', 'wp-china-yes' ),
				503
			);
		}

		return $this->public_item( $id );
	}

	/**
	 * Purchased products with installed / update_managed flags.
	 *
	 * Unconnected → empty list (200). Unreachable → stale cache ≤ 72h.
	 *
	 * @since 4.0.0
	 *
	 * @param string $id Provider id.
	 * @return array{products: list<array<string, mixed>>}|WP_Error
	 */
	public function products( string $id ) {
		$preset = Presets::get( $id );
		if ( null === $preset ) {
			return $this->unknown();
		}

		$item = $this->store->item( $id );
		$key  = $this->store->license_key( $id );
		if ( 'connected' !== $item['connection'] || ! is_string( $key ) || '' === $key ) {
			return array(
				'products' => array(),
			);
		}

		$cache = $this->store->products_cache( $id );
		if ( is_array( $cache ) && $this->store->products_fresh( $cache ) ) {
			return array(
				'products' => $this->annotate( $cache['products'] ),
			);
		}

		$instance = $this->store->instance( $id );
		$client   = $this->client( $preset['api_url'] );
		$result   = $client->product_list( $key, $instance );

		if ( 'ok' === $result['kind'] ) {
			$rows = $this->extract_products( $result['data'] );
			$this->store->put_products( $id, $rows );
			$this->store->put_item(
				$id,
				array(
					'connection'      => 'connected',
					'last_checked_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
					'product_count'   => count( $rows ),
				)
			);
			return array(
				'products' => $this->annotate( $rows ),
			);
		}

		if ( 'invalid' === $result['kind'] ) {
			$this->store->put_item(
				$id,
				array(
					'connection'      => 'invalid',
					'last_checked_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
				)
			);
			return array(
				'products' => array(),
			);
		}

		$this->store->put_item(
			$id,
			array(
				'connection'      => 'unreachable',
				'last_checked_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
			)
		);
		if ( is_array( $cache ) && $this->store->products_stale_ok( $cache ) ) {
			return array(
				'products' => $this->annotate( $cache['products'] ),
			);
		}

		return array(
			'products' => array(),
		);
	}

	/**
	 * Public REST item. No license_key, full email, or instance.
	 *
	 * @since 4.0.0
	 *
	 * @param string $id Provider id.
	 * @return array<string, mixed>
	 */
	public function public_item( string $id ): array {
		$preset = Presets::get( $id );
		$item   = $this->store->item( $id );
		$name   = is_array( $preset ) ? $preset['name'] : $id;
		$status = is_array( $preset ) ? $preset['status'] : 'coming_soon';

		return array(
			'id'              => $id,
			'name'            => $name,
			'status'          => $status,
			'connection'      => $item['connection'],
			'email_masked'    => $item['email_masked'],
			'connected_at'    => $item['connected_at'],
			'last_checked_at' => $item['last_checked_at'],
			'product_count'   => $item['product_count'],
		);
	}

	/**
	 * Option store (UpdateBridge / tests).
	 *
	 * @since 4.0.0
	 */
	public function store(): Store {
		return $this->store;
	}

	/**
	 * Binding status string.
	 *
	 * @since 4.0.0
	 */
	public function binding_status(): string {
		$snap = $this->binding->snapshot();
		return $snap['status'];
	}

	/**
	 * Whether this provider is connected (UpdateBridge).
	 *
	 * @since 4.0.0
	 *
	 * @param string $id Provider id.
	 */
	public function is_connected( string $id ): bool {
		return 'connected' === $this->store->item( $id )['connection']
			&& is_string( $this->store->license_key( $id ) );
	}

	/**
	 * WC AM update payload for one managed plugin.
	 *
	 * @since 4.0.0
	 *
	 * @param string $id          Provider id.
	 * @param string $product_id  Mall product id.
	 * @param string $slug        Directory slug.
	 * @param string $plugin_file Plugin basename.
	 * @param string $version     Installed version.
	 * @return array<string, mixed>
	 */
	public function fetch_update( string $id, string $product_id, string $slug, string $plugin_file, string $version ): array {
		$preset = Presets::get( $id );
		$key    = $this->store->license_key( $id );
		if ( null === $preset || ! is_string( $key ) || '' === $key ) {
			return array();
		}
		$instance = $this->store->instance( $id );
		$result   = $this->client( $preset['api_url'] )->update(
			$key,
			$instance,
			array(
				'product_id'  => $product_id,
				'slug'        => $slug,
				'plugin_name' => $plugin_file,
				'version'     => $version,
			)
		);
		if ( 'ok' !== $result['kind'] ) {
			return array();
		}
		$nested = $result['data']['data'] ?? null;
		if ( is_array( $nested ) ) {
			return $nested;
		}

		return $result['data'];
	}

	/**
	 * 404 unknown id.
	 *
	 * @return WP_Error
	 */
	private function unknown(): WP_Error {
		return RestError::make(
			'wpcy_provider_unknown',
			__( '暂时无法找到该供应商。', 'wp-china-yes' ),
			404
		);
	}

	/**
	 * Loose email check. WordPress is_email() when present.
	 *
	 * @param string $email Raw email.
	 */
	private function email_ok( string $email ): bool {
		if ( function_exists( 'is_email' ) ) {
			return false !== is_email( $email );
		}

		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL );
	}

	/**
	 * Client for an origin.
	 *
	 * @param string $api_url Origin.
	 */
	private function client( string $api_url ): WcAmClient {
		$client = call_user_func( $this->client_factory, $api_url );
		return $client instanceof WcAmClient ? $client : new WcAmClient( $api_url );
	}

	/**
	 * Instance option if already written.
	 *
	 * @param string $id Provider id.
	 */
	private function read_instance( string $id ): string {
		$stored = function_exists( 'get_option' ) ? get_option( $this->store->instance_option( $id ), '' ) : '';
		return is_string( $stored ) ? $stored : '';
	}

	/**
	 * Pull product_list after a successful connect.
	 *
	 * @param string     $id       Provider id.
	 * @param string     $key      License key.
	 * @param string     $instance UUID.
	 * @param WcAmClient $client   Client.
	 * @return int|null Count or null when unreachable.
	 */
	private function refresh_products( string $id, string $key, string $instance, WcAmClient $client ) {
		$result = $client->product_list( $key, $instance );
		if ( 'ok' !== $result['kind'] ) {
			return null;
		}
		$rows = $this->extract_products( $result['data'] );
		$this->store->put_products( $id, $rows );
		return count( $rows );
	}

	/**
	 * Flatten WC AM / Kestrel product_list without guessing slugs from titles.
	 *
	 * @param array<string, mixed> $data Response JSON.
	 * @return list<array{product_id: string, title: string, slug: string}>
	 */
	private function extract_products( array $data ): array {
		$list = array();
		if ( isset( $data['data']['product_list'] ) && is_array( $data['data']['product_list'] ) ) {
			$list = $data['data']['product_list'];
		} elseif ( isset( $data['product_list'] ) && is_array( $data['product_list'] ) ) {
			$list = $data['product_list'];
		}

		$products = array();
		if ( isset( $list['non_wc_subs_resources'] ) && is_array( $list['non_wc_subs_resources'] ) ) {
			$products = $list['non_wc_subs_resources'];
		} elseif ( isset( $list[0] ) ) {
			$products = $list;
		}

		$out = array();
		foreach ( $products as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$pid = '';
			if ( isset( $row['product_id'] ) ) {
				$pid = (string) $row['product_id'];
			}
			if ( '' === $pid ) {
				continue;
			}
			$title = '';
			if ( isset( $row['title'] ) && is_string( $row['title'] ) ) {
				$title = $row['title'];
			} elseif ( isset( $row['product_title'] ) && is_string( $row['product_title'] ) ) {
				$title = $row['product_title'];
			}
			$slug = '';
			if ( isset( $row['slug'] ) && is_string( $row['slug'] ) ) {
				$slug = $row['slug'];
			} elseif ( isset( $row['wp_slug'] ) && is_string( $row['wp_slug'] ) ) {
				$slug = $row['wp_slug'];
			}

			$out[] = array(
				'product_id' => $pid,
				'title'      => $title,
				'slug'       => $slug,
			);
		}

		return $out;
	}

	/**
	 * Mark installed / update_managed by exact plugin-directory match.
	 *
	 * @param list<array<string, mixed>> $rows Cached rows.
	 * @return list<array{product_id: string, title: string, slug: string, installed: bool|null, update_managed: bool}>
	 */
	private function annotate( array $rows ): array {
		$dirs = $this->installed_directories();
		$out  = array();
		foreach ( $rows as $row ) {
			$slug = isset( $row['slug'] ) && is_string( $row['slug'] ) ? $row['slug'] : '';
			$pid  = isset( $row['product_id'] ) ? (string) $row['product_id'] : '';
			if ( '' === $pid ) {
				continue;
			}
			$installed = '' === $slug ? null : isset( $dirs[ $slug ] );
			$out[]     = array(
				'product_id'     => $pid,
				'title'          => isset( $row['title'] ) && is_string( $row['title'] ) ? $row['title'] : '',
				'slug'           => $slug,
				'installed'      => $installed,
				'update_managed' => true === $installed,
			);
		}

		return $out;
	}

	/**
	 * Plugin directory names currently installed.
	 *
	 * @return array<string, string> directory => plugin file.
	 */
	private function installed_directories(): array {
		$plugins = call_user_func( $this->get_plugins );
		if ( ! is_array( $plugins ) ) {
			return array();
		}
		$dirs = array();
		foreach ( $plugins as $file => $data ) {
			unset( $data );
			$file = (string) $file;
			$dir  = dirname( $file );
			if ( '.' === $dir || '' === $dir ) {
				$dir = $file;
			}
			$dirs[ $dir ] = $file;
		}

		return $dirs;
	}
}
