<?php
/**
 * Kernel logger. Production default is warning and above.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Core;

/**
 * Level + context logger. Does not stand in for module failure records.
 */
final class Logger {

	/**
	 * PSR-3 priorities. Higher means more severe.
	 *
	 * @var array<string, int>
	 */
	private const PRIORITY = array(
		'debug'     => 100,
		'info'      => 200,
		'notice'    => 250,
		'warning'   => 300,
		'error'     => 400,
		'critical'  => 500,
		'alert'     => 550,
		'emergency' => 600,
	);

	/**
	 * Minimum level to keep.
	 *
	 * @var string
	 */
	private string $min_level;

	/**
	 * Optional writer. Null uses error_log().
	 *
	 * Callable is not a valid PHP 7.4 property type.
	 *
	 * @var callable|null
	 */
	private $writer;

	/**
	 * Kept records after min-level filter.
	 *
	 * @var list<array{level: string, message: string, context: array<string, mixed>}>
	 */
	private array $records = array();

	/**
	 * Create a logger.
	 *
	 * @param string        $min_level Minimum level to keep. Production default warning.
	 * @param callable|null $writer    Optional `fn(string $level, string $message, array $context): void`.
	 */
	public function __construct( string $min_level = 'warning', ?callable $writer = null ) {
		$this->min_level = strtolower( $min_level );
		$this->writer    = $writer;
	}

