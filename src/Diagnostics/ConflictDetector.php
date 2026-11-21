<?php
/**
 * Detects third-party overlapping/broken acceleration plugins and active WenPai companion synergies.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides runtime handoff checks and structured conflict/synergy reports for Overview, Diagnose, and Wizard.
 */
final class ConflictDetector {

	/**
	 * Allowlisted WenPai companion plugin metadata keyed by plugin slug.
	 *
	 * @var array<string, array{file: string, const: string, name: string, top: bool, url: string, synergy: string}>
	 */
	public const COMPANIONS = array(
		'wpavatar'             => array(
			'file'    => 'wpavatar/wpavatar.php',
			'const'   => 'WPAVATAR_VERSION',
			'name'    => '文派头像（WPAvatar）',
			'top'     => false,
			'url'     => 'https://wpcy.com/avatar',
			'synergy' => '头像源切换、首字头像生成与本地缓存已自动交由文派头像接管，避免双重过滤。',
		),
		'wpfonts'              => array(
			'file'    => 'wpfonts/wpfonts.php',
			'const'   => 'WPFONTS_VERSION',
			'name'    => '文派字库（WPFonts）',
			'top'     => true,
			'url'     => 'https://wpcy.com/webfonts',
			'synergy' => '中文字体切片与区块编辑器字体库已与文派字库协同工作。',
		),
		'wpsss'                => array(
			'file'    => 'wpsss/wpsss.php',
			'const'   => 'WPSSS_VERSION',
			'name'    => '文派智储（WPSSS）',
			'top'     => false,
			'url'     => 'https://wpcy.com/wpsss',
			'synergy' => '媒体库对象存储同步与自定义加速域名已由文派智储接管。',
		),
		'wpslug'               => array(
			'file'    => 'wpslug/wpslug.php',
			'const'   => 'WPSLUG_VERSION',
			'name'    => '文派素格（WPSlug）',
			'top'     => false,
			'url'     => 'https://wpcy.com/slug',
			'synergy' => '中文标题与媒体文件名转别名已由文派素格协同管理。',
		),
		'wpmind'               => array(
			'file'    => 'wpmind/wpmind.php',
			'const'   => 'WPMIND_VERSION',
			'name'    => '文派心思（WPMind）',
			'top'     => true,
			'url'     => 'https://wpcy.com/mind',
			'synergy' => '多模型 AI 接口路由与 GEO 搜索优化已由文派心思接管。',
		),
		'wpbridge'             => array(
			'file'    => 'wpbridge/wpbridge.php',
			'const'   => 'WPBRIDGE_VERSION',
			'name'    => '文派云桥（WPBridge）',
			'top'     => true,
			'url'     => 'https://wpcy.com/bridge',
			'synergy' => '生态插件目录浏览与商业更新通道已与文派云桥协同联动。',
		),
		'wpstatic'             => array(
			'file'    => 'wpstatic/wpstatic.php',
			'const'   => 'WPSTATIC_VERSION',
			'name'    => '萌芽静态加速（WPStatic）',
			'top'     => false,
			'url'     => 'https://wpcy.com/static',
			'synergy' => '核心静态文件托管与公共库白名单已由萌芽静态加速接管。',
		),
		'wpcomments'           => array(
			'file'    => 'wpcomments/wpcomments.php',
			'const'   => 'WPCOMMENTS_VERSION',
			'name'    => '文派评论（WPComments）',
			'top'     => false,
			'url'     => 'https://wpcy.com/comments',
			'synergy' => '评论审核流转与快捷回复模板已由文派评论接管。',
		),
		'wp-chinese-converter' => array(
			'file'    => 'wp-chinese-converter/wp-chinese-converter.php',
			'const'   => 'WPCC_VERSION',
			'name'    => '文派译词（WPCC）',
			'top'     => false,
			'url'     => 'https://wpcy.com/chinese-converter',
			'synergy' => '多地区中文简繁转换与语言切换标记已由文派译词接管。',
		),
		'wpphoto'              => array(
			'file'    => 'wpphoto/wpphoto.php',
			'const'   => 'WPPHOTO_VERSION',
			'name'    => '墨图图库（WPPhoto）',
			'top'     => true,
			'url'     => 'https://wpcy.com/photo',
			'synergy' => '矢量图标检索与免版权配图插入已由墨图图库接管。',
		),
		'wpicp'                => array(
			'file'    => 'wpicp/wpicp.php',
			'const'   => 'WPICP_VERSION',
			'name'    => '文派备案（WPICP）',
			'top'     => false,
			'url'     => 'https://wpcy.com/icp',
			'synergy' => '前台页脚工信部 ICP 与公安联网备案标识已由文派备案接管。',
		),
	);

