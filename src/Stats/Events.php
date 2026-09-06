<?php
/**
 * Ring-buffer event log. Memory in the request; one option write on shutdown.
 *
 * Title and detail are generated here from rest-api.md §/events templates
 * and stored already translated (Chinese source text).
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Stats;

use WenPai\ChinaYes\Diagnostics\Checker;
use WenPai\ChinaYes\Diagnostics\RouteGroups;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 50-entry `wpcy_events` log (autoload=false). ULID ids, UTC timestamps.
 */
final class Events {

	/**
	 * Option key. Not a settings field.
	 *
	 * @since 4.0.0
	 */
	public const OPTION = 'wpcy_events';

	/**
	 * Ring size.
	 *
	 * @since 4.0.0
	 */
	public const CAPACITY = 50;

	/**
	 * Crockford Base32 alphabet (ULID).
	 *
	 * @since 4.0.0
	 */
	private const CROCKFORD = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

	/**
	 * Known types from rest-api.md §/events.
	 *
	 * @since 4.0.0
	 *
	 * @var list<string>
	 */
	public const TYPES = array(
		'first_check',
		'route_recovered',
		'mirror_fallback',
		'route_fallback',
		'route_down',
		'update_check',
		'migrated',
		'profile_set',
		'recovery_entered',
		'recovery_exited',
	);

	/**
	 * Optional config with get(). Used for recovery_mode.
	 *
	 * @var object|null
	 */
	private $config;

	/**
	 * Clock: function(): string UTC ISO 8601.
	 *
	 * @var callable
	 */
	private $now;

	/**
	 * ULID factory: function(): string.
	 *
	 * @var callable
	 */
	private $ulid;

	/**
	 * In-memory entries, newest first.
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $entries = array();

	/**
	 * Whether option contents have been loaded.
	 *
	 * @var bool
	 */
	private bool $loaded = false;

	/**
	 * Whether record() changed memory since load / last flush.
	 *
	 * @var bool
	 */
	private bool $dirty = false;

	/**
	 * Whether shutdown already flushed this request.
	 *
	 * @var bool
	 */
	private bool $flushed = false;

	/**
	 * Constructor. Does not read or write options.
	 *
	 * @since 4.0.0
	 *
	 * @param object|null   $config Config with get(), or null.
	 * @param callable|null $now    Clock returning UTC ISO 8601.
	 * @param callable|null $ulid   ULID generator.
	 */
	public function __construct( $config = null, $now = null, $ulid = null ) {
		$this->config = ( is_object( $config ) && method_exists( $config, 'get' ) ) ? $config : null;
		$this->now    = null !== $now ? $now : static function () {
			return gmdate( 'Y-m-d\TH:i:s\Z' );
		};
		$this->ulid   = null !== $ulid ? $ulid : array( self::class, 'generate_ulid' );
	}

	/**
	 * Append one event. No I/O. Unknown types are ignored.
	 *
	 * Recovery mode records only recovery_* types.
	 *
	 * @since 4.0.0
	 *
	 * @param string               $type Event type.
	 * @param array<string, mixed> $vars Template variables.
	 */
	public function record( string $type, array $vars = array() ): void {
		if ( ! in_array( $type, self::TYPES, true ) ) {
			return;
		}
		if ( $this->recovery_mode() && 0 !== strpos( $type, 'recovery_' ) ) {
			return;
		}

		$rendered = $this->render( $type, $vars );
		if ( null === $rendered ) {
			return;
		}

		$this->hydrate();
		$entry = array(
			'id'     => (string) ( $this->ulid )(),
			'at'     => (string) ( $this->now )(),
			'type'   => $type,
			'tone'   => $rendered['tone'],
			'title'  => $rendered['title'],
			'detail' => $rendered['detail'],
		);
		if ( isset( $vars['group'] ) && is_string( $vars['group'] ) && '' !== $vars['group'] ) {
			$entry['group'] = $vars['group'];
		}

		array_unshift( $this->entries, $entry );
		if ( count( $this->entries ) > self::CAPACITY ) {
			$this->entries = array_slice( $this->entries, 0, self::CAPACITY );
		}
		$this->dirty = true;
	}

