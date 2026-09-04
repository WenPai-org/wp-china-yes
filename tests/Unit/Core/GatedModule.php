<?php
/**
 * Conditional test module.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Core;

use WenPai\ChinaYes\Core\ConditionalModule;
use WenPai\ChinaYes\Core\Config;
use WenPai\ChinaYes\Core\Environment;

/**
 * Enabled flag is fixed at construction.
 */
final class GatedModule implements ConditionalModule {

	/**
	 * Module id.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Whether enabled() returns true.
	 *
	 * @var bool
	 */
	private $enabled;

	/**
	 * Shared register log.
	 *
	 * @var list<string>
	 */
	private $order;

	/**
	 * Create a gated module.
	 *
	 * @param string   $id      Module id.
	 * @param bool     $enabled Whether enabled() returns true.
	 * @param string[] $order   Shared register log.
	 */
	public function __construct( string $id, bool $enabled, array &$order ) {
		$this->id      = $id;
		$this->enabled = $enabled;
		$this->order   =& $order;
	}

	/**
	 * Module id.
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * Fixed enabled flag.
	 *
	 * @param Config      $config      Unused.
	 * @param Environment $environment Unused.
	 */
	public function enabled( Config $config, Environment $environment ): bool {
		unset( $config, $environment );
		return $this->enabled;
	}

	/**
	 * Append id to the shared log.
	 */
	public function register(): void {
		$this->order[] = $this->id;
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
