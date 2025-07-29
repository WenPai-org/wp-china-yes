<?php

namespace WenPai\ChinaYes\Service;

defined( 'ABSPATH' ) || exit;

use WP_CHINA_YES;
use function WenPai\ChinaYes\get_settings;

/**
 * Class Setting
 * 插件设置服务
 * @package WenPai\ChinaYes\Service
 */
class Setting {
	private $prefix = 'wp_china_yes';
	private $settings;

	public function __construct() {
		$this->settings = get_settings();
		add_filter( 'wp_china_yes_enqueue_assets', '__return_true' );
		add_filter( 'wp_china_yes_fa4', '__return_true' );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu', [ $this, 'admin_menu' ] );
		self::admin_init();
	}


/**
 * 动态获取设置页面的 URL
 *
 * @return string
 */
private function get_settings_page_url() {
    if ( is_multisite() ) {
        return network_admin_url( 'settings.php?page=wp-china-yes' );
    }
    return admin_url( 'options-general.php?page=wp-china-yes' );
}

	/**
	 * 挂载设置项
	 */
	public function admin_init() {
		WP_CHINA_YES::createOptions( $this->prefix, [
			'framework_title'    => $this->settings['custom_name'], 
			'menu_hidden'        => $this->settings['hide'],
			'menu_title'         => $this->settings['custom_name'],
			'menu_slug'          => 'wp-china-yes',
			'menu_type'          => 'submenu',
			'menu_parent'        => is_multisite() ? 'settings.php' : 'options-general.php',
			'show_bar_menu'      => false,
			'show_sub_menu'      => false,
			'show_search'        => false,
			'show_reset_section' => false,
			'footer_text'        => sprintf( '%s 设置', $this->settings['custom_name'] ),
			'theme'              => 'light',
			'enqueue_webfont'    => false,
			'async_webfont'      => true,
			'database'           => is_multisite() ? 'network' : '',
		] );
 
    // 获取启用的 sections
        $enabled_sections = $this->settings['enabled_sections'] ?? ['welcome', 'store', 'admincdn', 'cravatar', 'other', 'about'];
 
    if (in_array('welcome', $enabled_sections)) {
        $settings_page_url = $this->get_settings_page_url();
            ob_start();
            include CHINA_YES_PLUGIN_PATH . 'templates/welcome-section.php';
            $welcome_content = ob_get_clean();
        
		WP_CHINA_YES::createSection( $this->prefix, [
			'title'  => '欢迎使用',
			'icon'   => 'icon icon-home-1',
			'fields' => [
				[
					'type'    => 'content',
					'content' =>$welcome_content,
				]
			],
		] );
    }
        if (in_array('store', $enabled_sections)) {
		WP_CHINA_YES::createSection( $this->prefix, [
			'title'  => '应用市场',
			'icon'   => 'icon icon-shop',
			'fields' => [
				[
					'id'       => 'store',
					'type'     => 'radio',
					'title'    => __( '应用市场', 'wp-china-yes' ),
					'inline'   => true,
					'options'  => [
	            		'wenpai' => '文派开源',
						'proxy'  => '官方镜像',
						'off'    => '不启用'
					],
					'default'  => 'wenpai',
					'subtitle' => '是否启用市场加速',
					'desc'     => __( '<a href="https://wenpai.org/" target="_blank">文派开源（WenPai.org）</a>中国境内自建托管仓库，同时集成文派翻译平台。<a href="https://wpmirror.com/" target="_blank">官方加速源（WPMirror）</a>直接从 .org 反代至大陆分发；可参考<a href="https://wpcy.com/document/wordpress-marketplace-acceleration" target="_blank">源站说明</a>。',
						'wp-china-yes' ),
				],
				[
					'id'       => 'bridge',
					'type'     => 'switcher',
					'default'  => true,
					'title'    => '云桥更新',
					'subtitle' => '是否启用更新加速',
					'desc'     => __( '<a href="https://wpbridge.com" target="_blank">文派云桥（wpbridge）</a>托管更新和应用分发渠道，可解决因 WordPress 社区分裂导致的混乱、旧应用无法更新，频繁 API 请求拖慢网速等问题。',
					'wp-china-yes' ),
				],
				[
					'id'       => 'arkpress',
					'type'     => 'switcher',
					'default'  => false,
					'title'    => '联合存储库',
					'subtitle' => '自动监控加速节点可用性',
					'desc'     => __( '<a href="https://maiyun.org" target="_blank">ArkPress.org </a>支持自动监控各加速节点可用性，当节点不可用时自动切换至可用节点或关闭加速，以保证您的网站正常访问',
					'wp-china-yes' ),
				],
			],
		] );
    }
    
    if (in_array('admincdn', $enabled_sections)) {
    WP_CHINA_YES::createSection($this->prefix, [
        'title'  => '萌芽加速',
        'icon'   => 'icon icon-flash-1',
        'fields' => [
            [
                'id'       => 'admincdn_public',
                'type'     => 'checkbox',
                'title'    => __('萌芽加速', 'wp-china-yes'),
                'inline'   => true,
                'options'  => [
                    'googlefonts'    => 'Google 字体',
                    'googleajax'     => 'Google 前端库',
                    'cdnjs'          => 'CDNJS 前端库',
                    'jsdelivr'       => 'jsDelivr 前端库',
                    'bootstrapcdn'   => 'Bootstrap 前端库',
                ],
                'default'  => [
                    'googlefonts'    => 'googlefonts',
                    'googleajax'     => '',
                    'cdnjs'          => '',
                    'jsdelivr'       => '',
                    'bootstrapcdn'   => '',
                ],
                'subtitle' => '是否启用萌芽加速',
                'desc'     => __('<a href="https://admincdn.com/" target="_blank">萌芽加速（adminCDN）</a>将 WordPress  插件依赖的静态文件切换为公共资源，解决卡顿、加载慢等问题。您可按需启用加速项目，更多细节控制和功能，请查看<a href="https://wpcy.com/document/admincdn" target="_blank">推荐设置</a>。',
                    'wp-china-yes'),
            ],
            [
                'id'       => 'admincdn_files',
                'type'     => 'checkbox',
                'title'    => __('文件加速', 'wp-china-yes'),
                'inline'   => true,
                'options'  => [
                    'admin'       => '后台加速',
                    'frontend'    => '前台加速',
                    'emoji'       => 'Emoji加速',
                    'sworg'       => '预览加速',
                ],
                'default'  => [
                    'admin'       => 'admin',
                    'frontend'    => '',
                    'emoji'       => 'emoji',
                    'sworg'       => '',
                ],
                'subtitle' => '是否启用文件加速',
                'desc'     => __('专为 WordPress 系统内置依赖的静态资源进行加速，加快网站访问速度，如遇异常请停用对应选项。预览加速可在不切换应用市场时加速插件目录预览截图。',
                    'wp-china-yes'),
            ],
            [
                'id'       => 'admincdn_dev',
                'type'     => 'checkbox',
                'title'    => __('开发加速', 'wp-china-yes'),
                'inline'   => true,
                'options'  => [
                    'react'          => 'React 前端库',
                    'jquery'         => 'jQuery 前端库',
                    'vuejs'          => 'Vue.js 前端库',
                    'datatables'     => 'DataTables 前端库',
                    'tailwindcss'    => 'Tailwind CSS'
                ],
                'default'  => [
                    'react'          => '',
                    'jquery'         => 'jquery',
                    'vuejs'          => '',
                    'datatables'     => '',
                    'tailwindcss'    => '',
                ],
                'subtitle' => '是否启用文件加速',
                'desc'     => __('部分高级 WordPress 插件主题会包含最新前端资源，可在此勾选对应的 adminCDN 子库专项加速。',
                    'wp-china-yes'),
            ],
        ],
    ]);
}
    
    if (in_array('cravatar', $enabled_sections)) {
		WP_CHINA_YES::createSection( $this->prefix, [
			'title'  => '初认头像',
			'icon'   => 'icon icon-profile-circle',
			'fields' => [
				[
					'id'       => 'cravatar',
					'type'     => 'radio',
					'title'    => __( '初认头像', 'wp-china-yes' ),
					'inline'   => true,
					'options'  => [
						'cn'       => '默认线路',
						'global'   => '国际线路',
						'weavatar' => '备用源（WeAvatar.com）',
						'off'      => '不启用'
					],
					'default'  => 'cn',
					'subtitle' => '是否启用头像加速',
					'desc'     => __( '<a href="https://cravatar.com/" target="_blank">初认头像（Cravatar）</a>Gravatar 在中国的完美替代方案，您可以在 Cravatar.com 上传头像，更多选项请安装 WPAavatar 插件。可自选<a href="https://wpcy.com/document/gravatar-alternatives" target="_blank">加速线路</a>。',
						'wp-china-yes' ),
				],
			],
		] );
    }
    
    if (in_array('windfonts', $enabled_sections)) {
		WP_CHINA_YES::createSection( $this->prefix, [
			'title'  => '文风字体',
			'icon'   => 'icon icon-text',
			'fields' => [
				[
					'id'       => 'windfonts',
					'type'     => 'radio',
					'title'    => __( '文风字体', 'wp-china-yes' ),
					'inline'   => true,
					'options'  => [
						'on'       => '全局启用',
						'frontend' => '前台启用',
						'optimize' => '本机字体',
						'off'      => '不启用',
					],
					'default'  => 'off',
					'subtitle' => '是否启用文风字体定制',
					'desc'     => __( '<a href="https://windfonts.com/" target="_blank">文风字体（Windfonts）</a>为您的网站增添无限活力。专为中文网页设计，旨在提升用户阅读体验和视觉享受。新手使用请先查看<a href="https://wpcy.com/document/chinese-fonts" target="_blank">字体使用说明</a>。',
						'wp-china-yes' ),
				],
				[
					'id'                     => 'windfonts_list',
					'type'                   => 'group',
					'title'                  => '字体列表',
					'subtitle'               => '使用的文风字体列表',
					'desc'                   => '支持添加多个文风字体，并配置应用元素、字体权重大小',
					'button_title'           => '添加字体',
					'accordion_title_number' => true,
					'dependency'             => [
						'windfonts',
						'any',
						'on,frontend',
					],
					'fields'                 => [
						[
							'id'       => 'family',
							'type'     => 'text',
							'title'    => __( '字体家族', 'wp-china-yes' ),
							'subtitle' => '字体家族名称',
							'desc'     => __( '填入从<a href="https://app.windfonts.com/" target="_blank">文风字体</a>获取的字体家族名称',
								'wp-china-yes' ),
							'default'  => 'wenfeng-syhtcjk',
						],
						[
							'id'       => 'css',
							'type'     => 'text',
							'title'    => __( '字体链接', 'wp-china-yes' ),
							'subtitle' => '字体 CSS 链接',
							'desc'     => __( '填入从<a href="https://app.windfonts.com/" target="_blank">文风字体</a>获取的字体 CSS 链接',
								'wp-china-yes' ),
							'default'  => 'https://cn.windfonts.com/wenfeng/fonts/syhtcjk/regular/web/index.css',
							'validate' => 'csf_validate_url',
						],
						[
							'id'         => 'weight',
							'type'       => 'number',
							'title'      => __( '字体字重', 'wp-china-yes' ),
							'subtitle'   => '字体字重大小',
							'desc'       => __( '设置字体权重大小（字体粗细）',
								'wp-china-yes' ),
							'default'    => 400,
							'attributes' => [
								'min'  => 100,
								'max'  => 1000,
								'step' => 10,
							],
							'validate'   => 'csf_validate_numeric',
						],
						[
							'id'       => 'style',
							'type'     => 'select',
							'title'    => __( '字体样式', 'wp-china-yes' ),
							'subtitle' => '字体样式选择',
							'options'  => [
								'normal'  => '正常',
								'italic'  => '斜体',
								'oblique' => '倾斜',
							],
							'desc'     => __( '设置字体样式（正常、斜体、倾斜）',
								'wp-china-yes' ),
						],
						[
							'id'       => 'selector',
							'type'     => 'textarea',
							'title'    => __( '字体应用', 'wp-china-yes' ),
							'subtitle' => '字体应用元素',
							'desc'     => __( '设置字体应用的元素（CSS 选择器）',
								'wp-china-yes' ),
							'default'  => 'a:not([class]),p,h1,h2,h3,h4,h5,h6,ul,ol,li,button,blockquote,pre,code,table,th,td,label,b,i:not([class]),em,small,strong,sub,sup,ins,del,mark,abbr,dfn,span:not([class])',
							'sanitize' => false,
						],
						[
							'id'       => 'enable',
							'type'     => 'switcher',
							'title'    => __( '启用字体', 'wp-china-yes' ),
							'subtitle' => '是否启用该字体',
							'default'  => true,
						],
					],
				],
				[
					'id'         => 'windfonts_typography',
					'type'       => 'checkbox',
					'title'      => __( '排印优化', 'wp-china-yes' ),
					'inline'     => true,
					'options'    => [
						'corner'      => '直角括号',
						'space'       => '文本空格',
						'punctuation' => '标点显示',
						'indent'      => '段首缩进',
						'align'       => '两端对齐',
					],
					'default'    => '',
					'subtitle'   => '是否启用排印优化',
					'desc'       => __( '文风字体排印优化可提升中文网页的视觉美感，适用于正式内容的网站。',
						'wp-china-yes' ),
				],
				[
					'id'         => 'windfonts_typography',
					'type'       => 'checkbox',
					'title'      => __( '英文美化', 'wp-china-yes' ),
					'inline'     => true,
					'options'    => [
    					'align'       => '排版优化',
						'corner'      => '去双空格',
						'space'       => '避免孤行',
						'punctuation' => '避免寡行',
						'indent'      => '中英标点',
					],
					'default'    => '',
					'subtitle'   => '是否启用英文美化',
					'desc'       => __( 'Windfonts 英文优化可提升英文网页的视觉美感，适用于多语内容网站。',
						'wp-china-yes' ),
				],
			],
		] );
    }

		if (in_array('motucloud', $enabled_sections)) {
		WP_CHINA_YES::createSection( $this->prefix, [
			'title'  => '墨图云集',
			'icon'   => 'icon icon-gallery',
			'fields' => [
				[
					'id'       => 'motucloud',
					'type'     => 'radio',
					'title'    => __( '墨图云集', 'wp-china-yes' ),
					'inline'   => true,
					'options'  => [
						'cn'       => '默认线路',
						'global'   => '国际线路',
						'weavatar' => '备用源（WeAvatar.com）',
						'off'      => '不启用'
					],
					'default'  => 'cn',
					'subtitle' => '是否启用墨图云集',
					'desc'     => __( '<a href="https://motucloud.com/" target="_blank">墨图云集（MotuCloud）</a>Gravatar 在中国的完美替代方案，您可以在 Cravatar.com 上传头像，更多选项请安装 WPAavatar 插件。可自选<a href="https://wpcy.com/document/gravatar-alternatives" target="_blank">加速线路</a>。',
						'wp-china-yes' ),
				],
			],
		] );
    }

		if (in_array('fewmail', $enabled_sections)) {
		WP_CHINA_YES::createSection( $this->prefix, [
			'title'  => '飞秒邮箱',
			'icon'   => 'icon icon-sms-tracking',
			'fields' => [
				[
					'id'       => 'fewmail',
					'type'     => 'radio',
					'title'    => __( '飞秒邮箱', 'wp-china-yes' ),
					'inline'   => true,
					'options'  => [
						'cn'       => '默认线路',
						'global'   => '国际线路',
						'weavatar' => '备用源（WeAvatar.com）',
						'off'      => '不启用'
					],
					'default'  => 'cn',
					'subtitle' => '是否启用飞秒邮箱',
					'desc'     => __( '<a href="https://fewmail.com/" target="_blank">飞秒邮箱（FewMail）</a>Gravatar 在中国的完美替代方案，您可以在 Cravatar.com 上传头像，更多选项请安装 WPAavatar 插件。可自选<a href="https://wpcy.com/document/gravatar-alternatives" target="_blank">加速线路</a>。',
						'wp-china-yes' ),
				],
			],
		] );
    }


		if (in_array('wordyeah', $enabled_sections)) {
		WP_CHINA_YES::createSection( $this->prefix, [
			'title'  => '无言会语',
			'icon'   => 'icon icon-message-text',
			'fields' => [
				[
					'id'       => 'wordyeah',
					'type'     => 'radio',
					'title'    => __( '无言会语', 'wp-china-yes' ),
					'inline'   => true,
					'options'  => [
						'cn'       => '审核评论',
						'global'   => '强化评论',
						'ban'      => '禁用评论',
						'off'      => '不启用'
					],
					'default'  => 'cn',
					'subtitle' => '是否启用无言会语',
					'desc'     => __( '<a href="https://wordyeah.com/" target="_blank">无言会语（WordYeah）</a>Gravatar 在中国的完美替代方案，您可以在 Cravatar.com 上传头像，更多选项请安装 WPAavatar 插件。可自选<a href="https://wpcy.com/document/gravatar-alternatives" target="_blank">加速线路</a>。',
						'wp-china-yes' ),
				],
			],
		] );
    }


		if (in_array('blocks', $enabled_sections)) {
		WP_CHINA_YES::createSection( $this->prefix, [
			'title'  => '笔笙区块',
			'icon'   => 'icon icon-document-1',
			'fields' => [
				[
					'id'       => 'bisheng',
					'type'     => 'radio',
					'title'    => __( '笔笙区块', 'wp-china-yes' ),
					'inline'   => true,
					'options'  => [
						'cn'       => '审核评论',
						'global'   => '强化评论',
						'ban'      => '禁用评论',
						'off'      => '不启用'
					],
					'default'  => 'cn',
					'subtitle' => '是否启用笔笙区块',
					'desc'     => __( '<a href="https://ibisheng.com/" target="_blank">笔笙区块（Bisheng）</a>Gravatar 在中国的完美替代方案，您可以在 Cravatar.com 上传头像，更多选项请安装 WPAavatar 插件。可自选<a href="https://wpcy.com/document/gravatar-alternatives" target="_blank">加速线路</a>。',
						'wp-china-yes' ),
				],
			],
		] );
    }


		if (in_array('deerlogin', $enabled_sections)) {
		WP_CHINA_YES::createSection( $this->prefix, [
			'title'  => '灯鹿用户',
			'icon'   => 'icon icon-user-tick',
			'fields' => [
				[
					'id'       => 'deerlogin',
					'type'     => 'radio',
					'title'    => __( '灯鹿用户', 'wp-china-yes' ),
					'inline'   => true,
					'options'  => [
						'cn'       => '默认线路',
						'global'   => '国际线路',
						'off'      => '不启用'
					],
					'default'  => 'cn',
					'subtitle' => '是否启用灯鹿用户',
					'desc'     => __( '<a href="https://deerlogin.com/" target="_blank">灯鹿用户（DeerLogin）</a>Gravatar 在中国的完美替代方案，您可以在 Cravatar.com 上传头像，更多选项请安装 WPAavatar 插件。可自选<a href="https://wpcy.com/document/gravatar-alternatives" target="_blank">加速线路</a>。',
						'wp-china-yes' ),
				],
			],
		] );
    }



		if (in_array('waimao', $enabled_sections)) {
		WP_CHINA_YES::createSection( $this->prefix, [
			'title'  => '跨飞外贸',
			'icon'   => 'icon icon-chart-success',
			'fields' => [
				[
					'id'       => 'waimao',
					'type'     => 'radio',
					'title'    => __( '灯鹿用户', 'wp-china-yes' ),
					'inline'   => true,
					'options'  => [
						'cn'       => '默认线路',
						'global'   => '国际线路',
						'off'      => '不启用'
					],
					'default'  => 'cn',
					'subtitle' => '是否启用灯鹿用户',
					'desc'     => __( '<a href="https://deerlogin.com/" target="_blank">灯鹿用户（DeerLogin）</a>Gravatar 在中国的完美替代方案，您可以在 Cravatar.com 上传头像，更多选项请安装 WPAavatar 插件。可自选<a href="https://wpcy.com/document/gravatar-alternatives" target="_blank">加速线路</a>。',
						'wp-china-yes' ),
				],
			],
		] );
    }



		if (in_array('woocn', $enabled_sections)) {
		WP_CHINA_YES::createSection( $this->prefix, [
			'title'  => 'Woo电商',
			'icon'   => 'icon icon-shopping-cart',
			'fields' => [
				[
					'id'       => 'woocn',
					'type'     => 'radio',
					'title'    => __( 'Woo电商', 'wp-china-yes' ),
					'inline'   => true,
					'options'  => [
						'cn'       => '默认线路',
						'global'   => '国际线路',
						'off'      => '不启用'
					],
					'default'  => 'cn',
					'subtitle' => '是否启用灯鹿用户',
					'desc'     => __( '<a href="https://deerlogin.com/" target="_blank">灯鹿用户（DeerLogin）</a>Gravatar 在中国的完美替代方案，您可以在 Cravatar.com 上传头像，更多选项请安装 WPAavatar 插件。可自选<a href="https://wpcy.com/document/gravatar-alternatives" target="_blank">加速线路</a>。',
						'wp-china-yes' ),
				],
			],
		] );
    }



		if (in_array('lelms', $enabled_sections)) {
		WP_CHINA_YES::createSection( $this->prefix, [
			'title'  => '乐尔达思',
			'icon'   => 'icon icon-teacher',
			'fields' => [
				[
					'id'       => 'lelms',
					'type'     => 'radio',
					'title'    => __( '乐尔达思', 'wp-china-yes' ),
					'inline'   => true,
					'options'  => [
						'cn'       => '默认线路',
						'global'   => '国际线路',
						'off'      => '不启用'
					],
					'default'  => 'cn',
					'subtitle' => '是否启用乐尔达思',
					'desc'     => __( '<a href="https://lelms.com/" target="_blank">乐尔达思（LeLMS）</a>Gravatar 在中国的完美替代方案，您可以在 Cravatar.com 上传头像，更多选项请安装 WPAavatar 插件。可自选<a href="https://wpcy.com/document/gravatar-alternatives" target="_blank">加速线路</a>。',
						'wp-china-yes' ),
				],
			],
		] );
    }



		if (in_array('wapuu', $enabled_sections)) {
		WP_CHINA_YES::createSection( $this->prefix, [
			'title'  => '瓦普文创',
			'icon'   => 'icon icon-ticket-discount',
			'fields' => [
				[
					'id'       => 'wapuu',
					'type'     => 'radio',
					'title'    => __( '瓦普文创', 'wp-china-yes' ),
					'inline'   => true,
					'options'  => [
						'cn'       => '默认线路',
						'global'   => '国际线路',
						'off'      => '不启用'
					],
					'default'  => 'cn',
					'subtitle' => '是否启用瓦普文创',
					'desc'     => __( '<a href="https://wapuu.com/" target="_blank">瓦普文创（Wapuu）</a>Gravatar 在中国的完美替代方案，您可以在 Cravatar.com 上传头像，更多选项请安装 WPAavatar 插件。可自选<a href="https://wpcy.com/document/gravatar-alternatives" target="_blank">加速线路</a>。',
						'wp-china-yes' ),
				],
			],
		] );
    }


if (in_array('adblock', $enabled_sections)) {
WP_CHINA_YES::createSection( $this->prefix, [
    'title'  => '广告拦截',
    'icon'   => 'icon icon-eye-slash',
    'fields' => [
        [
            'id'       => 'adblock',
            'type'     => 'radio',
            'title'    => __( '广告拦截', 'wp-china-yes' ),
            'inline'   => true,
            'options'  => [
                'on'  => '启用',
                'off' => '不启用',
            ],
            'default'  => 'off',
            'subtitle' => '是否启用后台广告拦截',
            'desc'     => __( '<a href="https://wpcy.com/adblocker" target="_blank">文派叶子🍃（WPCY.COM）</a>独家特色功能，让您拥有清爽整洁的 WordPress 后台，清除各类常用插件侵入式后台广告、通知及无用信息，拿回<a href="https://wpcy.com/document/ad-blocking-for-developers " target="_blank">后台控制权</a>。',
                'wp-china-yes' ),
        ],
        [
            'id'       => 'adblock_rule',
            'type'     => 'group',
            'title'    => '规则列表',
            'subtitle' => '使用的广告拦截规则列表',
            'desc'     => __( '默认规则跟随插件更新，插件更新后可删除规则重新添加以<a href="https://wpcy.com/adblocker" target="_blank">获取更多</a>最新拦截规则，出现异常，请尝试先停用规则<a href="https://wpcy.com/document/troubleshooting-ad-blocking" target="_blank">排查原因</a>。',
                'wp-china-yes' ),
            'button_title'           => '添加规则',
            'accordion_title_number' => true,
            'dependency'             => [
                'adblock',
                'any',
                'on',
            ],
            'fields'                 => [
                [
                    'id'       => 'name',
                    'type'     => 'text',
                    'title'    => __( '规则名称', 'wp-china-yes' ),
                    'subtitle' => '自定义规则名称',
                    'desc'     => __( '自定义规则名称，方便识别',
                        'wp-china-yes' ),
                    'default'  => '默认规则',
                ],
                [
                    'id'       => 'selector',
                    'type'     => 'textarea',
                    'title'    => __( '应用元素', 'wp-china-yes' ),
                    'subtitle' => '规则应用元素',
                    'desc'     => __( '设置规则应用的广告元素（CSS 选择器）',
                        'wp-china-yes' ),
                    'default'  => '.wpseo_content_wrapper',
                    'sanitize' => false,
                ],
                [
                    'id'       => 'enable',
                    'type'     => 'switcher',
                    'title'    => __( '启用规则', 'wp-china-yes' ),
                    'subtitle' => '是否启用该规则',
                    'default'  => true,
                ],
            ],
        ],
    ],
] );
    }
    
    
if (in_array('notice', $enabled_sections)) {
WP_CHINA_YES::createSection( $this->prefix, [
    'title'  => '通知管理',
    'icon'   => 'icon icon-notification-bing',
    'fields' => [
        [
            'id'       => 'notice_block',
            'type'     => 'radio',
            'title'    => __('通知管理', 'wp-china-yes'),
            'inline'   => true,
            'options'  => [
                'on'  => '启用',
                'off' => '不启用',
            ],
            'default'  => 'off',
            'subtitle' => '是否启用后台通知管理',
            'desc'     => __('管理和控制 WordPress 后台各类通知的显示。', 'wp-china-yes'),
        ],
        [
            'id'         => 'disable_all_notices',
            'type'       => 'switcher',
            'title'      => __('禁用所有通知', 'wp-china-yes'),
            'subtitle'   => '一键禁用所有后台通知',
            'default'    => false,
            'dependency' => ['notice_block', '==', 'on'],
        ],
        [
            'id'         => 'notice_control',
            'type'       => 'checkbox',
            'title'      => __('选择性禁用', 'wp-china-yes'),
            'inline'     => true,
            'subtitle'   => '选择需要禁用的通知类型',
            'desc'       => __('可以按住 Ctrl/Command 键进行多选', 'wp-china-yes'),
            'chosen'     => true,
            'multiple'   => true,
            'options'    => [
                'core'    => '核心更新通知',
                'error'   => '错误通知',
                'warning' => '警告通知',
                'info'    => '信息通知',
                'success' => '成功通知',
            ],
            'dependency' => ['notice_block|disable_all_notices', '==|==', 'on|false'],
            'default'    => [],
        ],
        [
            'id'         => 'notice_method',
            'type'       => 'radio',
            'title'      => __('禁用方式', 'wp-china-yes'),
            'inline'     => true,
            'options'    => [
                'hook'  => '移除钩子（推荐）',
                'css'   => 'CSS隐藏',
                'both'  => '双重保险',
            ],
            'default'    => 'hook',
            'dependency' => ['notice_block|disable_all_notices', '==|==', 'on|false'],
        ],
    ],
] );
    }



		if (in_array('plane', $enabled_sections)) {
		WP_CHINA_YES::createSection( $this->prefix, [
			'title'  => '飞行模式',
			'icon'   => 'icon icon-airplane',
			'fields' => [
				[
					'id'       => 'plane',
					'type'     => 'radio',
					'title'    => __( '飞行模式', 'wp-china-yes' ),
					'inline'   => true,
					'options'  => [
						'on'  => '启用',
						'off' => '不启用',
					],
					'default'  => 'off',
					'subtitle' => '是否启用飞行模式',
					'desc'     => __( '飞行模式可屏蔽 WordPress 插件主题在中国无法访问的 API 请求，加速网站前后台访问。注：部分外部请求为产品更新检测，若已屏蔽请定期检测。',
						'wp-china-yes' ),
				],
				[
					'id'       => 'plane_rule',
					'type'     => 'group',
					'title'    => '规则列表',
					'subtitle' => '飞行模式使用的 URL 屏蔽规则列表',
					'desc'     => __( '支持添加多条 <a href="https://wpcy.com/document/advertising-blocking-rules" target="_blank">URL 屏蔽规则</a>',
						'wp-china-yes' ),
					'button_title'           => '添加规则',
					'accordion_title_number' => true,
					'dependency'             => [
						'plane',
						'any',
						'on',
					],
					'fields'                 => [
						[
							'id'       => 'name',
							'type'     => 'text',
							'title'    => __( '规则名称', 'wp-china-yes' ),
							'subtitle' => '自定义规则名称',
							'desc'     => __( '自定义规则名称，方便识别',
								'wp-china-yes' ),
							'default'  => '未命名规则',
						],
						[
							'id'          => 'url',
							'type'        => 'textarea',
							'title'       => __( 'URL', 'wp-china-yes' ),
							'subtitle'    => 'URL',
							'desc'        => __( '填入需要屏蔽的 URL 链接，一行一条，注意不要串行',
								'wp-china-yes' ),
							'default'     => '',
							'placeholder' => 'example.com',
							'sanitize'    => false,
						],
						[
							'id'       => 'enable',
							'type'     => 'switcher',
							'title'    => __( '启用规则', 'wp-china-yes' ),
							'subtitle' => '是否启用该规则',
							'default'  => true,
						],
					],
				]
			],
		] );
    }

if (in_array('monitor', $enabled_sections)) {
WP_CHINA_YES::createSection( $this->prefix, [
    'title'  => '脉云维护',
    'icon'   => 'icon icon-story',
    'fields' => [
        [
            'id'       => 'monitor',
            'type'     => 'switcher',
            'default'  => true,
            'title'    => '节点监控',
            'subtitle' => '自动监控加速节点可用性',
            'desc'     => __( '<a href="https://maiyun.org" target="_blank">脉云维护（MainCloud）</a>支持自动监控各加速节点可用性，当节点不可用时自动切换至可用节点或关闭加速，以保证您的网站正常访问',
                'wp-china-yes' ),
        ],
        [
            'id'       => 'memory',
            'type'     => 'switcher',
            'default'  => true,
            'title'    => '系统监控',
            'subtitle' => '自动监控系统运行状态',
            'desc'     => __( '支持在管理后台页脚中显示系统运行状态，包括内存使用、CPU负载、MySQL版本、调试状态等信息',
                'wp-china-yes' ),
        ],
        [
            'id'         => 'memory_display',
            'type'       => 'checkbox',
            'title'      => __( '显示参数', 'wp-china-yes' ),
            'inline'     => true,
            'options'    => [
                'memory_usage'  => '内存使用量',
                'wp_limit'     => '内存限制',
                'server_ip'    => '服务器 IP',
                'hostname'     => '主机名称',
                'os_info'      => '操作系统',
                'mysql_version'=> 'MySQL版本',
                'cpu_usage'    => 'CPU使用率',
                'debug_status' => '调试状态',
                'php_info'     => 'PHP 版本'
            ],
            'default'    => [
                'memory_usage',
                'wp_limit',
                'server_ip',
                'php_info',
            ],
            'subtitle'   => '选择页脚要显示的信息',
            'desc'       => __( '为网站维护人员提供参考依据，无需登录服务器即可查看相关信息参数','wp-china-yes' ),
            'dependency' => ['memory', '==', 'true'],
        ],
        [
            'id'       => 'disk',
            'type'     => 'switcher',
            'default'  => true,
            'title'    => '站点监控',
            'subtitle' => '自动监控站点运行状态',
            'desc'     => __( '支持在管理后台页脚中显示系统运行状态，包括内存使用、CPU负载、MySQL版本、调试状态等信息',
                'wp-china-yes' ),
        ],
        [
            'id'         => 'disk_display',
            'type'       => 'checkbox',
            'title'      => __( '显示参数', 'wp-china-yes' ),
            'inline'     => true,
            'options'    => [
                'disk_usage'     => '磁盘用量',
                'disk_limit'     => '剩余空间',
                'media_num'      => '媒体数量',
                'admin_num'      => '管理数量',
                'user_num'       => '用户数量',
                'lastlogin'      => '上次登录',
            ],
            'default'    => [
                'disk_usage',
                'disk_limit',
                'media_num',
                'admin_num',
            ],
            'subtitle'   => '选择概览要显示的信息',
            'desc'       => __( '为网站管理人员提供参考依据，进入后台仪表盘即可查看相关信息参数','wp-china-yes' ),
            'dependency' => ['disk', '==', 'true'],
        ],
[
    'id'       => 'maintenance_mode',
    'type'     => 'switcher',
    'default'  => false,
    'title'    => '启用维护模式',
    'subtitle' => '启用或禁用网站维护模式',
    'desc'     => __( '启用后，网站将显示维护页面，只有管理员可以访问。', 'wp-china-yes' ),
],
[
    'id'         => 'maintenance_settings',
    'type'       => 'fieldset',
    'title'      => '维护模式设置',
    'fields'     => [
        [
            'id'      => 'maintenance_title',
            'type'    => 'text',
            'title'   => '页面标题',
            'default' => '网站维护中',
        ],
        [
            'id'      => 'maintenance_heading',
            'type'    => 'text',
            'title'   => '主标题',
            'default' => '网站维护中',
        ],
        [
            'id'      => 'maintenance_message',
            'type'    => 'textarea',
            'title'   => '维护说明',
            'default' => '网站正在进行例行维护，请稍后访问。感谢您的理解与支持！',
        ],
    ],
    'dependency' => ['maintenance_mode', '==', 'true'],
]

    ],
] );
    }

		if (in_array('security', $enabled_sections)) {
		WP_CHINA_YES::createSection( $this->prefix, [
    'title'  => '雨滴安全',
    'icon'   => 'icon icon-shield',
    'fields' => [
				[
					'id'       => 'yoodefender',
					'type'     => 'radio',
					'title'    => __( '雨滴安全', 'wp-china-yes' ),
					'inline'   => true,
					'options'  => [
						'cn'       => '默认线路',
						'global'   => '国际线路',
						'off'      => '不启用'
					],
					'default'  => 'cn',
					'subtitle' => '是否启用雨滴安全',
					'desc'     => __( '<a href="https://yoodefender.com/" target="_blank">雨滴安全（YooDefender）</a>安全设置可以帮助增强 WordPress 的安全性，请根据实际需求启用相关选项。更多选项请安装 WPBan 插件。可自选<a href="https://wpcy.com/document/gravatar-alternatives" target="_blank">加速线路</a>。',
						'wp-china-yes' ),
				],    
        [
            'id'       => 'disallow_file_edit',
            'type'     => 'switcher',
            'title'    => __( '禁用文件编辑', 'wp-china-yes' ),
            'subtitle' => '禁用 WordPress 后台的主题和插件编辑器',
            'default'  => true,
            'desc'     => __( '启用后，用户无法通过 WordPress 后台编辑主题和插件文件。', 'wp-china-yes' ),
        ],
        [
            'id'       => 'disallow_file_mods',
            'type'     => 'switcher',
            'title'    => __( '禁用文件修改', 'wp-china-yes' ),
            'subtitle' => '禁止用户安装、更新或删除主题和插件',
            'default'  => false,
            'desc'     => __( '启用后，用户无法通过 WordPress 后台安装、更新或删除主题和插件。', 'wp-china-yes' ),
        ],
    ],
] );
    }
		if (in_array('performance', $enabled_sections)) {
		WP_CHINA_YES::createSection( $this->prefix, [
    'title'  => '性能优化',
    'icon'   => 'icon icon-speedometer',
    'fields' => [
        [
            'id'       => 'performance',
            'type'     => 'switcher',
            'title'    => __( '性能优化', 'wp-china-yes' ),
            'subtitle' => '是否启用性能优化',
            'default'  => true,
            'desc'     => __( '性能优化设置可以帮助提升 WordPress 的运行效率，请根据服务器配置合理调整。', 'wp-china-yes' ),
        ],

        [
            'id'       => 'wp_memory_limit',
            'type'     => 'text',
            'title'    => __( '内存限制', 'wp-china-yes' ),
            'subtitle' => '设置 WordPress 内存限制',
            'default'  => '40M',
            'desc'     => __( '设置 WordPress 的内存限制，例如 64M、128M、256M 等。', 'wp-china-yes' ),
    'dependency' => ['performance', '==', 'true'],

        ],
        [
            'id'       => 'wp_max_memory_limit',
            'type'     => 'text',
            'title'    => __( '后台内存限制', 'wp-china-yes' ),
            'subtitle' => '设置 WordPress 后台内存限制',
            'default'  => '256M',
            'desc'     => __( '设置 WordPress 后台的内存限制，例如 128M、256M、512M 等。', 'wp-china-yes' ),
    'dependency' => ['performance', '==', 'true'],

        ],
        [
            'id'       => 'wp_post_revisions',
            'type'     => 'number',
            'title'    => __( '文章修订版本', 'wp-china-yes' ),
            'subtitle' => '控制文章修订版本的数量',
            'default'  => -1, // -1 表示启用所有修订版本
            'desc'     => __( '设置为 0 禁用修订版本，或设置为一个固定值（如 5）限制修订版本数量。', 'wp-china-yes' ),
    'dependency' => ['performance', '==', 'true'],

        ],
        [
            'id'       => 'autosave_interval',
            'type'     => 'number',
            'title'    => __( '自动保存间隔', 'wp-china-yes' ),
            'subtitle' => '设置文章自动保存的时间间隔（秒）',
            'default'  => 60,
            'desc'     => __( '设置文章自动保存的时间间隔，默认是 60 秒。', 'wp-china-yes' ),
    'dependency' => ['performance', '==', 'true'],

        ],
    ],
    
] );
    }
		
		if (in_array('brand', $enabled_sections)) {
		WP_CHINA_YES::createSection( $this->prefix, [
    'title'  => '品牌白标',
    'icon'   => 'icon icon-password-check',
    'fields' => [
        [
            'id'       => 'custom_name',
            'type'     => 'text',
            'title'    => '品牌白标',
            'subtitle' => '自定义插件显示品牌名',
            'desc'     => __( '专为 WordPress 建站服务商和代理机构提供的<a href="https://wpcy.com/white-label" target="_blank">自定义品牌 OEM </a>功能，输入您的品牌词启用后生效', 'wp-china-yes' ),
            'default'  => "文派叶子",
        ],
        [
            'id'       => 'header_logo',
            'type'     => 'media',
            'title'    => '品牌 Logo',
            'subtitle' => '自定义插件显示品牌 Logo',
            'library'  => 'image',
            'desc'     => '上传或选择媒体库的图片作为品牌 Logo',
            'default'  => ['url' => plugins_url('wp-china-yes/assets/images/wpcy-logo.png')], // 设置默认 Logo
        ],
				[
					'id'       => 'hide_option',
					'type'     => 'switcher',
					'default'  => false,
					'title'    => '隐藏设置',
					'subtitle' => '隐藏插件设置信息',
					'desc'     => __( '如果您不希望让客户知道本站启用了<a href="https://wpcy.com/" target="_blank">文派叶子🍃（WPCY.COM）</a>插件及服务，可开启此选项。',
						'wp-china-yes' ),
				],

            [
                'id'         => 'hide_elements',
                'type'       => 'checkbox',
                'title'      => '隐藏元素',
                'subtitle'   => '选择需要隐藏的元素',
                'desc'     => __( '注意：启用[隐藏菜单]前请务必<a href="https://wpcy.com/document/hide-settings-page" target="_blank">保存或收藏</a>当前设置页面 URL，否则将无法再次进入插件页面', 'wp-china-yes' ),
                'inline'     => true, 
                'options'    => [
                    'hide_logo'    => '隐藏 Logo',
                    'hide_title'   => '隐藏插件名',
                    'hide_version' => '隐藏版本号',
                    'hide_copyright'    => '隐藏版权',
                    'hide_menu'    => '隐藏菜单',
                ],
                'default'    => [], // 默认不隐藏任何元素
                'dependency' => ['hide_option', '==', 'true'], // 只有在 hide 为 true 时显示
            ],
                 [
            'id'       => 'enable_custom_rss',
            'type'     => 'switcher',
            'title'    => '品牌新闻',
            'subtitle' => '是否启用定制新闻源',
            'desc'     => '启用后，您可以自定义[文派茶馆]新闻源，输入自己的 RSS 源之后即可显示信息流。',
            'default'  => false
        ],
        [
            'id'       => 'custom_rss_url',
            'type'     => 'text',
            'title'    => '自定义 RSS 源',
            'subtitle' => '添加自定义 RSS 新闻源',
            'desc'     => '请输入有效的 RSS Feed URL，长期无更新时会恢复显示默认新闻源。',
            'dependency' => ['enable_custom_rss', '==', true]
        ],
        [
            'id'       => 'custom_rss_refresh',
            'type'     => 'select',
            'title'    => 'RSS 刷新频率',
            'options'  => [
                '1800'  => '30分钟',
                '3600'  => '1小时',
                '7200'  => '2小时',
                '14400' => '4小时',
                '28800' => '8小时'
            ],
            'default'  => '3600',
            'dependency' => ['enable_custom_rss', '==', true]
        ],
        [
            'id'       => 'rss_display_options',
            'type'     => 'checkbox',
            'inline'     => true,
            'title'    => 'RSS 显示选项',
            'subtitle' => '选择需要显示的内容',
            'options'  => [
                'show_date'    => '显示日期',
                'show_summary' => '显示摘要',
                'show_footer'  => '显示页脚',
            ],
            'default'  => ['show_date', 'show_summary', 'show_footer'], // 默认全部勾选
            'dependency' => ['enable_custom_rss', '==', true]
        ],
   
    ],
]);
    }
    
		WP_CHINA_YES::createSection( $this->prefix, [
			'title'  => '其他设置',
			'icon'   => 'icon icon-setting',
			'fields' => [
        [
            'id'       => 'enable_debug',
            'type'     => 'switcher',
            'title'    => __( '调试模式', 'wp-china-yes' ),
            'subtitle' => '启用或禁用调试模式',
            'default'  => false,
            'desc'     => __( '启用后，WordPress 将显示 PHP 错误、警告和通知。临时使用完毕后，请保持禁用此选项。', 'wp-china-yes' ),
        ],
        [
            'id'         => 'debug_options',
            'type'       => 'checkbox',
            'title'      => __( '调试选项', 'wp-china-yes' ),
            'subtitle'   => '选择要启用的调试功能',
            'dependency' => [ 'enable_debug', '==', 'true' ],
            'options'    => [
                'wp_debug_log'      => 'WP_DEBUG_LOG 记录日志',
                'wp_debug_display'  => 'WP_DEBUG_DISPLAY 页面显示调试信息',
                'script_debug'      => 'SCRIPT_DEBUG 加载未压缩的前端资源',
                'save_queries'      => 'SAVEQUERIES 记录数据库查询 ',
            ],
            'default'    => [
                'wp_debug_log' => true,
            ],
            'desc'       => __( '注意：调试模式仅适用于开发和测试环境，不建议在生产环境中长时间启用。选择要启用的调试功能，适用于开发和测试环境。', 'wp-china-yes' ),
        ],
        [
            'id'       => 'enable_db_tools',
            'type'     => 'switcher',
            'title'    => __( '数据库工具', 'wp-china-yes' ),
            'subtitle' => '启用或禁用数据库工具',
            'default'  => false,
            'desc'     => __( '启用后，您可以在下方访问数据库修复工具。定期使用完毕后，请保持禁用此选项。', 'wp-china-yes' ),
        ],
        [
            'id'         => 'db_tools_link',
            'type'       => 'content',
            'title'      => __( '数据库修复工具', 'wp-china-yes' ),
            'subtitle'   => '打开数据库修复工具',
            'dependency' => [ 'enable_db_tools', '==', 'true' ],
            'content'    => '<a class="button button-primary" href="' . esc_url( admin_url( 'maint/repair.php' ) ) . '" target="_blank">' . esc_html__( '打开数据库修复工具', 'wp-china-yes' ) . '</a>',
        ],
[
    'id'       => 'enable_sections',
    'type'     => 'switcher',
    'title'    => '高级定制',
    'subtitle' => '启用或禁用功能选项卡',
    'default'  => true,
    'desc'     => __( '启用后，您可以在下方选用文派叶子功能，特别提醒：禁用对应功能后再次启用需重新设置。', 'wp-china-yes' ),
],
[
    'id'       => 'enabled_sections',
    'type'     => 'checkbox',
    'title'    => '功能选项卡',
    'subtitle' => '选择要显示的功能选项卡',
    'inline'   => true,
    'options'  => [
        'store'     => '应用市场',
        'admincdn'  => '萌芽加速',
        'cravatar'  => '初认头像',
        'windfonts' => '文风字体',
        'motucloud' => '墨图云集',
        'fewmail'   => '飞秒邮箱',
        'wordyeah'  => '无言会语',
        'blocks'    => '笔笙区块',
        'deerlogin' => '灯鹿用户',
        'waimao'    => '跨飞外贸',
        'woocn'     => 'Woo电商',
        'lelms'     => '乐尔达思',
        'wapuu'     => '瓦普文创',
        'adblock'   => '广告拦截',
        'notice'    => '通知管理',
        'plane'     => '飞行模式',
        'monitor'   => '脉云维护',
        'forums'    => '赛博论坛',
        'monitor'   => '脉云维护',
        'forms'     => '重力表单',
        'panel'     => '天控面板',
        'security'  => '雨滴安全',
        'domain'    => '蛋叮域名',
        'performance' => '性能优化',
        'brand'     => '品牌白标',
        'sms'       => '竹莺短信',
        'chat'      => '点洽客服',
        'translate' => '文脉翻译',
        'ecosystem' => '生态系统',
        'deer'      => '建站套件',
        'docs'      => '帮助文档',
        'about'     => '关于插件',
        'welcome'   => '欢迎使用'
    ],
    'default'  => ['welcome', 'store', 'admincdn', 'cravatar', 'other', 'about'],
    'desc'     => '选择要在设置页面显示的功能选项卡，未选择的选项卡将被隐藏',
    'dependency' => ['enable_sections', '==', 'true'], 
]

		],
		] );

		if (in_array('deer', $enabled_sections)) {
        $settings_page_url = $this->get_settings_page_url();
            ob_start();
            include CHINA_YES_PLUGIN_PATH . 'templates/website-section.php';
            $website_content = ob_get_clean();

		WP_CHINA_YES::createSection( $this->prefix, [
			'title'  => '建站套件',
			'icon'   => 'icon icon-mouse',
			'fields' => [
				[
					'type'    => 'content',
					'content' =>$website_content,
				]
			],
		] );
		}		
		


		if (in_array('docs', $enabled_sections)) {
		WP_CHINA_YES::createSection( $this->prefix, [
			'title'  => '帮助文档',
			'icon'   => 'icon icon-lifebuoy',
			'fields' => [
				[
					'id'       => 'docs',
					'type'     => 'radio',
					'title'    => __( '帮助文档', 'wp-china-yes' ),
					'inline'   => true,
					'options'  => [
						'cn'       => '默认线路',
						'global'   => '国际线路',
						'off'      => '不启用'
					],
					'default'  => 'cn',
					'subtitle' => '是否启用灯鹿用户',
					'desc'     => __( '<a href="https://deerlogin.com/" target="_blank">灯鹿用户（DeerLogin）</a>Gravatar 在中国的完美替代方案，您可以在 Cravatar.com 上传头像，更多选项请安装 WPAavatar 插件。可自选<a href="https://wpcy.com/document/gravatar-alternatives" target="_blank">加速线路</a>。',
						'wp-china-yes' ),
				],
			],
		] );
    }
		
		if (in_array('about', $enabled_sections)) {
        $settings_page_url = $this->get_settings_page_url();
            ob_start();
            include CHINA_YES_PLUGIN_PATH . 'templates/about-section.php';
            $about_content = ob_get_clean();

		WP_CHINA_YES::createSection( $this->prefix, [
			'title'  => '关于插件',
			'icon'   => 'icon icon-info-circle',
			'fields' => [
				[
					'type'    => 'content',
					'content' =>$about_content,
				]
			],
		] );

	}
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
		// 自定义名称
		add_filter( 'all_plugins', function ( $plugins ) {
			if ( isset( $plugins['wp-china-yes/wp-china-yes.php'] ) ) {
				$plugins['wp-china-yes/wp-china-yes.php']['Name'] = $this->settings['custom_name'];
			}

			return $plugins;
		} );



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
}