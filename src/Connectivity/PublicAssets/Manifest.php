<?php
/**
 * Signed mirror manifest client, 7-day LKG cache, dual-channel ETag sync, and routing fusion.
 *
 * @package WenPai\ChinaYes
 * @since   4.1.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Connectivity\PublicAssets;

use WenPai\ChinaYes\Core\Config;
use WenPai\ChinaYes\Connectivity\MirrorHealth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages admincdn.mirror-manifest/v1 sync, fail-closed LKG storage, and mirror routing.
 */
class Manifest {

	public const OPTION_KEY           = 'wpcy_mirror_manifest';
	public const CRON_HOOK            = 'wpcy_mirror_manifest_sync';
	public const CRON_SCHEDULE        = 'wpcy_10min';
	public const PRIMARY_URL          = 'https://updates.wenpai.net/wpcy/mirror-manifest.json';
	public const SECONDARY_URL        = 'https://admincdn.oss-cn-hangzhou.aliyuncs.com/wpcy/mirror-manifest.json';
	public const LKG_MAX_AGE_SECONDS  = 604800; // 7 days.
	public const BACKOFF_BASE_SECONDS = 120;
	public const BACKOFF_MAX_SECONDS  = 3600;

	/**
	 * Mapping from AssetMap keys to manifest class identifiers.
	 *
	 * @var array<string, string>
	 */
	public const ASSET_KEY_TO_CLASS = array(
		'jsdelivr'      => 'npm',
		'cdnjs'         => 'cdnjs',
		'google_fonts'  => 'googlefonts',
		'gstatic'       => 'gstatic',
		'google_ajax'   => 'googleajax',
		'wp_org_assets' => 'wpstatic',
		'unpkg'         => 'unpkg',
		'emoji'         => 'emoji',
		'code'          => 'code',
	);

	/**
	 * Configuration reader.
	 *
	 * @var Config|null
	 */
	private ?Config $config;

	/**
	 * Page/object cache purger.
	 *
	 * @var CachePurger
	 */
	private CachePurger $purger;

	/**
	 * Optional keyring override for tests.
	 *
	 * @var mixed
	 */
	private $keyring_override;

	/**
	 * Optional HTTP GET callback `(string $url, array $headers): array{status: int, body: string, etag: string}`.
	 *
	 * @var callable|null
	 */
	private $http_get;

	/**
	 * Optional state reader/writer for tests.
	 *
	 * @var callable|null
	 */
	private $state_getter;

	/**
	 * Optional state writer for tests.
	 *
	 * @var callable|null
	 */
	private $state_setter;

	/**
	 * Constructor.
	 *
	 * @param Config|null      $config           Optional configuration reader.
	 * @param CachePurger|null $purger           Optional cache purger.
	 * @param mixed            $keyring_override Optional keyring override for unit tests.
	 * @param callable|null    $http_get         Optional HTTP getter override.
	 * @param callable|null    $state_getter     Optional state reader override.
	 * @param callable|null    $state_setter     Optional state writer override.
	 */
	public function __construct(
		?Config $config = null,
		?CachePurger $purger = null,
		$keyring_override = null,
		?callable $http_get = null,
		?callable $state_getter = null,
		?callable $state_setter = null
	) {
		$this->config           = $config;
		$this->purger           = null !== $purger ? $purger : new CachePurger();
		$this->keyring_override = $keyring_override;
		$this->http_get         = $http_get;
		$this->state_getter     = $state_getter;
		$this->state_setter     = $state_setter;
	}

	/**
	 * Verify a decoded manifest against a keyring (matches T4-S05 static call signature).
	 *
	 * @since 4.1.0
	 *
	 * @param mixed       $manifest    Decoded JSON object or map.
	 * @param mixed       $keyring     Keyring keys map or wrapper; null uses ManifestVerifier::DEFAULT_KEYRING.
	 * @param string|null $eval_now    Optional UTC RFC3339 timestamp.
	 * @param bool        $enforce_ttl Whether to enforce TTL expiry.
	 */
	public static function verify( $manifest, $keyring = null, ?string $eval_now = null, bool $enforce_ttl = false ): bool {
		return ManifestVerifier::verify( $manifest, $keyring, $eval_now, $enforce_ttl );
	}

