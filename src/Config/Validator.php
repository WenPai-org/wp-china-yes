<?php
/**
 * Sanitize option payloads against Schema: drop unknown keys, enforce enum.
 *
 * Invalid values are discarded (and replaced with defaults when filling a
 * complete object). Nothing here throws for bad input.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JSON-Schema-shaped walker. additionalProperties: false → drop + warning.
 */
final class Validator {

	/**
	 * Sentinel meaning “omit this key” (used for partial overlays).
	 *
	 * @var object
	 */
	private $omit;

	/**
	 * Warnings collected during the last sanitize() call.
	 *
	 * @var array<int, array{path: string, message: string}>
	 */
	private $warnings = array();

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 */
	public function __construct() {
		$this->omit = new \stdClass();
	}

	/**
	 * Warnings from the last sanitize() call.
	 *
	 * @since 4.0.0
	 *
	 * @return array<int, array{path: string, message: string}>
	 */
	public function warnings(): array {
		return $this->warnings;
	}

	/**
	 * Return a schema-conforming copy of $input.
	 *
	 * Site overrides do not receive defaults for missing keys (a missing
	 * segment means “do not override”). Other options are filled.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed  $input  Raw option value.
	 * @param string $option Option name.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input, string $option ): array {
		$this->warnings = array();
		$schema         = Schema::definition( $option );
		$fill           = Schema::SITE_OVERRIDES !== $option;

		if ( ! is_array( $input ) ) {
			$this->warn( '', 'Value is not an object; using an empty object.' );
			$input = array();
		}

		$result = $this->check_value( $input, $schema, '', $fill );
		return is_array( $result ) ? $result : array();
	}

	/**
	 * Validate one JSON-Schema node.
	 *
	 * @param mixed                $value  Input.
	 * @param array<string, mixed> $schema Node schema.
	 * @param string               $path   Dotted path for warnings.
	 * @param bool                 $fill   Fill defaults for missing keys.
	 * @return mixed
	 */
	private function check_value( $value, array $schema, string $path, bool $fill ) {
		if ( $value instanceof \stdClass ) {
			$value = (array) $value;
		}

		$type = $schema['type'] ?? null;
		if ( is_array( $type ) ) {
			if ( null === $value && in_array( 'null', $type, true ) ) {
				return null;
			}
			$non_null = array_values( array_diff( $type, array( 'null' ) ) );
			$type     = isset( $non_null[0] ) ? $non_null[0] : 'string';
		}

		if ( 'object' === $type ) {
			if ( ! is_array( $value ) || $this->is_list( $value ) ) {
				return $this->fail( $path, 'Expected an object.', $schema, $fill );
			}
			return $this->check_object( $value, $schema, $path, $fill );
		}

		if ( 'array' === $type ) {
			if ( ! is_array( $value ) ) {
				return $this->fail( $path, 'Expected an array.', $schema, $fill );
			}
			return $this->check_array( $value, $schema, $path );
		}

		if ( 'string' === $type && ! is_string( $value ) ) {
			return $this->fail( $path, 'Expected a string.', $schema, $fill );
		}
		if ( 'integer' === $type && ! is_int( $value ) ) {
			return $this->fail( $path, 'Expected an integer.', $schema, $fill );
		}
		if ( 'boolean' === $type && ! is_bool( $value ) ) {
			return $this->fail( $path, 'Expected a boolean.', $schema, $fill );
		}

		if ( array_key_exists( 'const', $schema ) && $value !== $schema['const'] ) {
			return $this->fail( $path, 'Value does not match const.', $schema, $fill );
		}

		if ( isset( $schema['enum'] ) && is_array( $schema['enum'] ) && ! in_array( $value, $schema['enum'], true ) ) {
			return $this->fail( $path, 'Value is not in enum.', $schema, $fill );
		}

		if ( is_int( $value ) && isset( $schema['minimum'] ) && $value < (int) $schema['minimum'] ) {
			return $this->fail( $path, 'Integer below minimum.', $schema, $fill );
		}

		if ( is_string( $value ) && isset( $schema['minLength'] ) && strlen( $value ) < (int) $schema['minLength'] ) {
			return $this->fail( $path, 'String shorter than minLength.', $schema, $fill );
		}

		if ( is_string( $value ) && isset( $schema['maxLength'] ) && strlen( $value ) > (int) $schema['maxLength'] ) {
			return $this->fail( $path, 'String longer than maxLength.', $schema, $fill );
		}

		if ( is_string( $value ) && isset( $schema['pattern'] ) && 1 !== preg_match( '/' . $schema['pattern'] . '/', $value ) ) {
			return $this->fail( $path, 'String does not match pattern.', $schema, $fill );
		}

		if ( is_string( $value ) && isset( $schema['format'] ) && ! $this->check_format( $value, (string) $schema['format'] ) ) {
			return $this->fail( $path, 'String does not match format.', $schema, $fill );
		}

		return $value;
	}

