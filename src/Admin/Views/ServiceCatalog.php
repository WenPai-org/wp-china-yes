<?php
/**
 * Ecosystem service catalog and companion plugin detector for WPCY admin views.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Admin\Views;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides WenPai ecosystem service definitions, live status reports, and companion detection.
 */
final class ServiceCatalog {

	/**
	 * Collect live status reports from companion WenPai standalone plugins.
	 *
	 * @param array<string, mixed> $settings Current settings snapshot.
	 * @return array<string, array<string, string>>
	 */
	public static function status_reports( array $settings ): array {
		if ( ! function_exists( 'apply_filters' ) ) {
			return array();
		}
		$raw = apply_filters( 'wpcy_service_status', array(), $settings );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$clean = array();
		foreach ( $raw as $key => $entry ) {
			if ( ! is_string( $key ) || ! is_array( $entry ) ) {
				continue;
			}
			$clean[ sanitize_key( $key ) ] = array(
				'summary'      => isset( $entry['summary'] ) ? sanitize_text_field( (string) $entry['summary'] ) : '',
				'pill'         => isset( $entry['pill'] ) ? sanitize_text_field( (string) $entry['pill'] ) : '',
				'state'        => isset( $entry['state'] ) ? sanitize_key( (string) $entry['state'] ) : 'ok',
				'action_label' => isset( $entry['action_label'] ) ? sanitize_text_field( (string) $entry['action_label'] ) : '',
				'action_url'   => isset( $entry['action_url'] ) ? esc_url_raw( (string) $entry['action_url'] ) : '',
			);
		}
		return $clean;
	}

