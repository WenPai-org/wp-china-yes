<?php
/**
 * Logger error_log line carries a scrubbed exception summary.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Core\Logger;
use WenPai\ChinaYes\Core\ModuleRegistry;

/**
 * Class, message, and relative file:line are appended; secrets are masked.
 */
class LoggerExceptionContextTest extends TestCase {

	/**
	 * Temp file the error_log sink writes to.
	 *
	 * @var string
	 */
	private string $log_file = '';

	/**
	 * Previous error_log ini value.
	 *
	 * @var string|false
	 */
	private $previous = false;

	/**
	 * Point error_log at a temp file.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$file = tempnam( sys_get_temp_dir(), 'wpcy-logger-' );
		$this->assertIsString( $file );
		$this->log_file = $file;
		// phpcs:ignore WordPress.PHP.IniSet.Risky -- capture Logger error_log sink.
		$this->previous = ini_set( 'error_log', $this->log_file );
	}

	/**
	 * Restore error_log and remove the temp file.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( is_string( $this->previous ) ) {
			// phpcs:ignore WordPress.PHP.IniSet.Risky -- restore error_log sink.
			ini_set( 'error_log', $this->previous );
		} else {
			ini_restore( 'error_log' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_ini_restore -- restore error_log sink.
		}
		if ( file_exists( $this->log_file ) ) {
			unlink( $this->log_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- temp log file.
		}
		parent::tearDown();
	}

	/**
	 * Class name, message, and file:line relative to the plugin root appear; no trace.
	 */
	public function test_throwable_class_message_and_relative_location_are_logged() {
		$error = new RuntimeException( 'boom' );
		$line  = __LINE__ - 1;

		( new Logger() )->error( 'Module register() failed: demo', array( 'exception' => $error ) );
		$logged = $this->logged();

		$this->assertStringContainsString(
			'WPCY.error: Module register() failed: demo [RuntimeException: boom @ tests/Unit/Core/LoggerExceptionContextTest.php:' . $line . ']',
			$logged
		);
		$this->assertStringNotContainsString( dirname( __DIR__, 3 ), $logged );
		$this->assertStringNotContainsString( '#0 ', $logged );
	}

	/**
	 * A throwable raised outside the plugin root logs only the file basename,
	 * and an anonymous class name does not leak its NUL-separated path.
	 */
	public function test_file_outside_plugin_root_logs_basename_only() {
		$error = new class( 'outside' ) extends RuntimeException {
			/**
			 * Pretend the throw site is another plugin.
			 *
			 * @param string $message Message.
			 */
			public function __construct( string $message ) {
				parent::__construct( $message );
				$this->file = '/srv/site/wp-content/plugins/other-plugin/includes/class-thing.php';
				$this->line = 42;
			}
		};

		( new Logger() )->error( 'Module register() failed: demo', array( 'exception' => $error ) );
		$logged = $this->logged();

		$this->assertStringContainsString( '[RuntimeException@anonymous: outside @ class-thing.php:42]', $logged );
		$this->assertStringNotContainsString( "\0", $logged );
		$this->assertStringNotContainsString( '/srv/site', $logged );
		$this->assertStringNotContainsString( 'other-plugin/', $logged );
	}

	/**
	 * Tokens, keys, passwords, emails, IPs, URL queries, and long hex are masked.
	 */
	public function test_secret_and_personal_data_in_message_are_scrubbed() {
		$message = 'auth failed for admin@example.com from 203.0.113.9 token=abc123SECRET password: hunter2 '
			. 'Authorization: Bearer eyJhbGciOiJIUzI1NiJ9.payload.sig '
			. 'url https://api.example.com/v1/bind?site_key=XYZ&nonce=1 '
			. 'hash 0123456789abcdef0123456789abcdef01234567';

		( new Logger() )->error( 'Binding failed.', array( 'exception' => new RuntimeException( $message ) ) );
		$logged = $this->logged();

		$this->assertStringContainsString( 'RuntimeException: auth failed for ***@*** from ***', $logged );
		$this->assertStringContainsString( 'token=***', $logged );
		$this->assertStringContainsString( 'password: ***', $logged );
		$this->assertStringContainsString( 'Bearer ***', $logged );
		$this->assertStringContainsString( 'https://api.example.com/v1/bind ', $logged );
		foreach ( array( 'admin@example.com', '203.0.113.9', 'abc123SECRET', 'hunter2', 'eyJhbGci', 'site_key=XYZ', 'nonce=1', '0123456789abcdef0123456789abcdef' ) as $secret ) {
			$this->assertStringNotContainsString( $secret, $logged );
		}
	}

