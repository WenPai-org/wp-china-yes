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
