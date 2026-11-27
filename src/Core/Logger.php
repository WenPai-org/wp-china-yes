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
	 * Exception and secret scrubber.
	 *
	 * @var LogScrubber
	 */
	private LogScrubber $scrubber;

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
		$this->scrubber  = new LogScrubber();
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

		$suffix          = $this->scrubber->exception_suffix( $context['exception'] ?? null );
		$message         = $this->scrubber->redact_query_secrets( $message );
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
				$out[ $key ] = $this->scrubber->redact_exception( $value );
				continue;
			}

			if ( 'url' === $key && is_string( $value ) ) {
				$out[ $key ] = $this->redact_url( $this->scrubber->redact_query_secrets( $value ) );
				continue;
			}

			if ( is_array( $value ) ) {
				$out[ $key ] = $this->redact( $value );
				continue;
			}

			if ( is_string( $value ) ) {
				$out[ $key ] = $this->scrubber->redact_query_secrets( $value );
				continue;
			}

			$out[ $key ] = $value;
		}

		return $out;
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

	/**
	 * Delegate exception message scrubbing (kept for unit reflection tests).
	 *
	 * @param string $text Raw text.
	 * @phpstan-ignore method.unused
	 */
	private function scrub( string $text ): string {
		return $this->scrubber->scrub( $text );
	}

	/**
	 * Delegate log line masking (kept for unit reflection tests).
	 *
	 * @param string $text Raw text.
	 * @phpstan-ignore method.unused
	 */
	private function mask( string $text ): string {
		return $this->scrubber->mask( $text );
	}

	/**
	 * Delegate UTF-8 safe byte cut (kept for unit reflection tests).
	 *
	 * @param string $text   Text.
	 * @param int    $max    Byte limit.
	 * @param bool   $use_mb Use mb_strcut().
	 * @phpstan-ignore method.unused
	 */
	private function utf8_cut( string $text, int $max, bool $use_mb ): string {
		return $this->scrubber->utf8_cut( $text, $max, $use_mb );
	}
}
