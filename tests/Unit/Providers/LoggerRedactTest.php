<?php
/**
 * Logger redacts license_key / api_key keys and api_key= query values.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Providers;

use PHPUnit\Framework\TestCase;
use WenPai\ChinaYes\Core\Logger;

require_once __DIR__ . '/wp-providers-stubs.php';

/**
 * P5 / P6 log redaction.
 */
class LoggerRedactTest extends TestCase {

	/**
	 * Query value api_key=abc in a message becomes api_key=***.
	 */
	public function test_message_api_key_query_is_redacted() {
		$logger = new Logger( 'debug', static function () {} );
		$logger->log( 'warning', 'GET https://mall.example/wc-api/wc-am-api/?api_key=abc&instance=1' );
		$records = $logger->records();
		$this->assertCount( 1, $records );
		$this->assertStringContainsString( 'api_key=***', $records[0]['message'] );
		$this->assertStringNotContainsString( 'api_key=abc', $records[0]['message'] );
	}

	/**
	 * Context license_key / api_key become ***.
	 */
	public function test_context_keys_are_stars() {
		$logger = new Logger( 'debug', static function () {} );
		$logger->log(
			'warning',
			'connect',
			array(
				'license_key' => 'WXD-SECRET',
				'api_key'     => 'abc',
			)
		);
		$ctx = $logger->records()[0]['context'];
		$this->assertSame( '***', $ctx['license_key'] );
		$this->assertSame( '***', $ctx['api_key'] );
	}

	/**
	 * Nested string with api_key= is redacted.
	 */
	public function test_nested_string_query_is_redacted() {
		$logger = new Logger( 'debug', static function () {} );
		$logger->log(
			'warning',
			'http',
			array(
				'detail' => 'retry api_key=abc failed',
			)
		);
		$this->assertSame( 'retry api_key=*** failed', $logger->records()[0]['context']['detail'] );
	}
}
