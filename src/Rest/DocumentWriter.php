<?php
/**
 * Merge + schema-check + persist for PUT /settings and /network-settings.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Rest;

use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Config\Validator;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unknown keys are dropped (warning). Type/enum failures are 400.
 */
final class DocumentWriter {

	/**
	 * Settings access.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param Repository $repository Settings access.
	 */
	public function __construct( Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Merge $incoming into $current, validate, persist. True or WP_Error.
	 *
	 * @since 4.0.0
	 *
	 * @param string               $option   Option name.
	 * @param array<string, mixed> $current  Existing document.
	 * @param mixed                $incoming PUT body.
	 * @return true|WP_Error
	 */
	public function put( string $option, array $current, $incoming ) {
		if ( ! is_array( $incoming ) || $this->is_list( $incoming ) ) {
			return RestError::invalid_schema();
		}

		$merged    = $this->deep_merge( $current, $incoming );
		$validator = new Validator();
		$clean     = $validator->sanitize( $merged, $option );

		if ( $this->schema_failed( $validator->warnings() ) ) {
			return RestError::invalid_schema();
		}

		$this->repository->save_option( $option, $clean );
		return true;
	}

	/**
	 * Site settings document (effective, no identity/credential).
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function site_document(): array {
		return $this->repository->all();
	}

	/**
	 * Network settings document. Not merged with site overrides.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function network_document(): array {
		$raw = array();
		if ( function_exists( 'get_site_option' ) ) {
			$loaded = get_site_option( Schema::NETWORK_SETTINGS, array() );
			$raw    = is_array( $loaded ) ? $loaded : array();
		}

		$validator = new Validator();
		return $validator->sanitize( $raw, Schema::NETWORK_SETTINGS );
	}

	/**
	 * Whether Validator warnings include a real schema failure.
	 *
	 * Unknown keys and extra array items are dropped, not rejected.
	 *
	 * @since 4.0.0
	 *
	 * @param array<int, array{path: string, message: string}> $warnings Validator warnings.
	 */
	public function schema_failed( array $warnings ): bool {
		foreach ( $warnings as $warning ) {
			if ( $this->is_drop_warning( $warning['message'] ) ) {
				continue;
			}
			return true;
		}

		return false;
	}

	/**
	 * Drop-not-reject warning text from Validator.
	 *
	 * @param string $message Warning text.
	 */
	private function is_drop_warning( string $message ): bool {
		if ( false !== strpos( $message, 'Unknown key discarded' ) ) {
			return true;
		}
		if ( false !== strpos( $message, 'extra entries discarded' ) ) {
			return true;
		}
		if ( false !== strpos( $message, 'Array item missing required keys' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Object keys merge recursively; lists and scalars are replaced.
	 *
	 * @param array<string, mixed> $base    Left.
	 * @param array<string, mixed> $overlay Right.
	 * @return array<string, mixed>
	 */
	private function deep_merge( array $base, array $overlay ): array {
		foreach ( $overlay as $key => $value ) {
			if ( is_array( $value )
				&& isset( $base[ $key ] )
				&& is_array( $base[ $key ] )
				&& ! $this->is_list( $value )
				&& ! $this->is_list( $base[ $key ] )
			) {
				$base[ $key ] = $this->deep_merge( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}

		return $base;
	}

	/**
	 * Whether $value is a JSON array (0-based list).
	 *
	 * @param array<int|string, mixed> $value Candidate.
	 */
	private function is_list( array $value ): bool {
		if ( array() === $value ) {
			return false;
		}

		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
