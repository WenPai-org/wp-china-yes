<?php
/**
 * GET /events: per_page cap and type filter.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Stats;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Rest\EventsController;
use WenPai\ChinaYes\Stats\Events;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WenPai\ChinaYes\Tests\Unit\Connectivity\MapConfig;
use WenPai\ChinaYes\Tests\Unit\Rest\RestStore;
use WP_REST_Request;

require_once __DIR__ . '/wp-stats-stubs.php';

/**
 * Events REST envelope.
 */
class EventsControllerTest extends TestCase {

	/**
	 * Sequential ULID.
	 *
	 * @var int
	 */
	private $n = 0;

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		OptionStore::reset();
		RestStore::reset();
		$this->n = 0;
	}

	/**
	 * Per_page above 50 is capped.
	 */
	public function test_per_page_caps_at_fifty() {
		$events = $this->events();
		for ( $i = 0; $i < 60; $i++ ) {
			$events->record( 'recovery_exited' );
		}
		$controller      = new EventsController( $events );
		$request         = new WP_REST_Request();
		$request->params = array( 'per_page' => 99 );
		$data            = $controller->get_items( $request )->get_data();

		$this->assertCount( 50, $data['events'] );
	}

	/**
	 * Type filters the list.
	 */
	public function test_type_filter() {
		$events = $this->events();
		$events->record( 'recovery_entered' );
		$events->record( 'recovery_exited' );
		$events->record( 'recovery_entered' );

		$controller      = new EventsController( $events );
		$request         = new WP_REST_Request();
		$request->params = array( 'type' => 'recovery_entered' );
		$data            = $controller->get_items( $request )->get_data();

		$this->assertCount( 2, $data['events'] );
		foreach ( $data['events'] as $row ) {
			$this->assertSame( 'recovery_entered', $row['type'] );
			$this->assertArrayHasKey( 'title', $row );
			$this->assertArrayHasKey( 'detail', $row );
		}
	}

	/**
	 * Default per_page is 20.
	 */
	public function test_default_per_page_is_twenty() {
		$events = $this->events();
		for ( $i = 0; $i < 25; $i++ ) {
			$events->record( 'recovery_exited' );
		}
		$data = ( new EventsController( $events ) )->get_items( new WP_REST_Request() )->get_data();
		$this->assertCount( 20, $data['events'] );
	}

	/**
	 * Events with frozen clock.
	 */
	private function events(): Events {
		return new Events(
			new MapConfig( array() ),
			static function () {
				return '2026-09-06T06:02:11Z';
			},
			function () {
				++$this->n;
				return sprintf( '01TEST%020d', $this->n );
			}
		);
	}
}