	/**
	 * Validate an object node and drop unknown keys.
	 *
	 * @param array<int|string, mixed> $input  Object.
	 * @param array<string, mixed>     $schema Node schema.
	 * @param string                   $path   Dotted path.
	 * @param bool                     $fill   Fill defaults.
	 * @return array<string, mixed>
	 */
	private function check_object( array $input, array $schema, string $path, bool $fill ): array {
		$properties = isset( $schema['properties'] ) && is_array( $schema['properties'] ) ? $schema['properties'] : array();
		$additional = $schema['additionalProperties'] ?? true;
		$required   = isset( $schema['required'] ) && is_array( $schema['required'] ) ? $schema['required'] : array();
		$out        = array();

		foreach ( $input as $key => $val ) {
			if ( ! is_string( $key ) ) {
				continue;
			}
			$child = $this->join( $path, $key );
			if ( ! isset( $properties[ $key ] ) ) {
				if ( false === $additional ) {
					$this->warn( $child, 'Unknown key discarded.' );
				}
				continue;
			}
			$checked = $this->check_value( $val, $properties[ $key ], $child, $fill );
			if ( $this->omit === $checked ) {
				continue;
			}
			$out[ $key ] = $checked;
		}

		foreach ( $properties as $key => $prop ) {
			if ( array_key_exists( $key, $out ) ) {
				continue;
			}
			$child       = $this->join( $path, $key );
			$is_required = in_array( $key, $required, true );
			if ( $fill ) {
				if ( $is_required || $this->has_default( $prop ) ) {
					$out[ $key ] = $this->default_value( $prop );
				}
			} elseif ( $is_required ) {
				$this->warn( $child, 'Missing required key.' );
			}
		}

		return $out;
	}

	/**
	 * Validate an array node; extra items beyond maxItems are dropped.
	 *
	 * @param array<int|string, mixed> $input  List.
	 * @param array<string, mixed>     $schema Node schema.
	 * @param string                   $path   Dotted path.
	 * @return array<int, mixed>
	 */
	private function check_array( array $input, array $schema, string $path ): array {
		$items = isset( $schema['items'] ) && is_array( $schema['items'] ) ? $schema['items'] : array();
		$max   = isset( $schema['maxItems'] ) ? (int) $schema['maxItems'] : null;
		$out   = array();
		$index = 0;

		foreach ( $input as $val ) {
			if ( null !== $max && count( $out ) >= $max ) {
				$this->warn( $path, 'Array longer than maxItems; extra entries discarded.' );
				break;
			}
			$item_path = $path . '[' . $index . ']';
			++$index;
			$checked = $this->check_value( $val, $items, $item_path, false );
			if ( $this->omit === $checked ) {
				continue;
			}
			if ( is_array( $checked ) && isset( $items['required'] ) && is_array( $items['required'] ) ) {
				$missing = false;
				foreach ( $items['required'] as $need ) {
					if ( ! array_key_exists( $need, $checked ) ) {
						$missing = true;
						break;
					}
				}
				if ( $missing ) {
					$this->warn( $item_path, 'Array item missing required keys; discarded.' );
					continue;
				}
			}
			$out[] = $checked;
		}

		if ( ! empty( $schema['uniqueItems'] ) ) {
			$deduped = array();
			$seen    = array();
			foreach ( $out as $item ) {
				$key = $this->unique_item_key( $item );
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;
				$deduped[]    = $item;
			}
			$out = $deduped;
		}

		return $out;
	}

