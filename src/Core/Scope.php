<?php
/**
 * Site vs network scope. Does not decide report cardinality.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Core;

use InvalidArgumentException;

/**
 * Minimal single-site / network scope. Report-per-network vs per-site is pending.
 */
final class Scope {

	public const SITE    = 'site';
	public const NETWORK = 'network';

	/**
	 * Site or network.
	 *
	 * @var string
	 */
	private string $level;

	/**
	 * Create a scope.
	 *
	 * @param string $level site or network.
	 *
	 * @throws InvalidArgumentException If $level is unknown.
	 */
	public function __construct( string $level = self::SITE ) {
		if ( self::SITE !== $level && self::NETWORK !== $level ) {
			throw new InvalidArgumentException( sprintf( 'Unknown scope: %s', $level ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
		}

		$this->level = $level;
	}

	/**
	 * Detect site vs network admin. Frontend of a subsite is site scope.
	 */
	public static function detect(): self {
		if ( function_exists( 'is_multisite' ) && is_multisite()
			&& function_exists( 'is_network_admin' ) && is_network_admin()
		) {
			return new self( self::NETWORK );
		}

		return new self( self::SITE );
	}

	/**
	 * Site or network level id.
	 */
	public function level(): string {
		return $this->level;
	}

	/**
	 * Whether this is network scope.
	 */
	public function is_network(): bool {
		return self::NETWORK === $this->level;
	}

	/**
	 * Whether this is single-site (or subsite) scope.
	 */
	public function is_site(): bool {
		return self::SITE === $this->level;
	}
}
