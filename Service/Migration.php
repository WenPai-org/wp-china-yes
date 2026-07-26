<?php

namespace WenPai\ChinaYes\Service;

defined( 'ABSPATH' ) || exit;

use function WenPai\ChinaYes\get_settings;

class Migration {

	private $settings;

	public function __construct() {
		$this->settings = get_settings();
		add_action( 'admin_init', [ $this, 'migrate_windfonts_settings' ] );
		add_action( 'admin_init', [ $this, 'migrate_deprecate_frontend_acceleration' ] );
	}

	/**
	 * 摘除已废弃的「前台加速」选项（3.9.3）。
	 *
	 * public.admincdn.com 是共享端点，无法持有各站自有的 wp-content 内容；
	 * 保留该选项只会让站点继续把资源指向一个必然取不到正确文件的地址。
	 * 详见 Service/Acceleration.php 中的废弃说明。
	 *
	 * 该选项此前默认关闭，因此绝大多数站点此迁移是空操作。
	 */
	public function migrate_deprecate_frontend_acceleration() {
		$current_settings = get_option( 'wp_china_yes', [] );

		if ( ! is_array( $current_settings ) || empty( $current_settings['admincdn_files'] ) ) {
			return;
		}

		$files = (array) $current_settings['admincdn_files'];

		// checkbox 字段可能是 ['frontend'] 或 ['frontend' => 'frontend'] 两种形态，都要处理
		$had_frontend = in_array( 'frontend', $files, true ) || array_key_exists( 'frontend', $files );

		if ( ! $had_frontend ) {
			return;
		}

		$files = array_filter(
			$files,
			static function ( $value, $key ) {
				return 'frontend' !== $value && 'frontend' !== $key;
			},
			ARRAY_FILTER_USE_BOTH
		);

		$current_settings['admincdn_files'] = $files;
		update_option( 'wp_china_yes', $current_settings );

		if ( function_exists( '\WenPai\ChinaYes\clear_settings_cache' ) ) {
			\WenPai\ChinaYes\clear_settings_cache();
		}
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