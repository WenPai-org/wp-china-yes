<?php
/**
 * Exception message and credential scrubber for the kernel logger.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Core;

/**
 * Scrubs secrets, PII, control characters, and absolute paths from log text.
 */
final class LogScrubber {

	/**
	 * Maximum length of the scrubbed exception message in an error_log line.
	 *
	 * @var int
	 */
	private const EXCEPTION_MESSAGE_MAX = 300;

	/**
	 * Raw bytes of an exception message that are masked at all. Every regex
	 * in mask() sees at most this many bytes, whatever the message length.
	 *
	 * @var int
	 */
	private const EXCEPTION_MESSAGE_SCAN_MAX = 4 * self::EXCEPTION_MESSAGE_MAX;

	/**
	 * Cap for drop_open_query() / drop_open_userinfo() reading past the raw cut.
	 *
	 * Past this many bytes without a terminator, treat the open run as a match
	 * and drop it (more masking, no leak). Linear and bounded.
	 */
	private const DROP_OPEN_SCAN_MAX = 4096;

	/**
	 * Bytes that may belong to one token at the raw cut: printable ASCII
	 * except whitespace and the separators , ; " ' { } [ ] ( ) < > ! ?
	 *
	 * Bytes 0x80-0xFF (any non-ASCII character: CJK text, fullwidth
	 * punctuation, NBSP, U+3000) end a token.
	 *
	 * @var string
	 */
	private const CUT_TOKEN_BYTES = '#$%&*+-./0123456789:=@ABCDEFGHIJKLMNOPQRSTUVWXYZ\\^_`abcdefghijklmnopqrstuvwxyz|~';

	/**
	 * Unix directories, see relative_paths(). An escaped slash still marked
	 * with \x01 (see mask()) may start a path whatever is in front of it.
	 */
	private const UNIX_PATH = '#(?:(?<![\w.:/\]])|(?<=(?i:file://|phar://)))/(?:[^\s/:\'"()<>\[\]]++/)++(?:[^\s/:\'"()<>\[\]]++(?: ++[^\s/:\'"()<>\[\]]++)*+/)*+#';

	/**
	 * SQL literal redactor.
	 *
	 * @var LogSqlRedactor
	 */
	private LogSqlRedactor $sql_redactor;

	/**
	 * Constructor.
	 *
	 * @param LogSqlRedactor|null $sql_redactor Optional SQL literal redactor.
	 */
	public function __construct( ?LogSqlRedactor $sql_redactor = null ) {
		$this->sql_redactor = null !== $sql_redactor ? $sql_redactor : new LogSqlRedactor();
	}

	/**
	 * Keep class name and message only. Drop traces.
	 *
	 * @param mixed $value Throwable, array, or message string.
	 * @return array{class: string, message: string}
	 */
	public function redact_exception( $value ): array {
		if ( $value instanceof \Throwable ) {
			return array(
				'class'   => get_class( $value ),
				'message' => $value->getMessage(),
			);
		}

		if ( is_array( $value ) ) {
			$class   = isset( $value['class'] ) && is_string( $value['class'] ) ? $value['class'] : '';
			$message = isset( $value['message'] ) && is_string( $value['message'] ) ? $value['message'] : '';
			return array(
				'class'   => $class,
				'message' => $message,
			);
		}

		return array(
			'class'   => '',
			'message' => is_string( $value ) ? $value : '',
		);
	}

	/**
	 * Mask api_key= and license_key= query values. Never keeps the secret.
	 *
	 * @param string $value Raw string.
	 */
	public function redact_query_secrets( string $value ): string {
		$redacted = preg_replace( '/((?:api_key|license_key)=)([^&\s]*)/i', '$1***', $value );
		return is_string( $redacted ) ? $redacted : $value;
	}

