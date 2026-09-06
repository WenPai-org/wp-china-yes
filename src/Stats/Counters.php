<?php
/**
 * Per-site daily counters. Memory in the request; one option write on shutdown.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Stats;

use WenPai\ChinaYes\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UTC-day buckets in `wpcy_stats` (autoload=false). Unknown names are ignored.
 */
final class Counters {

	/**
	 * Option key. Not a settings field.
	 *
	 * @since 4.0.0
	 */
	public const OPTION = 'wpcy_stats';

	/**
	 * Keep this many UTC days including today.
	 *
	 * @since 4.0.0
	 */
	public const RETAIN_DAYS = 31;

	/**
	 * Counter names from rest-api.md §/stats. outbound_blocked is named here
	 * only; HttpBlock is wired by M-BLOCK-1.
	 *
	 * @since 4.0.0
	 *
	 * @var list<string>
	 */
	public const NAMES = array(
		'mirror_downloads',
		'mirror_bytes_saved',
		'assets_rewrites_admin',
		'assets_rewrites_frontend',
		'avatar_rewrites_admin',
		'avatar_rewrites_frontend',
		'heartbeat_saved',
		'dashboard_feeds_blocked',
		'outbound_blocked',
		'mirror_fallbacks',
	);

	/**
	 * Optional config with get(). Used for recovery_mode.
	 *
	 * @var object|null
	 */
	private $config;

	/**
	 * Optional logger for unknown counter names.
	 *
	 * @var Logger|null
	 */
	private $logger;

	/**
	 * Clock: function(): string UTC ISO 8601 or Y-m-d.
	 *
	 * @var callable
	 */
	private $now;

	/**
	 * In-memory buckets keyed by YYYY-MM-DD then counter name.
	 *
	 * @var array<string, array<string, int>>
	 */
	private array $buckets = array();

	/**
	 * Whether option contents have been loaded.
	 *
	 * @var bool
	 */
	private bool $loaded = false;

	/**
	 * Whether increment() changed memory since load / last flush.
	 *
	 * @var bool
	 */
	private bool $dirty = false;

	/**
	 * Whether shutdown already flushed this request.
	 *
	 * @var bool
	 */
	private bool $flushed = false;

	/**
	 * Constructor. Does not read or write options.
	 *
	 * @since 4.0.0
	 *
	 * @param object|null   $config Config with get(), or null.
	 * @param Logger|null   $logger Warning sink for unknown names.
	 * @param callable|null $now    Clock returning UTC ISO 8601.
	 */
	public function __construct( $config = null, $logger = null, $now = null ) {
		$this->config = ( is_object( $config ) && method_exists( $config, 'get' ) ) ? $config : null;
		$this->logger = $logger instanceof Logger ? $logger : null;
		$this->now    = null !== $now ? $now : static function () {
			return gmdate( 'Y-m-d\TH:i:s\Z' );
		};
	}

	/**
	 * Add `$n` to `$counter` for today's UTC bucket. No I/O.
	 *
	 * Recovery mode is a no-op. Unknown names are ignored and logged.
	 *
	 * @since 4.0.0
	 *
	 * @param string $counter One of self::NAMES.
	 * @param int    $n       Delta; ignored when < 1.
	 */
	public function increment( string $counter, int $n = 1 ): void {
		if ( $n < 1 || $this->recovery_mode() ) {
			return;
		}

		if ( ! in_array( $counter, self::NAMES, true ) ) {
			if ( $this->logger instanceof Logger ) {
				$this->logger->log(
					'warning',
					'Unknown stats counter ignored.',
					array( 'counter' => $counter )
				);
			}
			return;
		}

		$this->hydrate();
		$day = $this->today();
		if ( ! isset( $this->buckets[ $day ] ) ) {
			$this->buckets[ $day ] = array();
		}
		$current                           = isset( $this->buckets[ $day ][ $counter ] ) ? (int) $this->buckets[ $day ][ $counter ] : 0;
		$this->buckets[ $day ][ $counter ] = $current + $n;
		$this->dirty                       = true;
		$this->flushed                     = false;
	}

