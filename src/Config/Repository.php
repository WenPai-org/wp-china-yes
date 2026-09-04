<?php
/**
 * Dotted-path settings access. Business code must not call get_option().
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Merges Defaults ← network/site option ← (optional) site overrides.
 *
 * Unknown paths are discarded with a warning; they never throw.
 */
final class Repository implements \WenPai\ChinaYes\Core\Config {

	/**
	 * Optional logger: callable(string $level, string $message, array $context): void
	 * or an object with log().
	 *
	 * Callable is not a valid PHP 7.4 property type.
	 *
	 * @var callable|null
	 */
	private $logger;

	/**
	 * Schema walker used on every read and write.
	 *
	 * @var Validator
	 */
	private Validator $validator;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param callable|object|null $logger Logger from Core when present; optional in M1-03.
	 */
	public function __construct( $logger = null ) {
		$this->validator = new Validator();
		if ( is_callable( $logger ) ) {
			$this->logger = $logger;
		} elseif ( is_object( $logger ) && method_exists( $logger, 'log' ) ) {
			$this->logger = array( $logger, 'log' );
		} else {
			$this->logger = null;
		}
	}

	/**
	 * Read a dotted path from the effective (merged) settings.
	 *
	 * @since 4.0.0
	 *
	 * @param string $path          Path such as connectivity.avatar.
	 * @param mixed  $fallback_value Returned for unknown paths.
	 * @return mixed
	 */
	public function get( string $path, $fallback_value = null ) {
		if ( '' === $path ) {
			return $this->all();
		}
		if ( ! $this->is_readable_path( $path ) ) {
			$this->warn( 'Unknown config path discarded.', array( 'path' => $path ) );
			return $fallback_value;
		}
		$all = $this->all();
		if ( ! self::path_exists( $all, $path ) ) {
			return $fallback_value;
		}
		return self::path_get( $all, $path );
	}

	/**
	 * Whether a dotted path exists on the effective settings (including a null leaf).
	 *
	 * Unknown schema paths are false. Does not log.
	 *
	 * @since 4.0.0
	 *
	 * @param string $path Dotted key.
	 */
	public function has( string $path ): bool {
		if ( '' === $path || ! $this->is_readable_path( $path ) ) {
			return false;
		}
		return self::path_exists( $this->all(), $path );
	}

	/**
	 * Write a dotted path. Unknown paths are discarded.
	 *
	 * On multisite, connectivity/modules go to site overrides (when allowed);
	 * other settings keys go to the network option.
	 *
	 * @since 4.0.0
	 *
	 * @param string $path  Path such as modules.windfonts.
	 * @param mixed  $value New value.
	 * @return bool False when the path was discarded.
	 */
	public function set( string $path, $value ): bool {
		if ( ! $this->is_writable_path( $path ) ) {
			$this->warn( 'Unknown config path discarded.', array( 'path' => $path ) );
			return false;
		}

		if ( $this->is_multisite() && $this->is_override_path( $path ) ) {
			return $this->set_override_path( $path, $value );
		}

		$option  = $this->is_multisite() ? Schema::NETWORK_SETTINGS : Schema::SETTINGS;
		$current = $this->load_option( $option );
		$updated = self::path_set( $current, $path, $value );
		return $this->save_option( $option, $updated );
	}

