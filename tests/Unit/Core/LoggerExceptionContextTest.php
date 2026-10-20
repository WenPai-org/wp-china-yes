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
	 * Review-164 c9767d7 2a: a 500 KB base64-like message is scrubbed in
	 * bounded time (was 72 s through the old quadratic look-ahead rule).
	 */
	public function test_500kb_base64_like_message_is_scrubbed_quickly() {
		$inputs = array(
			str_repeat( 'Ab+_', 128000 ),
			str_repeat( 'a+', 256000 ),
			str_repeat( 'Ab+/', 128000 ),
			str_repeat( 'Ab+_', 127999 ) . 'Ab1=',
		);
		foreach ( $inputs as $input ) {
			$start = microtime( true );
			$this->log_exception( $input );
			$elapsed = microtime( true ) - $start;
			$this->assertLessThan( 0.5, $elapsed, sprintf( '%d bytes of %s took %.3f s', strlen( $input ), substr( $input, 0, 4 ), $elapsed ) );
		}

		$lines = explode( "\n", trim( $this->logged() ) );
		$this->assertCount( 4, $lines );
		foreach ( $lines as $line ) {
			$this->assertLessThan( 500, strlen( $line ), $line );
			$this->assertStringContainsString( '… @ tests/Unit/Core/LoggerExceptionContextTest.php:', $line );
		}
	}

	/**
	 * Review-164 c9767d7 2a: the base64-like rule itself is linear (200 KB
	 * through mask() alone, no length cap in front of it).
	 */
	public function test_base64_like_rule_is_linear_without_the_cap() {
		$mask = new \ReflectionMethod( Logger::class, 'mask' );
		$mask->setAccessible( true );

		$start   = microtime( true );
		$out     = $mask->invoke( new Logger(), str_repeat( 'Ab+_', 50000 ) );
		$elapsed = microtime( true ) - $start;

		$this->assertLessThan( 0.5, $elapsed, sprintf( '200 KB took %.3f s', $elapsed ) );
		$this->assertSame( str_repeat( 'Ab+_', 50000 ), $out );

		// Path rules with many-word directories stay linear too.
		foreach ( array( str_repeat( '/a b c d e f g ', 14000 ), '/' . str_repeat( 'a ', 100000 ), 'C:\\' . str_repeat( 'a b ', 50000 ), str_repeat( 'C:\\a b ', 25000 ) ) as $input ) {
			$start = microtime( true );
			$mask->invoke( new Logger(), $input );
			$elapsed = microtime( true ) - $start;
			$this->assertLessThan( 0.5, $elapsed, sprintf( '%d bytes of %s took %.3f s', strlen( $input ), substr( $input, 0, 6 ), $elapsed ) );
		}
	}

	/**
	 * The linear base64-like rule still masks runs with a digit and keeps
	 * long identifiers without one.
	 */
	public function test_base64_like_run_with_digit_is_still_masked() {
		$this->log_exception( 'key material QWxhZGRpbjpvcGVuIHNlc2FtZQ1234abcdEFGH== end' );
		$this->log_exception( 'class WenPaiChinaYesTelemetryReportBuilderFactory missing' );
		$logged = $this->logged();

		$this->assertStringContainsString( 'key material *** end', $logged );
		$this->assertStringNotContainsString( 'QWxhZGRp', $logged );
		$this->assertStringContainsString( 'class WenPaiChinaYesTelemetryReportBuilderFactory missing', $logged );
	}

	/**
	 * Review-164 c9767d7 2b: Unicode line / paragraph separators, NEL, and
	 * bidi controls become spaces, so no reader splits or reorders the line.
	 */
	public function test_unicode_separators_and_bidi_controls_become_spaces() {
		$this->log_exception( "a\u{2028}WPCY.error: forged\u{2029}b\u{0085}c\u{202E}d\u{2066}e\u{200F}f\u{061C}g\u{2069}h" );
		$logged = $this->logged();

		foreach ( array( "\u{2028}", "\u{2029}", "\u{0085}", "\u{202E}", "\u{2066}", "\u{200F}", "\u{061C}", "\u{2069}" ) as $char ) {
			$this->assertStringNotContainsString( $char, $logged, bin2hex( $char ) );
		}
		$this->assertCount( 1, (array) preg_split( '/\R/u', trim( $logged ) ) );
		$this->assertStringContainsString( '[RuntimeException: a WPCY.error: forged b c d e f g h @ ', $logged );
	}

	/**
	 * Review-164 c9767d7 2b: invalid UTF-8 falls back to byte-wise stripping,
	 * which also catches the UTF-8 encoded separators.
	 */
	public function test_invalid_utf8_still_strips_unicode_separators() {
		$this->log_exception( "bad\xFF a\u{2028}b\u{202E}c\nd" );
		$logged = $this->logged();

		$this->assertStringNotContainsString( "\u{2028}", $logged );
		$this->assertStringNotContainsString( "\u{202E}", $logged );
		$this->assertSame( 1, substr_count( $logged, "\n" ) );
		$this->assertStringContainsString( ' a b c d @ ', $logged );
	}

	/**
	 * Review-164 c9767d7 2c (Windows): directories with spaces do not leak.
	 */
	public function test_windows_path_with_spaces_is_hidden() {
		$this->log_exception( 'C:\Program Files\Acme Corp\alice\wp-config.php' );
		$this->log_exception( 'open C:\Program Files (x86)\Acme\alice smith\cache.json failed' );
		$logged = $this->logged();

		foreach ( array( 'Program Files', 'Acme', 'alice', 'smith', 'x86' ) as $leak ) {
			$this->assertStringNotContainsString( $leak, $logged );
		}
		$this->assertStringContainsString( '[RuntimeException: [path]/wp-config.php @ ', $logged );
		$this->assertStringContainsString( 'open [path]/cache.json failed', $logged );
	}

	/**
	 * Review-164 c9767d7 2c (Unix): directories with spaces do not leak.
	 */
	public function test_unix_path_with_spaces_is_hidden() {
		$this->log_exception( '/home/alice smith/public_html/wp-config.php' );
		$this->log_exception( 'read /Users/alice/Library/Application Support/WPCY/cache.json failed' );
		$logged = $this->logged();

		foreach ( array( 'alice', 'smith', 'public_html', 'Application Support', 'Library' ) as $leak ) {
			$this->assertStringNotContainsString( $leak, $logged );
		}
		$this->assertStringContainsString( '[RuntimeException: [path]/wp-config.php @ ', $logged );
		$this->assertStringContainsString( 'read [path]/cache.json failed', $logged );
	}

	/**
	 * Review-164 c9767d7 (non-blocking): URL-encoded separators are masked.
	 */
	public function test_url_encoded_key_value_is_masked() {
		$this->log_exception( 'form body token%3Dsupersecret and api_key%3dK123xyz password%3Ahunter2' );
		$logged = $this->logged();

		foreach ( array( 'supersecret', 'K123xyz', 'hunter2' ) as $secret ) {
			$this->assertStringNotContainsString( $secret, $logged );
		}
		$this->assertStringContainsString( 'token%3D*** and api_key%3d*** password%3A***', $logged );
	}

	/**
	 * Review-164 c9767d7 (non-blocking): URL userinfo hides the user name too;
	 * the host stays for diagnosis.
	 */
	public function test_url_userinfo_is_masked() {
		$this->log_exception( 'connect https://bob:pa55w0rd@api.example.com/v1 refused' );
		$logged = $this->logged();

		$this->assertStringNotContainsString( 'bob', $logged );
		$this->assertStringNotContainsString( 'pa55w0rd', $logged );
		$this->assertStringContainsString( 'connect https://***@api.example.com/v1 refused', $logged );
	}

	/**
	 * The raw pre-mask cut drops the token it falls into: a shrinking mask
	 * (here a long path) cannot pull half a secret into the 300-byte line.
	 */
	public function test_raw_cut_drops_the_token_it_falls_into() {
		$prefix = '/' . str_repeat( 'dir/', 294 ) . 'file.php ';
		$this->assertSame( 1186, strlen( $prefix ) );
		$this->log_exception( $prefix . str_repeat( 'deadbeef', 5 ) . str_repeat( ' tail', 200 ) );
		$logged = $this->logged();

		$this->assertStringNotContainsString( 'dea', $logged );
		$this->assertStringContainsString( '[RuntimeException: [path]/file.php… @ ', $logged );
	}

	/**
	 * Review-164 f7e09b8: a token longer than 64 bytes across the raw cut is
	 * dropped whole. A compressible prefix (hex run → ***) would otherwise
	 * pull a sub-32-character key prefix into the line.
	 */
	public function test_raw_cut_drops_a_long_token_whole() {
		$token = 'Zm9vYmFy4QWxY2VkTOPSECRETab12cd34' . str_repeat( 'Zm9vYmFy4QWxY2Vk', 6 );
		$this->assertGreaterThan( 64, strlen( $token ) );
		$last = strlen( $token ) - 6;

		// 0-based token start; 1105 leaves the longest (31-character) prefix in f7e09b8.
		foreach ( array( 1105, 1130, 1104, 1150, 1199 ) as $start ) {
			$prefix = substr( str_repeat( 'ab', 600 ), 0, $start - 1 ) . ' ';
			$this->log_exception( $prefix . $token . ' tail' );
		}

		$lines = explode( "\n", trim( $this->logged() ) );
		$this->assertCount( 5, $lines );
		foreach ( $lines as $line ) {
			$this->assertSame( 1, preg_match( '/\[RuntimeException: (.*) @ tests\//', $line, $m ), $line );
			for ( $i = 0; $i <= $last; $i++ ) {
				$this->assertStringNotContainsString( substr( $token, $i, 6 ), $m[1], $line );
			}
			$this->assertSame( '***…', $m[1], $line );
		}
	}

	/**
	 * Review-164 f7e09b8 (non-blocking): a directory name of more than five
	 * words does not leak.
	 */
	public function test_path_with_long_multi_word_directory_is_hidden() {
		$this->log_exception( '/home/alice/My Very Long Folder Name Here Now/secret.txt' );
		$this->log_exception( 'D:\Users\alice\My Very Long Folder Name Here Now\secret.txt' );
		$logged = $this->logged();

		foreach ( array( 'alice', 'Folder', 'Here Now' ) as $leak ) {
			$this->assertStringNotContainsString( $leak, $logged );
		}
		$this->assertSame( 2, substr_count( $logged, '[RuntimeException: [path]/secret.txt @ ' ) );
	}

	/**
	 * A raw message over the cap without any whitespace keeps nothing raw.
	 */
	public function test_raw_cut_without_whitespace_keeps_nothing() {
		$this->log_exception( 'x' . str_repeat( 'Zm9vYmFy4QWxY2Vk', 100 ) );

		$this->assertStringContainsString( '[RuntimeException: … @ tests/', $this->logged() );
	}

	/**
	 * Review-164 28a67ca 1: a long message without ASCII whitespace keeps its
	 * text (Chinese, U+3000, NBSP, compact JSON), not just "…".
	 */
	public function test_long_message_without_ascii_whitespace_keeps_text() {
		$json  = '[' . implode( ',', array_fill( 0, 25, '{"code":"http_request_failed","message":"cURL error 28","data":{"status":503}}' ) ) . ']';
		$cases = array(
			'cjk'   => array( str_repeat( '数据库连接失败请稍后重试', 50 ), '数据库连接失败请稍后重试数据库连接失败' ),
			'u3000' => array( str_repeat( "连接超时\u{3000}timeout\u{3000}", 100 ), "连接超时\u{3000}timeout\u{3000}连接超时" ),
			'nbsp'  => array( str_repeat( "request\u{00A0}timeout\u{00A0}", 150 ), "request\u{00A0}timeout\u{00A0}request" ),
			'json'  => array( $json, '[{"code":"http_request_failed","message":"cURL error 28","data":{"status":503}},' ),
		);
		foreach ( $cases as $case ) {
			$this->assertGreaterThan( 1200, strlen( $case[0] ) );
			$this->log_exception( $case[0] );
		}

		$lines = explode( "\n", trim( $this->logged() ) );
		$this->assertCount( 4, $lines );
		foreach ( array_values( $cases ) as $i => $case ) {
			$this->assertTrue( mb_check_encoding( $lines[ $i ], 'UTF-8' ), (string) $i );
			$this->assertStringContainsString( '[RuntimeException: ' . $case[1], $lines[ $i ] );
			$this->assertStringContainsString( '… @ tests/', $lines[ $i ] );
		}
	}

	/**
	 * Review-164 28a67ca 1: with the new separators a key across the raw cut
	 * still leaves no piece, whatever ends the text before it.
	 */
	public function test_raw_cut_drops_a_long_token_after_unicode_or_json_separators() {
		$token  = 'Zm9vYmFy4QWxY2VkTOPSECRETab12cd34' . str_repeat( 'Zm9vYmFy4QWxY2Vk', 6 );
		$last   = strlen( $token ) - 6;
		$before = array( '，', "\u{3000}", "\u{00A0}", '中', '","api_key":"', ',"token=', '(', ';' );
		foreach ( $before as $separator ) {
			foreach ( array( 1105, 1130, 1199 ) as $start ) {
				$prefix = substr( str_repeat( 'ab', 700 ), 0, $start - strlen( $separator ) ) . $separator;
				$this->log_exception( $prefix . $token . ' tail' );
			}
		}

		$lines = explode( "\n", trim( $this->logged() ) );
		$this->assertCount( 24, $lines );
		foreach ( $lines as $line ) {
			$this->assertSame( 1, preg_match( '/\[RuntimeException: (.*) @ tests\//', $line, $m ), $line );
			for ( $i = 0; $i <= $last; $i++ ) {
				$this->assertStringNotContainsString( substr( $token, $i, 6 ), $m[1], $line );
			}
			$this->assertTrue( mb_check_encoding( $line, 'UTF-8' ), $line );
		}
	}

	/**
	 * Review-164 28a67ca 2: prose with " /word" and a later "x/y" is not taken
	 * for a path.
	 */
	public function test_prose_with_slashes_is_not_taken_for_a_path() {
		$messages = array(
			'see /docs for details about the and/or operator in the manual',
			'error near /token in this very long sentence, answer yes/no',
			'expected 3 /4 parts, got 2/3',
			'use /data or /tmp only; HTTP/1.1 text/html 2026/10/21',
		);
		foreach ( $messages as $message ) {
			$this->log_exception( $message );
		}
		$logged = $this->logged();

		foreach ( $messages as $message ) {
			$this->assertStringContainsString( '[RuntimeException: ' . $message . ' @ ', $logged );
		}
		$this->assertStringNotContainsString( '[path]', $logged );
	}

	/**
	 * Review-164 28a67ca 2: user names with spaces under common roots and on
	 * Windows still do not leak.
	 */
	public function test_user_names_with_spaces_still_hidden() {
		$this->log_exception( 'open /home/alice smith/public_html/wp-config.php failed' );
		$this->log_exception( 'open /Users/alice smith/Sites/wp/wp-config.php failed' );
		$this->log_exception( 'open /var/www/alice smith/wp-config.php failed' );
		$this->log_exception( 'open C:\Users\alice smith\AppData\Local\wp-config.php failed' );
		$this->log_exception( 'open /srv/a/b/c/wp-config.php failed' );
		$logged = $this->logged();

		foreach ( array( 'alice', 'smith', 'public_html', 'Sites', 'AppData', '/srv' ) as $leak ) {
			$this->assertStringNotContainsString( $leak, $logged );
		}
		$this->assertSame( 5, substr_count( $logged, 'open [path]/wp-config.php failed' ) );
	}

	/**
	 * Review-168 6a720ff: a user name with spaces under a root that is not a
	 * common one (/chroot, /u01) does not leak either.
	 */
	public function test_user_names_with_spaces_under_any_root_are_hidden() {
		$this->log_exception( '/chroot/home/alice smith/site/wp-config.php not readable' );
		$this->log_exception( '/u01/app/alice smith/wp/index.php failed' );
		$this->log_exception( 'open /opt2/a b/c d e/f/x.php failed' );
		$logged = $this->logged();

		foreach ( array( 'alice', 'smith', 'chroot', 'u01', 'site/', 'wp/', 'opt2', 'c d e' ) as $leak ) {
			$this->assertStringNotContainsString( $leak, $logged );
		}
		$this->assertStringContainsString( '[RuntimeException: [path]/wp-config.php not readable @ ', $logged );
		$this->assertStringContainsString( '[RuntimeException: [path]/index.php failed @ ', $logged );
		$this->assertStringContainsString( '[RuntimeException: open [path]/x.php failed @ ', $logged );
	}

	/**
	 * A URL user name with a separator byte in it does not leak when the raw
	 * cut falls inside the userinfo (inside the token, or right at "(").
	 */
	public function test_raw_cut_inside_url_userinfo_keeps_no_user_name() {
		$this->log_exception( str_repeat( 'ab', 590 ) . ' https://bob(' . str_repeat( 'y', 40 ) . ':pw@h.example.com/x tail' );
		$this->log_exception( str_repeat( 'ab', 594 ) . ' https://bob(x:pw@h.example.com/x tail' );
		$lines = explode( "\n", trim( $this->logged() ) );

		$this->assertCount( 2, $lines );
		foreach ( $lines as $line ) {
			$this->assertStringNotContainsString( 'bob', $line );
			$this->assertStringContainsString( '[RuntimeException: *** https… @ ', $line );
		}
	}

	/**
	 * Review-168 follow-up 1, set back by review-169-2e15bcf: prose after a
	 * URL path that has a "/" further on cannot be told from a user
	 * directory (the directory may hold spaces, commas and Chinese
	 * punctuation), so it is masked as in e423f4b: more masking, no leak.
	 */
	public function test_prose_after_a_url_path_is_not_taken_for_a_directory() {
		$this->log_exception( '调用 /wp-json/wpcy/v1 失败，请检查 and/or 设置' );
		$this->log_exception( '调用 /wp-json/wpcy/v1 失败。请检查 and/or 设置' );
		$this->log_exception( '请求 /wp-json/wp/v2/posts 返回 404：请检查 网络/代理' );
		$this->log_exception( 'use /api/v1 then foo bar/baz' );
		$logged = $this->logged();

		$this->assertSame( 2, substr_count( $logged, '[RuntimeException: 调用 [path]/or 设置 @ ' ) );
		$this->assertStringContainsString( '[RuntimeException: 请求 [path]/代理 @ ', $logged );
		$this->assertStringContainsString( '[RuntimeException: use [path]/baz @ ', $logged );
	}

	/**
	 * Review-168 follow-up 1: user names with spaces still do not leak, also
	 * at the end of a path and before a file name with spaces.
	 */
	public function test_spaced_user_directories_are_still_hidden() {
		$messages = array(
			'/chroot/home/alice smith/x.php failed'       => '[path]/x.php failed',
			'/u01/app/alice smith/wp/index.php failed'    => '[path]/index.php failed',
			'/home/alice smith/public_html/wp-config.php' => '[path]/wp-config.php',
			'cannot open /home/alice smith/ now'          => 'cannot open [path]/ now',
			'read /home/alice smith/My Notes.txt failed'  => 'read [path]/My Notes.txt failed',
			'read /Users/Alice Smith/Local Sites/a/b.php ok' => 'read [path]/b.php ok',
			'C:\Users\alice smith\AppData\x.log'          => '[path]/x.log',
			'C:\Program Files\Acme Corp\alice\x.dll'      => '[path]/x.dll',
		);
		foreach ( array_keys( $messages ) as $message ) {
			$this->log_exception( $message );
		}
		$logged = $this->logged();

		foreach ( array( 'alice', 'Alice', 'smith', 'Smith', 'chroot', 'u01', 'Program', 'Local Sites' ) as $leak ) {
			$this->assertStringNotContainsString( $leak, $logged );
		}
		foreach ( $messages as $expected ) {
			$this->assertStringContainsString( '[RuntimeException: ' . $expected . ' @ ', $logged );
		}
	}

	/**
	 * Review-168 follow-up 2: a complete URL before the raw cut is kept; a cut
	 * inside a userinfo still hides the user name.
	 */
	public function test_raw_cut_keeps_a_complete_url() {
		$this->log_exception( '无法连接到https://api.wenpai.net，' . str_repeat( '请稍后重试', 100 ) );
		$this->log_exception( 'GET https://api.wenpai.net' . str_repeat( ' x', 600 ) );
		$this->log_exception( str_repeat( 'ab', 590 ) . ' https://bob(' . str_repeat( 'y', 40 ) . ':pw@h.example.com/x tail' );
		$lines = explode( "\n", trim( $this->logged() ) );

		$this->assertCount( 3, $lines );
		$this->assertStringContainsString( '[RuntimeException: 无法连接到https://api.wenpai.net，请稍后重试', $lines[0] );
		$this->assertStringContainsString( '[RuntimeException: GET https://api.wenpai.net x x', $lines[1] );
		$this->assertStringNotContainsString( 'bob', $lines[2] );
	}

	/**
	 * Review-168 follow-up 3a: JSON-escaped base64 (\/ and \\) is masked.
	 */
	public function test_json_escaped_base64_is_masked() {
		$this->log_exception( 'x "Zm9vYmFy4QWx\/Y2VkTOPSECRETab12cd34\/Zm9vYmFy4QWxY2Vk"' );
		$this->log_exception( 'x "Zm9vYmFy4QWx\\\\Y2VkTOPSECRETab12cd34Zm9vYmFy4QWxY2Vk"' );
		$this->log_exception( 'x AbCdEfGh\\' . str_repeat( 'Zm9vYmFy4QWxY2Vk', 6 ) );
		$logged = $this->logged();

		foreach ( array( 'Zm9vYm', 'TOPSECRET', 'AbCdEfGh', 'Y2Vk' ) as $piece ) {
			$this->assertStringNotContainsString( $piece, $logged );
		}
		$this->assertSame( 2, substr_count( $logged, '[RuntimeException: x "***" @ ' ) );
		$this->assertStringContainsString( '[RuntimeException: x *** @ ', $logged );
	}

	/**
	 * Review-168 follow-up 3b: a key with one of ! " ' | ~ $ inside is masked
	 * as a whole, the part before the separator included.
	 */
	public function test_key_with_inner_separator_is_masked_whole() {
		foreach ( array( '!', '"', "'", '|', '~', '$', '^', '%' ) as $sep ) {
			$this->log_exception( 'key material Zm9vYmFy4QWxY2Vk' . $sep . 'Zm9vYmFy4QWxY2VkTOPSECRETab12cd34 end' );
			$this->log_exception( 'hex 0123456789abcdef0123456789abcdef01234567' . $sep . '89abcdef end' );
		}
		$lines = explode( "\n", trim( $this->logged() ) );

		$this->assertCount( 16, $lines );
		foreach ( $lines as $line ) {
			$this->assertStringNotContainsString( 'Zm9vYm', $line );
			$this->assertStringNotContainsString( '89abcdef', $line );
			$this->assertSame( 1, preg_match( '/\[RuntimeException: (?:key material|hex) \*\*\* end @ /', $line ), $line );
		}
	}

	/**
	 * Review-168 follow-up 3b at the raw cut: a key "A'B" cut inside B, or
	 * right at the separator, keeps no part of A.
	 */
	public function test_raw_cut_inside_a_key_with_inner_separator_keeps_no_part() {
		$a = 'Zm9vYmFy4QWxY2VkTOPSECRETab12cd';
		$b = str_repeat( 'Qk9QU0VDUkVUMTIz', 4 );
		foreach ( array( '!', '"', "'" ) as $sep ) {
			foreach ( array( 1110, 1150, 1168, 1169 ) as $start ) {
				$prefix = substr( str_repeat( 'ab', 700 ), 0, $start - 1 ) . ' ';
				$this->log_exception( $prefix . $a . $sep . $b . ' tail' );
			}
		}
		$lines = explode( "\n", trim( $this->logged() ) );

		$this->assertCount( 12, $lines );
		$last = strlen( $a ) - 6;
		foreach ( $lines as $line ) {
			for ( $i = 0; $i <= $last; $i++ ) {
				$this->assertStringNotContainsString( substr( $a, $i, 6 ), $line );
			}
			$this->assertStringNotContainsString( 'Qk9QU0', $line );
		}
	}

	/**
	 * Review-168 follow-up 3c: an apostrophe inside a word (it's) does not
	 * end a literal. Since review-169-2e15bcf a quote that pairs up with
	 * nothing masks the rest from its word on, so the text after doesn't is
	 * masked too (more masking, no leak).
	 */
	public function test_sql_apostrophe_in_a_word_is_not_a_quote() {
		$this->log_exception( "Table 'wp_options' doesn't exist (SQL: SELECT option_value FROM wp_options WHERE option_name = 'siteurl')" );
		$this->log_exception( "WordPress database error for query UPDATE wp_posts SET note = 'it's here' WHERE ID = 1" );
		$logged = $this->logged();

		$this->assertStringContainsString( "[RuntimeException: Table '***' doesn'***') @ ", $logged );
		$this->assertStringContainsString( "SET note = '***' WHERE ID = 1", $logged );
		$this->assertStringNotContainsString( 'siteurl', $logged );
		$this->assertStringNotContainsString( 'here', $logged );
	}

	/**
	 * Review-168 follow-up 3d: Bearer / Basic glued to a word are masked.
	 */
	public function test_bearer_without_word_boundary_is_masked() {
		$this->log_exception( 'header dirBearer eyJhbGciOiJIUzI1NiJ9abc' );
		$this->log_exception( 'header xBasic dXNlcjpwYXNz' );
		$logged = $this->logged();

		$this->assertStringNotContainsString( 'eyJhbGci', $logged );
		$this->assertStringNotContainsString( 'dXNlcjpw', $logged );
		$this->assertStringContainsString( 'header dirBearer *** @ ', $logged );
		$this->assertStringContainsString( 'header xBasic *** @ ', $logged );
	}

	/**
	 * Review-169 1: a directory with spaces followed by a name without an
	 * extension (the last part of the path) hides the user name.
	 */
	public function test_spaced_directory_before_a_bare_name_is_hidden() {
		$messages = array(
			'Directory /home/alice smith/public_html is not writable' => 'Directory [path]/public_html is not writable',
			'/home/alice smith/htdocs'                    => '[path]/htdocs',
			'/Users/Alice Smith/Desktop'                  => '[path]/Desktop',
			'"/home/alice smith/site" not found'          => '"[path]/site" not found',
			'(/home/alice smith/site)'                    => '([path]/site)',
			'/home/alice smith/site; retry'               => '[path]/site; retry',
			'/home/alice smith/README missing'            => '[path]/README missing',
			'/home/alice smith/site,/home/bob jones/site' => '[path]/site',
			'/data/Maria de la Cruz/public_html failed'   => '[path]/public_html failed',
		);
		foreach ( array_keys( $messages ) as $message ) {
			$this->log_exception( $message );
		}
		$logged = $this->logged();

		foreach ( array( 'alice', 'Alice', 'smith', 'Smith', 'bob', 'jones', 'Maria', 'Cruz' ) as $leak ) {
			$this->assertStringNotContainsString( $leak, $logged );
		}
		foreach ( $messages as $expected ) {
			$this->assertStringContainsString( '[RuntimeException: ' . $expected . ' @ ', $logged );
		}
	}

	/**
	 * Review-169 2: Chinese directory names with spaces and names with a
	 * comma are hidden (宝塔 site directories).
	 */
	public function test_cjk_and_comma_directories_are_hidden() {
		$messages = array(
			'/www/wwwroot/我的 网站/wp-config.php' => '[path]/wp-config.php',
			'/www/wwwroot/我的 网站/'              => '[path]/',
			'/www/wwwroot/我的 网站/public 失败'     => '[path]/public 失败',
			'/home/张 三/x.php'                  => '[path]/x.php',
			'/home/张 三/site'                   => '[path]/site',
			'/home/Smith, John/x.php'          => '[path]/x.php',
			'/home/Smith, John/site'           => '[path]/site',
		);
		foreach ( array_keys( $messages ) as $message ) {
			$this->log_exception( $message );
		}
		$logged = $this->logged();

		foreach ( array( '我的', '网站', '张', '三', 'Smith', 'John' ) as $leak ) {
			$this->assertStringNotContainsString( $leak, $logged );
		}
		foreach ( $messages as $expected ) {
			$this->assertStringContainsString( '[RuntimeException: ' . $expected . ' @ ', $logged );
		}
	}

	/**
	 * Review-169 1 and 2 as a scan: roots × user names (English, Chinese,
	 * with spaces, with commas, four words) × last parts × what comes before
	 * and after. No word of a user name may show up.
	 */
	public function test_user_name_scan_leaks_nothing() {
		$scrub = new \ReflectionMethod( Logger::class, 'scrub' );
		$scrub->setAccessible( true );
		$logger = new Logger();

		$names = array( 'alice smith', 'Mary Ann Smith', '张 三', '我的 网站', 'Smith, John', '王五, 赵六', 'José García', 'Tom and Jerry Site' );
		$leaks = array();
		foreach ( array( '/home/', '/Users/', '/www/wwwroot/', '/chroot/home/', '/u01/app/', '/data/' ) as $root ) {
			foreach ( $names as $name ) {
				foreach ( array( '/x.php', '/public_html', '/', '/My Notes.txt', '/a/b', '/站点' ) as $tail ) {
					foreach ( array( '', '"', '(', '调用 ' ) as $before ) {
						foreach ( array( '', ' failed', '"', ')', '; retry', ',', '，失败' ) as $after ) {
							$message = $before . $root . $name . $tail . $after;
							$out     = $scrub->invoke( $logger, $message );
							foreach ( preg_split( '/[\s,]+/u', $name, -1, PREG_SPLIT_NO_EMPTY ) as $word ) {
								if ( false !== strpos( $out, $word ) ) {
									$leaks[] = $message . ' => ' . $out;
									break;
								}
							}
						}
					}
				}
			}
		}

		$this->assertSame( array(), array_slice( $leaks, 0, 5 ), count( $leaks ) . ' leaks' );
	}

	/**
	 * Review-169 3: an SQL literal right after a word character (N'', X'',
	 * _utf8mb4'', LIKE'') or right before one ('..'AND) is masked.
	 */
	public function test_sql_literal_next_to_a_word_is_masked() {
		$messages = array(
			"INSERT INTO t VALUES (N'hunter2pass')"      => "INSERT INTO t VALUES (N'***')",
			"SELECT * FROM t WHERE pw='hunter2pass'AND x=1" => "SELECT * FROM t WHERE pw='***'AND x=1",
			"SELECT * FROM t WHERE a = _utf8mb4'hunter2pass'" => "SELECT * FROM t WHERE a = _utf8mb4'***'",
			"SELECT * FROM t WHERE a = X'4142434445'"    => "SELECT * FROM t WHERE a = X'***'",
			"SELECT * FROM t WHERE pw LIKE'hunter2pass'" => "SELECT * FROM t WHERE pw LIKE'***'",
			"SELECT * FROM t WHERE pw = 'hunter2pass'd AND x = 1" => "SELECT * FROM t WHERE pw = '***'d AND x = 1",
			"Table 'wp_options' doesn't exist (SQL: SELECT option_value FROM wp_options WHERE option_name = 'siteurl')" => "Table '***' doesn'***')",
		);
		foreach ( array_keys( $messages ) as $message ) {
			$this->log_exception( $message );
		}
		$logged = $this->logged();

		foreach ( array( 'hunter2', '4142434445', 'siteurl' ) as $leak ) {
			$this->assertStringNotContainsString( $leak, $logged );
		}
		foreach ( $messages as $expected ) {
			$this->assertStringContainsString( '[RuntimeException: ' . $expected . ' @ ', $logged );
		}
	}

	/**
	 * Review-169 4: with Bearer / Basic twice in a row the value after the
	 * last one is masked; a scheme glued to a word in front of prose keeps
	 * the prose.
	 */
	public function test_repeated_auth_scheme_masks_the_last_value() {
		$messages = array(
			'Header: X-Cardbearer Bearer tok_ab12cd34' => 'Header: X-Cardbearer Bearer ***',
			'authbearer Bearer tok_ab12cd34'           => 'authbearer Bearer ***',
			'sent as basic Basic dXNlcjpwdw=='         => 'sent as basic Basic ***',
			'xbearer Bearer plainlowercasetoken'       => 'xbearer Bearer ***',
			'flagbearer of the project'                => 'flagbearer of the project',
			'NonBasic users cannot access'             => 'NonBasic users cannot access',
		);
		foreach ( array_keys( $messages ) as $message ) {
			$this->log_exception( $message );
		}
		$logged = $this->logged();

		foreach ( array( 'tok_ab', 'dXNlcjpw', 'plainlower' ) as $leak ) {
			$this->assertStringNotContainsString( $leak, $logged );
		}
		foreach ( $messages as $expected ) {
			$this->assertStringContainsString( '[RuntimeException: ' . $expected . ' @ ', $logged );
		}
	}

	/**
	 * Review-169 (non-blocking): a PHP class name with a digit is kept; a
	 * key split by backslashes is still masked.
	 */
	public function test_php_class_name_is_kept() {
		$this->log_exception( 'Class WenPai\ChinaYes\Module\Mirror\Updater2 not found' );
		$this->log_exception( '{"class":"WenPai\\\\ChinaYes\\\\Module\\\\Mirror\\\\Updater2"}' );
		$this->log_exception( 'key Zm9vYmFy4QWx\Y2VkTOPSECRET\ab12cd34Zm9vYmFy end' );
		$this->log_exception( 'key Zm9vYmFyQWx\Y2VkTOPSECRET\Abcd34Zm9vYmFy end' );
		$logged = $this->logged();

		$this->assertStringContainsString( 'Class WenPai\ChinaYes\Module\Mirror\Updater2 not found @ ', $logged );
		$this->assertStringContainsString( '{"class":"WenPai\\\\ChinaYes\\\\Module\\\\Mirror\\\\Updater2"} @ ', $logged );
		$this->assertSame( 2, substr_count( $logged, '[RuntimeException: key *** end @ ' ) );
	}

	/**
	 * Review-169-2e15bcf 1: Chinese or other punctuation inside a directory
	 * name (，。、：！) does not end the directory: the user name is hidden.
	 */
	public function test_punctuation_inside_a_directory_name_is_hidden() {
		$messages = array(
			'/home/张三，李四/x.php'                        => '[path]/x.php',
			'/home/张三，李四/site'                         => '[path]/site',
			'/home/张 三，李四/site'                        => '[path]/site',
			'/home/张 三，李四/x.php'                       => '[path]/x.php',
			'/home/张 三，李四/site/x'                      => '[path]/x',
			'/home/alice smith，bob/site'               => '[path]/site',
			'/www/wwwroot/我的 网站、备份/site'               => '[path]/site',
			'/www/wwwroot/我的 网站。/site'                 => '[path]/site',
			'/home/张 三：李四/site'                        => '[path]/site',
			'/home/alice smith！/site'                  => '[path]/site',
			'无法写入 /www/wwwroot/我的 网站/wp-content，请检查权限' => '无法写入 [path]/wp-content，请检查权限',
		);
		foreach ( array_keys( $messages ) as $message ) {
			$this->log_exception( $message );
		}
		$logged = $this->logged();

		foreach ( array( '张', '三', '李四', 'alice', 'smith', 'bob', '我的', '网站', '备份' ) as $leak ) {
			$this->assertStringNotContainsString( $leak, $logged );
		}
		foreach ( $messages as $expected ) {
			$this->assertStringContainsString( '[RuntimeException: ' . $expected . ' @ ', $logged );
		}
	}

	/**
	 * Found by the review-169 differential scan (in 61a5b39..2e15bcf): a raw
	 * cut inside a URL user name that holds "{", a Chinese comma or other
	 * non-ASCII text kept the start of the name ("https://bob{…"), which the
	 * userinfo rule masks in the whole text. Only bytes that also end that
	 * rule (whitespace / ? #) mark a complete URL now.
	 */
	public function test_raw_cut_inside_a_non_ascii_userinfo_keeps_no_user_name() {
		$scrub = new \ReflectionMethod( Logger::class, 'scrub' );
		$scrub->setAccessible( true );
		$logger = new Logger();

		$leaks = array();
		for ( $back = 1; $back <= 14; $back++ ) {
			foreach ( array(
				'bob{'  => 'bob',
				'bob，'  => 'bob',
				'张三用户(' => '张三',
				'Ünal(' => 'Ünal',
			) as $user => $word ) {
				$message = substr( str_repeat( 'ab', 600 ), 0, 1191 - $back ) . ' https://' . $user . 'yyyy:pw@h.example.com/x tail';
				$out     = $scrub->invoke( $logger, $message );
				if ( false !== strpos( $out, $word ) ) {
					$leaks[] = $user . ' ' . $back . ' => ' . substr( $out, -30 );
				}
			}
		}

		$this->assertSame( array(), array_slice( $leaks, 0, 5 ), count( $leaks ) . ' leaks' );
	}

	/**
	 * Review-169-2e15bcf 2: a quote read as a contraction apostrophe ('it's',
	 * 'abc'd) cannot unmask a later literal. Both readings (contraction and
	 * plain) are masked, and a quote that pairs up with nothing masks the
	 * rest. The five cases fixed in 2e15bcf stay masked.
	 */
	public function test_contraction_reading_cannot_unmask_a_literal() {
		$scrub = new \ReflectionMethod( Logger::class, 'scrub' );
		$scrub->setAccessible( true );
		$logger = new Logger();
		$prefix = 'WordPress database error for query SELECT * FROM wp_users WHERE ';

		$cases = array(
			"pw='secret's'",
			"name='abc'd AND pw='hunter2pass'",
			"u='x'd OR p='hunter2pass'",
			"note='it's' AND pw='hunter2pass'",
			"note='it's, pw='hunter2pass'",
			"a='O'Brien' AND p='hunter2pass'",
			"a='Bob's' AND p='hunter2pass'",
			"pw='xyz'll AND p='hunter2pass'",
			"pw='abc'm' AND p='hunter2pass'",
			"pw='abc're AND p='hunter2pass'",
			"pw='abc've AND p='hunter2pass'",
			"pw='abc't AND p='hunter2pass'",
			"pw='abc'S AND p='hunter2pass'",
			"pw='hunter2pass's",
			"pw='hunter2pass'd",
			"pw='hunter2pass't AND x=1",
			"a='x' AND b='y'd AND p='hunter2pass'",
			"a=\"x\"d AND p='hunter2pass'",
			"a='x\\'s' AND p='hunter2pass'",
			"a=''d AND p='hunter2pass'",
			"N'hunter2pass'",
			"x=_utf8mb4'hunter2pass'",
			"x LIKE'hunter2pass'",
			"pw='hunter2pass'AND x=1",
			"a IN ('it's','hunter2pass')",
			"a='don't' AND p='hunter2pass'",
			"a='rock'n'roll' AND p='hunter2pass'",
			"a = 'abc'd'e' AND p='hunter2pass'",
			"SELECT 'a'd FROM t WHERE p='hunter2pass'",
			"Table 'wp_options' doesn't exist (SQL: SELECT 1 WHERE p='hunter2pass')",
		);
		$leaks = array();
		foreach ( $cases as $case ) {
			$out = $scrub->invoke( $logger, $prefix . $case );
			foreach ( array( 'hunter2', 'secret' ) as $secret ) {
				if ( false !== strpos( $out, $secret ) ) {
					$leaks[] = $case . ' => ' . substr( $out, strlen( $prefix ) - 6 );
					break;
				}
			}
		}
		$this->assertSame( array(), $leaks );

		$kept = array(
			"note='it's' AND pw='hunter2pass'"     => "note='***'",
			"a='Bob's' AND p='hunter2pass'"        => "a='***'",
			"pw='abc'm' AND p='hunter2pass'"       => "pw='***'",
			"a IN ('it's','hunter2pass')"          => "a IN ('***')",
			"a='don't' AND p='hunter2pass'"        => "a='***'",
			"pw='hunter2pass'AND x=1"              => "pw='***'AND x=1",
			"a='x' AND b='y'd AND p='hunter2pass'" => "a='***' AND b='***'",
		);
		foreach ( $kept as $case => $expected ) {
			$this->assertSame( $prefix . $expected, $scrub->invoke( $logger, $prefix . $case ) );
		}
	}

	/**
	 * Review-169-2e15bcf 3: the class name rule does not keep a part of 32+
	 * bytes with a digit (a pass phrase in front of a class-like tail).
	 */
	public function test_class_name_rule_does_not_keep_a_long_part_with_a_digit() {
		$this->log_exception( 'pw SummerVacationInHawaiiWithFamily2024\Corp\Alice' );
		$this->log_exception( 'SummerVacationInHawaiiWithFamily2024\\Corp\\Alice' );
		$this->log_exception( 'Vendor\Pkg\CorrectHorseBatteryStapleWithExtraWords2024' );
		$this->log_exception( 'Class WenPai\ChinaYes\Module\Mirror\Updater2 not found' );
		$logged = $this->logged();

		foreach ( array( 'Summer', 'Hawaii', 'Corp', 'CorrectHorse' ) as $leak ) {
			$this->assertStringNotContainsString( $leak, $logged );
		}
		$this->assertStringContainsString( '[RuntimeException: pw *** @ ', $logged );
		$this->assertSame( 2, substr_count( $logged, '[RuntimeException: *** @ ' ) );
		$this->assertStringContainsString( '[RuntimeException: Class WenPai\ChinaYes\Module\Mirror\Updater2 not found @ ', $logged );
	}

	/**
	 * The rules changed for review-169 stay linear on 200 KB through mask()
	 * alone, also with non-ASCII words and commas.
	 */
	public function test_review_169_rules_are_linear_without_the_cap() {
		$mask = new \ReflectionMethod( Logger::class, 'mask' );
		$mask->setAccessible( true );

		$inputs = array(
			'path cjk spaced words'  => '/r/' . str_repeat( '张 ', 50000 ),
			'path cjk spaced chain'  => '/r/' . str_repeat( '张 三/', 25000 ),
			'path comma words'       => '/r/' . str_repeat( 'a, ', 70000 ),
			'path cjk punctuation'   => '/r/' . str_repeat( '张 三，', 20000 ),
			'path cjk, no next'      => str_repeat( '/a/张 三 ', 15000 ),
			'path spaced to a name'  => str_repeat( '/a/b c/d ', 25000 ),
			'path u3000'             => '/r/' . str_repeat( "张\u{3000}", 40000 ),
			'class name runs'        => str_repeat( 'Ab\\', 70000 ),
			'class name digits'      => str_repeat( 'Abc1\\', 40000 ),
			'bearer chain'           => str_repeat( 'xBearer Bearer ', 14000 ),
			'bearer glued'           => str_repeat( 'xBearer abc ', 18000 ),
			'sql N quotes'           => 'SELECT x FROM y WHERE ' . str_repeat( "N'a", 66000 ),
			'sql apostrophes, quote' => 'SELECT x FROM y WHERE ' . str_repeat( "'it's ", 33000 ),
			'sql glued close'        => 'SELECT x FROM y WHERE ' . str_repeat( "'a'b", 50000 ),
			'sql contraction tails'  => "SELECT x FROM y WHERE '" . str_repeat( "a'll ", 40000 ),
			'sql both readings'      => 'SELECT x FROM y WHERE ' . str_repeat( "'it's' AND p='a' ", 12000 ),
			'sql contraction, close' => 'SELECT x FROM y WHERE ' . str_repeat( "'abc'd AND ", 18000 ),
			'sql unpaired, words'    => 'SELECT x FROM y WHERE ' . str_repeat( "'O'Brien' ", 20000 ),
			'class long part'        => str_repeat( 'Abcdefghijklmnopqrstuvwxyzabcdef1\\', 6000 ),
		);
		foreach ( $inputs as $name => $input ) {
			$start = microtime( true );
			$mask->invoke( new Logger(), $input );
			$elapsed = microtime( true ) - $start;
			$this->assertLessThan( 0.5, $elapsed, sprintf( '%s: %d bytes took %.3f s', $name, strlen( $input ), $elapsed ) );
		}
	}

	/**
	 * The rules changed for the review-168 follow-up stay linear on 200 KB
	 * through mask() alone (no length cap in front).
	 */
	public function test_follow_up_rules_are_linear_without_the_cap() {
		$mask = new \ReflectionMethod( Logger::class, 'mask' );
		$mask->setAccessible( true );

		$inputs = array(
			'base64 inner separators' => str_repeat( 'Ab+|', 50000 ),
			'base64 json escapes'     => str_repeat( 'Ab\\/', 40000 ),
			'base64 backslashes'      => str_repeat( 'Ab1\\', 50000 ),
			'sql unclosed quotes'     => 'SELECT x FROM y WHERE ' . str_repeat( " 'a", 66000 ),
			'sql apostrophes'         => 'SELECT x FROM y WHERE ' . str_repeat( "it's ", 40000 ),
			'path spaced, no next'    => str_repeat( '/a/b c ', 28000 ),
			'path spaced chain'       => '/r/' . str_repeat( 'a b/', 50000 ),
			'path spaced words'       => '/r/' . str_repeat( 'a ', 100000 ),
			'bearer'                  => str_repeat( 'xBearer ', 25000 ),
		);
		foreach ( $inputs as $name => $input ) {
			$start = microtime( true );
			$mask->invoke( new Logger(), $input );
			$elapsed = microtime( true ) - $start;
			$this->assertLessThan( 0.5, $elapsed, sprintf( '%s: %d bytes took %.3f s', $name, strlen( $input ), $elapsed ) );
		}
	}

	/**
	 * Long Chinese and compact JSON messages are scrubbed in bounded time,
	 * through Logger and through mask() alone.
	 */
	public function test_long_cjk_and_json_messages_are_scrubbed_quickly() {
		$object = '{"code":"http_request_failed","message":"cURL error 28","data":{"status":503,"path":"/a/b"}}';
		$inputs = array(
			str_repeat( '数据库连接失败', 25000 ),
			str_repeat( "连接\u{3000}", 60000 ),
			'[' . implode( ',', array_fill( 0, 5600, $object ) ) . ']',
		);
		$mask   = new \ReflectionMethod( Logger::class, 'mask' );
		$mask->setAccessible( true );

		foreach ( $inputs as $input ) {
			$this->assertGreaterThan( 500000, strlen( $input ) );
			$start = microtime( true );
			$this->log_exception( $input );
			$elapsed = microtime( true ) - $start;
			$this->assertLessThan( 0.5, $elapsed, sprintf( 'Logger: %d bytes took %.3f s', strlen( $input ), $elapsed ) );

			$piece = substr( $input, 0, 200001 );
			$start = microtime( true );
			$mask->invoke( new Logger(), $piece );
			$elapsed = microtime( true ) - $start;
			$this->assertLessThan( 0.5, $elapsed, sprintf( 'mask(): %d bytes took %.3f s', strlen( $piece ), $elapsed ) );
		}
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
