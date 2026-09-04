<?php
/**
 * WP-CLI: wp wpcy status
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
 * Prints kernel switch, recovery_mode, and latest target results as JSON.
 */
final class StatusCommand {

	/**
	 * Result source.
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
	 * @param Checker     $checker Result source.
	 * @param object|null $config  Config read model.
	 */
	public function __construct( Checker $checker, $config = null ) {
		$this->checker = $checker;
		$this->config  = ( is_object( $config ) && method_exists( $config, 'get' ) ) ? $config : null;
	}

	/**
	 * Show kernel and last diagnostic results.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. JSON only.
	 * ---
	 * default: json
	 * options:
	 *   - json
	 * ---
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Associative args.
	 * @return void
	 */
	public function __invoke( $args, $assoc_args ): void {
		unset( $args, $assoc_args );
		self::emit( $this->payload() );
	}

	/**
	 * Status document: kernel, recovery_mode, targets.
	 *
	 * @since 4.0.0
	 *
	 * @return array{kernel: string, recovery_mode: bool, targets: list<array<string, mixed>>}
	 */
	public function payload(): array {
		return self::build_payload( $this->checker, $this->config );
	}

	/**
	 * Shared envelope for status and doctor.
	 *
	 * @since 4.0.0
	 *
	 * @param Checker     $checker Result source.
	 * @param object|null $config  Config read model.
	 * @return array{kernel: string, recovery_mode: bool, targets: list<array<string, mixed>>}
	 */
	public static function build_payload( Checker $checker, $config = null ): array {
		$kernel = 'legacy';
		if ( defined( 'WPCY_KERNEL' ) ) {
			$kernel = (string) WPCY_KERNEL;
		}

		$recovery = false;
		if ( is_object( $config ) && method_exists( $config, 'get' ) ) {
			$recovery = (bool) $config->get( 'recovery_mode', false );
		}

		return array(
			'kernel'        => $kernel,
			'recovery_mode' => $recovery,
			'targets'       => $checker->latest(),
		);
	}

	/**
	 * Write JSON to stdout (WP-CLI line when available).
	 *
	 * @param array<string, mixed> $payload Document.
	 */
	public static function emit( array $payload ): void {
		$json = function_exists( 'wp_json_encode' )
			? wp_json_encode( $payload )
			: json_encode( $payload ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- unit bootstrap has no WordPress.

		if ( ! is_string( $json ) ) {
			$json = '{}';
		}

		if ( class_exists( '\WP_CLI' ) ) {
			\WP_CLI::line( $json );
			return;
		}

		echo $json . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON stdout for WP-CLI.
	}
}