	/**
	 * Newest-first slice. Does not write.
	 *
	 * @since 4.0.0
	 *
	 * @param int         $per_page Max rows (1–50).
	 * @param string|null $type     Optional type filter.
	 * @return list<array{id: string, at: string, type: string, tone: string, title: string, detail: string}>
	 */
	public function latest( int $per_page, $type = null ): array {
		$this->hydrate();
		$out = array();
		foreach ( $this->entries as $row ) {
			if ( is_string( $type ) && '' !== $type && ( ! isset( $row['type'] ) || $row['type'] !== $type ) ) {
				continue;
			}
			$out[] = array(
				'id'     => isset( $row['id'] ) ? (string) $row['id'] : '',
				'at'     => isset( $row['at'] ) ? (string) $row['at'] : '',
				'type'   => isset( $row['type'] ) ? (string) $row['type'] : '',
				'tone'   => isset( $row['tone'] ) ? (string) $row['tone'] : 'neutral',
				'title'  => isset( $row['title'] ) ? (string) $row['title'] : '',
				'detail' => isset( $row['detail'] ) ? (string) $row['detail'] : '',
			);
			if ( count( $out ) >= $per_page ) {
				break;
			}
		}

		return $out;
	}

	/**
	 * Compare Checker snapshots and record route / first_check events.
	 *
	 * First run (empty `$before`) records `first_check` only.
	 *
	 * @since 4.0.0
	 *
	 * @param list<array{target: string, result: string, latency_ms: int|null, checked_at: string, suggestion: string|null}> $before Previous Checker rows.
	 * @param list<array{target: string, result: string, latency_ms: int|null, checked_at: string, suggestion: string|null}> $after  New Checker rows.
	 */
	public function record_checker_transition( array $before, array $after ): void {
		$after_groups = RouteGroups::worst( $after );
		if ( array() === $before ) {
			$ok = 0;
			foreach ( $after_groups as $group ) {
				if ( Checker::RESULT_OK === $group['result'] ) {
					++$ok;
				}
			}
			$this->record(
				'first_check',
				array(
					'ok'    => $ok,
					'total' => count( $after_groups ),
				)
			);
			return;
		}

		$before_groups = array();
		foreach ( RouteGroups::worst( $before ) as $group ) {
			$before_groups[ $group['id'] ] = $group;
		}

		foreach ( $after_groups as $group ) {
			$prev = $before_groups[ $group['id'] ] ?? null;
			if ( ! is_array( $prev ) ) {
				continue;
			}
			$from = (string) $prev['result'];
			$to   = (string) $group['result'];
			if ( $from === $to ) {
				continue;
			}

			$vars = array(
				'route'    => $group['label'],
				'provider' => $group['provider'],
				'host'     => $group['host'],
				'group'    => $group['id'],
			);

			if ( Checker::RESULT_OK === $from && Checker::RESULT_FALLBACK === $to ) {
				$type = 'wordpress_org' === $group['id'] ? 'mirror_fallback' : 'route_fallback';
				$this->record( $type, $vars );
				continue;
			}

			if ( in_array( $from, array( Checker::RESULT_OK, Checker::RESULT_FALLBACK ), true )
				&& Checker::RESULT_DOWN === $to
			) {
				$this->record( 'route_down', $vars );
				continue;
			}

			if ( in_array( $from, array( Checker::RESULT_FALLBACK, Checker::RESULT_DOWN ), true )
				&& Checker::RESULT_OK === $to
			) {
				$vars['minutes'] = $this->minutes_since_non_ok( $group['id'] );
				$this->record( 'route_recovered', $vars );
			}
		}
	}

	/**
	 * Persist the ring once. No-op when nothing changed.
	 *
	 * @since 4.0.0
	 */
	public function flush(): void {
		if ( $this->flushed || ! $this->dirty ) {
			return;
		}
		$this->flushed = true;
		$this->hydrate();
		if ( ! function_exists( 'update_option' ) ) {
			$this->dirty = false;
			return;
		}
		update_option( self::OPTION, array( 'events' => $this->entries ), false );
		$this->dirty = false;
	}

	/**
	 * In-memory entries (tests). Newest first.
	 *
	 * @since 4.0.0
	 *
	 * @return list<array<string, mixed>>
	 */
	public function entries(): array {
		$this->hydrate();
		return $this->entries;
	}

