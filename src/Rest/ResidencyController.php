<?php
/**
 * GET /residency/ruleset and GET /residency/log
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Rest;

use WenPai\ChinaYes\Privacy\DataResidency\DataResidencyModule;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only host table and B-tier log. Does not return request bodies or query strings.
 */
final class ResidencyController {

	/**
	 * Default log page size.
	 *
	 * @since 4.0.0
	 */
	public const PER_PAGE_DEFAULT = 20;

	/**
	 * Maximum log page size.
	 *
	 * @since 4.0.0
	 */
	public const PER_PAGE_MAX = 100;

	/**
	 * Ruleset and B-tier log.
	 *
	 * @var DataResidencyModule
	 */
	private DataResidencyModule $module;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param DataResidencyModule|null $module Host table. Null constructs the default.
	 */
	public function __construct( $module = null ) {
		$this->module = $module instanceof DataResidencyModule ? $module : new DataResidencyModule();
	}

	/**
	 * Current ruleset: version, issued_at, tiers. No signature.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function get_ruleset( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return RestError::ok( $this->module->ruleset()->to_rest() );
	}

	/**
	 * B-tier log rows: host, data_class, count, last_seen. Paginates page/per_page.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function get_log( WP_REST_Request $request ): WP_REST_Response {
		$page     = $this->page( $request );
		$per_page = $this->per_page( $request );
		$items    = $this->log_items();
		$offset   = ( $page - 1 ) * $per_page;

		return RestError::ok(
			array(
				'items' => array_slice( $items, $offset, $per_page ),
			)
		);
	}

	/**
	 * Four-field rows, newest last_seen first. Never includes URL or body.
	 *
	 * @return list<array{host: string, data_class: string, count: int, last_seen: string}>
	 */
	private function log_items(): array {
		$items = array_values( $this->module->log() );

		usort(
			$items,
			static function ( array $a, array $b ): int {
				$by_seen = strcmp( $b['last_seen'], $a['last_seen'] );
				if ( 0 !== $by_seen ) {
					return $by_seen;
				}
				return strcmp( $a['host'], $b['host'] );
			}
		);

		return $items;
	}

	/**
	 * Page number, default 1.
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	private function page( WP_REST_Request $request ): int {
		$page = (int) $request->get_param( 'page' );
		return $page < 1 ? 1 : $page;
	}

	/**
	 * Page size, default 20, cap 100.
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	private function per_page( WP_REST_Request $request ): int {
		$per_page = (int) $request->get_param( 'per_page' );
		if ( $per_page < 1 ) {
			return self::PER_PAGE_DEFAULT;
		}
		if ( $per_page > self::PER_PAGE_MAX ) {
			return self::PER_PAGE_MAX;
		}

		return $per_page;
	}
}
