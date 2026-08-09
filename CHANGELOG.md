# Changelog

All notable changes to `WP-China-Yes` will be documented in this file.

## 未发布

## v3.9.3 - 2026-08-09

### 安全与隐私
- 设置导入、导出、重置只允许管理员操作固定的 `wp_china_yes` option；评论置顶接口补充 `moderate_comments` 权限与参数校验。
- 匿名运行数据改为默认关闭、明确选择加入；默认不发送站点 URL，并停止收集订单、用户、商品、配送区域、主题模板等业务数据。
- 数据库工具不再在普通插件加载阶段定义 `WP_ALLOW_REPAIR`，避免公开数据库修复入口。

### 稳定性
- 每个服务只由 `Service/Base.php` 初始化一次，修复 Fonts、Comments、Avatar、Adblock 等重复挂钩。
- 修复设置 option 损坏或数组字段变成字符串时的 PHP 8 类型错误，并使设置缓存可以真正清除。
- 保留 `jquery-migrate`，避免主题和插件依赖链在 WordPress 核心升级后断裂。
- 修复飞行模式保存 `url`、运行时读取 `domain` 的字段不一致；匹配改为精确主机或子域名，不再使用任意子串匹配。
- 停用会在短暂网络故障后静默改写用户设置的旧节点 Monitor；性能优化与系统页脚信息对新安装默认关闭。
- PHP 运行时要求与插件头统一为 7.4，兼容信息更新为实际验证目标 WordPress 7.0。
- 修复维护模式注册了不存在的方法而导致前台和后台 PHP Fatal；管理员、REST 与 AJAX 请求保持可用。
- 修复服务器内存信息在 `plugins_loaded` 之后再次注册同名钩子而始终不生效，并正确处理无限内存限制。
- 修复评论置顶 AJAX 处理器只在评论列表页面加载、实际 AJAX 请求返回 `0` 的问题。
- 移除 PHP 8 专用字符串函数和已废弃的 `utf8_decode()`，维持声明的 PHP 7.4 兼容性。

### 发布
- 新增 PHP 7.4–8.4 CI、独立测试入口和包含 Composer 依赖的可重复 ZIP 打包脚本。
- 插件列表操作链接只作用于 WPCY 自身，不再修改所有插件的操作链接。
- 发现可能冲突的旧插件时只提示管理员，不再自动停用其他插件。
- 修复当前 WordPress 后台产生的 jQuery `keydown()`、`isArray()` 废弃警告。
- 修复 Git worktree 构建时发布包夹带 `.git`、`composer.json` 和 `composer.lock`。

### 移除
- **废弃并移除「前台加速」（`public.admincdn.com`）**。该功能把站点自己的
  `/wp-content|/wp-includes` 路径整体改写到一个共享端点，但 **`wp-content` 是站点自有内容**
  （主题、插件、上传文件），共享端点不可能持有各站的这些文件 —— 结果要么 404，要么更糟：
  返回别的站的同名文件，使站点静默加载到不属于它的 CSS/JS/图片。**返回错内容比返回 404 更有害。**
  `wp-includes` 部分（核心文件、各站相同）本可保留，但已由「后台加速」覆盖，无需第二条链路。
  该选项此前**默认关闭**，绝大多数站点不受影响；已启用的站点在后台访问时自动摘除该项
  （`Service/Migration.php`），其余加速项不受影响。

### 修复
- **WordPress.org 镜像不可用时不再阻断插件/主题的安装与更新**。`filter_wordpress_org()`
  会把 `api.wordpress.org` 与 `downloads.wordpress.org` 的请求改写到自家镜像，而该替换
  **默认开启**（`store` 默认为 `wenpai`）。镜像一旦不能提供安装包，站点的插件/主题
  **搜索、信息查询、安装、更新下载会全链路失效** —— 把可用的上游换成不可用的镜像，
  比不加速糟得多。现在改写前先确认镜像确实能提供安装包，不能则原样走 WordPress.org。
  镜像恢复后自动重新启用加速。
  探测判据同时看**状态码与内容类型**：镜像故障时会以 `application/json`
  （`{"code":"rest_no_route"}`）或主题化 HTML 的 404 应答，光看状态码会把坏的判成好的。
- **加速镜像不可用时不再把站点资源一起带死**：`admincdn` 各镜像端点新增健康检测
  （`Service/MirrorHealth.php`），端点不可用时跳过替换、保留原始公共 CDN 链接，
  端点恢复后自动重新启用，并在后台提示当前回退状态。
  此前替换是无条件的字符串/正则替换，镜像故障会导致站点前端 JS/CSS/字体大面积 404 ——
  把可用的公共 CDN 换成失效镜像，比不加速更糟。
