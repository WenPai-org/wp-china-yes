<?php
/**
 * GET /wpcy/v1/events
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Rest;

use WenPai\ChinaYes\Stats\Events;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Local event log. Newest first. Does not write.
 */
final class EventsController {

	/**
	 * Default page size from rest-api.md.
	 *
	 * @since 4.0.0
	 */
	public const PER_PAGE_DEFAULT = 20;

	/**
	 * Max page size from rest-api.md.
	 *
	 * @since 4.0.0
	 */
	public const PER_PAGE_MAX = 50;

	/**
	 * Events service.
	 *
	 * @var Events
	 */
	private Events $events;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param Events $events Event log.
	 */
	public function __construct( Events $events ) {
		$this->events = $events;
	}

	/**
	 * Envelope `{ events: [...] }`.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function get_items( WP_REST_Request $request ): WP_REST_Response {
		$per_page = $this->parse_per_page( $request->get_param( 'per_page' ) );
		$type     = $request->get_param( 'type' );
		$type     = is_string( $type ) && '' !== $type ? $type : null;

		return RestError::ok(
			array(
				'events' => $this->events->latest( $per_page, $type ),
			)
		);
	}

	/**
	 * Default 20, cap 50. Non-numeric falls back to default.
	 *
	 * @param mixed $raw Query value.
	 */
	private function parse_per_page( $raw ): int {
		if ( null === $raw || '' === $raw ) {
			return self::PER_PAGE_DEFAULT;
		}
		if ( is_int( $raw ) ) {
			$n = $raw;
		} elseif ( is_string( $raw ) && 1 === preg_match( '/^[0-9]+$/', $raw ) ) {
			$n = (int) $raw;
		} else {
			return self::PER_PAGE_DEFAULT;
		}
		if ( $n < 1 ) {
			return self::PER_PAGE_DEFAULT;
		}
		return min( $n, self::PER_PAGE_MAX );
	}
}