	/**
	 * Effective settings: defaults merged with stored option(s).
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		if ( $this->is_multisite() ) {
			$base      = $this->load_option( Schema::NETWORK_SETTINGS );
			$allow     = ! empty( $base['allow_site_override'] );
			$overrides = $allow ? $this->load_option( Schema::SITE_OVERRIDES ) : array();
			unset( $overrides['schema_version'] );
			return $this->deep_merge( $base, $overrides );
		}

		$stored = $this->load_option( Schema::SETTINGS );
		return $this->deep_merge( Defaults::settings(), $stored );
	}

	/**
	 * Settings plus identity with binding.credential removed.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function export(): array {
		$identity = $this->get_identity();
		if ( isset( $identity['binding'] ) && is_array( $identity['binding'] ) ) {
			unset( $identity['binding']['credential'] );
		}
		return array(
			'settings' => $this->all(),
			'identity' => $identity,
		);
	}

	/**
	 * Site identity. Generates site_uuid on first read.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function get_identity(): array {
		$stored = get_option( Schema::SITE_IDENTITY, array() );
		$clean  = $this->validator->sanitize( is_array( $stored ) ? $stored : array(), Schema::SITE_IDENTITY );
		$this->emit_validator_warnings();

		$uuid = isset( $clean['site_uuid'] ) ? (string) $clean['site_uuid'] : '';
		if ( '' === $uuid || ! $this->is_uuid( $uuid ) ) {
			$clean['site_uuid'] = $this->new_uuid();
			$this->save_option( Schema::SITE_IDENTITY, $clean );
		}

		return $clean;
	}

	/**
	 * Replace the identity document (credential must already be ciphertext).
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $identity Raw identity.
	 * @return bool
	 */
	public function set_identity( array $identity ): bool {
		return $this->save_option( Schema::SITE_IDENTITY, $identity );
	}

	/**
	 * Decrypt binding.credential. Never logs the plaintext.
	 *
	 * @since 4.0.0
	 *
	 * @return string|null
	 */
	public function get_credential() {
		$identity = $this->get_identity();
		$cipher   = $identity['binding']['credential'] ?? null;
		if ( ! is_string( $cipher ) || '' === $cipher ) {
			return null;
		}
		$plain = $this->decrypt( $cipher );
		return is_string( $plain ) ? $plain : null;
	}

	/**
	 * Encrypt and store binding.credential. Null clears it.
	 *
	 * @since 4.0.0
	 *
	 * @param string|null $plaintext Secret; never written in the clear.
	 * @return bool
	 */
	public function set_credential( $plaintext ): bool {
		$identity = $this->get_identity();
		if ( ! isset( $identity['binding'] ) || ! is_array( $identity['binding'] ) ) {
			$identity['binding'] = Defaults::site_identity()['binding'];
		}
		if ( null === $plaintext || '' === $plaintext ) {
			$identity['binding']['credential'] = null;
			return $this->save_option( Schema::SITE_IDENTITY, $identity );
		}
		$sealed = $this->encrypt( (string) $plaintext );
		if ( null === $sealed ) {
			$this->warn( 'Credential encrypt failed; value not stored.', array() );
			return false;
		}
		$identity['binding']['credential'] = $sealed;
		return $this->save_option( Schema::SITE_IDENTITY, $identity );
	}

	/**
	 * Persist a whole option after validation.
	 *
	 * @since 4.0.0
	 *
	 * @param string               $option Option name.
	 * @param array<string, mixed> $value  Raw document.
	 * @return bool
	 */
	public function save_option( string $option, array $value ): bool {
		if ( ! Schema::is_known_option( $option ) ) {
			$this->warn( 'Unknown option name discarded.', array( 'option' => $option ) );
			return false;
		}

		if ( Schema::SITE_OVERRIDES === $option && $this->is_multisite() && ! $this->allows_site_override() ) {
			$this->warn( 'Site overrides ignored because allow_site_override is false.', array() );
			return false;
		}

		$clean = $this->validator->sanitize( $value, $option );
		$this->emit_validator_warnings();

		if ( Schema::NETWORK_SETTINGS === $option ) {
			return (bool) update_site_option( $option, $clean );
		}

		$autoload = Schema::SITE_IDENTITY === $option ? false : true;
		return (bool) update_option( $option, $clean, $autoload );
	}