	/**
	 * Error_log suffix for context['exception']: " [Class: message @ file:line]".
	 *
	 * Only class, scrubbed message, and file:line (relative to the plugin root,
	 * basename outside it). Never a stack trace or other context values.
	 *
	 * @param mixed $value Throwable, redacted array, message string, or null.
	 */
	public function exception_suffix( $value ): string {
		if ( null === $value ) {
			return '';
		}

		$where = '';
		if ( $value instanceof \Throwable ) {
			$class   = get_class( $value );
			$message = $value->getMessage();
			$where   = $this->relative_file( $value->getFile() ) . ':' . $value->getLine();
		} else {
			$info    = $this->redact_exception( $value );
			$class   = $info['class'];
			$message = $info['message'];
		}

		$class   = $this->class_label( $class );
		$message = $this->scrub( $message );
		if ( '' === $class && '' === $message ) {
			return '';
		}

		$text = '' === $class ? $message : $class . ': ' . $message;
		if ( '' !== $where ) {
			$text .= ' @ ' . $where;
		}

		return ' [' . $text . ']';
	}

	/**
	 * Mask secrets and personal data in free text, then cap its length.
	 *
	 * 1. Cut the raw text to EXCEPTION_MESSAGE_SCAN_MAX bytes, so masking
	 *    cost is bounded however long the message is; a token the cut falls
	 *    into is dropped.
	 * 2. Mask, cut to EXCEPTION_MESSAGE_MAX bytes, mask once more, so the
	 *    final cut never leaves half a secret behind.
	 *
	 * Every cut is UTF-8 safe.
	 *
	 * @param string $text Raw text (e.g. an exception message).
	 */
	public function scrub( string $text ): string {
		$use_mb    = function_exists( 'mb_strcut' );
		$truncated = strlen( $text ) > self::EXCEPTION_MESSAGE_SCAN_MAX;
		if ( $truncated ) {
			$text = $this->cut_raw( $text, $use_mb );
		}

		$text = $this->mask( $text );
		if ( ! $truncated && strlen( $text ) <= self::EXCEPTION_MESSAGE_MAX ) {
			return $text;
		}
		if ( strlen( $text ) <= self::EXCEPTION_MESSAGE_MAX ) {
			return $text . '…';
		}

		$text = $this->mask( $this->utf8_cut( $text, self::EXCEPTION_MESSAGE_MAX, $use_mb ) );
		if ( strlen( $text ) > self::EXCEPTION_MESSAGE_MAX ) {
			$text = $this->utf8_cut( $text, self::EXCEPTION_MESSAGE_MAX, $use_mb );
		}

		return $text . '…';
	}

	/**
	 * Cut raw text to EXCEPTION_MESSAGE_SCAN_MAX bytes before masking.
	 *
	 * @param string $text   Raw text longer than EXCEPTION_MESSAGE_SCAN_MAX.
	 * @param bool   $use_mb Use mb_strcut().
	 */
	private function cut_raw( string $text, bool $use_mb ): string {
		$cut  = $this->utf8_cut( $text, self::EXCEPTION_MESSAGE_SCAN_MAX, $use_mb );
		$next = substr( $text, strlen( $cut ), 1 );
		if ( '' !== $next && 0 < strspn( $next, self::CUT_TOKEN_BYTES ) ) {
			// Length of the trailing token run: one linear pass.
			$fragment = strspn( strrev( $cut ), self::CUT_TOKEN_BYTES );
			$cut      = substr( $cut, 0, strlen( $cut ) - $fragment );
		}

		// mask() reads a base64-like run across one of ! " ' (not token bytes),
		// so a key "A'B" cut inside B must not keep A: drop the run in front of
		// such a separator too, also when the cut is right at it. Each round
		// drops at least one byte.
		while ( true ) {
			$len  = strlen( $cut );
			$last = substr( $cut, -1 );
			$next = substr( $text, $len, 1 );
			if ( $this->is_run_join( $last ) && $this->is_base64_byte( substr( $cut, -2, 1 ) ) && $this->is_base64_byte( $next ) ) {
				$cut = substr( $cut, 0, -1 );
			} elseif ( ! $this->is_base64_byte( $last ) || ! $this->is_run_join( $next ) || ! $this->is_base64_byte( substr( $text, $len + 1, 1 ) ) ) {
				break;
			}
			$cut = substr( $cut, 0, strlen( $cut ) - strspn( strrev( $cut ), self::CUT_TOKEN_BYTES ) );
		}

		return rtrim( $this->drop_open_userinfo( $this->drop_open_query( $cut, $text ), $text ), " \t\n\r\v\f" );
	}