- **emoji 替换的守卫前置**：此前先 `remove_action` 摘掉 WP 自带 emoji 处理再指向镜像，
  镜像失效时等于既拿掉核心行为又没有替代品，现改为镜像不可用则完全不接管。
- **后台加速在镜像不可用时不再无谓关闭脚本合并**（`$concatenate_scripts`），此前有损无益。
- **修复两处无开关控制的硬编码 CDN 依赖**：`framework/fields/map`（leaflet）与
  `framework/fields/code_editor`（codemirror）此前硬编码 `jsd.admincdn.com`，
  不受任何加速开关控制，镜像故障会直接让插件自己的设置界面失效。
  现经 `field_cdn_base()` 取址，不可用时回退到 `cdn.jsdmirror.com`
  （国内可达；`cdn.jsdelivr.net` 自 2021-12 ICP 吊销后国内基本不可用）。
- **修复文风字体接口失效**：旧默认字体与 `regular/bold/...` 子集参数已被当前接口移除，
  现在使用有效字体族与 `en/zh/zh-common/full` 字符集，并取消会强制触发 CORS 校验的属性。

## v3.9 - 2026-02-15

### 新增
- 集成文派云桥客户端 v2.1（站点健康上报 + 更新降级策略）
- 站点健康上报：每日自动上报站点环境信息到文派云桥，含 WooCommerce 扩展数据
- 多级降级策略：当云桥不可用时自动降级到 WordPress.org 原始源
- 站点唯一标识：UUID v4 格式，用于灰度发布分组
- 受「云桥更新」设置开关控制，可随时关闭


## v3.8 - 2025-02-05

* 文派叶子 v3.8 重大更新！全新UI 设计更接近 WordPress 原生体验。

1. 替换业务域名 WP-China-Yes.com 为新域名 WPCY.COM ；
2. 修复 adminCDN 支持 jsDelivr 加速无效等问题；
3. 新增 Bootstrap CDN 转接至 adminCDN 加速支持；
4. 新增 Windfonts 中文排版优化：支持段首空格 2em；
5. 新增 Windfonts 中文排版优化：支持文本内容对齐；
6. 新增 [脉云维护] 菜单并支持WP系统状态监控，可在页脚位置显示内存、CPU用量等信息；
7. 新增 [欢迎使用] 用户引导页面，更清晰的功能指导和简介。
8. 新增 [建站工具] 文派·寻鹿建站套件展示页面，内容待完善。
9. 优化 [萌芽加速] 设置，与 WordPress 程序端加速选项分离便于添加后续项目；
10. 优化 [关于插件] 页面更简约的赞助商 Logo 和贡献者名单展示。
11. 补充 changelog.txt 文本文件，跟随插件副本分发。
12. 补充 copyright.txt 版权文件，跟随插件副本分发。

## v3.7.1 - 2024-11-19

1. 性能优化
2. 修复监控无法关闭的问题

**Full Changelog**: https://github.com/WenPai-org/wp-china-yes/compare/v3.6.5...v3.7.1

## v3.6.5 - 2024-08-23

1. 优化 CLI 判断
2. 回退替换钩子修改

**Full Changelog**: https://github.com/WenPai-org/wp-china-yes/compare/v3.6.4...v3.6.5

## v3.6.4 - 2024-08-23

1. WP-CLI 下不运行 adminCDN 部分，防止影响缓冲区。
2. 部分文案调整支持多语言。

**Full Changelog**: https://github.com/WenPai-org/wp-china-yes/compare/v3.6.3...v3.6.4

## v3.6.3 - 2024-08-23

1. 为自动监控功能添加开关
2. adminCDN 支持 jsDelivr 加速
3. Windfonts 支持优化模式开关

**adminCDN 的 jsd 加速默认屏蔽 gh 端点，如有主题插件作者需要使用请联系加白。**
**Full Changelog**: https://github.com/WenPai-org/wp-china-yes/compare/v3.6.2...v3.6.3

## v3.6.2 - 2024-03-09

1. UI 重构
2. 修复市场加速导致 Beta 测试插件失效的问题
3. 更新接口及测试 URL

**Full Changelog**: https://github.com/WenPai-org/wp-china-yes/compare/v3.6.1...v3.6.2

## v3.6.1 - 2024-03-08

1. 重构全部代码
2. 应用市场调整为[文派开源](https://wenpai.org)
3. 加速服务调整为[萌芽加速](https://admincdn.com)
4. 头像调整为[初认头像](https://cravatar.com)
5. 支持自动检测节点可用性

**Full Changelog**: https://github.com/WenPai-org/wp-china-yes/compare/V3.5.5...v3.6.1
