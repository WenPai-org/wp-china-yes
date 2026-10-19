<?php
/**
 * WP-CLI: wp wpcy doctor
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Cli;

use WenPai\ChinaYes\Diagnostics\Checker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs one probe round and prints the same JSON as status.
 */
final class DoctorCommand {

	/**
	 * Probe runner.
	 *
	 * @var Checker
	 */
	private $checker;

	/**
	 * Optional config for recovery_mode.
	 *
	 * @var object|null
	 */
	private $config;

	/**
	 * Constructor. Does not register the command.
	 *
	 * @since 4.0.0
	 *
	 * @param Checker     $checker Probe runner.
	 * @param object|null $config  Config read model.
	 */
	public function __construct( Checker $checker, $config = null ) {
		$this->checker = $checker;
		$this->config  = ( is_object( $config ) && method_exists( $config, 'get' ) ) ? $config : null;
	}

	/**
	 * Run connection checks and print JSON.
	 *
	 * Exit code 1 when any target is down.
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Associative args.
	 * @return int
	 */
	public function __invoke( $args, $assoc_args ): int {
		unset( $args, $assoc_args );
		$code = $this->run();
		if ( $code > 0 && class_exists( '\WP_CLI' ) ) {
			\WP_CLI::halt( $code );
		}

		return $code;
	}

	/**
	 * Probe, print, return 1 when any target is down.
	 *
	 * @since 4.0.0
	 */
	public function run(): int {
		$this->checker->run();
		$payload = StatusCommand::build_payload( $this->checker, $this->config );
		StatusCommand::emit( $payload );

		return self::has_down( $payload ) ? 1 : 0;
	}

	/**
	 * Whether any target result is down.
	 *
	 * @param array<string, mixed> $payload Status document.
	 */
	public static function has_down( array $payload ): bool {
		$targets = isset( $payload['targets'] ) && is_array( $payload['targets'] ) ? $payload['targets'] : array();
		foreach ( $targets as $row ) {
			if ( is_array( $row ) && isset( $row['result'] ) && Checker::RESULT_DOWN === $row['result'] ) {
				return true;
			}
		}

		return false;
	}
}
