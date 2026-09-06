<?php
/**
 * GET/PUT /site-blocklist: cap split, protected-host 400, Chinese message.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Rest;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Privacy\DataResidency\Ruleset;
use WenPai\ChinaYes\Privacy\SiteBlocklist\Repository as BlocklistRepository;
use WenPai\ChinaYes\Rest\RestError;
use WenPai\ChinaYes\Rest\RestModule;
use WenPai\ChinaYes\Rest\SiteBlocklistController;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WP_Error;
use WP_REST_Request;

require_once __DIR__ . '/wp-rest-stubs.php';

/**
 * Permission, 400 protected host, message 文派服务不可拦截.
 */
class SiteBlocklistControllerTest extends TestCase {

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		RestStore::reset();
		OptionStore::reset();
		RestError::reset();
	}

	/**
	 * Multisite without manage_network_options is 403; manage_options is not enough to PUT.
	 */
	public function test_multisite_requires_manage_network_options() {
		OptionStore::$multisite = true;
		$result                 = SiteBlocklistController::permission_read( new WP_REST_Request() );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_forbidden', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );

		RestStore::$caps['manage_options'] = true;
		$denied                            = SiteBlocklistController::permission_write( new WP_REST_Request() );
		$this->assertInstanceOf( WP_Error::class, $denied );
		$this->assertSame( 'wpcy_forbidden', $denied->get_error_code() );
	}

	/**
	 * Single site: manage_options is enough (WordPress maps the network cap).
	 */
	public function test_single_site_manage_options_can_read() {
		RestStore::$caps['manage_options'] = true;
		$this->assertTrue( SiteBlocklistController::permission_read( new WP_REST_Request() ) );
	}

	/**
	 * PUT a protected host returns 400 wpcy_blocklist_protected_host with the Chinese message.
	 */
	public function test_put_protected_host_is_400_chinese_message() {
		$controller    = new SiteBlocklistController(
			new BlocklistRepository( new Repository(), new Ruleset( null, null, false ) )
		);
		$request       = new WP_REST_Request();
		$request->json = array(
			'enabled' => true,
			'hosts'   => array(
				array(
					'host'  => 'api.wenpai.net',
					'match' => 'exact',
				),
			),
		);

		$result = $controller->update_item( $request );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_blocklist_protected_host', $result->get_error_code() );
		$this->assertSame( '文派服务不可拦截', $result->get_error_message() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	/**
	 * Empty list GET is enabled + hosts [].
	 */
	public function test_get_empty_list() {
		$controller = new SiteBlocklistController(
			new BlocklistRepository( new Repository(), new Ruleset( null, null, false ) )
		);
		$response   = $controller->get_item( new WP_REST_Request() );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['enabled'] );
		$this->assertSame( array(), $data['hosts'] );
	}

	/**
	 * REST index includes /site-blocklist.
	 */
	public function test_rest_index_includes_site_blocklist() {
		$module = new RestModule( new Repository() );
		$module->register_routes();
		$routes = array();
		foreach ( RestStore::$routes as $row ) {
			$routes[] = $row['route'];
		}
		$this->assertContains( '/site-blocklist', $routes );
	}

	/**
	 * Twenty-one hosts via PUT is wpcy_invalid_schema.
	 */
	public function test_put_twenty_one_is_invalid_schema() {
		$hosts = array();
		for ( $i = 1; $i <= 21; $i++ ) {
			$hosts[] = array(
				'host'  => sprintf( 'h%02d.example.com', $i ),
				'match' => 'exact',
			);
		}
		$controller    = new SiteBlocklistController(
			new BlocklistRepository( new Repository(), new Ruleset( null, null, false ) )
		);
		$request       = new WP_REST_Request();
		$request->json = array(
			'enabled' => true,
			'hosts'   => $hosts,
		);
		$result        = $controller->update_item( $request );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_invalid_schema', $result->get_error_code() );
		$this->assertArrayNotHasKey( Schema::SETTINGS, OptionStore::$options );
	}
}
