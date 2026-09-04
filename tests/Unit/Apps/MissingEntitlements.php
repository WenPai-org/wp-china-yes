<?php
/**
 * Empty entitlements stand-in: paid tools have no row.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Apps;

use WenPai\ChinaYes\Apps\EntitlementsClient;

/**
 * The get() method is always null.
 */
final class MissingEntitlements implements EntitlementsClient {

	/**
	 * No row.
	 *
	 * @since 4.0.0
	 *
	 * @param string $entitlement_id Id.
	 * @return null
	 */
	public function get( string $entitlement_id ) {
		unset( $entitlement_id );
		return null;
	}
}
