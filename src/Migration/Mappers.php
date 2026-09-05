<?php
/**
 * 3.x `wp_china_yes` → 4.0 settings (rewrite plan §7.2).
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Migration;

use WenPai\ChinaYes\Config\Defaults;
use WenPai\ChinaYes\Config\Schema;
use WenPai\ChinaYes\Config\Validator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure mapping. Does not read or write WordPress options.
 */
final class Mappers {

	/**
	 * 3.x admincdn_public / admincdn_files / admincdn_dev tokens still in the 4.0 whitelist.
	 *
	 * @var array<string, string>
	 */
	private const PUBLIC_ASSET_MAP = array(
		'googlefonts'  => 'google_fonts',
		'google_fonts' => 'google_fonts',
		'googleajax'   => 'google_ajax',
		'google_ajax'  => 'google_ajax',
		'cdnjs'        => 'cdnjs',
		'jsdelivr'     => 'jsdelivr',
		'emoji'        => 'emoji',
	);

	/**
	 * 3.x cravatar → 4.0 connectivity.avatar.
	 *
	 * @var array<string, string>
	 */
	private const AVATAR_MAP = array(
		'cn'       => 'cravatar_cn',
		'global'   => 'cravatar_global',
		'weavatar' => 'weavatar',
		'off'      => 'off',
	);

	/**
	 * Optional family allow-list. Empty means schema-valid families pass.
	 *
	 * @var array<int, string>
	 */
	private array $font_catalog;

	/**
	 * Schema walker used to fill a complete 4.0 document.
	 *
	 * @var Validator
	 */
	private Validator $validator;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param array<int, string> $font_catalog Optional Windfonts family catalog.
	 * @param Validator|null     $validator    Schema walker.
	 */
	public function __construct( array $font_catalog = array(), $validator = null ) {
		$this->font_catalog = $font_catalog;
		$this->validator    = $validator instanceof Validator ? $validator : new Validator();
	}

	/**
	 * Map a 3.x option array onto a 4.0 settings document.
	 *
	 * @since 4.0.0
	 *
	 * @param array<int|string, mixed> $legacy   Raw `wp_china_yes`.
	 * @param array<string, mixed>     $defaults Base document (site or network).
	 */
	public function map( array $legacy, array $defaults ): Report {
		$kept            = array();
		$ignored         = array();
		$ignored_reasons = array();
		$settings        = $defaults;

		foreach ( $legacy as $key => $_value ) {
			unset( $_value );
			$key = (string) $key;
			if ( '' === $key ) {
				continue;
			}
			switch ( $key ) {
				case 'store':
					$mapped = $this->map_store( $legacy[ $key ] );
					if ( null === $mapped ) {
						$ignored[]               = $key;
						$ignored_reasons[ $key ] = 'invalid_value';
						break;
					}
					$settings['connectivity']['wordpress_org'] = $mapped;
					$kept[]                                    = $key;
					break;

				case 'admincdn_public':
				case 'admincdn_files':
				case 'admincdn_dev':
					// Applied once when any of the three keys is present; present keys stay kept.
					break;

				case 'cravatar':
					$mapped = $this->map_avatar( $legacy[ $key ] );
					if ( null === $mapped ) {
						$ignored[]               = $key;
						$ignored_reasons[ $key ] = 'invalid_value';
						break;
					}
					$settings['connectivity']['avatar'] = $mapped;
					$kept[]                             = $key;
					break;

				case 'windfonts':
					$settings['modules']['windfonts'] = $this->is_enabled_switch( $legacy[ $key ] );
					$kept[]                           = $key;
					break;

				case 'windfonts_list':
					$fonts = $this->map_windfonts_list( $legacy[ $key ] );
					if ( null === $fonts ) {
						$ignored[]               = $key;
						$ignored_reasons[ $key ] = $this->windfonts_list_reason( $legacy[ $key ] );
						break;
					}
					if ( ! isset( $settings['integrations'] ) || ! is_array( $settings['integrations'] ) ) {
						$settings['integrations'] = array();
					}
					$settings['integrations']['windfonts'] = array( 'fonts' => $fonts );
					$kept[]                                = $key;
					break;

				case 'adblock':
					$settings['modules']['notice_control'] = $this->map_notice_control( $legacy );
					$kept[]                                = $key;
					break;

				case 'notice_control':
					if ( $this->has_meaningful_value( $legacy[ $key ] ) ) {
						$settings['modules']['notice_control'] = true;
						$kept[]                                = $key;
						break;
					}
					$ignored[]               = $key;
					$ignored_reasons[ $key ] = 'empty';
					break;

				case 'bridge':
					$ignored[]               = $key;
					$ignored_reasons[ $key ] = 'login_state';
					break;

				case 'telemetry':
				case 'telemetry_site_url':
					$ignored[]               = $key;
					$ignored_reasons[ $key ] = 'not_migrated';
					break;

				case 'adblock_rule':
					$ignored[]               = $key;
					$ignored_reasons[ $key ] = 'replaced_by_remote';
					break;

				case 'admincdn':
					$ignored[]               = $key;
					$ignored_reasons[ $key ] = 'unsupported_whitelist';
					break;

				default:
					$ignored[]               = $key;
					$ignored_reasons[ $key ] = 'feature_removed';
					break;
			}
		}

		if ( array_key_exists( 'admincdn_public', $legacy )
			|| array_key_exists( 'admincdn_files', $legacy )
			|| array_key_exists( 'admincdn_dev', $legacy )
		) {
			$mapped                                    = $this->map_public_assets( $legacy );
			$settings['connectivity']['public_assets'] = $mapped['assets'];
			foreach ( $mapped['unknown'] as $token ) {
				$ignored[]                 = $token;
				$ignored_reasons[ $token ] = 'unsupported_whitelist';
			}
			if ( array_key_exists( 'admincdn_public', $legacy ) ) {
				$kept[] = 'admincdn_public';
			}
			if ( array_key_exists( 'admincdn_files', $legacy ) ) {
				$kept[] = 'admincdn_files';
			}
			if ( array_key_exists( 'admincdn_dev', $legacy ) ) {
				$kept[] = 'admincdn_dev';
			}
		}

		$kept    = array_values( array_unique( $kept ) );
		$ignored = array_values( array_unique( $ignored ) );

		$option = isset( $defaults['allow_site_override'] ) ? Schema::NETWORK_SETTINGS : Schema::SETTINGS;
		$clean  = $this->validator->sanitize( $settings, $option );

		return new Report( $kept, $ignored, $ignored_reasons, $clean );
	}

