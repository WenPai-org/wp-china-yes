<?php
/**
 * Read-only access to the 3.x option `wp_china_yes`.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Migration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single-site option or network site_option. Never writes.
 */
final class LegacyReader {

	public const OPTION = 'wp_china_yes';

	/**
	 * Raw 3.x settings array. Empty when the option is missing or not an array.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function read(): array {
		$raw = $this->is_multisite()
			? get_site_option( self::OPTION, array() )
			: get_option( self::OPTION, array() );

		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Whether the 3.x option currently exists (even if empty array).
	 *
	 * @since 4.0.0
	 */
	public function exists(): bool {
		if ( $this->is_multisite() ) {
			$raw = get_site_option( self::OPTION, false );
		} else {
			$raw = get_option( self::OPTION, false );
		}

		return false !== $raw;
	}

	/**
	 * WordPress multisite flag.
	 *
	 * @since 4.0.0
	 */
	public function is_multisite(): bool {
		return function_exists( 'is_multisite' ) && is_multisite();
	}
}
