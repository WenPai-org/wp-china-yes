<?php
/**
 * Encrypt binding credentials with wp_salt('auth') and sodium secretbox.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Services\SiteBinding;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seals plaintext credentials as Base64 for wpcy_site_identity.binding.credential.
 *
 * Algorithm matches docs/dev/security.md: SHA-256 of wp_salt('auth') as the
 * secretbox key; nonce prepended to ciphertext. Plaintext is never logged.
 */
final class CredentialStore {

	/**
	 * Seal a credential. Returns Base64(nonce || ciphertext).
	 *
	 * @since 4.0.0
	 *
	 * @param string $plaintext Secret from the license server. Never logged.
	 * @return string|null Null when sodium is unavailable or sealing fails.
	 */
	public function seal( string $plaintext ) {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			return null;
		}

		$key   = $this->key();
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$box   = sodium_crypto_secretbox( $plaintext, $nonce, $key );

		return base64_encode( $nonce . $box ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- sodium ciphertext encoding required by spec §4.
	}

	/**
	 * Open a stored credential. Returns null on failure. Never logs plaintext.
	 *
	 * @since 4.0.0
	 *
	 * @param string $stored Base64 ciphertext from the identity option.
	 * @return string|null
	 */
	public function open( string $stored ) {
		if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
			return null;
		}

		$raw = base64_decode( $stored, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- inverse of seal().
		if ( ! is_string( $raw ) ) {
			return null;
		}

		$nonce_size = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
		if ( strlen( $raw ) < $nonce_size ) {
			return null;
		}

		$nonce = substr( $raw, 0, $nonce_size );
		$box   = substr( $raw, $nonce_size );
		$plain = sodium_crypto_secretbox_open( $box, $nonce, $this->key() );

		return is_string( $plain ) ? $plain : null;
	}

	/**
	 * Key from wp_salt('auth'). KDF rounds remain 待定（M0 / security.md）.
	 *
	 * @since 4.0.0
	 *
	 * @return string Binary 32-byte key.
	 */
	private function key(): string {
		$salt = function_exists( 'wp_salt' ) ? (string) wp_salt( 'auth' ) : 'wpcy-test-salt';
		return hash( 'sha256', $salt, true );
	}
}