	/**
	 * 26-character Crockford ULID.
	 *
	 * @since 4.0.0
	 */
	public static function generate_ulid(): string {
		$time = (int) floor( microtime( true ) * 1000 );
		if ( $time < 0 ) {
			$time = 0;
		}

		$chars = array();
		for ( $i = 9; $i >= 0; $i-- ) {
			$chars[ $i ] = self::CROCKFORD[ $time & 31 ];
			$time        = intdiv( $time, 32 );
		}

		$bytes = random_bytes( 10 );
		$acc   = 0;
		$bits  = 0;
		for ( $i = 0; $i < 10; $i++ ) {
			$acc   = ( $acc << 8 ) | ord( $bytes[ $i ] );
			$bits += 8;
			while ( $bits >= 5 ) {
				$bits   -= 5;
				$chars[] = self::CROCKFORD[ ( $acc >> $bits ) & 31 ];
			}
		}
		if ( $bits > 0 ) {
			$chars[] = self::CROCKFORD[ ( $acc << ( 5 - $bits ) ) & 31 ];
		}

		return substr( implode( '', $chars ), 0, 26 );
	}

	/**
	 * Title, detail, tone for `$type`, or null when the type is unknown.
	 *
	 * @param string               $type Event type.
	 * @param array<string, mixed> $vars Template variables.
	 * @return array{tone: string, title: string, detail: string}|null
	 */
	private function render( string $type, array $vars ) {
		$route    = $this->str( $vars, 'route' );
		$provider = $this->str( $vars, 'provider' );
		$minutes  = isset( $vars['minutes'] ) ? (string) (int) $vars['minutes'] : '0';
		$version  = $this->str( $vars, 'version' );
		$seconds  = $this->str( $vars, 'seconds' );
		$source   = $this->str( $vars, 'source_version' );
		$kept     = isset( $vars['kept'] ) ? (string) (int) $vars['kept'] : '0';
		$label    = $this->str( $vars, 'profile_label' );
		$profile  = $this->str( $vars, 'profile' );

		switch ( $type ) {
			case 'first_check':
				$ok    = isset( $vars['ok'] ) ? (int) $vars['ok'] : 0;
				$total = isset( $vars['total'] ) ? (int) $vars['total'] : 0;
				if ( $total > 0 && $ok === $total ) {
					$detail = sprintf(
						/* translators: %d: number of routes that passed. */
						__( '%d 条线路全部正常', 'wp-china-yes' ),
						$ok
					);
				} else {
					$detail = sprintf(
						/* translators: 1: ok count, 2: total routes. */
						__( '%1$d/%2$d 条线路正常', 'wp-china-yes' ),
						$ok,
						$total
					);
				}
				return array(
					'tone'   => 'ok',
					'title'  => __( '首次线路检查完成', 'wp-china-yes' ),
					'detail' => $detail,
				);

			case 'route_recovered':
				return array(
					'tone'   => 'ok',
					'title'  => sprintf(
						/* translators: %s: route group label. */
						__( '%s 恢复，已切回', 'wp-china-yes' ),
						$route
					),
					'detail' => sprintf(
						/* translators: 1: provider brand, 2: minutes offline. */
						__( '%1$s 中断 %2$s 分钟，期间走原始上游，访客不受影响', 'wp-china-yes' ),
						$provider,
						$minutes
					),
				);

			case 'mirror_fallback':
				return array(
					'tone'   => 'warn',
					'title'  => __( 'WordPress.org 镜像不可达，已回原始上游', 'wp-china-yes' ),
					'detail' => __( 'WenPai.org 镜像暂时不可达，每 1 分钟重试，恢复后自动切回', 'wp-china-yes' ),
				);

			case 'route_fallback':
				return array(
					'tone'   => 'warn',
					'title'  => sprintf(
						/* translators: %s: route group label. */
						__( '%s 不可达，已回原始上游', 'wp-china-yes' ),
						$route
					),
					'detail' => sprintf(
						/* translators: %s: provider brand. */
						__( '%s 暂时不可达，恢复后自动切回', 'wp-china-yes' ),
						$provider
					),
				);

			case 'route_down':
				return array(
					'tone'   => 'warn',
					'title'  => sprintf(
						/* translators: %s: route group label. */
						__( '%s 不可达', 'wp-china-yes' ),
						$route
					),
					'detail' => sprintf(
						/* translators: %s: provider brand. */
						__( '%s 暂时不可达，该项已暂停改写，恢复后自动继续', 'wp-china-yes' ),
						$provider
					),
				);

			case 'update_check':
				$via_mirror = ! empty( $vars['via_mirror'] );
				return array(
					'tone'   => $via_mirror ? 'ok' : 'neutral',
					'title'  => sprintf(
						/* translators: %s: WordPress version. */
						__( '完成 WordPress %s 更新检查', 'wp-china-yes' ),
						$version
					),
					'detail' => $via_mirror
						? sprintf(
							/* translators: %s: seconds with one decimal. */
							__( '经国内镜像，耗时 %s 秒', 'wp-china-yes' ),
							$seconds
						)
						: sprintf(
							/* translators: %s: seconds with one decimal. */
							__( '直连 WordPress.org，耗时 %s 秒', 'wp-china-yes' ),
							$seconds
						),
				);

			case 'migrated':
				return array(
					'tone'   => 'neutral',
					'title'  => sprintf(
						/* translators: %s: plugin version. */
						__( '插件更新到 %s', 'wp-china-yes' ),
						$version
					),
					'detail' => sprintf(
						/* translators: 1: source version, 2: kept setting count. */
						__( '从 %1$s 迁移 %2$s 项设置', 'wp-china-yes' ),
						$source,
						$kept
					),
				);

			case 'profile_set':
				$domestic = 'domestic' === $profile;
				return array(
					'tone'   => 'neutral',
					'title'  => sprintf(
						/* translators: %s: profile label. */
						__( '已按「%s」配置', 'wp-china-yes' ),
						$label
					),
					'detail' => $domestic
						? __( '更新走国内镜像，前端资源与头像走国内节点', 'wp-china-yes' )
						: __( '后台资源只在后台加速，更新直连 WordPress.org', 'wp-china-yes' ),
				);

			case 'recovery_entered':
				return array(
					'tone'   => 'warn',
					'title'  => __( '已进入恢复模式', 'wp-china-yes' ),
					'detail' => __( '全部 URL 改写与模块已停用', 'wp-china-yes' ),
				);

			case 'recovery_exited':
				return array(
					'tone'   => 'ok',
					'title'  => __( '已退出恢复模式', 'wp-china-yes' ),
					'detail' => __( '设置已恢复', 'wp-china-yes' ),
				);
		}

		return null;
	}

