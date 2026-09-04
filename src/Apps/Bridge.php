<?php
/**
 * Apps postMessage contract: envelope, types, permissions, origin helpers.
 *
 * Runtime delivery is the host page (src/Admin/app/apps/Bridge.js).
 * REST nonce stays on the host page and is never copied into an iframe.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Apps;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Spec §3 / §3.1 / §3.1a tables. PHP does not postMessage.
 */
final class Bridge {

	/**
	 * Envelope protocol version.
	 *
	 * @since 4.0.0
	 */
	public const PROTOCOL = 1;

	/**
	 * REST forward timeout in milliseconds.
	 *
	 * @since 4.0.0
	 */
	public const HOST_TIMEOUT_MS = 10000;

	/**
	 * Resize debounce in milliseconds.
	 *
	 * @since 4.0.0
	 */
	public const RESIZE_DEBOUNCE_MS = 200;

	/**
	 * Max iframe height in pixels.
	 *
	 * @since 4.0.0
	 */
	public const RESIZE_MAX_PX = 4000;

	/**
	 * Iframe sandbox tokens. Must not include allow-same-origin.
	 *
	 * @since 4.0.0
	 */
	public const IFRAME_SANDBOX = 'allow-scripts allow-forms';

	/**
	 * Iframe referrer policy.
	 *
	 * @since 4.0.0
	 */
	public const IFRAME_REFERRERPOLICY = 'strict-origin';

	/**
	 * Origin mismatch (bridge only).
	 *
	 * @since 4.0.0
	 */
	public const ERR_ORIGIN_MISMATCH = 'wpcy_apps_origin_mismatch';

	/**
	 * Host REST forward timed out.
	 *
	 * @since 4.0.0
	 */
	public const ERR_HOST_TIMEOUT = 'wpcy_apps_host_timeout';

	/**
	 * Manifest did not declare the permission.
	 *
	 * @since 4.0.0
	 */
	public const ERR_FORBIDDEN = 'wpcy_apps_forbidden_permission';

	/**
	 * Tool → host types that require a permission (spec §3.2).
	 *
	 * @since 4.0.0
	 * @var array<string, string>
	 */
	public const TYPE_PERMISSIONS = array(
		'context.get'     => 'site:read',
		'data.get'        => 'data:read',
		'data.set'        => 'data:write',
		'data.delete'     => 'data:delete',
		'data.list'       => 'data:read',
		'entitlement.get' => 'entitlement:read',
		'go.open'         => 'go:open',
	);

	/**
	 * Write / side-effect types. Host does not auto-retry these.
	 *
	 * @since 4.0.0
	 * @var list<string>
	 */
	public const WRITE_TYPES = array(
		'data.set',
		'data.delete',
		'go.open',
	);

	/**
	 * Host → tool types. No permission.
	 *
	 * @since 4.0.0
	 * @var list<string>
	 */
	public const HOST_TYPES = array(
		'init',
		'result',
		'error',
	);

	/**
	 * Tool → host types that do not require a permission.
	 *
	 * @since 4.0.0
	 * @var list<string>
	 */
	public const OPEN_TYPES = array(
		'ready',
		'resize',
	);

	/**
	 * Every type in the spec §3.2 table.
	 *
	 * @since 4.0.0
	 *
	 * @return list<string>
	 */
	public static function message_types(): array {
		return array_merge(
			self::OPEN_TYPES,
			array_keys( self::TYPE_PERMISSIONS ),
			self::HOST_TYPES
		);
	}

	/**
	 * Permission token for a tool → host type, or empty when none.
	 *
	 * @since 4.0.0
	 *
	 * @param string $type Message type.
	 */
	public static function permission_for( string $type ): string {
		return isset( self::TYPE_PERMISSIONS[ $type ] ) ? self::TYPE_PERMISSIONS[ $type ] : '';
	}

	/**
	 * Whether the host must not auto-retry this type.
	 *
	 * @since 4.0.0
	 *
	 * @param string $type Message type.
	 */
	public static function is_write_type( string $type ): bool {
		return in_array( $type, self::WRITE_TYPES, true );
	}

	/**
	 * Envelope: wpcy === 1 and type is a non-empty string.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $data Incoming event.data.
	 */
	public static function envelope_valid( $data ): bool {
		if ( ! is_array( $data ) ) {
			return false;
		}
		if ( ! isset( $data['wpcy'] ) || 1 !== (int) $data['wpcy'] ) {
			return false;
		}
		return isset( $data['type'] ) && is_string( $data['type'] ) && '' !== $data['type'];
	}

	/**
	 * Origin of manifest.entry_url, or empty.
	 *
	 * @since 4.0.0
	 *
	 * @param string $entry_url Absolute entry URL.
	 */
	public static function origin_from_entry_url( string $entry_url ): string {
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $entry_url ) : parse_url( $entry_url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- unit bootstrap has no WordPress.
		if ( ! is_array( $parts ) ) {
			return '';
		}
		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
		$host   = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
		if ( '' === $scheme || '' === $host ) {
			return '';
		}
		$origin = $scheme . '://' . $host;
		if ( isset( $parts['port'] ) ) {
			$origin .= ':' . (int) $parts['port'];
		}
		return $origin;
	}

