<?php
/**
 * Expired entitlements stand-in for REST write tests.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Apps;

use WenPai\ChinaYes\Apps\EntitlementsClient;

/**
 * Every non-empty entitlement id is expired.
 */
final class ExpiredEntitlements implements EntitlementsClient {

	/**
	 * Expired row for a non-empty id.
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
			'status' => 'expired',
			'quota'  => array(
				'limit'     => null,
				'used'      => null,
				'period'    => null,
				'resets_at' => null,
			),
		);
	}
}
