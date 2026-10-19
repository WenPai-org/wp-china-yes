<?php
/**
 * Site-level provider options. No network options. autoload=false.
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
 * Public summary in wpcy_providers; secrets in wpcy_secure_provider_{id}_*.
 *
 * Email is stored only as email_masked + email_hash. Decrypt failure is
 * fail-closed: connection becomes disconnected and no placeholder is returned.
 */
final class Store {

	/**
	 * Public summary option.
	 *
	 * @since 4.0.0
	 */
	public const OPTION = 'wpcy_providers';

	/**
	 * Fresh product-list window (seconds).
	 *
	 * @since 4.0.0
	 */
	public const PRODUCTS_FRESH = 900;

	/**
	 * Stale product-list window when unreachable (seconds).
	 *
	 * @since 4.0.0
	 */
	public const PRODUCTS_STALE = 259200;

	/**
	 * Public document. Always contains both preset ids.
	 *
	 * @since 4.0.0
	 *
	 * @return array{schema_version: int, items: array<string, array<string, mixed>>}
	 */
	public function document(): array {
		$stored = function_exists( 'get_option' ) ? get_option( self::OPTION, array() ) : array();
		$items  = array();
		$raw    = is_array( $stored ) && isset( $stored['items'] ) && is_array( $stored['items'] )
			? $stored['items']
			: array();

		foreach ( Presets::ids() as $id ) {
			$row          = isset( $raw[ $id ] ) && is_array( $raw[ $id ] ) ? $raw[ $id ] : array();
			$items[ $id ] = $this->normalize_item( $row );
		}

		return array(
			'schema_version' => 1,
			'items'          => $items,
		);
	}

	/**
	 * One public item.
	 *
	 * @since 4.0.0
	 *
	 * @param string $id Provider id.
	 * @return array<string, mixed>
	 */
	public function item( string $id ): array {
		$doc = $this->document();
		return isset( $doc['items'][ $id ] ) ? $doc['items'][ $id ] : $this->normalize_item( array() );
	}

	/**
	 * Write public fields for one id. Does not touch secrets.
	 *
	 * @since 4.0.0
	 *
	 * @param string               $id    Provider id.
	 * @param array<string, mixed> $patch Fields to merge.
	 */
	public function put_item( string $id, array $patch ): void {
		$doc                 = $this->document();
		$doc['items'][ $id ] = $this->normalize_item( array_merge( $doc['items'][ $id ] ?? array(), $patch ) );
		$this->write_document( $doc );
	}

	/**
	 * Seal and store the license key. Empty plaintext is refused.
	 *
	 * @since 4.0.0
	 *
	 * @param string $id        Provider id.
	 * @param string $plaintext License key.
	 */
	public function put_license_key( string $id, string $plaintext ): bool {
		if ( '' === $plaintext ) {
			return false;
		}
		$sealed = $this->box( $id )->seal( $plaintext );
		if ( ! is_string( $sealed ) || '' === $sealed ) {
			return false;
		}
		return $this->write_option( $this->license_option( $id ), $sealed );
	}

	/**
	 * Open the license key. Null on missing or decrypt failure (fail-closed).
	 *
	 * On decrypt failure the public connection is set to disconnected so
	 * callers never send an empty string or placeholder outbound.
	 *
	 * @since 4.0.0
	 *
	 * @param string $id Provider id.
	 * @return string|null
	 */
	public function license_key( string $id ) {
		$stored = $this->read_option( $this->license_option( $id ) );
		if ( ! is_string( $stored ) || '' === $stored ) {
			return null;
		}
		$plain = $this->box( $id )->open( $stored );
		if ( ! is_string( $plain ) || '' === $plain ) {
			$this->put_item(
				$id,
				array(
					'connection'      => 'disconnected',
					'last_checked_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
				)
			);
			return null;
		}

		return $plain;
	}

	/**
	 * Persist or create the WC AM instance UUID (secretbox).
	 *
	 * @since 4.0.0
	 *
	 * @param string $id Provider id.
	 */
	public function instance( string $id ): string {
		$existing = $this->read_instance( $id );
		if ( '' !== $existing ) {
			return $existing;
		}
		$uuid = $this->new_uuid();
		$this->put_instance( $id, $uuid );
		return $uuid;
	}

