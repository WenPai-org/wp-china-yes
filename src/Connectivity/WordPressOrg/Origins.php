<?php
/**
 * WordPress.org mirror origins. Metadata and packages are different hosts.
 *
 * Values match 3.9.3 Super::MIRROR_* constants.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Connectivity\WordPressOrg;

/**
 * Origin URLs, probe path, size floor, and cache TTLs.
 */
final class Origins {

	/**
	 * Metadata mirror (api.wordpress.org).
	 *
	 * @var string
	 */
	public const API_ORIGIN = 'https://api.wenpai.net';

	/**
	 * Package / translation mirror (downloads.wordpress.org).
	 *
	 * @var string
	 */
	public const PACKAGE_ORIGIN = 'https://downloads.wenpai.net';

	/**
	 * Real plugin zip path used for probes. Must hit the package host.
	 *
	 * @var string
	 */
	public const PROBE_PATH = '/plugin/classic-editor.zip';

	/**
	 * Smallest plausible package size in bytes.
	 *
	 * @var int
	 */
	public const MIN_PACKAGE_BYTES = 1024;

	/**
	 * Cache TTL when the mirror is up (1 hour).
	 *
	 * @var int
	 */
	public const UP_TTL = 3600;

	/**
	 * Cache TTL when the mirror is down (10 minutes).
	 *
	 * @var int
	 */
	public const DOWN_TTL = 600;

	/**
	 * Probe HTTP timeout in seconds.
	 *
	 * @var int
	 */
	public const PROBE_TIMEOUT = 3;

	/**
	 * Transient key for package-mirror usability.
	 *
	 * @var string
	 */
	public const STATE_KEY = 'wpcy_wporg_mirror_state';

	/**
	 * Upstream metadata host.
	 *
	 * @var string
	 */
	public const UPSTREAM_API_HOST = 'api.wordpress.org';

	/**
	 * Upstream package host.
	 *
	 * @var string
	 */
	public const UPSTREAM_PACKAGE_HOST = 'downloads.wordpress.org';
}