	/**
	 * Daily series and totals for the last `$days` UTC days (inclusive of today).
	 *
	 * Missing buckets are 0. Dates ascend. Does not write.
	 *
	 * @since 4.0.0
	 *
	 * @param int $days 1–30.
	 * @return array{days: int, from: string, to: string, series: array<string, list<array{date: string, value: int}>>, totals: array<string, int>}
	 */
	public function snapshot( int $days ): array {
		$this->hydrate();
		$dates  = $this->date_range( $days );
		$series = array();
		$totals = array();
		foreach ( self::NAMES as $name ) {
			$points = array();
			$total  = 0;
			foreach ( $dates as $date ) {
				$value    = isset( $this->buckets[ $date ][ $name ] ) ? (int) $this->buckets[ $date ][ $name ] : 0;
				$points[] = array(
					'date'  => $date,
					'value' => $value,
				);
				$total   += $value;
			}
			$series[ $name ] = $points;
			$totals[ $name ] = $total;
		}

		return array(
			'days'   => $days,
			'from'   => $dates[0],
			'to'     => $dates[ count( $dates ) - 1 ],
			'series' => $series,
			'totals' => $totals,
		);
	}

	/**
	 * Persist buckets once. No-op when nothing changed. Drops buckets older
	 * than RETAIN_DAYS.
	 *
	 * @since 4.0.0
	 */
	public function flush(): void {
		if ( $this->flushed || ! $this->dirty ) {
			return;
		}
		$this->flushed = true;
		$this->hydrate();
		$this->prune();
		if ( ! function_exists( 'update_option' ) ) {
			$this->dirty = false;
			return;
		}
		update_option(
			self::OPTION,
			array( 'buckets' => $this->buckets ),
			false
		);
		$this->dirty = false;
	}

	/**
	 * In-memory buckets (tests).
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, array<string, int>>
	 */
	public function buckets(): array {
		$this->hydrate();
		return $this->buckets;
	}

	/**
	 * Load option into memory once.
	 */
	private function hydrate(): void {
		if ( $this->loaded ) {
			return;
		}
		$this->loaded = true;
		if ( ! function_exists( 'get_option' ) ) {
			return;
		}
		$raw = get_option( self::OPTION, array() );
		if ( ! is_array( $raw ) ) {
			return;
		}
		$buckets = isset( $raw['buckets'] ) && is_array( $raw['buckets'] ) ? $raw['buckets'] : array();
		$clean   = array();
		foreach ( $buckets as $day => $counts ) {
			if ( ! is_string( $day ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) || ! is_array( $counts ) ) {
				continue;
			}
			$row = array();
			foreach ( $counts as $name => $value ) {
				if ( is_string( $name ) && in_array( $name, self::NAMES, true ) ) {
					$row[ $name ] = (int) $value;
				}
			}
			$clean[ $day ] = $row;
		}
		$this->buckets = $clean;
	}

	/**
	 * Drop buckets older than today − (RETAIN_DAYS − 1).
	 */
	private function prune(): void {
		$cutoff = $this->shift_days( $this->today(), 1 - self::RETAIN_DAYS );
		foreach ( array_keys( $this->buckets ) as $day ) {
			if ( $day < $cutoff ) {
				unset( $this->buckets[ $day ] );
			}
		}
	}

	/**
	 * Last `$days` UTC dates, ascending, ending today.
	 *
	 * @param int $days Length.
	 * @return list<string>
	 */
	private function date_range( int $days ): array {
		$out  = array();
		$last = $this->today();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$out[] = $this->shift_days( $last, -$i );
		}
		return $out;
	}

	/**
	 * Today's UTC date.
	 */
	private function today(): string {
		$stamp = (string) ( $this->now )();
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2})/', $stamp, $match ) ) {
			return $match[1];
		}
		return gmdate( 'Y-m-d' );
	}

	/**
	 * Add `$delta` days to a Y-m-d string.
	 *
	 * @param string $day   UTC date.
	 * @param int    $delta Days (may be negative).
	 */
	private function shift_days( string $day, int $delta ): string {
		$dt = \DateTimeImmutable::createFromFormat( '!Y-m-d', $day, new \DateTimeZone( 'UTC' ) );
		if ( ! $dt instanceof \DateTimeImmutable ) {
			return $day;
		}
		return $dt->modify( sprintf( '%+d days', $delta ) )->format( 'Y-m-d' );
	}

	/**
	 * Whether recovery_mode is on.
	 */
	private function recovery_mode(): bool {
		if ( ! is_object( $this->config ) || ! method_exists( $this->config, 'get' ) ) {
			return false;
		}
		return (bool) $this->config->get( 'recovery_mode', false );
	}
}
