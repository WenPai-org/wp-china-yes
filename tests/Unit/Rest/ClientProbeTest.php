<?php
/**
 * GET/POST /diagnostics/client-probe: last summary, allow-list, no SSRF.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Rest;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Rest\ClientProbeController;
use WenPai\ChinaYes\Rest\Permissions;
use WenPai\ChinaYes\Rest\RestError;
use WenPai\ChinaYes\Rest\RestModule;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WP_Error;
use WP_REST_Request;

require_once __DIR__ . '/wp-rest-stubs.php';

/**
 * Browser probe storage. Server never fetches the URLs.
 */
class ClientProbeTest extends TestCase {

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
	 * Empty storage returns the empty envelope.
	 */
	public function test_get_empty_storage() {
		$controller = new ClientProbeController( new Repository() );
		$data       = $controller->get_item( new WP_REST_Request() )->get_data();

		$this->assertSame(
			array(
				'checked_at' => null,
				'probes'     => array(),
			),
			$data
		);
	}

	/**
	 * POST legal body; GET equals that summary; second POST overwrites.
	 */
	public function test_post_overwrites_and_get_returns_latest() {
		$controller = new ClientProbeController( new Repository() );
		$first      = $this->request(
			array(
				array(
					'url'        => 'https://fonts.googleapis.com/css2?family=Roboto:wght@400',
					'result'     => 'ok',
					'latency_ms' => 123,
				),
			)
		);
		$saved      = $controller->update_item( $first );
		$this->assertNotInstanceOf( WP_Error::class, $saved );
		$first_data = $saved->get_data();
		$this->assertSame( 'fonts.googleapis.com', $first_data['probes'][0]['target'] );
		$this->assertSame( 123, $first_data['probes'][0]['latency_ms'] );
		$this->assertNotNull( $first_data['checked_at'] );

		$got = $controller->get_item( new WP_REST_Request() )->get_data();
		$this->assertSame( $first_data, $got );

		$second = $this->request(
			array(
				array(
					'url'        => 'https://secure.gravatar.com/avatar/00000000000000000000000000000000?d=404',
					'result'     => 'down',
					'latency_ms' => null,
				),
			)
		);
		$over   = $controller->update_item( $second )->get_data();
		$this->assertCount( 1, $over['probes'] );
		$this->assertSame( 'secure.gravatar.com', $over['probes'][0]['target'] );
		$this->assertSame( 'down', $over['probes'][0]['result'] );
		$this->assertSame( $over, $controller->get_item( new WP_REST_Request() )->get_data() );
	}

	/**
	 * Host outside the allow-list is 400 and does not write.
	 */
	public function test_illegal_host_is_400_and_keeps_old_summary() {
		$controller = new ClientProbeController( new Repository() );
		$ok         = $this->request(
			array(
				array(
					'url'        => 'https://fonts.googleapis.com/css2?family=Roboto:wght@400',
					'result'     => 'ok',
					'latency_ms' => 10,
				),
			)
		);
		$stored     = $controller->update_item( $ok )->get_data();

		$bad    = $this->request(
			array(
				array(
					'url'        => 'https://evil.example/probe',
					'result'     => 'ok',
					'latency_ms' => 1,
				),
			)
		);
		$result = $controller->update_item( $bad );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_invalid_schema', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
		$this->assertSame( $stored, $controller->get_item( new WP_REST_Request() )->get_data() );
	}

	/**
	 * Permission deny is wpcy_forbidden.
	 */
	public function test_permission_denied() {
		$result = Permissions::manage_options_read( new WP_REST_Request() );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_forbidden', $result->get_error_code() );
	}

	/**
	 * Route is registered.
	 */
	public function test_route_registered() {
		$module = new RestModule( new Repository() );
		$module->register_routes();
		$routes = array();
		foreach ( RestStore::$routes as $row ) {
			$routes[] = $row['route'];
		}
		$this->assertContains( '/diagnostics/client-probe', $routes );
	}

	/**
	 * POST request with probes JSON.
	 *
	 * @param list<array<string, mixed>> $probes Probe rows.
	 */
	private function request( array $probes ): WP_REST_Request {
		$request       = new WP_REST_Request();
		$request->json = array( 'probes' => $probes );
		return $request;
	}
}
