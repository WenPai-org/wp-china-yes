<?php
/**
 * Plugin::deactivate() clears the telemetry cron, network-wide on multisite.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Core\Plugin;
use WenPai\ChinaYes\Telemetry\TelemetryModule;

/**
 * Cron events are per site: a network deactivation must clear every site,
 * not only the main one. Separate processes keep these stubs isolated.
 */
class PluginDeactivateTest extends TestCase {

	/**
	 * Load stubs and set up the network.
	 *
	 * @param bool  $multisite Multisite flag.
	 * @param int[] $sites     Site ids.
	 * @return void
	 */
	private function network( bool $multisite, array $sites ): void {
		require_once __DIR__ . '/wp-deactivate-stubs.php';
		DeactivateStore::$multisite       = $multisite;
		DeactivateStore::$sites           = $sites;
		DeactivateStore::$blog            = 1;
		DeactivateStore::$stack           = array();
		DeactivateStore::$cleared         = array();
		DeactivateStore::$get_sites_calls = array();
		DeactivateStore::$throw_on_blog   = 0;
	}

	/**
	 * Blog ids whose telemetry cron was cleared.
	 *
	 * @return int[]
	 */
	private function cleared_blogs(): array {
		$blogs = array();
		foreach ( DeactivateStore::$cleared as $row ) {
			$this->assertSame( TelemetryModule::CRON_HOOK, $row[1] );
			$blogs[] = $row[0];
		}
		return $blogs;
	}

	/**
	 * Single site: one clear on the current site, no site query.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_single_site_clears_current_site() {
		$this->network( false, array( 1 ) );

		Plugin::deactivate( false );

		$this->assertSame( array( 1 ), $this->cleared_blogs() );
		$this->assertSame( array(), DeactivateStore::$get_sites_calls );
	}

	/**
	 * Network deactivation clears every site, paged by DEACTIVATE_SITES_BATCH,
	 * and returns to the original blog.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_network_deactivation_clears_every_site_in_batches() {
		$sites = range( 1, 250 );
		$this->network( true, $sites );

		Plugin::deactivate( true );

		$this->assertSame( $sites, $this->cleared_blogs() );
		$this->assertSame( 1, DeactivateStore::$blog );
		$this->assertSame( array(), DeactivateStore::$stack );

		$this->assertCount( 3, DeactivateStore::$get_sites_calls );
		$offsets = array();
		foreach ( DeactivateStore::$get_sites_calls as $args ) {
			$this->assertSame( 'ids', $args['fields'] );
			$this->assertSame( Plugin::DEACTIVATE_SITES_BATCH, $args['number'] );
			$this->assertSame( 1, $args['network_id'] );
			$offsets[] = $args['offset'];
		}
		$this->assertSame( array( 0, 100, 200 ), $offsets );
	}

	/**
	 * Exactly one full page: the follow-up page is empty and the loop stops.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_network_deactivation_full_page_stops_on_empty_page() {
		$sites = range( 1, Plugin::DEACTIVATE_SITES_BATCH );
		$this->network( true, $sites );

		Plugin::deactivate( true );

		$this->assertSame( $sites, $this->cleared_blogs() );
		$this->assertCount( 2, DeactivateStore::$get_sites_calls );
	}

	/**
	 * If wp_clear_scheduled_hook() throws on a site, restore_current_blog()
	 * still runs and the request is back on the original site.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_network_deactivation_restores_blog_when_clear_throws() {
		$this->network( true, array( 1, 2, 3 ) );
		DeactivateStore::$throw_on_blog = 2;

		$thrown = null;
		try {
			Plugin::deactivate( true );
		} catch ( \RuntimeException $e ) {
			$thrown = $e;
		}

		$this->assertInstanceOf( \RuntimeException::class, $thrown );
		$this->assertSame( 1, DeactivateStore::$blog );
		$this->assertSame( array(), DeactivateStore::$stack );
		$this->assertSame( array( 1 ), $this->cleared_blogs() );
	}

	/**
	 * Multisite, deactivated on one site only: that site alone is cleared.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_multisite_single_site_deactivation_clears_current_site_only() {
		$this->network( true, array( 1, 2, 3 ) );
		DeactivateStore::$blog = 2;

		Plugin::deactivate( false );

		$this->assertSame( array( 2 ), $this->cleared_blogs() );
		$this->assertSame( array(), DeactivateStore::$get_sites_calls );
	}
}
