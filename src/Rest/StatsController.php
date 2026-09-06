<?php
/**
 * GET /wpcy/v1/stats
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Rest;

use WenPai\ChinaYes\Migration\Runner;
use WenPai\ChinaYes\Stats\Counters;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Local counter series. Does not write except first installed_at.
 */
final class StatsController {

	/**
	 * Option written on first activate / first GET.
	 *
	 * @since 4.0.0
	 */
	public const INSTALLED_AT_OPTION = 'wpcy_installed_at';

	/**
	 * Counters service.
	 *
	 * @var Counters
	 */
	private Counters $counters;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param Counters $counters Daily counters.
	 */
	public function __construct( Counters $counters ) {
		$this->counters = $counters;
	}

	/**
	 * Series + totals for `days` (default 7, 1–30).
	 *
	 * @since 4.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( WP_REST_Request $request ) {
		$days = $this->parse_days( $request->get_param( 'days' ) );
		if ( null === $days ) {
			return RestError::invalid_schema();
		}

		$snap = $this->counters->snapshot( $days );

		return RestError::ok(
			array(
				'installed_at' => $this->installed_at(),
				'days'         => $snap['days'],
				'from'         => $snap['from'],
				'to'           => $snap['to'],
				'series'       => $snap['series'],
				'totals'       => $snap['totals'],
			)
		);
	}

	/**
	 * Resolve installed_at: option → migration report → now (and write).
	 *
	 * @since 4.0.0
	 */
	public function installed_at(): string {
		$stored = function_exists( 'get_option' ) ? get_option( self::INSTALLED_AT_OPTION, '' ) : '';
		if ( is_string( $stored ) && '' !== $stored ) {
			return $stored;
		}

		$migrated = $this->migrated_at();
		if ( '' !== $migrated ) {
			$this->write_installed_at( $migrated );
			return $migrated;
		}

		$now = RestError::now();
		$this->write_installed_at( $now );
		return $now;
	}

	/**
	 * Integer days 1–30, or null when illegal. Missing / empty → 7.
	 *
	 * @param mixed $raw Query value.
	 * @return int|null
	 */
	private function parse_days( $raw ) {
		if ( null === $raw || '' === $raw ) {
			return 7;
		}
		if ( is_int( $raw ) ) {
			$days = $raw;
		} elseif ( is_string( $raw ) && 1 === preg_match( '/^[0-9]+$/', $raw ) ) {
			$days = (int) $raw;
		} else {
			return null;
		}
		if ( $days < 1 || $days > 30 ) {
			return null;
		}
		return $days;
	}

	/**
	 * Migrated_at from the last execute() report.
	 *
	 * Same option scope as Runner::stored_report(): site_option on
	 * multisite, get_option otherwise.
	 */
	private function migrated_at(): string {
		$raw = $this->stored_migration_report();
		$at  = $raw['migrated_at'] ?? '';
		return is_string( $at ) ? $at : '';
	}

	/**
	 * Last persisted migration report, or empty.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	private function stored_migration_report(): array {
		$multisite = function_exists( 'is_multisite' ) && is_multisite();
		if ( $multisite ) {
			$raw = function_exists( 'get_site_option' ) ? get_site_option( Runner::REPORT_OPTION, array() ) : array();
		} else {
			$raw = function_exists( 'get_option' ) ? get_option( Runner::REPORT_OPTION, array() ) : array();
		}

		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Persist installed_at. First GET is the only GET that writes.
	 *
	 * @param string $value UTC ISO 8601.
	 */
	private function write_installed_at( string $value ): void {
		if ( function_exists( 'update_option' ) ) {
			update_option( self::INSTALLED_AT_OPTION, $value, false );
		}
	}
}
