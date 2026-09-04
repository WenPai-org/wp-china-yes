<?php
/**
 * WP-CLI: wp wpcy config export|import
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Cli;

use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Config\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Export settings (no credentials) and import through Validator.
 */
final class ConfigCommand {

	/**
	 * Settings repository.
	 *
	 * @var Repository
	 */
	private $repository;

	/**
	 * Constructor. Does not register the command.
	 *
	 * @since 4.0.0
	 *
	 * @param Repository|null $repository Settings access.
	 */
	public function __construct( $repository = null ) {
		$this->repository = $repository instanceof Repository ? $repository : new Repository();
	}

	/**
	 * Print wpcy_settings and network option as JSON. Credential stripped.
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Associative args.
	 * @return void
	 */
	public function export( $args, $assoc_args ): void {
		unset( $args, $assoc_args );
		StatusCommand::emit( $this->export_document() );
	}

	/**
	 * Import JSON through Validator. Unknown keys are discarded.
	 *
	 * ## OPTIONS
	 *
	 * [<file>]
	 * : Path to a JSON file. Reads stdin when omitted.
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Associative args.
	 * @return int
	 */
	public function import( $args, $assoc_args ): int {
		unset( $assoc_args );
		$file = isset( $args[0] ) ? (string) $args[0] : '';
		$raw  = $this->read_input( $file );
		if ( null === $raw ) {
			return 1;
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return 1;
		}

		$this->import_document( $decoded );
		return 0;
	}

	/**
	 * Settings plus network option. Never includes binding.credential.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function export_document(): array {
		$exported = $this->repository->export();
		$identity = isset( $exported['identity'] ) && is_array( $exported['identity'] ) ? $exported['identity'] : array();
		if ( isset( $identity['binding'] ) && is_array( $identity['binding'] ) ) {
			unset( $identity['binding']['credential'] );
		}

		$out = array(
			Schema::SETTINGS      => isset( $exported['settings'] ) ? $exported['settings'] : $this->repository->all(),
			Schema::SITE_IDENTITY => $identity,
		);

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			$out[ Schema::NETWORK_SETTINGS ] = $this->repository->all();
		}

		return $this->strip_secrets( $out );
	}

	/**
	 * Write known option documents. Unknown keys go through Validator.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $decoded Import payload.
	 */
	public function import_document( array $decoded ): void {
		$map = array(
			Schema::SETTINGS         => Schema::SETTINGS,
			Schema::NETWORK_SETTINGS => Schema::NETWORK_SETTINGS,
			Schema::SITE_OVERRIDES   => Schema::SITE_OVERRIDES,
			'settings'               => Schema::SETTINGS,
			'network_settings'       => Schema::NETWORK_SETTINGS,
		);

		foreach ( $map as $key => $option ) {
			if ( ! isset( $decoded[ $key ] ) || ! is_array( $decoded[ $key ] ) ) {
				continue;
			}
			if ( Schema::NETWORK_SETTINGS === $option && function_exists( 'is_multisite' ) && ! is_multisite() ) {
				continue;
			}
			$this->repository->save_option( $option, $decoded[ $key ] );
		}

		if ( ! isset( $decoded[ Schema::SETTINGS ] ) && ! isset( $decoded['settings'] ) && $this->looks_like_settings( $decoded ) ) {
			$this->repository->save_option( Schema::SETTINGS, $decoded );
		}
	}

	/**
	 * Recursively drop credential, email, and order bodies.
	 *
	 * @param mixed $value Tree.
	 * @return mixed
	 */
	private function strip_secrets( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		$blocked = array( 'credential', 'password', 'token', 'email', 'admin_email', 'order', 'order_items', 'line_items' );
		$out     = array();
		foreach ( $value as $key => $child ) {
			if ( is_string( $key ) && in_array( $key, $blocked, true ) ) {
				continue;
			}
			$out[ $key ] = $this->strip_secrets( $child );
		}

		return $out;
	}

	/**
	 * Whether $decoded looks like a settings object rather than an envelope.
	 *
	 * @param array<string, mixed> $decoded Payload.
	 */
	private function looks_like_settings( array $decoded ): bool {
		return isset( $decoded['schema_version'] ) || isset( $decoded['connectivity'] );
	}

	/**
	 * Read a file or stdin. Null on failure.
	 *
	 * @param string $file Path, empty for stdin.
	 * @return string|null
	 */
	private function read_input( string $file ) {
		if ( '' === $file || '-' === $file ) {
			$raw = file_get_contents( 'php://stdin' );
			return is_string( $raw ) ? $raw : null;
		}

		if ( ! is_readable( $file ) ) {
			return null;
		}

		$raw = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- CLI import from a local path.
		return is_string( $raw ) ? $raw : null;
	}
}
