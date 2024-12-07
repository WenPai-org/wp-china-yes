<?php

namespace WenPai\ChinaYes\Service;

defined( 'ABSPATH' ) || exit;

use function WenPai\ChinaYes\get_settings;

/**
 * Class Monitor
 * 插件监控服务
 * @package WenPai\ChinaYes\Service
 */
class Monitor {

	private $settings;

	public function __construct() {
		$this->settings = get_settings();
		wp_clear_scheduled_hook( 'wp_china_yes_maybe_check_store' ); // TODO 下个版本移除
		wp_clear_scheduled_hook( 'wp_china_yes_maybe_check_cravatar' ); // TODO 下个版本移除
		wp_clear_scheduled_hook( 'wp_china_yes_maybe_check_admincdn' ); // TODO 下个版本移除
		if ( $this->settings['monitor'] ) {
			// 站点网络下只在主站运行
			if ( is_main_site() ) {
				add_action( 'init', [ $this, 'init' ] );
				add_action( 'wp_china_yes_monitor', [
					$this,
					'run_monitor'
				] );
			}
		} else {
			if ( wp_get_scheduled_event( 'wp_china_yes_monitor' ) ) {
				wp_clear_scheduled_hook( 'wp_china_yes_monitor' );
			}
		}
	}

	/**
	 * 初始化
	 */
	public function init() {
		if ( ! wp_next_scheduled( 'wp_china_yes_monitor' ) ) {
			wp_schedule_event( time(), 'hourly', 'wp_china_yes_monitor' );
		}
	}

	/**
	 * 运行监控
	 */
	public function run_monitor() {
		if ( $this->settings['store'] != 'off' ) {
			$this->maybe_check_store();
		}
		if ( $this->settings['cravatar'] != 'off' ) {
			$this->maybe_check_cravatar();
		}
		if ( ! empty( $this->settings['admincdn'] ) ) {
			$this->maybe_check_admincdn();
		}
	}

	/**
	 * 检查应用市场可用性
	 */
	public function maybe_check_store() {
		$test_url = 'https://api.wenpai.net/china-yes/version-check';
		if ( $this->settings['store'] == 'proxy' ) {
			$test_url = 'https://api.wpmirror.com/core/version-check/1.7/';
		}
		$response = wp_remote_get( $test_url );
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != 200 ) {
			if ( $this->settings['store'] == 'wenpai' ) {
				$this->settings['store'] = 'proxy';
			} elseif ( $this->settings['store'] == 'proxy' ) {
				$this->settings['store'] = 'off';
			}
			$this->update_settings();
		}
	}

	/**
	 * 检查初认头像可用性
	 */
	public function maybe_check_cravatar() {
		$test_url = 'https://cn.cravatar.com/avatar/';
		switch ( $this->settings['cravatar'] ) {
			case 'global':
				$test_url = 'https://en.cravatar.com/avatar/';
				break;
			case 'weavatar':
				$test_url = 'https://weavatar.com/avatar/';
				break;
		}
		$response = wp_remote_get( $test_url );
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != 200 ) {
			if ( $this->settings['cravatar'] == 'cn' ) {
				$this->settings['cravatar'] = 'global';
			} elseif ( $this->settings['cravatar'] == 'global' ) {
				$this->settings['cravatar'] = 'weavatar';
			} elseif ( $this->settings['cravatar'] == 'weavatar' ) {
				$this->settings['cravatar'] = 'cn';
			}
			$this->update_settings();
		}
	}

	/**
	 * 检查萌芽加速可用性
	 */
	public function maybe_check_admincdn() {
		// 后台加速
		if ( in_array( 'admin', $this->settings['admincdn'] ) ) {
			$response = wp_remote_get( 'https://wpstatic.admincdn.com/6.7/wp-includes/js/wp-sanitize.min.js' );
			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != 200 ) {
				$this->settings['admincdn'] = array_values( array_diff( $this->settings['admincdn'], [ 'admin' ] ) );
				$this->update_settings();
			}
		}
		// 前台加速
		if ( in_array( 'frontend', $this->settings['admincdn'] ) ) {
			$url      = network_site_url( '/wp-includes/js/wp-sanitize.min.js' );
			$response = wp_remote_get( 'https://public.admincdn.com/' . $url );
			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != 200 ) {
				$this->settings['admincdn'] = array_values( array_diff( $this->settings['admincdn'], [ 'frontend' ] ) );
				$this->update_settings();
			}
		}
		// Google 字体
		if ( in_array( 'googlefonts', $this->settings['admincdn'] ) ) {
			$response = wp_remote_get( 'https://googlefonts.admincdn.com/css?family=Roboto:400,700' );
			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != 200 ) {
				$this->settings['admincdn'] = array_values( array_diff( $this->settings['admincdn'], [ 'googlefonts' ] ) );
				$this->update_settings();
			}
		}
		// Google 前端公共库
		if ( in_array( 'googleajax', $this->settings['admincdn'] ) ) {
			$response = wp_remote_get( 'https://googleajax.admincdn.com/ajax/libs/jquery/3.7.1/jquery.slim.min.js' );
			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != 200 ) {
				$this->settings['admincdn'] = array_values( array_diff( $this->settings['admincdn'], [ 'googleajax' ] ) );
				$this->update_settings();
			}
		}
		// CDNJS 前端公共库
		if ( in_array( 'cdnjs', $this->settings['admincdn'] ) ) {
			$response = wp_remote_get( 'https://cdnjs.admincdn.com/jquery/3.7.1/jquery.slim.min.js' );
			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != 200 ) {
				$this->settings['admincdn'] = array_values( array_diff( $this->settings['admincdn'], [ 'cdnjs' ] ) );
				$this->update_settings();
			}
		}
		// jsDelivr 公共库
		if ( in_array( 'jsdelivr', $this->settings['admincdn'] ) ) {
			$response = wp_remote_get( 'https://jsd.admincdn.com/npm/jquery@3.7.1/dist/jquery.slim.min.js' );
			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != 200 ) {
				$this->settings['admincdn'] = array_values( array_diff( $this->settings['admincdn'], [ 'jsdelivr' ] ) );
				$this->update_settings();
			}
		}
	}

	/**
	 * 更新设置
	 */
	private function update_settings() {
		if ( is_multisite() ) {
			update_site_option( 'wp_china_yes', $this->settings );
		} else {
			update_option( 'wp_china_yes', $this->settings, true );
		}
	}
}
