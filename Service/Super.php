<?php

namespace WenPai\ChinaYes\Service;

defined( 'ABSPATH' ) || exit;

use function WenPai\ChinaYes\get_settings;

/**
 * Class Super
 * 插件加速服务
 * @package WenPai\ChinaYes\Service
 */
class Super {

	private $settings;

	public function __construct() {
		$this->settings = get_settings();

		/**
		 * WordPress.Org API 替换
		 */
		if ( is_admin() || wp_doing_cron() ) {
			if ( $this->settings['store'] != 'off' ) {
				add_filter( 'pre_http_request', [ $this, 'filter_wordpress_org' ], 100, 3 );
			}
		}

		/**
		 * 添加「文派茶馆」小组件
		 */
		if ( is_admin() ) {
			add_action( 'wp_dashboard_setup', function () {
				global $wp_meta_boxes;

				unset( $wp_meta_boxes['dashboard']['side']['core']['dashboard_primary'] );
				wp_add_dashboard_widget( 'wenpai_tea', '文派茶馆', function () {
					echo <<<HTML
					<div class="wordpress-news hide-if-no-js">
					<div class="rss-widget">
					HTML;
					wp_widget_rss_output( 'https://wptea.com/feed/', [
						'items'        => 5,
						'show_summary' => 1,
					] );
					echo <<<HTML
					</div>
					</div>
					<p class="community-events-footer">
					<a href="https://wenpai.org/" target="_blank">文派开源</a>
					 | 
					<a href="https://wenpai.org/support" target="_blank">支持论坛</a>
					 | 
					<a href="https://translate.wenpai.org/" target="_blank">翻译平台</a>
					</p>
					<style>
						#wenpai_tea .rss-widget {
						  font-size:13px;
						  padding:0 12px
						}
						#wenpai_tea .rss-widget:last-child {
						  border-bottom:none;
						  padding-bottom:8px
						}
						#wenpai_tea .rss-widget a {
						  font-weight:400
						}
						#wenpai_tea .rss-widget span,
						#wenpai_tea .rss-widget span.rss-date {
						  color:#646970
						}
						#wenpai_tea .rss-widget span.rss-date {
						  margin-left:12px
						}
						#wenpai_tea .rss-widget ul li {
						  padding:4px 0;
						  margin:0
						}
					</style>
					HTML;
				} );
			} );
			add_action( 'wp_network_dashboard_setup', function () {
				global $wp_meta_boxes;

				unset( $wp_meta_boxes['dashboard-network']['side']['core']['dashboard_primary'] );
				wp_add_dashboard_widget( 'wenpai_tea', '文派茶馆', function () {
					echo <<<HTML
					<div class="wordpress-news hide-if-no-js">
					<div class="rss-widget">
					HTML;
					wp_widget_rss_output( 'https://wptea.com/feed/', [
						'items'        => 5,
						'show_summary' => 1,
					] );
					echo <<<HTML
					</div>
					</div>
					<p class="community-events-footer">
					<a href="https://wenpai.org/" target="_blank">文派开源</a>
					 | 
					<a href="https://wenpai.org/support" target="_blank">支持论坛</a>
					 | 
					<a href="https://translate.wenpai.org/" target="_blank">翻译平台</a>
					</p>
					<style>
						#wenpai_tea .rss-widget {
						  font-size:13px;
						  padding:0 12px
						}
						#wenpai_tea .rss-widget:last-child {
						  border-bottom:none;
						  padding-bottom:8px
						}
						#wenpai_tea .rss-widget a {
						  font-weight:400
						}
						#wenpai_tea .rss-widget span,
						#wenpai_tea .rss-widget span.rss-date {
						  color:#646970
						}
						#wenpai_tea .rss-widget span.rss-date {
						  margin-left:12px
						}
						#wenpai_tea .rss-widget ul li {
						  padding:4px 0;
						  margin:0
						}
					</style>
					HTML;
				} );
			} );
		}

		/**
		 * WordPress 核心静态文件链接替换
		 */
		if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			if (
				! empty( $this->settings['admincdn']['admin'] ) &&
				! stristr( $GLOBALS['wp_version'], 'alpha' ) &&
				! stristr( $GLOBALS['wp_version'], 'beta' ) &&
				! stristr( $GLOBALS['wp_version'], 'RC' )
			) {
				// 禁用合并加载，以便于使用公共资源节点
				global $concatenate_scripts;
				$concatenate_scripts = false;

				$this->page_str_replace( 'init', 'preg_replace', [
					'~' . home_url( '/' ) . '(wp-admin|wp-includes)/(css|js)/~',
					sprintf( 'https://wpstatic.admincdn.com/%s/$1/$2/', $GLOBALS['wp_version'] )
				] );
			}
		}

		if ( ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			/**
			 * 前台静态加速
			 */
			if ( ! empty( $this->settings['admincdn']['frontend'] ) ) {
				$this->page_str_replace( 'template_redirect', 'preg_replace', [
					'#(?<=[(\"\'])(?:' . quotemeta( home_url() ) . ')?/(?:((?:wp-content|wp-includes)[^\"\')]+\.(css|js)[^\"\')]+))(?=[\"\')])#',
					'https://public.admincdn.com/$0'
				] );
			}

			/**
			 * Google 字体替换
			 */
			if ( ! empty( $this->settings['admincdn']['googlefonts'] ) ) {
				$this->page_str_replace( 'init', 'str_replace', [
					'fonts.googleapis.com',
					'googlefonts.admincdn.com'
				] );
			}

			/**
			 * Google 前端公共库替换
			 */
			if ( ! empty( $this->settings['admincdn']['googleajax'] ) ) {
				$this->page_str_replace( 'init', 'str_replace', [
					'ajax.googleapis.com',
					'googleajax.admincdn.com'
				] );
			}

			/**
			 * CDNJS 前端公共库替换
			 */
			if ( ! empty( $this->settings['admincdn']['cdnjs'] ) ) {
				$this->page_str_replace( 'init', 'str_replace', [
					'cdnjs.cloudflare.com/ajax/libs',
					'cdnjs.admincdn.com'
				] );
			}

			/**
			 * jsDelivr 前端公共库替换
			 */
			if ( ! empty( $this->settings['admincdn']['jsdelivr'] ) ) {
				$this->page_str_replace( 'init', 'str_replace', [
					'cdn.jsdelivr.net',
					'jsd.admincdn.com'
				] );
			}
		}

		/**
		 * 初认头像
		 */
		if ( ! empty( $this->settings['cravatar'] ) ) {
			add_filter( 'user_profile_picture_description', [ $this, 'set_user_profile_picture_for_cravatar' ], 1 );
			add_filter( 'avatar_defaults', [ $this, 'set_defaults_for_cravatar' ], 1 );
			add_filter( 'um_user_avatar_url_filter', [ $this, 'get_cravatar_url' ], 1 );
			add_filter( 'bp_gravatar_url', [ $this, 'get_cravatar_url' ], 1 );
			add_filter( 'get_avatar_url', [ $this, 'get_cravatar_url' ], 1 );
		}

		/**
		 * 文风字体
		 */
		if ( ! empty( $this->settings['windfonts'] ) && $this->settings['windfonts'] == 'optimize' ) {
			add_action( 'init', function () {
				wp_enqueue_style( 'windfonts-optimize', CHINA_YES_PLUGIN_URL . 'assets/css/fonts.css', [], CHINA_YES_VERSION );
			} );
		}
	}

	/**
	 * WordPress.Org 替换
	 */
	public function filter_wordpress_org( $preempt, $args, $url ) {
		if ( $preempt || isset( $args['_wp_china_yes'] ) ) {
			return $preempt;
		}
		if ( ( ! strpos( $url, 'api.wordpress.org' ) && ! strpos( $url, 'downloads.wordpress.org' ) ) ) {
			return $preempt;
		}

		if ( $this->settings['store'] == 'wenpai' ) {
			$url = str_replace( 'api.wordpress.org', 'api.wenpai.net', $url );
		} else {
			$url = str_replace( 'api.wordpress.org', 'api.wpmirror.com', $url );
		}
		$url = str_replace( 'downloads.wordpress.org', 'downloads.wenpai.net', $url );

		$curl_version = '1.0.0';
		if ( function_exists( 'curl_version' ) ) {
			$curl_version_array = curl_version();
			if ( is_array( $curl_version_array ) && key_exists( 'version', $curl_version_array ) ) {
				$curl_version = $curl_version_array['version'];
			}
		}
		if ( version_compare( $curl_version, '7.15.0', '<' ) ) {
			$url = str_replace( 'https://', 'http://', $url );
		}

		$args['_wp_china_yes'] = true;

		return wp_remote_request( $url, $args );
	}

	/**
	 * 初认头像替换
	 */
	public function get_cravatar_url( $url ) {
		switch ( $this->settings['cravatar'] ) {
			case 'cn':
				return $this->replace_avatar_url( $url, 'cn.cravatar.com' );
			case 'global':
				return $this->replace_avatar_url( $url, 'en.cravatar.com' );
			case 'weavatar':
				return $this->replace_avatar_url( $url, 'weavatar.com' );
			default:
				return $url;
		}
	}

	/**
	 * 头像 URL 替换
	 */
	public function replace_avatar_url( $url, $domain ) {
		$sources = array(
			'www.gravatar.com',
			'0.gravatar.com',
			'1.gravatar.com',
			'2.gravatar.com',
			's.gravatar.com',
			'secure.gravatar.com',
			'cn.gravatar.com',
			'en.gravatar.com',
			'gravatar.com',
			'sdn.geekzu.org',
			'gravatar.duoshuo.com',
			'gravatar.loli.net',
			'dn-qiniu-avatar.qbox.me'
		);

		return str_replace( $sources, $domain, $url );
	}

	/**
	 * WordPress 讨论设置中的默认 LOGO 名称替换
	 */
	public function set_defaults_for_cravatar( $avatar_defaults ) {
		if ( $this->settings['cravatar'] == 'weavatar' ) {
			$avatar_defaults['gravatar_default'] = 'WeAvatar';
		} else {
			$avatar_defaults['gravatar_default'] = '初认头像';
		}

		return $avatar_defaults;
	}

	/**
	 * 个人资料卡中的头像上传地址替换
	 */
	public function set_user_profile_picture_for_cravatar() {
		if ( $this->settings['cravatar'] == 'weavatar' ) {
			return '<a href="https://weavatar.com" target="_blank">您可以在 WeAvatar 修改您的资料图片</a>';
		} else {
			return '<a href="https://cravatar.com" target="_blank">您可以在初认头像修改您的资料图片</a>';
		}
	}

	/**
	 * 页面替换
	 *
	 * @param $replace_func string 要调用的字符串关键字替换函数
	 * @param $param array 传递给字符串替换函数的参数
	 */
	private function page_str_replace( $hook, $replace_func, $param ) {
		// CLI 下返回，防止影响缓冲区
		if ( php_sapi_name() == 'cli' ) {
			return;
		}
		add_action( $hook, function () use ( $replace_func, $param ) {
			ob_start( function ( $buffer ) use ( $replace_func, $param ) {
				$param[] = $buffer;

				return call_user_func_array( $replace_func, $param );
			} );
		}, PHP_INT_MAX );
	}
}
