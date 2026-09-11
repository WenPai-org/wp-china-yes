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
	 * profile / heartbeat stay omitted. Dropped `admin_assets` is ignored.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $document     Raw stored document.
	 * @param bool                 $fill_missing Fill domestic defaults for absent keys.
	 * @return array<string, mixed>
	 */
	public static function upgrade_1_to_2( array $document, bool $fill_missing = true ): array {
		$version = isset( $document['schema_version'] ) ? (int) $document['schema_version'] : 0;
		if ( $version < 2 ) {
			if ( $fill_missing && ! isset( $document['profile'] ) ) {
				$document['profile'] = 'domestic';
			}

			if ( isset( $document['connectivity'] ) && is_array( $document['connectivity'] ) ) {
				$document['connectivity'] = self::upgrade_connectivity( $document['connectivity'], $fill_missing );
			} elseif ( $fill_missing ) {
				$document['connectivity'] = Defaults::settings()['connectivity'];
			}

			if ( isset( $document['diagnostics'] ) && is_array( $document['diagnostics'] ) ) {
				if ( ! array_key_exists( 'client_probe_url', $document['diagnostics'] ) ) {
					$document['diagnostics']['client_probe_url'] = '';
				}
			} elseif ( $fill_missing ) {
				$document['diagnostics'] = Defaults::settings()['diagnostics'];
			}

			$document['schema_version'] = 2;
		}

		return self::collapse_legacy_keys( $document );
	}

	/**
	 * Collapse split avatar / drop retired admin_assets on any stored document.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $document Stored document.
	 * @return array<string, mixed>
	 */
	public static function collapse_legacy_keys( array $document ): array {
		if ( isset( $document['connectivity'] ) && is_array( $document['connectivity'] ) && array_key_exists( 'avatar', $document['connectivity'] ) ) {
			$document['connectivity']['avatar'] = self::collapse_avatar( $document['connectivity']['avatar'] );
		}
		unset( $document['admin_assets'] );

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

		if ( isset( $connectivity['avatar'] ) ) {
			$connectivity['avatar'] = self::collapse_avatar( $connectivity['avatar'] );
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
	 * Collapse v2 {admin,frontend} or a weavatar string into a single enum.
	 *
	 * Prefer a live Cravatar line over off when the two sides differed.
	 *
	 * @param mixed $avatar Stored avatar.
	 */
	private static function collapse_avatar( $avatar ): string {
		if ( is_string( $avatar ) ) {
			return 'weavatar' === $avatar ? 'cravatar_cn' : $avatar;
		}
		if ( ! is_array( $avatar ) ) {
			return 'cravatar_cn';
		}

		$admin    = isset( $avatar['admin'] ) && is_string( $avatar['admin'] ) ? $avatar['admin'] : '';
		$frontend = isset( $avatar['frontend'] ) && is_string( $avatar['frontend'] ) ? $avatar['frontend'] : '';
		foreach ( array( $admin, $frontend ) as $mode ) {
			if ( 'weavatar' === $mode ) {
				$mode = 'cravatar_cn';
			}
			if ( in_array( $mode, Schema::AVATAR, true ) && 'off' !== $mode ) {
				return $mode;
			}
		}
		if ( in_array( $admin, Schema::AVATAR, true ) ) {
			return $admin;
		}
		if ( in_array( $frontend, Schema::AVATAR, true ) ) {
			return $frontend;
		}

		return 'cravatar_cn';
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
