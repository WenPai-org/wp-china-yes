<?php
/**
 * Tests for dual-channel ETag sync, LKG caching, cache purge transitions, and routing fusion.
 *
 * @package WenPai\ChinaYes
 * @since   4.1.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Connectivity\PublicAssets;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Connectivity\MirrorHealth;
use WenPai\ChinaYes\Connectivity\PublicAssets\AssetMap;
use WenPai\ChinaYes\Connectivity\PublicAssets\CachePurger;
use WenPai\ChinaYes\Connectivity\PublicAssets\Manifest;
use WenPai\ChinaYes\Connectivity\PublicAssets\ManifestVerifier;
use WenPai\ChinaYes\Connectivity\PublicAssets\PublicAssetsModule;
use WenPai\ChinaYes\Tests\Unit\Connectivity\HookStore;
use WenPai\ChinaYes\Tests\Unit\Connectivity\MapConfig;
use WenPai\ChinaYes\Tests\Unit\Connectivity\ScopeHarness;

require_once dirname( __DIR__ ) . '/wp-hook-stubs.php';
require_once dirname( __DIR__ ) . '/scope-function-stubs.php';

/**
 * Covers W2-W5 manifest sync, backoff, LKG 7-day expiry, wpstatic redline, and cache purger.
 */
class ManifestRoutingTest extends TestCase {

	/**
	 * Reset hook and scope stubs.
	 */
	protected function setUp(): void {
		parent::setUp();
		HookStore::reset();
		ScopeHarness::reset();
	}

