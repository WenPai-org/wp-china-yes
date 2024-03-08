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

		add_action( 'init', [ $this, 'init' ] );
		add_action( 'wp_china_yes_maybe_check_store', [
			$this,
			'maybe_check_store'
		] );
		add_action( 'wp_china_yes_maybe_check_cravatar', [
			$this,
			'maybe_check_cravatar'
		] );
		add_action( 'wp_china_yes_maybe_check_admincdn', [
			$this,
			'maybe_check_admincdn'
		] );
	}

	/**
	 * 初始化
	 */
	public function init() {
		// 检查应用市场可用性
		if ( ! wp_next_scheduled( 'wp_china_yes_maybe_check_store' ) && $this->settings['store'] != 'off' ) {
			wp_schedule_event( time(), 'hourly', 'wp_china_yes_maybe_check_store' );
		}
		// 检查初认头像可用性
		if ( ! wp_next_scheduled( 'wp_china_yes_maybe_check_cravatar' ) && $this->settings['cravatar'] != 'off' ) {
			wp_schedule_event( time(), 'hourly', 'wp_china_yes_maybe_check_cravatar' );
		}
		// 检查萌芽加速可用性
		if ( ! wp_next_scheduled( 'wp_china_yes_maybe_check_admincdn' ) && ! empty( $this->settings['admincdn'] ) ) {
			wp_schedule_event( time(), 'hourly', 'wp_china_yes_maybe_check_admincdn' );
		}
	}

	/**
	 * 检查应用市场可用性
	 */
	public function maybe_check_store() {
		$test_url = 'https://api.wenpai.org/china-yes/version-check';
		if ( $this->settings['store'] == 'proxy' ) {
			$test_url = 'http://wpa.cdn.haozi.net/core/version-check/1.7/';
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
		if ( ! empty( $this->settings['admincdn']['admin'] ) ) {
			$response = wp_remote_get( 'https://wpstatic.admincdn.com/6.4.3/wp-includes/js/wp-sanitize.min.js' );
			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != 200 ) {
				unset( $this->settings['admincdn']['admin'] );
				$this->update_settings();
			}
		}
		// 前台加速
		if ( ! empty( $this->settings['admincdn']['frontend'] ) ) {
			$url      = network_site_url( '/wp-includes/js/wp-sanitize.min.js' );
			$response = wp_remote_get( 'https://public.admincdn.com/' . $url );
			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != 200 ) {
				unset( $this->settings['admincdn']['frontend'] );
				$this->update_settings();
			}
		}
		// Google 字体
		if ( ! empty( $this->settings['admincdn']['googlefonts'] ) ) {
			$response = wp_remote_get( 'https://googlefonts.admincdn.com/css?family=Roboto:400,700' );
			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != 200 ) {
				unset( $this->settings['admincdn']['googlefonts'] );
				$this->update_settings();
			}
		}
		// Google 前端公共库
		if ( ! empty( $this->settings['admincdn']['googleajax'] ) ) {
			$response = wp_remote_get( 'https://googleajax.admincdn.com/ajax/libs/jquery/3.5.1/jquery.min.js' );
			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != 200 ) {
				unset( $this->settings['admincdn']['googleajax'] );
				$this->update_settings();
			}
		}
		// CDNJS 前端公共库
		if ( ! empty( $this->settings['admincdn']['cdnjs'] ) ) {
			$response = wp_remote_get( 'https://cdnjs.admincdn.com/jquery/3.5.1/jquery.min.js' );
			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != 200 ) {
				unset( $this->settings['admincdn']['cdnjs'] );
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
			update_option( 'wp_china_yes', $this->settings );
		}
	}
}
