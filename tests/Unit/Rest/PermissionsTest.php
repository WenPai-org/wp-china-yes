<?php
/**
 * REST permission, nonce, schema, and recovery action tests.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Rest;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Admin\RecoveryPage;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Diagnostics\Checker;
use WenPai\ChinaYes\Rest\DiagnosticsController;
use WenPai\ChinaYes\Rest\DocumentWriter;
use WenPai\ChinaYes\Rest\Permissions;
use WenPai\ChinaYes\Rest\RecoveryActions;
use WenPai\ChinaYes\Rest\RecoveryController;
use WenPai\ChinaYes\Rest\RestError;
use WenPai\ChinaYes\Rest\RestModule;
use WenPai\ChinaYes\Rest\SettingsController;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

require_once __DIR__ . '/wp-rest-stubs.php';

/**
 * Acceptance: 403 without cap, 403 bad nonce, unknown keys dropped, unknown action.
 */
class PermissionsTest extends TestCase {

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		RestStore::reset();
		\WenPai\ChinaYes\Tests\Unit\Config\OptionStore::reset();
		RestError::reset();
		$_POST = array();
	}

	/**
	 * Clear POST.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$_POST = array();
		parent::tearDown();
	}

	/**
	 * GET /settings without manage_options is 403 wpcy_forbidden.
	 */
	public function test_get_settings_without_manage_options_is_forbidden() {
		$request = new WP_REST_Request();
		$result  = Permissions::manage_options_read( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_forbidden', $result->get_error_code() );
		$data = $result->get_error_data();
		$this->assertSame( 403, $data['status'] );
		$this->assertArrayHasKey( 'request_id', $data );
	}

	/**
	 * PUT /settings with a bad nonce is 403 even when the user can manage_options.
	 */
	public function test_put_settings_bad_nonce_is_forbidden() {
		RestStore::$caps['manage_options'] = true;
		$request                           = new WP_REST_Request();
		$request->headers['x-wp-nonce']    = 'not-a-nonce';

		$result = Permissions::manage_options_write( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_forbidden', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	/**
	 * Missing nonce header is also 403.
	 */
	public function test_put_settings_missing_nonce_is_forbidden() {
		RestStore::$caps['manage_options'] = true;
		$result                            = Permissions::manage_options_write( new WP_REST_Request() );
		$this->assertSame( 'wpcy_forbidden', $result->get_error_code() );
	}

	/**
	 * Unknown keys on PUT are dropped; HTTP 200; the key is absent.
	 */
	public function test_put_settings_unknown_key_is_dropped_and_200() {
		$controller    = $this->settings_controller();
		$request       = new WP_REST_Request();
		$request->json = array(
			'not_a_real_key' => 'drop-me',
			'recovery_mode'  => true,
		);

		$response = $controller->update_item( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayNotHasKey( 'not_a_real_key', $data );
		$this->assertTrue( $data['recovery_mode'] );
		$this->assertArrayHasKey( RestError::HEADER, $response->get_headers() );
	}

	/**
	 * Type / enum failures are wpcy_invalid_schema 400, not a silent default.
	 */
	public function test_put_settings_invalid_type_is_schema_error() {
		$controller    = $this->settings_controller();
		$request       = new WP_REST_Request();
		$request->json = array( 'recovery_mode' => 'yes' );

		$result = $controller->update_item( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_invalid_schema', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	/**
	 * Illegal recovery action is wpcy_recovery_unknown_action.
	 */
	public function test_unknown_recovery_action_is_bad_request() {
		$controller      = new RecoveryController( new RecoveryActions( new Repository() ) );
		$request         = new WP_REST_Request();
		$request->params = array( 'action' => 'explode' );

		$result = $controller->update_item( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_recovery_unknown_action', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	/**
	 * Action disable_rewrites turns rewrite switches off and sets recovery_mode.
	 */
	public function test_disable_rewrites_sets_recovery_and_turns_rewrites_off() {
		$repo    = new Repository();
		$actions = new RecoveryActions( $repo );
		$this->assertTrue( $actions->apply( RecoveryActions::DISABLE_REWRITES ) );

		$this->assertTrue( $repo->get( 'recovery_mode' ) );
		$this->assertSame( 'off', $repo->get( 'connectivity.wordpress_org' ) );
		$this->assertSame( array(), $repo->get( 'connectivity.public_assets' ) );
		$this->assertSame( 'off', $repo->get( 'connectivity.avatar' ) );
	}

	/**
	 * Action disable_modules turns optional modules off and sets recovery_mode.
	 */
	public function test_disable_modules_sets_recovery_and_turns_modules_off() {
		$repo = new Repository();
		$repo->set( 'modules.notice_control', true );
		$repo->set( 'modules.windfonts', true );
		$actions = new RecoveryActions( $repo );
		$this->assertTrue( $actions->apply( RecoveryActions::DISABLE_MODULES ) );

		$this->assertTrue( $repo->get( 'recovery_mode' ) );
		$this->assertFalse( $repo->get( 'modules.notice_control' ) );
		$this->assertFalse( $repo->get( 'modules.windfonts' ) );
	}

	/**
	 * Action exit only clears the flag. Previously disabled rewrites stay off.
	 */
	public function test_exit_clears_flag_and_does_not_restore_rewrites() {
		$repo    = new Repository();
		$actions = new RecoveryActions( $repo );
		$actions->apply( RecoveryActions::DISABLE_REWRITES );
		$actions->apply( RecoveryActions::EXIT );

		$this->assertFalse( $repo->get( 'recovery_mode' ) );
		$this->assertSame( 'off', $repo->get( 'connectivity.wordpress_org' ) );
		$this->assertSame( array(), $repo->get( 'connectivity.public_assets' ) );
		$this->assertSame( 'off', $repo->get( 'connectivity.avatar' ) );
	}

	/**
	 * GET /settings returns the full object and a request-id header.
	 */
	public function test_get_settings_returns_full_object() {
		$controller = $this->settings_controller();
		$response   = $controller->get_item( new WP_REST_Request() );
		$data       = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $data['recovery_mode'] );
		$this->assertArrayHasKey( 'connectivity', $data );
		$this->assertArrayNotHasKey( 'credential', $data );
	}

	/**
	 * GET /diagnostics wraps Checker latest() as { targets }.
	 */
	public function test_get_diagnostics_returns_targets_envelope() {
		RestStore::$transients[ Checker::STORE_KEY ] = array(
			array(
				'target'     => 'cdnjs.admincdn.com',
				'result'     => Checker::RESULT_OK,
				'latency_ms' => 12,
				'checked_at' => '2026-09-04T12:00:00Z',
				'suggestion' => null,
			),
		);

		$controller = new DiagnosticsController( new Checker() );
		$response   = $controller->get_item( new WP_REST_Request() );
		$data       = $response->get_data();

		$this->assertArrayHasKey( 'targets', $data );
		$this->assertCount( 1, $data['targets'] );
		$this->assertSame( 'cdnjs.admincdn.com', $data['targets'][0]['target'] );
		$this->assertSame( Checker::RESULT_OK, $data['targets'][0]['result'] );
		$this->assertNull( $data['targets'][0]['suggestion'] );
	}

	/**
	 * RestModule registers the five routes on wpcy/v1.
	 */
	public function test_rest_module_registers_namespace_routes() {
		$module = new RestModule( new Repository() );
		$module->register_routes();

		$routes = array();
		foreach ( RestStore::$routes as $row ) {
			$this->assertSame( RestModule::NAMESPACE, $row['namespace'] );
			$routes[] = $row['route'];
		}

		$this->assertContains( '/settings', $routes );
		$this->assertContains( '/network-settings', $routes );
		$this->assertContains( '/diagnostics', $routes );
		$this->assertContains( '/diagnostics/run', $routes );
		$this->assertContains( '/recovery', $routes );
		$this->assertNotContains( '/binding', $routes );
		$this->assertNotContains( '/apps', $routes );
	}

	/**
	 * Recovery page is registered with a null parent and the wpcy-recovery slug.
	 */
	public function test_recovery_page_is_hidden_submenu() {
		$page = new RecoveryPage( new RecoveryActions( new Repository() ) );
		$page->add_page();

		$this->assertCount( 1, RestStore::$pages );
		$this->assertNull( RestStore::$pages[0]['parent'] );
		$this->assertSame( RecoveryPage::SLUG, RestStore::$pages[0]['menu_slug'] );
		$this->assertSame( 'manage_options', RestStore::$pages[0]['capability'] );
		$this->assertSame( '文派叶子 · 恢复模式', RestStore::$pages[0]['page_title'] );
	}

	/**
	 * Rendered markup matches admin-ui-spec §3.5 and has no 遥测 copy.
	 */
	public function test_recovery_page_render_has_spec_copy_and_no_js() {
		RestStore::$caps['manage_options'] = true;
		$page                              = new RecoveryPage( new RecoveryActions( new Repository() ) );

		ob_start();
		$page->render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'wrap', $html );
		$this->assertStringContainsString( '<h1>文派叶子 · 恢复模式</h1>', $html );
		$this->assertStringContainsString( '如果后台样式错乱或站点无法访问，可在此一键停用所有 URL 改写与模块。此页不依赖 JavaScript。', $html );
		$this->assertStringContainsString( '关闭全部 URL 改写', $html );
		$this->assertStringContainsString( '停用全部模块', $html );
		$this->assertStringContainsString( 'button-primary', $html );
		$this->assertStringContainsString( 'button-secondary', $html );
		$this->assertStringContainsString( '返回概览', $html );
		$this->assertStringNotContainsString( '遥测', $html );
		$this->assertStringNotContainsString( '匿名数据', $html );
		$this->assertStringNotContainsString( '<script', $html );
	}

	/**
	 * In recovery mode the green notice and exit button appear.
	 */
	public function test_recovery_page_shows_notice_when_enabled() {
		RestStore::$caps['manage_options'] = true;
		$repo                              = new Repository();
		$repo->set( 'recovery_mode', true );
		$page = new RecoveryPage( new RecoveryActions( $repo ) );

		ob_start();
		$page->render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'notice-success', $html );
		$this->assertStringContainsString( '恢复模式已开启', $html );
		$this->assertStringContainsString( '退出恢复模式', $html );
	}

	/**
	 * Form POST disable_rewrites sets recovery_mode without REST.
	 */
	public function test_recovery_page_form_post_sets_recovery_mode() {
		RestStore::$caps['manage_options'] = true;
		RestStore::$admin_nonces[ 'wpcy_recovery_' . RecoveryActions::DISABLE_REWRITES ] = 'token';
		$_POST[ RecoveryPage::ACTION_FIELD ] = RecoveryActions::DISABLE_REWRITES;
		$_POST[ RecoveryPage::NONCE_FIELD ]  = 'token';

		$repo = new Repository();
		$page = new RecoveryPage( new RecoveryActions( $repo ) );
		try {
			$page->handle_post();
			$this->fail( 'handle_post must redirect after a successful POST' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'http://example.test/wp-admin/admin.php?page=wpcy-recovery', $e->getMessage() );
		}

		$this->assertTrue( $repo->get( 'recovery_mode' ) );
		$this->assertSame( 'off', $repo->get( 'connectivity.wordpress_org' ) );
		$this->assertSame( 'http://example.test/wp-admin/admin.php?page=wpcy-recovery', RestStore::$redirect );
	}

	/**
	 * Multisite recovery_mode stays on site overrides; network option is unchanged.
	 */
	public function test_recovery_actions_multisite_does_not_write_network_option() {
		\WenPai\ChinaYes\Tests\Unit\Config\OptionStore::$multisite = true;
		update_site_option(
			Schema::NETWORK_SETTINGS,
			array(
				'schema_version'      => 1,
				'allow_site_override' => true,
				'recovery_mode'       => false,
			)
		);

		$repo    = new Repository();
		$actions = new RecoveryActions( $repo );
		$this->assertTrue( $actions->apply( RecoveryActions::DISABLE_REWRITES ) );
		$this->assertTrue( $repo->get( 'recovery_mode' ) );

		$network   = get_site_option( Schema::NETWORK_SETTINGS );
		$overrides = get_option( Schema::SITE_OVERRIDES );
		$this->assertIsArray( $network );
		$this->assertIsArray( $overrides );
		$this->assertFalse( $network['recovery_mode'] );
		$this->assertTrue( $overrides['recovery_mode'] );
	}

	/**
	 * Network write without manage_network_options is forbidden.
	 */
	public function test_network_settings_requires_manage_network_options() {
		RestStore::$caps['manage_options'] = true;
		$result                            = Permissions::manage_network_read( new WP_REST_Request() );
		$this->assertSame( 'wpcy_forbidden', $result->get_error_code() );
	}

	/**
	 * Filter rest_post_dispatch attaches the request-id header on wpcy/v1.
	 */
	public function test_attach_request_id_on_namespace_responses() {
		$module         = new RestModule( new Repository() );
		$request        = new WP_REST_Request();
		$request->route = '/wpcy/v1/settings';
		$response       = new WP_REST_Response( array(), 200 );
		$out            = $module->attach_request_id( $response, null, $request );

		$this->assertArrayHasKey( RestError::HEADER, $out->get_headers() );
	}

	/**
	 * Settings controller for PUT/GET tests.
	 *
	 * @return SettingsController
	 */
	private function settings_controller(): SettingsController {
		return new SettingsController( new DocumentWriter( new Repository() ) );
	}
}
