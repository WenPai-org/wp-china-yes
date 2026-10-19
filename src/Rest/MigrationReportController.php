<?php
/**
 * GET /migration/report
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Rest;

use WenPai\ChinaYes\Migration\Runner;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Last execute() report. No history returns { status: none }.
 */
final class MigrationReportController {

	/**
	 * Migration runner.
	 *
	 * @var Runner
	 */
	private Runner $runner;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param Runner|null $runner Runner. Null constructs the default.
	 */
	public function __construct( $runner = null ) {
		$this->runner = $runner instanceof Runner ? $runner : new Runner();
	}

	/**
	 * Report::to_array() plus migrated_at, source_version, ignored. Or { status: none }.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function get_item( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		$stored = $this->runner->stored_report();
		if ( array() === $stored ) {
			return RestError::ok( array( 'status' => 'none' ) );
		}

		return RestError::ok( $stored );
	}
}
