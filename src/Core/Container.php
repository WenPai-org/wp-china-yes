<?php
/**
 * Minimal service container. Modules resolve collaborators by id, not new.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Core;

use Closure;
use InvalidArgumentException;

/**
 * Id-based locator. Closures are factory entries and resolve once.
 */
final class Container {

	/**
	 * Bound instances and factories, keyed by id.
	 *
	 * @var array<string, mixed>
	 */
	private array $entries = array();

	/**
	 * Bind an instance or a factory closure.
	 *
	 * @param string $id    Entry id.
	 * @param mixed  $entry Instance or `function (Container $c): mixed`.
	 */
	public function set( string $id, $entry ): void {
		$this->entries[ $id ] = $entry;
	}

	/**
	 * Fetch a bound entry. Factory closures are invoked once and cached.
	 *
	 * @param string $id Entry id.
	 * @return mixed
	 *
	 * @throws InvalidArgumentException If $id is not bound.
	 */
	public function get( string $id ) {
		if ( ! $this->has( $id ) ) {
			throw new InvalidArgumentException( sprintf( 'Container has no entry: %s', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
		}

		$entry = $this->entries[ $id ];
		if ( $entry instanceof Closure ) {
			$entry                = $entry( $this );
			$this->entries[ $id ] = $entry;
		}

		return $entry;
	}

	/**
	 * Whether an id is bound.
	 *
	 * @param string $id Entry id.
	 */
	public function has( string $id ): bool {
		return array_key_exists( $id, $this->entries );
	}
}
