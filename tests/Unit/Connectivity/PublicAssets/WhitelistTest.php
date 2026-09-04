<?php
/**
 * PublicAssets whitelist rewrite and node-down fallback.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Connectivity\PublicAssets;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Connectivity\MirrorHealth;
use WenPai\ChinaYes\Connectivity\PublicAssets\AssetMap;
use WenPai\ChinaYes\Connectivity\PublicAssets\PublicAssetsModule;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Tests\Unit\Connectivity\HookStore;
use WenPai\ChinaYes\Tests\Unit\Connectivity\MapConfig;

require_once dirname( __DIR__ ) . '/wp-hook-stubs.php';

/**
 * Unit: off-whitelist unchanged; down node unchanged; emoji optional.
 */
class WhitelistTest extends TestCase {

	/**
	 * Reset hook/transient bags.
	 */
	protected function setUp(): void {
		parent::setUp();
		HookStore::reset();
	}

	/**
	 * URLs outside the whitelist are returned unchanged.
	 */
	public function test_keeps_origin_when_not_on_whitelist() {
		$module = $this->module(
			array( 'google_fonts', 'google_ajax', 'cdnjs', 'jsdelivr', 'emoji' ),
			array()
		);
		$origin = 'https://example.com/vendor/jquery.min.js';

		$this->assertSame( $origin, $module->rewrite( $origin ) );
	}

	/**
	 * Site wp-content paths are not in the map (no frontend acceleration).
	 */
	public function test_does_not_rewrite_site_wp_content() {
		$module = $this->module( array( 'jsdelivr' ), array() );
		$origin = 'https://example.test/wp-content/themes/twentytwentyfour/style.css';

		$this->assertSame( $origin, $module->rewrite( $origin ) );
		$this->assertStringNotContainsString( 'public.admincdn.com', implode( ' ', ( new AssetMap() )->targets() ) );
	}

	/**
	 * Public.admincdn.com must not appear as a replacement target.
	 */
	public function test_map_excludes_public_admincdn() {
		$haystack = implode( ' ', ( new AssetMap() )->targets() );
		$this->assertStringNotContainsString( 'public.admincdn.com', $haystack );
		$this->assertStringNotContainsString( 'wpstatic.admincdn.com', $haystack );
		$this->assertArrayNotHasKey( 'admin', AssetMap::table() );
	}

	/**
	 * Healthy node rewrites a whitelisted jsDelivr URL.
	 */
	public function test_rewrites_whitelisted_when_node_healthy() {
		$module = $this->module( array( 'jsdelivr' ), array( 'jsd.admincdn.com' => true ) );
		$out    = $module->rewrite( 'https://cdn.jsdelivr.net/npm/jquery@3/dist/jquery.min.js' );

		$this->assertStringContainsString( 'jsd.admincdn.com', $out );
		$this->assertStringNotContainsString( 'cdn.jsdelivr.net', $out );
	}

	/**
	 * Down node keeps the origin URL.
	 */
	public function test_keeps_origin_when_node_unhealthy() {
		$module = $this->module( array( 'jsdelivr' ), array( 'jsd.admincdn.com' => false ) );
		$origin = 'https://cdn.jsdelivr.net/npm/jquery@3/dist/jquery.min.js';

		$this->assertSame( $origin, $module->rewrite( $origin ) );
	}

	/**
	 * Unknown health is treated as healthy (3.x is_healthy).
	 */
	public function test_unknown_health_rewrites() {
		$module = $this->module( array( 'google_fonts' ), array() );
		$out    = $module->rewrite( 'https://fonts.googleapis.com/css?family=Roboto' );

		$this->assertStringContainsString( 'googlefonts.admincdn.com', $out );
	}

	/**
	 * Cdnjs prefix /ajax/libs is consumed.
	 */
	public function test_cdnjs_strips_ajax_libs_prefix() {
		$module = $this->module( array( 'cdnjs' ), array( 'cdnjs.admincdn.com' => true ) );
		$out    = $module->rewrite( 'https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js' );

		$this->assertSame( 'https://cdnjs.admincdn.com/jquery/3.7.1/jquery.min.js', $out );
	}

	/**
	 * Google Ajax rewrites ajax.googleapis.com.
	 */
	public function test_google_ajax_rewrites() {
		$module = $this->module( array( 'google_ajax' ), array() );
		$out    = $module->rewrite( 'https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js' );

		$this->assertStringContainsString( 'googleajax.admincdn.com', $out );
	}

	/**
	 * Jsdelivr also covers 3.x admincdn_dev unpkg/jquery hosts.
	 */
	public function test_jsdelivr_covers_dev_libraries() {
		$module = $this->module( array( 'jsdelivr' ), array() );

		$this->assertStringContainsString(
			'jsd.admincdn.com/npm/jquery',
			$module->rewrite( 'https://code.jquery.com/jquery-3.7.1.min.js' )
		);
		$this->assertStringContainsString(
			'jsd.admincdn.com/npm/react',
			$module->rewrite( 'https://unpkg.com/react@18/umd/react.production.min.js' )
		);
	}

