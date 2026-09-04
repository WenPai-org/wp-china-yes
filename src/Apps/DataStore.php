<?php
/**
 * Per-app JSON rows in {prefix}wpcy_app_data.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Apps;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * App-namespaced key/value store. Tool A cannot read tool B.
 *
 * Key count / 5MB total are 待定（M0）. Single value cap is 64KB.
 */
final class DataStore {

	/**
	 * Unprefixed table name.
	 *
	 * @since 4.0.0
	 */
	public const TABLE = 'wpcy_app_data';

	/**
	 * Data key pattern from spec §4.
	 *
	 * @since 4.0.0
	 */
	public const KEY_PATTERN = '/^[a-z0-9_.-]{1,64}$/';

	/**
	 * PUT body cap in bytes.
	 *
	 * @since 4.0.0
	 */
	public const MAX_BYTES = 65536;

	/**
	 * In-memory rows when $wpdb is absent (unit tests).
	 *
	 * @var array<string, array<string, string>>
	 */
	private array $memory = array();

	/**
	 * Optional wpdb. Null uses memory. Not typed: tests inject a stand-in.
	 *
	 * @var object|null
	 */
	private $wpdb;

	/**
	 * Constructor. Does not create the table.
	 *
	 * @since 4.0.0
	 *
	 * @param object|null $wpdb wpdb or null for memory.
	 */
	public function __construct( $wpdb = null ) {
		$this->wpdb = is_object( $wpdb ) ? $wpdb : null;
	}

	/**
	 * Create `{prefix}wpcy_app_data` via dbDelta. Multisite uses the blog prefix.
	 *
	 * @since 4.0.0
	 *
	 * @param object|null $wpdb wpdb. Null uses the global.
	 */
	public static function install( $wpdb = null ): void {
		if ( ! is_object( $wpdb ) && isset( $GLOBALS['wpdb'] ) && is_object( $GLOBALS['wpdb'] ) ) {
			$wpdb = $GLOBALS['wpdb'];
		}
		if ( ! is_object( $wpdb ) ) {
			return;
		}

		$charset = isset( $wpdb->charset ) && is_string( $wpdb->charset ) && '' !== $wpdb->charset
			? $wpdb->charset
			: 'utf8mb4';
		$collate = isset( $wpdb->collate ) && is_string( $wpdb->collate ) && '' !== $wpdb->collate
			? $wpdb->collate
			: '';
		$prefix  = isset( $wpdb->prefix ) && is_string( $wpdb->prefix ) ? $wpdb->prefix : 'wp_';
		$table   = $prefix . self::TABLE;

		$charset_collate = 'DEFAULT CHARACTER SET ' . $charset;
		if ( '' !== $collate ) {
			$charset_collate .= ' COLLATE ' . $collate;
		}

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			app_id VARCHAR(64) NOT NULL,
			data_key VARCHAR(64) NOT NULL,
			data_json LONGTEXT NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY app_key (app_id, data_key)
		) {$charset_collate};";

