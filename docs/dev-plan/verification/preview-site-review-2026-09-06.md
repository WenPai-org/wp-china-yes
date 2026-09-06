# 预览站走查（Grok，2026-09-06）

> 站点：Studio wpcy-40-preview，localhost:8890。§4 的九条是规格与实现的差距清单，归入 M-UI 产品化阶段的功能缺口；第 7（恢复页 title null Deprecated）与第 8（版本号）为引擎侧小修，随 M4-03 处理。

先核对站点现状和 Studio CLI 用法，再按步骤配 4.0 预览站。Studio CLI 还在跑，先读配置和设计文档，等 status 回来再动手。站点在线，`WPCY_KERNEL` 已是 `v4`。接着查插件、语言和站点名。上一步已把常量、插件、语言和站名配好。接下来跑 `wp wpcy status` 并核对 HTTP。内核已是 v4，诊断目标全 `ok`。接着核 URL、REST 和模块列表。REST 命名空间已挂上。接着取站点在线：WP 7.1、PHP 8.2。接下来生成�预览站已经能打开：http://localhost:8890 ，后台左侧有「文派叶子」四个子页，内核是 v4。上一步中断前，常量、�登录成功，站点已是中文。接着逐页打后台并看 debug.log。默认 REST 已确认：`/apps` 是空列表且 `index_status=ok`。接下来写 3.8 option、跑迁移，并灌演示数据。sh status --format json` | 在线。`http://localhost:8890/`，PHP 8.2，WP 7.1，runtime 原生 |
| 常量 | 读 `wp-config.php` + `wp config get WPCY_KERNEL` | 已是 `v4`，未再 `config set` |
| 插件 | `wp plugin list --status=active` | `wp-china-yes` 已启用（插件头版本仍是 3.9.3） |
| 语言 | `wp option get WPLANG` + `wp-content/languages/zh_CN.mo` | 已是 `zh_CN`，核心语言包已在，未再 `language core install` |
| 站名 | `wp option get blogname` | 已是 `WPCY 4.0 预览站`，未再 `option update` |
| 内核 | `wp wpcy status` | `kernel=v4`，`recovery_mode=false`，7 个探测目标全 `ok` |
| 模块 | `wp eval` → `Plugin::create()->registry()->ids()` | 14 个模块都在（见下） |
| URL | `wp option get siteurl` | `http://localhost:8890` |
| HTTP | `curl` `/wp-admin/` | **302** → `wp-login.php`（正常） |
| REST | `/?rest_route=/wpcy/v1/status` | **404**（规格里没有这个公开端点） |
| REST | `/?rest_route=/wpcy/v1/binding/challenge` | **400** 缺 `id`（路由在，公开只读） |
| REST 索引 | `/?rest_route=/wpcy/v1` | 命名空间已挂，见下方端点列表 |
| 后台页 | Studio 自动登录后抓 HTML | 四页 React 根节点 + `build/index.js` / `style-index.css` 200；恢复页 PHP 直出 |

未改插件源码，未 git push，未装系统依赖。

`wp wpcy status` 本身不打印模块表。用 `Plugin::create()->registry()->ids()` 得到：

`connectivity.wordpress_org`, `connectivity.public_assets`, `connectivity.avatar`, `modules.windfonts`, `telemetry`, `privacy.data_residency`, `diagnostics`, `services.site_binding`, `services.apps`, `rest`, `admin`, `services.entitlements`, `admin.notice_control`, `admin.announcements`

与 `src/Core/Plugin.php:86` 的 `create()` 注册清单一致。

## 2. 站点与登录

- **前台**：http://localhost:8890
- **后台**：http://localhost:8890/wp-admin/
- **用户 / 密码**：`admin` / `wpcy-preview`（未改密码）
- Studio 自动登录：http://localhost:8890/studio-auto-login?redirect_to=%2Fwp-admin%2F
- 语言：`zh_CN`（登录页、仪表盘标题均为中文）
- 插件列表显示名：`WPCY.COM`，版本 **3.9.3**（4.0 发版前头文件未改）

`wp wpcy status` 原文：

