<?php
/**
 * GET /profile/suggest permissions and payload shape.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Rest;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\ProfileSuggest;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Rest\Permissions;
use WenPai\ChinaYes\Rest\ProfileSuggestController;
use WenPai\ChinaYes\Rest\RestError;
use WenPai\ChinaYes\Rest\RestModule;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WP_Error;
use WP_REST_Request;

require_once __DIR__ . '/wp-rest-stubs.php';

/**
 * Suggestion endpoint. Never returns IP.
 */
class ProfileSuggestControllerTest extends TestCase {

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
	 * Successful suggestion has the rest-api.md shape and no ip key.
	 */
	public function test_get_item_shape_has_no_ip() {
		$engine          = new ProfileSuggest(
			static function () {
				return array( 'country' => 'CN' );
			}
		);
		$controller      = new ProfileSuggestController( $engine );
		$request         = new WP_REST_Request();
		$request->params = array(
			'locale'   => 'zh-CN',
			'timezone' => 'Asia/Shanghai',
		);
		$data            = $controller->get_item( $request )->get_data();

		$this->assertSame( 'domestic', $data['suggestion'] );
		$this->assertSame( 'CN', $data['signals']['server_country'] );
		$this->assertSame( 'zh-CN', $data['signals']['admin_locale_hint'] );
		$this->assertSame( 'Asia/Shanghai', $data['signals']['admin_tz_hint'] );
		$this->assertArrayNotHasKey( 'ip', $data );
		$this->assertArrayNotHasKey( 'ip', $data['signals'] );
	}

	/**
	 * Geo failure is still HTTP 200 with suggestion null.
	 */
	public function test_geo_failure_is_not_an_error() {
		$engine     = new ProfileSuggest(
			static function () {
				return null;
			}
		);
		$controller = new ProfileSuggestController( $engine );
		$data       = $controller->get_item( new WP_REST_Request() )->get_data();

		$this->assertNull( $data['suggestion'] );
		$this->assertNull( $data['signals']['server_country'] );
	}

	/**
	 * Accept-Language header is used when locale is omitted.
	 */
	public function test_accept_language_header() {
		$engine           = new ProfileSuggest(
			static function () {
				return array( 'country' => 'US' );
			}
		);
		$controller       = new ProfileSuggestController( $engine );
		$request          = new WP_REST_Request();
		$request->headers = array( 'accept-language' => 'zh-CN,zh;q=0.9' );
		$data             = $controller->get_item( $request )->get_data();

		$this->assertSame( 'crossborder', $data['suggestion'] );
		$this->assertSame( 'zh-CN', $data['signals']['admin_locale_hint'] );
	}

	/**
	 * Locale / timezone query params longer than 64 are not echoed in signals.
	 */
	public function test_locale_timezone_over_64_are_dropped() {
		$engine          = new ProfileSuggest(
			static function () {
				return array( 'country' => 'CN' );
			}
		);
		$controller      = new ProfileSuggestController( $engine );
		$long            = str_repeat( 'z', 65 );
		$request         = new WP_REST_Request();
		$request->params = array(
			'locale'   => $long,
			'timezone' => $long,
		);
		$data            = $controller->get_item( $request )->get_data();

		$this->assertNull( $data['signals']['admin_locale_hint'] );
		$this->assertNull( $data['signals']['admin_tz_hint'] );
		$this->assertSame( 'domestic', $data['suggestion'] );
	}

	/**
	 * Missing manage_options is wpcy_forbidden.
	 */
	public function test_permission_denied() {
		$result = Permissions::manage_options_read( new WP_REST_Request() );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_forbidden', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
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
		$this->assertContains( '/profile/suggest', $routes );
	}
}