	/**
	 * Site-option mapping (single-site defaults).
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $legacy Raw `wp_china_yes`.
	 */
	public function map_site( array $legacy ): Report {
		return $this->map( $legacy, Defaults::settings() );
	}

	/**
	 * Network-option mapping.
	 *
	 * @since 4.0.0
	 *
	 * @param array<string, mixed> $legacy Raw `wp_china_yes`.
	 */
	public function map_network( array $legacy ): Report {
		return $this->map( $legacy, Defaults::network_settings() );
	}

	/**
	 * `store` → connectivity.wordpress_org.
	 *
	 * @param mixed $value 3.x store value.
	 * @return string|null
	 */
	private function map_store( $value ) {
		if ( ! is_string( $value ) ) {
			return null;
		}
		if ( 'wenpai' === $value ) {
			return 'auto';
		}
		if ( 'off' === $value ) {
			return 'off';
		}
		return null;
	}

	/**
	 * `cravatar` → connectivity.avatar.
	 *
	 * @param mixed $value 3.x cravatar value.
	 * @return string|null
	 */
	private function map_avatar( $value ) {
		if ( ! is_string( $value ) ) {
			return null;
		}
		return self::AVATAR_MAP[ $value ] ?? null;
	}

	/**
	 * Merge admincdn_public ∪ admincdn_files ∪ admincdn_dev onto the 4.0 public_assets enum.
	 *
	 * Output follows Schema::PUBLIC_ASSETS order. Present keys with an empty
	 * (or fully unsupported) list become []. That is an explicit choice, not a
	 * cue to refill schema defaults. Unknown tokens are returned for ignored.
	 *
	 * @param array<string, mixed> $legacy Raw `wp_china_yes`.
	 * @return array{assets: array<int, string>, unknown: array<int, string>}
	 */
	private function map_public_assets( array $legacy ): array {
		$tokens = array_merge(
			$this->as_token_list( $legacy['admincdn_public'] ?? array() ),
			$this->as_token_list( $legacy['admincdn_files'] ?? array() ),
			$this->as_token_list( $legacy['admincdn_dev'] ?? array() )
		);

		$wanted  = array();
		$unknown = array();
		foreach ( $tokens as $token ) {
			if ( ! isset( self::PUBLIC_ASSET_MAP[ $token ] ) ) {
				$unknown[] = $token;
				continue;
			}
			$wanted[ self::PUBLIC_ASSET_MAP[ $token ] ] = true;
		}

		$assets = array();
		foreach ( Schema::PUBLIC_ASSETS as $asset ) {
			if ( isset( $wanted[ $asset ] ) ) {
				$assets[] = $asset;
			}
		}

		return array(
			'assets'  => $assets,
			'unknown' => array_values( array_unique( $unknown ) ),
		);
	}

