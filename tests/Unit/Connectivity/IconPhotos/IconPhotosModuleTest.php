<?php
/**
 * MotuCloud mirror track: tri-state switch, origin swap, fail-open to core.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Connectivity\IconPhotos;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Connectivity\IconPhotos\IconPhotosModule;
use WenPai\ChinaYes\Connectivity\MirrorHealth;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Tests\Unit\Connectivity\HookStore;
use WenPai\ChinaYes\Tests\Unit\Connectivity\MapConfig;
use WenPai\ChinaYes\Tests\Unit\Connectivity\ScopeHarness;
use WP_Error;

require_once dirname( __DIR__ ) . '/wp-hook-stubs.php';
require_once dirname( __DIR__ ) . '/wp-error-stub.php';
require_once dirname( __DIR__ ) . '/scope-function-stubs.php';

/**
 * Switch tri-state, rewritten_url origin, failure returns preempt.
 */
class IconPhotosModuleTest extends TestCase {

	/**
	 * Last URLs passed to the injected HTTP callable.
	 *
	 * @var list<string>
	 */
	private $http_urls = array();

	/**
	 * Canned HTTP result for the next request.
	 *
	 * @var mixed
	 */
	private $canned;

	/**
	 * Reset bags.
	 */
	protected function setUp(): void {
		parent::setUp();
		HookStore::reset();
		ScopeHarness::reset();
		$this->http_urls = array();
		$this->canned    = array(
			'code' => 200,
			'body' => '{}',
		);
	}

	/**
	 * Empty bases keep the module closed even when enabled=on.
	 */
	public function test_empty_base_does_not_enable(): void {
		$row    = array(
			'enabled'         => 'on',
			'mirrored_base'   => '',
			'native_api_base' => '',
		);
		$module = $this->module( $row );
		$env    = new Environment( Environment::ADMIN, true );

		$this->assertFalse( $module->enabled( $this->config( $row ), $env ) );
		$this->assertNull( $module->rewritten_url( 'https://api.wordpress.org/core/icons/1.0/' ) );
	}

	/**
	 * Off never registers even with a live base.
	 */
	public function test_off_does_not_enable(): void {
		$row    = array(
			'enabled'       => 'off',
			'mirrored_base' => 'https://motu.example/m',
		);
		$module = $this->module( $row );
		$env    = new Environment( Environment::ADMIN, true );

		$this->assertFalse( $module->enabled( $this->config( $row ), $env ) );
	}

	/**
	 * On + HTTPS base enables in admin.
	 */
	public function test_on_with_base_enables(): void {
		$row    = array(
			'enabled'       => 'on',
			'mirrored_base' => 'https://motu.example/m',
		);
		$module = $this->module( $row );
		$env    = new Environment( Environment::ADMIN, true );

		$this->assertTrue( $module->enabled( $this->config( $row ), $env ) );
	}

	/**
	 * Admin mode is off on the frontend.
	 */
	public function test_admin_mode_skips_frontend(): void {
		ScopeHarness::$is_admin = false;
		$row                    = array(
			'enabled'       => 'admin',
			'mirrored_base' => 'https://motu.example/m',
		);
		$module                 = $this->module( $row );
		$env                    = new Environment( Environment::FRONTEND, true );

		$this->assertFalse( $module->enabled( $this->config( $row ), $env ) );
	}

	/**
	 * Admin mode enables on admin requests.
	 */
	public function test_admin_mode_enables_in_admin(): void {
		ScopeHarness::$is_admin = true;
		$row                    = array(
			'enabled'       => 'admin',
			'mirrored_base' => 'https://motu.example/m',
		);
		$module                 = $this->module( $row );
		$env                    = new Environment( Environment::ADMIN, true );

		$this->assertTrue( $module->enabled( $this->config( $row ), $env ) );
	}

	/**
	 * Recovery mode skips register.
	 */
	public function test_recovery_does_not_enable(): void {
		$config = new MapConfig(
			array(
				'recovery_mode'            => true,
				'connectivity.icon_photos' => array(
					'enabled'       => 'on',
					'mirrored_base' => 'https://motu.example/m',
				),
			)
		);
		$module = new IconPhotosModule( $config, array( $this, 'http' ), new MirrorHealth( array() ) );
		$env    = new Environment( Environment::ADMIN, true );

		$this->assertFalse( $module->enabled( $config, $env ) );
	}

