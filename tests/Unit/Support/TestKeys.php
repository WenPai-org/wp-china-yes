<?php
/**
 * Throwaway Ed25519 key pair for unit tests.
 *
 * The pair is generated once per PHP process with ext-sodium. No private key
 * is stored in this repository; the optional key file lives in the system
 * temp directory and is removed at shutdown.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-process TEST ONLY signing key.
 */
final class TestKeys {

	/**
	 * Kid values the verifiers know about.
	 *
	 * @since 4.0.0
	 * @var list<string>
	 */
	public const KIDS = array(
		'wpcy-ruleset-2026',
		'wpcy-apps-2026',
	);

	/**
	 * Raw sodium key pair, empty until first use.
	 *
	 * @var string
	 */
	private static string $keypair = '';

	/**
	 * Temp file holding the Base64 secret key, empty until requested.
	 *
	 * @var string
	 */
	private static string $secret_file = '';

	/**
	 * Raw secret key bytes.
	 *
	 * @since 4.0.0
	 */
	public static function secret(): string {
		return sodium_crypto_sign_secretkey( self::keypair() );
	}

	/**
	 * Base64 public key.
	 *
	 * @since 4.0.0
	 */
	public static function public_key(): string {
		return sodium_bin2base64( sodium_crypto_sign_publickey( self::keypair() ), SODIUM_BASE64_VARIANT_ORIGINAL );
	}

	/**
	 * Kid map pointing every known kid at the throwaway public key.
	 *
	 * @since 4.0.0
	 * @return array<string, string>
	 */
	public static function keys_by_kid(): array {
		return array_fill_keys( self::KIDS, self::public_key() );
	}

	/**
	 * Path of a temp file with the Base64 secret key, for the sign-ruleset CLI.
	 *
	 * @since 4.0.0
	 */
	public static function secret_key_file(): string {
		if ( '' !== self::$secret_file && is_file( self::$secret_file ) ) {
			return self::$secret_file;
		}

		$path = tempnam( sys_get_temp_dir(), 'wpcy-test-key-' );
		if ( false === $path ) {
			return '';
		}

		chmod( $path, 0600 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- temp key file, owner only.
		$body = "# TEST ONLY throwaway key, generated at test runtime.\n"
			. sodium_bin2base64( self::secret(), SODIUM_BASE64_VARIANT_ORIGINAL ) . "\n";
		file_put_contents( $path, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- temp key file.

		self::$secret_file = $path;
		register_shutdown_function( array( self::class, 'cleanup' ) );
		return $path;
	}

	/**
	 * Remove the temp key file.
	 *
	 * @since 4.0.0
	 */
	public static function cleanup(): void {
		if ( '' !== self::$secret_file && is_file( self::$secret_file ) ) {
			unlink( self::$secret_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- temp key file.
		}
		self::$secret_file = '';
	}

	/**
	 * Lazily generated key pair.
	 */
	private static function keypair(): string {
		if ( '' === self::$keypair ) {
			self::$keypair = sodium_crypto_sign_keypair();
		}
		return self::$keypair;
	}
}