```json
{"kernel":"v4","recovery_mode":false,"targets":[
  {"target":"api.wenpai.net","result":"ok","latency_ms":229,"checked_at":"2026-09-06T03:57:43Z","suggestion":null},
  {"target":"downloads.wenpai.net","result":"ok","latency_ms":207,"checked_at":"2026-09-06T03:57:43Z","suggestion":null},
  {"target":"cdnjs.admincdn.com","result":"ok","latency_ms":238,"checked_at":"2026-09-06T03:57:43Z","suggestion":null},
  {"target":"jsd.admincdn.com","result":"ok","latency_ms":215,"checked_at":"2026-09-06T03:57:43Z","suggestion":null},
  {"target":"googleajax.admincdn.com","result":"ok","latency_ms":198,"checked_at":"2026-09-06T03:57:43Z","suggestion":null},
  {"target":"googlefonts.admincdn.com","result":"ok","latency_ms":179,"checked_at":"2026-09-06T03:57:43Z","suggestion":null},
  {"target":"cn.cravatar.com","result":"ok","latency_ms":136,"checked_at":"2026-09-06T03:57:43Z","suggestion":null}
]}
```

登录后 REST 实测：`GET /wp-json/wpcy/v1/settings` 200（默认配置、`recovery_mode:false`）；`/diagnostics` 200（同上探测）；`/binding` `{"status":"unbound",...}`；`/entitlements` `{"entitlements":[]}`；`/apps` `{"apps":[],"index_status":"ok"}`；`/announcements` `{"generated_at":null,"items":[]}`。

## 3. 看什么

浏览器打开后台后，左侧菜单 **文派叶子**（位置约第 80 项，仪表盘/文章之后）。

| 菜单/入口 | URL | 模块 | 现状（真数据/占位/依赖未上线） | 看点 |
|-----------|-----|------|--------------------------------|------|
| 概览 | `/wp-admin/admin.php?page=wpcy` | `admin` + 诊断/公告 | **真数据**：线路三卡来自已跑过的诊断；公告无缓存则整段不渲染（正常）。绑定卡写死「未绑定」 | 三张卡应绿点/「N 项已加速」；无「需要处理」、无公告。空态「立即检查」只在从未诊断时出现，本站已有结果所以顶栏没有该按钮 |
| 连接优化 | `?page=wpcy-connect` | `connectivity.*` + `modules.windfonts` | **真数据可改可存**（`PUT /settings`）。字体族列表未做 | 四组：WP.org 自动/关闭；公共库五项勾选+节点圆点；头像 Cravatar 中国/国际/WeAvatar/关闭；Windfonts 开关。保存未改时禁用 |
| 文派服务 | `?page=wpcy-services` | `services.site_binding` / `entitlements` / `apps` | **未绑定真状态**；绑定/权益/小工具索引 **依赖未上线**（license-server、apps.wpcy.com） | 应看到「绑定本站」。权益/小工具均为「绑定后显示」。点绑定会失败（见 §4），不是环境坏了 |
| 诊断 | `?page=wpcy-diagnose` | `diagnostics` + `admin.notice_control` | **连接检查真数据**；隐藏通知真端点、现为空；出站主机 **占位空表**；数据与恢复 **只做了恢复入口** | Tab：连接检查 / 被隐藏的通知 / 出站主机记录 / 数据与恢复。顶栏「立即检查」会再探一次（要出网） |
| 恢复模式（菜单里没有） | `?page=wpcy-recovery` | `rest` → `RecoveryPage` | **真页面**，PHP 直出、不加载 React | 从诊断页「进入恢复模式」点进去。两个按钮：关闭全部 URL 改写、停用全部模块。现在不在恢复模式，没有「退出」 |
| 命令面板 | 四个 React 页内 ⌘K | `admin` / `commands.js` | 已注册 5 条命令 | 「打开文派叶子概览 / 连接优化 / 文派服务 / 运行连接诊断 / 进入恢复模式」 |
| 站点健康 | `/wp-admin/site-health.php?tab=debug` | `diagnostics` SiteHealth | **真数据**（debug_information 段 `wp-china-yes`） | 「信息」页里应有连接探测摘要 |
| 插件列表 | `/wp-admin/plugins.php` | — | 已启用 | 名称 WPCY.COM，版本 3.9.3，可停用 |

依赖未上线（开发计划 M3，默认不连生产）：

- 站点绑定：未定义 `WPCY_SERVICES_API`，客户端禁止出站（`ChallengeClient.php:129`）。点「绑定本站」会 503。
- 小工具索引：默认 `wpcy_apps_index_source` 为空，**不拉** `https://apps.wpcy.com/index.json`（`AppsModule.php:64`）。因此 `index_status` 是 `"ok"` + 空列表，**不会**出现「小工具目录暂时不可用」。
- 公告：生产聚合 `https://wpcy.com/wp-json/wpcy/v1/announcements` 不是默认源；无缓存则概览不画公告段。