	/**
	 * Existing instance or a fresh UUID. Does not create a new option
	 * unless a leftover plaintext value is migrated.
	 *
	 * @since 4.0.0
	 *
	 * @param string $id Provider id.
	 */
	public function peek_instance( string $id ): string {
		$existing = $this->read_instance( $id );
		return '' !== $existing ? $existing : $this->new_uuid();
	}

	/**
	 * Decrypt the stored instance. Empty when missing.
	 *
	 * Leftover plaintext (pre-secretbox) is treated as the UUID and
	 * rewritten as ciphertext. Decrypt failure on a value that is not
	 * plaintext is fail-closed (empty string).
	 *
	 * @since 4.0.0
	 *
	 * @param string $id Provider id.
	 */
	public function read_instance( string $id ): string {
		$stored = $this->read_option( $this->instance_option( $id ) );
		if ( ! is_string( $stored ) || '' === $stored ) {
			return '';
		}
		$plain = $this->box( $id )->open( $stored );
		if ( is_string( $plain ) && '' !== $plain ) {
			return $plain;
		}
		if ( $this->looks_like_ciphertext( $stored ) ) {
			return '';
		}
		$this->put_instance( $id, $stored );
		return $stored;
	}

	/**
	 * Seal and persist the WC AM instance UUID. Empty plaintext is refused.
	 *
	 * @since 4.0.0
	 *
	 * @param string $id   Provider id.
	 * @param string $uuid Instance id.
	 */
	public function put_instance( string $id, string $uuid ): void {
		if ( '' === $uuid ) {
			return;
		}
		$sealed = $this->box( $id )->seal( $uuid );
		if ( ! is_string( $sealed ) || '' === $sealed ) {
			return;
		}
		$this->write_option( $this->instance_option( $id ), $sealed );
	}