	/**
	 * Drop an unfinished query parameter name at the end of the raw cut.
	 *
	 * @param string $cut  Raw text after the cut.
	 * @param string $text Raw text before the cut ($cut is its prefix).
	 */
	private function drop_open_query( string $cut, string $text ): string {
		$query = strrpos( $cut, '?' );
		if ( false === $query ) {
			return $cut;
		}

		$stops = " \t\n\r\v\f?#=&:@";
		$kept  = strlen( $cut ) - $query - 1;
		if ( 0 === $kept || strcspn( $cut, $stops, $query + 1 ) < $kept ) {
			return $cut;
		}

		$rest_off = strlen( $cut );
		$limit    = min( strlen( $text ), $rest_off + self::DROP_OPEN_SCAN_MAX );
		$window   = substr( $text, $rest_off, $limit - $rest_off );
		$extra    = strcspn( $window, $stops );
		$name     = $kept + $extra;
		$term_at  = $query + 1 + $name;
		if ( $extra >= strlen( $window ) ) {
			return $limit < strlen( $text ) ? substr( $cut, 0, $query ) : $cut;
		}
		$term = $text[ $term_at ];
		if ( false === strpos( '=:@', $term ) ) {
			return $cut;
		}

		return substr( $cut, 0, $query );
	}

