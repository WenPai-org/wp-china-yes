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
	 * URL query strings, Bearer tokens, key=/token=/password= values, emails,
	 * IPv4/IPv6 addresses, and long hex / base64-like runs are replaced.
	 *
	 * @param string $text Raw text (e.g. an exception message).
	 */
	private function scrub( string $text ): string {
		$text     = $this->redact_query_secrets( $text );
		$patterns = array(
			'#(\bhttps?://[^\s?\#]+)\?[^\s\#]*#i'         => '$1',
			'/\bBearer\s+[^\s,;]+/i'                      => 'Bearer ***',
			'/\b((?:[a-z_]*key|[a-z_]*token|password|passwd|pwd|secret|credential|auth)\s*[=:]\s*)("[^"]*"|\'[^\']*\'|[^\s&,;]+)/i' => '$1***',
			'/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i'    => '***@***',
			'/\b(?:\d{1,3}\.){3}\d{1,3}\b/'               => '***',
			'/\b(?:[0-9a-f]{1,4}:){2,7}[0-9a-f]{1,4}\b/i' => '***',
			'/\b[0-9a-f]{32,}\b/i'                        => '***',
			'#(?<![\w\\\\/.-])(?=[A-Za-z0-9+/_-]*\d)[A-Za-z0-9+/_-]{32,}={0,2}#' => '***',
		);
		foreach ( $patterns as $pattern => $replacement ) {
			$replaced = preg_replace( $pattern, $replacement, $text );
			if ( is_string( $replaced ) ) {
				$text = $replaced;
			}
		}

		if ( strlen( $text ) > self::EXCEPTION_MESSAGE_MAX ) {
			$text  = function_exists( 'mb_strcut' )
				? mb_strcut( $text, 0, self::EXCEPTION_MESSAGE_MAX, 'UTF-8' )
				: substr( $text, 0, self::EXCEPTION_MESSAGE_MAX );
			$text .= '…';
		}

		return $text;
	}

	/**
	 * File path relative to the plugin root; basename when outside it.
	 *
	 * @param string $file Absolute file path.
	 */
	private function relative_file( string $file ): string {
		$root = dirname( __DIR__, 2 ) . DIRECTORY_SEPARATOR;
		if ( 0 === strpos( $file, $root ) ) {
			return str_replace( DIRECTORY_SEPARATOR, '/', substr( $file, strlen( $root ) ) );
		}

		return basename( $file );
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
