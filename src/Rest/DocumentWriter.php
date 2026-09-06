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

		$incoming  = $this->expand_legacy_avatar( $incoming );
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
	 * Site settings document (effective, no identity/credential).
	 *
	 * Frozen connect UI still reads connectivity.avatar as a string, so the
	 * REST presentation includes that scalar alongside admin/frontend.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function site_document(): array {
		return self::present_legacy_avatar( $this->stored_site_document() );
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
	 * Expand a v1 string connectivity.avatar into {admin, frontend} of the same value.
	 *
	 * Frozen React connect page still PUTs a single enum. Invalid strings are
	 * left for Validator so they stay wpcy_invalid_schema.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $incoming PUT body.
	 * @return array<string, mixed>
	 */
	private function expand_legacy_avatar( array $incoming ): array {
		if ( ! isset( $incoming['connectivity'] ) || ! is_array( $incoming['connectivity'] ) ) {
			return $incoming;
		}
		if ( ! array_key_exists( 'avatar', $incoming['connectivity'] ) ) {
			return $incoming;
		}

		$avatar = $incoming['connectivity']['avatar'];
		if ( ! is_string( $avatar ) ) {
			return $incoming;
		}
		if ( ! in_array( $avatar, Schema::AVATAR, true ) ) {
			return $incoming;
		}

		$incoming['connectivity']['avatar'] = array(
			'admin'    => $avatar,
			'frontend' => $avatar,
		);

		return $incoming;
	}

	/**
	 * Present connectivity.avatar as both the v2 object and a legacy string.
	 *
	 * Frozen connect UI reads `connectivity.avatar` as a scalar. JSON cannot
	 * be a string and an object at the same key, so the HTTP field is the
	 * string (admin when the two sides differ). Split values stay on the
	 * stored option and on sibling keys the frozen page ignores.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $document Settings document.
	 * @return array<string, mixed>
	 */
	public static function present_legacy_avatar( array $document ): array {
		if ( ! isset( $document['connectivity'] ) || ! is_array( $document['connectivity'] ) ) {
			return $document;
		}

		$avatar = $document['connectivity']['avatar'] ?? null;
		if ( ! is_array( $avatar ) ) {
			return $document;
		}

		$admin    = isset( $avatar['admin'] ) && is_string( $avatar['admin'] ) ? $avatar['admin'] : null;
		$frontend = isset( $avatar['frontend'] ) && is_string( $avatar['frontend'] ) ? $avatar['frontend'] : null;
		if ( null === $admin ) {
			return $document;
		}

		$frontend = is_string( $frontend ) ? $frontend : $admin;

		$document['connectivity']['avatar']          = $admin;
		$document['connectivity']['avatar_admin']    = $admin;
		$document['connectivity']['avatar_frontend'] = $frontend;

		return $document;
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
