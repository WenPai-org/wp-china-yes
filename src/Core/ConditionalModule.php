<?php
/**
 * Module that may opt out of register() for a request.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Core;

/**
 * Optional module gated by config and environment.
 */
interface ConditionalModule extends Module {

	/**
	 * Whether this module should register in the current request.
	 *
	 * @param Config      $config      Config read model.
	 * @param Environment $environment Current request scene.
	 */
	public function enabled( Config $config, Environment $environment ): bool;
}