		if ( ! function_exists( 'dbDelta' ) ) {
			$upgrade = defined( 'ABSPATH' ) ? ABSPATH . 'wp-admin/includes/upgrade.php' : '';
			if ( '' !== $upgrade && is_readable( $upgrade ) ) {
				require_once $upgrade;
			}
		}
		if ( function_exists( 'dbDelta' ) ) {
			dbDelta( $sql );
		}
	}

	/**
	 * Prefixed table name.
	 *
	 * @since 4.0.0
	 */
	public function table_name(): string {
		if ( is_object( $this->wpdb ) && isset( $this->wpdb->prefix ) && is_string( $this->wpdb->prefix ) ) {
			return $this->wpdb->prefix . self::TABLE;
		}
		return 'wp_' . self::TABLE;
	}

	/**
	 * Whether $key matches spec §4.
	 *
	 * @since 4.0.0
	 *
	 * @param string $key Data key.
	 */
	public static function key_valid( string $key ): bool {
		return (bool) preg_match( self::KEY_PATTERN, $key );
	}

	/**
	 * Keys for one app, document order.
	 *
	 * @since 4.0.0
	 *
	 * @param string $app_id App id.
	 * @return list<string>
	 */
	public function list_keys( string $app_id ): array {
		if ( is_object( $this->wpdb ) && method_exists( $this->wpdb, 'get_col' ) && method_exists( $this->wpdb, 'prepare' ) ) {
			$table = function_exists( 'esc_sql' ) ? esc_sql( $this->table_name() ) : $this->table_name();
			$sql   = $this->wpdb->prepare(
				"SELECT data_key FROM {$table} WHERE app_id = %s ORDER BY data_key ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from prefix + esc_sql, not user input.
				$app_id
			);
			$cols  = $this->wpdb->get_col( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql from $wpdb->prepare(); table from prefix + esc_sql.
			if ( ! is_array( $cols ) ) {
				return array();
			}
			$out = array();
			foreach ( $cols as $key ) {
				if ( is_string( $key ) ) {
					$out[] = $key;
				}
			}
			return $out;
		}

		if ( ! isset( $this->memory[ $app_id ] ) ) {
			return array();
		}
		$keys = array_keys( $this->memory[ $app_id ] );
		sort( $keys, SORT_STRING );
		return $keys;
	}

	/**
	 * One JSON value, or null when missing.
	 *
	 * @since 4.0.0
	 *
	 * @param string $app_id App id.
	 * @param string $key    Data key.
	 * @return mixed|null
	 */
	public function get( string $app_id, string $key ) {
		$json = $this->get_json( $app_id, $key );
		if ( null === $json ) {
			return null;
		}
		$decoded = json_decode( $json, true );
		return null !== $decoded || 'null' === $json ? $decoded : $json;
	}

	/**
	 * Whether a row exists for this app and key.
	 *
	 * @since 4.0.0
	 *
	 * @param string $app_id App id.
	 * @param string $key    Data key.
	 */
	public function has( string $app_id, string $key ): bool {
		return null !== $this->get_json( $app_id, $key );
	}

	/**
	 * Write one JSON value. Body larger than 64KB is rejected.
	 *
	 * @since 4.0.0
	 *
	 * @param string $app_id App id.
	 * @param string $key    Data key.
	 * @param mixed  $value  JSON-serializable value.
	 * @param int    $bytes  Raw body size. 0 means encode $value and measure.
	 * @return true|WP_Error
	 */
	public function put( string $app_id, string $key, $value, int $bytes = 0 ) {
		if ( ! self::key_valid( $key ) ) {
			return $this->error(
				'wpcy_apps_key_invalid',
				__( 'The data key is invalid.', 'wp-china-yes' ),
				400
			);
		}

		$encoded = $this->encode( $value );
		if ( '' === $encoded && null !== $value ) {
			return $this->error(
				'wpcy_apps_key_invalid',
				__( 'The data key is invalid.', 'wp-china-yes' ),
				400
			);
		}

		$size = $bytes > 0 ? $bytes : strlen( $encoded );
		if ( $size > self::MAX_BYTES ) {
			return $this->error(
				'wpcy_apps_payload_too_large',
				__( 'The request body exceeds 64KB.', 'wp-china-yes' ),
				413
			);
		}

		$now = gmdate( 'Y-m-d H:i:s' );
		if ( is_object( $this->wpdb ) && method_exists( $this->wpdb, 'replace' ) ) {
			$this->wpdb->replace(
				$this->table_name(),
				array(
					'app_id'     => $app_id,
					'data_key'   => $key,
					'data_json'  => $encoded,
					'updated_at' => $now,
				),
				array( '%s', '%s', '%s', '%s' )
			);
			return true;
		}

		if ( ! isset( $this->memory[ $app_id ] ) ) {
			$this->memory[ $app_id ] = array();
		}
		$this->memory[ $app_id ][ $key ] = $encoded;
		return true;
	}

	/**
	 * Delete one key. Missing keys succeed.
	 *
	 * @since 4.0.0
	 *
	 * @param string $app_id App id.
	 * @param string $key    Data key.
	 */
	public function delete( string $app_id, string $key ): bool {
		if ( ! self::key_valid( $key ) ) {
			return false;
		}

		if ( is_object( $this->wpdb ) && method_exists( $this->wpdb, 'delete' ) ) {
			$this->wpdb->delete(
				$this->table_name(),
				array(
					'app_id'   => $app_id,
					'data_key' => $key,
				),
				array( '%s', '%s' )
			);
			return true;
		}

		if ( isset( $this->memory[ $app_id ][ $key ] ) ) {
			unset( $this->memory[ $app_id ][ $key ] );
		}
		return true;
	}

	/**
	 * Raw JSON string or null.
	 *
	 * @param string $app_id App id.
	 * @param string $key    Data key.
	 * @return string|null
	 */
	private function get_json( string $app_id, string $key ) {
		if ( is_object( $this->wpdb ) && method_exists( $this->wpdb, 'get_var' ) && method_exists( $this->wpdb, 'prepare' ) ) {
			$table = function_exists( 'esc_sql' ) ? esc_sql( $this->table_name() ) : $this->table_name();
			$sql   = $this->wpdb->prepare(
				"SELECT data_json FROM {$table} WHERE app_id = %s AND data_key = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from prefix + esc_sql, not user input.
				$app_id,
				$key
			);
			$json  = $this->wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql from $wpdb->prepare(); table from prefix + esc_sql.
			return is_string( $json ) ? $json : null;
		}

		if ( ! isset( $this->memory[ $app_id ][ $key ] ) ) {
			return null;
		}
		return $this->memory[ $app_id ][ $key ];
	}

	/**
	 * JSON encode with the same flags as canonical payloads (no pretty print).
	 *
	 * @param mixed $value Value.
	 */
	private function encode( $value ): string {
		$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
		if ( defined( 'JSON_THROW_ON_ERROR' ) ) {
			$flags |= JSON_THROW_ON_ERROR;
		}
		try {
			$encoded = json_encode( $value, $flags ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- stored bytes, not filtered through wp_json_encode.
		} catch ( \JsonException $e ) {
			unset( $e );
			return '';
		}
		return is_string( $encoded ) ? $encoded : '';
	}

	/**
	 * WP_Error when the class is loaded; otherwise skip.
	 *
	 * @param string $code    Code.
	 * @param string $message Message.
	 * @param int    $status  HTTP status.
	 * @return WP_Error
	 */
	private function error( string $code, string $message, int $status ): WP_Error {
		if ( class_exists( \WenPai\ChinaYes\Rest\RestError::class, false ) || class_exists( \WenPai\ChinaYes\Rest\RestError::class ) ) {
			return \WenPai\ChinaYes\Rest\RestError::make( $code, $message, $status );
		}
		return new WP_Error(
			$code,
			$message,
			array(
				'status' => $status,
			)
		);
	}
}
