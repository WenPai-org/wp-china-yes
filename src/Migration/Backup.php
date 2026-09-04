<?php
/**
 * Persist `wpcy_migration_backup` (no credentials).
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Migration;

use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Config\Validator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hash + ignored-field record used to detect post-migration edits to 3.x.
 */
final class Backup {

	/**
	 * Schema walker.
	 *
	 * @var Validator
	 */
	private Validator $validator;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param Validator|null $validator Schema walker.
	 */
	public function __construct( $validator = null ) {
		$this->validator = $validator instanceof Validator ? $validator : new Validator();
	}

	/**
	 * SHA-256 of a canonical JSON encoding of the 3.x option.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $legacy Raw `wp_china_yes`.
	 */
	public static function hash( array $legacy ): string {
		$copy = $legacy;
		self::ksort_recursive( $copy );
		return hash( 'sha256', self::encode( $copy ) );
	}

	/**
	 * Write the backup option. Network writes use site_option.
	 *
	 * @since 4.0.0
	 *
	 * @param string             $from_version    3.x plugin version.
	 * @param string             $legacy_hash     SHA-256 of `wp_china_yes`.
	 * @param array<int, string> $ignored_fields  Unmapped 3.x keys.
	 */
	public function write( string $from_version, string $legacy_hash, array $ignored_fields ): bool {
		$document = $this->validator->sanitize(
			array(
				'schema_version' => Schema::VERSION,
				'from_version'   => $from_version,
				'migrated_at'    => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'legacy_hash'    => $legacy_hash,
				'ignored_fields' => array_values( $ignored_fields ),
			),
			Schema::MIGRATION_BACKUP
		);

		if ( $this->is_multisite() ) {
			return (bool) update_site_option( Schema::MIGRATION_BACKUP, $document );
		}

		return (bool) update_option( Schema::MIGRATION_BACKUP, $document, false );
	}

	/**
	 * Stored backup, or empty array when missing.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function read(): array {
		$raw = $this->is_multisite()
			? get_site_option( Schema::MIGRATION_BACKUP, array() )
			: get_option( Schema::MIGRATION_BACKUP, array() );

		if ( ! is_array( $raw ) || array() === $raw ) {
			return array();
		}

		return $this->validator->sanitize( $raw, Schema::MIGRATION_BACKUP );
	}

	/**
	 * Drop the backup option. Does not touch `wp_china_yes`.
	 *
	 * @since 4.0.0
	 */
	public function delete(): bool {
		if ( $this->is_multisite() ) {
			return function_exists( 'delete_site_option' )
				? (bool) delete_site_option( Schema::MIGRATION_BACKUP )
				: true;
		}

		return function_exists( 'delete_option' )
			? (bool) delete_option( Schema::MIGRATION_BACKUP )
			: true;
	}

	/**
	 * WordPress multisite flag.
	 */
	private function is_multisite(): bool {
		return function_exists( 'is_multisite' ) && is_multisite();
	}

	/**
	 * JSON encode without leaking credentials into logs.
	 *
	 * @param mixed $value Tree.
	 * @return string
	 */
	private static function encode( $value ): string {
		if ( function_exists( 'wp_json_encode' ) ) {
			$json = wp_json_encode( $value );
			return is_string( $json ) ? $json : '';
		}

		$json = json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit bootstrap has no WordPress.
		return is_string( $json ) ? $json : '';
	}

	/**
	 * Sort object keys so the hash is stable across process runs.
	 *
	 * @param mixed $value Tree.
	 */
	private static function ksort_recursive( &$value ): void {
		if ( ! is_array( $value ) ) {
			return;
		}
		foreach ( $value as &$child ) {
			self::ksort_recursive( $child );
		}
		unset( $child );
		if ( array() !== $value && array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
			ksort( $value );
		}
	}
}
