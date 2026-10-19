<?php
/**
 * Apps EntitlementsClient backed by Services\Entitlements (cached Client).
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Apps;

use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Core\Logger;
use WenPai\ChinaYes\Services\Entitlements\EntitlementsModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Looks up a row by entitlement id. Quota numbers come from the server copy.
 */
final class CachedEntitlements implements EntitlementsClient {

	/**
	 * Cached entitlements module.
	 *
	 * @var EntitlementsModule
	 */
	private EntitlementsModule $module;

	/**
	 * Constructor. Does not send HTTP.
	 *
	 * @since 4.0.0
	 *
	 * @param EntitlementsModule|null $module     Cache. Null builds the default.
	 * @param Repository|null         $repository Identity when $module is omitted.
	 * @param Logger|null             $logger     Failure sink.
	 */
	public function __construct( $module = null, $repository = null, $logger = null ) {
		if ( $module instanceof EntitlementsModule ) {
			$this->module = $module;
			return;
		}

		$repo         = $repository instanceof Repository ? $repository : new Repository( $logger );
		$this->module = new EntitlementsModule( $repo, $logger );
	}

	/**
	 * Entitlement row for $entitlement_id, or null when none.
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

		foreach ( $this->module->items() as $row ) {
			$id = isset( $row['id'] ) && is_string( $row['id'] ) ? $row['id'] : '';
			if ( $id !== $entitlement_id ) {
				continue;
			}

			$status = isset( $row['status'] ) && is_string( $row['status'] ) ? $row['status'] : '';
			$quota  = isset( $row['quota'] ) && is_array( $row['quota'] ) ? $row['quota'] : array();

			return array(
				'status' => $status,
				'quota'  => array(
					'limit'     => $quota['limit'] ?? null,
					'used'      => $quota['used'] ?? null,
					'period'    => $quota['period'] ?? null,
					'resets_at' => $quota['resets_at'] ?? null,
				),
			);
		}

		return null;
	}
}