	/**
	 * Known third-party overlapping or defunct acceleration plugin signatures keyed by directory slug.
	 *
	 * @var array<string, array{name: string, category: string, level: string, reason: string}>
	 */
	private const CONFLICT_RULES = array(
		'useso-take-over-google'         => array(
			'name'     => '360 前端公共库替换（Useso）',
			'category' => 'assets',
			'level'    => 'bad',
			'reason'   => 'useso.com 节点早已停服关闭，继续开启会导致后台与前台请求长时间卡顿超时，建议立即停用。',
		),
		'wp-china-plus'                  => array(
			'name'     => 'WP-China-Plus',
			'category' => 'mirror',
			'level'    => 'warn',
			'reason'   => '与文派叶子同挂更新镜像和静态源替换钩子，同时启用会导致请求地址被重复改写，建议仅保留一款。',
		),
		'kill-the-linktr'                => array(
			'name'     => 'Kill The Linktr / 旧版镜像替换',
			'category' => 'mirror',
			'level'    => 'warn',
			'reason'   => '与内置 WP.org 国内镜像通道重叠，建议停用旧插件由文派叶子统一提供健康回退保护。',
		),
		'faster-updates'                 => array(
			'name'     => 'Faster Updates 镜像插件',
			'category' => 'mirror',
			'level'    => 'warn',
			'reason'   => '同样拦截 api.wordpress.org 更新请求，双插件并存易引发证书或下载源冲突。',
		),
		'google-font-fix'                => array(
			'name'     => 'Google Font Fix',
			'category' => 'assets',
			'level'    => 'warn',
			'reason'   => '旧版 Google 字体替换插件节点陈旧，且与内置 adminCDN 静态加速重复处理同一外部资源。',
		),
		'replace-google-fonts'           => array(
			'name'     => 'Replace Google Fonts',
			'category' => 'assets',
			'level'    => 'warn',
			'reason'   => '与文派叶子公共前端库加速范围重叠，建议停用旧替换插件以免二次改写样式地址。',
		),
		'disable-google-fonts'           => array(
			'name'     => 'Disable Google Fonts',
			'category' => 'assets',
			'level'    => 'warn',
			'reason'   => '直接清空字体样式队列会导致主题预设字族失效；保留文派叶子即可通过国内节点高速加载。',
		),
		'remove-google-fonts-references' => array(
			'name'     => 'Remove Google Fonts References',
			'category' => 'assets',
			'level'    => 'warn',
			'reason'   => '全页正则剥离字体链接会拦截 adminCDN 国内替换，无需与文派叶子同时开启。',
		),
		'gravatar-china'                 => array(
			'name'     => 'Gravatar China 头像替换',
			'category' => 'avatar',
			'level'    => 'warn',
			'reason'   => '与内置初认（Cravatar）头像通道重复挂载 get_avatar_url 钩子，建议只保留一处。',
		),
		'geekzu-gravatar'                => array(
			'name'     => '极客族 Gravatar 替换',
			'category' => 'avatar',
			'level'    => 'warn',
			'reason'   => '旧版头像源与内置初认（Cravatar）通道重复改写，停用后即可由 Cravatar 稳定加速。',
		),
	);

