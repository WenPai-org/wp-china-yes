<?php
/**
 * Kernel module contract.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Core;

/**
 * A kernel module. Hooks are registered only in register().
 *
 * Optional dependencies() / contexts() follow docs/dev/module-authoring.md.
 * Registry defaults: no dependencies; scenes admin and frontend.
 */
interface Module {

	/**
	 * Dotted module id, e.g. connectivity.public_assets.
	 */
	public function id(): string;

	/**
	 * Register WordPress hooks. Must not run in the constructor.
	 */
	public function register(): void;
}
