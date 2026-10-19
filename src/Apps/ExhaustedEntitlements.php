<?php
/**
 * Default entitlements client while M2-03 is not merged: every id is exhausted.
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
 * Parallel group E has no Entitlements module. Quota numbers are not hardcoded.
 */
final class ExhaustedEntitlements implements EntitlementsClient {

	/**
	 * Exhausted row for a non-empty id; null when the manifest has no entitlement.
	 *
	 * @since 4.0.0
	 *
	 * @param string $entitlement_id Entitlement id from the manifest.
	 * @return array<string, mixed>|null
	 */
	public function get( string $entitlement_id ) {
		if ( '' === $entitlement_id ) {
			return null;
		}

		return array(
			'status' => 'exhausted',
			'quota'  => array(
				'limit'     => null,
				'used'      => null,
				'period'    => null,
				'resets_at' => null,
			),
		);
	}
}
