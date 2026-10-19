<?php
/**
 * Test module that appends its id to a shared log on register().
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Core;

use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Core\Module;

/**
 * Records register() order.
 */
final class RecordingModule implements Module {

	/**
	 * Module id.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Module ids that must register first.
	 *
	 * @var list<string>
	 */
	private $dependencies;

	/**
	 * Scenes this module may run in.
	 *
	 * @var list<string>
	 */
	private $contexts;

	/**
	 * Shared register log.
	 *
	 * @var list<string>
	 */
	private $order;

	/**
	 * Create a recording module.
	 *
	 * @param string   $id           Module id.
	 * @param string[] $dependencies Module ids that must register first.
	 * @param string[] $contexts     Scenes.
	 * @param string[] $order        Shared register log.
	 */
	public function __construct( string $id, array $dependencies, array $contexts, array &$order ) {
		$this->id           = $id;
		$this->dependencies = $dependencies;
		$this->contexts     = $contexts;
		$this->order        =& $order;
	}

	/**
	 * Module id.
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * Append id to the shared log.
	 */
	public function register(): void {
		$this->order[] = $this->id;
	}

	/**
	 * Module ids that must register first.
	 *
	 * @return list<string>
	 */
	public function dependencies(): array {
		return $this->dependencies;
	}

	/**
	 * Scenes this module may run in.
	 *
	 * @return list<string>
	 */
	public function contexts(): array {
		return $this->contexts;
	}

	/**
	 * Factory for a module that runs in every scene.
	 *
	 * @param string   $id    Module id.
	 * @param string[] $order Shared register log.
	 */
	public static function everywhere( string $id, array &$order ): self {
		return new self( $id, array(), Environment::CONTEXTS, $order );
	}
}
