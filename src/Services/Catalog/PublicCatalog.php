<?php
/**
 * Public static CDN catalog consumer for WPCY 4.0 (`updates.wenpai.net/wpcy/catalog.json`).
 *
 * Fetches read-only public catalog metadata (quotas, spotlight promos, cloud toolbox entries)
 * strictly off the frontend critical path (WP-Cron or manual admin refresh), with 6h fresh cache,
 * 72h stale fallback, 1h error backoff, and strict schema sanitization.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Services\Catalog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Zero-auth static catalog sync with strict schema sanitization and stale fallback.
 */
final class PublicCatalog {

	/**
	 * Default public static JSON URL served by WenPai Bridge / CDN edge.
	 *
	 * @since 4.0.0
	 */
	public const ENDPOINT = 'https://updates.wenpai.net/wpcy/catalog.json';

	/**
	 * WP-Cron hook name for background catalog refresh.
	 *
	 * @since 4.0.0
	 */
	public const CRON_HOOK = 'wpcy_sync_public_catalog';

	/**
	 * Fresh transient key (6h).
	 *
	 * @since 4.0.0
	 */
	public const TRANSIENT_FRESH = 'wpcy_public_catalog';

	/**
	 * Stale fallback transient key (72h).
	 *
	 * @since 4.0.0
	 */
	public const TRANSIENT_STALE = 'wpcy_public_catalog_stale';

	/**
	 * Error backoff transient key (1h).
	 *
	 * @since 4.0.0
	 */
	public const TRANSIENT_BACKOFF = 'wpcy_public_catalog_backoff';

	/**
	 * Fresh TTL in seconds (6 hours).
	 *
	 * @since 4.0.0
	 */
	public const TTL_FRESH = 21600;

	/**
	 * Stale TTL in seconds (72 hours).
	 *
	 * @since 4.0.0
	 */
	public const TTL_STALE = 259200;

	/**
	 * Error backoff TTL in seconds (1 hour).
	 *
	 * @since 4.0.0
	 */
	public const TTL_BACKOFF = 3600;

	/**
	 * Maximum accepted JSON body bytes (64 KB).
	 *
	 * @since 4.0.0
	 */
	private const MAX_BYTES = 65536;

	/**
	 * HTTP GET callable.
	 *
	 * @var callable
	 */
	private $http;

	/**
	 * Transient reader.
	 *
	 * @var callable
	 */
	private $get_transient;

	/**
	 * Transient writer.
	 *
	 * @var callable
	 */
	private $set_transient;

	/**
	 * Endpoint override (tests).
	 *
	 * @var string|null
	 */
	private ?string $endpoint;

	/**
	 * Constructor. Does not perform HTTP.
	 *
	 * @param callable|null $http          HTTP GET callback `fn(string $url, array $args): array|\WP_Error`.
	 * @param callable|null $get_transient Transient reader.
	 * @param callable|null $set_transient Transient writer.
	 * @param string|null   $endpoint      Optional URL override.
	 */
	public function __construct( $http = null, $get_transient = null, $set_transient = null, ?string $endpoint = null ) {
		$this->http          = null !== $http ? $http : 'wp_remote_get';
		$this->get_transient = null !== $get_transient ? $get_transient : 'get_transient';
		$this->set_transient = null !== $set_transient ? $set_transient : 'set_transient';
		$this->endpoint      = $endpoint;
	}

	/**
	 * Read cached public catalog without issuing any outbound HTTP request.
	 *
	 * @return array<string, mixed> Sanitized catalog map or empty array.
	 */
	public static function cached(): array {
		if ( ! function_exists( 'get_transient' ) ) {
			return array();
		}
		$fresh = get_transient( self::TRANSIENT_FRESH );
		if ( is_array( $fresh ) ) {
			return $fresh;
		}
		$stale = get_transient( self::TRANSIENT_STALE );
		if ( is_array( $stale ) ) {
			return $stale;
		}
		return array();
	}

	/**
	 * Sync the public catalog from CDN edge (unless fresh or backed off, unless `$force` is true).
	 *
	 * @param bool $force Bypass fresh cache and error backoff.
	 * @return array{
	 *     source: string,
	 *     updated_at: string,
	 *     quotas: list<array<string, mixed>>,
	 *     promos: list<array<string, mixed>>,
	 *     toolbox: list<array<string, mixed>>
	 * }
	 */
	public function sync( bool $force = false ): array {
		if ( ! $force ) {
			$fresh = $this->read_cached( self::TRANSIENT_FRESH );
			if ( null !== $fresh ) {
				return $this->envelope( 'cache', $fresh );
			}
			if ( false !== call_user_func( $this->get_transient, self::TRANSIENT_BACKOFF ) ) {
				$stale = $this->read_cached( self::TRANSIENT_STALE );
				return null !== $stale ? $this->envelope( 'stale', $stale ) : $this->empty_envelope();
			}
		}

		$url = $this->resolve_endpoint();
		if ( '' === $url ) {
			$stale = $this->read_cached( self::TRANSIENT_STALE );
			return null !== $stale ? $this->envelope( 'stale', $stale ) : $this->empty_envelope();
		}

		$response = call_user_func(
			$this->http,
			$url,
			array(
				'timeout'     => 4,
				'redirection' => 2,
				'sslverify'   => true,
				'headers'     => array( 'Accept' => 'application/json' ),
			)
		);

		$parsed = $this->parse_response( $response );
		if ( null === $parsed ) {
			call_user_func( $this->set_transient, self::TRANSIENT_BACKOFF, '1', self::TTL_BACKOFF );
			$stale = $this->read_cached( self::TRANSIENT_STALE );
			return null !== $stale ? $this->envelope( 'stale', $stale ) : $this->empty_envelope();
		}

		call_user_func( $this->set_transient, self::TRANSIENT_FRESH, $parsed, self::TTL_FRESH );
		call_user_func( $this->set_transient, self::TRANSIENT_STALE, $parsed, self::TTL_STALE );

		return $this->envelope( 'remote', $parsed );
	}

