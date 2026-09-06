<?php
/**
 * Providers REST: five endpoints, error codes, no secrets in JSON.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Rest;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Providers\ProviderService;
use WenPai\ChinaYes\Providers\Store;
use WenPai\ChinaYes\Providers\WcAmClient;
use WenPai\ChinaYes\Rest\ProvidersController;
use WenPai\ChinaYes\Rest\RestError;
use WenPai\ChinaYes\Rest\RestModule;
use WenPai\ChinaYes\Services\SiteBinding\SiteBindingModule;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WenPai\ChinaYes\Tests\Unit\Services\SiteBinding\BindingStore;
use WP_Error;
use WP_REST_Request;

require_once dirname( __DIR__ ) . '/Providers/wp-providers-stubs.php';

/**
 * Providers.md §3 shape and messages.
 */
class ProvidersControllerTest extends TestCase {

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		RestStore::reset();
		OptionStore::reset();
		BindingStore::reset();
		RestError::reset();
		$GLOBALS['wpcy_test_plugins'] = array();
	}

	/**
	 * GET /providers lists both presets.
	 */
	public function test_get_items_shape() {
		$controller = new ProvidersController( $this->service( 'unbound' ) );
		$response   = $controller->get_items( new WP_REST_Request() );
		$data       = $response->get_data();
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'unbound', $data['binding_status'] );
		$this->assertCount( 2, $data['providers'] );
		$this->assertSame( 'weixiaoduo-mall', $data['providers'][0]['id'] );
		$this->assertSame( 'wenpai-marketplace', $data['providers'][1]['id'] );
		$this->assertSame( 'coming_soon', $data['providers'][1]['status'] );
		$this->assertSame( 'disconnected', $data['providers'][0]['connection'] );
		$this->secret_free( $data, 'alice@example.com' );
	}

	/**
	 * Unknown id is 404 with frozen message.
	 */
	public function test_unknown_id_is_404() {
		$controller      = new ProvidersController( $this->service( 'bound' ) );
		$request         = new WP_REST_Request();
		$request->params = array( 'id' => 'custom-bridge-api' );
		$result          = $controller->connect( $request );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_provider_unknown', $result->get_error_code() );
		$this->assertSame( 404, $result->get_error_data()['status'] );
		$this->assertSame( '暂时无法找到该供应商。', $result->get_error_message() );
	}

	/**
	 * Unbound connect is 403 with frozen message.
	 */
	public function test_connect_unbound_message() {
		$controller      = new ProvidersController( $this->service( 'unbound' ) );
		$request         = new WP_REST_Request();
		$request->params = array( 'id' => 'weixiaoduo-mall' );
		$request->json   = array(
			'email'       => 'alice@example.com',
			'license_key' => 'key',
		);
		$result          = $controller->connect( $request );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_provider_binding_required', $result->get_error_code() );
		$this->assertSame( '暂时无法连接供应商，请先绑定本站。', $result->get_error_message() );
	}

	/**
	 * Coming soon message is frozen.
	 */
	public function test_connect_coming_soon_message() {
		$controller      = new ProvidersController( $this->service( 'bound' ) );
		$request         = new WP_REST_Request();
		$request->params = array( 'id' => 'wenpai-marketplace' );
		$request->json   = array(
			'email'       => 'alice@example.com',
			'license_key' => 'key',
		);
		$result          = $controller->connect( $request );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_provider_coming_soon', $result->get_error_code() );
		$this->assertSame( '文派集市即将开放，现在还不能连接。', $result->get_error_message() );
	}

	/**
	 * Successful connect returns the public item, no secrets.
	 */
	public function test_connect_success_shape() {
		BindingStore::$responses = array(
			array(
				'code' => 200,
				'body' => '{"success":true}',
			),
			array(
				'code' => 200,
				'body' => '{"success":true,"data":{"product_list":[]}}',
			),
		);
		$controller              = new ProvidersController( $this->service( 'bound' ) );
		$request                 = new WP_REST_Request();
		$request->params         = array( 'id' => 'weixiaoduo-mall' );
		$request->json           = array(
			'email'       => 'alice@example.com',
			'license_key' => 'WXD-SECRET-KEY',
		);
		$response                = $controller->connect( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'connected', $data['connection'] );
		$this->assertSame( 'a***@example.com', $data['email_masked'] );
		$this->secret_free( $data, 'alice@example.com' );
	}

	/**
	 * GET products empty when disconnected.
	 */
	public function test_products_empty_when_disconnected() {
		$controller      = new ProvidersController( $this->service( 'bound' ) );
		$request         = new WP_REST_Request();
		$request->params = array( 'id' => 'weixiaoduo-mall' );
		$response        = $controller->get_products( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'products' => array() ), $response->get_data() );
	}

	/**
	 * DELETE is idempotent disconnected.
	 */
	public function test_delete_is_disconnected() {
		$controller      = new ProvidersController( $this->service( 'bound' ) );
		$request         = new WP_REST_Request();
		$request->params = array( 'id' => 'weixiaoduo-mall' );
		$response        = $controller->delete_item( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'disconnected', $response->get_data()['connection'] );
		$this->secret_free( $response->get_data(), 'alice@example.com' );
	}

	/**
	 * Test without connect is 400 frozen message.
	 */
	public function test_not_connected_message() {
		$controller      = new ProvidersController( $this->service( 'bound' ) );
		$request         = new WP_REST_Request();
		$request->params = array( 'id' => 'weixiaoduo-mall' );
		$result          = $controller->test_item( $request );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_provider_not_connected', $result->get_error_code() );
		$this->assertSame( '暂时无法测试连接，请先连接该供应商。', $result->get_error_message() );
	}

	/**
	 * RestModule registers the five provider routes.
	 */
	public function test_rest_module_registers_provider_routes() {
		$module = new RestModule( new Repository() );
		$module->register_routes();
		$routes = array();
		foreach ( RestStore::$routes as $row ) {
			$routes[] = $row['route'];
		}
		$this->assertContains( '/providers', $routes );
		$this->assertContains( '/providers/(?P<id>[a-z0-9\-]+)/connect', $routes );
		$this->assertContains( '/providers/(?P<id>[a-z0-9\-]+)', $routes );
		$this->assertContains( '/providers/(?P<id>[a-z0-9\-]+)/test', $routes );
		$this->assertContains( '/providers/(?P<id>[a-z0-9\-]+)/products', $routes );
	}

	/**
	 * JSON must not contain license_key or the full email.
	 *
	 * @param mixed  $data  Payload.
	 * @param string $email Full email used in the request.
	 */
	private function secret_free( $data, string $email ): void {
		$json = wp_json_encode( $data );
		$this->assertIsString( $json );
		$this->assertSame( 0, preg_match( '/license_key|' . preg_quote( $email, '/' ) . '/', $json ) );
		$this->assertStringNotContainsString( 'instance', $json );
	}

	/**
	 * Bound or unbound service.
	 *
	 * @param string $status Binding status.
	 */
	private function service( string $status ): ProviderService {
		OptionStore::$options[ Schema::SITE_IDENTITY ] = array(
			'schema_version' => 1,
			'site_uuid'      => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
			'binding'        => array(
				'status'       => $status,
				'site_hash'    => 'bound' === $status ? 'hash' : null,
				'credential'   => null,
				'bound_at'     => 'bound' === $status ? '2026-09-06T00:00:00Z' : null,
				'challenge_id' => null,
			),
		);
		$repo    = new Repository();
		$factory = static function ( string $api_url ) {
			return new WcAmClient(
				$api_url,
				static function ( string $url, array $args ) {
					return wp_remote_post( $url, $args );
				}
			);
		};

		return new ProviderService( new Store(), new SiteBindingModule( $repo ), $factory, 'get_plugins', null, $repo );
	}
}
