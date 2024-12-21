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
	 * 挂载设置项
	 */
	public function admin_init() {
		WP_CHINA_YES::createOptions( $this->prefix, [
			'framework_title'    => sprintf( '%s <small>v%s</small>', $this->settings['custom_name'], CHINA_YES_VERSION ),
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

		WP_CHINA_YES::createSection( $this->prefix, [
			'title'  => '欢迎使用',
			'icon'   => 'icon icon-home-1',
			'fields' => [
				[
					'type'    => 'content',
					'content' =>
						<<<HTML
<div class="wp_china_yes-field wp_china_yes-field-content"><div class="wpcy-about__section"><div class="wpcy-about__grid"><div class="column wpcy-banner"><span class="wpcy-icon-inner"> <i class="icon icon-mirroring-screen"></i></span><h2>原生体验</h2><p>文派叶子🍃（WP-China-Yes）是一款不可多得的 WordPress 系统底层优化和生态基础设施软件。</p><div class="wpcy-buttons"><a href="options-general.php?page=wp-china-yes#tab=%e5%bb%ba%e7%ab%99%e5%a5%97%e4%bb%b6" class="components-button button-primary">获取 WP Deer 建站套件</a><a href="https://wenpai.org/" target="_blank" rel="noopener" class="components-button button-secondary">文派开源（WenPai.org）↗</a></div><img src="/wp-content/plugins/wp-china-yes/assets/images/website-banner.jpg" width="358" height="140" alt=""></div><div class="column"><span class="wpcy-icon-inner"> <i class="icon icon-link-21"></i> </span><h2>特色功能</h2><ul class="wpcy-about__list"><li><a href="options-general.php?page=wp-china-yes#tab=%e5%8a%a0%e9%80%9f%e8%ae%be%e7%bd%ae" class="components-button button-link has-text has-icon"><span class="wpcy-icon-inner-list"> <i class="icon icon-folder-open"></i> </span>文派（WordPress）中国更新源</a></li><li><a href="options-general.php?page=wp-china-yes#tab=%e5%8a%a0%e9%80%9f%e8%ae%be%e7%bd%ae" class="components-button button-link has-text has-icon"><span class="wpcy-icon-inner-list"> <i class="icon icon-colorfilter"></i> </span>前端公共资源库 CDN 加速</a></li><li><a href="options-general.php?page=wp-china-yes#tab=%e6%96%87%e9%a3%8e%e5%ad%97%e4%bd%93" class="components-button button-link has-text has-icon"><span class="wpcy-icon-inner-list"> <i class="icon icon-language-square"></i> </span>中文网页字体</a></li><li><a href="options-general.php?page=wp-china-yes#tab=%e5%b9%bf%e5%91%8a%e5%b1%8f%e8%94%bd" class="components-button button-link has-text has-icon"><span class="wpcy-icon-inner-list"> <i class="icon icon-eye-slash"></i> </span>后台广告拦截</a></li><li><a href="options-general.php?page=wp-china-yes#tab=%e9%a3%9e%e8%a1%8c%e6%a8%a1%e5%bc%8f" class="components-button button-link has-text has-icon"><span class="wpcy-icon-inner-list"> <i class="icon icon-filter"></i> </span>外部 API 请求屏蔽</a></li><li><a href="options-general.php?page=wp-china-yes#tab=%e5%93%81%e7%89%8c%e7%99%bd%e6%a0%87" class="components-button button-link has-text has-icon"><span class="wpcy-icon-inner-list"> <i class="icon icon-password-check"></i> </span>品牌 OEM 定制</a></li></ul><p>* 100% 兼容 WP 程序及发行分支版本，更多优秀插件待您体验。</p></div></div><div class="wpcy-about__grid columns-3"><div class="column"><span class="wpcy-icon-inner"> <i class="icon icon-flash"></i></span><h2>网站加速</h2><p>优化加速插件多如牛毛，为何文派叶子如此与众不同？</p><a href="https://wpcy.com/acceleration" class="components-button button-link">进一步了解 ↗</a></div><div class="column"><span class="wpcy-icon-inner"> <i class="icon icon-box-tick"></i></span><h2>翻译推送</h2><p>高质量翻译中文本地化翻译由文派开源官方提供，欢迎参与改进。</p><a href="https://wpcy.com/translate" class="components-button button-link">本地化改进 ↗</a></div><div class="column"><span class="wpcy-icon-inner"> <i class="icon icon-broom"></i></span><h2>广告屏蔽</h2><p>呈现清爽整洁的网站后台，清除侵入式后台广告、无用信息。</p><a href="https://wpcy.com/ads" class="components-button button-link">获取广告规则 ↗</a></div></div><div class="wpcy-about__grid"><div class="column"><span class="wpcy-icon-inner"> <i class="icon icon-sms-notification"></i></span><h2>加入我们</h2><p>关注文派茶馆 WPTEA.com 公众号以及订阅我们的时事通讯即可接收独家内容、提示和更新。</p><div class="wpcy-buttons"><a href="https://wptea.com/newsletter" target="_blank" rel="noopener" class="components-button button-secondary">订阅新闻 ↗</a></div></div><div class="column"><span class="wpcy-icon-inner"> <i class="icon icon-more-square"></i></span><h2>浏览更多</h2><ul class="wpcy-about__list"><li><a href="https://wpcy.com" target="_blank" rel="noopener" class="components-button button-link has-text has-icon"><span class="wpcy-icon-inner-list"> <i class="icon icon-global"></i> </span>文派叶子 🍃 （WPCY.com）</a></li><li><a href="https://wpcy.com/document" target="_blank" rel="noopener" class="components-button button-link has-text has-icon"><span class="wpcy-icon-inner-list"> <i class="icon icon-archive-book"></i> </span>快速入门指南</a></li><li><a href="https://wpcy.com/support/" target="_blank" rel="noopener noreferrer" class="components-button button-link has-text has-icon"><span class="wpcy-icon-inner-list"> <i class="icon icon-message-notif"></i> </span>支持论坛</a></li><li><a href="https://space.bilibili.com/3546657484442062" target="_blank" rel="noopener noreferrer" class="components-button button-link has-text has-icon"><span class="wpcy-icon-inner-list"> <i class="icon icon-video-square"></i> </span>Bilibili 官方频道</a></li><li><a href="https://wpcy.com/faqs" target="_blank" rel="noopener noreferrer" class="components-button button-link has-text has-icon"><span class="wpcy-icon-inner-list"> <i class="icon icon-lifebuoy"></i> </span>常见问题 &amp; 故障排查…</a></li></ul></div></div></div><div class="clear"></div></div>
HTML,
				]
			],
		] );
		
		WP_CHINA_YES::createSection( $this->prefix, [
			'title'  => '加速设置',
			'icon'   => 'icon icon-flash-1',
			'fields' => [
				[
					'id'       => 'store',
					'type'     => 'radio',
					'title'    => __( '应用市场', 'wp-china-yes' ),
					'inline'   => true,
					'options'  => [
						'proxy'  => '官方镜像',
						'wenpai' => '文派开源',
						'off'    => '不启用'
					],
					'default'  => 'wenpai',
					'subtitle' => '是否启用市场加速',
					'desc'     => __( '<a href="https://wpmirror.com/" target="_blank">官方加速源（WPMirror）</a>直接从 .org 反代至大陆分发；<a href="https://wenpai.org/" target="_blank">文派开源（WenPai.org）</a>中国境内自建托管仓库，同时集成文派翻译平台。可参考<a href="https://wpcy.com/document/wordpress-marketplace-acceleration" target="_blank">源站说明</a>。',
						'wp-china-yes' ),
				],
				[
					'id'       => 'admincdn',
					'type'     => 'checkbox',
					'title'    => __( '萌芽加速', 'wp-china-yes' ),
					'inline'   => true,
					'options'  => [
						'admin'       => '后台加速',
						'frontend'    => '前台加速',
						'googlefonts' => 'Google 字体',
						'googleajax'  => 'Google 前端库',
						'cdnjs'       => 'CDNJS 前端库',
						'jsdelivr'    => 'jsDelivr 前端库'
					],
					'default'  => [
						'admin' => 'admin',
					],
					'subtitle' => '是否启用萌芽加速',
					'desc'     => __( '<a href="https://admincdn.com/" target="_blank">萌芽加速（adminCDN）</a>将 WordPress 依赖的静态文件切换为公共资源，加快网站访问速度。您可按需启用需要加速的项目，更多细节控制和功能，请查看<a href="https://wpcy.com/document/admincdn" target="_blank">推荐设置</a>。',
						'wp-china-yes' ),
				],
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
					'id'         => 'windfonts_typography',
					'type'       => 'checkbox',
					'title'      => __( '排印优化', 'wp-china-yes' ),
					'inline'     => true,
					'options'    => [
						'corner'      => '直角括号',
						'space'       => '文本空格',
						'punctuation' => '标点显示',
					],
					'default'    => '',
					'subtitle'   => '是否启用排印优化',
					'desc'       => __( '排印优化可提升中文网页的视觉美感，适用于中文字体的网站。',
						'wp-china-yes' ),
					'dependency' => [
						'windfonts',
						'any',
						'on,frontend,optimize',
					],
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
					'type'    => 'content',
					'content' => '默认<a href="https://wpcy.com/document/add-html-tag" target="_blank">字体适配规则</a>跟随插件更新，插件更新后可删除字体重新添加以获取最新适配规则',
				],
			],
		] );

		WP_CHINA_YES::createSection( $this->prefix, [
			'title'  => '广告屏蔽',
			'icon'   => 'icon icon-eye-slash',
			'fields' => [
				[
					'id'       => 'adblock',
					'type'     => 'radio',
					'title'    => __( '广告屏蔽', 'wp-china-yes' ),
					'inline'   => true,
					'options'  => [
						'on'  => '启用',
						'off' => '不启用',
					],
					'default'  => 'off',
					'subtitle' => '是否启用后台广告屏蔽',
					'desc'     => __( '<a href="https://wpcy.com/ads" target="_blank">文派叶子🍃（WP-China-Yes）</a>独家特色功能，让您拥有清爽整洁的 WordPress 后台，清除各类常用插件侵入式后台广告、通知及无用信息，拿回您的<a href="https://wpcy.com/document/ad-blocking-for-developers " target="_blank">后台控制权</a>。',
						'wp-china-yes' ),
				],
				[
					'id'       => 'adblock_rule',
					'type'     => 'group',
					'title'    => '规则列表',
					'subtitle' => '使用的广告屏蔽规则列表',
					'desc'     => __( '支持添加多条<a href="https://wpcy.com/document/advertising-blocking-rules" target="_blank">广告屏蔽规则</a>',
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
							'default'  => '.wpseo_content_wrapper #sidebar-container, .yoast_premium_upsell, #wpseo-local-seo-upsell, .yoast-settings-section-upsell, #rank_math_review_plugin_notice, #bwp-get-social, .bwp-button-paypal, #bwp-sidebar-right, .tjcc-custom-css #postbox-container-1, .settings_page_wpcustomtaxfilterinadmin #postbox-container-1, #duplicate-post-notice #newsletter-subscribe-form, div[id^="dnh-wrm"], .notice-info.dst-notice, #googleanalytics_terms_notice, .fw-brz-dismiss, div.elementor-message[data-notice_id="elementor_dev_promote"], .notice-success.wpcf7r-notice, .dc-text__block.disable__comment__alert, #ws_sidebar_pro_ad, .pa-new-feature-notice, #redux-connect-message, .frash-notice-email, .frash-notice-rate, #smush-box-pro-features, #wp-smush-bulk-smush-upsell-row, #easy-updates-manager-dashnotice, #metaslider-optin-notice, #extendifysdk_announcement, .ml-discount-ad, .mo-admin-notice, .post-smtp-donation, div[data-dismissible="notice-owa-sale-forever"], .neve-notice-upsell, #pagelayer_promo, #simple-custom-post-order-epsilon-review-notice, .sfsi_new_prmium_follw, div.fs-slug-the-events-calendar[data-id="connect_account"], .tribe-notice-event-tickets-install, div.notice[data-notice="webp-converter-for-media"], .webpLoader__popup.webpPopup, .put-dismiss-notice, .wp-mail-smtp-review-notice, #wp-mail-smtp-pro-banner, body div.promotion.fs-notice, .analytify-review-thumbnail, .analytify-review-notice, .jitm-banner.is-upgrade-premium, div[data-name*="wbcr_factory_notice_adverts"], .sui-subscription-notice, #sui-cross-sell-footer, .sui-cross-sell-modules, .forminator-rating-notice, .sui-dashboard-upsell-upsell, .anwp-post-grid__rate, .cff-settings-cta, .cff-header-upgrade-notice, .cff_notice.cff_review_notice_step_1, .cff_get_pro_highlight, .aal-install-elementor, #ws_sidebar_pro_ad, .bold-timeline-lite-feedback-notice-wrapper, #elementskit-lite-go-pro-noti2ce, #elementskit-lite-_plugin_rating_msg_used_in_day, .yarpp-review-notice, #prli_review_notice, #webdados_invoicexpress_nag, #vc_license-activation-notice, .villatheme-dashboard.updated, #njt-FileBird-review, .notice[data-dismissible="pro_release_notice"], #thwvsf_review_request_notice, .wpdeveloper-review-notice, div[data-notice_type="tinvwl-user-review"], div[data-notice_type="tinvwl-user-premium"], #sg-backup-review-wrapper, .notice-wpmet-jhanda-getgenie-cross-promo, .notice-getgenie-go-pro-noti2ce, .notice-wpmet-jhanda-Summer2023, .thwcfd-review-wrapper, .woo-permalink-manager-banner, div.notice.bundle-notice, div.notice[data-dismissible="notice-owa-upgrade-forever"], .wpsm-acc-r-review-notice, .wpsm_ac_h_i, .edac-review-notice, .notice-iworks-rate, #monterinsights-admin-menu-tooltip, .monsterinsights-floating-bar, #monterinsights-admin-menu-tooltip, .exactmetrics-floating-bar, #metform-unsupported-metform-pro-version, .lwptocRate, .wpsm-tabs-b-review-notice, .quadlayers_woocommerce-direct-checkout_notice_delay, .iworks-rate-notice, #metform-_plugin_rating_msg_used_in_day, [id^="wpmet-jhanda-"], #wpmet-stories, #ti-optml-notice-helper, .menu-icon-dashboard-notice, .catch-bells-admin-notice, .wpdt-bundles-notice, .td-admin-web-services, .cf-plugin-popup, .wpzinc-review-media-library-organizer, .oxi-image-notice',
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
				[
					'type'    => 'content',
					'content' => '默认规则跟随插件更新，插件更新后可删除规则重新添加以<a href="https://wpcy.com/adblocker" target="_blank">获取更多</a>最新屏蔽规则，出现异常，请尝试先停用规则<a href="https://wpcy.com/document/troubleshooting-ad-blocking" target="_blank">排查原因</a>。',
				],
			],
		] );

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
							'type'        => 'text',
							'title'       => __( 'URL', 'wp-china-yes' ),
							'subtitle'    => 'URL',
							'desc'        => __( '设置需要屏蔽的 URL 关键词',
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

		
		WP_CHINA_YES::createSection( $this->prefix, [
			'title'  => '节点监控',
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
					'type'    => 'content',
					'content' => '启用隐藏设置前请务必的<a href="https://wpcy.com/document/hide-settings-page" target="_blank">保存或收藏</a>当前设置页面 URL，否则您将无法再次进入插件设置页面',
				],
			],
		] );

		WP_CHINA_YES::createSection( $this->prefix, [
			'title'  => '品牌白标',
			'icon'   => 'icon icon-password-check',
			'fields' => [
				[
					'id'       => 'custom_name',
					'type'     => 'text',
					'title'    => '品牌白标',
					'subtitle' => '自定义插件显示品牌名',
					'desc'     => __( '专为 WordPress 建站服务商和代理机构提供的<a href="https://wpcy.com/white-label" target="_blank">自定义品牌 OEM </a>功能，输入您的品牌词启用后生效',
						'wp-china-yes' ),
					'default'  => "WP-China-Yes",
				],
				[
					'type'    => 'content',
					'content' => '启用隐藏设置前请务必的<a href="https://wpcy.com/document/hide-settings-page" target="_blank">保存或收藏</a>当前设置页面 URL，否则您将无法再次进入插件设置页面',
				],
			],
		] );


		WP_CHINA_YES::createSection( $this->prefix, [
			'title'  => '其他设置',
			'icon'   => 'icon icon-setting',
			'fields' => [
				[
					'id'       => 'hide',
					'type'     => 'switcher',
					'default'  => false,
					'title'    => '隐藏设置',
					'subtitle' => '隐藏插件设置入口',
					'desc'     => __( '如果您不希望让客户知道本站启用了<a href="https://wpcy.com/" target="_blank">文派叶子🍃（WP-China-Yes）</a>插件及服务，可开启此选项',
						'wp-china-yes' ),
				],
			],
		] );


		WP_CHINA_YES::createSection( $this->prefix, [
			'title'  => '建站套件',
			'icon'   => 'icon icon-mouse',
			'fields' => [
				[
					'type'    => 'content',
					'content' =>
						<<<HTML
<div class="wp_china_yes-field wp_china_yes-field-content"><div class="wpcy-about__section"><div class="wpcy-about__grid columns-1"><div class="column wpcy-kit-banner"><span class="wpcy-icon-inner"> <i class="icon icon-magic-star"></i></span><h2>开源建站</h2><p>文派寻鹿🦌（WP Deer）建站套件是由文派科技官方提供的企业建站产品集合，代码均为 100% GPL 开源，无任何加密隐藏。</p><div class="wpcy-buttons"><a href="https://wpcy.com/deer" target="_blank" rel="noopener" class="components-button button-primary">阅读《软件授权协议》 ↗</a></div></div></div><div class="wpcy-about__grid columns-3"><div class="column"><span class="wpcy-icon-inner"> <i class="icon icon-flash"></i></span><h2>SEO 优化技巧</h2><a href="https://wpxyz.com/" class="components-button button-link">WPXYZ.com ↗</a></div><div class="column"><span class="wpcy-icon-inner"> <i class="icon icon-box-tick"></i></span><h2>网站政策合规</h2><a href="https://wpicp.com" class="components-button button-link">WPICP.com ↗</a></div><div class="column"><span class="wpcy-icon-inner"> <i class="icon icon-broom"></i></span><h2>软件开发工具</h2><a href="https://wpsdk.com/" class="components-button button-link">WPSDK.com ↗</a></div></div></div><div class="clear"></div></div>
HTML,
				]
			],
		] );
		
		
		
		WP_CHINA_YES::createSection( $this->prefix, [
			'title'  => '关于插件',
			'icon'   => 'icon icon-info-circle',
			'fields' => [
				[
					'type'    => 'content',
					'content' =>
						<<<HTML
<div class="wp_china_yes-field wp_china_yes-field-content"><div class="wpcy-about__section"><div class="wpcy-about__grid columns-1">
<div class="column wpcy-kit-banner"><span class="wpcy-icon-inner"> <i class="icon icon-archive-1"></i></span><h2>项目简介</h2><p>文派（WordPress）中国本土化项目始于 2019 年，由 文派叶子🍃（WPCY） 插件开启，其前身为 WP-China-Yes。

</p><p>2023 年 5 月，文派科技完成对该项目的收购，并对其进行了全面的品牌重塑。</p><p></p>
<div class="wpcy-buttons"><a href="https://wpcy.com/news" target="_blank" rel="noopener" class="components-button button-secondary">进一步了解 ↗</a><a href="https://wpcy.com/about/investor" target="_blank" rel="noopener" class="components-button button-secondary">

文派开源（WenPai.org）↗</a></div></div><div class="column wpcy-kit-banner"><span class="wpcy-icon-inner"> <i class="icon icon-lovely"></i></span><h2>赞助支持</h2><p>特别感谢以下企业品牌对文派项目提供的资金资源支持。早期伙伴未来有机会共享文派生态资源，期待社会各界参与。</p><div class="card-body sponsor-logos">
	<img src="https://cravatar.cn/wp-content/uploads/2024/09/feibisi-logo.png">
	<img src="https://cravatar.cn/wp-content/uploads/2024/09/shujue-logo.png">
	<img src="https://cravatar.cn/wp-content/uploads/2024/09/upyun-logo.png">
	<img src="https://cravatar.cn/wp-content/uploads/2024/09/haozi-logo.png">
	<img src="https://cravatar.cn/wp-content/uploads/2024/09/wpsaas-logo.png">
	<img src="https://cravatar.cn/wp-content/uploads/2024/09/lingding-logo.png">
	<img src="https://cravatar.cn/wp-content/uploads/2024/09/weixiaoduo-logo-2020.png">
	<img src="https://cravatar.cn/wp-content/uploads/2024/09/modiqi-logo.png">
	<img src="https://cravatar.cn/wp-content/uploads/2024/09/kekechong-logo.png">
	<img src="https://cravatar.cn/wp-content/uploads/2024/09/wenpai-logo@2X.png">
</div><div class="wpcy-buttons"><a href="https://wpcy.com/ecosystem" target="_blank" rel="noopener" class="components-button button-secondary">生态共建 ↗</a><a href="https://wpcy.com/about/investor" target="_blank" rel="noopener" class="components-button button-secondary">项目投资 ↗</a></div></div></div><div class="wpcy-about__grid columns-1"><div class="column wpcy-kit-banner"><span class="wpcy-icon-inner"> <i class="icon icon-user-octagon"></i></span><h2>开发 &amp; 贡献者</h2><p>100% 开源代码，诚邀您一起参与文派 （WordPress） 软件国产化进程，打造属于自己的开源自助建站程序。</p><div class="card-body contributors-name">
<a href="https://github.com/sunxiyuan" target="_blank">孙锡源</a><a href="https://github.com/devhaozi/" target="_blank">耗子</a><a href="https://github.com/Yulinn233/" target="_blank">Yulinn</a><a href="https://github.com/zhaofeng-shu33/" target="_blank">赵丰</a><a href="https://github.com/djl0415/" target="_blank">jialong Dong</a><a href="https://github.com/k99k5/" target="_blank">TigerKK</a><a href="https://github.com/xianyu125/" target="_blank">xianyu125</a><a href="https://github.com/ElliotHughes/" target="_blank">ElliotHughes</a><a href="https://bbs.weixiaoduo.com/users/feibisi/" target="_blank">诗语</a><a href="https://www.modiqi.com/" target="_blank">莫蒂奇</a><a href="https://www.weixiaoduo.com/" target="_blank">薇晓朵</a><p></p>
</div><div class="wpcy-buttons"><a href="https://wpcy.com/about/promoter" target="_blank" rel="noopener" class="components-button button-secondary">协助推广 ↗</a><a href="https://wpcy.com/about/investor" target="_blank" rel="noopener" class="components-button button-secondary">参与贡献 ↗</a></div></div></div></div><div class="clear"></div></div>
HTML,
				]
			],
		] );

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
