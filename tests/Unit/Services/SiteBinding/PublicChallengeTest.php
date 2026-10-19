<?php
/**
 * Public /binding/challenge: expired and non-pending do not return the token.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Services\SiteBinding;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Core\Logger;
use WenPai\ChinaYes\Rest\BindingController;
use WenPai\ChinaYes\Rest\RestError;
use WenPai\ChinaYes\Services\SiteBinding\SiteBindingModule;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WenPai\ChinaYes\Tests\Unit\Rest\RestStore;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

require_once __DIR__ . '/wp-binding-stubs.php';

/**
 * Acceptance: expired / non-pending → 409 wpcy_binding_not_pending.
 */
class PublicChallengeTest extends TestCase {

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
		BindingStore::$api = 'https://mock.wpcy.test/v1';
	}

	/**
	 * Expired pending challenge is 409 wpcy_binding_not_pending, no token.
	 */
	public function test_expired_challenge_is_not_pending() {
		$expired = $this->load_fixture( 'challenge-start-expired.json' );
		$module  = $this->module();
		$this->queue_json( $expired );
		$started = $module->start();
		$this->assertIsArray( $started );
		$this->assertSame( 'pending', $started['status'] );

		$controller            = new BindingController( null, $module );
		$request               = new WP_REST_Request();
		$request->params['id'] = $expired['challenge_id'];
		$result                = $controller->challenge( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcy_binding_not_pending', $result->get_error_code() );
		$this->assertSame( 409, $result->get_error_data()['status'] );
		$this->assertStringNotContainsString( $expired['challenge_token'], (string) $result->get_error_message() );
	}

	/**
	 * Unbound / wrong id / revoked do not respond with a token.
	 */
	public function test_non_pending_challenge_is_not_pending() {
		$module                = $this->module();
		$controller            = new BindingController( null, $module );
		$request               = new WP_REST_Request();
		$request->params['id'] = 'ch_test_01';

		$unbound = $controller->challenge( $request );
		$this->assertInstanceOf( WP_Error::class, $unbound );
		$this->assertSame( 'wpcy_binding_not_pending', $unbound->get_error_code() );
		$this->assertSame( 409, $unbound->get_error_data()['status'] );

		$start = $this->load_fixture( 'challenge-start.json' );
		$this->queue_json( $start );
		$module->start();

		$wrong               = new WP_REST_Request();
		$wrong->params['id'] = 'ch_other';
		$mismatch            = $controller->challenge( $wrong );
		$this->assertSame( 'wpcy_binding_not_pending', $mismatch->get_error_code() );

		$ok               = new WP_REST_Request();
		$ok->params['id'] = $start['challenge_id'];
		$pending          = $controller->challenge( $ok );
		$this->assertInstanceOf( WP_REST_Response::class, $pending );
		$this->assertSame( 200, $pending->get_status() );

		$module->revoke();
		$after = $controller->challenge( $ok );
		$this->assertInstanceOf( WP_Error::class, $after );
		$this->assertSame( 'wpcy_binding_not_pending', $after->get_error_code() );
		$this->assertSame( 409, $after->get_error_data()['status'] );
	}

	/**
	 * Empty or unsafe id is treated as not pending.
	 */
	public function test_empty_id_is_not_pending() {
		$module                = $this->module();
		$controller            = new BindingController( null, $module );
		$request               = new WP_REST_Request();
		$request->params['id'] = '../nope';
		$result                = $controller->challenge( $request );
		$this->assertSame( 'wpcy_binding_not_pending', $result->get_error_code() );
	}

	/**
	 * Module under test.
	 *
	 * @return SiteBindingModule
	 */
	private function module(): SiteBindingModule {
		$logger = new Logger( 'warning' );
		return new SiteBindingModule( new Repository( $logger ), $logger );
	}

	/**
	 * Queue a 200 JSON body as the next HTTP response.
	 *
	 * @param array<string, mixed> $payload Body.
	 */
	private function queue_json( array $payload ): void {
		BindingStore::$responses[] = array(
			'code' => 200,
			'body' => wp_json_encode( $payload ),
		);
	}

	/**
	 * Load a mock-license fixture.
	 *
	 * @param string $name File name.
	 * @return array<string, mixed>
	 */
	private function load_fixture( string $name ): array {
		$path = dirname( __DIR__, 3 ) . '/fixtures/mock-license/' . $name;
		$raw  = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture.
		$this->assertIsString( $raw );
		$decoded = json_decode( $raw, true );
		$this->assertIsArray( $decoded );
		return $decoded;
	}
}
