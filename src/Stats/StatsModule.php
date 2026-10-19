<?php
/**
 * Stats kernel module: increment action, event action, shutdown flush.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Stats;

use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Core\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module id `stats`. Runs in every request scene.
 */
final class StatsModule implements Module {

	/**
	 * Action connectivity modules fire to increment a counter.
	 *
	 * @since 4.0.0
	 */
	public const INCREMENT_ACTION = 'wpcy_stats_increment';

	/**
	 * Action other modules fire to append an event.
	 *
	 * @since 4.0.0
	 */
	public const RECORD_ACTION = 'wpcy_events_record';

	/**
	 * Counters service.
	 *
	 * @var Counters
	 */
	private Counters $counters;

	/**
	 * Events service.
	 *
	 * @var Events
	 */
	private Events $events;

	/**
	 * Constructor. Does not register hooks.
	 *
	 * @since 4.0.0
	 *
	 * @param Counters $counters Daily counters.
	 * @param Events   $events   Event log.
	 */
	public function __construct( Counters $counters, Events $events ) {
		$this->counters = $counters;
		$this->events   = $events;
	}

	/**
	 * Module id.
	 *
	 * @since 4.0.0
	 */
	public function id(): string {
		return 'stats';
	}

	/**
	 * Every scene: counters and events are per-request memory plus shutdown.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function contexts(): array {
		return Environment::CONTEXTS;
	}

	/**
	 * No module graph edges.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function dependencies(): array {
		return array();
	}

	/**
	 * Listen for increment / record and flush once on shutdown.
	 *
	 * @since 4.0.0
	 */
	public function register(): void {
		add_action( self::INCREMENT_ACTION, array( $this, 'on_increment' ), 10, 2 );
		add_action( self::RECORD_ACTION, array( $this, 'on_record' ), 10, 2 );
		add_action( 'shutdown', array( $this, 'flush' ), 20 );
	}

	/**
	 * Forward wpcy_stats_increment to Counters. No I/O.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $counter Counter name.
	 * @param mixed $n       Delta.
	 */
	public function on_increment( $counter, $n = 1 ): void {
		if ( ! is_string( $counter ) || '' === $counter ) {
			return;
		}
		$this->counters->increment( $counter, is_int( $n ) ? $n : (int) $n );
	}

	/**
	 * Forward wpcy_events_record to Events. No I/O.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $type Event type.
	 * @param mixed $vars Template vars.
	 */
	public function on_record( $type, $vars = array() ): void {
		if ( ! is_string( $type ) || '' === $type ) {
			return;
		}
		$this->events->record( $type, is_array( $vars ) ? $vars : array() );
	}

	/**
	 * One option write each when dirty.
	 *
	 * @since 4.0.0
	 */
	public function flush(): void {
		$this->counters->flush();
		$this->events->flush();
	}

	/**
	 * Counters instance (REST / tests).
	 *
	 * @since 4.0.0
	 */
	public function counters(): Counters {
		return $this->counters;
	}

	/**
	 * Events instance (REST / tests).
	 *
	 * @since 4.0.0
	 */
	public function events(): Events {
		return $this->events;
	}
}