	/**
	 * Resolve public catalog endpoint (supports `wpcy_public_catalog_url` filter).
	 */
	private function resolve_endpoint(): string {
		$url = null !== $this->endpoint ? $this->endpoint : self::ENDPOINT;
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'wpcy_public_catalog_url', $url );
			if ( is_string( $filtered ) ) {
				$url = trim( $filtered );
			}
		}
		if ( 0 !== strpos( $url, 'https://' ) ) {
			return '';
		}
		return $url;
	}

	/**
	 * Parse and strictly sanitize HTTP response JSON.
	 *
	 * @param mixed $response `wp_remote_get()` result.
	 * @return array{updated_at: string, quotas: list<array<string, mixed>>, promos: list<array<string, mixed>>, toolbox: list<array<string, mixed>>}|null
	 */
	private function parse_response( $response ): ?array {
		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			return null;
		}
		$code = isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
		if ( 200 !== $code ) {
			return null;
		}
		$body = isset( $response['body'] ) && is_string( $response['body'] ) ? $response['body'] : '';
		if ( '' === $body || strlen( $body ) > self::MAX_BYTES ) {
			return null;
		}
		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			return null;
		}

		return array(
			'updated_at' => isset( $decoded['updated_at'] ) && is_string( $decoded['updated_at'] ) ? self::clean_text( $decoded['updated_at'], 40 ) : '',
			'quotas'     => self::sanitize_quotas( isset( $decoded['quotas'] ) && is_array( $decoded['quotas'] ) ? $decoded['quotas'] : array() ),
			'promos'     => self::sanitize_promos( isset( $decoded['promos'] ) && is_array( $decoded['promos'] ) ? $decoded['promos'] : array() ),
			'toolbox'    => self::sanitize_toolbox( isset( $decoded['toolbox'] ) && is_array( $decoded['toolbox'] ) ? $decoded['toolbox'] : array() ),
		);
	}

	/**
	 * Sanitize quota entries (max 12).
	 *
	 * @param array<int|string, mixed> $rows Raw quota rows.
	 * @return list<array<string, mixed>>
	 */
	private static function sanitize_quotas( array $rows ): array {
		$out = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['name'] ) || ! is_string( $row['name'] ) ) {
				continue;
			}
			$item = array(
				'name'  => self::clean_text( $row['name'], 64 ),
				'usage' => isset( $row['usage'] ) && is_string( $row['usage'] ) ? self::clean_text( $row['usage'], 64 ) : '',
			);
			if ( isset( $row['id'] ) && is_string( $row['id'] ) && '' !== $row['id'] ) {
				$item['id'] = sanitize_key( $row['id'] );
			}
			if ( isset( $row['percent'] ) && is_numeric( $row['percent'] ) ) {
				$item['percent'] = max( 0, min( 100, (int) $row['percent'] ) );
			}
			if ( isset( $row['note'] ) && is_string( $row['note'] ) && '' !== $row['note'] ) {
				$item['note'] = self::clean_text( $row['note'], 96 );
			}
			$action_url = isset( $row['action_url'] ) && is_string( $row['action_url'] ) ? self::clean_https_url( $row['action_url'] ) : '';
			if ( '' !== $action_url && isset( $row['action_label'] ) && is_string( $row['action_label'] ) && '' !== $row['action_label'] ) {
				$item['action_label'] = self::clean_text( $row['action_label'], 32 );
				$item['action_url']   = $action_url;
			}
			$out[] = $item;
			if ( count( $out ) >= 12 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Sanitize spotlight promo slides (max 6).
	 *
	 * @param array<int|string, mixed> $rows Raw promo rows.
	 * @return list<array<string, mixed>>
	 */
	private static function sanitize_promos( array $rows ): array {
		$out = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['title'] ) || ! is_string( $row['title'] ) ) {
				continue;
			}
			$pills = array();
			if ( isset( $row['pills'] ) && is_array( $row['pills'] ) ) {
				foreach ( array_slice( $row['pills'], 0, 4 ) as $p ) {
					if ( is_string( $p ) && '' !== trim( $p ) ) {
						$pills[] = self::clean_text( $p, 32 );
					}
				}
			}
			$cta_url = isset( $row['cta_url'] ) && is_string( $row['cta_url'] ) ? self::clean_https_url( $row['cta_url'] ) : '';
			$out[]   = array(
				'badge'      => isset( $row['badge'] ) && is_string( $row['badge'] ) ? self::clean_text( $row['badge'], 24 ) : '',
				'sub'        => isset( $row['sub'] ) && is_string( $row['sub'] ) ? self::clean_text( $row['sub'], 32 ) : '',
				'title'      => self::clean_text( $row['title'], 80 ),
				'desc'       => isset( $row['desc'] ) && is_string( $row['desc'] ) ? self::clean_text( $row['desc'], 160 ) : '',
				'pills'      => $pills,
				'foot_meta'  => isset( $row['foot_meta'] ) && is_string( $row['foot_meta'] ) ? self::clean_text( $row['foot_meta'], 40 ) : '',
				'cta_label'  => '' !== $cta_url && isset( $row['cta_label'] ) && is_string( $row['cta_label'] ) ? self::clean_text( $row['cta_label'], 32 ) : '',
				'cta_url'    => $cta_url,
				'cta_target' => '' !== $cta_url ? '_blank' : '',
			);
			if ( count( $out ) >= 6 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Sanitize toolbox launcher entries (max 4).
	 *
	 * @param array<int|string, mixed> $rows Raw toolbox rows.
	 * @return list<array<string, mixed>>
	 */
	private static function sanitize_toolbox( array $rows ): array {
		$out = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['label'] ) || ! is_string( $row['label'] ) ) {
				continue;
			}
			$href = isset( $row['href'] ) && is_string( $row['href'] ) ? self::clean_https_url( $row['href'] ) : '';
			if ( '' === $href ) {
				continue;
			}
			$out[] = array(
				'label'  => self::clean_text( $row['label'], 24 ),
				'icon'   => isset( $row['icon'] ) && is_string( $row['icon'] ) && '' !== $row['icon'] ? sanitize_key( $row['icon'] ) : 'sparkle',
				'href'   => $href,
				'target' => '_blank',
			);
			if ( count( $out ) >= 4 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Strip tags and clamp plain text length.
	 *
	 * @param string $value Raw string.
	 * @param int    $max   Max character count.
	 */
	private static function clean_text( string $value, int $max ): string {
		$clean = trim( wp_strip_all_tags( $value ) );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $clean, 0, $max, 'UTF-8' );
		}
		return substr( $clean, 0, $max );
	}

	/**
	 * Allow only `https://` URLs.
	 *
	 * @param string $url Raw URL.
	 */
	private static function clean_https_url( string $url ): string {
		$trimmed = trim( $url );
		if ( 0 !== strpos( $trimmed, 'https://' ) ) {
			return '';
		}
		return function_exists( 'esc_url_raw' ) ? esc_url_raw( $trimmed, array( 'https' ) ) : $trimmed;
	}

	/**
	 * Read cached catalog array from transient or return null.
	 *
	 * @param string $key Transient key.
	 * @return array{updated_at: string, quotas: list<array<string, mixed>>, promos: list<array<string, mixed>>, toolbox: list<array<string, mixed>>}|null
	 */
	private function read_cached( string $key ): ?array {
		$raw = call_user_func( $this->get_transient, $key );
		if ( ! is_array( $raw ) ) {
			return null;
		}
		return array(
			'updated_at' => isset( $raw['updated_at'] ) && is_string( $raw['updated_at'] ) ? $raw['updated_at'] : '',
			'quotas'     => isset( $raw['quotas'] ) && is_array( $raw['quotas'] ) ? array_values( $raw['quotas'] ) : array(),
			'promos'     => isset( $raw['promos'] ) && is_array( $raw['promos'] ) ? array_values( $raw['promos'] ) : array(),
			'toolbox'    => isset( $raw['toolbox'] ) && is_array( $raw['toolbox'] ) ? array_values( $raw['toolbox'] ) : array(),
		);
	}

	/**
	 * Wrap sanitized data with source tag.
	 *
	 * @param string                                                                                                                                 $source Source tag.
	 * @param array{updated_at: string, quotas: list<array<string, mixed>>, promos: list<array<string, mixed>>, toolbox: list<array<string, mixed>>} $data   Payload.
	 * @return array{source: string, updated_at: string, quotas: list<array<string, mixed>>, promos: list<array<string, mixed>>, toolbox: list<array<string, mixed>>}
	 */
	private function envelope( string $source, array $data ): array {
		return array(
			'source'     => $source,
			'updated_at' => $data['updated_at'],
			'quotas'     => $data['quotas'],
			'promos'     => $data['promos'],
			'toolbox'    => $data['toolbox'],
		);
	}

	/**
	 * Empty fallback envelope.
	 *
	 * @return array{source: string, updated_at: string, quotas: list<array<string, mixed>>, promos: list<array<string, mixed>>, toolbox: list<array<string, mixed>>}
	 */
	private function empty_envelope(): array {
		return array(
			'source'     => 'fallback',
			'updated_at' => '',
			'quotas'     => array(),
			'promos'     => array(),
			'toolbox'    => array(),
		);
	}
}