	/**
	 * Companion-conditional overlap rules (flagged when the corresponding WenPai companion is also active,
	 * or when two plugins in the same functional slot are active simultaneously).
	 *
	 * @var array<string, array{name: string, companion: string, level: string, reason: string}>
	 */
	private const COMPANION_OVERLAP_RULES = array(
		'wp-pinyin-slug'       => array(
			'name'      => 'WP Pinyin Slug',
			'companion' => 'wpslug',
			'level'     => 'warn',
			'reason'    => '与文派素格同挂 sanitize_title 钩子，双插件并存会导致文章或媒体别名被重复转写。',
		),
		'so-pinyin-slugs'      => array(
			'name'      => 'SO Pinyin Slugs',
			'companion' => 'wpslug',
			'level'     => 'warn',
			'reason'    => '与文派素格功能重叠，建议只保留一款拼音别名转换插件。',
		),
		'wenprise-pinyin-slug' => array(
			'name'      => 'Wenprise Pinyin Slug',
			'companion' => 'wpslug',
			'level'     => 'warn',
			'reason'    => '与文派素格同为标题转拼音插件，同时启用易引发固定链接二次截断。',
		),
		'aliyun-oss'           => array(
			'name'      => 'Aliyun OSS 第三方卸载插件',
			'companion' => 'wpsss',
			'level'     => 'warn',
			'reason'    => '与文派智储同时挂载媒体附件上传钩子会导致附件 URL 混乱，请仅保留一款存储插件。',
		),
		'wpcos'                => array(
			'name'      => 'WPCOS 腾讯云存储插件',
			'companion' => 'wpsss',
			'level'     => 'warn',
			'reason'    => '与文派智储同时接管媒体库会导致附件路径冲突，建议停用重复存储插件。',
		),
		'qiniu-cloud-storage'  => array(
			'name'      => '七牛云存储第三方插件',
			'companion' => 'wpsss',
			'level'     => 'warn',
			'reason'    => '与文派智储功能重叠，多插件同改媒体地址易导致前台缩略图失效。',
		),
	);

	/**
	 * Check whether a specific WenPai companion plugin is currently active.
	 *
	 * @param string $slug Companion plugin directory slug (e.g. `wpavatar`).
	 * @return bool
	 */
	public static function is_companion_active( string $slug ): bool {
		if ( ! isset( self::COMPANIONS[ $slug ] ) ) {
			return false;
		}
		$meta = self::COMPANIONS[ $slug ];
		if ( defined( $meta['const'] ) ) {
			return true;
		}
		$active_map = self::active_plugin_slugs();
		return isset( $active_map[ $slug ] );
	}

