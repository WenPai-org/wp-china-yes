<?php
/**
 * Test module whose register() throws.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Core;

use RuntimeException;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Core\Module;

/**
 * Isolates a register() failure.
 */
final class ThrowingModule implements Module {

	/**
	 * Module id.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Create a throwing module.
	 *
	 * @param string $id Module id.
	 */
	public function __construct( string $id ) {
		$this->id = $id;
	}

	/**
	 * Module id.
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * Always throws.
	 *
	 * @throws RuntimeException Always.
	 */
	public function register(): void {
		throw new RuntimeException( sprintf( 'boom from %s', $this->id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
	}

	/**
	 * No dependencies.
	 *
	 * @return list<string>
	 */
	public function dependencies(): array {
		return array();
	}

	/**
	 * Every scene.
	 *
	 * @return list<string>
	 */
	public function contexts(): array {
		return Environment::CONTEXTS;
	}
}
