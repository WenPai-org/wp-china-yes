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
use WenPai\ChinaYes\Tests\Unit\Connectivity\ScopeHarness;

require_once dirname( __DIR__ ) . '/wp-hook-stubs.php';
require_once dirname( __DIR__ ) . '/scope-function-stubs.php';

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
		ScopeHarness::reset();
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
	 * Down primary node falls back to domestic mirror when fallback host is healthy.
	 */
	public function test_falls_back_to_domestic_mirror_when_primary_unhealthy() {
		$module = $this->module( array( 'jsdelivr' ), array( 'jsd.admincdn.com' => false ) );
		$origin = 'https://cdn.jsdelivr.net/npm/jquery@3/dist/jquery.min.js';

		$this->assertSame(
			'https://cdn.jsdmirror.com/npm/jquery@3/dist/jquery.min.js',
			$module->rewrite( $origin )
		);
	}

	/**
	 * Both primary and domestic fallback down keeps the origin URL.
	 */
	public function test_keeps_origin_when_primary_and_fallback_unhealthy() {
		$module = $this->module(
			array( 'jsdelivr' ),
			array(
				'jsd.admincdn.com'  => false,
				'cdn.jsdmirror.com' => false,
			)
		);
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
	 * Emoji key with jsd down falls back to domestic zstatic mirror, and keeps origin when both down.
	 */
	public function test_emoji_fallback_when_jsd_down() {
		$origin = 'https://s.w.org/images/core/emoji/15.0.3/svg/1f600.svg';

		$domestic = $this->module( array( 'emoji' ), array( 'jsd.admincdn.com' => false ) );
		$this->assertSame(
			'https://s4.zstatic.net/npm/@twemoji/api/dist/15.0.3/svg/1f600.svg',
			$domestic->rewrite( $origin )
		);

		$both_down = $this->module(
			array( 'emoji' ),
			array(
				'jsd.admincdn.com' => false,
				's4.zstatic.net'   => false,
			)
		);
		$this->assertSame( $origin, $both_down->rewrite( $origin ) );
	}

	/**
	 * All four admincdn public-library hosts fall back to verified domestic mirrors without double paths.
	 */
	public function test_all_admincdn_hosts_fall_back_to_domestic_mirrors() {
		$module = $this->module(
			array( 'google_fonts', 'google_ajax', 'cdnjs', 'jsdelivr' ),
			array(
				'googlefonts.admincdn.com' => false,
				'googleajax.admincdn.com'  => false,
				'cdnjs.admincdn.com'       => false,
				'jsd.admincdn.com'         => false,
			)
		);

		$this->assertSame(
			'https://fonts.googleapis.cn/css2?family=Roboto:wght@400',
			$module->rewrite( 'https://fonts.googleapis.com/css2?family=Roboto:wght@400' )
		);

		$ajax = $module->rewrite( 'https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js' );
		$this->assertSame( 'https://s4.zstatic.net/ajax/libs/jquery/3.7.1/jquery.min.js', $ajax );
		$this->assertStringNotContainsString( '/ajax/libs/ajax/libs', $ajax );

		$cdnjs = $module->rewrite( 'https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js' );
		$this->assertSame( 'https://s4.zstatic.net/ajax/libs/jquery/3.7.1/jquery.min.js', $cdnjs );
		$this->assertStringNotContainsString( '/ajax/libs/ajax/libs', $cdnjs );

		$this->assertSame(
			'https://cdn.jsdmirror.com/gh/org/repo@1.0/dist/app.js',
			$module->rewrite( 'https://cdn.jsdelivr.net/gh/org/repo@1.0/dist/app.js' )
		);
		$this->assertSame(
			'https://cdn.jsdmirror.com/bootstrap/3.3.7/css/bootstrap.min.css',
			$module->rewrite( 'https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css' )
		);
		$this->assertSame(
			'https://s4.zstatic.net/npm/react@18/umd/react.production.min.js',
			$module->rewrite( 'https://unpkg.com/react@18/umd/react.production.min.js' )
		);
		$this->assertSame(
			'https://s4.zstatic.net/npm/jquery/jquery-3.7.1.min.js',
			$module->rewrite( 'https://code.jquery.com/jquery-3.7.1.min.js' )
		);
		$this->assertSame(
			'https://s4.zstatic.net/npm/vue@3/dist/vue.global.prod.js',
			$module->rewrite( 'https://unpkg.com/vue@3/dist/vue.global.prod.js' )
		);
		$this->assertSame(
			'https://s4.zstatic.net/npm/datatables.net/1.13.6/js/jquery.dataTables.min.js',
			$module->rewrite( 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js' )
		);
		$this->assertSame(
			'https://s4.zstatic.net/npm/tailwindcss@3/dist/tailwind.min.css',
			$module->rewrite( 'https://unpkg.com/tailwindcss@3/dist/tailwind.min.css' )
		);
	}

	/**
	 * When both primary and domestic fallback hosts are down, all sources return origin URL.
	 */
	public function test_returns_origin_when_both_primary_and_domestic_fallback_down() {
		$module = $this->module(
			array( 'google_fonts', 'google_ajax', 'cdnjs', 'jsdelivr' ),
			array(
				'googlefonts.admincdn.com' => false,
				'fonts.googleapis.cn'      => false,
				'googleajax.admincdn.com'  => false,
				'cdnjs.admincdn.com'       => false,
				's4.zstatic.net'           => false,
				'jsd.admincdn.com'         => false,
				'cdn.jsdmirror.com'        => false,
			)
		);

		$urls = array(
			'https://fonts.googleapis.com/css2?family=Roboto:wght@400',
			'https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js',
			'https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js',
			'https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js',
			'https://unpkg.com/react@18/umd/react.production.min.js',
		);

		foreach ( $urls as $origin ) {
			$this->assertSame( $origin, $module->rewrite( $origin ), $origin );
		}
	}

	/**
	 * Fallback table filter allows overriding domestic fallback targets.
	 */
	public function test_fallback_table_filter_overrides_targets() {
		add_filter(
			'wpcy_public_assets_fallback_table',
			static function ( array $table ): array {
				$table['cdnjs']['cdnjs.cloudflare.com/ajax/libs'] = 'lib.baomitu.com';
				return $table;
			}
		);

		$module = $this->module( array( 'cdnjs' ), array( 'cdnjs.admincdn.com' => false ) );
		$this->assertSame(
			'https://lib.baomitu.com/jquery/3.7.1/jquery.min.js',
			$module->rewrite( 'https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js' )
		);
	}

	/**
	 * Fallback table excludes wpstatic.admincdn.com and public.admincdn.com.
	 */
	public function test_fallback_table_excludes_wpstatic() {
		$targets = array();
		foreach ( AssetMap::fallback_table() as $pairs ) {
			foreach ( $pairs as $replace ) {
				$targets[] = $replace;
			}
		}

		$haystack = implode( ' ', $targets );
		$this->assertStringNotContainsString( 'wpstatic.admincdn.com', $haystack );
		$this->assertStringNotContainsString( 'public.admincdn.com', $haystack );
		$this->assertArrayNotHasKey( 'admin', AssetMap::fallback_table() );
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
	 * Empty public_assets disables the module.
	 */
	public function test_enabled_false_when_list_empty() {
		$config = new MapConfig(
			array(
				'connectivity.public_assets' => array(
					'items' => array(),
					'scope' => 'both',
				),
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
				'connectivity.public_assets' => array(
					'items' => array( 'jsdelivr' ),
					'scope' => 'both',
				),
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
				'connectivity.public_assets' => array(
					'items' => array( 'jsdelivr' ),
					'scope' => 'both',
				),
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
	 * Admin scope does not rewrite on a frontend request.
	 */
	public function test_admin_scope_does_not_rewrite_frontend() {
		$config = new MapConfig(
			array(
				'connectivity.public_assets' => array(
					'items' => array( 'jsdelivr' ),
					'scope' => 'admin',
				),
				'recovery_mode'              => false,
			)
		);
		$module = new PublicAssetsModule( $config, new AssetMap(), new MirrorHealth( array( 'jsd.admincdn.com' => true ) ) );
		$env    = new Environment( Environment::FRONTEND, true );
		$origin = 'https://cdn.jsdelivr.net/npm/jquery@3/dist/jquery.min.js';

		$this->assertFalse( $module->enabled( $config, $env ) );
		$this->assertSame( $origin, $module->rewrite( $origin ) );
	}

	/**
	 * Frontend scope does not rewrite on an admin request.
	 */
	public function test_frontend_scope_does_not_rewrite_admin() {
		ScopeHarness::$is_admin = true;
		$config                 = new MapConfig(
			array(
				'connectivity.public_assets' => array(
					'items' => array( 'jsdelivr' ),
					'scope' => 'frontend',
				),
				'recovery_mode'              => false,
			)
		);
		$module                 = new PublicAssetsModule( $config, new AssetMap(), new MirrorHealth( array( 'jsd.admincdn.com' => true ) ) );
		$env                    = new Environment( Environment::ADMIN, true );
		$origin                 = 'https://cdn.jsdelivr.net/npm/jquery@3/dist/jquery.min.js';

		$this->assertFalse( $module->enabled( $config, $env ) );
		$this->assertSame( $origin, $module->rewrite( $origin ) );
	}

	/**
	 * Off scope disables the module even with items.
	 */
	public function test_scope_off_disables() {
		$config = new MapConfig(
			array(
				'connectivity.public_assets' => array(
					'items' => array( 'jsdelivr' ),
					'scope' => 'off',
				),
				'recovery_mode'              => false,
			)
		);
		$module = new PublicAssetsModule( $config, new AssetMap(), new MirrorHealth() );
		$env    = new Environment( Environment::ADMIN, true );

		$this->assertFalse( $module->enabled( $config, $env ) );
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
				'connectivity.public_assets' => array(
					'items' => $enabled,
					'scope' => 'both',
				),
				'recovery_mode'              => false,
			)
		);

		return new PublicAssetsModule( $config, new AssetMap(), new MirrorHealth( $health ) );
	}
}
