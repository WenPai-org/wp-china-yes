<?php
/**
 * WP-CLI: wp wpcy migrate [--dry-run] [--rollback]
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Cli;

use WenPai\ChinaYes\Migration\Runner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dry-run, execute, or roll back 3.x → 4.0 settings.
 */
final class MigrateCommand {

	/**
	 * Migration runner.
	 *
	 * @var Runner
	 */
	private $runner;

	/**
	 * Constructor. Does not register the command.
	 *
	 * @since 4.0.0
	 *
	 * @param Runner|null $runner Migration runner.
	 */
	public function __construct( $runner = null ) {
		$this->runner = $runner instanceof Runner ? $runner : new Runner();
	}

	/**
	 * Migrate 3.x `wp_china_yes` into 4.0 options.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Map and print kept / ignored keys. Does not write.
	 *
	 * [--rollback]
	 * : Restore 4.0 options to pre-migration. Does not rewrite `wp_china_yes`.
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
	 * @return int
	 */
	public function __invoke( $args, $assoc_args ): int {
		unset( $args );

		if ( ! empty( $assoc_args['rollback'] ) ) {
			$ok = $this->runner->rollback();
			StatusCommand::emit(
				array(
					'ok'     => $ok,
					'action' => 'rollback',
				)
			);
			return $ok ? 0 : 1;
		}

		if ( ! empty( $assoc_args['dry-run'] ) ) {
			$report = $this->runner->dry_run();
			StatusCommand::emit( $this->report_payload( $report->to_array(), 'dry-run' ) );
			return 0;
		}

		$report = $this->runner->execute();
		StatusCommand::emit( $this->report_payload( $report->to_array(), 'execute' ) );
		return 0;
	}

	/**
	 * CLI JSON: kept / ignored / action. Settings included for inspection.
	 *
	 * @param array<string, mixed> $report Mapper document.
	 * @param string               $action dry-run|execute.
	 * @return array<string, mixed>
	 */
	private function report_payload( array $report, string $action ): array {
		return array(
			'action'          => $action,
			'kept'            => isset( $report['kept'] ) && is_array( $report['kept'] ) ? $report['kept'] : array(),
			'ignored'         => isset( $report['ignored'] ) && is_array( $report['ignored'] ) ? $report['ignored'] : array(),
			'ignored_reasons' => isset( $report['ignored_reasons'] ) && is_array( $report['ignored_reasons'] ) ? $report['ignored_reasons'] : array(),
			'settings'        => isset( $report['settings'] ) && is_array( $report['settings'] ) ? $report['settings'] : array(),
		);
	}
}
