<?php
/**
 * Always-on compatibility report cron. No setting, no UI.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Telemetry;

use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Core\Logger;
use WenPai\ChinaYes\Core\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schedules the daily report regardless of any leftover 3.x telemetry option.
 */
final class TelemetryModule implements Module {

	/**
	 * Cron hook name. Same as 3.x so an in-flight event still fires.
	 *
	 * @since 4.0.0
	 */
	public const CRON_HOOK = 'wpcy_daily_telemetry';

	/**
	 * Cloud-bridge ingest URL.
	 *
	 * @since 4.0.0
	 */
	public const ENDPOINT = 'https://updates.wenpai.net/api/v1/telemetry';

	/**
	 * Config used only for site_uuid. Never read a telemetry switch.
	 *
	 * @var Repository|null
	 */
	private $config;

	/**
	 * Kernel logger.
	 *
	 * @var Logger|null
	 */
	private $logger;

	/**
	 * Report collector. Injected in tests.
	 *
	 * @var Report|null
	 */
	private $report;

	/**
	 * Create the module. Constructor does not register hooks.
	 *
	 * @since 4.0.0
	 *
	 * @param Repository|null $config Identity source.
	 * @param Logger|null     $logger Failure sink.
	 * @param Report|null     $report Collector override.
	 */
	public function __construct( $config = null, $logger = null, $report = null ) {
		$this->config = $config instanceof Repository ? $config : null;
		$this->logger = $logger instanceof Logger ? $logger : null;
		$this->report = $report instanceof Report ? $report : null;
	}

	/**
	 * Module id.
	 *
	 * @since 4.0.0
	 */
	public function id(): string {
		return 'telemetry';
	}

	/**
	 * Every scene: 3.x scheduled from init on any request.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function contexts(): array {
		return Environment::CONTEXTS;
	}

	/**
	 * Hook the cron callback and schedule the next run if missing.
	 *
	 * Unconditional. Leftover `telemetry` / `telemetry_site_url` options are ignored.
	 *
	 * @since 4.0.0
	 */
	public function register(): void {
		add_action( self::CRON_HOOK, array( $this, 'send_report' ) );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			$delay = wp_rand( 0, 6 * HOUR_IN_SECONDS );
			wp_schedule_single_event( time() + $delay, self::CRON_HOOK );
		}
	}

	/**
	 * Collect and POST the report. At most once per UTC day.
	 *
	 * @since 4.0.0
	 */
	public function send_report(): void {
		$today     = gmdate( 'Y-m-d' );
		$last_sent = get_transient( 'wpcy_telemetry_last_sent' );
		if ( $last_sent === $today ) {
			$this->schedule_next();
			return;
		}

		try {
			$collector = $this->report instanceof Report ? $this->report : new Report( $this->config );
			$payload   = $collector->collect();
			$body      = function_exists( 'wp_json_encode' ) ? wp_json_encode( $payload ) : json_encode( $payload ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit bootstrap has no WordPress.

			$response = wp_remote_post(
				self::ENDPOINT,
				array(
					'body'      => $body,
					'headers'   => array( 'Content-Type' => 'application/json' ),
					'timeout'   => 10,
					'blocking'  => true,
					'sslverify' => true,
				)
			);

			if ( is_wp_error( $response ) ) {
				$this->log_send_failure( $response->get_error_message() );
			} else {
				$status = (int) wp_remote_retrieve_response_code( $response );
				if ( $status < 200 || $status >= 300 ) {
					$this->log_send_failure( 'Compatibility report endpoint returned HTTP ' . $status );
				} else {
					set_transient( 'wpcy_telemetry_last_sent', $today, 36 * HOUR_IN_SECONDS );
				}
			}
		} catch ( \Throwable $e ) {
			$this->log_send_failure( $e->getMessage() );
		}

		$this->schedule_next();
	}

	/**
	 * Record a send failure for diagnostics. Does not pretend success.
	 *
	 * @since 4.0.0
	 *
	 * @param string $message Failure text.
	 */
	private function log_send_failure( string $message ): void {
		if ( $this->logger instanceof Logger ) {
			$this->logger->error(
				'Compatibility report send failed.',
				array(
					'exception' => $message,
				)
			);
		}
	}

	/**
	 * Clear the cron hook on plugin deactivation.
	 *
	 * @since 4.0.0
	 */
	public function deactivate(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Schedule tomorrow midnight plus a 0–6h jitter.
	 *
	 * @since 4.0.0
	 */
	private function schedule_next(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			$tomorrow = strtotime( 'tomorrow midnight' );
			$delay    = wp_rand( 0, 6 * HOUR_IN_SECONDS );
			wp_schedule_single_event( $tomorrow + $delay, self::CRON_HOOK );
		}
	}
}