	/**
	 * Emoji is optional: without the key, s.w.org emoji URLs stay.
	 */
	public function test_emoji_not_rewritten_when_not_enabled() {
		$module = $this->module( array( 'jsdelivr' ), array() );
		$origin = 'https://s.w.org/images/core/emoji/15.0.3/svg/1f600.svg';

		$this->assertSame( $origin, $module->rewrite( $origin ) );
	}

	/**
	 * Emoji key rewrites Twemoji / s.w.org paths when jsd is healthy.
	 */
	public function test_emoji_rewritten_when_enabled_and_healthy() {
		$module = $this->module( array( 'emoji' ), array( 'jsd.admincdn.com' => true ) );
		$out    = $module->rewrite( 'https://s.w.org/images/core/emoji/15.0.3/svg/1f600.svg' );

		$this->assertStringContainsString( 'jsd.admincdn.com/npm/@twemoji/api/dist', $out );
	}

	/**
	 * Emoji key with jsd down keeps origin.
	 */
	public function test_emoji_keeps_origin_when_jsd_down() {
		$module = $this->module( array( 'emoji' ), array( 'jsd.admincdn.com' => false ) );
		$origin = 'https://s.w.org/images/core/emoji/15.0.3/svg/1f600.svg';

		$this->assertSame( $origin, $module->rewrite( $origin ) );
	}

	/**
	 * Disabled feature keys do not rewrite even if the URL matches another table.
	 */
	public function test_google_fonts_not_rewritten_when_feature_off() {
		$module = $this->module( array( 'cdnjs' ), array() );
		$origin = 'https://fonts.googleapis.com/css?family=Roboto';

		$this->assertSame( $origin, $module->rewrite( $origin ) );
	}

	/**
	 * Exhausted entitlement keeps origin URLs.
	 */
	public function test_keeps_origin_when_entitlement_exhausted() {
		$config = new MapConfig(
			array(
				'connectivity.public_assets' => array( 'jsdelivr' ),
				'recovery_mode'              => false,
			)
		);
		$module = new PublicAssetsModule(
			$config,
			new AssetMap(),
			new MirrorHealth( array( 'jsd.admincdn.com' => true ) ),
			static function () {
				return false;
			}
		);
		$origin = 'https://cdn.jsdelivr.net/npm/jquery@3/dist/jquery.min.js';

		$this->assertSame( $origin, $module->rewrite( $origin ) );
	}

	/**
	 * Empty public_assets disables the module.
	 */
	public function test_enabled_false_when_list_empty() {
		$config = new MapConfig(
			array(
				'connectivity.public_assets' => array(),
				'recovery_mode'              => false,
			)
		);
		$module = new PublicAssetsModule( $config, new AssetMap(), new MirrorHealth() );
		$env    = new Environment( Environment::ADMIN, true );

		$this->assertFalse( $module->enabled( $config, $env ) );
	}

	/**
	 * Recovery mode disables rewrite.
	 */
	public function test_enabled_false_in_recovery_mode() {
		$config = new MapConfig(
			array(
				'connectivity.public_assets' => array( 'jsdelivr' ),
				'recovery_mode'              => true,
			)
		);
		$module = new PublicAssetsModule( $config, new AssetMap(), new MirrorHealth() );
		$env    = new Environment( Environment::ADMIN, true );

		$this->assertFalse( $module->enabled( $config, $env ) );
	}

	/**
	 * AllowsUrlRewrite false disables rewrite.
	 */
	public function test_enabled_false_when_rewrite_disallowed() {
		$config = new MapConfig(
			array(
				'connectivity.public_assets' => array( 'jsdelivr' ),
				'recovery_mode'              => false,
			)
		);
		$module = new PublicAssetsModule( $config, new AssetMap(), new MirrorHealth() );
		$env    = new Environment( Environment::ADMIN, false );

		$this->assertFalse( $module->enabled( $config, $env ) );
	}

	/**
	 * Register attaches loader-src filters.
	 */
	public function test_register_hooks_loader_src() {
		$module = $this->module( array( 'jsdelivr' ), array() );
		$module->register();

		$this->assertArrayHasKey( 'style_loader_src', HookStore::$hooks );
		$this->assertArrayHasKey( 'script_loader_src', HookStore::$hooks );
	}

	/**
	 * Emoji hooks stay unattached when emoji is not in the list.
	 */
	public function test_register_skips_emoji_hooks_when_not_enabled() {
		$module = $this->module( array( 'jsdelivr' ), array( 'jsd.admincdn.com' => true ) );
		$module->register();

		$this->assertArrayNotHasKey( 'emoji_url', HookStore::$hooks );
	}

	/**
	 * Build a module with the given enabled keys and host health map.
	 *
	 * @param string[]            $enabled Enabled public_assets keys.
	 * @param array<string, bool> $health  Host => healthy.
	 */
	private function module( array $enabled, array $health ): PublicAssetsModule {
		$config = new MapConfig(
			array(
				'connectivity.public_assets' => $enabled,
				'recovery_mode'              => false,
			)
		);

		return new PublicAssetsModule( $config, new AssetMap(), new MirrorHealth( $health ) );
	}
}
