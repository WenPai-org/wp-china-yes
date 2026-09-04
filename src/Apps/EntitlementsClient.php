<?php
/**
 * Entitlements lookup used by the apps REST layer.
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
 * Injectable entitlements reader. M2-03 may replace the default exhausted client.
 */
interface EntitlementsClient {

	/**
	 * Entitlement row for $entitlement_id, or null when none.
	 *
	 * Shape: { status, quota: { limit, used, period, resets_at } }.
	 * status ∈ active | exhausted | expired.
	 *
	 * @since 4.0.0
	 *
	 * @param string $entitlement_id Entitlement id from the manifest.
	 * @return array<string, mixed>|null
	 */
	public function get( string $entitlement_id );
}
