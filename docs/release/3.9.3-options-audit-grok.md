任务 393-AUDIT 是只读审计：先装依赖、读 `.grok-context/` 和设置定义，再逐项核对每个选项的读取处。依赖还在装。我先把 `Setting.php` 和各 Service 里的字段定义摸完。字段清单已经出来了。接下来对照各 Service 的真实读取处，并核对 `store`/`wenpai`/`proxy` 是否分叉。关键错位已经露出来了：`hide` 和 `hide_option` 不是同一个键，`Monitor` 也没被装载。接下来把每个字段的读取处扫完。`admincdn` 和界面勾选已经对不上。接下来用脚本把字段抽全，并用 `git log -S` 追引入版本。3.9.3 设置页里，用户能看见的选项大约一半没有对应运行逻辑：产品壳开关整组无读取，后台加速勾选写错键，通知/白标/RSS 的细项开关是摆设。下面是可直接拍板的表。

---

## 0. 范围与存储

- 运行时设置页只来自 `Service/Setting.php`（`Service/Base.php:43-45` 仅在 `is_admin()` 时装 `Setting` + `Adblock`）。
- `Plugin.php` 无 `createSection`。
- `Service/ModernSetting.php` 也会 `createSection`（store/admincdn/notice），**未被 Base 装载**，运行时不出现。
- 单站：`get_option('wp_china_yes')`；多站点：`get_site_option('wp_china_yes')`（`helpers.php:10-12`，`Setting.php:62` `database => network`）。**字段集合相同，没有网络专用选项。**
- CSF 保存是整表覆盖（`framework/classes/admin-options.class.php:214-327, 343-354`），未注册字段会从 option 里消失，再被 `get_settings()` 的 `wp_parse_args` 补默认；`admincdn` 另有「已有记录且缺键则置空」守卫（`helpers.php:96-98`）。

开工前依赖：`composer install --no-interaction` 与 `npm ci` 均 exit 0。本任务只读，未改文件。

---

## 1. 改了哪些文件

```
# git diff --stat
（空：无被跟踪文件改动）
```

工作树仅有预先存在的未跟踪目录 `dist-ci/`（本任务未写入）。

---

## 2. 总表（用户可见选项）

默认值以 **界面 `default`** 为准；与 `helpers.php` 不一致的在「判定」里注明。

