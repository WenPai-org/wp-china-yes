<?php
/**
 * In-memory Config for Connectivity unit tests.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Connectivity;

use WenPai\ChinaYes\Core\Config;

/**
 * Dotted-path bag. Missing keys return the fallback.
 */
final class MapConfig implements Config {

	/**
	 * Stored values keyed by dotted path.
	 *
	 * @var array<string, mixed>
	 */
	private $values;

	/**
	 * Store dotted-path values.
	 *
	 * @param array<string, mixed> $values Path => value.
	 */
	public function __construct( array $values = array() ) {
		$this->values = $values;
	}

	/**
	 * Read a dotted path.
	 *
	 * @param string $path     Dotted key.
	 * @param mixed  $fallback Value when the path is absent.
	 * @return mixed
	 */
	public function get( string $path, $fallback = null ) {
		return array_key_exists( $path, $this->values ) ? $this->values[ $path ] : $fallback;
	}

	/**
	 * Whether a dotted path exists.
	 *
	 * @param string $path Dotted key.
	 */
	public function has( string $path ): bool {
		return array_key_exists( $path, $this->values );
	}
}
