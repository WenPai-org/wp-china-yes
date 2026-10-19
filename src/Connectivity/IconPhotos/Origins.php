<?php
/**
 * MotuCloud isomorphic origins: WP core icon API and media-library photo search.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Connectivity\IconPhotos;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Upstream hosts whose origin is swapped onto connectivity.icon_photos.mirrored_base.
 */
final class Origins {

	/**
	 * Diagnostics / RouteGroups target id (stable; host is config-driven).
	 *
	 * @since 4.0.0
	 */
	public const TARGET_ID = 'motucloud';

	/**
	 * Mirror request timeout in seconds.
	 *
	 * @since 4.0.0
	 */
	public const TIMEOUT = 10;

	/**
	 * Native API timeout in seconds.
	 *
	 * @since 4.0.0
	 */
	public const NATIVE_TIMEOUT = 5;

	/**
	 * Down-host TTL (seconds), same window as WordPress.org mirrors.
	 *
	 * @since 4.0.0
	 */
	public const DOWN_TTL = 600;

	/**
	 * Core Openverse search used when the native API fails.
	 *
	 * @since 4.0.0
	 */
	public const CORE_SEARCH_ORIGIN = 'https://api.openverse.org';

	/**
	 * Openverse list/search path (core source).
	 *
	 * @since 4.0.0
	 */
	public const CORE_IMAGES_PATH = '/v1/images/';

	/**
	 * Native MotuCloud list path (relative to native_api_base).
	 *
	 * @since 4.0.0
	 */
	public const NATIVE_LIST_PATH = '/v1/list';

	/**
	 * Native MotuCloud search path (relative to native_api_base).
	 *
	 * @since 4.0.0
	 */
	public const NATIVE_SEARCH_PATH = '/v1/search';

	/**
	 * Probe path appended to mirrored_base.
	 *
	 * @since 4.0.0
	 */
	public const PROBE_PATH = '/';

	/**
	 * Rules for the isomorphic mirror track. Path is kept; only the origin changes.
	 *
	 * @since 4.0.0
	 *
	 * @return list<array{host: string, path_prefix: string, path_exclude_prefix: string}>
	 */
	public static function mirror_rules(): array {
		return array(
			array(
				'host'                => 'api.wordpress.org',
				'path_prefix'         => '/core/icons/',
				'path_exclude_prefix' => '',
			),
			array(
				'host'                => 's.w.org',
				'path_prefix'         => '/images/core/',
				'path_exclude_prefix' => '/images/core/emoji',
			),
			array(
				'host'                => 'api.openverse.org',
				'path_prefix'         => '/',
				'path_exclude_prefix' => '',
			),
			array(
				'host'                => 'public-api.wordpress.com',
				'path_prefix'         => '/wpcom/v2/external-media',
				'path_exclude_prefix' => '',
			),
			array(
				'host'                => 'wordpress.org',
				'path_prefix'         => '/photos/',
				'path_exclude_prefix' => '',
			),
		);
	}
}