	/**
	 * Long messages are capped at 300 bytes plus an ellipsis.
	 */
	public function test_long_exception_message_is_capped() {
		( new Logger() )->error( 'Long.', array( 'exception' => new RuntimeException( str_repeat( 'x ', 600 ) ) ) );
		$logged = $this->logged();

		$this->assertMatchesRegularExpression( '/RuntimeException: (x ){150}…/', $logged );
		$this->assertStringNotContainsString( str_repeat( 'x ', 151 ), $logged );
	}

	/**
	 * Without an exception the line is unchanged.
	 */
	public function test_line_without_exception_is_unchanged() {
		( new Logger() )->log( 'warning', 'Unknown or invalid config key discarded.', array( 'path' => 'announcements' ) );

		$this->assertSame( 1, preg_match( '/WPCY\.warning: Unknown or invalid config key discarded\.$/m', $this->logged() ) );
		$this->assertStringNotContainsString( 'discarded. [', $this->logged() );
	}

	/**
	 * Context records keep their existing shape (class + message only).
	 */
	public function test_records_context_shape_is_unchanged() {
		$logger = new Logger( 'error', static function (): void {} );
		$logger->error( 'x', array( 'exception' => new RuntimeException( 'boom' ) ) );

		$this->assertSame(
			array(
				'class'   => RuntimeException::class,
				'message' => 'boom',
			),
			$logger->records()[0]['context']['exception']
		);
	}

	/**
	 * ModuleRegistry's register() catch now logs why the module failed.
	 */
	public function test_module_register_failure_line_names_the_exception() {
		$registry = new ModuleRegistry( new ArrayConfig(), new Environment( Environment::ADMIN ), new Logger() );
		$registry->add( new ThrowingModule( 'demo' ) );
		$registry->boot( Environment::ADMIN );

		$this->assertMatchesRegularExpression(
			'#WPCY\.error: Module register\(\) failed: demo \[RuntimeException: boom from demo @ tests/Unit/Core/ThrowingModule\.php:\d+\]#',
			$this->logged()
		);
	}

	/**
	 * Review-164 ①: CR / LF in the message cannot forge a second log line.
	 */
	public function test_newlines_cannot_forge_a_log_line() {
		$this->log_exception( "first line\nWPCY.error: forged line\r\nanother" );
		$logged = $this->logged();

		$this->assertSame( 1, substr_count( $logged, "\n" ), $logged );
		$this->assertStringNotContainsString( "\r", $logged );
		$this->assertSame( 0, preg_match( '/^WPCY\.error: forged line/m', $logged ) );
		$this->assertStringContainsString( '[RuntimeException: first line WPCY.error: forged line another @ ', $logged );
	}

	/**
	 * Review-164 ②: a NUL byte does not cut the line; file:line and "]" stay.
	 */
	public function test_nul_byte_does_not_truncate_the_line() {
		$this->log_exception( "before\0after" );
		$logged = $this->logged();

		$this->assertStringNotContainsString( "\0", $logged );
		$this->assertSame( 1, preg_match( '/\[RuntimeException: before after @ tests\/Unit\/Core\/LoggerExceptionContextTest\.php:\d+\]$/m', $logged ), $logged );
	}

	/**
	 * Review-164 ③ (Unix): absolute paths in the message keep only the file name.
	 */
	public function test_unix_absolute_path_in_message_is_hidden() {
		$this->log_exception( 'include(/home/alice/public_html/wp-content/...)' );
		$this->log_exception( 'include(/home/alice/public_html/wp-content/plugins/other/thing.php): failed to open stream' );
		$logged = $this->logged();

		$this->assertStringNotContainsString( '/home/alice', $logged );
		$this->assertStringNotContainsString( 'public_html', $logged );
		$this->assertStringContainsString( 'include([path]/...)', $logged );
		$this->assertStringContainsString( 'include([path]/thing.php): failed to open stream', $logged );
	}

	/**
	 * Review-164 ③ (Windows): drive and UNC paths keep only the file name.
	 */
	public function test_windows_absolute_path_in_message_is_hidden() {
		$this->log_exception( 'C:\inetpub\wwwroot\wp-config.php' );
		$this->log_exception( 'open \\\\fileserver\share\site\wp-config.php failed' );
		$logged = $this->logged();

		$this->assertStringNotContainsString( 'inetpub', $logged );
		$this->assertStringNotContainsString( 'fileserver', $logged );
		$this->assertStringContainsString( '[RuntimeException: [path]/wp-config.php @ ', $logged );
		$this->assertStringContainsString( 'open [path]/wp-config.php failed', $logged );
	}

	/**
	 * Review-164 ③: a path inside the plugin becomes relative to the plugin root.
	 */
	public function test_plugin_path_in_message_is_relative() {
		$root = dirname( __DIR__, 3 );
		$this->log_exception( 'require(' . $root . '/src/Core/Missing.php): failed' );
		$logged = $this->logged();

		$this->assertStringNotContainsString( $root, $logged );
		$this->assertStringContainsString( 'require(src/Core/Missing.php): failed', $logged );
	}