	/**
	 * Sign a manifest array using the test Ed25519 seed from fixtures.
	 *
	 * @param array<string, mixed> $overrides Partial field overrides.
	 * @param string               $key_id    Keyring keyId to sign with.
	 * @return array{raw: string, keyring: array<string, mixed>}
	 */
	private function signed_fixture( array $overrides = array(), string $key_id = 'admincdn-test-2026-11' ): array {
		$dir     = dirname( __DIR__, 3 ) . '/fixtures/manifest';
		$keyring = json_decode( (string) file_get_contents( $dir . '/keyring.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture.
		$doc     = json_decode( (string) file_get_contents( $dir . '/mirror-manifest.valid.json' ), false ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture.

		$overrides['keyId'] = $overrides['keyId'] ?? $key_id;
		foreach ( $overrides as $k => $v ) {
			$doc->$k = $v;
		}
		unset( $doc->sig );

		$seed = sodium_base642bin( (string) $keyring['keys'][ $key_id ]['DO_NOT_USE_IN_PROD_testPrivateKeyB64'], SODIUM_BASE64_VARIANT_ORIGINAL );
		$kp   = sodium_crypto_sign_seed_keypair( $seed );
		$sk   = sodium_crypto_sign_secretkey( $kp );

		$doc->sig = ManifestVerifier::SIG_PREFIX . sodium_bin2base64(
			sodium_crypto_sign_detached( ManifestVerifier::canonical_json( $doc ), $sk ),
			SODIUM_BASE64_VARIANT_ORIGINAL
		);

		$encoded = json_encode( $doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- test fixture builder.
		return array(
			'raw'     => is_string( $encoded ) ? $encoded : '',
			'keyring' => $keyring['keys'],
		);
	}

	/**
	 * Primary HTTP failure falls back to OSS secondary channel and subsequent 304 preserves LKG.
	 */
	public function test_dual_channel_failover_and_etag_304_preserve_lkg(): void {
		$signed  = $this->signed_fixture( array( 'issuedAt' => '2026-11-26T08:00:00Z' ) );
		$state   = array();
		$calls   = array();
		$attempt = 0;

		add_filter(
			'wpcy_mirror_manifest_secondary_url',
			static function () {
				return 'https://oss-secondary.example.cn/manifest.json';
			}
		);

		$http = static function ( string $url, array $headers ) use ( &$calls, &$attempt, $signed ) {
			$calls[] = array(
				'url'     => $url,
				'headers' => $headers,
			);
			++$attempt;
			if ( 1 === $attempt ) {
				return array(
					'status' => 502,
					'body'   => '',
					'etag'   => '',
				);
			}
			if ( 2 === $attempt ) {
				return array(
					'status' => 200,
					'body'   => $signed['raw'],
					'etag'   => '"v2026112601"',
				);
			}
			return array(
				'status' => 304,
				'body'   => '',
				'etag'   => '"v2026112601"',
			);
		};

		$manifest = new Manifest(
			new MapConfig( array() ),
			new CachePurger(),
			$signed['keyring'],
			$http,
			static function () use ( &$state ) {
				return $state;
			},
			static function ( array $new_state ) use ( &$state ) {
				$state = $new_state;
			}
		);

		$r1 = $manifest->sync( false, '2026-11-26T08:05:00Z' );
		$this->assertTrue( $r1['ok'] );
		$this->assertSame( 'updated', $r1['status'] );
		$this->assertSame( 'secondary', $manifest->status_snapshot()['active_source'] );
		$this->assertSame( 'https://oss-secondary.example.cn/manifest.json', $calls[1]['url'] );

		$r2 = $manifest->sync( false, '2026-11-26T08:06:00Z' );
		$this->assertTrue( $r2['ok'] );
		$this->assertSame( 'not_modified', $r2['status'] );
		$this->assertSame( '"v2026112601"', $calls[2]['headers']['If-None-Match'] );
	}

	/**
	 * Invalid signature rejects update, retains valid LKG, and sets exponential backoff.
	 */
	public function test_invalid_signature_keeps_lkg_and_activates_backoff(): void {
		$signed = $this->signed_fixture( array( 'issuedAt' => '2026-11-26T08:00:00Z' ) );
		$state  = array();
		$round  = 0;

		$http = static function () use ( &$round, $signed ) {
			++$round;
			if ( 1 === $round ) {
				return array(
					'status' => 200,
					'body'   => $signed['raw'],
					'etag'   => '"ok-1"',
				);
			}
			return array(
				'status' => 200,
				'body'   => str_replace( '"version":2026112601', '"version":2026112699', $signed['raw'] ),
				'etag'   => '"bad-2"',
			);
		};

		$manifest = new Manifest(
			new MapConfig( array() ),
			new CachePurger(),
			$signed['keyring'],
			$http,
			static function () use ( &$state ) {
				return $state;
			},
			static function ( array $new_state ) use ( &$state ) {
				$state = $new_state;
			}
		);

		$this->assertTrue( $manifest->sync( false, '2026-11-26T08:05:00Z' )['ok'] );
		$bad = $manifest->sync( false, '2026-11-26T08:06:00Z' );
		$this->assertFalse( $bad['ok'] );
		$this->assertSame( 'invalid_signature', $bad['code'] );
		$this->assertSame( 2026112601, $bad['version'] );

		$backoff = $manifest->sync( false, '2026-11-26T08:06:30Z' );
		$this->assertSame( 'backoff', $backoff['status'] );
	}

	/**
	 * Transition from ok -> down or killSwitch flip triggers CachePurger action.
	 */
	public function test_transition_triggers_cache_purger_when_enabled(): void {
		$v1     = $this->signed_fixture(
			array(
				'version'    => 100,
				'issuedAt'   => '2026-11-26T08:00:00Z',
				'killSwitch' => false,
			)
		);
		$v2     = $this->signed_fixture(
			array(
				'version'    => 101,
				'issuedAt'   => '2026-11-26T08:02:00Z',
				'killSwitch' => true,
			)
		);
		$state  = array();
		$round  = 0;
		$purged = array();

		add_action(
			'wpcy_manifest_cache_purged',
			static function ( string $reason ) use ( &$purged ) {
				$purged[] = $reason;
			}
		);

		$manifest = new Manifest(
			new MapConfig( array( 'connectivity.purge_cache_on_failover' => true ) ),
			new CachePurger(),
			$v1['keyring'],
			static function () use ( &$round, $v1, $v2 ) {
				++$round;
				return array(
					'status' => 200,
					'body'   => 1 === $round ? $v1['raw'] : $v2['raw'],
					'etag'   => '',
				);
			},
			static function () use ( &$state ) {
				return $state;
			},
			static function ( array $s ) use ( &$state ) {
				$state = $s;
			}
		);

		$manifest->sync( false, '2026-11-26T08:03:00Z' );
		$this->assertSame( array(), $purged );
		$manifest->sync( true, '2026-11-26T08:04:00Z' );
		$this->assertSame( array( 'kill_switch_enabled' ), $purged );
	}

	/**
	 * Routing honors killSwitch, down->domestic clientFallbacks, wpstatic redline, and 7-day LKG expiry fallback.
	 */
	public function test_routing_fusion_wpstatic_redline_and_lkg_expiry(): void {
		$signed = $this->signed_fixture( array( 'issuedAt' => '2026-11-26T08:00:00Z' ) );
		$state  = array();

		$manifest = new Manifest(
			new MapConfig( array() ),
			new CachePurger(),
			$signed['keyring'],
			static function () use ( $signed ) {
				return array(
					'status' => 200,
					'body'   => $signed['raw'],
					'etag'   => '"e1"',
				);
			},
			static function () use ( &$state ) {
				return $state;
			},
			static function ( array $s ) use ( &$state ) {
				$state = $s;
			}
		);
		$manifest->sync( false, '2026-11-26T08:01:00Z' );

		// Modify npm class state to down while keeping stored_at recent.
		$state['manifest']['classes']['npm']['state']           = 'down';
		$state['manifest']['classes']['npm']['clientFallbacks'] = array(
			'https://cdn.jsdelivr.net/npm/{pkg}@{ver}/{file}', // Overseas origin must be skipped.
			'https://registry.npmmirror.com/{pkg}/{ver}/files/{file}',
		);

		$config = new MapConfig(
			array(
				'connectivity.public_assets' => array(
					'items' => array( 'jsdelivr', 'emoji' ),
					'scope' => 'both',
				),
				'recovery_mode'              => false,
			)
		);
		$health = new MirrorHealth(
			array(
				'jsd.admincdn.com'       => false,
				'registry.npmmirror.com' => true,
				's4.zstatic.net'         => true,
				'wpstatic.admincdn.com'  => false,
			)
		);
		$module = new PublicAssetsModule( $config, new AssetMap(), $health, $manifest );

		$this->assertSame(
			'https://registry.npmmirror.com/jquery/3.7.1/files/dist/jquery.min.js',
			$module->rewrite( 'https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js' )
		);

		// wpstatic redline: never falls back to 3rd-party or s.w.org when unhealthy.
		$wp_origin = 'https://wpstatic.admincdn.com/6.7.1/wp-includes/js/jquery/jquery.min.js';
		$this->assertSame( $wp_origin, $manifest->resolve_url( $wp_origin, 'wp_org_assets', $health ) );

		// Malformed stored manifest with third-party wpstatic.primary or clientFallbacks must never route away from origin.
		$state['manifest']['classes']['wpstatic']['primary']         = 'https://evil-3p.example/{path}';
		$state['manifest']['classes']['wpstatic']['state']           = 'ok';
		$state['manifest']['classes']['wpstatic']['clientFallbacks'] = array( 'https://s.w.org/{path}' );
		$this->assertSame( $wp_origin, $manifest->resolve_url( $wp_origin, 'wp_org_assets', $health ) );
		$state['manifest']['classes']['wpstatic']['primary']         = 'https://wpstatic.admincdn.com/{ver}/{path}';
		$state['manifest']['classes']['wpstatic']['clientFallbacks'] = array();

		// killSwitch=true passes origin unchanged.
		$state['manifest']['killSwitch'] = true;
		$js_origin                       = 'https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js';
		$this->assertSame( $js_origin, $module->rewrite( $js_origin ) );

		// > 7 days expired LKG falls back cleanly to static AssetMap::table()/fallback_table().
		$state['manifest']['killSwitch'] = false;
		$state['stored_at']              = time() - ( Manifest::LKG_MAX_AGE_SECONDS + 100 );
		$fallback_health                 = new MirrorHealth(
			array(
				'jsd.admincdn.com'  => false,
				'cdn.jsdmirror.com' => true,
			)
		);
		$expired_module                  = new PublicAssetsModule( $config, new AssetMap(), $fallback_health, $manifest );
		$this->assertSame(
			'https://cdn.jsdmirror.com/npm/jquery@3.7.1/dist/jquery.min.js',
			$expired_module->rewrite( $js_origin )
		);
	}

	/**
	 * Emoji rewrites to jsd when signed manifest is active & npm/jsd healthy, falls back to zstatic on npm down, and skips on killSwitch.
	 */
	public function test_emoji_rewrites_with_active_signed_manifest_npm_down_and_kill_switch(): void {
		$signed = $this->signed_fixture( array( 'issuedAt' => '2026-11-26T08:00:00Z' ) );
		$state  = array();

		$manifest = new Manifest(
			new MapConfig( array() ),
			new CachePurger(),
			$signed['keyring'],
			static function () use ( $signed ) {
				return array(
					'status' => 200,
					'body'   => $signed['raw'],
					'etag'   => '"e-emoji"',
				);
			},
			static function () use ( &$state ) {
				return $state;
			},
			static function ( array $s ) use ( &$state ) {
				$state = $s;
			}
		);
		$manifest->sync( false, '2026-11-26T08:01:00Z' );

		$config = new MapConfig(
			array(
				'connectivity.public_assets' => array(
					'items' => array( 'emoji' ),
					'scope' => 'both',
				),
				'recovery_mode'              => false,
			)
		);
		$emoji  = 'https://s.w.org/images/core/emoji/15.0.3/svg/1f600.svg';

		// Active signed manifest + healthy npm/jsd -> jsd.admincdn.com/npm/@twemoji/api/dist/...
		$healthy = new MirrorHealth(
			array(
				'jsd.admincdn.com' => true,
				's4.zstatic.net'   => true,
			)
		);
		$mod_ok  = new PublicAssetsModule( $config, new AssetMap(), $healthy, $manifest );
		$this->assertSame(
			'https://jsd.admincdn.com/npm/@twemoji/api/dist/15.0.3/svg/1f600.svg',
			$mod_ok->rewrite( $emoji )
		);

		// Manifest npm class down -> domestic zstatic fallback even while local MirrorHealth still says jsd=true.
		$state['manifest']['classes']['npm']['state'] = 'down';
		$this->assertSame(
			'https://s4.zstatic.net/npm/@twemoji/api/dist/15.0.3/svg/1f600.svg',
			$mod_ok->rewrite( $emoji )
		);

		ob_start();
		$mod_ok->print_emoji_settings();
		$emoji_down_js = (string) ob_get_clean();
		$this->assertStringContainsString( 'https://s4.zstatic.net/npm/@twemoji/api@15.0.3/dist/72x72/', $emoji_down_js );
		$this->assertStringContainsString( 'https://s4.zstatic.net/npm/@twemoji/api@15.0.3/dist/svg/', $emoji_down_js );
		$this->assertStringNotContainsString( 'jsd.admincdn.com', $emoji_down_js );

		// Manifest npm class ok -> primary jsd.admincdn.com in print_emoji_settings().
		$state['manifest']['classes']['npm']['state'] = 'ok';
		ob_start();
		$mod_ok->print_emoji_settings();
		$emoji_ok_js = (string) ob_get_clean();
		$this->assertStringContainsString( 'https://jsd.admincdn.com/npm/@twemoji/api@15.0.3/dist/72x72/', $emoji_ok_js );
		$this->assertStringContainsString( 'https://jsd.admincdn.com/npm/@twemoji/api@15.0.3/dist/svg/', $emoji_ok_js );

		// killSwitch=true -> s.w.org passes through unchanged and register() skips emoji hooks.
		$state['manifest']['classes']['npm']['state'] = 'ok';
		$state['manifest']['killSwitch']              = true;
		$this->assertSame( $emoji, $mod_ok->rewrite( $emoji ) );
		HookStore::reset();
		$mod_ok->register();
		$this->assertArrayNotHasKey( 'emoji_url', HookStore::$hooks );
	}

	/**
	 * Cdnjs rewrites cleanly for both state=ok (stripped /ajax/libs/) and state=down (zstatic /ajax/libs/).
	 */
	public function test_cdnjs_stripped_prefix_template_expansion_ok_and_down(): void {
		$signed = $this->signed_fixture( array( 'issuedAt' => '2026-11-26T08:00:00Z' ) );
		$doc    = json_decode( $signed['raw'], true );
		$this->assertIsArray( $doc );
		$doc['classes']['cdnjs'] = array(
			'resourceKind'    => 'immutable_pkg',
			'primary'         => 'https://cdnjs.admincdn.com/{lib}/{ver}/{file}',
			'state'           => 'ok',
			'clientFallbacks' => array(
				'https://s4.zstatic.net/ajax/libs/{lib}/{ver}/{file}',
			),
		);
		$state                   = array(
			'manifest'  => $doc,
			'stored_at' => time(),
		);
		$manifest                = new Manifest(
			new MapConfig( array() ),
			new CachePurger(),
			$signed['keyring'],
			null,
			static function () use ( &$state ) {
				return $state;
			}
		);
		$config                  = new MapConfig(
			array(
				'connectivity.public_assets' => array(
					'items' => array( 'cdnjs' ),
					'scope' => 'both',
				),
				'recovery_mode'              => false,
			)
		);
		$health                  = new MirrorHealth(
			array(
				'cdnjs.admincdn.com' => true,
				's4.zstatic.net'     => true,
			)
		);
		$module                  = new PublicAssetsModule( $config, new AssetMap(), $health, $manifest );
		$origin                  = 'https://cdnjs.cloudflare.com/ajax/libs/lodash.js/4.17.21/lodash.min.js';

		$this->assertSame(
			'https://cdnjs.admincdn.com/lodash.js/4.17.21/lodash.min.js',
			$module->rewrite( $origin )
		);

		$state['manifest']['classes']['cdnjs']['state'] = 'down';
		$this->assertSame(
			'https://s4.zstatic.net/ajax/libs/lodash.js/4.17.21/lodash.min.js',
			$module->rewrite( $origin )
		);
	}

	/**
	 * Active signed manifest keeps legacy jsdelivr aliases on jsd.admincdn.com (ok/degraded) and falls back domestically when down.
	 */
	public function test_active_manifest_rewrites_legacy_jsdelivr_aliases_to_jsd_and_domestic_fallback(): void {
		$signed = $this->signed_fixture( array( 'issuedAt' => gmdate( 'Y-m-d\TH:i:s\Z' ) ) );
		$doc    = json_decode( $signed['raw'], true );
		$this->assertIsArray( $doc );

		$state    = array(
			'manifest'  => $doc,
			'stored_at' => time(),
		);
		$manifest = new Manifest(
			new MapConfig( array() ),
			new CachePurger(),
			$signed['keyring'],
			null,
			static function () use ( &$state ) {
				return $state;
			}
		);
		$config   = new MapConfig(
			array(
				'connectivity.public_assets' => array(
					'items' => array( 'jsdelivr' ),
					'scope' => 'both',
				),
				'recovery_mode'              => false,
			)
		);
		$health   = new MirrorHealth(
			array(
				'jsd.admincdn.com'  => true,
				's4.zstatic.net'    => true,
				'cdn.jsdmirror.com' => true,
			)
		);
		$module   = new PublicAssetsModule( $config, new AssetMap(), $health, $manifest );

		$cases = array(
			'https://code.jquery.com/jquery-3.7.1.min.js' => array(
				'primary'  => 'https://jsd.admincdn.com/npm/jquery/jquery-3.7.1.min.js',
				'fallback' => 'https://s4.zstatic.net/npm/jquery/jquery-3.7.1.min.js',
			),
			'https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js' => array(
				'primary'  => 'https://jsd.admincdn.com/bootstrap/3.3.7/js/bootstrap.min.js',
				'fallback' => 'https://cdn.jsdmirror.com/bootstrap/3.3.7/js/bootstrap.min.js',
			),
			'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js' => array(
				'primary'  => 'https://jsd.admincdn.com/npm/datatables.net/1.13.6/js/jquery.dataTables.min.js',
				'fallback' => 'https://s4.zstatic.net/npm/datatables.net/1.13.6/js/jquery.dataTables.min.js',
			),
		);

		foreach ( array( 'ok', 'degraded' ) as $npm_state ) {
			$state['manifest']['classes']['npm']['state'] = $npm_state;
			foreach ( $cases as $origin => $expected ) {
				$this->assertSame( $expected['primary'], $module->rewrite( $origin ) );
			}
		}

		$state['manifest']['classes']['npm']['state'] = 'down';
		foreach ( $cases as $origin => $expected ) {
			$this->assertSame( $expected['fallback'], $module->rewrite( $origin ) );
		}

		$state['manifest']['classes']['npm']['state'] = 'ok';
		$state['manifest']['killSwitch']              = true;
		foreach ( $cases as $origin => $unused ) {
			unset( $unused );
			$this->assertSame( $origin, $module->rewrite( $origin ) );
		}
	}

	/**
	 * 304 Not Modified sync refreshes stored_at so valid LKG survives beyond 7 days from initial fetch.
	 */
	public function test_304_not_modified_refreshes_stored_at_and_preserves_lkg_beyond_7_days(): void {
		$t0     = '2026-11-26T08:00:00Z';
		$signed = $this->signed_fixture( array( 'issuedAt' => $t0 ) );
		$state  = array();
		$round  = 0;

		$manifest = new Manifest(
			new MapConfig( array() ),
			new CachePurger(),
			$signed['keyring'],
			static function () use ( &$round, $signed ) {
				++$round;
				if ( 1 === $round ) {
					return array(
						'status' => 200,
						'body'   => $signed['raw'],
						'etag'   => '"v1"',
					);
				}
				return array(
					'status' => 304,
					'body'   => '',
					'etag'   => '"v1"',
				);
			},
			static function () use ( &$state ) {
				return $state;
			},
			static function ( array $s ) use ( &$state ) {
				$state = $s;
			}
		);

		$r1 = $manifest->sync( false, '2026-11-26T08:01:00Z' );
		$this->assertTrue( $r1['ok'] );
		$initial_stored_at = (int) $state['stored_at'];

		// 6 days later, 304 refreshes stored_at.
		$r2 = $manifest->sync( false, '2026-12-02T08:01:00Z' );
		$this->assertTrue( $r2['ok'] );
		$this->assertSame( 'not_modified', $r2['status'] );
		$this->assertGreaterThan( $initial_stored_at, (int) $state['stored_at'] );

		// 8 days after initial sync (only 2 days after 304), LKG remains active and not stale.
		$day8_epoch = (int) strtotime( '2026-12-04T08:01:00Z' );
		$snapshot   = $manifest->status_snapshot( $day8_epoch );
		$this->assertFalse( $snapshot['stale_lkg'] );
		$health = new MirrorHealth( array( 'jsd.admincdn.com' => true ) );
		$this->assertSame(
			'https://jsd.admincdn.com/npm/jquery@3.7.1/dist/jquery.min.js',
			$manifest->resolve_url( 'https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js', 'jsdelivr', $health, $day8_epoch )
		);
	}

	/**
	 * 304 Not Modified fails closed when the cached manifest keyId is revoked or outside window.
	 */
	public function test_304_not_modified_fails_closed_when_cached_key_is_revoked(): void {
		$t0      = '2026-11-26T08:00:00Z';
		$signed  = $this->signed_fixture( array( 'issuedAt' => $t0 ) );
		$keyring = $signed['keyring'];
		$state   = array();
		$round   = 0;

		$manifest = new Manifest(
			new MapConfig( array() ),
			new CachePurger(),
			null,
			static function () use ( &$round, $signed ) {
				++$round;
				if ( 1 === $round ) {
					return array(
						'status' => 200,
						'body'   => $signed['raw'],
						'etag'   => '"v1"',
					);
				}
				return array(
					'status' => 304,
					'body'   => '',
					'etag'   => '"v1"',
				);
			},
			static function () use ( &$state ) {
				return $state;
			},
			static function ( array $s ) use ( &$state ) {
				$state = $s;
			}
		);

		add_filter(
			'wpcy_mirror_manifest_keyring',
			static function () use ( &$keyring ) {
				return $keyring;
			}
		);

		$r1 = $manifest->sync( false, '2026-11-26T08:01:00Z' );
		$this->assertTrue( $r1['ok'] );
		$initial_stored_at = (int) $state['stored_at'];

		// Revoke the signing key in the keyring; subsequent 304 must fail closed and NOT refresh stored_at.
		$keyring['admincdn-test-2026-11']['status'] = 'revoked';
		$r2 = $manifest->sync( true, '2026-11-27T08:01:00Z' );
		$this->assertFalse( $r2['ok'] );
		$this->assertSame( 'failed', $r2['status'] );
		$this->assertSame( 'revoked_key', $r2['code'] );
		$this->assertSame( $initial_stored_at, (int) $state['stored_at'] );
		$this->assertSame( 'revoked_key', $state['last_error'] );

		// Restore active status but expire notAfter before eval_now; 304 must fail with key_outside_window.
		$keyring['admincdn-test-2026-11']['status']   = 'active';
		$keyring['admincdn-test-2026-11']['notAfter'] = '2026-11-27T00:00:00Z';
		$r3 = $manifest->sync( true, '2026-11-27T08:02:00Z' );
		$this->assertFalse( $r3['ok'] );
		$this->assertSame( 'key_outside_window', $r3['code'] );
		$this->assertSame( $initial_stored_at, (int) $state['stored_at'] );
	}

	/**
	 * 304 Not Modified succeeds for legacy states without raw_json (preserving empty lists/maps) and fails closed on tamper.
	 */
	public function test_304_not_modified_without_raw_json_preserves_empty_lists_and_fails_closed_on_tamper(): void {
		$dir  = dirname( __DIR__, 3 ) . '/fixtures/manifest';
		$base = json_decode( (string) file_get_contents( $dir . '/mirror-manifest.valid.json' ), false ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture.
		$this->assertIsObject( $base );
		$classes = $base->classes;
		$this->assertIsObject( $classes );
		$classes->googlefonts->regions      = new \stdClass();
		$classes->release->hashes           = new \stdClass();
		$classes->wpstatic->clientFallbacks = array();

		foreach ( array( 'admincdn-test-2026-11', 'admincdn-test-2026-10-retiring' ) as $key_id ) {
			$signed  = $this->signed_fixture(
				array(
					'issuedAt' => '2026-11-26T08:00:00Z',
					'classes'  => $classes,
					'notices'  => array(),
				),
				$key_id
			);
			$doc_arr = json_decode( $signed['raw'], true );
			$this->assertIsArray( $doc_arr );

			$initial_stored_at = (int) strtotime( '2026-11-26T08:01:00Z' );
			$state             = array(
				'manifest'  => $doc_arr,
				'version'   => (int) $doc_arr['version'],
				'etag'      => '"legacy-v1"',
				'stored_at' => $initial_stored_at,
			);

			$manifest = new Manifest(
				new MapConfig( array() ),
				new CachePurger(),
				$signed['keyring'],
				static function () {
					return array(
						'status' => 304,
						'body'   => '',
						'etag'   => '"legacy-v1"',
					);
				},
				static function () use ( &$state ) {
					return $state;
				},
				static function ( array $s ) use ( &$state ) {
					$state = $s;
				}
			);

			$ok = $manifest->sync( true, '2026-12-04T08:01:00Z' );
			$this->assertTrue( $ok['ok'], '304 without raw_json must succeed for key ' . $key_id . ': ' . $ok['code'] );
			$this->assertSame( 'not_modified', $ok['status'] );
			$this->assertGreaterThan( $initial_stored_at, (int) $state['stored_at'] );

			// Tampering the cached manifest array without raw_json must fail closed with invalid_signature.
			$refreshed_at                    = (int) $state['stored_at'];
			$state['manifest']['killSwitch'] = true;
			$bad                             = $manifest->sync( true, '2026-12-04T08:02:00Z' );
			$this->assertFalse( $bad['ok'] );
			$this->assertSame( 'failed', $bad['status'] );
			$this->assertSame( 'invalid_signature', $bad['code'] );
			$this->assertSame( $refreshed_at, (int) $state['stored_at'] );
			$this->assertSame( 'invalid_signature', $state['last_error'] );
		}
	}
}
