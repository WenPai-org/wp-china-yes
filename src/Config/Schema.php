<?php
/**
 * JSON Schema definitions for the five WPCY option keys.
 *
 * Shapes match docs/specs/config-schema.md (draft 2020-12).
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
 * Option names and property schemas. No product keys beyond the spec.
 */
final class Schema {

	public const SETTINGS         = 'wpcy_settings';
	public const NETWORK_SETTINGS = 'wpcy_network_settings';
	public const SITE_OVERRIDES   = 'wpcy_site_overrides';
	public const SITE_IDENTITY    = 'wpcy_site_identity';
	public const MIGRATION_BACKUP = 'wpcy_migration_backup';
	public const VERSION          = 1;

	public const PUBLIC_ASSETS = array(
		'google_fonts',
		'google_ajax',
		'cdnjs',
		'jsdelivr',
		'emoji',
	);

	public const AVATAR = array(
		'cravatar_cn',
		'cravatar_global',
		'weavatar',
		'off',
	);

	/**
	 * Schema document for one option key.
	 *
	 * @since 4.0.0
	 *
	 * @param string $option Option name.
	 * @return array<string, mixed>
	 */
	public static function definition( string $option ): array {
		switch ( $option ) {
			case self::SETTINGS:
				return self::settings();
			case self::NETWORK_SETTINGS:
				return self::network_settings();
			case self::SITE_OVERRIDES:
				return self::site_overrides();
			case self::SITE_IDENTITY:
				return self::site_identity();
			case self::MIGRATION_BACKUP:
				return self::migration_backup();
			default:
				return array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(),
				);
		}
	}

	/**
	 * Whether $option is one of the five documented keys.
	 *
	 * @since 4.0.0
	 *
	 * @param string $option Option name.
	 * @return bool
	 */
	public static function is_known_option( string $option ): bool {
		return in_array(
			$option,
			array(
				self::SETTINGS,
				self::NETWORK_SETTINGS,
				self::SITE_OVERRIDES,
				self::SITE_IDENTITY,
				self::MIGRATION_BACKUP,
			),
			true
		);
	}

	/**
	 * Site option schema (`wpcy_settings`).
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public static function settings(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array(
				'schema_version',
				'connectivity',
				'modules',
				'diagnostics',
				'data_residency',
				'announcements',
				'apps',
				'recovery_mode',
			),
			'properties'           => self::settings_properties(),
		);
	}

	/**
	 * Same as settings plus allow_site_override.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public static function network_settings(): array {
		$properties                        = self::settings_properties();
		$properties['allow_site_override'] = array(
			'type'    => 'boolean',
			'default' => true,
		);
		$required                          = self::settings()['required'];
		$required[]                        = 'allow_site_override';

		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => $required,
			'properties'           => $properties,
		);
	}

	/**
	 * Partial overlay: missing segments mean “do not override”.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public static function site_overrides(): array {
		$props = self::settings_properties();

		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'schema_version' => $props['schema_version'],
				'connectivity'   => $props['connectivity'],
				'modules'        => $props['modules'],
				'recovery_mode'  => $props['recovery_mode'],
			),
		);
	}

	/**
	 * Site identity schema (`wpcy_site_identity`).
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public static function site_identity(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'schema_version', 'site_uuid', 'binding' ),
			'properties'           => array(
				'schema_version' => array(
					'type'    => 'integer',
					'const'   => 1,
					'default' => 1,
				),
				'site_uuid'      => array(
					'type'   => 'string',
					'format' => 'uuid',
				),
				'binding'        => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'status' ),
					'properties'           => array(
						'status'       => array(
							'type'    => 'string',
							'enum'    => array( 'unbound', 'pending', 'bound', 'revoked', 'failed' ),
							'default' => 'unbound',
						),
						'site_hash'    => array(
							'type'    => array( 'string', 'null' ),
							'default' => null,
						),
						'credential'   => array(
							'type'    => array( 'string', 'null' ),
							'default' => null,
						),
						'bound_at'     => array(
							'type'    => array( 'string', 'null' ),
							'format'  => 'date-time',
							'default' => null,
						),
						'challenge_id' => array(
							'type'    => array( 'string', 'null' ),
							'default' => null,
						),
					),
				),
			),
		);
	}

	/**
	 * Migration backup schema (`wpcy_migration_backup`).
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	public static function migration_backup(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array(
				'schema_version',
				'from_version',
				'migrated_at',
				'legacy_hash',
				'ignored_fields',
			),
			'properties'           => array(
				'schema_version' => array(
					'type'    => 'integer',
					'const'   => 1,
					'default' => 1,
				),
				'from_version'   => array(
					'type' => 'string',
				),
				'migrated_at'    => array(
					'type'   => 'string',
					'format' => 'date-time',
				),
				'legacy_hash'    => array(
					'type' => 'string',
				),
				'ignored_fields' => array(
					'type'    => 'array',
					'items'   => array( 'type' => 'string' ),
					'default' => array(),
				),
			),
		);
	}

	/**
	 * Shared property map for wpcy_settings (and the network clone).
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	private static function settings_properties(): array {
		return array(
			'schema_version' => array(
				'type'    => 'integer',
				'const'   => 1,
				'default' => 1,
			),
			'connectivity'   => self::connectivity(),
			'modules'        => self::modules(),
			'integrations'   => self::integrations(),
			'diagnostics'    => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'scheduled_checks' ),
				'properties'           => array(
					'scheduled_checks' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
			),
			'data_residency' => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'ruleset_version' ),
				'properties'           => array(
					'ruleset_version' => array(
						'type'    => 'integer',
						'minimum' => 1,
						'default' => 1,
					),
				),
			),
			'announcements'  => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'dismissed' ),
				'properties'           => array(
					'dismissed' => array(
						'type'     => 'array',
						'items'    => array(
							'type'      => 'string',
							'minLength' => 1,
							'maxLength' => 128,
						),
						'maxItems' => 100,
						'default'  => array(),
					),
				),
			),
			'apps'           => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'disabled' ),
				'properties'           => array(
					'disabled' => array(
						'type'    => 'array',
						'items'   => array(
							'type'      => 'string',
							'minLength' => 1,
							'maxLength' => 64,
						),
						'default' => array(),
					),
				),
			),
			'recovery_mode'  => array(
				'type'    => 'boolean',
				'default' => false,
			),
		);
	}

	/**
	 * Connectivity object schema.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	private static function connectivity(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'wordpress_org', 'public_assets', 'avatar' ),
			'properties'           => array(
				'wordpress_org' => array(
					'type'    => 'string',
					'enum'    => array( 'auto', 'off' ),
					'default' => 'auto',
				),
				'public_assets' => array(
					'type'        => 'array',
					'uniqueItems' => true,
					'items'       => array(
						'type' => 'string',
						'enum' => self::PUBLIC_ASSETS,
					),
					'default'     => self::PUBLIC_ASSETS,
				),
				'avatar'        => array(
					'type'    => 'string',
					'enum'    => self::AVATAR,
					'default' => 'cravatar_cn',
				),
			),
		);
	}

	/**
	 * Optional-modules object schema.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	private static function modules(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'notice_control', 'windfonts' ),
			'properties'           => array(
				'notice_control' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'windfonts'      => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
		);
	}

	/**
	 * Optional (not in required[]). Structure from spec after M0 close-out.
	 *
	 * @since 4.0.0
	 *
	 * @return array<string, mixed>
	 */
	private static function integrations(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'windfonts' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'fonts' => array(
							'type'     => 'array',
							'maxItems' => 20,
							'items'    => array(
								'type'                 => 'object',
								'additionalProperties' => false,
								'required'             => array( 'family', 'selector' ),
								'properties'           => array(
									'family'   => array(
										'type'    => 'string',
										'pattern' => '^[a-z0-9-]{1,64}$',
									),
									'subset'   => array(
										'type'    => 'string',
										'enum'    => array( 'full', 'zh', 'zh-common', 'en' ),
										'default' => 'full',
									),
									'selector' => array(
										'type'      => 'string',
										'maxLength' => 200,
									),
									'enable'   => array(
										'type'    => 'boolean',
										'default' => true,
									),
								),
							),
							'default'  => array(),
						),
					),
				),
			),
		);
	}
}