## 4. 发现的 UI / 功能问题（不修）

1. **概览「文派服务」卡写死未绑定**  
   `Overview.js:454` 不读 `/binding`，绑上之后这张卡也不会变。

2. **点「绑定本站」会失败，文案还是英文**  
   `ChallengeClient::unavailable()`：`Site binding is not available.`（503 `wpcy_binding_unavailable`）。规格词表要中文「暂时无法连接文派服务」。

3. **小工具不会出现「索引不可达」**  
   空 source 被当成 `index_status=ok`（`Index.php:140`）。未绑定时空态是「绑定后显示」，不是琥珀条「小工具目录暂时不可用」。

4. **诊断「出站主机记录」是空表占位**  
   `Diagnose.js:272` 用 `EmptyTable`，不请求数据。规格里的 `GET /residency/log`、`/residency/ruleset` **未注册**（REST 索引里没有这两条）。

5. **诊断「数据与恢复」缺两项**  
   规格要「导出诊断报告」和「按小工具导出/删除数据」。实现只有「进入恢复模式」按钮（`Diagnose.js:322`）。

6. **连接优化 Windfonts**  
   规格：未绑定应禁用并提示配额；开启后出字体族 DataViews。现在开关始终可点，没有字体列表；说明「绑定后可用配额」一直挂着。

7. **恢复页 `<title>` 空，PHP 8.2 Deprecated**  
   `add_submenu_page( null, … )` 后 `admin-header.php:41` `strip_tags( null )`。页面 h1 正常，浏览器标题变成 `‹ WPCY 4.0 预览站`。日志：

```
[06-Sep-2026 04:07:20 UTC] PHP Deprecated:  strip_tags(): Passing null to parameter #1 ($string) of type string is deprecated in .../wp-admin/admin-header.php on line 41
```

8. **插件版本号仍是 3.9.3**  
   插件头与 `CHINA_YES_VERSION`、bootstrap `pluginVersion` 都是 3.9.3。后台跑的是 v4 内核，列表上看不出 4.0。

9. **规格有、实现没有的 REST**  
   `/wpcy/v1/status` 不存在（任务里写的探测路径，404 符合当前代码）。已挂路由：settings、network-settings、diagnostics、diagnostics/run、recovery、binding、binding/challenge、binding/start、entitlements、announcements、apps*、notice-control/hidden。

恢复页关键 HTML（正常应看到的）：

```html
<h1>文派叶子 · 恢复模式</h1>
<p>如果后台样式错乱或站点无法访问，可在此一键停用所有 URL 改写与模块。此页不依赖 JavaScript。</p>
<button type="submit" class="button button-primary">关闭全部 URL 改写</button>
<button type="submit" class="button button-secondary">停用全部模块</button>
<a href="http://localhost:8890/wp-admin/admin.php?page=wpcy">返回概览
```

概览挂载点：

```html
<div class="wrap wpcy-admin-wrap"><div id="wpcy-admin-root"></div></div>
<script src="http://localhost:8890/wp-content/plugins/wp-china-yes/build/index.js?ver=30de2764f28c4eecd26e">
```

## 5. 没做 / 做不到 / 有疑问

- 未再执行 `wp config set` / `plugin activate` / `language core install` / `option update blogname`：检查后都已是目标值。
- 未改密码、未新建用户。
- 未改插件源码；上面 UI 问题都只记不修。
- 未连生产 license-server / apps.wpcy.com / 公告聚合（M3 默认不启用）。
- 没有浏览器截图（只用 curl 抓 HTML）。React 四页内容在 `#wpcy-admin-root` 里由 JS 画，curl 看不到卡面文字；卡面状态按 REST + 源码推断。
- `debug.log` 里另有一条 Fatal，是本次误跑 `ModuleRegistry::all()` 的 WP-CLI eval，不是站点运行时错误。
- Studio `fileAccess` 是「站点目录」不是 `all-files`。插件是 rsync 进站内的，不是 symlink，所以能加载。
- 有疑问：任务预期小工具页会显示「索引不可达」；当前实现空索引 = `ok`，负责人会看到「绑定后显示」而不是那条琥珀提示。


---

# 第二次走查（同日，含演示数据灌入：3.8 option 迁移、演示绑定态、mock 小工具）

