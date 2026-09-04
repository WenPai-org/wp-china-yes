<?php
/**
 * Signed apps index: mock/fixture source, 24h transient, fail closed.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Apps;

use WenPai\ChinaYes\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pulls a signed index. Does not fetch production apps.wpcy.com unless the source is set.
 */
final class Index {

	/**
	 * Production index URL. Not the default source.
	 *
	 * @since 4.0.0
	 */
	public const PRODUCTION_URL = 'https://apps.wpcy.com/index.json';

	/**
	 * Transient that holds the last verified apps list.
	 *
	 * @since 4.0.0
	 */
	public const TRANSIENT_KEY = 'wpcy_apps_index';

	/**
	 * Cache TTL in seconds (24h).
	 *
	 * @since 4.0.0
	 */
	public const TTL = 86400;

	/**
	 * Verifier.
	 *
	 * @var ManifestVerifier
	 */
	private ManifestVerifier $verifier;

	/**
	 * Local path or HTTPS URL. Empty means do not fetch.
	 *
	 * @var string
	 */
	private string $source;

	/**
	 * Optional HTTP/file fetcher. Callable is not a PHP 7.4 property type.
	 *
	 * @var callable|null
	 */
	private $fetcher;

	/**
	 * Logger for index-level signature failures.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Plugin version used against min_plugin_version.
	 *
	 * @var string
	 */
	private string $plugin_version;

	/**
	 * Constructor. Does not fetch.
	 *
	 * @since 4.0.0
	 *
	 * @param ManifestVerifier $verifier        Ed25519 verifier.
	 * @param string           $source          Fixture path or HTTPS URL. Empty disables fetch.
	 * @param callable|null    $fetcher         Optional `fn(string $source): string`.
	 * @param Logger|null      $logger          Logger. Null constructs the default.
	 * @param string|null      $plugin_version  Plugin version. Null uses CHINA_YES_VERSION.
	 */
	public function __construct(
		ManifestVerifier $verifier,
		string $source = '',
		$fetcher = null,
		$logger = null,
		$plugin_version = null
	) {
		$this->verifier = $verifier;
		$this->source   = $source;
		$this->fetcher  = is_callable( $fetcher ) ? $fetcher : null;
		$this->logger   = $logger instanceof Logger ? $logger : new Logger();
		if ( is_string( $plugin_version ) && '' !== $plugin_version ) {
			$this->plugin_version = $plugin_version;
		} else {
			$this->plugin_version = '4.0.0';
		}
	}

	/**
	 * Last verified apps list from the transient, or empty.
	 *
	 * @since 4.0.0
	 *
	 * @return list<array<string, mixed>>
	 */
	public function cached(): array {
		if ( ! function_exists( 'get_transient' ) ) {
			return array();
		}
		$stored = get_transient( self::TRANSIENT_KEY );
		return $this->sanitize_cached( $stored );
	}

	/**
	 * Fetch, verify, replace the cache. Index-level failure keeps the previous cache.
	 *
	 * @since 4.0.0
	 *
	 * @return list<array<string, mixed>>
	 */
	public function refresh(): array {
		$previous = $this->cached();
		if ( '' === $this->source ) {
			return $previous;
		}

		$raw = $this->read_source();
		if ( '' === $raw ) {
			return $previous;
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || $this->is_list( $decoded ) ) {
			return $previous;
		}

		if ( ! $this->verifier->verify( $decoded ) ) {
			$this->logger->log(
				'warning',
				'Apps index signature invalid; keeping previous cache.',
				array(
					'code' => 'wpcy_apps_signature_invalid',
				)
			);
			return $previous;
		}

		$apps = isset( $decoded['apps'] ) && is_array( $decoded['apps'] ) ? $decoded['apps'] : array();
		$keep = array();
		foreach ( $apps as $manifest ) {
			$verified = $this->accept_manifest( $manifest );
			if ( is_array( $verified ) ) {
				$keep[] = $verified;
			}
		}

		$this->store( $keep );
		return $keep;
	}

	/**
	 * Verified list: refresh when the cache is empty, otherwise the cache.
	 *
	 * @since 4.0.0
	 *
	 * @return list<array<string, mixed>>
	 */
	public function apps(): array {
		$cached = $this->cached();
		if ( array() !== $cached ) {
			return $cached;
		}
		return $this->refresh();
	}