| 选项 ID | section | 标题 | 类型 | 默认 | 读取处 | 判定 | 建议 |
|---|---|---|---|---|---|---|---|
| （无 id） | 欢迎使用 | 欢迎使用 | content | — | 仅展示 `templates/welcome-section.php` | 正常（无 option） | 保留 |
| store | 应用市场 | 应用市场 | radio | `wenpai` | `Service/Super.php:18`（只判断 `!= 'off'`） | 失效（`wenpai`/`proxy` 走同一镜像） | 合并为开/关；或恢复双后端 |
| bridge | 应用市场 | 云桥更新 | switcher | UI `false` / helpers `true` | `client/wenpai-bridge-client.php:28` | 正常（只控更新降级；默认值打架） | 保留；统一默认 |
| arkpress | 应用市场 | 联合存储库 | switcher | `false` | 无 | 无用（无读取） | 删 |
| admincdn_public | 萌芽加速 | 萌芽加速 | checkbox | `googlefonts` 开，其余空 | `Acceleration.php:85-87,334-337` | 正常 | 保留 |
| admincdn_files | 萌芽加速 | 文件加速 | checkbox | `admin`,`emoji` | emoji/sworg：`Acceleration.php:103-105,378-384`；**`admin` 子项无读取** | 失效（「后台加速」勾选不驱动替换） | `admin` 合并到 `admincdn`；保留 emoji/sworg |
| admincdn_dev | 萌芽加速 | 开发加速 | checkbox | `jquery` | `Acceleration.php:94-96,354-356` | 正常 | 保留 |
| admincdn_version_enable | 萌芽加速 | 版本控制 | switcher | `false` | `Acceleration.php:465` | 正常 | 保留 |
| admincdn_version | 萌芽加速 | 版本控制选项 | checkbox | `css`,`js`,`timestamp` | `Acceleration.php:469-480,526-538` | 正常 | 保留 |
| cravatar | 初认头像 | 初认头像 | radio | `cn` | `Avatar.php:27-32,42-51,81-114` | 正常 | 保留 |
| windfonts | 文风字体 | 文风字体 | radio | `off` | `Fonts.php:27-44` | 正常 | 保留 |
| windfonts_list | 文风字体 | 字体列表 | group | 一条默认字体 | `Fonts.php:79`；`Migration.php:106-120` | 正常 | 保留 |
| windfonts_list.family | 文风字体 | 字体名称 | text | `wenfeng-hcszt` | `Fonts.php:83-93,123` | 正常 | 保留 |
| windfonts_list.subset | 文风字体 | 字体子集 | select | `full` | `Fonts.php:125-128` | 正常 | 保留 |
| windfonts_list.lang | 文风字体 | 语言设置 | select | （字段无 default） | `Fonts.php:131-133` | 正常 | 保留 |
| windfonts_list.weight | 文风字体 | 字体字重 | number | `400` | `Fonts.php:109` | 正常 | 保留 |
| windfonts_list.style | 文风字体 | 字体样式 | select | `normal` | `Fonts.php:108` | 正常 | 保留 |
| windfonts_list.selector | 文风字体 | 字体应用 | textarea | 一长串选择器 | `Fonts.php:107` | 正常 | 保留 |
| windfonts_list.enable | 文风字体 | 启用字体 | switcher | `true` | `Fonts.php:80` | 正常 | 保留 |
| windfonts_typography_cn | 文风字体 | 中文排印 | checkbox | `''` | `Fonts.php:160-228`（corner/space/punctuation/indent/align 均读） | 正常 | 保留 |
| windfonts_typography_en | 文风字体 | 英文排印 | checkbox | `''` | `Fonts.php:236-274`（optimize/spacing/orphan/widow 均读） | 正常 | 保留 |
| windfonts_reading_enable | 文风字体 | RTL镜像测试 | switcher | `false` | `Fonts.php:53` | 正常 | 保留 |
| windfonts_reading | 文风字体 | RTL镜像模式 | radio | `off` | `Fonts.php:57-60` | 正常 | 保留 |
| motucloud | 墨图云集 | 墨图云集 | radio | `cn` | 无 | 无用（无读取） | 删 |
| fewmail | 飞秒邮箱 | 飞秒邮箱 | radio | `cn` | 无（`Service/Mail.php` 是 0 字节且未装载） | 无用（无读取） | 删 |
| comments_enable | 无言会语 | 评论增强 | switcher | `false` | `Comments.php:24` | 正常 | 保留 |
| comments_role_badge | 无言会语 | 角色徽章 | switcher | `true` | `Comments.php:38` | 正常 | 保留 |
| comments_remove_website | 无言会语 | 移除网站字段 | switcher | `false` | `Comments.php:48` | 正常 | 保留 |
| comments_validation | 无言会语 | 评论验证 | switcher | `true` | `Comments.php:56` | 正常 | 保留 |
| comments_herp_derp | 无言会语 | 阿巴阿巴模式 | switcher | `false` | `Comments.php:66` | 正常 | 保留 |
| comments_sticky_moderate | 无言会语 | 置顶审核 | switcher | `false` | `Comments.php:76` | 正常 | 保留 |
| bisheng | 笔笙区块 | 笔笙区块 | radio | `cn` | 无 | 无用（无读取） | 删 |
| deerlogin | 灯鹿用户 | 灯鹿用户 | radio | `cn` | 无 | 无用（无读取） | 删 |
| waimao_enable | 跨飞外贸 | 跨飞外贸 | switcher | `false` | `Language.php:20,75,226,241,256` | 正常 | 保留 |
| waimao_language_split | 跨飞外贸 | 前后台语言分离 | switcher | `false` | `Language.php:24,76,227,242,257` | 正常 | 保留 |
| waimao_admin_language | 跨飞外贸 | 后台语言 | select | 当前用户 locale | `Language.php:81-84,115` | 正常 | 保留 |
| waimao_frontend_language | 跨飞外贸 | 前台语言 | select | `WPLANG` | `Language.php:88-90,130` | 正常 | 保留 |
| waimao_auto_detect | 跨飞外贸 | 自动语言检测 | switcher | `false` | `Language.php:42,123` | 正常 | 保留 |
| woocn | Woo电商 | Woo电商 | radio | `cn` | 无 | 无用（无读取） | 删 |
| lelms | 乐尔达思 | 乐尔达思 | radio | `cn` | 无 | 无用（无读取） | 删 |
| wapuu | 瓦普文创 | 瓦普文创 | radio | `cn` | 无 | 无用（无读取） | 删 |
| adblock | 广告拦截 | 广告拦截 | radio | `off` | `Adblock.php:27` | 正常 | 保留 |
| adblock_rule | 广告拦截 | 规则列表 | group | — | `Adblock.php:37` | 正常 | 保留 |
| adblock_rule.name | 广告拦截 | 规则名称 | text | `默认规则` | 无（仅手风琴标题） | 无用（无读取） | 保留作标签 |
| adblock_rule.selector | 广告拦截 | 应用元素 | textarea | `.wpseo_content_wrapper` | `Adblock.php:38-42` | 正常 | 保留 |
| adblock_rule.enable | 广告拦截 | 启用规则 | switcher | `true` | `Adblock.php:38` | 正常 | 保留 |
| notice_block | 通知管理 | 通知管理 | radio | `off` | `Super.php:24,34-39`（开则 CSS 藏全部 notice） | 正常（实现比 UI 粗） | 保留；实现与文案对齐 |
| disable_all_notices | 通知管理 | 禁用所有通知 | switcher | `false` | 无 | 无用（无读取） | 删（已由 notice_block 完成） |
| notice_control | 通知管理 | 选择性禁用 | checkbox | `[]` | 无 | 无用（无读取） | 删 |
| notice_method | 通知管理 | 禁用方式 | radio | `hook` | 无（实际永远 CSS） | 无用（无读取） | 删 |
| plane | 飞行模式 | 飞行模式 | radio | `off` | `Super.php:29` | 正常 | 保留 |
| plane_rule | 飞行模式 | 规则列表 | group | — | `Super.php:45` | 正常 | 保留 |
| plane_rule.name | 飞行模式 | 规则名称 | text | `未命名规则` | 无 | 无用（无读取） | 保留作标签 |
| plane_rule.domain | 飞行模式 | URL | textarea | `''` | `Super.php:46`（兼读旧键 `url`） | 正常 | 保留 |
| plane_rule.enable | 飞行模式 | 启用规则 | switcher | `true` | `Super.php:47` | 正常 | 保留 |
| memory | 系统信息 | 系统监控 | switcher | `false` | `Memory.php:25,169` | 正常 | 保留 |
| memory_display | 系统信息 | 显示参数 | checkbox | memory_usage, wp_limit, server_ip, php_info | `Memory.php:164-240`；**hostname 仅在勾了 server_ip 时拼进去** | 正常（hostname 条件依附） | 保留 |
| disk | 系统信息 | 站点监控 | switcher | `false` | `Maintenance.php:22` | 正常 | 保留 |
| disk_display | 系统信息 | 显示参数 | checkbox | disk_usage, disk_limit, media_num, admin_num | `Maintenance.php:52-111` | 正常 | 保留 |
| maintenance_mode | 系统信息 | 启用维护模式 | switcher | `false` | `Maintenance.php:16` | 正常 | 保留 |
| maintenance_settings | 系统信息 | 维护模式设置 | fieldset | — | `Maintenance.php:158-163` | 正常 | 保留 |
| maintenance_settings.maintenance_title | 系统信息 | 页面标题 | text | `网站维护中` | `Maintenance.php:161` | 正常 | 保留 |
| maintenance_settings.maintenance_heading | 系统信息 | 主标题 | text | `网站维护中` | `Maintenance.php:162` | 正常 | 保留 |
| maintenance_settings.maintenance_message | 系统信息 | 维护说明 | textarea | 例行维护文案 | `Maintenance.php:163` | 正常 | 保留 |
| yoodefender | 雨滴安全 | 雨滴安全 | radio | `cn` | 无 | 无用（无读取） | 删 |
| disallow_file_edit | 雨滴安全 | 禁用文件编辑 | switcher | `true` | `Database.php:71-72` | 正常（默认 true，打开本 tab 再保存会关编辑器） | 保留；默认改 false |
| disallow_file_mods | 雨滴安全 | 禁用文件修改 | switcher | `false` | `Database.php:74-75` | 正常 | 保留 |
| performance | 性能优化 | 性能优化 | switcher | `false` | `Performance.php:19`（只闸本文件钩子） | 正常（不管内存常量） | 保留；应同时闸下面四项 |
| wp_memory_limit | 性能优化 | 内存限制 | text | UI `40M` / helpers `256M` | `wp-china-yes.php:36-38`（**不看 performance**） | 失效（主开关关了仍可能改常量） | 合并到 performance 闸门 |
| wp_max_memory_limit | 性能优化 | 后台内存限制 | text | UI `256M` / helpers `512M` | `wp-china-yes.php:40-41` | 失效（同上） | 同上 |
| wp_post_revisions | 性能优化 | 文章修订版本 | number | UI `-1` / helpers `5` | `wp-china-yes.php:43-44` | 失效（同上） | 同上 |
| autosave_interval | 性能优化 | 自动保存间隔 | number | UI `60` / helpers `300` | `wp-china-yes.php:46-47` | 失效（同上） | 同上 |
| custom_name | 品牌白标 | 品牌白标 | text | UI `文派叶子` / helpers `WP-China-Yes` | `Setting.php:47-49,58,1503` | 正常（默认值打架） | 保留；统一默认 |
| header_logo | 品牌白标 | 品牌 Logo | media | 插件 logo | `admin-options.class.php:507` | 正常 | 保留 |
| hide_option | 品牌白标 | 隐藏设置 | switcher | `false` | 无 PHP 读取（只做 CSF 依赖） | 失效（不写 `hide`，菜单不会藏） | 合并到 `hide`/`hide_menu` |
| hide_elements | 品牌白标 | 隐藏元素 | checkbox | `[]` | `admin-options.class.php:505-515`（logo/title/version）；**`hide_copyright` 不读** | 部分失效 | 删 hide_copyright 或接到页脚 |
| hide_menu_confirm | 品牌白标 | 隐藏菜单确认 | checkbox | `[]` | 无 | 无用（无读取） | 删或接到 hide_menu |
| hide_menu | 品牌白标 | 隐藏菜单 | switcher | `false` | 无 | 无用（无读取） | 合并到 `hide` |
| enable_custom_rss | 品牌白标 | 品牌新闻 | switcher | `false` | 无（Widget 未装载） | 失效 | 删或装回 Widget |
| custom_rss_url | 品牌白标 | 自定义 RSS 源 | text | `https://one.weixiaoduo.com/feed` | 仅 `Widget.php:44`（未装载） | 失效 | 同上 |
| custom_rss_refresh | 品牌白标 | RSS 刷新频率 | select | UI `14400` / helpers `3600` | 仅 `Widget.php:45` | 失效 | 同上 |
| rss_display_options | 品牌白标 | RSS 显示选项 | checkbox | 三项全开 | 仅 `Widget.php:47` | 失效 | 同上 |
| enable_sections | 其他设置 | 功能模块管理 | switcher | `true` | 仅 CSF dependency（`Setting.php:1307,1323`） | 正常（只藏勾选 UI） | 保留 |
| quick_select | 其他设置 | 快速选择 | button_set | `custom` | 仅同页 JS（`Setting.php:1375-1383`），PHP 不读入库值 | 无用（无运行时读取） | 不入库，或删 |
| enabled_sections | 其他设置 | 功能选项卡 | checkbox | UI 含 wordyeah；helpers 无 wordyeah | `Setting.php:66` | 部分失效（8 个值无对应 section；`other` 勾了也不闸「其他设置」） | 砍幽灵值；给「其他设置」加闸 |
| （无 id） | 建站套件 | 建站套件 | content | — | `templates/website-section.php` | 正常（无 option） | 保留或改外链页 |
| docs | 帮助文档 | 帮助文档 | radio | `cn` | 无 | 无用（无读取） | 删（改 content 链文档） |
| （无 id） | 关于插件 | 关于插件 | content | — | `templates/about-section.php` | 正常（无 option） | 保留 |
| （无 id） | 备份选项 | 备份与恢复操作 | backup | — | CSF 导入导出（无独立 option 键） | 正常 | 保留 |