> 注意：预览站里的"已绑定"是直接写入 wpcy_site_identity 的演示数据，不是真绑定；权益表为空（无生产 license-server）。新增缺口：空索引源被记为 ok（永不出"不可达"）；迁移报告只在 CLI/option，诊断页看不到。

先核对站点现状和 Studio CLI 用法，再按步骤配 4.0 预览站。Studio CLI 还在跑，先读配置和设计文档，等 status 回来再动手。站点在线，`WPCY_KERNEL` 已是 `v4`。接着查插件、语言和站点名。上一步已把常量、插件、语言和站名配好。接下来跑 `wp wpcy status` 并核对 HTTP。内核已是 v4，诊断目标全 `ok`。接着核 URL、REST 和模块列表。REST 命名空间已挂上。接着取站点在线：WP 7.1、PHP 8.2。接下来生成�预览站已经能打开：http://localhost:8890 ，后台左侧有「文派叶子」四个子页，内核是 v4。上一步中断前，常量、�登录成功，站点已是中文。接着逐页打后台并看 debug.log。默认 REST 已确认：`/apps` 是空列表且 `index_status=ok`。接下来写 3.8 option、跑迁移，并灌演示数据。预览站已经能打开看：浏览器进 `http://localhost:8890/wp-admin/`，账号 `admin` / `wpcy-preview`。左侧菜单「文派叶子」就是 4.0 后台。

## 1. 站点事实

| 项 | 值 |
|---|---|
| URL | `http://localhost:8890/` |
| 后台 | `http://localhost:8890/wp-admin/` |
| 免登（Studio） | `http://localhost:8890/studio-auto-login?redirect_to=%2Fwp-admin%2F` |
| 账号 / 密码 | `admin` / `wpcy-preview` |
| 站名 | `WPCY 4.0 预览站` |
| 语言 | `zh_CN`（页面 `lang="zh-Hans"`） |
| WordPress | 7.1 |
| PHP | 8.2（Studio 原生运行时） |
| `WPCY_KERNEL` | `v4`（`wp-config.php`） |
| 插件列表显示版本 | **3.9.3**（包头 `Version` / `CHINA_YES_VERSION` 仍是 3.9.3，内核已是 v4） |
| `wp wpcy status` | `{"kernel":"v4","recovery_mode":false,...}` 七条镜像探测均为 `ok` |

激活、中文包、站名这三项原先就已经是目标状态，本次又写了一遍，结果是 already active / already installed / unchanged。

## 2. 导览表

登录后看左侧 **文派叶子**（概览 / 连接优化 / 文派服务 / 诊断）。3.x 的 `options-general.php?page=wp-china-yes` 在 v4 下 **403**，不要走那条。