	/**
	 * Walk a dotted path. Missing segments return null.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $data Tree.
	 * @param string               $path Dotted path.
	 * @return mixed|null
	 */
	public static function path_get( array $data, string $path ) {
		if ( '' === $path ) {
			return $data;
		}
		$current = $data;
		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $current ) || ! array_key_exists( $segment, $current ) ) {
				return null;
			}
			$current = $current[ $segment ];
		}
		return $current;
	}

	/**
	 * Whether a dotted path is present. Distinguishes a missing key from a null value.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $data Tree.
	 * @param string               $path Dotted path.
	 */
	public static function path_exists( array $data, string $path ): bool {
		if ( '' === $path ) {
			return true;
		}
		$current = $data;
		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $current ) || ! array_key_exists( $segment, $current ) ) {
				return false;
			}
			$current = $current[ $segment ];
		}
		return true;
	}

	/**
	 * Write a dotted path into a nested array.
	 *
	 * @param array<string, mixed> $data  Tree.
	 * @param string               $path  Dotted path.
	 * @param mixed                $value New leaf.
	 * @return array<string, mixed>
	 */
	private static function path_set( array $data, string $path, $value ): array {
		$segments = explode( '.', $path );
		$leaf     = array_pop( $segments );
		$cursor   = &$data;
		foreach ( $segments as $segment ) {
			if ( ! isset( $cursor[ $segment ] ) || ! is_array( $cursor[ $segment ] ) ) {
				$cursor[ $segment ] = array();
			}
			$cursor = &$cursor[ $segment ];
		}
		$cursor[ $leaf ] = $value;
		return $data;
	}

	/**
	 * Load, sanitize, and merge one option with its defaults.
	 *
	 * @param string $option Option name.
	 * @return array<string, mixed>
	 */
	private function load_option( string $option ): array {
		$defaults = $this->defaults_for( $option );
		$raw      = Schema::NETWORK_SETTINGS === $option
			? get_site_option( $option, array() )
			: get_option( $option, array() );

		if ( ! is_array( $raw ) || array() === $raw ) {
			return $defaults;
		}

		$clean = $this->validator->sanitize( $raw, $option );
		$this->emit_validator_warnings();

		if ( Schema::SITE_OVERRIDES === $option ) {
			return $clean;
		}

		return $this->deep_merge( $defaults, $clean );
	}

	/**
	 * Defaults table for one option name.
	 *
	 * @param string $option Option name.
	 * @return array<string, mixed>
	 */
	private function defaults_for( string $option ): array {
		switch ( $option ) {
			case Schema::NETWORK_SETTINGS:
				return Defaults::network_settings();
			case Schema::SITE_OVERRIDES:
				return Defaults::site_overrides();
			case Schema::SITE_IDENTITY:
				return Defaults::site_identity();
			default:
				return Defaults::settings();
		}
	}

	/**
	 * Object keys merge recursively; lists and scalars are replaced.
	 *
	 * @param array<string, mixed> $base    Left.
	 * @param array<string, mixed> $overlay Right.
	 * @return array<string, mixed>
	 */
	private function deep_merge( array $base, array $overlay ): array {
		foreach ( $overlay as $key => $value ) {
			if ( is_array( $value )
				&& isset( $base[ $key ] )
				&& is_array( $base[ $key ] )
				&& ! $this->is_list( $value )
				&& ! $this->is_list( $base[ $key ] )
			) {
				$base[ $key ] = $this->deep_merge( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}
		return $base;
	}

	/**
	 * Whether $value is a JSON array (0-based list).
	 *
	 * @param array<int|string, mixed> $value Candidate.
	 * @return bool
	 */
	private function is_list( array $value ): bool {
		if ( array() === $value ) {
			return true;
		}
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	/**
	 * Write a connectivity/modules path into wpcy_site_overrides.
	 *
	 * @param string $path  Dotted path.
	 * @param mixed  $value New value.
	 * @return bool
	 */
	private function set_override_path( string $path, $value ): bool {
		if ( ! $this->allows_site_override() ) {
			$this->warn( 'Site overrides ignored because allow_site_override is false.', array( 'path' => $path ) );
			return false;
		}
		$current = $this->load_option( Schema::SITE_OVERRIDES );
		$updated = self::path_set( $current, $path, $value );
		return $this->save_option( Schema::SITE_OVERRIDES, $updated );
	}

	/**
	 * Whether the network option currently allows site overlays.
	 *
	 * @return bool
	 */
	private function allows_site_override(): bool {
		$network = $this->load_option( Schema::NETWORK_SETTINGS );
		return ! empty( $network['allow_site_override'] );
	}

	/**
	 * Whether this path belongs in wpcy_site_overrides on multisite.
	 *
	 * @param string $path Dotted path.
	 * @return bool
	 */
	private function is_override_path( string $path ): bool {
		$root = explode( '.', $path )[0];
		return in_array( $root, array( 'connectivity', 'modules' ), true );
	}

	/**
	 * Whether the path exists on the effective settings schema.
	 *
	 * @param string $path Dotted path.
	 * @return bool
	 */
	private function is_readable_path( string $path ): bool {
		$schema = $this->is_multisite() ? Schema::network_settings() : Schema::settings();
		return $this->path_in_schema( $path, $schema );
	}

	/**
	 * Whether the path may be written via set().
	 *
	 * @param string $path Dotted path.
	 * @return bool
	 */
	private function is_writable_path( string $path ): bool {
		return $this->is_readable_path( $path );
	}

	/**
	 * Walk schema properties along a dotted path.
	 *
	 * @param string               $path   Dotted path.
	 * @param array<string, mixed> $schema Node.
	 * @return bool
	 */
	private function path_in_schema( string $path, array $schema ): bool {
		$node = $schema;
		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! isset( $node['properties'] ) || ! is_array( $node['properties'] ) || ! isset( $node['properties'][ $segment ] ) ) {
				return false;
			}
			$node = $node['properties'][ $segment ];
		}
		return true;
	}

	/**
	 * WordPress multisite flag. False in unit tests unless stubbed.
	 *
	 * @return bool
	 */
	private function is_multisite(): bool {
		return function_exists( 'is_multisite' ) && is_multisite();
	}

	/**
	 * RFC 4122 UUID check used by site_uuid.
	 *
	 * @param string $value Candidate uuid.
	 * @return bool
	 */
	private function is_uuid( string $value ): bool {
		return (bool) preg_match(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
			$value
		);
	}

	/**
	 * Generate a v4 UUID for first-run identity.
	 *
	 * @return string
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

	/**
	 * Seal a credential with sodium secretbox. Ciphertext is Base64.
	 *
	 * @param string $plaintext Secret.
	 * @return string|null Base64 ciphertext.
	 */
	private function encrypt( string $plaintext ) {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			return null;
		}
		$key   = $this->credential_key();
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$box   = sodium_crypto_secretbox( $plaintext, $nonce, $key );
		return base64_encode( $nonce . $box ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- sodium ciphertext encoding required by spec §4.
	}

	/**
	 * Open a stored credential. Returns null on failure.
	 *
	 * @param string $stored Base64 ciphertext.
	 * @return string|null
	 */
	private function decrypt( string $stored ) {
		if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
			return null;
		}
		$raw = base64_decode( $stored, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- inverse of encrypt().
		if ( ! is_string( $raw ) ) {
			return null;
		}
		$nonce_size = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
		if ( strlen( $raw ) < $nonce_size ) {
			return null;
		}
		$nonce = substr( $raw, 0, $nonce_size );
		$box   = substr( $raw, $nonce_size );
		$plain = sodium_crypto_secretbox_open( $box, $nonce, $this->credential_key() );
		return is_string( $plain ) ? $plain : null;
	}

	/**
	 * Key from wp_salt('auth'). KDF rounds: 待定（M0 / security.md M1）.
	 *
	 * @return string
	 */
	private function credential_key(): string {
		$salt = function_exists( 'wp_salt' ) ? (string) wp_salt( 'auth' ) : 'wpcy-test-salt';
		return hash( 'sha256', $salt, true );
	}

	/**
	 * Forward Validator warnings to the optional logger.
	 *
	 * @return void
	 */
	private function emit_validator_warnings(): void {
		foreach ( $this->validator->warnings() as $warning ) {
			$this->warn(
				'Unknown or invalid config key discarded.',
				array(
					'path'   => $warning['path'],
					'reason' => $warning['message'],
				)
			);
		}
	}

	/**
	 * Log at warning. Context must not contain credentials.
	 *
	 * @param string               $message English log line. No secrets.
	 * @param array<string, mixed> $context Path / reason only.
	 * @return void
	 */
	private function warn( string $message, array $context ): void {
		if ( null === $this->logger ) {
			return;
		}
		call_user_func( $this->logger, 'warning', $message, $context );
	}
}