**无界面、但 `wp_china_yes` / helpers 里存在的键（必须进拍板）：**

| 选项 ID | section | 标题 | 类型 | 默认 | 读取处 | 判定 | 建议 |
|---|---|---|---|---|---|---|---|
| admincdn | （无界面） | （旧「后台加速」） | array | helpers `['admin']`；已有记录缺键则 `[]` | `Acceleration.php:46,77-78,285` | 失效（界面勾的是 `admincdn_files.admin`） | 与界面勾选合并；迁移见 §5 |
| hide | （无界面） | （菜单隐藏） | bool | `false` | `Setting.php:48` → CSF `menu_hidden` | 失效（界面写 `hide_option`/`hide_menu`） | 由 hide_menu 映射过来 |
| monitor | （无界面） | （旧节点监控） | bool | `false` | 仅未装载的 `Monitor.php:23` | 无用（无读取） | 直接丢弃 |
| wordyeah | （无界面） | （旧产品壳） | string | `off` | 无（section 闸门用的是 `enabled_sections` 里的 `wordyeah`） | 无用（无读取） | 直接丢弃 |
| waimao | （无界面） | — | helpers 有键 | `off` | 无（真正用的是 `waimao_enable`） | 无用（无读取） | 直接丢弃 |

顶层带 `id` 的界面字段 **70** 个（功能审计写 71，对不上）。另 4 个无 id 展示块、5 个幽灵键。