	/**
	 * Sync signed manifest from primary and secondary channels with ETag and backoff.
	 *
	 * @since 4.1.0
	 *
	 * @param bool        $force    Bypass backoff cooldown.
	 * @param string|null $eval_now Optional UTC evaluation timestamp for deterministic tests.
	 * @return array{ok: bool, status: string, code: string, version: int}
	 */
	public function sync( bool $force = false, ?string $eval_now = null ): array {
		$now   = $this->now_epoch( $eval_now );
		$state = $this->read_state();

		if ( ! $force && isset( $state['next_retry_at'] ) && (int) $state['next_retry_at'] > $now ) {
			return array(
				'ok'      => false,
				'status'  => 'backoff',
				'code'    => 'backoff_active',
				'version' => (int) ( $state['version'] ?? 0 ),
			);
		}

		$primary_url   = function_exists( 'apply_filters' ) ? (string) apply_filters( 'wpcy_mirror_manifest_primary_url', self::PRIMARY_URL ) : self::PRIMARY_URL;
		$secondary_url = function_exists( 'apply_filters' ) ? (string) apply_filters( 'wpcy_mirror_manifest_secondary_url', self::SECONDARY_URL ) : self::SECONDARY_URL;
		$channels      = array_filter(
			array(
				'primary'   => $primary_url,
				'secondary' => $secondary_url,
			),
			static function ( string $u ): bool {
				return '' !== $u;
			}
		);

		$last_code = 'fetch_failed';
		foreach ( $channels as $source_name => $url ) {
			$headers = array( 'Accept' => 'application/json' );
			if ( ! empty( $state['etag'] ) && is_string( $state['etag'] ) ) {
				$headers['If-None-Match'] = $state['etag'];
			}

			$resp = $this->fetch_url( $url, $headers );
			if ( 304 === $resp['status'] && ! empty( $state['manifest'] ) && is_array( $state['manifest'] ) ) {
				$cached_doc = $this->decode_cached_manifest_object( $state );
				if ( null === $cached_doc ) {
					$last_code = 'invalid_json';
					continue;
				}
				$check = ManifestVerifier::verify_detailed(
					$cached_doc,
					$this->keyring(),
					$eval_now ?? gmdate( 'Y-m-d\TH:i:s\Z', $now ),
					false
				);
				if ( ! $check['ok'] ) {
					$last_code = $check['code'];
					continue;
				}

				$state['stored_at']       = $now;
				$state['last_checked_at'] = $now;
				$state['active_source']   = $source_name;
				$state['source_url']      = $url;
				$state['failures']        = 0;
				$state['next_retry_at']   = 0;
				$state['last_error']      = '';
				$this->write_state( $state );

				return array(
					'ok'      => true,
					'status'  => 'not_modified',
					'code'    => 'not_modified',
					'version' => (int) ( $state['version'] ?? 0 ),
				);
			}

			if ( 200 !== $resp['status'] || '' === $resp['body'] ) {
				$last_code = 'http_' . $resp['status'];
				continue;
			}

			$doc = json_decode( $resp['body'], false );
			if ( ! is_object( $doc ) ) {
				$last_code = 'invalid_json';
				continue;
			}

			$check = ManifestVerifier::verify_detailed( $doc, $this->keyring(), $eval_now ?? gmdate( 'Y-m-d\TH:i:s\Z', $now ), true );
			if ( ! $check['ok'] ) {
				$last_code = $check['code'];
				continue;
			}

			$new_manifest = $this->object_to_array( $doc );
			$reason       = $this->detect_transition( $state['manifest'] ?? null, $new_manifest );
			if ( '' !== $reason && $this->should_purge_on_transition() ) {
				$this->purger->purge( $reason );
			}

			$state = array(
				'manifest'        => $new_manifest,
				'raw_json'        => $resp['body'],
				'version'         => (int) $new_manifest['version'],
				'issued_at'       => (string) $new_manifest['issuedAt'],
				'key_id'          => (string) $new_manifest['keyId'],
				'etag'            => $resp['etag'],
				'active_source'   => $source_name,
				'source_url'      => $url,
				'stored_at'       => $now,
				'last_checked_at' => $now,
				'failures'        => 0,
				'next_retry_at'   => 0,
				'last_error'      => '',
				'switch_reason'   => '' !== $reason ? $reason : (string) ( $state['switch_reason'] ?? '' ),
			);
			$this->write_state( $state );

			return array(
				'ok'      => true,
				'status'  => 'updated',
				'code'    => 'ok',
				'version' => (int) $new_manifest['version'],
			);
		}

		$failures                 = (int) ( $state['failures'] ?? 0 ) + 1;
		$delay                    = min( self::BACKOFF_MAX_SECONDS, self::BACKOFF_BASE_SECONDS * ( 1 << min( 5, $failures - 1 ) ) );
		$state['last_checked_at'] = $now;
		$state['failures']        = $failures;
		$state['next_retry_at']   = $now + $delay;
		$state['last_error']      = $last_code;
		$this->write_state( $state );

		return array(
			'ok'      => false,
			'status'  => 'failed',
			'code'    => $last_code,
			'version' => (int) ( $state['version'] ?? 0 ),
		);
	}

