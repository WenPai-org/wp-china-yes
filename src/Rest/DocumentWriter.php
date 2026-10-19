<?php
/**
 * Merge + schema-check + persist for PUT /settings and /network-settings.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Rest;

use WenPai\ChinaYes\Config\Profile;
use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Config\Validator;
use WenPai\ChinaYes\Privacy\SiteBlocklist\Repository as BlocklistRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unknown keys are dropped (warning). Type/enum failures are 400.
 */
final class DocumentWriter {

	/**
	 * Roots that belong to the network document on multisite.
	 *
	 * @since 4.0.0
	 */
	private const NETWORK_ROOTS = array( 'data_residency', 'announcements', 'apps', 'diagnostics' );

	/**
	 * Subsite override roots refused while allow_site_override is false.
	 *
	 * @since 4.0.0
	 */
	private const LOCKABLE_ROOTS = array( 'profile', 'connectivity', 'modules', 'admin' );

	/**
	 * Settings access.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param Repository $repository Settings access.
	 */
	public function __construct( Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Settings access used by SettingsController for read-only stamps.
	 *
	 * @since 4.0.0
	 */
	public function repository(): Repository {
		return $this->repository;
	}

	/**
	 * Merge $incoming into $current, validate, persist. True or WP_Error.
	 *
	 * @since 4.0.0
	 *
	 * @param string               $option   Option name.
	 * @param array<string, mixed> $current  Existing document.
	 * @param mixed                $incoming PUT body.
	 * @return true|WP_Error
	 */
	public function put( string $option, array $current, $incoming ) {
		if ( ! is_array( $incoming ) || $this->is_list( $incoming ) ) {
			return RestError::invalid_schema();
		}

		if ( $this->has_site_blocklist( $incoming ) && $this->site_blocklist_forbidden( $option ) ) {
			return RestError::make(
				'wpcy_settings_network_only_key',
				__( '暂时无法保存设置，请检查填写内容后重试。', 'wp-china-yes' ),
				400
			);
		}

		$incoming  = $this->normalize_incoming_avatar( $incoming );
		$current   = $this->apply_profile_switch( $current, $incoming );
		$merged    = $this->deep_merge( $current, $incoming );
		$validator = new Validator();
		$clean     = $validator->sanitize( $merged, $option );

		if ( $this->schema_failed( $validator->warnings() ) ) {
			return RestError::invalid_schema();
		}

		$blocked = $this->reject_protected_blocklist( $incoming, $clean );
		if ( is_wp_error( $blocked ) ) {
			return $blocked;
		}

		$this->repository->save_option( $option, $clean );
		return true;
	}

	/**
	 * PUT /settings. Single-site writes wpcy_settings; multisite writes
	 * site overrides only, never the network option or an unread wpcy_settings.
	 *
	 * On multisite the whole request is refused (nothing saved) when it changes a
	 * network-scope root (403 wpcy_settings_network_scope) or, while the network
	 * has allow_site_override=false, a locked root (409 wpcy_settings_locked).
	 * data.fields lists the refused roots. Roots sent with their current value
	 * are ignored, so a full-document PUT that only edits other keys succeeds.
	 *
	 * These checks apply to every site of a multisite network, including the
	 * main site, and to every caller, including super admins. That is on
	 * purpose: network-scope roots may only be changed through PUT
	 * /network-settings (manage_network_options, NetworkSettingsController),
	 * so PUT /settings is never a network write path, whoever calls it and on
	 * whichever site. Super admins use /network-settings for those roots.
	 *
	 * @since 4.0.0
	 *
	 * @param mixed $incoming PUT body.
	 * @return true|WP_Error
	 */
	public function put_site( $incoming ) {
		if ( ! function_exists( 'is_multisite' ) || ! is_multisite() ) {
			return $this->put( Schema::SETTINGS, $this->stored_site_document(), $incoming );
		}

		if ( ! is_array( $incoming ) || $this->is_list( $incoming ) ) {
			return RestError::invalid_schema();
		}

		$incoming  = $this->normalize_incoming_avatar( $incoming );
		$before    = $this->stored_site_document();
		$current   = $this->apply_profile_switch( $before, $incoming );
		$merged    = $this->deep_merge( $current, $incoming );
		$validator = new Validator();
		$clean     = $validator->sanitize( $merged, Schema::NETWORK_SETTINGS );
		if ( $this->schema_failed( $validator->warnings() ) ) {
			return RestError::invalid_schema();
		}

		// Only changed values count: a full-document PUT echoes untouched locked / network roots.
		$baseline = ( new Validator() )->sanitize( $before, Schema::NETWORK_SETTINGS );

		// modules.site_blocklist is network-only (决定 A2): subsites may not store it in overrides either.
		if ( $this->has_site_blocklist( $incoming ) ) {
			if ( ( $clean['modules']['site_blocklist'] ?? null ) !== ( $baseline['modules']['site_blocklist'] ?? null ) ) {
				return RestError::make(
					'wpcy_settings_network_only_key',
					__( '暂时无法保存设置，请检查填写内容后重试。', 'wp-china-yes' ),
					400
				);
			}
			unset( $incoming['modules']['site_blocklist'] );
		}

		$network_fields = $this->changed_roots( self::NETWORK_ROOTS, $incoming, $clean, $baseline );
		if ( array() !== $network_fields ) {
			return RestError::make(
				'wpcy_settings_network_scope',
				__( '这些设置只能在网络后台修改。', 'wp-china-yes' ),
				403,
				array( 'fields' => $network_fields )
			);
		}

		$locked        = false === $this->repository->get( 'allow_site_override', true );
		$locked_fields = $locked ? $this->changed_roots( self::LOCKABLE_ROOTS, $incoming, $clean, $baseline ) : array();
		if ( array() !== $locked_fields ) {
			return RestError::make(
				'wpcy_settings_locked',
				__( '网络管理员已锁定这些设置，子站点暂时无法修改。', 'wp-china-yes' ),
				409,
				array( 'fields' => $locked_fields )
			);
		}

		// Unchanged network / locked roots are ignored, never stored as overrides.
		foreach ( $locked ? array_merge( self::NETWORK_ROOTS, self::LOCKABLE_ROOTS ) : self::NETWORK_ROOTS as $key ) {
			unset( $incoming[ $key ] );
		}
		unset( $clean['modules']['site_blocklist'] );

		// Only $override_roots are copied into site overrides. allow_site_override is
		// neither a network root (no 403) nor an override root, so a subsite
		// body that carries it is silently dropped: the lock is changed only
		// through /network-settings. Intentional; keep it that way.
		$from            = isset( $before['profile'] ) && is_string( $before['profile'] ) ? $before['profile'] : 'domestic';
		$profile_switch  = isset( $incoming['profile'] ) && is_string( $incoming['profile'] )
			&& in_array( $incoming['profile'], Schema::PROFILES, true )
			&& $incoming['profile'] !== $from;
		$override_roots  = array( 'profile', 'profile_confirmed_at', 'connectivity', 'admin', 'modules', 'recovery_mode' );
		$overrides_touch = $profile_switch;
		$overrides       = array( 'schema_version' => Schema::VERSION );
		$stored_raw      = function_exists( 'get_option' ) ? get_option( Schema::SITE_OVERRIDES, array() ) : array();
		$stored          = is_array( $stored_raw ) ? $stored_raw : array();
		foreach ( $override_roots as $key ) {
			if ( array_key_exists( $key, $stored ) ) {
				$overrides[ $key ] = $stored[ $key ];
			}
		}
		foreach ( $override_roots as $key ) {
			$from_switch = $profile_switch && in_array( $key, array( 'profile', 'connectivity', 'modules' ), true );
			if ( array_key_exists( $key, $incoming ) || $from_switch ) {
				$overrides[ $key ] = $clean[ $key ];
				$overrides_touch   = true;
			}
		}
		if ( $overrides_touch ) {
			$this->repository->save_option( Schema::SITE_OVERRIDES, $overrides );
		}

		return true;
	}

	/**
	 * Site settings document (effective, no identity/credential).
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function site_document(): array {
		return $this->stored_site_document();
	}

	/**
	 * Stored site settings (v2 objects, no REST presentation).
	 *
	 * PUT merge must start from this, not site_document().
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function stored_site_document(): array {
		return $this->repository->all();
	}

	/**
	 * Network settings document. Not merged with site overrides.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function network_document(): array {
		$raw = array();
		if ( function_exists( 'get_site_option' ) ) {
			$loaded = get_site_option( Schema::NETWORK_SETTINGS, array() );
			$raw    = is_array( $loaded ) ? $loaded : array();
		}

		$validator = new Validator();
		return $validator->sanitize( $raw, Schema::NETWORK_SETTINGS );
	}

	/**
	 * Whether Validator warnings include a real schema failure.
	 *
	 * Unknown keys and extra array items are dropped, not rejected.
	 *
	 * @since 4.0.0
	 *
	 * @param array<int, array{path: string, message: string}> $warnings Validator warnings.
	 */
	public function schema_failed( array $warnings ): bool {
		foreach ( $warnings as $warning ) {
			if ( $this->is_drop_warning( $warning['message'] ) ) {
				continue;
			}
			return true;
		}

		return false;
	}

	/**
	 * Drop-not-reject warning text from Validator.
	 *
	 * @param string $message Warning text.
	 */
	private function is_drop_warning( string $message ): bool {
		if ( false !== strpos( $message, 'Unknown key discarded' ) ) {
			return true;
		}
		if ( false !== strpos( $message, 'extra entries discarded' ) ) {
			return true;
		}
		if ( false !== strpos( $message, 'Array item missing required keys' ) ) {
			return true;
		}

		return false;
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
	 * Accept a leftover {admin,frontend} PUT body and collapse it to one enum.
	 *
	 * Invalid strings stay for Validator so they remain wpcy_invalid_schema.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $incoming PUT body.
	 * @return array<string, mixed>
	 */
	private function normalize_incoming_avatar( array $incoming ): array {
		if ( ! isset( $incoming['connectivity'] ) || ! is_array( $incoming['connectivity'] ) ) {
			return $incoming;
		}
		if ( ! array_key_exists( 'avatar', $incoming['connectivity'] ) ) {
			return $incoming;
		}

		$avatar = $incoming['connectivity']['avatar'];
		if ( is_string( $avatar ) ) {
			return $incoming;
		}
		if ( ! is_array( $avatar ) ) {
			return $incoming;
		}

		$incoming['connectivity']['avatar'] = self::collapse_avatar_value( $avatar );

		return $incoming;
	}

	/**
	 * Collapse a leftover split avatar object into one enum.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $avatar Split avatar.
	 */
	public static function collapse_avatar_value( array $avatar ): string {
		$admin    = isset( $avatar['admin'] ) && is_string( $avatar['admin'] ) ? $avatar['admin'] : '';
		$frontend = isset( $avatar['frontend'] ) && is_string( $avatar['frontend'] ) ? $avatar['frontend'] : '';
		foreach ( array( $admin, $frontend ) as $mode ) {
			if ( in_array( $mode, Schema::AVATAR, true ) && 'off' !== $mode ) {
				return $mode;
			}
		}
		if ( in_array( $admin, Schema::AVATAR, true ) ) {
			return $admin;
		}
		if ( in_array( $frontend, Schema::AVATAR, true ) ) {
			return $frontend;
		}

		return 'cravatar_cn';
	}

	/**
	 * When PUT profile differs from current, reset D2 connectivity keys first.
	 *
	 * Remaining incoming fields overlay those defaults (per-item override).
	 *
	 * @param array<string, mixed> $current  Stored document.
	 * @param array<string, mixed> $incoming PUT body.
	 * @return array<string, mixed>
	 */
	private function apply_profile_switch( array $current, array $incoming ): array {
		if ( ! isset( $incoming['profile'] ) || ! is_string( $incoming['profile'] ) ) {
			return $current;
		}
		if ( ! in_array( $incoming['profile'], Schema::PROFILES, true ) ) {
			return $current;
		}

		$from = isset( $current['profile'] ) && is_string( $current['profile'] )
			? $current['profile']
			: 'domestic';
		if ( $incoming['profile'] === $from ) {
			return $current;
		}

		return Profile::apply_to( $current, $incoming['profile'] );
	}

	/**
	 * Roots from $roots that the PUT body names with a value different from the effective one.
	 *
	 * @since 4.0.0
	 *
	 * @param array<int, string>   $roots    Candidate roots.
	 * @param array<string, mixed> $incoming PUT body.
	 * @param array<string, mixed> $clean    Sanitized merge.
	 * @param array<string, mixed> $baseline Sanitized effective document before the PUT.
	 * @return array<int, string>
	 */
	private function changed_roots( array $roots, array $incoming, array $clean, array $baseline ): array {
		$changed = array();
		foreach ( $roots as $key ) {
			if ( ! array_key_exists( $key, $incoming ) ) {
				continue;
			}
			if ( ( $clean[ $key ] ?? null ) !== ( $baseline[ $key ] ?? null ) ) {
				$changed[] = $key;
			}
		}

		return $changed;
	}

	/**
	 * Whether PUT body includes the network-only site_blocklist segment.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $incoming PUT body.
	 */
	private function has_site_blocklist( array $incoming ): bool {
		return isset( $incoming['modules'] ) && is_array( $incoming['modules'] ) && array_key_exists( 'site_blocklist', $incoming['modules'] );
	}

	/**
	 * L0 hosts in modules.site_blocklist reject the whole PUT. Does not persist.
	 *
	 * Runs after sanitize so schema failures stay wpcy_invalid_schema.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $incoming PUT body.
	 * @param array<string, mixed> $clean    Sanitized merge.
	 * @return WP_Error|null
	 */
	private function reject_protected_blocklist( array $incoming, array $clean ) {
		if ( ! $this->has_site_blocklist( $incoming ) ) {
			return null;
		}

		if ( ! isset( $clean['modules']['site_blocklist'] ) || ! is_array( $clean['modules']['site_blocklist'] ) ) {
			return null;
		}

		$list    = new BlocklistRepository( $this->repository );
		$checked = $list->validate( $clean['modules']['site_blocklist'] );
		if ( ! is_wp_error( $checked ) ) {
			return null;
		}

		if ( 'wpcy_blocklist_protected_host' === $checked->get_error_code() ) {
			return RestError::make(
				'wpcy_blocklist_protected_host',
				$checked->get_error_message(),
				400
			);
		}

		return $checked;
	}

	/**
	 * Site blocklist may not be written via site overrides or a subsite /settings PUT.
	 *
	 * @since 4.0.0
	 *
	 * @param string $option Option name.
	 */
	private function site_blocklist_forbidden( string $option ): bool {
		if ( Schema::SITE_OVERRIDES === $option ) {
			return true;
		}

		return Schema::SETTINGS === $option && function_exists( 'is_multisite' ) && is_multisite();
	}

	/**
	 * Whether $value is a JSON array (0-based list).
	 *
	 * @param array<int|string, mixed> $value Candidate.
	 */
	private function is_list( array $value ): bool {
		if ( array() === $value ) {
			return false;
		}

		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