	/**
	 * Review-164 ④: JSON-style keys and values are masked.
	 */
	public function test_json_secrets_are_masked() {
		$this->log_exception( '{"api_key":"sk_live_abcdef","secret":"s3cr3t"}' );
		$logged = $this->logged();

		$this->assertStringNotContainsString( 'sk_live_abcdef', $logged );
		$this->assertStringNotContainsString( 's3cr3t', $logged );
		$this->assertStringContainsString( '{"api_key":"***","secret":"***"}', $logged );
	}

	/**
	 * Review-164 ⑤: Authorization Basic and Bearer credentials are masked.
	 */
	public function test_basic_and_bearer_credentials_are_masked() {
		$this->log_exception( 'Authorization: Basic dXNlcjpwYXNz' );
		$this->log_exception( 'Authorization: Bearer abc.def-ghi' );
		$logged = $this->logged();

		$this->assertStringNotContainsString( 'dXNlcjpwYXNz', $logged );
		$this->assertStringNotContainsString( 'abc.def-ghi', $logged );
		$this->assertStringContainsString( 'Authorization: Basic *** @ ', $logged );
		$this->assertStringContainsString( 'Authorization: Bearer *** @ ', $logged );
	}

	/**
	 * Review-164 ⑥: the query string of a relative URL is removed.
	 */
	public function test_relative_url_query_is_removed() {
		$this->log_exception( '/wp-login.php?redirect_to=x&sig=abc123' );
		$logged = $this->logged();

		$this->assertStringNotContainsString( 'sig=', $logged );
		$this->assertStringNotContainsString( 'abc123', $logged );
		$this->assertStringNotContainsString( 'redirect_to', $logged );
		$this->assertStringContainsString( '[RuntimeException: /wp-login.php @ ', $logged );
	}

	/**
	 * Review-164 ⑦: quoted SQL literals become '***'.
	 */
	public function test_sql_literals_are_masked() {
		$this->log_exception( 'WordPress database error for query SELECT * FROM wp_users WHERE user_login = "alice" AND phone = "13800138000"' );
		$logged = $this->logged();

		$this->assertStringNotContainsString( 'alice', $logged );
		$this->assertStringNotContainsString( '13800138000', $logged );
		$this->assertStringContainsString( "WHERE user_login = '***' AND phone = '***'", $logged );
	}

	/**
	 * Review-164 ⑧: without mbstring the cut keeps UTF-8 valid.
	 */
	public function test_cut_without_mbstring_keeps_utf8_valid() {
		$cut  = new \ReflectionMethod( Logger::class, 'utf8_cut' );
		$text = str_repeat( '中', 150 );
		$cut->setAccessible( true );

		foreach ( array( 300, 301, 302 ) as $max ) {
			$out = $cut->invoke( new Logger(), $text, $max, false );
			$this->assertTrue( mb_check_encoding( $out, 'UTF-8' ), (string) $max );
			$this->assertSame( 300, strlen( $out ), (string) $max );
		}
		$this->assertSame( 'a', $cut->invoke( new Logger(), 'a中', 2, false ) );
		$this->assertSame( $cut->invoke( new Logger(), $text, 301, true ), $cut->invoke( new Logger(), $text, 301, false ) );

		$this->log_exception( $text );
		$this->assertTrue( mb_check_encoding( $this->logged(), 'UTF-8' ) );
	}

	/**
	 * Masking happens before the cap: a secret across the 300-byte boundary
	 * leaves no fragment behind.
	 */
	public function test_secret_across_the_cap_leaves_no_fragment() {
		$tail = str_repeat( ' tail', 20 );
		$this->log_exception( str_repeat( 'x', 290 ) . ' token=SECRETVALUE1234567890' . $tail );
		$this->log_exception( str_repeat( 'y', 285 ) . ' Bearer abcdefghijklmnopqrstuvwxyz' . $tail );
		$this->log_exception( str_repeat( 'z', 280 ) . ' ' . str_repeat( 'deadbeef', 6 ) . $tail );
		$logged = $this->logged();

		$this->assertStringNotContainsString( 'SECR', $logged );
		$this->assertStringNotContainsString( 'abcdefgh', $logged );
		$this->assertStringNotContainsString( 'dead', $logged );
		$this->assertSame( 3, substr_count( $logged, '…' ) );
	}

	/**
	 * Log one RuntimeException with $message through Logger::error().
	 *
	 * @param string $message Exception message.
	 */
	private function log_exception( string $message ): void {
		( new Logger() )->error( 'Module register() failed: demo', array( 'exception' => new RuntimeException( $message ) ) );
	}

	/**
	 * Captured error_log output.
	 */
	private function logged(): string {
		return (string) file_get_contents( $this->log_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local temp log, not a remote URL.
	}
}
