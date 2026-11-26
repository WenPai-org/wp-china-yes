<?php
/**
 * Tests for W6 frontend <2KB browser fallback SDK injection and gating.
 *
 * @package WenPai\ChinaYes
 * @since   4.1.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Connectivity\PublicAssets;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Connectivity\PublicAssets\BrowserFallbackLoader;
use WenPai\ChinaYes\Connectivity\PublicAssets\Manifest;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Tests\Unit\Connectivity\HookStore;
use WenPai\ChinaYes\Tests\Unit\Connectivity\MapConfig;

require_once dirname( __DIR__ ) . '/wp-hook-stubs.php';

/**
 * Verifies W6 script tag emission, JSON_HEX_TAG/AMP safety, and killSwitch/recovery suppression.
 */
class BrowserFallbackHookTest extends TestCase {

	/**
	 * Reset hook bag.
	 */
	protected function setUp(): void {
		parent::setUp();
		HookStore::reset();
	}

	/**
	 * Frontend emits <script id="wpcy-admincdn-loader"> with manifest fallbacks and excludes wpstatic.
	 */
	public function test_frontend_emits_escaped_loader_tag_excluding_wpstatic(): void {
		$now   = time();
		$state = array(
			'stored_at' => $now,
			'manifest'  => array(
				'killSwitch' => false,
				'backupHost' => 'backup.admincdn.com',
				'classes'    => array(
					'npm'      => array(
						'clientFallbacks' => array(
							'https://s4.zstatic.net/npm/{pkg}@{ver}/{file}&tag=<script>',
						),
					),
					'wpstatic' => array(
						'clientFallbacks' => array(
							'https://s.w.org/{path}',
						),
					),
				),
			),
		);

		$config   = new MapConfig(
			array(
				'recovery_mode'                        => false,
				'connectivity.browser_fallback_loader' => true,
				'connectivity.public_assets'           => array(
					'items' => array( 'jsdelivr' ),
					'scope' => 'both',
				),
			)
		);
		$manifest = new Manifest(
			$config,
			null,
			null,
			null,
			static function () use ( $state ) {
				return $state;
			}
		);

		$loader = new BrowserFallbackLoader( $config, $manifest );
		$loader->register( new Environment( Environment::FRONTEND, true ) );
		$this->assertArrayHasKey( 'wp_head', HookStore::$hooks );

		$html = $loader->render_tag( $now );
		$this->assertStringContainsString( 'id="wpcy-admincdn-loader"', $html );
		$this->assertStringContainsString( 'admincdn-loader.min.js', $html );
		$this->assertStringContainsString( 'data-backup-host="backup.admincdn.com"', $html );
		$this->assertStringNotContainsString( 'wpstatic', $html );
		$this->assertStringNotContainsString( 's.w.org', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	/**
	 * Loader is suppressed when killSwitch=true, recovery_mode=true, or toggle off.
	 */
	public function test_loader_suppressed_by_kill_switch_recovery_or_toggle_off(): void {
		$now   = time();
		$state = array(
			'stored_at' => $now,
			'manifest'  => array(
				'killSwitch' => true,
				'classes'    => array(),
			),
		);

		$base_cfg = array(
			'recovery_mode'                        => false,
			'connectivity.browser_fallback_loader' => true,
			'connectivity.public_assets'           => array(
				'items' => array( 'jsdelivr' ),
				'scope' => 'both',
			),
		);

		$ks_cfg  = new MapConfig( $base_cfg );
		$ks_mf   = new Manifest(
			$ks_cfg,
			null,
			null,
			null,
			static function () use ( $state ) {
				return $state;
			}
		);
		$ks_load = new BrowserFallbackLoader( $ks_cfg, $ks_mf );
		$this->assertSame( '', $ks_load->render_tag( $now ) );

		$state['manifest']['killSwitch'] = false;
		$rec_cfg                         = new MapConfig( array_merge( $base_cfg, array( 'recovery_mode' => true ) ) );
		$rec_mf                          = new Manifest(
			$rec_cfg,
			null,
			null,
			null,
			static function () use ( $state ) {
				return $state;
			}
		);
		$this->assertSame( '', ( new BrowserFallbackLoader( $rec_cfg, $rec_mf ) )->render_tag( $now ) );

		$off_cfg = new MapConfig( array_merge( $base_cfg, array( 'connectivity.browser_fallback_loader' => false ) ) );
		$off_mf  = new Manifest(
			$off_cfg,
			null,
			null,
			null,
			static function () use ( $state ) {
				return $state;
			}
		);
		$this->assertSame( '', ( new BrowserFallbackLoader( $off_cfg, $off_mf ) )->render_tag( $now ) );
	}

	/**
	 * Static fallback config includes /ajax/libs/{lib}/{ver}/{file} for cdnjs when no manifest is active.
	 */
	public function test_static_fallback_config_includes_cdnjs_ajax_libs_template_without_manifest(): void {
		$config   = new MapConfig(
			array(
				'recovery_mode'                        => false,
				'connectivity.browser_fallback_loader' => true,
				'connectivity.public_assets'           => array(
					'items' => array( 'cdnjs' ),
					'scope' => 'both',
				),
			)
		);
		$manifest = new Manifest(
			$config,
			null,
			null,
			null,
			static function () {
				return array();
			}
		);
		$loader   = new BrowserFallbackLoader( $config, $manifest );
		$cfg      = $loader->client_config();

		$this->assertSame(
			array( 'https://s4.zstatic.net/ajax/libs/{lib}/{ver}/{file}' ),
			$cfg['fallbacks']['cdnjs']
		);
	}
}