	/**
	 * Record a log line if it meets the minimum level.
	 *
	 * @param string               $level   PSR-3 level name.
	 * @param string               $message Human-readable line.
	 * @param array<string, mixed> $context Context. Secrets are stripped.
	 */
	public function log( string $level, string $message, array $context = array() ): void {
		$level = strtolower( $level );
		if ( $this->priority( $level ) < $this->priority( $this->min_level ) ) {
			return;
		}

		$suffix          = $this->exception_suffix( $context['exception'] ?? null );
		$message         = $this->redact_query_secrets( $message );
		$context         = $this->redact( $context );
		$this->records[] = array(
			'level'   => $level,
			'message' => $message,
			'context' => $context,
		);

		if ( null !== $this->writer ) {
			( $this->writer )( $level, $message, $context );
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- kernel sink; failures also go to ModuleRegistry::failures().
		error_log( sprintf( 'WPCY.%s: %s', $level, $message ) . $suffix );
	}

	/**
	 * Convenience for error-level lines.
	 *
	 * @param string               $message Human-readable line.
	 * @param array<string, mixed> $context Context.
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( 'error', $message, $context );
	}

	/**
	 * Kept records (already filtered by min level).
	 *
	 * @return list<array{level: string, message: string, context: array<string, mixed>}>
	 */
	public function records(): array {
		return $this->records;
	}

	/**
	 * Strip credentials, email, and URL query strings from context.
	 *
	 * @param array<string, mixed> $context Raw context.
	 * @return array<string, mixed>
	 */
	private function redact( array $context ): array {
		$out = array();

		foreach ( $context as $key => $value ) {
			if ( in_array( $key, array( 'license_key', 'api_key' ), true ) ) {
				$out[ $key ] = '***';
				continue;
			}

			if ( in_array( $key, array( 'password', 'credential', 'token', 'authorization', 'email', 'auth', 'ip', 'cookie' ), true ) ) {
				continue;
			}

			if ( 'exception' === $key ) {
				$out[ $key ] = $this->redact_exception( $value );
				continue;
			}

			if ( 'url' === $key && is_string( $value ) ) {
				$out[ $key ] = $this->redact_url( $this->redact_query_secrets( $value ) );
				continue;
			}

			if ( is_array( $value ) ) {
				$out[ $key ] = $this->redact( $value );
				continue;
			}

			if ( is_string( $value ) ) {
				$out[ $key ] = $this->redact_query_secrets( $value );
				continue;
			}

			$out[ $key ] = $value;
		}

		return $out;
	}

	/**
	 * Keep class name and message only. Drop traces.
	 *
	 * @param mixed $value Throwable, array, or message string.
	 * @return array{class: string, message: string}
	 */
	private function redact_exception( $value ): array {
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
	 * Raw bytes past the cut looked at to tell a URL userinfo from a host.
	 *
	 * @var int
	 */
	private const USERINFO_SCAN_MAX = 256;

	/**
	 * Bytes that may belong to one token at the raw cut: printable ASCII
	 * except whitespace and the separators , ; " ' { } [ ] ( ) < > ! ?
	 *
	 * Bytes 0x80-0xFF (any non-ASCII character: CJK text, fullwidth
	 * punctuation, NBSP, U+3000) end a token.
	 *
	 * A byte left out of this list is a boundary, so the cut KEEPS the part
	 * of a secret before it instead of dropping the whole run: leaving out a
	 * byte that can occur inside a secret makes the cut keep more, not less.
	 * The list therefore holds every byte the masking rules match (A-Z a-z
	 * 0-9 + / _ - = % . @ : & * ...). The bytes left out also end a match of
	 * the base64-like, hex and email rules, and key=value / Bearer still mask
	 * by their key, so a kept prefix is exactly what the same message leaves
	 * unmasked without any cut (review-168 scan: 143640 runs, no extra leak;
	 * kernel scan: 20592 runs, longest kept piece 0). One rule does not end
	 * at those bytes: URL userinfo (a user name may contain , ; ' ( ) !), so
	 * cut_raw() also drops an unfinished "scheme://user" at the end.
	 *
	 * @var string
	 */
	private const CUT_TOKEN_BYTES = '#$%&*+-./0123456789:=@ABCDEFGHIJKLMNOPQRSTUVWXYZ\\^_`abcdefghijklmnopqrstuvwxyz|~';

	/**
	 * Error_log suffix for context['exception']: " [Class: message @ file:line]".
	 *
	 * Only class, scrubbed message, and file:line (relative to the plugin root,
	 * basename outside it). Never a stack trace or other context values.
	 *
	 * @param mixed $value Throwable, redacted array, message string, or null.
	 */
	private function exception_suffix( $value ): string {
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
	private function scrub( string $text ): string {
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
	 * When the cut falls inside a token (a run of CUT_TOKEN_BYTES), the whole
	 * run is dropped, however long: a cut-off key shorter than 32 characters,
	 * or a cut-off email, would no longer match its masking rule. The run ends
	 * at whitespace, ASCII separators, or any non-ASCII character, so Chinese
	 * text, NBSP / U+3000 separated words and compact JSON keep their text.
	 * The drop never splits a UTF-8 character: the run is ASCII only.
	 * A cut inside a 1200-byte run of token bytes keeps nothing.
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

		return rtrim( $this->drop_open_userinfo( $cut, $text ), " \t\n\r\v\f" );
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
	 * "https://bob(x:pw@host" cut before "@" would keep "https://bob(" and
	 * the userinfo rule (which needs the "@") could no longer mask "bob".
	 *
	 * Decided on the raw text, not on the cut: from the last "://" of the cut,
	 * look for the first byte that cannot be in a userinfo (whitespace, "/",
	 * "?", "#", "\"", "<", ">", "[", "]", "{", "}", "|", "\\", "^", "`", or
	 * any non-ASCII byte such as a Chinese comma) or "@". Only an "@" past
	 * the cut means the cut ends inside a userinfo: everything from "://" on
	 * is dropped. An "@" inside the cut is masked by the userinfo rule; any
	 * other end means a complete URL, which is kept. No end within
	 * USERINFO_SCAN_MAX bytes past the cut also drops (fail closed).
	 *
	 * @param string $cut  Raw text after the cut.
	 * @param string $text Raw text before the cut ($cut is its prefix).
	 */
	private function drop_open_userinfo( string $cut, string $text ): string {
		$scheme = strrpos( $cut, '://' );
		if ( false === $scheme ) {
			return $cut;
		}

		$kept = strlen( $cut ) - $scheme - 3;
		$rest = substr( $text, $scheme + 3, $kept + self::USERINFO_SCAN_MAX );
		$stop = strcspn( $rest, " \t\n\r\v\f/?#@\"<>[]{}|\\^`" . self::non_ascii_bytes() );
		if ( $stop < strlen( $rest ) && ( '@' !== $rest[ $stop ] || $stop < $kept ) ) {
			return $cut;
		}

		return substr( $cut, 0, $scheme );
	}

	/**
	 * Bytes 0x80-0xFF, for strcspn().
	 */
	private static function non_ascii_bytes(): string {
		static $bytes = null;
		if ( null === $bytes ) {
			$bytes = implode( '', array_map( 'chr', range( 0x80, 0xFF ) ) );
		}

		return $bytes;
	}

	/**
	 * Masking pipeline for one log line. Order matters.
	 *
	 * 1. Control characters (CR, LF, NUL, ...), Unicode line / paragraph
	 *    separators and bidi controls become spaces: no forged, cut-off, or
	 *    visually reordered lines.
	 * 2. Paths inside the plugin become relative; other absolute paths (Unix,
	 *    Windows drive, UNC) keep only the file name behind "[path]/".
	 * 3. URL userinfo (https://user:pass@) becomes "***@"; quoted literals
	 *    in SQL become '***'.
	 * 4. Bearer / Basic credentials; key=, token=, password=, secret=, ...
	 *    values, also with quoted (JSON) keys and values and URL-encoded
	 *    separators (token%3D...).
	 * 5. Query strings (absolute and relative URLs), emails, IPv4/IPv6, long
	 *    hex and base64-like runs. Every rule is linear in the input.
	 *
	 * @param string $text Raw text.
	 */
	private function mask( string $text ): string {
		$text = $this->strip_controls( $text );
		// JSON-escaped slashes ("https:\/\/..", "Zm9v\/Y2Vk..") are read as "/", so
		// URL, path and base64 rules see the real text. "\\" pairs stay as they are.
		$text = strtr(
			$text,
			array(
				'\\\\' => '\\\\',
				'\\/'  => '/',
			)
		);
		$text = $this->relative_paths( $text );
		$text = $this->replace( '#\b([a-z][a-z0-9+.-]*://)[^\s/?\#@]++@#i', '$1***@', $text );
		$text = $this->redact_sql_literals( $text );
		$text = $this->redact_query_secrets( $text );

		// No word boundary in front: "dirBearer <token>" is masked too.
		$text = $this->replace( '/(Bearer|Basic)\s+[^\s,;"\']+/i', '$1 ***', $text );
		$text = $this->redact_key_values( $text );

		$patterns = array(
			'#(\bhttps?://[^\s?\#]+)\?[^\s\#]*#i'         => '$1',
			'#\?[^\s?\#=&]+=[^\s\#]*#'                    => '',
			'/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i'    => '***@***',
			'/\b(?:\d{1,3}\.){3}\d{1,3}\b/'               => '***',
			'/\b(?:[0-9a-f]{1,4}:){2,7}[0-9a-f]{1,4}\b/i' => '***',
		);
		foreach ( $patterns as $pattern => $replacement ) {
			$text = $this->replace( $pattern, $replacement, $text );
		}

		// Base64-like runs of 32+ bytes that contain a digit. A run may hold
		// JSON escapes (\/ and \\) and single inner separators ! " ' | ~ $ ^ ` % \
		// between base64 characters, so a key split by one of them is masked
		// as a whole instead of leaving a part shorter than 32 behind.
		// Possessive match, length and digit check in the callback: one pass.
		// Runs before the hex rule, so a hex key split by a separator is one run.
		$masked = preg_replace_callback(
			'#(?<![\w\\\\/.-])(?:[A-Za-z0-9+/_-]|\\\\[\\\\/])++(?:[!"\'|~$^`%\\\\](?:[A-Za-z0-9+/_-]|\\\\[\\\\/])++)*+={0,2}#',
			static function ( array $m ): string {
				return strlen( $m[0] ) < 32 || false === strpbrk( $m[0], '0123456789' ) ? $m[0] : '***';
			},
			$text
		);
		$text   = is_string( $masked ) ? $masked : $text;

		return $this->replace( '/\b[0-9a-f]{32,}\b/i', '***', $text );
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
	 * Control characters (C0, DEL, C1 incl. U+0085 NEL), line / paragraph
	 * separators (U+2028, U+2029) and bidi controls (U+061C, U+200E, U+200F,
	 * U+202A-U+202E, U+2066-U+2069) become one space per run.
	 *
	 * @param string $text Raw text.
	 */
	private function strip_controls( string $text ): string {
		$clean = preg_replace( '/[\p{Cc}\p{Zl}\p{Zp}\x{061C}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]+/u', ' ', $text );
		if ( ! is_string( $clean ) ) {
			// Not valid UTF-8: replace byte-wise, including the UTF-8 encodings of the characters above.
			$clean = preg_replace( '/(?:[\x00-\x1F\x7F]|\xC2[\x80-\x9F]|\xD8\x9C|\xE2\x80[\x8E\x8F\xA8-\xAE]|\xE2\x81[\xA6-\xA9])+/', ' ', $text );
		}

		return is_string( $clean ) ? $clean : '';
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

		// Directories after a Windows drive letter or a UNC server / share prefix.
		// A directory may contain spaces and parentheses (C:\Program Files (x86)\).
		// Possessive throughout and the input is capped at EXCEPTION_MESSAGE_SCAN_MAX: linear.
		$text = $this->replace( '#(?<!\w)(?:[A-Za-z]:|\\\\\\\\[^\s\\\\/]++)[\\\\/](?:[^\s\\\\/:*?"\'<>|]++(?: ++[^\s\\\\/:*?"\'<>|]++)*+[\\\\/])*+#', '[path]/', $text );

		// Unix directories. A URL path is preceded by its host, so it is left alone.
		// The first directory is one word (/home/, /chroot/, /u01/). A deeper
		// directory may contain spaces (/home/alice smith/x/) when
		// - its words hold no comma and no non-ASCII byte (Chinese text and
		// punctuation), and
		// - it is followed by another directory, a file name with an extension,
		// or the end of the path (whitespace, quote, bracket, , ; : or end).
		// So prose such as "use /api/v1 then foo bar/baz" or "调用 /wp-json/v1
		// 失败，请检查 and/or" keeps its words. Possessive throughout and the
		// look-ahead reads one path part: linear.
		$word   = '[^\s/:\'"()<>\[\]]++';
		$sword  = '[^\s/:\'"()<>\[\],\x80-\xFF]++';
		$file   = '(?:[^\s/:\'"()<>\[\],.\x80-\xFF]++ ++)*+[^\s/:\'"()<>\[\].]*+\.[A-Za-z0-9]';
		$next   = '(?=' . $word . '/|' . $sword . '(?: ++' . $sword . ')*+/|' . $file . '|[\s\'"()<>\[\],;:]|$)';
		$spaced = $sword . '(?: ++' . $sword . ')++/' . $next;

		return $this->replace(
			'#(?<![\w.:/\]])/(?:' . $word . '/)++(?:' . $word . '/|' . $spaced . ')*+#',
			'[path]/',
			$text
		);
	}

	/**
	 * Quoted literals in an SQL statement become '***'.
	 *
	 * @param string $text Text.
	 */
	private function redact_sql_literals( string $text ): string {
		if ( ! preg_match( '/\b(?:SELECT|INSERT|UPDATE|DELETE|REPLACE)\b.*\b(?:FROM|INTO|SET|WHERE|VALUES)\b/is', $text ) ) {
			return $text;
		}

		return $this->mask_quoted( $this->mask_quoted( $text, "'" ), '"' );
	}

	/**
	 * Quoted literals with quote $q become '***'.
	 *
	 * A quote opens a literal only when no word character is in front of it
	 * and closes it only when no word character follows, so "doesn't" is not
	 * a quote; a quote with a word character behind it inside a literal
	 * ('it's here') stays in the literal. Linear: a literal that never closes
	 * means no later one can close either (same scan from there on), so the
	 * scan stops.
	 *
	 * @param string $text Text.
	 * @param string $q    Quote character, ' or ".
	 */
	private function mask_quoted( string $text, string $q ): string {
		$open    = '/(?<!\w)' . $q . '/';
		$literal = '/\G' . $q . '(?:[^' . $q . '\\\\]++|\\\\.|' . $q . '(?=\w))*+' . $q . '(?!\w)/s';
		$out     = '';
		$offset  = 0;
		while ( 1 === preg_match( $open, $text, $o, PREG_OFFSET_CAPTURE, $offset ) ) {
			$start = $o[0][1];
			if ( 1 !== preg_match( $literal, $text, $l, 0, $start ) ) {
				break;
			}
			$out   .= substr( $text, $offset, $start - $offset ) . "'***'";
			$offset = $start + strlen( $l[0] );
		}

		return $out . substr( $text, $offset );
	}

	/**
	 * Mask key=value / "key":"value" secrets. Quotes around the key and the
	 * value are kept; an already masked Bearer / Basic value is left alone.
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
	private function utf8_cut( string $text, int $max, bool $use_mb ): string {
		if ( $use_mb ) {
			return mb_strcut( $text, 0, $max, 'UTF-8' );
		}

		$cut = substr( $text, 0, $max );

		// Drop a trailing lead byte whose continuation bytes were cut off.
		return $this->replace( '/(?:[\xC0-\xDF]|[\xE0-\xEF][\x80-\xBF]?|[\xF0-\xF7][\x80-\xBF]{0,2})$/', '', $cut );
	}

	/**
	 * Class name safe for one log line.
	 *
	 * Anonymous classes are named "Parent@anonymous" followed by a NUL byte and
	 * the absolute file path; keep only the part before the NUL.
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

	/**
	 * Mask api_key= and license_key= query values. Never keeps the secret.
	 *
	 * @param string $value Raw string.
	 */
	private function redact_query_secrets( string $value ): string {
		$redacted = preg_replace( '/((?:api_key|license_key)=)([^&\s]*)/i', '$1***', $value );
		return is_string( $redacted ) ? $redacted : $value;
	}

	/**
	 * Strip query string from a URL.
	 *
	 * @param string $url Raw URL.
	 */
	private function redact_url( string $url ): string {
		$parts = function_exists( 'wp_parse_url' )
			? wp_parse_url( $url )
			: parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- unit bootstrap has no WordPress.
		if ( ! is_array( $parts ) ) {
			return $url;
		}

		$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '';
		$host   = isset( $parts['host'] ) ? $parts['host'] : '';
		$port   = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
		$path   = isset( $parts['path'] ) ? $parts['path'] : '';

		return $scheme . $host . $port . $path;
	}

	/**
	 * Numeric priority for a level name. Unknown levels count as error.
	 *
	 * @param string $level Level name.
	 */
	private function priority( string $level ): int {
		return isset( self::PRIORITY[ $level ] ) ? self::PRIORITY[ $level ] : self::PRIORITY['error'];
	}
}