| 看什么 | 打开哪个 URL | 期望看到 | 本次实测 |
|---|---|---|---|
| 概览 | http://localhost:8890/wp-admin/admin.php?page=wpcy | React 壳 `#wpcy-admin-root`；WordPress.org / 公共库卡片；公告最多 5 条 | **200**，mount 点在。公告已灌 5 条（文派茶馆 / 薇晓朵）。探测数据来自 REST `/diagnostics`，七条均为 `ok` |
| 连接优化 · WordPress.org 源 | http://localhost:8890/wp-admin/admin.php?page=wpcy-connect | 单选：自动 / 关闭 | **200**。迁移后当前是 **关闭**（3.8 `store=off` → `wordpress_org=off`） |
| 连接优化 · 公共前端库 | 同上 | Google Fonts / Ajax / CDNJS / jsDelivr / Emoji 勾选 + 状态点 | **200**。五项全开。旁路状态点读上次诊断 |
| 连接优化 · 头像 | 同上 | Cravatar 中国 / 国际 / WeAvatar / 关闭 | **200**。当前 **Cravatar 中国** |
| 连接优化 · Windfonts | 同上，分组「字体」 | 开关，说明「绑定后可用配额」 | **200**。开关 **开**（3.8 `windfonts=optimize`）。字体列表空（fixture 的 `windfonts_list` 为空） |
| 连通性 / 镜像健康 | 概览卡片，或诊断默认 tab | 国内镜像正常 / 已回原始上游 / 不可用 | REST `/diagnostics` 七条 `ok`，延迟 136–238 ms。点诊断页「立即检查」会再跑一遍 |
| 诊断 · 连接检查 | http://localhost:8890/wp-admin/admin.php?page=wpcy-diagnose | 目标 / 结果 / 延迟 / 最近检查表 | **200**。默认 tab `connect` |
| 诊断 · 被隐藏的通知 | 同上 `#/tab=notices` | 表；空态「暂无被隐藏的通知。」；说明核心/安全/站点健康永不隐藏 | **200**。已灌 2 行演示：`woocommerce / woo-connect-promo ×12`、`acme-seo / acme-dashboard-banner ×3` |
| 诊断 · 出站主机记录 | 同上 `#/tab=hosts` | 「主机表由文派发布，用户不可编辑」+ 空表「暂无出站主机记录。」 | **200**。此 tab 是空表占位，没有真实主机数据 |
| 诊断 · 数据与恢复 | 同上 `#/tab=data` | 「进入恢复模式」按钮 | **200**。只有跳转恢复页的按钮，**没有**迁移报告 UI |
| Site Health | http://localhost:8890/wp-admin/site-health.php?tab=debug | 信息页手风琴「文派叶子」 | **200**。区块在，字段为 `api.wenpai.net` 等七条 `ok · N ms · 时间`。复制调试信息里键名被去掉点（`apiwenpainet`） |
| 通知控制 | 诊断 `#/tab=notices`；设置项在 `modules.notice_control` | 无独立菜单。规则来自远端 JSON，本包 `PRODUCTION_URL` 为空 | 模块默认关生产拉取。演示日志是本地 `wpcy_notice_control_log`。3.8 迁移曾把该项写成 `false`（`adblock=off`），为让该 tab 的 REST 还在，已 PUT 回 `true` |
| 公告 | 概览页下部 | 最多 5 条，可关闭 | REST `/announcements` **200**，5 条。第 6 条 fixture 按设计被裁掉。生产源默认空，不拉 `wpcy.com` |
| 服务与绑定 | http://localhost:8890/wp-admin/admin.php?page=wpcy-services | 站点绑定卡片 + 权益配额表 | **200**。绑定被写成本地演示 `bound`（hash `previewlocalwpcy40hash0001`），**不是**文派账号真绑定。`/entitlements` 200 且列表空（没有许可证服务器） |
| 小工具容器 | 文派服务页「小工具」 | 未绑定：「绑定后显示」。索引失败：琥珀 Notice「小工具目录暂时不可用」（文案不是「索引不可达」） | 默认：`{"apps":[],"index_status":"ok"}`（生产索引未启用，**不会**出琥珀条）。缓存写成 `unreachable` 且列表非空时，REST 返回 `index_status=unreachable`。现已挂本地 mock「站点体检」，`index_status=ok`。点卡片应出 iframe 沙箱，源 `http://localhost:8890/wp-content/uploads/wpcy-mock-app/index.html`（静态 **200**） |
| 迁移报告 | CLI；设置页可对照迁移后的值 | `wp wpcy migrate --dry-run` / 真跑 JSON：kept / ignored / reasons | dry-run 与 execute 均成功。kept：`store, cravatar, windfonts, adblock`。备份 option `wpcy_migration_backup`：`from_version=3.x`，`migrated_at=2026-09-06T04:16:05Z`。诊断页看不到这份报告 |
| 恢复页 | http://localhost:8890/wp-admin/?page=wpcy-recovery 或 `admin.php?page=wpcy-recovery` | 无 JS；「关闭全部 URL 改写」「停用全部模块」；链回概览 | 两 URL 都 **200**，h1「文派叶子 · 恢复模式」。**浏览器标题为空**（`<title> ‹ WPCY 4.0 预览站`） |
| WP-CLI 子命令 | 站点目录下 `studio-cli.sh wp wpcy --help` | `status` / `doctor` / `config` / `migrate` | 实测有：`status`、`doctor`、`config export\|import`、`migrate [--dry-run] [--rollback]`。`wp wpcy config` 还列出 `export_document` / `import_document`（类的 public 方法漏进 CLI） |

REST 命名空间 `GET http://localhost:8890/?rest_route=/wpcy/v1` **200**，带 `X-WPCY-Request-Id`。带 cookie + `X-WP-Nonce` 抽查：

- `GET /wpcy/v1/settings` 200
- `GET /wpcy/v1/diagnostics` 200
- `GET /wpcy/v1/binding` 200
- 另外 `/apps`、`/announcements`、`/entitlements`、`/notice-control/hidden` 在模块启用时也是 200

## 3. 实测问题（未改插件）