	/**
	 * `adblock=off` → false; any other adblock value, or a filled notice_control → true.
	 *
	 * @param array<string, mixed> $legacy Raw `wp_china_yes`.
	 */
	private function map_notice_control( array $legacy ): bool {
		if ( array_key_exists( 'notice_control', $legacy ) && $this->has_meaningful_value( $legacy['notice_control'] ) ) {
			return true;
		}
		if ( ! array_key_exists( 'adblock', $legacy ) ) {
			return true;
		}
		return $this->is_enabled_switch( $legacy['adblock'] );
	}

	/**
	 * Non-empty windfonts_list → integrations.windfonts.fonts. Null when ignored.
	 *
	 * @param mixed $value 3.x windfonts_list.
	 * @return array<int, array<string, mixed>>|null
	 */
	private function map_windfonts_list( $value ) {
		if ( ! is_array( $value ) || array() === $value ) {
			return null;
		}

		$fonts = array();
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$family = isset( $row['family'] ) && is_string( $row['family'] ) ? $row['family'] : '';
			if ( 1 !== preg_match( '/^[a-z0-9-]{1,64}$/', $family ) ) {
				continue;
			}
			if ( array() !== $this->font_catalog && ! in_array( $family, $this->font_catalog, true ) ) {
				continue;
			}
			$selector = isset( $row['selector'] ) && is_string( $row['selector'] ) ? $row['selector'] : '';
			if ( '' === $selector || strlen( $selector ) > 200 ) {
				continue;
			}

			$subset = 'full';
			if ( isset( $row['subset'] ) && is_string( $row['subset'] )
				&& in_array( $row['subset'], array( 'full', 'zh', 'zh-common', 'en' ), true )
			) {
				$subset = $row['subset'];
			}

			$fonts[] = array(
				'family'   => $family,
				'subset'   => $subset,
				'selector' => $selector,
				'enable'   => $this->is_truthy( $row['enable'] ?? true ),
			);
		}

		if ( array() === $fonts ) {
			return null;
		}

		return $fonts;
	}

	/**
	 * Ignored-reason token for a windfonts_list that did not map.
	 *
	 * @param mixed $value 3.x windfonts_list.
	 */
	private function windfonts_list_reason( $value ): string {
		if ( ! is_array( $value ) || array() === $value ) {
			return 'empty';
		}
		if ( array() !== $this->font_catalog ) {
			return 'not_in_catalog';
		}
		return 'invalid_value';
	}

	/**
	 * CSF checkbox / list → list of non-empty string tokens.
	 *
	 * @param mixed $value admincdn_public, admincdn_files, or admincdn_dev.
	 * @return array<int, string>
	 */
	private function as_token_list( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$out = array();
		foreach ( $value as $key => $item ) {
			if ( is_string( $key ) && ! is_numeric( $key ) ) {
				if ( $this->is_truthy( $item ) || $item === $key ) {
					$out[] = $key;
				}
				continue;
			}
			if ( is_string( $item ) && '' !== $item ) {
				$out[] = $item;
			}
		}

		return $out;
	}

	/**
	 * 3.x switcher: anything other than off / empty / 0 / false is on.
	 *
	 * @param mixed $value Switcher value.
	 */
	private function is_enabled_switch( $value ): bool {
		if ( false === $value || null === $value || 0 === $value || 0.0 === $value ) {
			return false;
		}
		if ( is_string( $value ) ) {
			$normalized = strtolower( trim( $value ) );
			return ! in_array( $normalized, array( '', 'off', '0', 'false', 'no' ), true );
		}
		if ( is_array( $value ) ) {
			return array() !== $value;
		}
		return (bool) $value;
	}

	/**
	 * Checkbox / enable flags stored as "1" / "0" / bool.
	 *
	 * @param mixed $value Raw flag.
	 */
	private function is_truthy( $value ): bool {
		if ( true === $value || 1 === $value || '1' === $value || 'on' === $value || 'true' === $value ) {
			return true;
		}
		return false;
	}

	/**
	 * Non-empty scalar or non-empty array. Empty string is not meaningful.
	 *
	 * @param mixed $value Raw 3.x value.
	 */
	private function has_meaningful_value( $value ): bool {
		if ( null === $value || false === $value ) {
			return false;
		}
		if ( is_string( $value ) ) {
			return '' !== trim( $value );
		}
		if ( is_array( $value ) ) {
			return array() !== $value;
		}
		return true;
	}
}