---

## 3. 无用 / 重复 / 失效：证据

### 3.1 产品壳整组无读取（复制 Gravata r 文案）

`motucloud` / `fewmail` / `bisheng` / `deerlogin` / `woocn` / `lelms` / `wapuu` / `yoodefender` / `docs`：全仓除 `Setting.php` 定义和 `helpers.php` 默认外，无 `$settings['…']` 读取。`woocn`、`docs` 的 subtitle/desc 直接复制灯鹿（`Setting.php:687-689,1429-1431`）。`arkpress` 同：无读取，desc 还声称「自动监控加速节点」（`Setting.php:113-118`），而 Monitor 已从 Base 卸掉。

引入：`64748fe 2025-07-29 Add new service modules and enhance initialization`（arkpress 同提交）。

### 3.2 「后台加速」勾选与运行时脱节（最伤）

- 界面：`admincdn_files` 的 `admin`（`Setting.php:152-168`）。
- 运行时后台静态改写：读 **`admincdn`**（`Acceleration.php:77-78,285`）。
- `admincdn_files` 只用于 `emoji` / `sworg`（`Acceleration.php:103-105,378-384`）。
- 3.8 界面字段还叫 `admincdn`（`5370b07`）；`64748fe` 改成 `admincdn_files` 后，Acceleration 未改读键。
- CSF 保存不写 `admincdn` → `helpers.php:96-98` 在「已有 option 且缺该键」时把 `admincdn` 置 `[]` → **保存任意设置后，后台加速实际关掉**，与勾选无关。

