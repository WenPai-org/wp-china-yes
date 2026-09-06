<?php
/**
 * Network-level L2 host list: read, validate, persist.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Privacy\SiteBlocklist;

use WenPai\ChinaYes\Config\Repository as ConfigRepository;
use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Privacy\DataResidency\Ruleset;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes modules.site_blocklist. Rejects L0 hosts on save.
 */
final class Repository {

	/**
	 * Maximum host rows.
	 *
	 * @since 4.0.0
	 */
	public const MAX_HOSTS = 20;

	/**
	 * Host name pattern from config-schema.md (no scheme, path, port, wildcard).
	 *
	 * @since 4.0.0
	 */
	public const HOST_PATTERN = '/^[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?(\\.[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*$/';

	/**
	 * Settings access.
	 *
	 * @var ConfigRepository
	 */
	private ConfigRepository $config;

	/**
	 * L0 table.
	 *
	 * @var Ruleset
	 */
	private Ruleset $ruleset;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param ConfigRepository $config  Settings.
	 * @param Ruleset|null     $ruleset Protected hosts. Null loads the shipped baseline.
	 */
	public function __construct( ConfigRepository $config, $ruleset = null ) {
		$this->config  = $config;
		$this->ruleset = $ruleset instanceof Ruleset ? $ruleset : new Ruleset();
	}

