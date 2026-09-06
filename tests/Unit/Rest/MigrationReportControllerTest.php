<?php
/**
 * GET /migration/report with and without history.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Rest;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Migration\LegacyReader;
use WenPai\ChinaYes\Migration\Runner;
use WenPai\ChinaYes\Rest\MigrationReportController;
use WenPai\ChinaYes\Rest\RestError;
use WenPai\ChinaYes\Rest\RestModule;
use WenPai\ChinaYes\Tests\Unit\Config\OptionStore;
use WP_REST_Request;

require_once __DIR__ . '/wp-rest-stubs.php';

/**
 * None vs stored report; route is on wpcy/v1.
 */
class MigrationReportControllerTest extends TestCase {

	/**
	 * Reset bags.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		RestStore::reset();
		OptionStore::reset();
		RestError::reset();
	}

	/**
	 * No execute() history returns { status: none }.
	 */
	public function test_no_history_returns_status_none() {
		$controller = new MigrationReportController( new Runner() );
		$response   = $controller->get_item( new WP_REST_Request() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'status' => 'none' ), $response->get_data() );
	}

	/**
	 * After execute(), GET returns to_array plus migrated_at, source_version, ignored.
	 */
	public function test_stored_report_after_execute() {
		OptionStore::$options[ LegacyReader::OPTION ] = array(
			'store'    => 'off',
			'cravatar' => 'cn',
			'version'  => '3.9.3',
		);

		$runner = new Runner();
		$report = $runner->execute();
		$body   = ( new MigrationReportController( $runner ) )->get_item( new WP_REST_Request() )->get_data();

		$this->assertArrayNotHasKey( 'status', $body );
		$this->assertSame( $report->kept(), $body['kept'] );
		$this->assertSame( $report->ignored(), $body['ignored'] );
		$this->assertSame( $report->ignored_reasons(), $body['ignored_reasons'] );
		$this->assertSame( $report->settings(), $body['settings'] );
		$this->assertArrayHasKey( 'migrated_at', $body );
		$this->assertSame( '3.9.3', $body['source_version'] );
		$this->assertSame( $report->to_array()['ignored'], $body['ignored'] );
		$this->assertArrayHasKey( Runner::REPORT_OPTION, OptionStore::$options );
		$this->assertSame( 'wpcy_migration_report', Runner::REPORT_OPTION );
	}

	/**
	 * Legacy option without version/plugin_version → source_version 3.x.
	 */
	public function test_source_version_defaults_to_3x() {
		OptionStore::$options[ LegacyReader::OPTION ] = array(
			'store'    => 'off',
			'cravatar' => 'cn',
		);

		$runner = new Runner();
		$runner->execute();
		$body = ( new MigrationReportController( $runner ) )->get_item( new WP_REST_Request() )->get_data();

		$this->assertSame( '3.x', $body['source_version'] );
	}

	/**
	 * REST index includes /migration/report.
	 */
	public function test_rest_index_includes_migration_report() {
		$module = new RestModule( new Repository() );
		$module->register_routes();

		$routes = array();
		foreach ( RestStore::$routes as $row ) {
			$routes[] = $row['route'];
		}
		$this->assertContains( '/migration/report', $routes );
	}
}
