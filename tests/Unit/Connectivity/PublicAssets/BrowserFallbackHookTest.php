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
	 * Static fallback config prepends owned dual-backup domains (*.wenpai.com.cn -> *.feibisi.com.cn) ahead of third-party mirrors.
	 */
	public function test_static_fallback_config_prepends_dual_backup_domains_ahead_of_third_party(): void {
		$config   = new MapConfig(
			array(
				'recovery_mode'                        => false,
				'connectivity.browser_fallback_loader' => true,
				'connectivity.public_assets'           => array(
					'items' => array( 'cdnjs', 'jsdelivr', 'google_fonts', 'google_ajax', 'emoji' ),
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
		$fb       = $cfg['fallbacks'];

		$this->assertSame(
			array(
				'https://cdnjs.wenpai.com.cn/{lib}/{ver}/{file}',
				'https://cdnjs.feibisi.com.cn/{lib}/{ver}/{file}',
				'https://s4.zstatic.net/ajax/libs/{lib}/{ver}/{file}',
			),
			$fb['cdnjs']
		);
		$this->assertSame(
			array(
				'https://jsd.wenpai.com.cn',
				'https://jsd.feibisi.com.cn',
				'https://cdn.jsdmirror.com',
				'https://s4.zstatic.net/npm/{pkg}@{ver}/{file}',
			),
			$fb['npm']
		);
		$this->assertSame(
			array(
				'https://googlefonts.wenpai.com.cn',
				'https://googlefonts.feibisi.com.cn',
				'https://fonts.googleapis.cn',
			),
			$fb['googlefonts']
		);
		$this->assertSame(
			array(
				'https://gstatic.wenpai.com.cn',
				'https://gstatic.feibisi.com.cn',
			),
			$fb['gstatic']
		);
		$this->assertSame(
			array(
				'https://googleajax.wenpai.com.cn',
				'https://googleajax.feibisi.com.cn',
				'https://s4.zstatic.net/ajax/libs/{lib}/{ver}/{file}',
			),
			$fb['googleajax']
		);
		$this->assertSame(
			array(
				'https://unpkg.wenpai.com.cn',
				'https://unpkg.feibisi.com.cn',
				'https://s4.zstatic.net/npm/{pkg}@{ver}/{file}',
			),
			$fb['unpkg']
		);
		$this->assertSame(
			array(
				'https://emoji.wenpai.com.cn',
				'https://emoji.feibisi.com.cn',
				'https://s4.zstatic.net/npm/@twemoji/api/dist/{path}',
			),
			$fb['emoji']
		);
		$this->assertSame(
			array(
				'https://code.wenpai.com.cn',
				'https://code.feibisi.com.cn',
			),
			$fb['code']
		);

		// Redlines: wpstatic excluded from browser fallbacks; never use apex (@) or www. of backup root domains.
		$this->assertArrayNotHasKey( 'wpstatic', $fb );
		foreach ( $fb as $cls => $urls ) {
			foreach ( $urls as $url ) {
				$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- unit test assertion.
				$this->assertNotContains(
					$host,
					array( 'wenpai.com.cn', 'www.wenpai.com.cn', 'feibisi.com.cn', 'www.feibisi.com.cn', 's.w.org' ),
					'Disallowed host in static fallback class ' . $cls
				);
			}
		}
	}

	/**
	 * Manifest-provided unpkg, emoji, and code fallbacks pass through BrowserFallbackLoader cleanly.
	 */
	public function test_manifest_provided_unpkg_emoji_and_code_classes_pass_through(): void {
		$now   = time();
		$state = array(
			'stored_at' => $now,
			'manifest'  => array(
				'killSwitch' => false,
				'backupHost' => null,
				'classes'    => array(
					'unpkg' => array(
						'clientFallbacks' => array(
							'https://unpkg.wenpai.com.cn/{pkg}@{ver}/{file}',
							'https://unpkg.feibisi.com.cn/{pkg}@{ver}/{file}',
							'https://unpkg.com/{pkg}@{ver}/{file}',
						),
					),
					'emoji' => array(
						'clientFallbacks' => array(
							'https://emoji.wenpai.com.cn/{path}',
							'https://emoji.feibisi.com.cn/{path}',
							'https://s.w.org/{path}',
						),
					),
					'code'  => array(
						'clientFallbacks' => array(
							'https://code.wenpai.com.cn/{path}',
							'https://code.feibisi.com.cn/{path}',
							'https://code.jquery.com/{path}',
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
					'items' => array( 'jsdelivr', 'emoji' ),
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
		$loader   = new BrowserFallbackLoader( $config, $manifest );
		$cfg      = $loader->client_config( $now );

		$this->assertSame(
			array(
				'https://unpkg.wenpai.com.cn/{pkg}@{ver}/{file}',
				'https://unpkg.feibisi.com.cn/{pkg}@{ver}/{file}',
			),
			$cfg['fallbacks']['unpkg']
		);
		$this->assertSame(
			array(
				'https://emoji.wenpai.com.cn/{path}',
				'https://emoji.feibisi.com.cn/{path}',
			),
			$cfg['fallbacks']['emoji']
		);
		$this->assertSame(
			array(
				'https://code.wenpai.com.cn/{path}',
				'https://code.feibisi.com.cn/{path}',
			),
			$cfg['fallbacks']['code']
		);
	}
}