	/**
	 * Whether an active non-expired LKG manifest has killSwitch === true.
	 *
	 * @param int|null $now Optional epoch override.
	 */
	public function is_kill_switch( ?int $now = null ): bool {
		$manifest = $this->active_manifest( $now );
		return null !== $manifest && ! empty( $manifest['killSwitch'] );
	}

	/**
	 * Effective manifest class state fused with MirrorHealth, or null if absent.
	 *
	 * @since 4.1.0
	 *
	 * @param string       $cls    Manifest class key.
	 * @param MirrorHealth $health Mirror health evaluator.
	 * @param int|null     $now    Optional epoch override.
	 */
	public function class_state( string $cls, MirrorHealth $health, ?int $now = null ): ?string {
		$manifest = $this->active_manifest( $now );
		if ( null === $manifest || empty( $manifest['classes'][ $cls ] ) || ! is_array( $manifest['classes'][ $cls ] ) ) {
			return null;
		}
		return ManifestRouter::effective_class_state( $manifest['classes'][ $cls ], $health );
	}

	/**
	 * Resolve a public asset URL using the active signed manifest fused with MirrorHealth.
	 *
	 * Returns null when no valid/non-stale LKG manifest covers the asset key so the caller
	 * falls back to static AssetMap::table(). Returns the original $origin_url when
	 * killSwitch=true or when wpstatic is unhealthy (redline: never route wpstatic to 3rd-party or s.w.org).
	 *
	 * @since 4.1.0
	 *
	 * @param string       $origin_url    Original asset URL.
	 * @param string       $asset_key     AssetMap key.
	 * @param MirrorHealth $health        Mirror health probe evaluator.
	 * @param int|null     $now           Optional epoch override.
	 * @param string|null  $canonical_url Optional normalized URL for template parameter extraction.
	 */
	public function resolve_url( string $origin_url, string $asset_key, MirrorHealth $health, ?int $now = null, ?string $canonical_url = null ): ?string {
		$manifest = $this->active_manifest( $now );
		if ( null === $manifest ) {
			return null;
		}
		return ManifestRouter::resolve_url( $manifest, $origin_url, $asset_key, $health, $canonical_url );
	}

	/**
	 * Build sanitized clientFallbacks config map for BrowserFallbackLoader (W6).
	 *
	 * @since 4.1.0
	 *
	 * @param int|null $now Optional epoch override.
	 * @return array{killSwitch: bool, backupHost: string|null, fallbacks: array<string, list<string>>}
	 */
	public function client_fallback_config( ?int $now = null ): array {
		$manifest = $this->active_manifest( $now );
		if ( null === $manifest ) {
			return array(
				'killSwitch' => false,
				'backupHost' => null,
				'fallbacks'  => array(),
			);
		}
		return ManifestRouter::client_fallback_config( $manifest );
	}

	/**
	 * Return status summary for Admin status card and REST endpoint (W7).
	 *
	 * @since 4.1.0
	 *
	 * @param int|null $now Optional epoch override.
	 * @return array<string, mixed>
	 */
	public function status_snapshot( ?int $now = null ): array {
		$ts    = null !== $now ? $now : time();
		$state = $this->read_state();
		$has   = ! empty( $state['manifest'] ) && is_array( $state['manifest'] );
		$age   = $has && ! empty( $state['stored_at'] ) ? max( 0, $ts - (int) $state['stored_at'] ) : null;
		$stale = null !== $age && $age > self::LKG_MAX_AGE_SECONDS;

		$classes = array();
		if ( $has && ! empty( $state['manifest']['classes'] ) && is_array( $state['manifest']['classes'] ) ) {
			foreach ( $state['manifest']['classes'] as $cls => $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}
				$classes[ (string) $cls ] = array(
					'state'          => (string) ( $entry['state'] ?? 'ok' ),
					'primary'        => (string) ( $entry['primary'] ?? '' ),
					'fallback_count' => isset( $entry['clientFallbacks'] ) && is_array( $entry['clientFallbacks'] ) ? count( $entry['clientFallbacks'] ) : 0,
				);
			}
		}