3.9.3 声称「后台加速覆盖 wp-includes / 镜像不可用时不再关脚本合并」（`CHANGELOG.md:38,58`），修的是镜像守卫，**没修键错位**。

### 3.3 store：`wenpai` 与 `proxy` 同一后端

`Super.php:18` 只判断 `!= 'off'`；`filter_wordpress_org` 一律改写到 `api.wenpai.net` / `downloads.wenpai.net`（`Super.php:102-110,157-159`）。界面写「文派开源」vs「官方镜像（WPMirror）」（`Setting.php:96-102`）是假分叉。

`Monitor.php:66-74` 曾区分二者并改写用户设置，但 `96574d4`（3.9.3）已从 `Base.php` 去掉 Monitor。引入双值：`e1a6083 2024-03-08 refactor: 全局重构`。

### 3.4 通知细项是摆设

`Super.php:24,34-39`：`notice_block == on` 时输出 CSS 隐藏全部 `.notice/.update-nag/.error`。`disable_all_notices` / `notice_control` / `notice_method` 无读取。引入：`64748fe`。

### 3.5 白标隐藏三键互不相通

- 菜单隐藏读 `hide`（`Setting.php:48`），界面没有这个字段。
- 界面 `hide_option`、`hide_menu`、`hide_menu_confirm` 无 PHP 读取。
- `hide_elements` 的 logo/title/version 在 `admin-options.class.php:505-515` 生效；`hide_copyright` 无读取，页脚版权写死（`:589-592`）。
- `hide` 引入 `2391c18 2024-09-23 feat: v3.7.0`；`hide_option`/`hide_menu` 引入 `64748fe`；`hide_menu_confirm` 引入 `f431a75 2025-09-27`。

### 3.6 品牌新闻：读取代码未挂载

`Widget.php:44-47` 读 RSS 三键，但 `git log -G Widget -- Service/Base.php` 为空——**从未装进 Base**。`enable_custom_rss` 连 Widget 都不读。引入：`64748fe`（Widget 文件本身 `f431a75`）。