	/**
	 * Configured source. Empty when production fetch is disabled.
	 *
	 * @since 4.0.0
	 */
	public function source(): string {
		return $this->source;
	}

	/**
	 * One manifest: signature, origin, schema, min_plugin_version.
	 *
	 * Failed verify: that tool is omitted.
	 *
	 * @param mixed $manifest Candidate.
	 * @return array<string, mixed>|null
	 */
	private function accept_manifest( $manifest ) {
		if ( ! is_array( $manifest ) || $this->is_list( $manifest ) ) {
			return null;
		}
		if ( ! $this->verifier->verify( $manifest ) ) {
			return null;
		}

		$id = isset( $manifest['id'] ) && is_string( $manifest['id'] ) ? $manifest['id'] : '';
		if ( '' === $id ) {
			return null;
		}

		$entry = isset( $manifest['entry_url'] ) && is_string( $manifest['entry_url'] ) ? $manifest['entry_url'] : '';
		$icon  = isset( $manifest['icon'] ) && is_string( $manifest['icon'] ) ? $manifest['icon'] : '';
		if ( ! ManifestVerifier::origin_allowed( $entry ) ) {
			return null;
		}
		if ( '' !== $icon && ! ManifestVerifier::origin_allowed( $icon ) ) {
			return null;
		}

		$min = isset( $manifest['min_plugin_version'] ) && is_string( $manifest['min_plugin_version'] )
			? $manifest['min_plugin_version']
			: '';
		if ( '' !== $min && version_compare( $this->plugin_version, $min, '<' ) ) {
			return null;
		}

		$schema = isset( $manifest['schema_version'] ) ? (int) $manifest['schema_version'] : 0;
		if ( 1 !== $schema ) {
			return null;
		}

		unset( $manifest['signature'] );
		return $manifest;
	}

	/**
	 * Read the source. Local files do not use HTTP.
	 *
	 * @return string
	 */
	private function read_source(): string {
		if ( is_callable( $this->fetcher ) ) {
			$result = call_user_func( $this->fetcher, $this->source );
			return is_string( $result ) ? $result : '';
		}

		if ( $this->is_local_path( $this->source ) ) {
			if ( ! is_readable( $this->source ) ) {
				return '';
			}
			$raw = file_get_contents( $this->source ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture, not a remote URL.
			return is_string( $raw ) ? $raw : '';
		}

		if ( 0 !== strpos( $this->source, 'https://' ) || ! ManifestVerifier::origin_allowed( $this->source ) ) {
			return '';
		}

		if ( ! function_exists( 'wp_remote_get' ) ) {
			return '';
		}

		$response = wp_remote_get( $this->source );
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $response ) ) {
			return '';
		}

		$code = function_exists( 'wp_remote_retrieve_response_code' )
			? (int) wp_remote_retrieve_response_code( $response )
			: 0;
		if ( 200 !== $code ) {
			return '';
		}

		if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
			return '';
		}
		return (string) wp_remote_retrieve_body( $response );
	}

	/**
	 * Persist verified apps.
	 *
	 * @param list<array<string, mixed>> $apps Apps.
	 */
	private function store( array $apps ): void {
		if ( function_exists( 'set_transient' ) ) {
			$ttl = defined( 'DAY_IN_SECONDS' ) ? (int) DAY_IN_SECONDS : self::TTL;
			set_transient( self::TRANSIENT_KEY, $apps, $ttl );
		}
	}

	/**
	 * Keep only list-shaped cached rows.
	 *
	 * @param mixed $stored Transient value.
	 * @return list<array<string, mixed>>
	 */
	private function sanitize_cached( $stored ): array {
		if ( ! is_array( $stored ) ) {
			return array();
		}
		$out = array();
		foreach ( $stored as $row ) {
			if ( is_array( $row ) && isset( $row['id'] ) && is_string( $row['id'] ) && '' !== $row['id'] ) {
				$out[] = $row;
			}
		}
		return $out;
	}

	/**
	 * Whether $source is a local filesystem path, not an HTTP URL.
	 *
	 * @param string $source Source.
	 */
	private function is_local_path( string $source ): bool {
		return 0 !== strpos( $source, 'http://' ) && 0 !== strpos( $source, 'https://' );
	}

	/**
	 * PHP 7.4 stand-in for array_is_list().
	 *
	 * @param array<mixed> $value Array.
	 */
	private function is_list( array $value ): bool {
		if ( array() === $value ) {
			return true;
		}
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