		return array(
			'has_manifest'    => $has,
			'active_source'   => (string) ( $state['active_source'] ?? ( $has ? 'lkg' : 'static_fallback' ) ),
			'source_url'      => (string) ( $state['source_url'] ?? '' ),
			'version'         => (int) ( $state['version'] ?? 0 ),
			'issued_at'       => (string) ( $state['issued_at'] ?? '' ),
			'key_id'          => (string) ( $state['key_id'] ?? '' ),
			'stored_at'       => (int) ( $state['stored_at'] ?? 0 ),
			'age_seconds'     => $age,
			'stale_lkg'       => $stale,
			'kill_switch'     => $has && ! empty( $state['manifest']['killSwitch'] ),
			'switch_reason'   => (string) ( $state['switch_reason'] ?? '' ),
			'last_error'      => (string) ( $state['last_error'] ?? '' ),
			'last_checked_at' => (int) ( $state['last_checked_at'] ?? 0 ),
			'classes'         => $classes,
		);
	}

	/**
	 * Expand `{pkg}`, `{ver}`, `{file}`, `{lib}`, `{path}` template against origin URL.
	 *
	 * @param string $template   Manifest template URL.
	 * @param string $origin_url Original asset URL.
	 */
	public static function expand_template( string $template, string $origin_url ): ?string {
		return ManifestRouter::expand_template( $template, $origin_url );
	}

	/**
	 * Active manifest array if present and within 7-day LKG window.
	 *
	 * @param int|null $now Optional epoch override.
	 * @return array<string, mixed>|null
	 */
	private function active_manifest( ?int $now = null ): ?array {
		$ts    = null !== $now ? $now : time();
		$state = $this->read_state();
		if ( empty( $state['manifest'] ) || ! is_array( $state['manifest'] ) || empty( $state['stored_at'] ) ) {
			return null;
		}
		if ( ( $ts - (int) $state['stored_at'] ) > self::LKG_MAX_AGE_SECONDS ) {
			return null;
		}
		return $state['manifest'];
	}

	/**
	 * Detect failover / kill-switch transitions between old and new manifests.
	 *
	 * @param array<string, mixed>|null $prev Previous manifest.
	 * @param array<string, mixed>      $next New manifest.
	 */
	private function detect_transition( ?array $prev, array $next ): string {
		$prev_ks = null !== $prev && ! empty( $prev['killSwitch'] );
		$next_ks = ! empty( $next['killSwitch'] );
		if ( $prev_ks !== $next_ks ) {
			return $next_ks ? 'kill_switch_enabled' : 'kill_switch_disabled';
		}
		if ( null === $prev || empty( $prev['classes'] ) || empty( $next['classes'] ) ) {
			return '';
		}
		foreach ( $next['classes'] as $cls => $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$new_state = (string) ( $entry['state'] ?? 'ok' );
			$old_state = (string) ( $prev['classes'][ $cls ]['state'] ?? 'ok' );
			if ( 'down' === $new_state && 'down' !== $old_state ) {
				return 'class_down:' . (string) $cls;
			}
		}
		return '';
	}

	/**
	 * Whether cache purge is enabled on failover transitions.
	 */
	private function should_purge_on_transition(): bool {
		if ( null === $this->config ) {
			return true;
		}
		return (bool) $this->config->get( 'connectivity.purge_cache_on_failover', true );
	}

	/**
	 * Resolve active keyring.
	 *
	 * @return mixed
	 */
	private function keyring() {
		if ( null !== $this->keyring_override ) {
			return $this->keyring_override;
		}
		$default = ManifestVerifier::default_keyring();
		return function_exists( 'apply_filters' ) ? apply_filters( 'wpcy_mirror_manifest_keyring', $default ) : $default;
	}

	/**
	 * Fetch manifest URL via WP HTTP API or test callback.
	 *
	 * @param string                $url     Target URL.
	 * @param array<string, string> $headers Request headers.
	 * @return array{status: int, body: string, etag: string}
	 */
	private function fetch_url( string $url, array $headers ): array {
		if ( null !== $this->http_get ) {
			$res = call_user_func( $this->http_get, $url, $headers );
			return is_array( $res ) ? array(
				'status' => (int) ( $res['status'] ?? 0 ),
				'body'   => (string) ( $res['body'] ?? '' ),
				'etag'   => (string) ( $res['etag'] ?? '' ),
			) : array(
				'status' => 0,
				'body'   => '',
				'etag'   => '',
			);
		}
		if ( ! function_exists( 'wp_remote_get' ) || ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
			return array(
				'status' => 0,
				'body'   => '',
				'etag'   => '',
			);
		}
		$resp = wp_remote_get(
			$url,
			array(
				'timeout' => 8,
				'headers' => $headers,
			)
		);
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $resp ) ) {
			return array(
				'status' => 0,
				'body'   => '',
				'etag'   => '',
			);
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		$body = function_exists( 'wp_remote_retrieve_body' ) ? (string) wp_remote_retrieve_body( $resp ) : '';
		$etag = function_exists( 'wp_remote_retrieve_header' ) ? (string) wp_remote_retrieve_header( $resp, 'etag' ) : '';

		return array(
			'status' => $code,
			'body'   => $body,
			'etag'   => $etag,
		);
	}

	/**
	 * Read persisted LKG state from wp_option or test override.
	 *
	 * @return array<string, mixed>
	 */
	private function read_state(): array {
		if ( null !== $this->state_getter ) {
			$val = call_user_func( $this->state_getter );
			return is_array( $val ) ? $val : array();
		}
		if ( ! function_exists( 'get_option' ) ) {
			return array();
		}
		$val = get_option( self::OPTION_KEY, array() );
		return is_array( $val ) ? $val : array();
	}

	/**
	 * Persist LKG state with autoload=false.
	 *
	 * @param array<string, mixed> $state State payload.
	 */
	private function write_state( array $state ): void {
		if ( null !== $this->state_setter ) {
			call_user_func( $this->state_setter, $state );
			return;
		}
		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION_KEY, $state, false );
		}
	}

	/**
	 * Recursively convert stdClass structures into associative arrays for wp_option storage.
	 *
	 * @param mixed $value Node.
	 * @return mixed
	 */
	private function object_to_array( $value ) {
		if ( is_object( $value ) ) {
			$value = get_object_vars( $value );
		}
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $k => $v ) {
				$out[ $k ] = $this->object_to_array( $v );
			}
			return $out;
		}
		return $value;
	}

	/**
	 * Reconstruct cached manifest stdClass object for signature/keyring verification on 304.
	 *
	 * @param array<string, mixed> $state Persisted state payload.
	 */
	private function decode_cached_manifest_object( array $state ): ?\stdClass {
		if ( ! empty( $state['raw_json'] ) && is_string( $state['raw_json'] ) ) {
			$decoded = json_decode( $state['raw_json'], false );
			if ( is_object( $decoded ) ) {
				return $decoded;
			}
			return null;
		}
		if ( empty( $state['manifest'] ) || ! is_array( $state['manifest'] ) ) {
			return null;
		}
		$obj = $this->assoc_to_object( $state['manifest'] );
		if ( isset( $obj->classes ) && is_array( $obj->classes ) && array() === $obj->classes ) {
			$obj->classes = new \stdClass();
		}
		if ( isset( $obj->classes ) && is_object( $obj->classes ) ) {
			foreach ( get_object_vars( $obj->classes ) as $cls_entry ) {
				if ( ! is_object( $cls_entry ) ) {
					continue;
				}
				foreach ( array( 'regions', 'hashes' ) as $map_field ) {
					if ( isset( $cls_entry->$map_field ) && is_array( $cls_entry->$map_field ) && array() === $cls_entry->$map_field ) {
						$cls_entry->$map_field = new \stdClass();
					}
				}
			}
		}
		return $obj;
	}

	/**
	 * Convert associative array recursively into stdClass while preserving list arrays.
	 *
	 * @param array<mixed> $arr Input array.
	 */
	private function assoc_to_object( array $arr ): \stdClass {
		$obj = new \stdClass();
		foreach ( $arr as $k => $v ) {
			$key = (string) $k;
			if ( is_array( $v ) && array() !== $v && array_keys( $v ) !== range( 0, count( $v ) - 1 ) ) {
				$obj->$key = $this->assoc_to_object( $v );
			} elseif ( is_array( $v ) ) {
				$obj->$key = array_map(
					function ( $item ) {
						if ( is_array( $item ) && array() !== $item && array_keys( $item ) !== range( 0, count( $item ) - 1 ) ) {
							return $this->assoc_to_object( $item );
						}
						return $item;
					},
					$v
				);
			} else {
				$obj->$key = $v;
			}
		}
		return $obj;
	}

	/**
	 * Convert optional UTC ISO string or wall-clock time to epoch seconds.
	 *
	 * @param string|null $eval_now Optional UTC string.
	 */
	private function now_epoch( ?string $eval_now ): int {
		if ( null !== $eval_now ) {
			$ts = ManifestVerifier::parse_utc_timestamp( $eval_now );
			if ( null !== $ts ) {
				return $ts[0];
			}
		}
		return time();
	}
}