后台四个 React 页、恢复页、Site Health **没有 500 / 白屏 / PHP Fatal**。`WP_DEBUG` + `WP_DEBUG_LOG` 已开，`WP_DEBUG_DISPLAY` 仍为 false，浏览器里看不到这些 notice。

**恢复页空标题 + Deprecated**（每次打开恢复页写一条）：

```
PHP Deprecated:  strip_tags(): Passing null to parameter #1 ($string) of type string is deprecated in .../wp-admin/admin-header.php on line 41
```

对应 HTML：`<title> &lsaquo; WPCY 4.0 预览站 — WordPress</title>`。`add_submenu_page( null, ...)` 的隐藏页拿不到 admin title。

**翻译加载过早**（WP-CLI / 部分请求，WP 6.7+ doing_it_wrong）：

```
PHP Notice:  Function _load_textdomain_just_in_time was called incorrectly. Translation loading for the wp-china-yes domain was triggered too early. ... (This message was added in version 6.7.0.) in .../wp-includes/functions.php on line 6260
```

**debug.log 里有一条 CLI eval Fatal**（不是后台页面，堆栈是 `eval()'d code`）：

```
PHP Fatal error:  Uncaught Error: Call to undefined method WenPai\ChinaYes\Core\ModuleRegistry::all() in phar://.../Eval_Command.php(39) : eval()'d code:1
```

时间 2026-09-06 04:05:20 UTC。本次对 admin.php / REST 的访问没有再打出这条。

**其它产品可见不一致：**

- 插件页写「3.9.3 版本」，React bootstrap 的 `pluginVersion` 也是 `3.9.3`，内核已是 v4。
- Site Health「复制站点信息」里 WPCY 字段键被去掉点：`apiwenpainet` 而不是 `api.wenpai.net`。手风琴表格本身显示正常。
- 3.x 设置 URL `options-general.php?page=wp-china-yes` → **403**（v4 不注册 CSF 菜单）。

## 4. 没做 / 做不到 / 有疑问

- **生产小工具索引默认不拉。** `AppsModule` 里 `wpcy_apps_index_source` 默认空字符串，所以空目录时 `index_status` 是 `ok` 而不是 `unreachable`，页面不会出琥珀条。文案实际是「小工具目录暂时不可用」。
- **空列表无法把 `unreachable` 保住。** `Index::apps()` 把空缓存当 miss，空 source 的 `refresh()` 会把状态写回 `ok`。要让 REST 返回 `unreachable`，缓存里必须至少有一条 app。有疑问：这是设计还是漏了。
- **真绑定做不到。** `/binding/start` 要打文派 challenge。当前 `bound` 是写入 `wpcy_site_identity` 的演示数据。点「解除绑定」会清掉；再点绑定可能失败并出「暂时无法连接文派服务」。
- **权益表是空的。** `/entitlements` 200、`entitlements: []`。没有许可证服务。
- **mock 沙箱是 transient 注入，不是验签索引。** 文件在 `wp-content/uploads/wpcy-mock-app/`。签名校验只允许 `apps.wpcy.com` 一类 HTTPS origin，本地 `http://localhost` 过不了 `ManifestVerifier::origin_allowed`。iframe 能否在浏览器里完整走完 bridge（ready → init → data.*）未在有头浏览器里点过，只确认了 HTML 200 且 REST 已返回该 app。
- **迁移报告不在诊断页。** 只在 CLI JSON 和 option `wpcy_migration_backup`。诊断「数据与恢复」只有进恢复模式。
- **公告 / 通知规则生产 URL 默认不拉。** 概览公告、通知日志是本地 fixture/option。
- **`wp wpcy config` 多出 `export_document` / `import_document`。** 有疑问：是不是不该暴露的内部方法。
- 未跑 `wp wpcy doctor`（会再打一遍外网探测；`status` 里已有最近一次结果）。
- 未点恢复页上的两个 POST 按钮（会关掉改写/模块，挡展示）。
- 未改插件源码，未 push，未动其它 Studio 站。
�推断。
- `debug.log` 里另有一条 Fatal，是本次误跑 `ModuleRegistry::all()` 的 WP-CLI eval，不是站点运行时错误。
- Studio `fileAccess` 是「站点目录」不是 `all-files`。插件是 rsync 进站内的，不是 symlink，所以能加载。
- 有疑问：任务预期小工具页会显示「索引不可达」；当前实现空索引 = `ok`，负责人会看到「绑定后显示」而不是那条琥珀提示。
