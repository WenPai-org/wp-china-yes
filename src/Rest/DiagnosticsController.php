<?php
/**
 * GET /diagnostics and POST /diagnostics/run
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Rest;

use WenPai\ChinaYes\Diagnostics\Checker;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns Checker snapshots as-is. Does not invent result fields.
 */
final class DiagnosticsController {

	/**
	 * Probe runner from M1-06.
	 *
	 * @var Checker
	 */
	private Checker $checker;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param Checker $checker Probe runner.
	 */
	public function __construct( Checker $checker ) {
		$this->checker = $checker;
	}

	/**
	 * Last stored results. Empty targets when none.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function get_item( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return RestError::ok( $this->checker->snapshot() );
	}

	/**
	 * Run probes and return the same snapshot shape.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function run( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		$this->checker->run();
		return RestError::ok( $this->checker->snapshot() );
	}
}