	/**
	 * One byte of the base64-like rule's alphabet.
	 *
	 * @param string $byte One byte or "".
	 */
	private function is_base64_byte( string $byte ): bool {
		return '' !== $byte && 1 === strspn( $byte, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/_-' );
	}

	/**
	 * A separator the base64-like rule reads across that is not a token byte.
	 *
	 * @param string $byte One byte or "".
	 */
	private function is_run_join( string $byte ): bool {
		return '' !== $byte && false !== strpos( '!"\'', $byte );
	}

	/**
	 * Drop an unfinished URL userinfo at the end of the raw cut.
	 *
	 * @param string $cut  Raw text after the cut.
	 * @param string $text Raw text before the cut ($cut is its prefix).
	 */
	private function drop_open_userinfo( string $cut, string $text ): string {
		$scheme = strrpos( $cut, '://' );
		if ( false === $scheme ) {
			return $cut;
		}

		$kept     = strlen( $cut ) - $scheme - 3;
		$rest_off = $scheme + 3;
		$limit    = min( strlen( $text ), $rest_off + self::DROP_OPEN_SCAN_MAX );
		$rest     = substr( $text, $rest_off, $limit - $rest_off );
		$stop     = strcspn( $rest, " \t\n\r\v\f/?#@" );
		if ( $stop >= strlen( $rest ) ) {
			if ( $limit < strlen( $text ) && $stop >= $kept ) {
				return substr( $cut, 0, $scheme );
			}
			return $cut;
		}
		if ( '@' !== $rest[ $stop ] || $stop < $kept ) {
			return $cut;
		}

		return substr( $cut, 0, $scheme );
	}

	/**
	 * Masking pipeline for one log line. Order matters.
	 *
	 * @param string $text Raw text.
	 */
	public function mask( string $text ): string {
		$text = $this->strip_controls( $text );
		$text = $this->sql_redactor->redact( $text );
		$text = strtr(
			$text,
			array(
				'\\\\' => '\\\\',
				'\\/'  => "\x01/",
			)
		);
		$text = $this->replace( '#\b([a-z][a-z0-9+.-]*:\x01?/\x01?/)[^\s/?\#@]++@#i', '$1***@', $text );
		$text = $this->replace( '#\b((?i:file|phar):)(\x01?/\x01?/)localhost(?=/)#', '$1$2', $text );
		$text = $this->mask_escaped_runs( $text );
		$text = str_replace( "\x01", '', $this->relative_paths( $text ) );
		$text = $this->redact_query_secrets( $text );

		$text = $this->redact_auth_schemes( $text, false );
		$text = $this->redact_key_values( $text );

		$patterns = array(
			'#(\bhttps?://[^\s?\#]+)\?[^\s\#]*#i'         => '$1',
			'#\?[^\s?\#=&:@]*+[=:@][^\s\#]*#'             => '',
			'/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i'    => '***@***',
			'/\b(?:\d{1,3}\.){3}\d{1,3}\b/'               => '***',
			'/\b(?:[0-9a-f]{1,4}:){2,7}[0-9a-f]{1,4}\b/i' => '***',
		);
		foreach ( $patterns as $pattern => $replacement ) {
			$text = $this->replace( $pattern, $replacement, $text );
		}
		$text = $this->redact_auth_schemes( $text, true );

		$masked = preg_replace_callback(
			'#(?<![\w\\\\/.-])(?:[A-Za-z0-9+/_-]|\\\\[\\\\/])++(?:[!"\'|~$^`%\\\\](?:[A-Za-z0-9+/_-]|\\\\[\\\\/])++(?!:))*+={0,2}#',
			static function ( array $m ): string {
				return self::is_key_run( $m[0] ) ? '***' : $m[0];
			},
			$text
		);
		$text   = is_string( $masked ) ? $masked : $text;
		$text   = $this->replace( '/\b[0-9a-f]{32,}\b/i', '***', $text );

		$masked = preg_replace_callback(
			'#(?<=\*\*\*)[A-Za-z0-9+/_-]{32,}+={0,2}#',
			static function ( array $m ): string {
				return false === strpbrk( $m[0], '0123456789' ) ? $m[0] : '***';
			},
			$text
		);

		return is_string( $masked ) ? $masked : $text;
	}

	/**
	 * Bearer / Basic credentials become "Bearer ***".
	 *
	 * @param string $text  Text.
	 * @param bool   $glued Pass for glued scheme words.
	 */
	private function redact_auth_schemes( string $text, bool $glued ): string {
		$masked = preg_replace_callback(
			'/([A-Za-z0-9]?)((?:Bearer|Basic)(?:\s++(?:Bearer|Basic)(?=\s))*+)\s++((?:(?!(?:Bearer|Basic)\s)[^\s,;"\'])*+)/i',
			static function ( array $m ) use ( $text, $glued ): string {
				$at       = $m[2][1];
				$is_glued = $at > 0 && 1 === preg_match( '/[A-Za-z0-9]/', $text[ $at - 1 ] ) && strlen( $m[2][0] ) === strcspn( $m[2][0], " \t\n\r\v\f" );
				$value    = $m[3][0];
				if ( $is_glued !== $glued || '' === $value ) {
					return $m[0][0];
				}
				$keys = '/((?<![a-z0-9])(?:[a-z_]*key|[a-z_]*token|password|passwd|pwd|secret|credential|authorization|auth)(?:[=:]|%3D|%3A))/i';
				if ( $glued ) {
					if ( 1 === preg_match( $keys, $value ) || 1 !== preg_match( '/[0-9+\/=_.-]|.[A-Z]|^[a-f]{8}/', $value ) ) {
						return $m[0][0];
					}
					return $m[1][0] . $m[2][0] . ' ***';
				}
				$q = strpos( $value, '?' );
				if ( false !== $q && false === strpos( $value, '#', $q ) && 1 === preg_match( '/\G\?[^\s?\#=&]++=/', $text, $qm, 0, $m[3][1] + $q ) ) {
					return 0 === $q ? $m[0][0] : $m[1][0] . $m[2][0] . ' ***' . substr( $value, $q );
				}
				$parts = preg_split( $keys, $value, -1, PREG_SPLIT_DELIM_CAPTURE );
				if ( is_array( $parts ) && count( $parts ) > 1 ) {
					$out = '';
					foreach ( $parts as $i => $part ) {
						$out .= ( 1 === $i % 2 || '' === $part ) ? $part : '***';
					}
					return $out === $value ? $m[0][0] : $m[1][0] . $m[2][0] . ' ' . $out;
				}
				return $m[1][0] . $m[2][0] . ' ***';
			},
			$text,
			-1,
			$found,
			PREG_OFFSET_CAPTURE
		);

		return is_string( $masked ) ? $masked : $text;
	}

	/**
	 * Preg_replace() that keeps the input when the pattern fails.
	 *
	 * @param string $pattern     Regex.
	 * @param string $replacement Replacement.
	 * @param string $text        Subject.
	 */
	private function replace( string $pattern, string $replacement, string $text ): string {
		$replaced = preg_replace( $pattern, $replacement, $text );
		return is_string( $replaced ) ? $replaced : $text;
	}

	/**
	 * Control characters, line/paragraph separators and bidi controls become one space per run.
	 *
	 * @param string $text Raw text.
	 */
	private function strip_controls( string $text ): string {
		$clean = preg_replace( '/[\p{Cc}\p{Zl}\p{Zp}\x{061C}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]+/u', ' ', $text );
		if ( ! is_string( $clean ) ) {
			$clean = preg_replace( '/(?:[\x00-\x1F\x7F]|\xC2[\x80-\x9F]|\xD8\x9C|\xE2\x80[\x8E\x8F\xA8-\xAE]|\xE2\x81[\xA6-\xA9])+/', ' ', $text );
		}

		return is_string( $clean ) ? $clean : '';
	}

	/**
	 * Whether a base64-like run is masked: 32+ bytes with a digit, unless it is a PHP class name.
	 *
	 * @param string $run Run matched by the base64-like rule.
	 */
	private static function is_key_run( string $run ): bool {
		if ( strlen( $run ) < 32 || false === strpbrk( $run, '0123456789' ) ) {
			return false;
		}
		if ( 1 !== preg_match( '/^\\\\?+(?:(?:[A-Z][a-z]++)++[0-9]{0,4}+\\\\{1,2}+){2,}+(?:[A-Z][a-z]++)++[0-9]{0,4}+$/D', $run ) ) {
			return true;
		}
		foreach ( preg_split( '/\\\\++/', $run ) as $part ) {
			if ( strlen( $part ) >= 32 && false !== strpbrk( $part, '0123456789' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Length of $run without a URL scheme at its end.
	 *
	 * @param string $run  Base64-like run.
	 * @param string $next Text right after the run.
	 */
	private static function run_without_scheme( string $run, string $next ): int {
		if ( 0 === strncmp( $next, '://', 3 ) && 1 === preg_match( '#^(.*/)[a-z][a-z0-9+.-]*+$#isD', $run, $m ) ) {
			return strlen( $m[1] );
		}
		return strlen( $run );
	}

	/**
	 * Mask base64-like runs that hold an escaped slash before the path rule splits them.
	 *
	 * @param string $text Text with escaped slashes marked.
	 */
	private function mask_escaped_runs( string $text ): string {
		if ( false === strpos( $text, "\x01" ) ) {
			return $text;
		}
		$paths = array();
		if ( preg_match_all( self::UNIX_PATH, $text, $pm, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $pm[0] as $p ) {
				$paths[] = array( $p[1], $p[1] + strlen( $p[0] ) );
			}
		}
		$next   = 0;
		$masked = preg_replace_callback(
			'#(?<![\w\\\\/.\x01-])(?!/)(?:[A-Za-z0-9+/_-]|\\\\\\\\|\x01/)++#',
			static function ( array $m ) use ( $paths, &$next, $text ): string {
				$run   = $m[0][0];
				$start = $m[0][1];
				$run   = substr( $run, 0, self::run_without_scheme( $run, (string) substr( $text, $start + strlen( $run ), 3 ) ) );
				if ( 1 === preg_match( '#^(.*)\x01/[A-Za-z_/]*+$#s', $run, $w ) ) {
					$run = $w[1];
				}
				if ( '' === $run || "\x01" === $run[0] || false === strpos( $run, "\x01" ) || '@' === substr( $text, $start + strlen( $m[0][0] ), 1 ) ) {
					return $m[0][0];
				}
				$end   = $start + strlen( $run );
				$count = count( $paths );
				while ( $next < $count && $paths[ $next ][1] <= $start ) {
					++$next;
				}
				for ( $i = $next; $i < $count && $paths[ $i ][0] < $end; $i++ ) {
					if ( $paths[ $i ][0] < $start || $paths[ $i ][1] > $end ) {
						return $m[0][0];
					}
				}
				if ( ! self::is_key_run( str_replace( "\x01", '', $run ) ) ) {
					return $m[0][0];
				}
				return '***' . substr( $m[0][0], strlen( $run ) );
			},
			$text,
			-1,
			$runs,
			PREG_OFFSET_CAPTURE
		);

		return is_string( $masked ) ? $masked : $text;
	}

	/**
	 * Plugin paths become relative; other absolute paths keep the file name.
	 *
	 * @param string $text Text without control characters.
	 */
	private function relative_paths( string $text ): string {
		$root = dirname( __DIR__, 2 );
		$text = str_replace(
			array( $root . '/', $root . '\\', str_replace( '/', '\\', $root ) . '\\' ),
			'',
			$text
		);

		$text = $this->replace( '#(?<!\w)(?:[A-Za-z]:|(?:\\\\){2,4}[^\s\\\\/\x01]++)[\\\\/\x01]++(?:[^\s\\\\/:*?"\'<>|\x01]++(?: ++[^\s\\\\/:*?"\'<>|\x01]++)*+[\\\\/\x01]++)*+#', '[path]/', $text );

		return $this->replace( self::UNIX_PATH, '[path]/', $text );
	}

	/**
	 * Mask key=value / "key":"value" secrets.
	 *
	 * @param string $text Text.
	 */
	private function redact_key_values( string $text ): string {
		$masked = preg_replace_callback(
			'/(["\']?)\b((?:[a-z_]*key|[a-z_]*token|password|passwd|pwd|secret|credential|authorization|auth))\1(\s*(?:[=:]|%3D|%3A)\s*)(?!(?:Bearer|Basic)\s\*\*\*)("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|[^\s&,;}\]]+)/i',
			static function ( array $m ): string {
				$quote = substr( $m[4], 0, 1 );
				$value = ( '"' === $quote || "'" === $quote ) ? $quote . '***' . $quote : '***';
				return $m[1] . $m[2] . $m[1] . $m[3] . $value;
			},
			$text
		);

		return is_string( $masked ) ? $masked : $text;
	}

	/**
	 * Cut to at most $max bytes without splitting a UTF-8 character.
	 *
	 * @param string $text   Text.
	 * @param int    $max    Byte limit.
	 * @param bool   $use_mb Use mb_strcut() (false: substr() plus trailing fix-up).
	 */
	public function utf8_cut( string $text, int $max, bool $use_mb ): string {
		if ( $use_mb ) {
			return mb_strcut( $text, 0, $max, 'UTF-8' );
		}

		$cut = substr( $text, 0, $max );

		return $this->replace( '/(?:[\xC0-\xDF]|[\xE0-\xEF][\x80-\xBF]?|[\xF0-\xF7][\x80-\xBF]{0,2})$/', '', $cut );
	}

	/**
	 * Class name safe for one log line.
	 *
	 * @param string $name Class name.
	 */
	private function class_label( string $name ): string {
		$nul = strpos( $name, "\0" );
		if ( false !== $nul ) {
			$name = substr( $name, 0, $nul );
		}

		return (string) preg_replace( '/[^A-Za-z0-9_\\\\@]/', '', $name );
	}

	/**
	 * File path relative to the plugin root; basename when outside it.
	 *
	 * @param string $file Absolute file path.
	 */
	private function relative_file( string $file ): string {
		$file = $this->strip_controls( $file );
		$root = dirname( __DIR__, 2 ) . DIRECTORY_SEPARATOR;
		if ( 0 === strpos( $file, $root ) ) {
			return str_replace( DIRECTORY_SEPARATOR, '/', substr( $file, strlen( $root ) ) );
		}

		return basename( str_replace( '\\', '/', $file ) );
	}
}
