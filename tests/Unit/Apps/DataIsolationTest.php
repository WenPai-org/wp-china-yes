<?php
/**
 * App data isolation, key rules, 64KB cap, and REST permission cut.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Apps;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Apps\AppsModule;
use WenPai\ChinaYes\Apps\DataStore;
use WenPai\ChinaYes\Apps\EntitlementsClient;
use WenPai\ChinaYes\Apps\ExhaustedEntitlements;
use WenPai\ChinaYes\Apps\Index;
use WenPai\ChinaYes\Apps\ManifestVerifier;
use WenPai\ChinaYes\Apps\Registry;
use WenPai\ChinaYes\Rest\AppsController;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

require_once __DIR__ . '/wp-apps-stubs.php';

/**
 * Acceptance: A cannot read B; bad key 400; PUT 65KB 413; undeclared permission 403.
 */
class DataIsolationTest extends TestCase {

	/**
	 * Reset transients.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		AppsStore::reset();
	}

	/**
	 * DataStore: motusnap cannot read noteboard's key.
	 */
	public function test_store_app_a_cannot_read_app_b() {
		$store = new DataStore();
		$this->assertTrue( $store->put( 'noteboard', 'settings', array( 'secret' => 1 ) ) );
		$this->assertTrue( $store->put( 'motusnap', 'settings', array( 'own' => true ) ) );

		$this->assertSame( array( 'own' => true ), $store->get( 'motusnap', 'settings' ) );
		$this->assertNull( $store->get( 'motusnap', 'noteboard-only' ) );
		$this->assertSame( array( 'secret' => 1 ), $store->get( 'noteboard', 'settings' ) );
		$this->assertSame( array( 'settings' ), $store->list_keys( 'motusnap' ) );
		$this->assertSame( array( 'settings' ), $store->list_keys( 'noteboard' ) );
	}

	/**
	 * REST: motusnap GET of a key that only noteboard stored is 404.
	 */
	public function test_rest_app_a_cannot_read_app_b_key() {
		$store = new DataStore();
		$store->put( 'noteboard', 'notes', array( 'x' => 1 ) );
		$controller = $this->controller( $store );

		$request         = new WP_REST_Request();
		$request->params = array(
			'id'  => 'motusnap',
			'key' => 'notes',
		);
		$result          = $controller->get_data( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 404, $result->get_error_data()['status'] );
	}

