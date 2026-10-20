<?php
/**
 * In-memory WordPress option bags for Config unit tests.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Config;

/**
 * Process-wide option bags. Reset in each test setUp().
 */
final class OptionStore {

	/**
	 * Site options keyed by name.
	 *
	 * @var array<string, mixed>
	 */
	public static $options = array();

	/**
	 * Network options keyed by name.
	 *
	 * @var array<string, mixed>
	 */
	public static $site_options = array();

	/**
	 * Whether is_multisite() should return true.
	 *
	 * @var bool
	 */
	public static $multisite = false;

	/**
	 * Value returned by wp_salt().
	 *
	 * @var string
	 */
	public static $salt = 'wpcy-unit-auth-salt';

	/**
	 * Optional callback for update_option stubs. Throw to simulate a write failure.
	 *
	 * @var callable|null
	 */
	public static $on_update = null;

	/**
	 * When true, update_option() / update_site_option() fail like a database
	 * error: return false and write nothing.
	 *
	 * The stubs check it after the "unchanged" test on purpose: an unchanged
	 * write already returns false before this flag is read, as in WordPress,
	 * so the flag only turns a real change into a failed write.
	 *
	 * @var bool
	 */
	public static $fail_writes = false;

	/**
	 * WordPress update_option() / update_network_option() "unchanged" check:
	 * the new value is identical to the stored one, or both serialize the
	 * same way (maybe_serialize() serializes arrays and objects only).
	 *
	 * @param mixed $old_value Stored value.
	 * @param mixed $value     New value.
	 * @return bool
	 */
	public static function unchanged( $old_value, $value ): bool {
		if ( $value === $old_value ) {
			return true;
		}
		$serialize = static function ( $data ) {
			return is_array( $data ) || is_object( $data ) ? serialize( $data ) : $data; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		};
		return $serialize( $value ) === $serialize( $old_value );
	}

	/**
	 * Clear bags between tests.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$options      = array();
		self::$site_options = array();
		self::$multisite    = false;
		self::$on_update    = null;
		self::$fail_writes  = false;
		\WenPai\ChinaYes\Config\Repository::reset_warning_dedupe();
	}
}
