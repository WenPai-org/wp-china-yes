<?php
/**
 * PUT /settings writes profile_confirmed_at only when the body has profile.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Stats;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Rest\DocumentWriter;
use WenPai\ChinaYes\Rest\SettingsController;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WenPai\ChinaYes\Tests\Unit\Rest\RestStore;
use WP_REST_Request;

require_once __DIR__ . '/wp-stats-stubs.php';

/**
 * Profile_confirmed_at stamp and ignored PUT key.
 */
class SettingsProfileConfirmedTest extends TestCase {

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		OptionStore::reset();
		RestStore::reset();
	}

	/**
	 * PUT with profile writes profile_confirmed_at.
	 */
	public function test_put_with_profile_writes_confirmed_at() {
		$controller    = $this->controller();
		$request       = new WP_REST_Request();
		$request->json = array( 'profile' => 'domestic' );
		$data          = $controller->update_item( $request )->get_data();

		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
			(string) $data['profile_confirmed_at']
		);
	}

	/**
	 * PUT without profile leaves the stamp null.
	 */
	public function test_put_without_profile_does_not_write_confirmed_at() {
		$controller    = $this->controller();
		$request       = new WP_REST_Request();
		$request->json = array( 'recovery_mode' => false );
		$data          = $controller->update_item( $request )->get_data();

		$this->assertNull( $data['profile_confirmed_at'] );
	}

	/**
	 * Body profile_confirmed_at is ignored; the server writes now.
	 */
	public function test_put_body_confirmed_at_is_ignored() {
		$controller    = $this->controller();
		$request       = new WP_REST_Request();
		$request->json = array(
			'profile'              => 'domestic',
			'profile_confirmed_at' => '2020-01-01T00:00:00Z',
		);
		$data          = $controller->update_item( $request )->get_data();

		$this->assertNotSame( '2020-01-01T00:00:00Z', $data['profile_confirmed_at'] );
		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
			(string) $data['profile_confirmed_at']
		);
	}

	/**
	 * Settings controller.
	 */
	private function controller(): SettingsController {
		return new SettingsController( new DocumentWriter( new Repository() ) );
	}
}
