<?php

namespace WenPai\ChinaYes\Service;

defined( 'ABSPATH' ) || exit;

use TheTNB\Setting\API;

/**
 * Class Setting
 * 插件设置服务
 * @package WenPai\ChinaYes\Service
 */
class Setting {
	private $setting_api;

	public function __construct() {
		$this->setting_api = new API();
		add_action( 'admin_init', [ $this, 'admin_init' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu', [ $this, 'admin_menu' ] );
	}

	/**
	 * 挂载设置项
	 */
	public function admin_init() {

		$sections = [
			[
				'id'    => 'wp_china_yes',
				'title' => __( '设置', 'wp-china-yes' )
			]
		];

		$fields = [
			'wp_china_yes' => [
				[
					'name'    => 'store',
					'label'   => __( '应用市场', 'wp-china-yes' ),
					'desc'    => __( '<a href="https://wpmirror.com/" target="_blank">官方加速源（WPMirror）</a>直接从 .org 反代至大陆分发；<a href="https://wenpai.org/" target="_blank">文派开源（WenPai.org）</a>中国境内自建托管仓库，同时集成文派翻译平台。',
						'wp-china-yes' ),
					'type'    => 'radio',
					'default' => 'wenpai',
					'options' => [
						'proxy'  => '官方镜像',
						'wenpai' => '文派开源',
						'off'    => '不启用'
					]
				],
				[
					'name'    => 'admincdn',
					'label'   => __( '萌芽加速', 'wp-china-yes' ),
					'desc'    => __( '<a href="https://admincdn.com/" target="_blank">萌芽加速（adminCDN）</a>将 WordPress 依赖的静态文件切换为公共资源，加快网站访问速度。您可按需启用需要加速的项目，更多细节控制和功能，请关注 adminCDN 项目。',
						'wp-china-yes' ),
					'type'    => 'multicheck',
					'default' => [
						'admin' => 'admin',
					],
					'options' => [
						'admin'       => '后台加速',
						'frontend'    => '前台加速',
						'googlefonts' => 'Google 字体',
						'googleajax'  => 'Google 前端公共库',
						'cdnjs'       => 'CDNJS 前端公共库',
						'jsdelivr'    => 'jsDelivr 公共库',
					]
				],
				[
					'name'    => 'cravatar',
					'label'   => __( '初认头像', 'wp-china-yes' ),
					'desc'    => __( '<a href="https://cravatar.com/" target="_blank">初认头像（Cravatar）</a>Gravatar 在中国的完美替代方案，您可以在 Cravatar.com 上传头像，更多选项请安装 WPAavatar 插件。（任何开发者均可在自己的产品中集成该服务，不局限于 WordPress）',
						'wp-china-yes' ),
					'type'    => 'radio',
					'default' => 'cn',
					'options' => [
						'cn'       => '默认线路',
						'global'   => '国际线路',
						'weavatar' => '备用源（<a href="https://weavatar.com/" target="_blank">WeAvatar</a>）',
						'off'      => '不启用'
					]
				],
				[
					'name'    => 'windfonts',
					'label'   => __( '文风字体', 'wp-china-yes' ),
					'desc'    => __( '<a href="https://windfonts.com/" target="_blank">文风字体（Windfonts）</a>为您的网页渲染中文字体并对主题、插件内的字体进行加速；优化模式可优化浏览器字体调用。',
						'wp-china-yes' ),
					'type'    => 'radio',
					'default' => 'off',
					'options' => [
						'optimize' => '优化模式',
						'off' => '不启用',
					]
				],
				[
					'name'    => 'adblock',
					'label'   => __( '广告拦截', 'wp-china-yes' ),
					'desc'    => __( '<a href="https://wp-china-yes.com/ads" target="_blank">文派叶子🍃（WP-China-Yes）</a>独家特色功能，让您拥有清爽整洁的 WordPress 后台，清除各类常用插件侵入式后台广告、通知及无用信息；启用后若存在异常拦截，请切换为手动模式，查看<a href="https://wp-china-yes.com/" target="_blank">可优化插件列表</a>。',
						'wp-china-yes' ),
					'type'    => 'radio',
					'default' => 'off',
					'options' => [
						'off' => '即将上线',
					]
				],
				[
					'name'    => 'monitor',
					'label'   => __( '自动监控', 'wp-china-yes' ),
					'desc'    => __( '<a href="https://wp-china-yes.com/ads" target="_blank">文派叶子🍃（WP-China-Yes）</a>支持自动监控各加速节点可用性，当节点不可用时自动切换至可用节点或关闭加速，以保证您的网站正常访问。',
						'wp-china-yes' ),
					'type'    => 'radio',
					'default' => 'on',
					'options' => [
						'on'  => '启用',
						'off' => '不启用',
					]
				],
			]
		];

		$this->setting_api->set_sections( $sections );
		$this->setting_api->set_fields( $fields );
		$this->setting_api->admin_init();
	}

	/**
	 * 加载后台资源
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( strpos( $hook_suffix, 'wp-china-yes' ) === false ) {
			return;
		}
		wp_enqueue_style( 'wpcy-admin', CHINA_YES_PLUGIN_URL . 'assets/css/setting.css', [], CHINA_YES_VERSION );
	}

	/**
	 * 挂载设置页面
	 */
	public function admin_menu() {
		// 后台设置
		add_submenu_page(
			is_multisite() ? 'settings.php' : 'options-general.php',
			esc_html__( 'WP-China-Yes', 'wp-china-yes' ),
			esc_html__( 'WP-China-Yes', 'wp-china-yes' ),
			is_multisite() ? 'manage_network_options' : 'manage_options',
			'wp-china-yes',
			[ $this, 'setting_page' ]
		);
		// 插件页设置
		add_filter( 'plugin_action_links', function ( $links, $file ) {
			if ( 'wp-china-yes/wp-china-yes.php' !== $file ) {
				return $links;
			}
			$settings_link = '<a href="' . add_query_arg( array( 'page' => 'wp-china-yes' ),
					is_multisite() ? 'settings.php' : 'options-general.php' ) . '">' . esc_html__( '设置',
					'wp-china-yes' ) . '</a>';
			array_unshift( $links, $settings_link );

			return $links;
		}, 10, 2 );
	}

	/**
	 * 设置页面模版
	 */
	public function setting_page() {
		echo <<<HTML
<div class="settings-header">
    <div class="settings-title-section">
        <h1>文派叶子🍃（WP-China-Yes）</h1>
    </div>
    <h3>将您的 WordPress 接入本土生态体系，这将为您提供一个更贴近中国人使用习惯的 WordPress。</h3>
    <h4>100% 开源代码，一起参与文派（WordPress）软件国产化进程，打造属于您自己的开源自助建站程序。</h4>
</div>
<div class="container">
    <div class="left-column">
HTML;
		$this->setting_api->show_navigation();
		$this->setting_api->show_forms();

		echo <<<HTML
        <div class="settings-footer">
            <p><strong>提示：</strong>插件会定期检查节点可用性，并在节点不可用时自动切换至可用节点，以保证您的网站正常访问。如您发现设置项被自动切换，可重新设置或关闭自动监控功能。</p>
            <p><strong>帮助：</strong>您可以随时在此处调整个性化设置以便适应不同的业务场景，萌新请保持默认即可。此项目的发展离不开您的支持和建议，<a href="https://wp-china-yes.com/contact" target="_blank">查看联系方式</a>。</p>
        </div>
    </div>
    <div class="right-column">
        <div class="card">
            <h3>项目简介</h3>
            <div class="card-body">
                文派叶子 🍃（WP-China-Yes）是一款不可多得的 WordPress 系统底层优化和生态基础设施软件。项目起源于 2019 年，专为解决困扰了中国互联网数十年的特色问题而存在。此为文派开源（WenPai.org）的一部分。
            </div>
            <div class="card-footer">
                <a class="button button-primary" href="https://wp-china-yes.com/" target="_blank">了解更多</a>
            </div>
        </div>
        <div class="card">
            <h3>赞助商</h3>
            <div class="card-body sponsor-logos">
                <img src="https://wp-china-yes.com/wp-content/uploads/2023/08/feibisi-logo.png">
                <img src="https://wp-china-yes.com/wp-content/uploads/2023/08/shujue-logo.png">
                <img src="https://wp-china-yes.com/wp-content/uploads/2023/08/upyun-logo.png">
                <img src="https://wp-china-yes.com/wp-content/uploads/2023/08/wenpai-logo@2X.png">
                <img src="https://wp-china-yes.com/wp-content/uploads/2023/08/wpsaas-logo.png">
                <img src="https://wp-china-yes.com/wp-content/uploads/2023/08/lingding-logo.png">
                <img src="https://wp-china-yes.com/wp-content/uploads/2023/08/weixiaoduo-logo-2020.png">
                <img src="https://wp-china-yes.com/wp-content/uploads/2023/08/modiqi-logo.png">
                <img src="https://wp-china-yes.com/wp-content/uploads/2023/08/kekechong-logo-1.png">
            </div>
            <div class="card-footer">
                <a class="button button-primary" href="https://wp-china-yes.com/about/sponsor" target="_blank">成为赞助商</a>
            </div>
        </div>
        <div class="card">
            <h3>建站套件</h3>
            <div class="card-body">
                <ul>
                    <li><a href="https://wenpai.org/plugins/wpicp-license" target="_blank">WPICP License 备案号管理器</a></li>
                    <li><a href="https://wenpai.org/plugins/wpavatar/" target="_blank">WPAvatar 文派头像</a></li>
                    <li><a href="https://wenpai.org/plugins/wpsite-shortcode/" target="_blank">WPSite Shortcode 网站简码</a></li>
                    <li><a href="https://wenpai.org/plugins/wpfanyi-import/" target="_blank">WPfanyi Import 翻译导入器</a></li>
                </ul>
            </div>
            <div class="card-footer">
                <a class="button button-primary" href="https://wp-china-yes.com/products" target="_blank">一键安装</a>
                <a class="button button-primary" href="https://wp-china-yes.com" target="_blank">功能请求</a>
            </div>
        </div>
    </div>
</div>
HTML;

	}
}