	/**
	 * Clamp resize height: integer px, max 4000.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $height Payload height.
	 */
	public static function clamp_height( $height ): int {
		$n = is_numeric( $height ) ? (int) $height : 0;
		if ( $n < 0 ) {
			$n = 0;
		}
		if ( $n > self::RESIZE_MAX_PX ) {
			return self::RESIZE_MAX_PX;
		}
		return $n;
	}

	/**
	 * REST method + path for a tool request. Null when the type is not forwarded.
	 *
	 * @since 4.0.0
	 *
	 * @param string               $type    Message type.
	 * @param string               $app_id  App id.
	 * @param array<string, mixed> $payload Payload.
	 * @return array{method: string, path: string}|null
	 */
	public static function rest_for( string $type, string $app_id, array $payload ) {
		if ( '' === $app_id ) {
			return null;
		}
		$base = '/wpcy/v1/apps/' . rawurlencode( $app_id );
		switch ( $type ) {
			case 'context.get':
				return array(
					'method' => 'GET',
					'path'   => $base . '/context',
				);
			case 'data.list':
				return array(
					'method' => 'GET',
					'path'   => $base . '/data',
				);
			case 'data.get':
				$key = isset( $payload['key'] ) && is_string( $payload['key'] ) ? $payload['key'] : '';
				return array(
					'method' => 'GET',
					'path'   => $base . '/data/' . rawurlencode( $key ),
				);
			case 'data.set':
				$key = isset( $payload['key'] ) && is_string( $payload['key'] ) ? $payload['key'] : '';
				return array(
					'method' => 'PUT',
					'path'   => $base . '/data/' . rawurlencode( $key ),
				);
			case 'data.delete':
				$key = isset( $payload['key'] ) && is_string( $payload['key'] ) ? $payload['key'] : '';
				return array(
					'method' => 'DELETE',
					'path'   => $base . '/data/' . rawurlencode( $key ),
				);
			case 'entitlement.get':
				return array(
					'method' => 'GET',
					'path'   => $base . '/entitlement',
				);
			case 'go.open':
				return array(
					'method' => 'POST',
					'path'   => $base . '/go',
				);
			default:
				return null;
		}
	}

	/**
	 * Decide what the host does with one inbound event (spec §3 / §3.1 / §3.1a).
	 *
	 * Does not perform REST. Does not postMessage.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $event Event description.
	 * @return array<string, mixed>
	 */
	public static function classify( array $event ): array {
		$data = isset( $event['data'] ) ? $event['data'] : null;
		if ( ! self::envelope_valid( $data ) ) {
			return array( 'action' => 'discard' );
		}

		if ( ! empty( $event['source_is_parent'] ) ) {
			return array( 'action' => 'discard' );
		}
		if ( empty( $event['source_is_iframe'] ) ) {
			return array( 'action' => 'discard' );
		}

		$type         = (string) $data['type'];
		$request_id   = isset( $data['request_id'] ) && is_string( $data['request_id'] ) ? $data['request_id'] : '';
		$payload      = isset( $data['payload'] ) && is_array( $data['payload'] ) ? $data['payload'] : array();
		$origin       = isset( $event['origin'] ) && is_string( $event['origin'] ) ? $event['origin'] : '';
		$entry_origin = isset( $event['entry_origin'] ) && is_string( $event['entry_origin'] ) ? $event['entry_origin'] : '';

		if ( $origin !== $entry_origin ) {
			if ( '' !== $request_id ) {
				return array(
					'action'     => 'error',
					'code'       => self::ERR_ORIGIN_MISMATCH,
					'request_id' => $request_id,
				);
			}
			return array( 'action' => 'discard' );
		}

		$ready = ! empty( $event['ready'] );
		if ( ! $ready && 'ready' !== $type ) {
			return array( 'action' => 'discard' );
		}

		if ( 'ready' === $type ) {
			return array( 'action' => 'init' );
		}

		if ( 'resize' === $type ) {
			$height = isset( $payload['height'] ) ? $payload['height'] : 0;
			return array(
				'action' => 'resize',
				'height' => self::clamp_height( $height ),
			);
		}

		$permission  = self::permission_for( $type );
		$permissions = isset( $event['permissions'] ) && is_array( $event['permissions'] ) ? $event['permissions'] : array();
		if ( '' !== $permission && ! in_array( $permission, $permissions, true ) ) {
			return array(
				'action'     => 'error',
				'code'       => self::ERR_FORBIDDEN,
				'request_id' => $request_id,
				'type'       => $type,
			);
		}

		$app_id = isset( $event['app_id'] ) && is_string( $event['app_id'] ) ? $event['app_id'] : '';
		$rest   = self::rest_for( $type, $app_id, $payload );
		if ( ! is_array( $rest ) ) {
			return array( 'action' => 'discard' );
		}

		return array(
			'action'     => 'rest',
			'type'       => $type,
			'request_id' => $request_id,
			'rest'       => $rest,
			'retry'      => false,
			'timeout_ms' => self::HOST_TIMEOUT_MS,
			'write'      => self::is_write_type( $type ),
		);
	}
}