	/**
	 * Current list: enabled + hosts. Empty hosts when unset.
	 *
	 * @since 4.0.0
	 *
	 * @return array{enabled: bool, hosts: list<array{host: string, match: string, note: string}>}
	 */
	public function get(): array {
		$raw = $this->config->get( 'modules.site_blocklist', array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		return $this->normalize_document( $raw );
	}

	/**
	 * Validate and persist. Rejects the whole document on L0 or schema failure.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $incoming PUT body.
	 * @return array{enabled: bool, hosts: list<array{host: string, match: string, note: string}>}|WP_Error
	 */
	public function save( $incoming ) {
		$checked = $this->validate( $incoming );
		if ( is_wp_error( $checked ) ) {
			return $checked;
		}

		$option = $this->is_multisite() ? Schema::NETWORK_SETTINGS : Schema::SETTINGS;
		$doc    = $this->load_option( $option );
		if ( ! isset( $doc['modules'] ) || ! is_array( $doc['modules'] ) ) {
			$doc['modules'] = array();
		}
		$doc['modules']['site_blocklist'] = $checked;

		if ( ! $this->config->save_option( $option, $doc ) ) {
			return $this->invalid_schema();
		}

		return $checked;
	}

	/**
	 * Schema + L0 checks. Does not write.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $incoming PUT body.
	 * @return array{enabled: bool, hosts: list<array{host: string, match: string, note: string}>}|WP_Error
	 */
	public function validate( $incoming ) {
		if ( ! is_array( $incoming ) || $this->is_list( $incoming ) ) {
			return $this->invalid_schema();
		}

		$enabled = array_key_exists( 'enabled', $incoming ) ? $incoming['enabled'] : true;
		if ( ! is_bool( $enabled ) ) {
			return $this->invalid_schema();
		}

		$hosts = array_key_exists( 'hosts', $incoming ) ? $incoming['hosts'] : array();
		if ( ! is_array( $hosts ) || ( array() !== $hosts && ! $this->is_list( $hosts ) ) ) {
			return $this->invalid_schema();
		}

		if ( count( $hosts ) > self::MAX_HOSTS ) {
			return $this->invalid_schema();
		}

		$clean = array();
		foreach ( $hosts as $row ) {
			$item = $this->validate_row( $row );
			if ( is_wp_error( $item ) ) {
				return $item;
			}
			$clean[] = $item;
		}

		return array(
			'enabled' => $enabled,
			'hosts'   => $clean,
		);
	}

	/**
	 * Runtime rows that are not L0. Already-stored protected rows are skipped.
	 *
	 * @since 4.0.0
	 *
	 * @return list<array{host: string, match: string, note: string}>
	 */
	public function runtime_hosts(): array {
		$out = array();
		foreach ( $this->get()['hosts'] as $row ) {
			if ( $this->ruleset->is_protected( $row['host'] ) ) {
				continue;
			}
			$out[] = $row;
		}

		return $out;
	}

	/**
	 * Whether $host matches one runtime (non-protected) row.
	 *
	 * @since 4.0.0
	 *
	 * @param string $host Request host.
	 */
	public function matches( string $host ): bool {
		$host = strtolower( $host );
		if ( '' === $host ) {
			return false;
		}

		foreach ( $this->runtime_hosts() as $row ) {
			if ( $this->host_matches( $host, $row ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Loaded ruleset.
	 *
	 * @since 4.0.0
	 */
	public function ruleset(): Ruleset {
		return $this->ruleset;
	}

	/**
	 * One host row, or WP_Error.
	 *
	 * @param mixed $row Raw row.
	 * @return array{host: string, match: string, note: string}|WP_Error
	 */
	private function validate_row( $row ) {
		if ( ! is_array( $row ) || $this->is_list( $row ) ) {
			return $this->invalid_schema();
		}

		$host = isset( $row['host'] ) && is_string( $row['host'] ) ? strtolower( $row['host'] ) : '';
		if ( '' === $host || 1 !== preg_match( self::HOST_PATTERN, $host ) ) {
			return $this->invalid_schema();
		}
		if ( false !== strpos( $host, '*' ) || false !== strpos( $host, '/' ) || false !== strpos( $host, ':' ) ) {
			return $this->invalid_schema();
		}

		$match = isset( $row['match'] ) && is_string( $row['match'] ) ? $row['match'] : '';
		if ( ! in_array( $match, array( 'exact', 'suffix' ), true ) ) {
			return $this->invalid_schema();
		}

		$note = '';
		if ( isset( $row['note'] ) ) {
			if ( ! is_string( $row['note'] ) || strlen( $row['note'] ) > 200 ) {
				return $this->invalid_schema();
			}
			$note = $row['note'];
		}

		if ( $this->ruleset->is_protected( $host ) ) {
			return $this->protected_host();
		}

		return array(
			'host'  => $host,
			'match' => $match,
			'note'  => $note,
		);
	}

	/**
	 * Fill defaults for a stored document.
	 *
	 * @param array<string, mixed> $raw Stored object.
	 * @return array{enabled: bool, hosts: list<array{host: string, match: string, note: string}>}
	 */
	private function normalize_document( array $raw ): array {
		$enabled = array_key_exists( 'enabled', $raw ) ? (bool) $raw['enabled'] : true;
		$hosts   = array();
		if ( isset( $raw['hosts'] ) && is_array( $raw['hosts'] ) ) {
			foreach ( $raw['hosts'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$host = isset( $row['host'] ) && is_string( $row['host'] ) ? strtolower( $row['host'] ) : '';
				if ( '' === $host ) {
					continue;
				}
				$match   = isset( $row['match'] ) && is_string( $row['match'] ) && 'suffix' === $row['match']
					? 'suffix'
					: 'exact';
				$note    = isset( $row['note'] ) && is_string( $row['note'] ) ? $row['note'] : '';
				$hosts[] = array(
					'host'  => $host,
					'match' => $match,
					'note'  => $note,
				);
			}
		}

		return array(
			'enabled' => $enabled,
			'hosts'   => $hosts,
		);
	}

	/**
	 * Exact / suffix match, same semantics as Ruleset.
	 *
	 * @param string               $host Request host (lowercase).
	 * @param array<string, mixed> $rule Row.
	 */
	private function host_matches( string $host, array $rule ): bool {
		$needle = isset( $rule['host'] ) && is_string( $rule['host'] ) ? strtolower( $rule['host'] ) : '';
		if ( '' === $needle ) {
			return false;
		}

		$match = isset( $rule['match'] ) && is_string( $rule['match'] ) ? $rule['match'] : 'exact';
		if ( 'suffix' === $match ) {
			return $host === $needle || substr( $host, -strlen( '.' . $needle ) ) === '.' . $needle;
		}

		return $host === $needle;
	}

	/**
	 * Load one option without merging site overrides.
	 *
	 * @param string $option Option name.
	 * @return array<string, mixed>
	 */
	private function load_option( string $option ): array {
		$raw = Schema::NETWORK_SETTINGS === $option
			? ( function_exists( 'get_site_option' ) ? get_site_option( $option, array() ) : array() )
			: ( function_exists( 'get_option' ) ? get_option( $option, array() ) : array() );

		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Schema 400.
	 *
	 * @return WP_Error
	 */
	private function invalid_schema(): WP_Error {
		return new WP_Error(
			'wpcy_invalid_schema',
			function_exists( '__' )
				? __( '暂时无法保存设置，请检查填写内容后重试。', 'wp-china-yes' )
				: '暂时无法保存设置，请检查填写内容后重试。',
			array(
				'status' => 400,
			)
		);
	}

	/**
	 * Protected-host 400.
	 *
	 * @return WP_Error
	 */
	private function protected_host(): WP_Error {
		return new WP_Error(
			'wpcy_blocklist_protected_host',
			function_exists( '__' )
				? __( '文派服务不可拦截', 'wp-china-yes' )
				: '文派服务不可拦截',
			array(
				'status' => 400,
			)
		);
	}

	/**
	 * WordPress list check.
	 *
	 * @param array<int|string, mixed> $value Candidate.
	 */
	private function is_list( array $value ): bool {
		if ( array() === $value ) {
			return false;
		}

		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	/**
	 * Multisite flag.
	 */
	private function is_multisite(): bool {
		return function_exists( 'is_multisite' ) && is_multisite();
	}
}