	/**
	 * Product ids already activated for update checks.
	 *
	 * @since 4.0.0
	 *
	 * @param string $id Provider id.
	 * @return list<string>
	 */
	public function activated_products( string $id ): array {
		$item = $this->item( $id );
		$raw  = isset( $item['activated_products'] ) && is_array( $item['activated_products'] )
			? $item['activated_products']
			: array();
		$out  = array();
		foreach ( $raw as $pid ) {
			if ( is_string( $pid ) && '' !== $pid ) {
				$out[] = $pid;
			} elseif ( is_int( $pid ) || is_float( $pid ) ) {
				$out[] = (string) $pid;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Remember a product_id that activated successfully. Idempotent.
	 *
	 * @since 4.0.0
	 *
	 * @param string $id         Provider id.
	 * @param string $product_id Mall product id.
	 */
	public function mark_activated( string $id, string $product_id ): void {
		if ( '' === $product_id ) {
			return;
		}
		$have = $this->activated_products( $id );
		if ( in_array( $product_id, $have, true ) ) {
			return;
		}
		$have[] = $product_id;
		$this->put_item( $id, array( 'activated_products' => $have ) );
	}

	/**
	 * Mask + hash an email. Never stores plaintext.
	 *
	 * @since 4.0.0
	 *
	 * @param string $email Raw email.
	 * @return array{email_masked: string, email_hash: string}
	 */
	public function email_fields( string $email ): array {
		$email = strtolower( trim( $email ) );
		$at    = strpos( $email, '@' );
		$local = false === $at ? $email : substr( $email, 0, $at );
		$dom   = false === $at ? '' : substr( $email, $at + 1 );
		$first = '' !== $local ? substr( $local, 0, 1 ) : '';

		return array(
			'email_masked' => $first . '***@' . $dom,
			'email_hash'   => hash( 'sha256', $email ),
		);
	}

	/**
	 * Product-list cache payload or null.
	 *
	 * @since 4.0.0
	 *
	 * @param string $id Provider id.
	 * @return array{fetched_at: string, products: list<array<string, mixed>>}|null
	 */
	public function products_cache( string $id ) {
		$key = $this->products_transient( $id );
		$raw = function_exists( 'get_transient' ) ? get_transient( $key ) : false;
		if ( ! is_array( $raw ) ) {
			return null;
		}
		$fetched = isset( $raw['fetched_at'] ) && is_string( $raw['fetched_at'] ) ? $raw['fetched_at'] : '';
		$list    = isset( $raw['products'] ) && is_array( $raw['products'] ) ? $raw['products'] : array();
		if ( '' === $fetched ) {
			return null;
		}

		return array(
			'fetched_at' => $fetched,
			'products'   => $list,
		);
	}

	/**
	 * Whether the cache is within the 15-minute fresh window.
	 *
	 * @since 4.0.0
	 *
	 * @param array{fetched_at: string, products: list<array<string, mixed>>} $cache Cache.
	 */
	public function products_fresh( array $cache ): bool {
		return $this->age_seconds( $cache['fetched_at'] ) <= self::PRODUCTS_FRESH;
	}

	/**
	 * Whether the cache is within the 72-hour stale window.
	 *
	 * @since 4.0.0
	 *
	 * @param array{fetched_at: string, products: list<array<string, mixed>>} $cache Cache.
	 */
	public function products_stale_ok( array $cache ): bool {
		return $this->age_seconds( $cache['fetched_at'] ) <= self::PRODUCTS_STALE;
	}

	/**
	 * Write product cache. Transient TTL is 72h; freshness is fetched_at.
	 *
	 * @since 4.0.0
	 *
	 * @param string                     $id       Provider id.
	 * @param list<array<string, mixed>> $products Rows.
	 */
	public function put_products( string $id, array $products ): void {
		if ( ! function_exists( 'set_transient' ) ) {
			return;
		}
		set_transient(
			$this->products_transient( $id ),
			array(
				'fetched_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'products'   => $products,
			),
			self::PRODUCTS_STALE
		);
	}

	/**
	 * Delete license, instance, and product cache. Public item becomes disconnected.
	 *
	 * @since 4.0.0
	 *
	 * @param string $id Provider id.
	 */
	public function disconnect( string $id ): void {
		$this->delete_option( $this->license_option( $id ) );
		$this->delete_option( $this->instance_option( $id ) );
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( $this->products_transient( $id ) );
		}
		$this->put_item(
			$id,
			array(
				'connection'         => 'disconnected',
				'email_masked'       => null,
				'email_hash'         => null,
				'connected_at'       => null,
				'last_checked_at'    => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'product_count'      => null,
				'activated_products' => array(),
			)
		);
	}

	/**
	 * License option name.
	 *
	 * @since 4.0.0
	 *
	 * @param string $id Provider id.
	 */
	public function license_option( string $id ): string {
		return 'wpcy_secure_provider_' . $id . '_license_key';
	}

	/**
	 * Instance option name.
	 *
	 * @since 4.0.0
	 *
	 * @param string $id Provider id.
	 */
	public function instance_option( string $id ): string {
		return 'wpcy_secure_provider_' . $id . '_instance';
	}

	/**
	 * Products transient name.
	 *
	 * @since 4.0.0
	 *
	 * @param string $id Provider id.
	 */
	public function products_transient( string $id ): string {
		return 'wpcy_provider_' . $id . '_products';
	}

	/**
	 * Purpose-scoped sealer.
	 *
	 * @param string $id Provider id.
	 */
	private function box( string $id ): SecretBox {
		return new SecretBox( 'provider:' . $id );
	}

	/**
	 * Fill missing public fields.
	 *
	 * @param array<string, mixed> $row Raw row.
	 * @return array<string, mixed>
	 */
	private function normalize_item( array $row ): array {
		$connection = isset( $row['connection'] ) && is_string( $row['connection'] ) ? $row['connection'] : 'disconnected';
		if ( ! in_array( $connection, array( 'disconnected', 'connected', 'invalid', 'unreachable' ), true ) ) {
			$connection = 'disconnected';
		}

		$count = null;
		if ( array_key_exists( 'product_count', $row ) && is_int( $row['product_count'] ) ) {
			$count = $row['product_count'];
		} elseif ( array_key_exists( 'product_count', $row ) && is_numeric( $row['product_count'] ) ) {
			$count = (int) $row['product_count'];
		}

		$activated = array();
		if ( isset( $row['activated_products'] ) && is_array( $row['activated_products'] ) ) {
			foreach ( $row['activated_products'] as $pid ) {
				if ( is_string( $pid ) && '' !== $pid ) {
					$activated[] = $pid;
				} elseif ( is_int( $pid ) || is_float( $pid ) ) {
					$activated[] = (string) $pid;
				}
			}
			$activated = array_values( array_unique( $activated ) );
		}

		return array(
			'connection'         => $connection,
			'email_masked'       => isset( $row['email_masked'] ) && is_string( $row['email_masked'] ) ? $row['email_masked'] : null,
			'email_hash'         => isset( $row['email_hash'] ) && is_string( $row['email_hash'] ) ? $row['email_hash'] : null,
			'connected_at'       => isset( $row['connected_at'] ) && is_string( $row['connected_at'] ) ? $row['connected_at'] : null,
			'last_checked_at'    => isset( $row['last_checked_at'] ) && is_string( $row['last_checked_at'] ) ? $row['last_checked_at'] : null,
			'product_count'      => $count,
			'activated_products' => $activated,
		);
	}

	/**
	 * Persist the public document.
	 *
	 * @param array{schema_version: int, items: array<string, array<string, mixed>>} $doc Document.
	 */
	private function write_document( array $doc ): void {
		$this->write_option( self::OPTION, $doc );
	}

	/**
	 * Site option write, autoload=false.
	 *
	 * @param string $key   Option name.
	 * @param mixed  $value Value.
	 */
	private function write_option( string $key, $value ): bool {
		if ( ! function_exists( 'update_option' ) ) {
			return false;
		}
		return (bool) update_option( $key, $value, false );
	}

	/**
	 * Site option read.
	 *
	 * @param string $key Option name.
	 * @return mixed
	 */
	private function read_option( string $key ) {
		if ( ! function_exists( 'get_option' ) ) {
			return false;
		}
		return get_option( $key, false );
	}

	/**
	 * Site option delete.
	 *
	 * @param string $key Option name.
	 */
	private function delete_option( string $key ): void {
		if ( function_exists( 'delete_option' ) ) {
			delete_option( $key );
		}
	}

	/**
	 * Age of an ISO timestamp in seconds. Huge when unparseable.
	 *
	 * @param string $iso UTC ISO 8601.
	 */
	private function age_seconds( string $iso ): int {
		$ts = strtotime( $iso );
		if ( false === $ts ) {
			return PHP_INT_MAX;
		}
		$age = time() - $ts;
		return $age > 0 ? $age : 0;
	}

	/**
	 * Whether $stored looks like secretbox output rather than a UUID.
	 *
	 * Plain UUIDs are 36 chars with dashes; seal() is Base64(nonce||box)
	 * and is much longer. Used so decrypt failure on garbage ciphertext
	 * does not get rewritten as "plaintext".
	 *
	 * @param string $stored Option value.
	 */
	private function looks_like_ciphertext( string $stored ): bool {
		if ( strlen( $stored ) < 48 ) {
			return false;
		}
		$raw        = base64_decode( $stored, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- inverse of SecretBox::seal().
		$nonce_size = defined( 'SODIUM_CRYPTO_SECRETBOX_NONCEBYTES' ) ? SODIUM_CRYPTO_SECRETBOX_NONCEBYTES : 24;
		return is_string( $raw ) && strlen( $raw ) >= $nonce_size;
	}

	/**
	 * UUID v4.
	 */
	private function new_uuid(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}
		$data    = random_bytes( 16 );
		$data[6] = chr( ( ord( $data[6] ) & 0x0f ) | 0x40 );
		$data[8] = chr( ( ord( $data[8] ) & 0x3f ) | 0x80 );
		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
	}
}
