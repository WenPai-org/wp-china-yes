<?php
/**
 * Entitlement degrade hooks for other modules.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Services\Entitlements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query cached entitlement status. Does not compute quota.
 *
 * Names statusFor / shouldUseUpstream are frozen by task M2-03.
 */
final class Degrade {

	/**
	 * Cached list reader.
	 *
	 * @var EntitlementsModule
	 */
	private EntitlementsModule $module;

	/**
	 * Constructor. Does not register hooks.
	 *
	 * @since 4.0.0
	 *
	 * @param EntitlementsModule $module List + cache.
	 */
	public function __construct( EntitlementsModule $module ) {
		$this->module = $module;
	}

	/**
	 * Entitlement status for a service id, or empty when none / baseline.
	 *
	 * @since 4.0.0
	 *
	 * @param string $service Service id such as motusnap.
	 */
	public function statusFor( string $service ): string { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- task M2-03 frozen name.
		$needle = self::sanitize_service( $service );
		if ( '' === $needle ) {
			return '';
		}

		foreach ( $this->module->items() as $row ) {
			$id = isset( $row['service'] ) && is_string( $row['service'] ) ? $row['service'] : '';
			if ( $needle !== $id ) {
				continue;
			}
			$status = isset( $row['status'] ) && is_string( $row['status'] ) ? $row['status'] : '';
			if ( in_array( $status, array( 'active', 'exhausted', 'expired' ), true ) ) {
				return $status;
			}
		}

		return '';
	}

	/**
	 * Whether the named service should fall back to the original upstream.
	 *
	 * Active returns false. Exhausted, expired, missing, and baseline return true.
	 *
	 * @since 4.0.0
	 *
	 * @param string $service Service id such as motusnap.
	 */
	public function shouldUseUpstream( string $service ): bool { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- task M2-03 frozen name.
		return 'active' !== $this->statusFor( $service );
	}

	/**
	 * Commercial handoff URL. UI label is 获取 (M2-05). Shown when expired.
	 *
	 * @since 4.0.0
	 *
	 * @param string $service Service id such as motusnap.
	 */
	public function acquire_url( string $service ): string {
		$slug = self::sanitize_service( $service );
		if ( '' === $slug ) {
			return '';
		}

		return 'https://wpcy.com/go/' . $slug;
	}

	/**
	 * Restrict service ids used in status lookup and /go/ paths.
	 *
	 * @param string $service Raw service id.
	 */
	private static function sanitize_service( string $service ): string {
		if ( ! preg_match( '/^[a-z0-9_-]{1,64}$/', $service ) ) {
			return '';
		}

		return $service;
	}
}
