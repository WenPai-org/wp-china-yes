<?php
/**
 * Verified app manifest list.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Apps;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Already-verified manifests from Index. Unknown ids are absent.
 */
final class Registry {

	/**
	 * Iframe sandbox tokens. Must not include allow-same-origin.
	 *
	 * @since 4.0.0
	 */
	public const IFRAME_SANDBOX = 'allow-scripts allow-forms';

	/**
	 * Bridge-only error code, reserved for M2-05. REST does not emit it.
	 *
	 * @since 4.0.0
	 */
	public const ERR_ORIGIN_MISMATCH = 'wpcy_apps_origin_mismatch';

	/**
	 * Known permission tokens from spec §1.2.
	 *
	 * @since 4.0.0
	 * @var list<string>
	 */
	public const PERMISSIONS = array(
		'site:read',
		'data:read',
		'data:write',
		'data:delete',
		'entitlement:read',
		'go:open',
	);

	/**
	 * Signed index.
	 *
	 * @var Index
	 */
	private Index $index;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param Index $index Signed index.
	 */
	public function __construct( Index $index ) {
		$this->index = $index;
	}

	/**
	 * Verified manifests.
	 *
	 * @since 4.0.0
	 *
	 * @return list<array<string, mixed>>
	 */
	public function all(): array {
		return $this->index->apps();
	}

	/**
	 * One verified manifest, or null.
	 *
	 * @since 4.0.0
	 *
	 * @param string $id App id.
	 * @return array<string, mixed>|null
	 */
	public function get( string $id ) {
		if ( '' === $id ) {
			return null;
		}
		foreach ( $this->all() as $manifest ) {
			if ( isset( $manifest['id'] ) && $id === $manifest['id'] ) {
				return $manifest;
			}
		}
		return null;
	}

	/**
	 * Whether $id is in the verified list.
	 *
	 * @since 4.0.0
	 *
	 * @param string $id App id.
	 */
	public function has( string $id ): bool {
		return is_array( $this->get( $id ) );
	}

	/**
	 * Whether the verified manifest lists $permission.
	 *
	 * @since 4.0.0
	 *
	 * @param string $id         App id.
	 * @param string $permission Permission token.
	 */
	public function allows( string $id, string $permission ): bool {
		$manifest = $this->get( $id );
		if ( ! is_array( $manifest ) ) {
			return false;
		}
		$perms = isset( $manifest['permissions'] ) && is_array( $manifest['permissions'] )
			? $manifest['permissions']
			: array();
		return in_array( $permission, $perms, true );
	}

	/**
	 * Iframe sandbox attribute value. Never includes allow-same-origin.
	 *
	 * @since 4.0.0
	 */
	public static function iframe_sandbox(): string {
		return self::IFRAME_SANDBOX;
	}
}
