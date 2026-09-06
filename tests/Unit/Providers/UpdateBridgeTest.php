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
	 * Connected service with one managed product and a queued update payload.
	 */
	private function connected_service(): ProviderService {
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
		BindingStore::$responses                       = array(
			array(
				'code' => 200,
				'body' => '{"success":true}',
			),
			array(
				'code' => 200,
				'body' => '{"success":true,"data":{"product_list":[{"product_id":1,"slug":"demo-plugin","title":"Demo"},{"product_id":2,"slug":"not-installed","title":"Ghost"}]}}',
			),
			array(
				'code' => 200,
				'body' => '{"success":true,"data":{"package":"https://mall.weixiaoduo.com/pkg.zip","new_version":"2.0.0"}}',
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
		$service = new ProviderService( new Store(), new SiteBindingModule( $repo ), $factory, 'get_plugins', null, $repo );
		$service->connect( 'weixiaoduo-mall', 'a@example.com', 'key' );
		return $service;
	}
}
