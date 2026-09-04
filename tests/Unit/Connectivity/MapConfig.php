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
 * Path => value map.
 */
final class MapConfig implements Config {

	/**
	 * Stored paths.
	 *
	 * @var array<string, mixed>
	 */
	private $values;

	/**
	 * Create a map.
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
	 * @param mixed  $fallback Value when absent.
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
