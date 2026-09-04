<?php
/**
 * Conditional test module whose enabled() throws.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Core;

use RuntimeException;
use WenPai\ChinaYes\Core\ConditionalModule;
use WenPai\ChinaYes\Core\Config;
use WenPai\ChinaYes\Core\Environment;

/**
 * Isolates an enabled() failure.
 */
final class ThrowingEnabledModule implements ConditionalModule {

	/**
	 * Module id.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Create a throwing-enabled module.
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
	 * @param Config      $config      Unused.
	 * @param Environment $environment Unused.
	 *
	 * @throws RuntimeException Always.
	 */
	public function enabled( Config $config, Environment $environment ): bool {
		unset( $config, $environment );
		throw new RuntimeException( sprintf( 'enabled boom from %s', $this->id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
	}

	/**
	 * Must not run when enabled() throws.
	 */
	public function register(): void {
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