	/**
	 * Noteboard has only data:read: PUT is 403 forbidden_permission.
	 */
	public function test_undeclared_permission_is_forbidden() {
		$controller      = $this->controller();
		$request         = new WP_REST_Request();
		$request->params = array(
			'id'  => 'noteboard',
			'key' => 'settings',
		);
		$request->json   = array( 'value' => 1 );

		$result = $controller->put_data( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_apps_forbidden_permission', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	/**
	 * Unknown app id is 404 wpcy_apps_unknown_app.
	 */
	public function test_unknown_app_is_404() {
		$controller      = $this->controller();
		$request         = new WP_REST_Request();
		$request->params = array( 'id' => 'no-such-app' );
		$result          = $controller->get_context( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_apps_unknown_app', $result->get_error_code() );
		$this->assertSame( 404, $result->get_error_data()['status'] );
	}

	/**
	 * Key outside ^[a-z0-9_.-]{1,64}$ is 400.
	 */
	public function test_invalid_key_is_400() {
		$controller      = $this->controller();
		$request         = new WP_REST_Request();
		$request->params = array(
			'id'  => 'motusnap',
			'key' => 'Bad Key!',
		);
		$result          = $controller->get_data( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_apps_key_invalid', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
		$this->assertFalse( DataStore::key_valid( str_repeat( 'a', 65 ) ) );
		$this->assertTrue( DataStore::key_valid( 'a.b_c-1' ) );
	}

	/**
	 * PUT body larger than 64KB is 413.
	 */
	public function test_put_65kb_is_413() {
		$controller      = $this->controller();
		$request         = new WP_REST_Request();
		$request->params = array(
			'id'  => 'motusnap',
			'key' => 'blob',
		);
		$request->body   = str_repeat( 'a', DataStore::MAX_BYTES + 1 );

		$result = $controller->put_data( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_apps_payload_too_large', $result->get_error_code() );
		$this->assertSame( 413, $result->get_error_data()['status'] );
	}

	/**
	 * Motusnap can write and read its own key.
	 */
	public function test_put_and_get_own_key() {
		$controller   = $this->controller();
		$put          = new WP_REST_Request();
		$put->params  = array(
			'id'  => 'motusnap',
			'key' => 'settings',
		);
		$put->json    = array( 'value' => array( 'theme' => 'dark' ) );
		$put->body    = '{"value":{"theme":"dark"}}';
		$put_response = $controller->put_data( $put );

		$this->assertInstanceOf( WP_REST_Response::class, $put_response );
		$this->assertSame( 200, $put_response->get_status() );

		$get         = new WP_REST_Request();
		$get->params = array(
			'id'  => 'motusnap',
			'key' => 'settings',
		);
		$got         = $controller->get_data( $get );
		$this->assertInstanceOf( WP_REST_Response::class, $got );
		$this->assertSame( array( 'theme' => 'dark' ), $got->get_data()['value'] );
	}

	/**
	 * POST /go returns the wpcy.com/go URL with UTM.
	 */
	public function test_go_url_uses_wpcy_go() {
		$controller      = $this->controller();
		$request         = new WP_REST_Request();
		$request->params = array( 'id' => 'motusnap' );
		$response        = $controller->open_go( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$url = $response->get_data()['url'];
		$this->assertSame(
			'https://wpcy.com/go/motusnap?utm_source=wpcy-plugin&utm_medium=app&utm_campaign=motusnap',
			$url
		);
	}

	/**
	 * Paid with no entitlement row is 403 entitlement_required.
	 */
	public function test_paid_without_entitlement_is_403() {
		$controller      = $this->controller( null, new MissingEntitlements() );
		$request         = new WP_REST_Request();
		$request->params = array( 'id' => 'paidtool' );
		$result          = $controller->get_entitlement( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_apps_entitlement_required', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	/**
	 * Default exhausted client: write on a limited-free app that is exhausted is 403 quota.
	 */
	public function test_exhausted_write_is_quota_exceeded() {
		$controller      = $this->controller( null, new ExhaustedEntitlements() );
		$request         = new WP_REST_Request();
		$request->params = array(
			'id'  => 'motusnap',
			'key' => 'settings',
		);
		$request->json   = array( 'value' => 1 );
		$request->body   = '{"value":1}';
		$result          = $controller->put_data( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_apps_quota_exceeded', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	/**
	 * GET /apps lists verified ids. AppsController registers /apps*.
	 */
	public function test_list_apps_and_register_routes() {
		$controller = $this->controller();
		$response   = $controller->list_apps( new WP_REST_Request() );
		$ids        = array();
		foreach ( $response->get_data() as $row ) {
			$ids[] = $row['id'];
		}
		$this->assertSame( array( 'motusnap', 'noteboard', 'paidtool' ), $ids );

		$controller->register_routes();
		$routes = array();
		foreach ( AppsStore::$routes as $row ) {
			$routes[] = $row['route'];
		}
		$this->assertContains( '/apps', $routes );
		$this->assertNotContains( '/settings', $routes );
	}

	/**
	 * AppsModule id is services.apps. Table install is a no-op without wpdb.
	 */
	public function test_apps_module_id_and_install_without_wpdb() {
		$module = new AppsModule();
		$this->assertSame( 'services.apps', $module->id() );
		DataStore::install();
		$this->assertSame( 'wp_wpcy_app_data', $module->store()->table_name() );
	}

	/**
	 * Table name uses the wpdb prefix (per-site on multisite).
	 */
	public function test_table_name_uses_prefix() {
		$wpdb         = new \stdClass();
		$wpdb->prefix = 'wp_2_';
		$store        = new DataStore( $wpdb );
		$this->assertSame( 'wp_2_wpcy_app_data', $store->table_name() );
	}

	/**
	 * Controller + in-memory store over the valid index fixture.
	 *
	 * @param DataStore|null          $store        Store.
	 * @param EntitlementsClient|null $entitlements Entitlements. Null is active (writes allowed).
	 */
	private function controller( $store = null, $entitlements = null ): AppsController {
		$path     = dirname( __DIR__, 2 ) . '/fixtures/apps/index.valid.json';
		$index    = new Index( new ManifestVerifier(), $path, null, null, '4.0.0' );
		$registry = new Registry( $index );
		$store    = $store instanceof DataStore ? $store : new DataStore();
		$ents     = $entitlements instanceof EntitlementsClient ? $entitlements : new ActiveEntitlements();
		return new AppsController( $registry, $store, $ents );
	}
}
