<?php
/**
 * In-place schema_version step functions. Repository calls these on read.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure upgrades. Does not read or write options.
 */
final class SchemaMigrator {

	/**
	 * Upgrade a v1 settings / network / overrides document to v2.
	 *
	 * Idempotent: documents with schema_version >= 2 are returned unchanged.
	 * Overlays ($fill_missing = false) only convert present keys; missing
	 * profile / admin_assets / heartbeat stay omitted.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $document     Raw stored document.
	 * @param bool                 $fill_missing Fill domestic defaults for absent keys.
	 * @return array<string, mixed>
	 */
	public static function upgrade_1_to_2( array $document, bool $fill_missing = true ): array {
		$version = isset( $document['schema_version'] ) ? (int) $document['schema_version'] : 0;
		if ( $version >= 2 ) {
			return $document;
		}

		if ( $fill_missing && ! isset( $document['profile'] ) ) {
			$document['profile'] = 'domestic';
		}

		if ( isset( $document['connectivity'] ) && is_array( $document['connectivity'] ) ) {
			$document['connectivity'] = self::upgrade_connectivity( $document['connectivity'], $fill_missing );
		} elseif ( $fill_missing ) {
			$document['connectivity'] = Defaults::settings()['connectivity'];
		}

		if ( $fill_missing && ! isset( $document['admin_assets'] ) ) {
			$document['admin_assets'] = 'off';
		}

		if ( isset( $document['diagnostics'] ) && is_array( $document['diagnostics'] ) ) {
			if ( ! array_key_exists( 'client_probe_url', $document['diagnostics'] ) ) {
				$document['diagnostics']['client_probe_url'] = '';
			}
		} elseif ( $fill_missing ) {
			$document['diagnostics'] = Defaults::settings()['diagnostics'];
		}

		$document['schema_version'] = 2;

		return $document;
	}

	/**
	 * Convert v1 connectivity (array public_assets, string avatar) to v2 objects.
	 *
	 * @param array<string, mixed> $connectivity Connectivity object.
	 * @param bool                 $fill_missing Fill heartbeat / dashboard_feeds.
	 * @return array<string, mixed>
	 */
	private static function upgrade_connectivity( array $connectivity, bool $fill_missing ): array {
		if ( isset( $connectivity['public_assets'] ) && self::is_list( $connectivity['public_assets'] ) ) {
			$connectivity['public_assets'] = array(
				'items' => $connectivity['public_assets'],
				'scope' => 'both',
			);
		}

		if ( isset( $connectivity['avatar'] ) && is_string( $connectivity['avatar'] ) ) {
			$mode                   = $connectivity['avatar'];
			$connectivity['avatar'] = array(
				'admin'    => $mode,
				'frontend' => $mode,
			);
		}

		if ( $fill_missing && ! isset( $connectivity['heartbeat'] ) ) {
			$connectivity['heartbeat'] = 'off';
		}

		if ( $fill_missing && ! isset( $connectivity['dashboard_feeds'] ) ) {
			$connectivity['dashboard_feeds'] = 'allow';
		}

		return $connectivity;
	}

	/**
	 * Whether $value is a JSON array (0-based list).
	 *
	 * @param mixed $value Candidate.
	 */
	private static function is_list( $value ): bool {
		if ( ! is_array( $value ) ) {
			return false;
		}
		if ( array() === $value ) {
			return true;
		}

		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
