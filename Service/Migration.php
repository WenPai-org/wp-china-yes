<?php

namespace WenPai\ChinaYes\Service;

defined( 'ABSPATH' ) || exit;

use function WenPai\ChinaYes\get_settings;

class Migration {

	private $settings;

	public function __construct() {
		$this->settings = get_settings();
		add_action( 'admin_init', [ $this, 'migrate_windfonts_settings' ] );
	}

	public function migrate_windfonts_settings() {
		$current_settings = get_option( 'wp_china_yes', [] );
		$needs_migration = false;

		if ( ! empty( $current_settings['windfonts_list'] ) ) {
			foreach ( $current_settings['windfonts_list'] as $index => $font ) {
				if ( isset( $font['css'] ) && ! isset( $font['subset'] ) ) {
					$migrated_font = $this->migrate_font_config( $font );
					$current_settings['windfonts_list'][$index] = $migrated_font;
					$needs_migration = true;
				}
			}
		}

		if ( $needs_migration ) {
			update_option( 'wp_china_yes', $current_settings );
		}
	}

	private function migrate_font_config( $old_font ) {
		$new_font = [
			'family'   => $this->extract_family_from_old_config( $old_font ),
			'subset'   => $this->extract_subset_from_old_config( $old_font ),
			'lang'     => '',
			'weight'   => $old_font['weight'] ?? 400,
			'style'    => $old_font['style'] ?? 'normal',
			'selector' => $old_font['selector'] ?? 'a:not([class]),p,h1,h2,h3,h4,h5,h6,ul,ol,li,button,blockquote,pre,code,table,th,td,label,b,i:not([class]),em,small,strong,sub,sup,ins,del,mark,abbr,dfn,span:not([class])',
			'enable'   => $old_font['enable'] ?? true,
		];

		return $new_font;
	}

	private function extract_family_from_old_config( $old_font ) {
		if ( isset( $old_font['family'] ) ) {
			return $old_font['family'];
		}

		if ( isset( $old_font['css'] ) ) {
			$css_url = $old_font['css'];
			
			if ( strpos( $css_url, 'syhtcjk' ) !== false ) {
				return 'cszt';
			}
			
			if ( preg_match( '/fonts\/([^\/]+)\//', $css_url, $matches ) ) {
				return $matches[1];
			}
		}

		return 'cszt';
	}

	private function extract_subset_from_old_config( $old_font ) {
		if ( isset( $old_font['css'] ) ) {
			$css_url = $old_font['css'];
			
			if ( strpos( $css_url, '/regular/' ) !== false ) {
				return 'regular';
			}
			if ( strpos( $css_url, '/bold/' ) !== false ) {
				return 'bold';
			}
			if ( strpos( $css_url, '/light/' ) !== false ) {
				return 'light';
			}
			if ( strpos( $css_url, '/medium/' ) !== false ) {
				return 'medium';
			}
		}

		if ( isset( $old_font['weight'] ) ) {
			$weight = intval( $old_font['weight'] );
			if ( $weight <= 200 ) {
				return 'thin';
			} elseif ( $weight <= 300 ) {
				return 'light';
			} elseif ( $weight <= 500 ) {
				return 'regular';
			} elseif ( $weight <= 600 ) {
				return 'medium';
			} elseif ( $weight <= 700 ) {
				return 'semibold';
			} elseif ( $weight <= 800 ) {
				return 'bold';
			} else {
				return 'black';
			}
		}

		return 'regular';
	}
}