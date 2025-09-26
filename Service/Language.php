<?php

namespace WenPai\ChinaYes\Service;

defined( 'ABSPATH' ) || exit;

use function WenPai\ChinaYes\get_settings;

/**
 * Class Language
 * 语言切换服务
 * @package WenPai\ChinaYes\Service
 */
class Language {

	private $settings;

	public function __construct() {
		$this->settings = get_settings();

		if ( $this->is_enabled( $this->settings['waimao_enable'] ?? false ) ) {
			if ( $this->is_enabled( $this->settings['waimao_language_split'] ?? false ) ) {
				$this->init_language_split();
			}
		}

		add_action( 'wp_china_yes_wp_china_yes_save_after', [ $this, 'apply_language_settings' ] );
		
		add_action( 'update_option_WPLANG', [ $this, 'sync_frontend_language_to_plugin' ], 10, 2 );
		add_action( 'updated_user_meta', [ $this, 'sync_admin_language_to_plugin' ], 10, 4 );
		add_action( 'admin_init', [ $this, 'sync_plugin_settings_from_wp' ] );
	}

	/**
	 * 初始化语言分离功能
	 */
	private function init_language_split() {
		add_filter( 'locale', [ $this, 'set_locale' ], 10, 1 );
		add_filter( 'determine_locale', [ $this, 'determine_locale' ], 10, 1 );
		
		if ( $this->is_enabled( $this->settings['waimao_auto_detect'] ?? false ) ) {
			add_action( 'init', [ $this, 'auto_detect_language' ], 1 );
		}
	}

	/**
	 * 设置语言环境
	 */
	public function set_locale( $locale ) {
		if ( is_admin() ) {
			return $this->get_admin_locale();
		} else {
			return $this->get_frontend_locale();
		}
	}

	/**
	 * 确定语言环境
	 */
	public function determine_locale( $locale ) {
		if ( is_admin() ) {
			return $this->get_admin_locale();
		} else {
			return $this->get_frontend_locale();
		}
	}