### 3.7 性能主开关不管内存常量

`Performance.php:19` 闸的是去 generator/xmlrpc 等。`wp_memory_limit` 等四项由 `wp-china-yes.php:36-47` 在 option 非空时直接 `define`，不看 `performance`。CSF 只要「性能优化」section 被创建，保存就会写入这四项。界面默认与 helpers 默认还不一致（40M vs 256M 等）。

### 3.8 `enabled_sections` 幽灵值 + 「其他设置」无闸

勾选有、无 `createSection`：`forums` 赛博论坛、`forms` 重力表单、`panel` 天控面板、`domain` 蛋叮域名、`sms` 竹莺短信、`chat` 点洽客服、`translate` 文脉翻译、`ecosystem` 生态系统（`Setting.php:1344-1354` vs 26 个 createSection）。

「其他设置」「备份选项」无 `in_array` 闸（`Setting.php:1290,1456`）。勾 `other` 不改变「其他设置」是否出现。备份块被夹在 about 的 if 后面，始终显示。

`quick_select` 只在设置页 JS 里改勾选（`Setting.php:1369-1383`），入库值无运行时读者。引入：`f431a75`。

### 3.9 同一 section / 同一标题 / 死说明

- **运行时同一 section 不会出现两次**（ModernSetting 未装载）。源码里 ModernSetting 会再声明应用市场/萌芽加速/通知管理，若将来误装就会双份。
- **同一标题两处：** section 名与字段名大量相同（应用市场、萌芽加速、初认头像、文风字体及 9 个产品壳、广告拦截、通知管理、飞行模式、雨滴安全、性能优化、品牌白标、帮助文档）。`显示参数` 用于 `memory_display` 与 `disk_display`。`规则列表`/`规则名称`/`启用规则` 用于广告拦截与飞行模式。`是否启用灯鹿用户` 出现在灯鹿、Woo电商、帮助文档。`admincdn_dev` subtitle 写成「是否启用文件加速」（`Setting.php:192`，复制自文件加速）。
- **说明指向不存在的功能：** 9 个产品壳 desc 是 Gravata r/Cravatar 套话并链 `gravatar-alternatives`；arkpress 宣传已停用的节点监控；`enable_custom_rss` 写「文派茶馆」但 Widget 未挂；`hide_option` 声称隐藏插件但菜单键是 `hide`；`plane_rule` desc 链的是广告拦截文档（`Setting.php:903`）；monitor section 标题「系统信息」，`enabled_sections` 标签却是「脉云维护」（`Setting.php:948 vs 1343`）。

### 3.10 3.9.3 与这些选项的关系（声称 vs 实际）

| 3.9.3 说法 | 实际 |
|---|---|
| 移除前台加速 `frontend` | 属实：UI 已无；`Migration.php:68-89` 摘除 |
| 飞行模式 `url`/`domain` 不一致已修 | 属实：`Super.php:46` 兼读 |
| 停用旧节点 Monitor | 属实卸载（`96574d4` 从 Base 删 `Monitor`）；**未删 arkpress UI/文案** |
| 后台加速镜像不可用时不关 concatenate | 属实（`Acceleration.php:290-296`）；**未修 admincdn 键错位** |
| 云桥开关只控更新降级、报告常开 | 属实（`wenpai-bridge-client.php:22-30`）；UI 仍写「是否启用更新加速」 |
| 文风字体 API 失效已修 | 属实（`Fonts.php:119-128`） |
| 性能/页脚对新安装默认关 | helpers 里 `performance`/`memory` 确为 false |
| （未声称）清无用选项 | **没做**，正是撤回原因 |

---

## 4. 3.9.3 changelog / 审计文档与代码对不上

1. **后台加速：** changelog 当它可用；界面 `admincdn_files.admin` 不驱动 `Acceleration` 读的 `admincdn`。
2. **arkpress / 「自动监控」：** Monitor 已停，设置项和文案还在。
3. **`docs/3.9.3-functional-audit.md:45`：**「无言会语……没有对应本地 Service」——`Comments.php` 完整实现 `comments_*`。
4. **同文件「保存后 option 含 71 个字段」：** 带 id 顶层字段 70；差 1，未在代码里找到第 71 个界面 id。
5. **同文件「遥测默认关闭」：** 已被后续提交反转（changelog 3.9.3「报告常开」）；审计正文未改。
6. **`CHANGELOG.md` v3.9 段仍写「受云桥更新开关控制，可随时关闭」上报：** 与 3.9.3 行为相反（历史段未改）。
7. **bridge 默认：** UI `false`（`Setting.php:107`），helpers `true`（`helpers.php:18`）。未保存时降级策略开着，打开设置页看到的是关。
8. **`store` 两个选项同一后端：** 3.9.3 写了镜像回源（这部分属实），没写双选项是假的。
9. **Widget/文派茶馆：** 3.9.3 未提；设置项在、服务从未挂载。
10. **`custom_name` / 内存四项界面默认 vs helpers 默认不一致：** 未处理。

