<?php
/**
 * Narrow config read contract. Repository lands in M1-03.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Core;

/**
 * Read-only dotted-path config. Does not implement the four options.
 */
interface Config {

	/**
	 * Read a dotted path.
	 *
	 * @param string $path     Dotted key, e.g. connectivity.avatar.
	 * @param mixed  $fallback Value when the path is absent.
	 * @return mixed
	 */
	public function get( string $path, $fallback = null );

	/**
	 * Whether a dotted path exists.
	 *
	 * @param string $path Dotted key.
	 */
	public function has( string $path ): bool;
}