	/**
	 * Record a warning and return the default or omit sentinel.
	 *
	 * @param string               $path    Dotted path.
	 * @param string               $message Warning text.
	 * @param array<string, mixed> $schema  Node schema.
	 * @param bool                 $fill    Fill defaults.
	 * @return mixed
	 */
	private function fail( string $path, string $message, array $schema, bool $fill ) {
		$this->warn( $path, $message );
		if ( $fill ) {
			return $this->default_value( $schema );
		}
		return $this->omit;
	}

	/**
	 * Schema default, or a type-appropriate empty value.
	 *
	 * @param array<string, mixed> $schema Node schema.
	 * @return mixed
	 */
	private function default_value( array $schema ) {
		if ( array_key_exists( 'default', $schema ) ) {
			return $schema['default'];
		}

		$type = $schema['type'] ?? null;
		if ( is_array( $type ) ) {
			if ( in_array( 'null', $type, true ) ) {
				return null;
			}
			$type = isset( $type[0] ) ? $type[0] : 'string';
		}

		if ( 'object' === $type ) {
			return $this->check_object( array(), $schema, '', true );
		}
		if ( 'array' === $type ) {
			return array();
		}
		if ( 'boolean' === $type ) {
			return false;
		}
		if ( 'integer' === $type ) {
			return isset( $schema['const'] ) ? $schema['const'] : 0;
		}
		return '';
	}

	/**
	 * Whether the node declares a default.
	 *
	 * @param mixed $schema Node schema.
	 * @return bool
	 */
	private function has_default( $schema ): bool {
		return is_array( $schema ) && array_key_exists( 'default', $schema );
	}

	/**
	 * Check JSON Schema format annotations used by this spec.
	 *
	 * @param string $value  Candidate.
	 * @param string $format uuid|date-time.
	 * @return bool
	 */
	private function check_format( string $value, string $format ): bool {
		if ( 'uuid' === $format ) {
			return (bool) preg_match(
				'/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
				$value
			);
		}
		if ( 'date-time' === $format ) {
			if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $value ) ) {
				return false;
			}
			try {
				new \DateTimeImmutable( $value );
				return true;
			} catch ( \Exception $e ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Whether $value is a JSON array (0-based list), not an object.
	 *
	 * @param array<int|string, mixed> $value Candidate array.
	 * @return bool
	 */
	private function is_list( array $value ): bool {
		if ( array() === $value ) {
			return false;
		}
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	/**
	 * Stable key for uniqueItems comparison. Scalars stringify; arrays recurse.
	 *
	 * @param mixed $item Array item.
	 * @return string
	 */
	private function unique_item_key( $item ): string {
		if ( is_scalar( $item ) || null === $item ) {
			return gettype( $item ) . ':' . (string) $item;
		}
		if ( ! is_array( $item ) ) {
			return gettype( $item );
		}
		$parts = array();
		foreach ( $item as $k => $v ) {
			$parts[] = (string) $k . '=' . $this->unique_item_key( $v );
		}
		return '{' . implode( ',', $parts ) . '}';
	}

	/**
	 * Join a parent dotted path with a child key.
	 *
	 * @param string $base Parent path.
	 * @param string $key  Child key.
	 * @return string
	 */
	private function join( string $base, string $key ): string {
		return '' === $base ? $key : $base . '.' . $key;
	}

	/**
	 * Append a warning. Message must not include secrets.
	 *
	 * @param string $path    Dotted path (may be empty).
	 * @param string $message Warning text. Must not include secrets.
	 * @return void
	 */
	private function warn( string $path, string $message ): void {
		$this->warnings[] = array(
			'path'    => $path,
			'message' => $message,
		);
	}
}