---

## 5. 若删这些选项：`wp_china_yes` 键迁移

| 键 | 建议 |
|---|---|
| arkpress, motucloud, fewmail, bisheng, deerlogin, woocn, lelms, wapuu, yoodefender, docs, wordyeah, monitor, waimao（非 waimao_enable） | **直接丢弃** |
| disable_all_notices, notice_control, notice_method | **直接丢弃**（行为已由 notice_block 覆盖） |
| hide_option, hide_menu, hide_menu_confirm | **合并值**：任一为真 → 写入 `hide=true`；然后丢弃三键 |
| hide | 保留为菜单隐藏真源，或改名为 hide_menu 并改 `Setting.php:48` |
| hide_elements.hide_copyright | **直接丢弃**（从未生效） |
| enable_custom_rss, custom_rss_url, custom_rss_refresh, rss_display_options | **直接丢弃**（除非先装回 Widget） |
| quick_select | **直接丢弃** |
| enabled_sections 中 forums/forms/panel/domain/sms/chat/translate/ecosystem | **从数组里丢掉** |
| store=`proxy` | **映射** `store=wenpai`（现网等价）；`off` 保持 |
| admincdn_files 含 `admin` | **合并**：写入 `admincdn=['admin']`，并从 admincdn_files 去掉 `admin`；emoji/sworg 留在 admincdn_files |
| 已有 option 无 admincdn 键 | 不要再走 `helpers.php:96-98` 的置空；按 admincdn_files 重建 |
| wp_memory_limit 等四项 | **保留值**；应用时必须 `performance==true` |
| comments_* / waimao_* / cravatar / windfonts* / adblock* / plane* / memory* / disk* / maintenance_* / custom_name / header_logo / hide_elements（logo/title/version） / enabled_sections（有效值） / bridge / store | **保留** |
| disallow_file_edit=`true` 且用户从未进过雨滴安全 | 保持现状即可；若该 section 被默认藏，option 里通常没有此键，不会误关编辑器 |

---

## 6. 每条验收标准：命令与输出摘要

**A. 设置定义位置**

```
rg -n "createSection" --glob '*.php'
```

- `Service/Setting.php`：26 次（欢迎…备份）。
- `Service/ModernSetting.php`：3 次（未装载）。
- `framework/classes/setup.class.php:298`：API 定义。
- `Plugin.php`：0。

**B. 字段清单（不许漏）**

```
python3 扫描 Setting.php 的 id/title/type/default
```

输出：`TOTAL_FIELDS 85`（含 group/fieldset 子字段）；顶层 id 70；createSection 26（welcome/store/admincdn/cravatar/windfonts/motucloud/fewmail/wordyeah/blocks/deerlogin/waimao/woocn/lelms/wapuu/adblock/notice/plane/monitor/security/performance/brand/其他设置 ungated/deer/docs/about/备份 ungated）。

**C. 读取处**

```
rg "settings\['KEY'\]" Service/ client/ helpers.php wp-china-yes.php framework/classes/admin-options.class.php
```

与总表「读取处」列一致。Base 装载列表见 `Service/Base.php:21-46`（无 Monitor/Widget/ModernSetting/Mail）。

**D. 重复 section / 标题 / 死文案**

见 §3.9。运行时无双 section；源码 ModernSetting 是隐患。

**E. git log -S（无用/重复引入，一行）**

