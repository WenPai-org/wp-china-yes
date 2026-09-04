<?php
/**
 * In-memory Config for unit tests.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Core;

use WenPai\ChinaYes\Core\Config;

/**
 * Empty config. Paths never exist.
 */
final class ArrayConfig implements Config {

	/**
	 * Read a dotted path. Always returns the fallback.
	 *
	 * @param string $path     Dotted key.
	 * @param mixed  $fallback Value when absent.
	 * @return mixed
	 */
	public function get( string $path, $fallback = null ) {
		unset( $path );
		return $fallback;
	}

	/**
	 * Whether a dotted path exists. Always false.
	 *
	 * @param string $path Dotted key.
	 */
	public function has( string $path ): bool {
		unset( $path );
		return false;
	}
}