	/**
	 * 应用语言设置到WordPress系统
	 */
	public function apply_language_settings( $data ) {
		$this->settings = $data;
		
		$waimao_enable = $this->settings['waimao_enable'] ?? false;
		$language_split = $this->settings['waimao_language_split'] ?? false;
		
		if ( $this->is_enabled( $waimao_enable ) ) {
			if ( $this->is_enabled( $language_split ) ) {
				
				if ( ! empty( $this->settings['waimao_admin_language'] ) ) {
					$user_id = get_current_user_id();
					if ( $user_id ) {
						update_user_meta( $user_id, 'locale', $this->settings['waimao_admin_language'] );
					}
				}

				if ( isset( $this->settings['waimao_frontend_language'] ) ) {
					$old_wplang = get_option( 'WPLANG', '' );
					$new_wplang = $this->settings['waimao_frontend_language'];
					
					if ( $old_wplang !== $new_wplang ) {
						update_option( 'WPLANG', $new_wplang );
						
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( "WPLANG updated from '{$old_wplang}' to '{$new_wplang}'" );
						}
					}
				}
			}
		}
	}

	/**
	 * 检查设置是否启用（处理布尔值和字符串）
	 */
	private function is_enabled( $value ) {
		return $value === true || $value === 'true' || $value === '1' || $value === 1;
	}

	/**
	 * 获取后台语言
	 */
	private function get_admin_locale() {
		$admin_language = $this->settings['waimao_admin_language'] ?? get_locale();
		return $admin_language;
	}

	/**
	 * 获取前台语言
	 */
	private function get_frontend_locale() {
		if ( $this->is_enabled( $this->settings['waimao_auto_detect'] ?? false ) ) {
			$detected_locale = $this->detect_browser_language();
			if ( $detected_locale ) {
				return $detected_locale;
			}
		}
		
		$frontend_language = $this->settings['waimao_frontend_language'] ?? get_option('WPLANG', 'en_US');
		return $frontend_language;
	}

	/**
	 * 自动检测语言
	 */
	public function auto_detect_language() {
		if ( is_admin() ) {
			return;
		}

		$detected_locale = $this->detect_browser_language();
		if ( $detected_locale ) {
			add_filter( 'locale', function() use ( $detected_locale ) {
				return $detected_locale;
			}, 20 );
		}
	}

	/**
	 * 检测浏览器语言
	 */
	private function detect_browser_language() {
		if ( ! isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
			return false;
		}

		$supported_languages = [
			'zh-cn' => 'zh_CN',
			'zh-tw' => 'zh_TW',
			'zh-hk' => 'zh_TW',
			'en-us' => 'en_US',
			'en-gb' => 'en_GB',
			'en'    => 'en_US',
			'ja'    => 'ja',
			'ko'    => 'ko_KR',
			'de'    => 'de_DE',
			'fr'    => 'fr_FR',
			'es'    => 'es_ES',
			'ru'    => 'ru_RU',
		];

		$accept_language = strtolower( $_SERVER['HTTP_ACCEPT_LANGUAGE'] );
		$languages = explode( ',', $accept_language );

		foreach ( $languages as $language ) {
			$language = trim( explode( ';', $language )[0] );
			
			if ( isset( $supported_languages[ $language ] ) ) {
				return $supported_languages[ $language ];
			}
			
			$language_code = explode( '-', $language )[0];
			if ( isset( $supported_languages[ $language_code ] ) ) {
				return $supported_languages[ $language_code ];
			}
		}

		return false;
	}

	/**
	 * 获取可用语言列表
	 */
	public static function get_available_languages() {
		return [
			'zh_CN' => '简体中文',
			'zh_TW' => '繁体中文',
			'en_US' => 'English (US)',
			'en_GB' => 'English (UK)',
			'ja'    => '日本語',
			'ko_KR' => '한국어',
			'de_DE' => 'Deutsch',
			'fr_FR' => 'Français',
			'es_ES' => 'Español',
			'ru_RU' => 'Русский',
		];
	}

	/**
	 * 检查语言文件是否存在
	 */
	public function is_language_available( $locale ) {
		if ( $locale === 'en_US' ) {
			return true;
		}

		$language_file = WP_LANG_DIR . '/wp-' . $locale . '.mo';
		return file_exists( $language_file );
	}

	/**
	 * 同步前台语言设置到插件
	 */
	public function sync_frontend_language_to_plugin( $old_value, $new_value ) {
		if ( $this->is_enabled( $this->settings['waimao_enable'] ?? false ) ) {
			if ( $this->is_enabled( $this->settings['waimao_language_split'] ?? false ) ) {
				$current_settings = get_option( 'wp_china_yes', [] );
				$current_settings['waimao_frontend_language'] = $new_value;
				update_option( 'wp_china_yes', $current_settings );
			}
		}
	}

	/**
	 * 同步后台语言设置到插件
	 */
	public function sync_admin_language_to_plugin( $meta_id, $user_id, $meta_key, $meta_value ) {
		if ( $meta_key === 'locale' && $user_id === get_current_user_id() ) {
			if ( $this->is_enabled( $this->settings['waimao_enable'] ?? false ) ) {
				if ( $this->is_enabled( $this->settings['waimao_language_split'] ?? false ) ) {
					$current_settings = get_option( 'wp_china_yes', [] );
					$current_settings['waimao_admin_language'] = $meta_value;
					update_option( 'wp_china_yes', $current_settings );
				}
			}
		}
	}

	/**
	 * 从WordPress系统同步语言设置到插件
	 */
	public function sync_plugin_settings_from_wp() {
		if ( $this->is_enabled( $this->settings['waimao_enable'] ?? false ) ) {
			if ( $this->is_enabled( $this->settings['waimao_language_split'] ?? false ) ) {
				$current_settings = get_option( 'wp_china_yes', [] );
				$needs_update = false;

				$wp_frontend_lang = get_option( 'WPLANG', '' );
				if ( $current_settings['waimao_frontend_language'] !== $wp_frontend_lang ) {
					$current_settings['waimao_frontend_language'] = $wp_frontend_lang;
					$needs_update = true;
				}

				$user_locale = get_user_meta( get_current_user_id(), 'locale', true );
				if ( $user_locale && $current_settings['waimao_admin_language'] !== $user_locale ) {
					$current_settings['waimao_admin_language'] = $user_locale;
					$needs_update = true;
				}

				if ( $needs_update ) {
					update_option( 'wp_china_yes', $current_settings );
					$this->settings = $current_settings;
				}
			}
		}
	}
}