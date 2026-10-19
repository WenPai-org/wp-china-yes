<?php
/**
 * In-memory bags for SiteBinding unit tests.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Services\SiteBinding;

/**
 * Process-wide HTTP / filter bags. Reset in each test setUp().
 */
final class BindingStore {

	/**
	 * Filter override for wpcy_services_api. Empty string means no outbound.
	 *
	 * @var string
	 */
	public static $api = '';

	/**
	 * Recorded wp_remote_post calls.
	 *
	 * @var array<int, array{url: string, args: array<string, mixed>}>
	 */
	public static $requests = array();

	/**
	 * Queued HTTP responses, FIFO. Each is array{code: int, body: string}|WP_Error.
	 *
	 * @var array<int, mixed>
	 */
	public static $responses = array();

	/**
	 * Site URL returned by site_url().
	 *
	 * @var string
	 */
	public static $site_url = 'https://example.test';

	/**
	 * Concatenated logger sink (level + message + json context).
	 *
	 * @var string
	 */
	public static $log_sink = '';

	/**
	 * Scheduled single events.
	 *
	 * @var array<int, array{timestamp: int, hook: string, args: array<int, mixed>}>
	 */
	public static $cron = array();

	/**
	 * Clear bags between tests.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$api       = '';
		self::$requests  = array();
		self::$responses = array();
		self::$site_url  = 'https://example.test';
		self::$log_sink  = '';
		self::$cron      = array();
	}
}
