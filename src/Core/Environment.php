<?php
/**
 * Current request scene.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Core;

use InvalidArgumentException;

/**
 * Request scene: admin / frontend / rest / cli / cron.
 */
final class Environment {

	public const ADMIN    = 'admin';
	public const FRONTEND = 'frontend';
	public const REST     = 'rest';
	public const CLI      = 'cli';
	public const CRON     = 'cron';

	/**
	 * Known request scenes.
	 *
	 * @var list<string>
	 */
	public const CONTEXTS = array(
		self::ADMIN,
		self::FRONTEND,
		self::REST,
		self::CLI,
		self::CRON,
	);

	/**
	 * Current scene.
	 *
	 * @var string
	 */
	private $context;

	/**
	 * Whether URL rewrite modules may run.
	 *
	 * @var bool
	 */
	private $allows_url_rewrite;

	/**
	 * Create a scene.
	 *
	 * @param string $context            One of CONTEXTS.
	 * @param bool   $allows_url_rewrite Whether rewrite modules may run.
	 *
	 * @throws InvalidArgumentException If $context is unknown.
	 */
	public function __construct( string $context, bool $allows_url_rewrite = true ) {
		if ( ! in_array( $context, self::CONTEXTS, true ) ) {
			throw new InvalidArgumentException( sprintf( 'Unknown request scene: %s', $context ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
		}

		$this->context            = $context;
		$this->allows_url_rewrite = $allows_url_rewrite;
	}

	/**
	 * Detect the current WordPress request scene.
	 *
	 * Falls back to frontend when WordPress is not loaded (unit tests).
	 */
	public static function detect(): self {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return new self( self::CLI );
		}

		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return new self( self::CRON );
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return new self( self::REST );
		}

		if ( function_exists( 'wp_doing_rest' ) && wp_doing_rest() ) {
			return new self( self::REST );
		}

		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return new self( self::ADMIN );
		}

		return new self( self::FRONTEND );
	}

	/**
	 * Current scene id.
	 */
	public function context(): string {
		return $this->context;
	}

	/**
	 * Whether URL rewrite modules may run in this request.
	 *
	 * @return bool
	 */
	public function allowsUrlRewrite(): bool { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- module-authoring.md / M1-05 contract.
		return $this->allows_url_rewrite;
	}
}
