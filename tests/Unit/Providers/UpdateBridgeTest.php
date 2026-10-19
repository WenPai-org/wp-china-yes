<?php
/**
 * UpdateBridge fills only update_managed items and skips recovery_mode.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Providers;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Providers\ProviderService;
use WenPai\ChinaYes\Providers\Store;
use WenPai\ChinaYes\Providers\UpdateBridge;
use WenPai\ChinaYes\Providers\WcAmClient;
use WenPai\ChinaYes\Services\SiteBinding\SiteBindingModule;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WenPai\ChinaYes\Tests\Unit\Rest\RestStore;
use WenPai\ChinaYes\Tests\Unit\Services\SiteBinding\BindingStore;

require_once __DIR__ . '/wp-providers-stubs.php';

/**
 * P7: exact match only; recovery_mode does not hook.
 */
class UpdateBridgeTest extends TestCase {

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		OptionStore::reset();
		RestStore::reset();
		BindingStore::reset();
		$GLOBALS['wpcy_test_plugins'] = array(
			'demo-plugin/demo-plugin.php' => array(
				'Name'    => 'Demo',
				'Version' => '1.0.0',
			),
			'other-plugin/other.php'      => array(
				'Name'    => 'Other',
				'Version' => '1.0.0',
			),
		);
	}

	/**
	 * Only update_managed items land in response[].
	 */
	public function test_injects_only_update_managed() {
		$service = $this->connected_service();
		$bridge  = new UpdateBridge( new Repository(), $service, 'get_plugins' );
		$out     = $bridge->inject( (object) array( 'response' => array() ) );

		$this->assertArrayHasKey( 'demo-plugin/demo-plugin.php', $out->response );
		$this->assertSame( '2.0.0', $out->response['demo-plugin/demo-plugin.php']->new_version );
		$this->assertSame( 'https://mall.weixiaoduo.com/pkg.zip', $out->response['demo-plugin/demo-plugin.php']->package );
		$this->assertArrayNotHasKey( 'other-plugin/other.php', $out->response );
	}

	/**
	 * Recovery mode: register() does not add the filter.
	 */
	public function test_recovery_mode_does_not_hook() {
		OptionStore::$options[ Schema::SETTINGS ] = array(
			'schema_version' => 2,
			'recovery_mode'  => true,
		);
		$before                                   = RestStore::$hooks;
		$bridge                                   = new UpdateBridge( new Repository(), $this->connected_service(), 'get_plugins' );
		$bridge->register();
		$this->assertSame( $before, RestStore::$hooks );
		$this->assertArrayNotHasKey( 'pre_set_site_transient_update_plugins', RestStore::$hooks );
	}

	/**
	 * Recovery mode: a direct inject() call is a no-op.
	 */
	public function test_recovery_mode_inject_is_noop() {
		OptionStore::$options[ Schema::SETTINGS ] = array(
			'schema_version' => 2,
			'recovery_mode'  => true,
		);
		$service                                  = $this->connected_service();
		$before                                   = count( BindingStore::$requests );
		$bridge                                   = new UpdateBridge( new Repository(), $service, 'get_plugins' );
		$in                                       = (object) array( 'response' => array() );
		$out                                      = $bridge->inject( $in );
		$this->assertSame( $in, $out );
		$this->assertSame( array(), $out->response );
		$this->assertSame( $before, count( BindingStore::$requests ) );
	}

	/**
	 * Empty package is not written into response[].
	 */
	public function test_empty_package_is_not_injected() {
		$service = $this->connected_service(
			'{"success":true,"data":{"package":"","new_version":"2.0.0"}}'
		);
		$bridge  = new UpdateBridge( new Repository(), $service, 'get_plugins' );
		$out     = $bridge->inject( (object) array( 'response' => array() ) );
		$this->assertArrayNotHasKey( 'demo-plugin/demo-plugin.php', $out->response );
	}

	/**
	 * New version that is not greater than installed is not written.
	 */
	public function test_not_newer_version_is_not_injected() {
		$service = $this->connected_service(
			'{"success":true,"data":{"package":"https://mall.weixiaoduo.com/pkg.zip","new_version":"1.0.0"}}'
		);
		$bridge  = new UpdateBridge( new Repository(), $service, 'get_plugins' );
		$out     = $bridge->inject( (object) array( 'response' => array() ) );
		$this->assertArrayNotHasKey( 'demo-plugin/demo-plugin.php', $out->response );
	}

	/**
	 * HTTP (non-HTTPS) package is rejected by UrlGuard.
	 */
	public function test_http_package_is_not_injected() {
		$service = $this->connected_service(
			'{"success":true,"data":{"package":"http://mall.weixiaoduo.com/pkg.zip","new_version":"2.0.0"}}'
		);
		$bridge  = new UpdateBridge( new Repository(), $service, 'get_plugins' );
		$out     = $bridge->inject( (object) array( 'response' => array() ) );
		$this->assertArrayNotHasKey( 'demo-plugin/demo-plugin.php', $out->response );
	}

	/**
	 * Activate for product_id is recorded; a failed activate does not block another product.
	 */
	public function test_activate_failure_does_not_block_other_product() {
		$GLOBALS['wpcy_test_plugins'] = array(
			'demo-plugin/demo-plugin.php' => array(
				'Name'    => 'Demo',
				'Version' => '1.0.0',
			),
			'other-plugin/other.php'      => array(
				'Name'    => 'Other',
				'Version' => '1.0.0',
			),
		);
		$service                      = $this->connected_service(
			'{"success":true,"data":{"package":"https://mall.weixiaoduo.com/pkg.zip","new_version":"2.0.0"}}',
			'{"success":true,"data":{"product_list":[{"product_id":1,"slug":"demo-plugin","title":"Demo"},{"product_id":2,"slug":"other-plugin","title":"Other"}]}}',
			array(
				array(
					'code' => 200,
					'body' => '{"success":false}',
				),
				array(
					'code' => 200,
					'body' => '{"success":true}',
				),
				array(
					'code' => 200,
					'body' => '{"success":true,"data":{"package":"https://mall.weixiaoduo.com/other.zip","new_version":"3.0.0"}}',
				),
			)
		);
		$bridge                       = new UpdateBridge( new Repository(), $service, 'get_plugins' );
		$out                          = $bridge->inject( (object) array( 'response' => array() ) );
		$this->assertArrayNotHasKey( 'demo-plugin/demo-plugin.php', $out->response );
		$this->assertArrayHasKey( 'other-plugin/other.php', $out->response );
		$this->assertSame( array( '2' ), ( new Store() )->activated_products( 'weixiaoduo-mall' ) );
		$urls = array();
		foreach ( BindingStore::$requests as $row ) {
			$urls[] = $row['url'];
		}
		$this->assertTrue( (bool) preg_grep( '/wc_am_action=activate/', $urls ) );
		$this->assertTrue( (bool) preg_grep( '/product_id=2/', $urls ) );
	}

	/**
	 * Connected service with one managed product and a queued update payload.
	 *
	 * Connect uses product_list. fetch_update then activate + update.
	 *
	 * @param string            $update_body Extra update JSON after the default activate.
	 * @param string            $list_body   product_list JSON for connect.
	 * @param array<int, mixed> $extra       Extra queued HTTP after connect.
	 */
	private function connected_service( string $update_body = '{"success":true,"data":{"package":"https://mall.weixiaoduo.com/pkg.zip","new_version":"2.0.0"}}', string $list_body = '{"success":true,"data":{"product_list":[{"product_id":1,"slug":"demo-plugin","title":"Demo"},{"product_id":2,"slug":"not-installed","title":"Ghost"}]}}', array $extra = array() ): ProviderService {
		OptionStore::$options[ Schema::SITE_IDENTITY ] = array(
			'schema_version' => 1,
			'site_uuid'      => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
			'binding'        => array(
				'status'       => 'bound',
				'site_hash'    => 'hash',
				'credential'   => null,
				'bound_at'     => '2026-09-06T00:00:00Z',
				'challenge_id' => null,
			),
		);
		$queue = array(
			array(
				'code' => 200,
				'body' => $list_body,
			),
		);
		if ( array() === $extra ) {
			$queue[] = array(
				'code' => 200,
				'body' => '{"success":true}',
			);
			$queue[] = array(
				'code' => 200,
				'body' => $update_body,
			);
		} else {
			foreach ( $extra as $row ) {
				$queue[] = $row;
			}
		}
		BindingStore::$responses = $queue;
		$repo                    = new Repository();
		$factory                 = static function ( string $api_url ) {
			return new WcAmClient(
				$api_url,
				static function ( string $url, array $args ) {
					return wp_remote_post( $url, $args );
				}
			);
		};
		$service                 = new ProviderService( new Store(), new SiteBindingModule( $repo ), $factory, 'get_plugins', null, $repo );
		$service->connect( 'weixiaoduo-mall', 'a@example.com', 'key' );
		return $service;
	}
}
