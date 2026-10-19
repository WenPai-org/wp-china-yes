<?php
/**
 * Sodium secretbox with a purpose-scoped key. Same algorithm as CredentialStore.
 *
 * CredentialStore::key() is private and cannot take a purpose, so this class
 * copies the ~30-line seal/open primitive and derives
 * SHA-256( wp_salt('auth') . '|provider:{id}' ) instead of SHA-256(salt).
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seals plaintext as Base64(nonce || ciphertext). Never logs plaintext.
 */
final class SecretBox {

	/**
	 * Key-derivation purpose, e.g. provider:weixiaoduo-mall.
	 *
	 * @var string
	 */
	private string $purpose;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param string $purpose Non-empty purpose string mixed into the key.
	 */
	public function __construct( string $purpose ) {
		$this->purpose = $purpose;
	}

	/**
	 * Seal a secret. Returns Base64(nonce || ciphertext).
	 *
	 * @since 4.0.0
	 *
	 * @param string $plaintext Secret. Never logged.
	 * @return string|null Null when sodium is unavailable or sealing fails.
	 */
	public function seal( string $plaintext ) {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			return null;
		}

		$key   = $this->key();
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$box   = sodium_crypto_secretbox( $plaintext, $nonce, $key );

		return base64_encode( $nonce . $box ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- sodium ciphertext encoding required by providers.md §2.
	}

	/**
	 * Open a stored secret. Returns null on failure. Never logs plaintext.
	 *
	 * @since 4.0.0
	 *
	 * @param string $stored Base64 ciphertext.
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
	 * 32-byte secretbox key. Purpose keeps provider ciphertext distinct from binding.
	 *
	 * @since 4.0.0
	 *
	 * @return string
	 */
	private function key(): string {
		$salt = function_exists( 'wp_salt' ) ? (string) wp_salt( 'auth' ) : 'wpcy-test-salt';
		return hash( 'sha256', $salt . '|' . $this->purpose, true );
	}
}