	/**
	 * Core icon API origin is swapped onto mirrored_base; path and query stay.
	 */
	public function test_rewritten_url_swaps_icon_origin(): void {
		$module = $this->module(
			array(
				'enabled'       => 'on',
				'mirrored_base' => 'https://motu.example/m',
			)
		);

		$this->assertSame(
			'https://motu.example/m/core/icons/1.0/?ver=6.8',
			$module->rewritten_url( 'https://api.wordpress.org/core/icons/1.0/?ver=6.8' )
		);
	}

	/**
	 * Openverse search origin is swapped (media-library photo search).
	 */
	public function test_rewritten_url_swaps_openverse_origin(): void {
		$module = $this->module(
			array(
				'enabled'       => 'on',
				'mirrored_base' => 'https://motu.example/m',
			)
		);

		$this->assertSame(
			'https://motu.example/m/v1/images/?q=cat',
			$module->rewritten_url( 'https://api.openverse.org/v1/images/?q=cat' )
		);
	}

	/**
	 * Emoji paths on s.w.org stay (PublicAssets owns them).
	 */
	public function test_emoji_path_is_not_rewritten(): void {
		$module = $this->module(
			array(
				'enabled'       => 'on',
				'mirrored_base' => 'https://motu.example/m',
			)
		);

		$this->assertNull(
			$module->rewritten_url( 'https://s.w.org/images/core/emoji/15.0.3/svg/1f600.svg' )
		);
	}

	/**
	 * Unrelated api.wordpress.org paths stay (WordPressOrgModule owns them).
	 */
	public function test_plugin_update_path_is_not_rewritten(): void {
		$module = $this->module(
			array(
				'enabled'       => 'on',
				'mirrored_base' => 'https://motu.example/m',
			)
		);

		$this->assertNull(
			$module->rewritten_url( 'https://api.wordpress.org/plugins/update-check/1.1/' )
		);
	}

	/**
	 * Successful rewrite returns the mirror response and records the URL.
	 */
	public function test_filter_hits_mirror(): void {
		$module = $this->module(
			array(
				'enabled'       => 'on',
				'mirrored_base' => 'https://motu.example/m',
			)
		);

		$out = $module->filter_pre_http_request(
			false,
			array( 'timeout' => 5 ),
			'https://api.wordpress.org/core/icons/1.0/'
		);

		$this->assertSame( array( 'https://motu.example/m/core/icons/1.0/' ), $this->http_urls );
		$this->assertSame( $this->canned, $out );
		$this->assertSame( 'https://motu.example/m/core/icons/1.0/', $module->last_request_url() );
	}

	/**
	 * Mirror WP_Error falls back to the original preempt (core source).
	 */
	public function test_mirror_error_falls_back_to_preempt(): void {
		$this->canned = new WP_Error( 'http_request_failed', 'timeout' );
		$module       = $this->module(
			array(
				'enabled'       => 'on',
				'mirrored_base' => 'https://motu.example/m',
			)
		);

		$out = $module->filter_pre_http_request(
			false,
			array(),
			'https://api.openverse.org/v1/images/?q=cat'
		);

		$this->assertFalse( $out );
		$this->assertSame( array( 'https://motu.example/m/v1/images/?q=cat' ), $this->http_urls );
	}

	/**
	 * Non-2xx mirror response also falls back.
	 */
	public function test_mirror_http_error_falls_back(): void {
		$this->canned = array( 'code' => 502 );
		$module       = $this->module(
			array(
				'enabled'       => 'on',
				'mirrored_base' => 'https://motu.example/m',
			)
		);

		$out = $module->filter_pre_http_request(
			false,
			array(),
			'https://api.wordpress.org/core/icons/1.0/'
		);

		$this->assertFalse( $out );
	}

	/**
	 * HTTP callable used by the module. Public so it is a valid callable.
	 *
	 * @param string               $url  Request URL.
	 * @param array<string, mixed> $args Unused.
	 * @return mixed
	 */
	public function http( $url, $args ) {
		unset( $args );
		$this->http_urls[] = $url;
		return $this->canned;
	}

	/**
	 * Module under test.
	 *
	 * @param array<string, mixed> $row icon_photos row.
	 */
	private function module( array $row ): IconPhotosModule {
		return new IconPhotosModule( $this->config( $row ), array( $this, 'http' ), new MirrorHealth( array() ) );
	}

	/**
	 * Config bag for one icon_photos row.
	 *
	 * @param array<string, mixed> $row icon_photos row.
	 */
	private function config( array $row ): MapConfig {
		return new MapConfig(
			array(
				'recovery_mode'            => false,
				'connectivity.icon_photos' => $row,
			)
		);
	}
}
