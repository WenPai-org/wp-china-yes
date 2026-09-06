<?php
/**
 * GET / PUT wpcy/v1/site-blocklist
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Rest;

use WenPai\ChinaYes\Config\Repository as ConfigRepository;
use WenPai\ChinaYes\Privacy\DataResidency\Ruleset;
use WenPai\ChinaYes\Privacy\SiteBlocklist\Repository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Network-level L2 list. Permission is manage_network_options (or manage_options on single site).
 */
final class SiteBlocklistController {

	/**
	 * List store.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param Repository|null       $repository List store.
	 * @param ConfigRepository|null $config     Settings.
	 * @param Ruleset|null          $ruleset    L0 table.
	 */
	public function __construct( $repository = null, $config = null, $ruleset = null ) {
		if ( $repository instanceof Repository ) {
			$this->repository = $repository;
			return;
		}

		$config           = $config instanceof ConfigRepository ? $config : new ConfigRepository();
		$this->repository = new Repository( $config, $ruleset );
	}

	/**
	 * Current list.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function get_item( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return RestError::ok( $this->repository->get() );
	}

	/**
	 * Replace the list. Protected hosts → 400 wpcy_blocklist_protected_host.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( WP_REST_Request $request ) {
		$result = $this->repository->save( SettingsController::body( $request ) );
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			if ( ! is_array( $data ) ) {
				$data = array();
			}
			$data['status']     = isset( $data['status'] ) ? (int) $data['status'] : 400;
			$data['request_id'] = RestError::request_id();
			return RestError::make(
				(string) $result->get_error_code(),
				(string) $result->get_error_message(),
				$data['status']
			);
		}

		return RestError::ok( $result );
	}

	/**
	 * Read: network cap on multisite, manage_options on single site.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return true|WP_Error
	 */
	public static function permission_read( WP_REST_Request $request ) {
		unset( $request );
		if ( ! current_user_can( self::required_cap() ) ) {
			return RestError::forbidden();
		}

		return true;
	}

	/**
	 * Write: same cap plus REST nonce.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return true|WP_Error
	 */
	public static function permission_write( WP_REST_Request $request ) {
		if ( ! current_user_can( self::required_cap() ) ) {
			return RestError::forbidden();
		}
		if ( ! Permissions::nonce_ok( $request ) ) {
			return RestError::forbidden();
		}

		return true;
	}

	/**
	 * Capability: manage_network_options on multisite, manage_options on single site.
	 *
	 * @since 4.0.0
	 */
	public static function required_cap(): string {
		return function_exists( 'is_multisite' ) && is_multisite()
			? 'manage_network_options'
			: 'manage_options';
	}
}
