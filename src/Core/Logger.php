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
	 * Unix directories, see relative_paths(). An escaped slash still marked
	 * with \x01 (see mask()) may start a path whatever is in front of it.
	 */
	private const UNIX_PATH = '#(?:(?<![\w.:/\]])|(?<=file://|phar://|FILE://|PHAR://))/(?:[^\s/:\'"()<>\[\]]++/)++(?:[^\s/:\'"()<>\[\]]++(?: ++[^\s/:\'"()<>\[\]]++)*+/)*+#';

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
	 * A cut inside a 1200-byte run of token bytes keeps nothing. An
	 * unfinished query parameter name or URL userinfo at the end goes too
	 * (drop_open_query(), drop_open_userinfo()).
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
	 * The query rule needs "?name=": "#?name(=value\"..." cut (or dropped
	 * back by the run-join rounds above) before the "=" would keep
	 * "?name(" in clear. Decided on the raw text, as for the userinfo: when
	 * the name run that starts at the last "?" of the cut (bytes the rule
	 * reads in a name: not whitespace, "?", "#", "=", "&") goes on past the
	 * cut and ends at "=", everything from that "?" on is dropped. Only a
	 * cut that ends inside such a name reads on, with one strcspn() over
	 * the rest of the raw text: linear.
	 *
	 * @param string $cut  Raw text after the cut.
	 * @param string $text Raw text before the cut ($cut is its prefix).
	 */
	private function drop_open_query( string $cut, string $text ): string {
		$query = strrpos( $cut, '?' );
		if ( false === $query ) {
			return $cut;
		}

		$stops = " \t\n\r\v\f?#=&";
		$kept  = strlen( $cut ) - $query - 1;
		if ( 0 === $kept || strcspn( $cut, $stops, $query + 1 ) < $kept ) {
			return $cut;
		}

		$name = $kept + strcspn( $text, $stops, strlen( $cut ) );
		if ( '=' !== substr( $text, $query + 1 + $name, 1 ) ) {
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
	 * "https://bob(x:pw@host" cut before "@" would keep "https://bob(" and
	 * the userinfo rule (which needs the "@") could no longer mask "bob".
	 *
	 * Decided on the raw text, not on the cut: from the last "://" of the cut,
	 * look for the first byte the userinfo rule does not read across
	 * (whitespace, "/", "?", "#"; any other byte, also non-ASCII such as a
	 * Chinese comma, can be in what it masks) or "@". Only an "@" past
	 * the cut means the cut ends inside a userinfo: everything from "://" on
	 * is dropped. An "@" inside the cut is masked by the userinfo rule; any
	 * other end (also the end of the text) means a complete URL, which is
	 * kept: the userinfo rule could not match it in the whole text either.
	 * One strcspn() over the rest of the raw text: linear.
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
		$rest = substr( $text, $scheme + 3 );
		$stop = strcspn( $rest, " \t\n\r\v\f/?#@" );
		if ( $stop >= strlen( $rest ) || '@' !== $rest[ $stop ] || $stop < $kept ) {
			return $cut;
		}

		return substr( $cut, 0, $scheme );
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
		// Until the path rule has run, an escaped slash carries a marker byte
		// (\x01; strip_controls() leaves none in the text): a path may start at
		// an escaped slash whatever is in front of it ("file:\/\/\/home\/..",
		// "src\/home\/alice\/x"), as on the raw text before the slashes were read.
		$text = strtr(
			$text,
			array(
				'\\\\' => '\\\\',
				'\\/'  => "\x01/",
			)
		);
		// URL userinfo before paths: the path rule may take the "//" of an escaped URL.
		$text = $this->replace( '#\b([a-z][a-z0-9+.-]*:\x01?/\x01?/)[^\s/?\#@]++@#i', '$1***@', $text );
		$text = $this->mask_escaped_runs( $text );
		$text = str_replace( "\x01", '', $this->relative_paths( $text ) );
		$text = $this->redact_sql_literals( $text );
		$text = $this->redact_query_secrets( $text );

		$text = $this->redact_auth_schemes( $text, false );
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
		$text = $this->redact_auth_schemes( $text, true );

		// Base64-like runs of 32+ bytes that contain a digit. A run may hold
		// JSON escapes (\/ and \\) and single inner separators ! " ' | ~ $ ^ ` % \
		// between base64 characters, so a key split by one of them is masked
		// as a whole instead of leaving a part shorter than 32 behind.
		// Possessive match, length and digit check in the callback: one pass.
		// Runs before the hex rule, so a hex key split by a separator is one run.
		// A PHP class name is kept for diagnosis: three or more parts split by
		// "\" (or "\\"), each made of capitalised words (a capital, then
		// lower case letters) and at most four digits at the end, e.g.
		// WenPai\ChinaYes\Module\Updater2. A key has no "\", and a random run
		// almost never has this shape in every part. A part of 32+ bytes with
		// a digit is still masked.
		$masked = preg_replace_callback(
			'#(?<![\w\\\\/.-])(?:[A-Za-z0-9+/_-]|\\\\[\\\\/])++(?:[!"\'|~$^`%\\\\](?:[A-Za-z0-9+/_-]|\\\\[\\\\/])++(?!:))*+={0,2}#',
			static function ( array $m ): string {
				return self::is_key_run( $m[0] ) ? '***' : $m[0];
			},
			$text
		);
		$text   = is_string( $masked ) ? $masked : $text;
		$text   = $this->replace( '/\b[0-9a-f]{32,}\b/i', '***', $text );

		// A plain run right after a masked hex key ("<hex>/<base64>"), as
		// when the hex rule came first.
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
	 * Scheme words in a row ("X-Cardbearer Bearer <token>", "basic Basic
	 * <creds>") are kept and the value after the last one is masked. A scheme
	 * glued to a word in front ("dirBearer <token>") is masked too when the
	 * value looks like a credential (a digit, one of + / = _ . -, a capital
	 * after the first character, or 8 hex letters in front, as in a cut-off
	 * "deadbeefca"), so "flagbearer of" and "NonBasic users" keep their words.
	 *
	 * A free-standing scheme runs before the key=value rule ($glued false),
	 * a glued one after the query, URL and e-mail rules ($glued true): those
	 * rules then see the text as they did when only a free-standing scheme
	 * was masked, and a glued scheme masks what they left.
	 *
	 * @param string $text  Text.
	 * @param bool   $glued Pass for glued scheme words.
	 */
	private function redact_auth_schemes( string $text, bool $glued ): string {
		$masked = preg_replace_callback(
			'/([A-Za-z0-9]?)((?:Bearer|Basic)(?:\s++(?:Bearer|Basic)(?=\s))*+)\s++((?:(?!(?:Bearer|Basic)\s)[^\s,;"\'])*+)/i',
			static function ( array $m ) use ( $text, $glued ): string {
				$at = $m[2][1];
				// Only the last scheme word counts: "xbearer Bearer <value>" is
				// not glued. The character in front may be the end of the match
				// before ("Basic Q0ZBearer <value>").
				$is_glued = $at > 0 && 1 === preg_match( '/[A-Za-z0-9]/', $text[ $at - 1 ] ) && strlen( $m[2][0] ) === strcspn( $m[2][0], " \t\n\r\v\f" );
				$value    = $m[3][0];
				// An empty value still matches, so a run of scheme words with
				// nothing after it is passed over once, not retried from each
				// word ("Bearer Bearer ... " stays linear).
				if ( $is_glued !== $glued || '' === $value ) {
					return $m[0][0];
				}
				$keys = '/((?<![a-z0-9])(?:[a-z_]*key|[a-z_]*token|password|passwd|pwd|secret|credential|authorization|auth)(?:[=:]|%3D|%3A))/i';
				if ( $glued ) {
					// Keys are masked by now ("xBearer token=***").
					if ( 1 === preg_match( $keys, $value ) || 1 !== preg_match( '/[0-9+\/=_.-]|.[A-Z]|^[a-f]{8}/', $value ) ) {
						return $m[0][0];
					}
					return $m[1][0] . $m[2][0] . ' ***';
				}
				// A query string in the value ("Bearer abc?x=1", "Bearer ?x=1")
				// is left to the query rule, which drops it whole up to a space
				// or "#".
				$q = strpos( $value, '?' );
				if ( false !== $q && false === strpos( $value, '#', $q ) && 1 === preg_match( '/\G\?[^\s?\#=&]++=/', $text, $qm, 0, $m[3][1] + $q ) ) {
					return 0 === $q ? $m[0][0] : $m[1][0] . $m[2][0] . ' ***' . substr( $value, $q );
				}
				// Keys in the value ("Bearer token= <value>", "Bearer x&token=y")
				// are kept and only the text between them is masked: the
				// key=value rule then masks what follows the last key too.
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
	 * Whether a base64-like run is masked: 32+ bytes with a digit, unless it
	 * is a PHP class name (see mask()) whose parts are all shorter than 32
	 * bytes or have no digit.
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
		// A part of 32+ bytes with a digit is a key on its own (masked
		// without the class name rule too): no exception then.
		foreach ( preg_split( '/\\\\++/', $run ) as $part ) {
			if ( strlen( $part ) >= 32 && false !== strpbrk( $part, '0123456789' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Length of $run without a URL scheme at its end ("<key>\/x/https"
	 * before "://"): the scheme stays for the URL rules, as when the "\" of
	 * the escaped slash ended the run.
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
	 * Mask base64-like runs that hold an escaped slash ("\x01/" in mask())
	 * before the path rule could split them ("Zm9v\/Y2Vk..\/Zm9v"). A run
	 * that starts with an escaped slash is left to the path rule, as a
	 * "/..." run is without escapes. Same alphabet, separators and
	 * decision as the base64-like rule in mask(), so the run is masked as
	 * it is without escapes. Possessive, one pass: linear.
	 *
	 * @param string $text Text with escaped slashes marked.
	 */
	private function mask_escaped_runs( string $text ): string {
		if ( false === strpos( $text, "\x01" ) ) {
			return $text;
		}
		// Paths as the path rule would see them now. A run that one of them
		// crosses into or out of is left to the path rule, so nothing it
		// masked on the raw text is shown ("x/Q0Z&<key>\/", "<key>\/a，b/").
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
				// A word after the last escaped slash ("<key>\/Basic <token>",
				// "<key>\/token=x", "<key>\//home/Basic") stays for the scheme
				// and key=value rules. A run before "@" is left to the e-mail
				// rule, "=" after a run to the query and key=value rules.
				$run = substr( $run, 0, self::run_without_scheme( $run, (string) substr( $text, $start + strlen( $run ), 3 ) ) );
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

		// Directories after a Windows drive letter or a UNC server / share prefix.
		// A directory may contain spaces and parentheses (C:\Program Files (x86)\).
		// The \x01 of an escaped slash (see mask()) is a separator, as the "\" was.
		// Possessive throughout and the input is capped at EXCEPTION_MESSAGE_SCAN_MAX: linear.
		$text = $this->replace( '#(?<!\w)(?:[A-Za-z]:|\\\\\\\\[^\s\\\\/\x01]++)[\\\\/\x01](?:[^\s\\\\/:*?"\'<>|\x01]++(?: ++[^\s\\\\/:*?"\'<>|\x01]++)*+[\\\\/\x01])*+#', '[path]/', $text );

		// Unix directories. A URL path is preceded by its host, so it is left alone.
		// The first directory is one word (/home/, /chroot/, /u01/); deeper
		// directories may contain spaces, commas and any non-ASCII text
		// (/home/alice smith/x/, /home/张 三，李四/site). Prose such as
		// "see /docs for ... and/or" is not a path: no "/" right after "docs".
		// Prose with a "/" after a word ("use /api/v1 then foo bar/baz") is
		// masked like a path: a user directory cannot be told from it, so
		// more masking, no leak.
		// A file or phar URL with an empty host (file:///home/..,
		// phar:///home/..) is followed by a local path.
		// Possessive throughout and the input is capped: linear.
		return $this->replace( self::UNIX_PATH, '[path]/', $text );
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

		return $this->mask_quoted( $text );
	}

	/**
	 * Quoted literals ('...' or "...") become '***'.
	 *
	 * Two readings of the quotes are masked together (the union of their
	 * byte ranges):
	 * - plain: each ' or " closes at the next one of its kind;
	 * - contractions: a ' between a letter and an English contraction ending
	 *   (s, t, re, ve, ll, d, m, then a word end: doesn't, it's) is an
	 *   apostrophe, so 'it's here' is one literal.
	 * Then a quote left outside every masked range (one that never pairs up)
	 * masks the rest of the text from the start of its word. More masking,
	 * no leak: the text after doesn't may be masked as well.
	 *
	 * Linear: one match per literal in each reading, then one merge pass.
	 *
	 * @param string $text Text.
	 */
	private function mask_quoted( string $text ): string {
		$apostrophe = '(?<=[A-Za-z])\'(?=(?i:s|t|re|ve|ll|d|m)\b)';
		$plain      = '\'(?:[^\'\\\\]++|\\\\.)*+\'|\'';
		$contracted = '(?!' . $apostrophe . ')\'(?:[^\'\\\\]++|\\\\.|' . $apostrophe . ')*+\'|(?!' . $apostrophe . ')\'';
		$first      = $this->quoted_spans( $text, array( $plain ) );
		$second     = $this->quoted_spans( $text, array( $contracted, $plain ) );

		// Both lists run left to right: merge them by start in one pass.
		$spans = array();
		for ( $i = 0, $j = 0, $n1 = count( $first ), $n2 = count( $second ); $i < $n1 || $j < $n2; ) {
			$spans[] = ( $j >= $n2 || ( $i < $n1 && $first[ $i ][0] <= $second[ $j ][0] ) ) ? $first[ $i++ ] : $second[ $j++ ];
		}

		$out = '';
		$end = 0;
		foreach ( $spans as $span ) {
			if ( $span[0] < $end ) {
				// Overlaps the masked range before it: extend that range.
				$end = max( $end, $span[1] );
				continue;
			}
			$gap = substr( $text, $end, $span[0] - $end );
			if ( false !== strpbrk( $gap, '\'"' ) ) {
				break;
			}
			$out .= ( 0 === $end ? '' : "'***'" ) . $gap;
			$end  = $span[1];
		}
		if ( 0 !== $end ) {
			$out .= "'***'";
		}

		$rest  = substr( $text, $end );
		$quote = strcspn( $rest, '\'"' );
		if ( $quote < strlen( $rest ) ) {
			// A quote that never pairs up: mask from the start of its word
			// ("hunter2pass'" after 'O'Brien' AND p=') to the end.
			$word = $quote - strcspn( strrev( substr( $rest, 0, $quote ) ), " \t\n\r\v\f" );
			return $out . substr( $rest, 0, $word ) . "'***";
		}

		return $out . $rest;
	}

	/**
	 * Byte ranges [start, end) of quoted literals, left to right.
	 *
	 * One match per literal. A quote that never closes matches alone and
	 * changes the state: no later " can close either; for ' the next reading
	 * in $single is used, and after the last one no later ' can close.
	 *
	 * @param string   $text   Text.
	 * @param string[] $single Patterns for a ' literal, each ending in |' (an unclosed quote).
	 * @return array<int, array{0: int, 1: int}>
	 */
	private function quoted_spans( string $text, array $single ): array {
		$double = '"(?:[^"\\\\]++|\\\\.)*+"|"';
		$state  = 0;
		$offset = 0;
		$spans  = array();
		while ( null !== $double || isset( $single[ $state ] ) ) {
			$pattern = '/' . implode( '|', array_filter( array( $double, $single[ $state ] ?? null ) ) ) . '/s';
			if ( 1 !== preg_match( $pattern, $text, $m, PREG_OFFSET_CAPTURE, $offset ) ) {
				break;
			}
			$found = $m[0][0];
			if ( 1 === strlen( $found ) ) {
				if ( '"' === $found ) {
					$double = null;
				} else {
					++$state;
				}
				continue;
			}
			$spans[] = array( $m[0][1], $m[0][1] + strlen( $found ) );
			$offset  = $m[0][1] + strlen( $found );
		}

		return $spans;
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
