<?php
/**
 * Active entitlements stand-in for Apps REST write tests.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Apps;

use WenPai\ChinaYes\Apps\EntitlementsClient;

/**
 * Every id is active. Quota numbers are not hardcoded product limits.
 */
final class ActiveEntitlements implements EntitlementsClient {

	/**
	 * Active row.
	 *
	 * @since 4.0.0
	 *
	 * @param string $entitlement_id Id.
	 * @return array<string, mixed>
	 */
	public function get( string $entitlement_id ) {
		unset( $entitlement_id );
		return array(
			'status' => 'active',
			'quota'  => array(
				'limit'     => null,
				'used'      => null,
				'period'    => null,
				'resets_at' => null,
			),
		);
	}
}
