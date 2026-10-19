<?php
/**
 * Module that only implements the §5.3 methods.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Core;

use WenPai\ChinaYes\Core\Module;

/**
 * No dependencies() / contexts(); registry must apply defaults.
 */
final class BareModule implements Module {

	/**
	 * Shared register log.
	 *
	 * @var list<string>
	 */
	private $order;

	/**
	 * Create a bare module.
	 *
	 * @param string[] $order Shared register log.
	 */
	public function __construct( array &$order ) {
		$this->order =& $order;
	}

	/**
	 * Module id.
	 */
	public function id(): string {
		return 'bare';
	}

	/**
	 * Append id to the shared log.
	 */
	public function register(): void {
		$this->order[] = 'bare';
	}
}