	/**
	 * Minutes from now back to the last non-ok event for `$group_id`.
	 *
	 * @param string $group_id RouteGroups id.
	 */
	private function minutes_since_non_ok( string $group_id ): int {
		$this->hydrate();
		$now = strtotime( (string) ( $this->now )() );
		if ( ! is_int( $now ) ) {
			return 0;
		}
		foreach ( $this->entries as $row ) {
			$type = isset( $row['type'] ) ? (string) $row['type'] : '';
			if ( ! in_array( $type, array( 'route_fallback', 'mirror_fallback', 'route_down' ), true ) ) {
				continue;
			}
			if ( isset( $row['group'] ) && $row['group'] !== $group_id ) {
				continue;
			}
			$at = isset( $row['at'] ) ? strtotime( (string) $row['at'] ) : false;
			if ( ! is_int( $at ) ) {
				return 0;
			}
			return max( 0, (int) floor( ( $now - $at ) / 60 ) );
		}

		return 0;
	}

	/**
	 * Load option into memory once.
	 */
	private function hydrate(): void {
		if ( $this->loaded ) {
			return;
		}
		$this->loaded = true;
		if ( ! function_exists( 'get_option' ) ) {
			return;
		}
		$raw = get_option( self::OPTION, array() );
		if ( ! is_array( $raw ) ) {
			return;
		}
		$list = isset( $raw['events'] ) && is_array( $raw['events'] ) ? $raw['events'] : $raw;
		$out  = array();
		foreach ( $list as $row ) {
			if ( is_array( $row ) && isset( $row['id'], $row['type'] ) ) {
				$out[] = $row;
			}
		}
		$this->entries = $out;
	}

	/**
	 * String var or empty.
	 *
	 * @param array<string, mixed> $vars Vars.
	 * @param string               $key  Key.
	 */
	private function str( array $vars, string $key ): string {
		return isset( $vars[ $key ] ) && is_scalar( $vars[ $key ] ) ? (string) $vars[ $key ] : '';
	}

	/**
	 * Whether recovery_mode is on.
	 */
	private function recovery_mode(): bool {
		if ( ! is_object( $this->config ) || ! method_exists( $this->config, 'get' ) ) {
			return false;
		}
		return (bool) $this->config->get( 'recovery_mode', false );
	}
}