```
arkpress|motucloud|fewmail|bisheng|deerlogin|woocn|lelms|wapuu|yoodefender|docs|hide_option|hide_menu|hide_elements|enable_custom_rss|notice_*|admincdn_files|enabled_sections
→ 64748fe 2025-07-29 Add new service modules and enhance initialization
quick_select|hide_menu_confirm|Widget.php → f431a75 2025-09-27 Refactor and extend service architecture
monitor 键 → 4a7c871 2024-08-23 feat: bump version 3.6.3
hide 键 → 2391c18 2024-09-23 feat: v3.7.0
store proxy → e1a6083 2024-03-08 refactor: 全局重构
Monitor 从 Base 卸掉 → 96574d4 2026-08-09 fix: prepare wp-china-yes 3.9.3 stability release
admincdn 作为界面 id 最后出现 → 5370b07 2025-01-02 升级至 3.8 版本（其后 64748fe 改名为 admincdn_files）
Widget 从未进入 Base.php
```

**F. 多站点 vs 单站**

```
rg -n "is_multisite|get_site_option|database" helpers.php Service/Setting.php Plugin.php
```

同一 prefix `wp_china_yes`，多站点走 site_option + network 菜单能力；无额外字段。

---

## 7. 提交哈希

```
# git log --oneline -5
3ea2fb2 docs(release): record 3.9.3 withdrawal (channel back to 3.8, release marked pre-release)
3f6eb1f docs(release): 3.9.3 release record
cc41906 release: 3.9.3
02ddd91 chore(release): 3.9.3 release date
873a289 chore(release): 3.9.3 预检阻断修复（changelog、发布排除、版权年）
```

任务书写 master 在 `3f6eb1f`；当前 HEAD 是其上一条文档提交 `3ea2fb2`（撤回记录），产品代码与 `3f6eb1f` 相同。

---

## 8. 没做 / 做不到 / 有疑问

1. **未改文件、未提交、未 push**（任务要求只读）。
2. **未跑 WordPress 后台点开 26 个 tab 的浏览器核对**；重复标题/死文案以源码为准。功能审计里「展开 26 个 section」与代码 26 个 createSection 数量相符，但其中 2 个无闸、8 个 enabled_sections 值根本建不出 section。
3. **未探测 motucloud.com / fewmail.com 等域名是否还活着**（禁止联网）。判定「无用」只根据本仓无读取，不根据外站死活。
4. **`punctuation` 在中文排印与英文排印各有一份**，存在不同数组里，不算重复键；英文排印的 `punctuation` 在 `Fonts.php` 后半是否还有读取：中文五子项都有分支；英文看到 optimize/spacing/orphan/widow，**`windfonts_typography_en` 的 `punctuation`（中英标点）在已读的 `Fonts.php:236-274` 没有对应 `in_array('punctuation')`**。标 **失效（有 UI 子项无分支）**。建议删该子项或补 CSS。此项在总表 `windfonts_typography_en` 行应视为「四子项正常、punctuation 失效」。
5. **Media.php / Performance.php / Database.php 还读一批完全没有界面的键**（`optimize_images`、`lazy_load`、`webp_support`、`jpeg_quality`、`responsive_images`、`disable_embeds`、`defer_scripts`、`disable_admin_bar`、`force_ssl_admin`、`force_ssl_login`、`enable_debug`）。`Database::handle_debug_constants()` 自身也未被构造函数调用。这些不是用户可见选项，未进总表；它们让对应 Service 行为不可配置。
6. **`wp-china-yes.php:49` 读 `empty_trash_days`，无界面、无 helpers 默认。**
7. **hostname 勾选**依附 `server_ip`（`Memory.php:203-208`），单独勾无效。未拆成独立行。
8. **英文排印 `punctuation`、CSF 保存后 `admincdn` 被置空** 未在真实 WP 里点保存验证；逻辑来自 `set_options` 整表覆盖 + `helpers.php:96-98`。不确定线上 3.8 升级站是否仍在 option 里残留 `admincdn` 键（发版记录里 updates.weixiaoduo.com 的 option 样本含 `"admincdn":["admin"]"` 且无 `admincdn_files`——那种站后台加速仍走旧键；一旦打开 3.9.3 设置页点保存，旧键会丢）。
9. **`enabled_sections` 界面 default 含 `wordyeah`，helpers 不含**：新装未保存时无「无言会语」tab；重置该 section 会把它加回来。
10. **任务要求「3.9.3 是否声称处理了它」：** 绝大多数无用壳选项 3.9.3 **从未声称要处理**；声称处理过的是前台加速、飞行模式字段、Monitor 停用、镜像回源、字体 API。对不上的已列在 §4。

以上总表可直接拍：删哪些壳、把 `admincdn` 与界面勾选接上、藏菜单三键并成一个、RSS 要么装回要么删。