	/**
	 * Scan active plugins for WenPai companion synergies and third-party conflicts.
	 *
	 * @param list<string>|null $active_files Optional explicit list of active plugin basenames (for testing).
	 * @return array{
	 *     conflicts: list<array{id: string, slug: string, file: string, name: string, level: string, reason: string}>,
	 *     synergies: list<array{slug: string, file: string, name: string, admin: string, url: string, synergy: string}>
	 * }
	 */
	public static function scan( ?array $active_files = null ): array {
		$adm_script = 'admin';
		$adm_entry  = $adm_script . '.php?page=';
		$slug_map   = self::active_plugin_slugs( $active_files );
		$synergies  = array();
		foreach ( self::COMPANIONS as $slug => $meta ) {
			$is_on = isset( $slug_map[ $slug ] ) || ( null === $active_files && defined( $meta['const'] ) );
			if ( ! $is_on ) {
				continue;
			}
			$admin_rel   = ( class_exists( '\Wenpai_Admin_Menu', false ) && \Wenpai_Admin_Menu::owns_page( $slug ) )
				? \Wenpai_Admin_Menu::url( $slug )
				: ( ( $meta['top'] ? $adm_entry : 'options-general.php?page=' ) . $slug );
			$synergies[] = array(
				'slug'    => $slug,
				'file'    => $slug_map[ $slug ] ?? $meta['file'],
				'name'    => $meta['name'],
				'admin'   => $admin_rel,
				'url'     => $meta['url'],
				'synergy' => $meta['synergy'],
			);
		}

		$conflicts = array();
		foreach ( self::CONFLICT_RULES as $slug => $rule ) {
			if ( ! isset( $slug_map[ $slug ] ) ) {
				continue;
			}
			$conflicts[] = array(
				'id'     => 'conflict.' . $slug,
				'slug'   => $slug,
				'file'   => $slug_map[ $slug ],
				'name'   => $rule['name'],
				'level'  => $rule['level'],
				'reason' => $rule['reason'],
			);
		}

		$active_synergy_slugs = array();
		foreach ( $synergies as $syn ) {
			$active_synergy_slugs[ $syn['slug'] ] = true;
		}

		foreach ( self::COMPANION_OVERLAP_RULES as $slug => $rule ) {
			if ( ! isset( $slug_map[ $slug ] ) ) {
				continue;
			}
			if ( ! isset( $active_synergy_slugs[ $rule['companion'] ] ) ) {
				continue;
			}
			$conflicts[] = array(
				'id'     => 'conflict.' . $slug,
				'slug'   => $slug,
				'file'   => $slug_map[ $slug ],
				'name'   => $rule['name'],
				'level'  => $rule['level'],
				'reason' => $rule['reason'],
			);
		}

		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'wpcy_detected_conflicts', $conflicts, $synergies );
			if ( is_array( $filtered ) ) {
				$conflicts = array_values( array_filter( $filtered, 'is_array' ) );
			}
		}

		return array(
			'conflicts' => $conflicts,
			'synergies' => $synergies,
		);
	}

	/**
	 * Starter bundle recommendations for the Onboarding Wizard Step 3, ordered by priority.
	 *
	 * @param string $profile Current site profile (`domestic`, `crossborder`, `mixed`).
	 * @return list<array{slug: string, file: string, icon: string, name: string, short_desc: string, recommended: bool}>
	 */
	public static function wizard_starter_items( string $profile = 'domestic' ): array {
		$is_cross = 'crossborder' === $profile;
		return array(
			array(
				'slug'        => 'wpavatar',
				'file'        => 'wpavatar/wpavatar.php',
				'icon'        => 'user',
				'name'        => '文派头像（WPAvatar）',
				'short_desc'  => '开启本地上传头像、中文首字矢量头像生成与头像本地缓存',
				'recommended' => ! $is_cross,
			),
			array(
				'slug'        => 'wpslug',
				'file'        => 'wpslug/wpslug.php',
				'icon'        => 'equalizer',
				'name'        => '文派素格（WPSlug）',
				'short_desc'  => '中文文章标题、分类与上传媒体文件名自动生成干净拼音或英文别名',
				'recommended' => true,
			),
			array(
				'slug'        => 'wpfonts',
				'file'        => 'wpfonts/wpfonts.php',
				'icon'        => 'feather',
				'name'        => '文派字库（WPFonts）',
				'short_desc'  => '为区块编辑器补充标准中英日韩系统字栈，并可按需接入中文字体切片',
				'recommended' => ! $is_cross,
			),
			array(
				'slug'        => 'wpsss',
				'file'        => 'wpsss/wpsss.php',
				'icon'        => 'server',
				'name'        => '文派智储（WPSSS）',
				'short_desc'  => '将媒体库图片与附件自动同步至阿里云 OSS、腾讯云 COS、OBS 或 S3 存储桶',
				'recommended' => false,
			),
			array(
				'slug'        => 'wp-chinese-converter',
				'file'        => 'wp-chinese-converter/wp-chinese-converter.php',
				'icon'        => 'map',
				'name'        => '文派译词（WPCC）',
				'short_desc'  => '面向港澳台与海外华语访客提供多地区简繁词汇转换与搜索引擎语言标记',
				'recommended' => $is_cross,
			),
			array(
				'slug'        => 'wpicp',
				'file'        => 'wpicp/wpicp.php',
				'icon'        => 'shield-check',
				'name'        => '文派备案（WPICP）',
				'short_desc'  => '在站点页脚规范输出工信部 ICP 备案号与公安联网备案标识',
				'recommended' => 'domestic' === $profile,
			),
		);
	}

	/**
	 * Build a map of `directory_slug => plugin_basename` for currently active site + network plugins.
	 *
	 * @param list<string>|null $explicit Optional explicit active basenames list.
	 * @return array<string, string>
	 */
	private static function active_plugin_slugs( ?array $explicit = null ): array {
		$files = array();
		if ( null !== $explicit ) {
			$files = $explicit;
		} else {
			if ( function_exists( 'get_option' ) ) {
				$site_active = get_option( 'active_plugins', array() );
				if ( is_array( $site_active ) ) {
					foreach ( $site_active as $f ) {
						if ( is_string( $f ) && '' !== $f ) {
							$files[] = $f;
						}
					}
				}
			}
			if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_site_option' ) ) {
				$net_active = get_site_option( 'active_sitewide_plugins', array() );
				if ( is_array( $net_active ) ) {
					foreach ( array_keys( $net_active ) as $nf ) {
						if ( is_string( $nf ) && '' !== $nf ) {
							$files[] = $nf;
						}
					}
				}
			}
		}

		$map = array();
		foreach ( $files as $file ) {
			$pos  = strpos( $file, '/' );
			$slug = false !== $pos ? substr( $file, 0, $pos ) : basename( $file, '.php' );
			$slug = strtolower( trim( $slug ) );
			if ( '' !== $slug && 'wp-china-yes' !== $slug ) {
				$map[ $slug ] = $file;
			}
		}
		return $map;
	}
}
