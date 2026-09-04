<?php
/**
 * Default values for WPCY options (spec “默认值汇总”).
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
 * Static default table. Unknown paths are not invented here.
 */
final class Defaults {

	/**
	 * Site option defaults (`wpcy_settings`).
	 *
	 * `integrations` is in the JSON Schema (M0 close-out) but not the
	 * summary table; fonts default to [].
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public static function settings(): array {
		return array(
			'schema_version' => Schema::VERSION,
			'connectivity'   => array(
				'wordpress_org' => 'auto',
				'public_assets' => Schema::PUBLIC_ASSETS,
				'avatar'        => 'cravatar_cn',
			),
			'modules'        => array(
				'notice_control' => true,
				'windfonts'      => false,
			),
			'integrations'   => array(
				'windfonts' => array(
					'fonts' => array(),
				),
			),
			'diagnostics'    => array(
				'scheduled_checks' => true,
			),
			'data_residency' => array(
				'ruleset_version' => 1,
			),
			'announcements'  => array(
				'dismissed' => array(),
			),
			'apps'           => array(
				'disabled' => array(),
			),
			'recovery_mode'  => false,
		);
	}

	/**
	 * Network option defaults (`wpcy_network_settings`).
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public static function network_settings(): array {
		$defaults                        = self::settings();
		$defaults['allow_site_override'] = true;
		return $defaults;
	}

	/**
	 * Empty overlay: omitted segments do not override the network value.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public static function site_overrides(): array {
		return array(
			'schema_version' => Schema::VERSION,
		);
	}

	/**
	 * Identity defaults. `site_uuid` is generated on first read, not here.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public static function site_identity(): array {
		return array(
			'schema_version' => Schema::VERSION,
			'site_uuid'      => '',
			'binding'        => array(
				'status'       => 'unbound',
				'site_hash'    => null,
				'credential'   => null,
				'bound_at'     => null,
				'challenge_id' => null,
			),
		);
	}

	/**
	 * Look up a dotted path in the site defaults table.
	 *
	 * @since 4.0.0
	 *
	 * @param string $path Dot-separated path.
	 * @return mixed|null Null when the path is not in the table.
	 */
	public static function get( string $path ) {
		return Repository::path_get( self::settings(), $path );
	}
}