	/**
	 * Service catalog metadata and companion standalone plugin definitions.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function catalog(): array {
		$adm_script = 'admin';
		$adm_entry  = $adm_script . '.php?page=';
		$opt_entry  = 'options-general.php?page=';

		$catalog = array(
			'wporg'      => array(
				'group'         => 'builtin',
				'category'      => 'ops',
				'title'         => __( 'WP.org 镜像', 'wp-china-yes' ),
				'icon'          => 'cloud',
				'short_desc'    => __( '核心、主题、插件与中文语言包镜像 · WenPai.org', 'wp-china-yes' ),
				'desc'          => __( '为 WordPress 核心、插件、主题更新与中文翻译包提供国内高速镜像及自动故障回退。', 'wp-china-yes' ),
				'plugin_name'   => __( '文派云桥（WPBridge）', 'wp-china-yes' ),
				'plugin_slug'   => 'wpbridge',
				'plugin_file'   => 'wpbridge/wpbridge.php',
				'plugin_admin'  => $adm_entry . 'wpbridge',
				'plugin_url'    => 'https://wpcy.com/bridge',
				'plugin_pitch'  => __( '在后台直接浏览与安装文派生态插件与主题目录，并管理商业版更新通道。', 'wp-china-yes' ),
				'takeover_desc' => __( '生态目录浏览与自定义更新通道已由文派云桥（WPBridge）协同管理。', 'wp-china-yes' ),
			),
			'admincdn'   => array(
				'group'         => 'builtin',
				'category'      => 'ops',
				'title'         => __( '公共前端库', 'wp-china-yes' ),
				'icon'          => 'flash',
				'short_desc'    => __( 'Google Fonts、CDNJS、jsDelivr 与核心静态加速 · adminCDN', 'wp-china-yes' ),
				'desc'          => __( '由萌芽加速（adminCDN）提供常用开源前端库与静态资源的国内分发加速。', 'wp-china-yes' ),
				'plugin_name'   => __( '萌芽静态加速（WPStatic）', 'wp-china-yes' ),
				'plugin_slug'   => 'wpstatic',
				'plugin_file'   => 'wpstatic/wpstatic.php',
				'plugin_admin'  => $opt_entry . 'wpstatic',
				'plugin_url'    => 'https://wpcy.com/static',
				'plugin_pitch'  => __( '按版本精细托管 WordPress 核心静态资源，并自定义公共库白名单规则。', 'wp-china-yes' ),
				'takeover_desc' => __( '核心静态资源托管与公共库白名单策略已由萌芽静态加速（WPStatic）接管。', 'wp-china-yes' ),
			),
			'cravatar'   => array(
				'group'         => 'builtin',
				'category'      => 'media',
				'title'         => __( 'Cravatar 头像', 'wp-china-yes' ),
				'icon'          => 'image',
				'short_desc'    => __( 'Gravatar 国内替代与全球双线分发 · 初认 Cravatar', 'wp-china-yes' ),
				'desc'          => __( '由初认（Cravatar）提供国内外双线头像加速，消除头像破图与跨海等待。', 'wp-china-yes' ),
				'plugin_name'   => __( '文派头像（WPAvatar）', 'wp-china-yes' ),
				'plugin_slug'   => 'wpavatar',
				'plugin_file'   => 'wpavatar/wpavatar.php',
				'plugin_admin'  => $opt_entry . 'wpavatar-settings',
				'plugin_url'    => 'https://wpcy.com/avatar',
				'plugin_pitch'  => __( '开启用户本地上传头像、中文首字头像生成、默认矢量头像与本地缓存管理。', 'wp-china-yes' ),
				'takeover_desc' => __( '本地头像上传、首字头像生成与缓存策略已由文派头像（WPAvatar）接管。', 'wp-china-yes' ),
			),
			'noise'      => array(
				'group'         => 'ext',
				'category'      => 'ops',
				'title'         => __( '评论治理', 'wp-china-yes' ),
				'icon'          => 'feedback',
				'short_desc'    => __( '评论审核工作流、常用回复模板与垃圾评论拦截 · WPComments', 'wp-china-yes' ),
				'desc'          => __( '为站点评论区提供高效的审核流转、快捷回复模板与垃圾信息过滤。', 'wp-china-yes' ),
				'plugin_name'   => __( '文派评论（WPComments）', 'wp-china-yes' ),
				'plugin_slug'   => 'wpcomments',
				'plugin_file'   => 'wpcomments/wpcomments.php',
				'plugin_admin'  => $opt_entry . 'wpcomments-settings',
				'plugin_url'    => 'https://wpcy.com/comments',
				'plugin_pitch'  => __( '提供常用回复短语、评论者身份标识与自动化垃圾评论拦截能力。', 'wp-china-yes' ),
				'takeover_desc' => __( '评论审核与常用回复工作流已由文派评论（WPComments）接管。', 'wp-china-yes' ),
			),
			'ai'         => array(
				'group'         => 'ext',
				'category'      => 'ai',
				'title'         => __( 'AI 智能与 GEO', 'wp-china-yes' ),
				'icon'          => 'sparkle',
				'short_desc'    => __( '多模型 AI 网关路由、写作辅助与生成式搜索优化 · WPMind', 'wp-china-yes' ),
				'desc'          => __( '统一接入国内外主流大模型接口，提供故障自动切换与生成式引擎优化（GEO）。', 'wp-china-yes' ),
				'plugin_name'   => __( '文派心思（WPMind）', 'wp-china-yes' ),
				'plugin_slug'   => 'wpmind',
				'plugin_file'   => 'wpmind/wpmind.php',
				'plugin_admin'  => $adm_entry . 'wpmind',
				'plugin_url'    => 'https://wpcy.com/mind',
				'plugin_pitch'  => __( '统一管理 DeepSeek、通义千问、OpenAI 等多服务商路由，内置 llms.txt 生成与内容辅助。', 'wp-china-yes' ),
				'takeover_desc' => __( '多模型 AI 网关与 GEO 生成式搜索优化已由文派心思（WPMind）接管。', 'wp-china-yes' ),
			),
			'storage'    => array(
				'group'         => 'ext',
				'category'      => 'media',
				'title'         => __( '对象存储', 'wp-china-yes' ),
				'icon'          => 'server',
				'short_desc'    => __( '媒体库附件自动同步至存储桶与自定义 CDN · WPSSS', 'wp-china-yes' ),
				'desc'          => __( '将本站上传的图片与媒体文件自动同步至阿里云 OSS、腾讯云 COS、华为云 OBS 或 S3 兼容存储桶。', 'wp-china-yes' ),
				'plugin_name'   => __( '文派智储（WPSSS）', 'wp-china-yes' ),
				'plugin_slug'   => 'wpsss',
				'plugin_file'   => 'wpsss/wpsss.php',
				'plugin_admin'  => $opt_entry . 'wpsss',
				'plugin_url'    => 'https://wpcy.com/wpsss',
				'plugin_pitch'  => __( '连接主流云厂商存储桶与自定义加速域名，支持历史媒体补传与连接自检。', 'wp-china-yes' ),
				'takeover_desc' => __( '媒体文件同步至存储桶与自定义加速域名已由文派智储（WPSSS）接管。', 'wp-china-yes' ),
			),
			'compliance' => array(
				'group'         => 'ext',
				'category'      => 'ops',
				'title'         => __( '政策合规', 'wp-china-yes' ),
				'icon'          => 'info',
				'short_desc'    => __( '工信部 ICP 备案号与公安联网备案规范展示 · WPICP', 'wp-china-yes' ),
				'desc'          => __( '在中国大陆运营站点页脚规范展示 ICP 备案号、公安网安备案号与经营许可信息。', 'wp-china-yes' ),
				'plugin_name'   => __( '文派备案（WPICP）', 'wp-china-yes' ),
				'plugin_slug'   => 'wpicp',
				'plugin_file'   => 'wpicp/wpicp.php',
				'plugin_admin'  => $opt_entry . 'wpicp',
				'plugin_url'    => 'https://wpcy.com/icp',
				'plugin_pitch'  => __( '在站点页脚规范输出工信部 ICP 备案、公安联网备案标识及增值电信许可证信息。', 'wp-china-yes' ),
				'takeover_desc' => __( '前台页脚备案标识与合规公示已由文派备案（WPICP）接管。', 'wp-china-yes' ),
			),
			'windfonts'  => array(
				'group'         => 'ext',
				'category'      => 'media',
				'title'         => __( '中文字体', 'wp-china-yes' ),
				'icon'          => 'feather',
				'short_desc'    => __( '云端 WOFF2 中文动态子集字体库与古腾堡集成 · Windfonts', 'wp-china-yes' ),
				'desc'          => __( '通过文风字体（Windfonts）按需加载轻量中文子集切片，兼顾中文排版品质与首屏速度。', 'wp-china-yes' ),
				'plugin_name'   => __( '文派字库（WPFonts）', 'wp-china-yes' ),
				'plugin_slug'   => 'wpfonts',
				'plugin_file'   => 'wpfonts/wpfonts.php',
				'plugin_admin'  => $opt_entry . 'wpfonts',
				'plugin_url'    => 'https://wpcy.com/webfonts',
				'plugin_pitch'  => __( '检索免费商用中文字体，接入区块编辑器字体库并按标题、正文精细指定字族。', 'wp-china-yes' ),
				'takeover_desc' => __( '中文字库选择、区块字体库与样式绑定已由文派字库（WPFonts）接管。', 'wp-china-yes' ),
			),
			'motucloud'  => array(
				'group'         => 'ext',
				'category'      => 'media',
				'title'         => __( '图标与图库', 'wp-china-yes' ),
				'icon'          => 'image',
				'short_desc'    => __( '墨图爱可矢量图标与免版权高清图片库 · WPPhoto', 'wp-china-yes' ),
				'desc'          => __( '集成墨图云集（MotuCloud）开源矢量图标与免版权图库，由墨图图库插件统一承载。', 'wp-china-yes' ),
				'plugin_name'   => __( '墨图图库（WPPhoto）', 'wp-china-yes' ),
				'plugin_slug'   => 'wpphoto',
				'plugin_file'   => 'wpphoto/wpphoto.php',
				'plugin_admin'  => $adm_entry . 'wpphoto',
				'plugin_url'    => 'https://wpcy.com/photo',
				'plugin_pitch'  => __( '在区块编辑器与媒体库内直接检索、插入墨图爱可 SVG 图标与免版权高清配图。', 'wp-china-yes' ),
				'takeover_desc' => __( '编辑器矢量图标选择与免版权图片检索已由墨图图库（WPPhoto）接管。', 'wp-china-yes' ),
			),
			'slug'       => array(
				'group'         => 'ext',
				'category'      => 'chinese',
				'title'         => __( '中文体验优化', 'wp-china-yes' ),
				'icon'          => 'equalizer',
				'short_desc'    => __( '中文标题自动转拼音或英文固定链接别名 · WPSlug', 'wp-china-yes' ),
				'desc'          => __( '自动将文章、分类法与上传媒体的中文名称转换为干净的拼音或英文别名。', 'wp-china-yes' ),
				'plugin_name'   => __( '文派素格（WPSlug）', 'wp-china-yes' ),
				'plugin_slug'   => 'wpslug',
				'plugin_file'   => 'wpslug/wpslug.php',
				'plugin_admin'  => $opt_entry . 'wpslug',
				'plugin_url'    => 'https://wpcy.com/slug',
				'plugin_pitch'  => __( '支持本地拼音、多语种翻译与音译别名生成，内置历史文章批量转换与自定义词表。', 'wp-china-yes' ),
				'takeover_desc' => __( '中文标题转别名与上传文件名规范化已由文派素格（WPSlug）接管。', 'wp-china-yes' ),
			),
			'converter'  => array(
				'group'         => 'ext',
				'category'      => 'chinese',
				'title'         => __( '中文简繁转换', 'wp-china-yes' ),
				'icon'          => 'map',
				'short_desc'    => __( '大陆、港澳台及海外华人多地区简繁词汇转换 · WPCC', 'wp-china-yes' ),
				'desc'          => __( '面向跨地区华语访客提供准确的中文简繁转换、地域词汇适配与搜索引擎多地区标记。', 'wp-china-yes' ),
				'plugin_name'   => __( '文派译词（WPCC）', 'wp-china-yes' ),
				'plugin_slug'   => 'wp-chinese-converter',
				'plugin_file'   => 'wp-chinese-converter/wp-chinese-converter.php',
				'plugin_admin'  => $opt_entry . 'wp-chinese-converter',
				'plugin_url'    => 'https://wpcy.com/chinese-converter',
				'plugin_pitch'  => __( '基于专业词汇表支持 6 种中文地区变体实时转换、访客语言自动识别与 hreflang 标记。', 'wp-china-yes' ),
				'takeover_desc' => __( '前台多地区中文简繁转换与词汇适配已由文派译词（WPCC）接管。', 'wp-china-yes' ),
			),
		);

		if ( function_exists( 'get_transient' ) ) {
			$remote = get_transient( 'wpcy_catalog' );
			if ( ! is_array( $remote ) ) {
				$remote = get_transient( 'wpcy_catalog_stale' );
			}
			if ( is_array( $remote ) ) {
				$items = isset( $remote['services'] ) && is_array( $remote['services'] ) ? $remote['services'] : $remote;
				foreach ( $items as $row ) {
					if ( ! is_array( $row ) || empty( $row['id'] ) || empty( $row['title'] ) ) {
						continue;
					}
					$rid = sanitize_key( (string) $row['id'] );
					if ( '' === $rid || isset( $catalog[ $rid ] ) ) {
						continue;
					}
					$slug            = ! empty( $row['plugin_slug'] ) ? sanitize_key( (string) $row['plugin_slug'] ) : $rid;
					$catalog[ $rid ] = array(
						'group'         => 'ext',
						'category'      => ! empty( $row['category'] ) ? sanitize_key( (string) $row['category'] ) : 'ops',
						'title'         => sanitize_text_field( (string) $row['title'] ),
						'icon'          => ! empty( $row['icon'] ) ? sanitize_key( (string) $row['icon'] ) : 'sparkle',
						'short_desc'    => ! empty( $row['short_desc'] ) ? sanitize_text_field( (string) $row['short_desc'] ) : sanitize_text_field( (string) ( $row['desc'] ?? '' ) ),
						'desc'          => sanitize_text_field( (string) ( $row['desc'] ?? $row['short_desc'] ?? '' ) ),
						'plugin_name'   => sanitize_text_field( (string) ( $row['plugin_name'] ?? $row['title'] ) ),
						'plugin_slug'   => $slug,
						'plugin_file'   => ! empty( $row['plugin_file'] ) ? sanitize_text_field( (string) $row['plugin_file'] ) : $slug . '/' . $slug . '.php',
						'plugin_admin'  => ! empty( $row['plugin_admin'] ) ? sanitize_text_field( (string) $row['plugin_admin'] ) : $adm_entry . $slug,
						'plugin_url'    => ! empty( $row['plugin_url'] ) ? esc_url_raw( (string) $row['plugin_url'] ) : 'https://wpcy.com/' . $slug,
						'plugin_pitch'  => sanitize_text_field( (string) ( $row['plugin_pitch'] ?? $row['desc'] ?? '' ) ),
						'takeover_desc' => sanitize_text_field( (string) ( $row['takeover_desc'] ?? __( '已由对应文派独立插件协同接管。', 'wp-china-yes' ) ) ),
					);
				}
			}
		}

		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'wpcy_service_catalog', $catalog );
			if ( is_array( $filtered ) ) {
				return $filtered;
			}
		}

		return $catalog;
	}

	/**
	 * Detect whether a companion standalone plugin is installed and active.
	 *
	 * @param string $plugin_file Relative plugin file path.
	 * @param string $plugin_slug Plugin directory slug.
	 * @return array{installed: bool, active: bool, version: string}
	 */
	public static function detect_companion_plugin( string $plugin_file, string $plugin_slug ): array {
		$installed = false;
		$active    = false;
		$version   = '';

		if ( function_exists( 'get_plugins' ) ) {
			$all = get_plugins();
			if ( isset( $all[ $plugin_file ] ) ) {
				$installed = true;
				$version   = isset( $all[ $plugin_file ]['Version'] ) ? (string) $all[ $plugin_file ]['Version'] : '';
			} else {
				foreach ( $all as $file => $data ) {
					if ( 0 === strpos( (string) $file, $plugin_slug . '/' ) ) {
						$installed   = true;
						$plugin_file = (string) $file;
						$version     = isset( $data['Version'] ) ? (string) $data['Version'] : '';
						break;
					}
				}
			}
		}

		if ( $installed && function_exists( 'is_plugin_active' ) ) {
			$active = is_plugin_active( $plugin_file );
		} elseif ( $installed && function_exists( 'get_option' ) ) {
			$act    = (array) get_option( 'active_plugins', array() );
			$active = in_array( $plugin_file, $act, true );
		}

		return array(
			'installed' => $installed,
			'active'    => $active,
			'version'   => $version,
		);
	}
}
