<?php
/**
 * Diagnostics module: scheduled probes, Site Health, WP-CLI commands.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Diagnostics;

use WenPai\ChinaYes\Cli\ConfigCommand;
use WenPai\ChinaYes\Cli\DoctorCommand;
use WenPai\ChinaYes\Cli\StatusCommand;
use WenPai\ChinaYes\Connectivity\PublicAssets\Manifest;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Core\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module id diagnostics. Cron is gated by diagnostics.scheduled_checks.
 */
final class DiagnosticsModule implements Module {

	/**
	 * Cron hook for scheduled connection checks.
	 *
	 * @since 4.0.0
	 */
	public const CRON_HOOK = 'wpcy_diagnostics_check';

	/**
	 * Optional config (Core\Config or Repository).
	 *
	 * @var object|null
	 */
	private $config;

	/**
	 * Probe runner.
	 *
	 * @var Checker|null
	 */
	private $checker;

	/**
	 * Site Health section.
	 *
	 * @var SiteHealth|null
	 */
	private $site_health;

	/**
	 * Signed mirror manifest client.
	 *
	 * @var Manifest|null
	 */
	private ?Manifest $manifest;

	/**
	 * Constructor. Does not register hooks.
	 *
	 * @since 4.0.0
	 *
	 * @param object|null     $config      Config read model.
	 * @param Checker|null    $checker     Probe runner.
	 * @param SiteHealth|null $site_health Debug section.
	 * @param Manifest|null   $manifest    Optional signed mirror manifest client.
	 */
	public function __construct( $config = null, $checker = null, $site_health = null, ?Manifest $manifest = null ) {
		$this->config      = ( is_object( $config ) && method_exists( $config, 'get' ) ) ? $config : null;
		$this->checker     = $checker instanceof Checker ? $checker : null;
		$this->site_health = $site_health instanceof SiteHealth ? $site_health : null;
		$this->manifest    = $manifest;
	}

	/**
	 * Module id.
	 *
	 * @since 4.0.0
	 */
	public function id(): string {
		return 'diagnostics';
	}

	/**
	 * Admin (Site Health), cron (scheduled checks), CLI, REST (results for M1-07).
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public function contexts(): array {
		return array(
			Environment::ADMIN,
			Environment::CRON,
			Environment::CLI,
			Environment::REST,
		);
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
	 * Hook cron, Site Health, and WP-CLI. Constructor does not register hooks.
	 *
	 * @since 4.0.0
	 */
	public function register(): void {
		add_filter( 'cron_schedules', array( $this, 'register_cron_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- 10-minute manifest sync interval per T4.3 spec.
		add_action( self::CRON_HOOK, array( $this, 'run_scheduled' ) );
		add_action( Manifest::CRON_HOOK, array( $this, 'run_manifest_sync' ) );

		if ( $this->scheduled_checks_enabled() ) {
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time(), 'hourly', self::CRON_HOOK );
			}
			if ( ! wp_next_scheduled( Manifest::CRON_HOOK ) ) {
				wp_schedule_event( time(), Manifest::CRON_SCHEDULE, Manifest::CRON_HOOK );
			}
		} else {
			wp_clear_scheduled_hook( self::CRON_HOOK );
			wp_clear_scheduled_hook( Manifest::CRON_HOOK );
		}

		$this->site_health()->register();
		$this->register_cli();
	}

	/**
	 * Register 10-minute WP-Cron recurrence for signed mirror manifest sync.
	 *
	 * @since 4.1.0
	 *
	 * @param array<string, array<string, mixed>> $schedules Existing cron schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_cron_schedules( array $schedules ): array {
		if ( ! isset( $schedules[ Manifest::CRON_SCHEDULE ] ) ) {
			$schedules[ Manifest::CRON_SCHEDULE ] = array(
				'interval' => 600,
				'display'  => 'Every 10 Minutes (WPCY Manifest)',
			);
		}

		return $schedules;
	}

	/**
	 * Cron callback for 10-minute signed mirror manifest sync.
	 *
	 * @since 4.1.0
	 */
	public function run_manifest_sync(): void {
		if ( ! $this->scheduled_checks_enabled() ) {
			return;
		}

		$this->manifest()->sync();
	}

	/**
	 * Manifest client instance.
	 */
	private function manifest(): Manifest {
		if ( null === $this->manifest ) {
			$cfg            = $this->config instanceof \WenPai\ChinaYes\Core\Config ? $this->config : null;
			$this->manifest = new Manifest( $cfg );
		}

		return $this->manifest;
	}

	/**
	 * Cron callback. Skips when scheduled_checks is false.
	 *
	 * @since 4.0.0
	 */
	public function run_scheduled(): void {
		if ( ! $this->scheduled_checks_enabled() ) {
			return;
		}

		$this->checker()->run();
	}

	/**
	 * Probe runner used by REST (M1-07) and CLI.
	 *
	 * @since 4.0.0
	 */
	public function checker(): Checker {
		if ( ! $this->checker instanceof Checker ) {
			$this->checker = new Checker( null, null, null, $this->config );
		}

		return $this->checker;
	}

	/**
	 * Site Health helper.
	 */
	private function site_health(): SiteHealth {
		if ( ! $this->site_health instanceof SiteHealth ) {
			$this->site_health = new SiteHealth( $this->checker() );
		}

		return $this->site_health;
	}

	/**
	 * Register wp wpcy status|doctor|config in the CLI scene only.
	 */
	private function register_cli(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		if ( ! class_exists( '\WP_CLI' ) ) {
			return;
		}

		if ( defined( 'WPCY_CLI_REGISTERED' ) ) {
			return;
		}

		define( 'WPCY_CLI_REGISTERED', true );

		$checker = $this->checker();
		$config  = $this->config;
		$repo    = $config instanceof \WenPai\ChinaYes\Config\Repository ? $config : null;

		\WP_CLI::add_command( 'wpcy status', new StatusCommand( $checker, $config ) );
		\WP_CLI::add_command( 'wpcy doctor', new DoctorCommand( $checker, $config ) );
		\WP_CLI::add_command( 'wpcy config', new ConfigCommand( $repo ) );
	}

	/**
	 * Whether diagnostics.scheduled_checks is true (default true).
	 */
	private function scheduled_checks_enabled(): bool {
		if ( ! is_object( $this->config ) || ! method_exists( $this->config, 'get' ) ) {
			return true;
		}

		return (bool) $this->config->get( 'diagnostics.scheduled_checks', true );
	}
}
