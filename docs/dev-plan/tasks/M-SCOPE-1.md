# 任务 M-SCOPE-1：站点场景与作用域引擎（Schema v2 / Connectivity 门控 / Profile / REST / 迁移 D3）

## 解决什么问题

4.0 连通性对所有请求一视同仁，跨境站会把国内公共库改写打到海外访客。本任务把 [ADR-004](../../architecture/adr-004-site-profile-and-scope.md) 写进 Schema、Repository、三模块、迁移与 REST，不改 React。

## 不做什么

见下方「禁区」。不改 `src/Admin/app/`；不实现 `admin_assets` 改写；不自动切换场景；不改决定文件；不为连通性建配额。`privacy.data_residency` 已拍板：方案 A + 保险，本任务实现闸。

## 目标

`schema_version` 升到 2；连通性三模块按 scope 门控；Profile 默认矩阵与切换重置；`GET /profile/suggest`；撤销 M4-02b 的 `admin→ignored`；telemetry payload 加 `profile`；Heartbeat 分屏节流；挡仪表盘外部内容；`GET/POST /diagnostics/client-probe`；A 档改道按 `profile` 闸。单元 + 现有 e2e 不红。

## 背景与上下文文件

- [`docs/dev-plan/decisions/2026-09-06-site-profile-and-scope.md`](../decisions/2026-09-06-site-profile-and-scope.md)（决定原文，不许改）
- [`docs/architecture/adr-004-site-profile-and-scope.md`](../../architecture/adr-004-site-profile-and-scope.md)
- [`docs/specs/config-schema.md`](../../specs/config-schema.md)（v2、`upgrade_1_to_2`、D2 矩阵、D3 映射、heartbeat / dashboard_feeds）
- [`docs/specs/rest-api.md`](../../specs/rest-api.md)（`/settings` 字段、`GET /profile/suggest`、`GET/POST /diagnostics/client-probe`）
- [`docs/specs/data-residency-ruleset.md`](../../specs/data-residency-ruleset.md) §9 方案 A + 保险
- [`docs/design/admin-ui-spec.md`](../../design/admin-ui-spec.md) §4 词表（迁移报告那一行；无「配额」连通性文案）
- [`docs/dev/task-book-template.md`](../../dev/task-book-template.md)
- [`docs/dev-plan/verification/m4-upgrade-matrix-2026-09-06.md`](../verification/m4-upgrade-matrix-2026-09-06.md) 末尾「待补用例（D3）」
- 现有实现：`src/Config/{Schema,Defaults,Repository,Validator}.php`（`Schema::VERSION` 现为 1）、`src/Core/Scope.php`（站点 vs 网络，**禁止改写**）、`src/Core/Environment.php`、`src/Connectivity/{PublicAssets,Avatar,WordPressOrg}/`、`src/Migration/Mappers.php`（`admincdn` 整键 `unsupported_whitelist`，token `admin` 进 ignored）、`src/Telemetry/Report.php`、`src/Rest/RestModule.php`
- `.grok-context/` 若存在可读；测试不得读它，样本进 `tests/fixtures/` 并提交。

依赖：**M-UI-0 已合入 `main`**。本任务基于合入后的 `main` 开 worktree。M-UI-0 未合不得开工，停下来在报告写明。

工作分支 `grok/m-scope-1`。预计 diff ≤ 1500 行。

## 商业前提

连通性全部免费、无配额、无套餐。此前「基线 / 受限免费 / 付费」三层作废；`Services/Entitlements` 里不再为连通性功能建配额，只承载第三方/文派服务（Windfonts、wei-*、小工具）的权益。

叶子是接入客户端与分发渠道，不卖功能。商业价值 = 识别跨境 WooCommerce 店（`profile` + telemetry 的 Woo 字段）→ 在其后台经小工具容器分发文派付费产品（wei-* 支付/登录/通知/发票、微小朵服务）→ `/go/` 成交。小工具容器首批内容是这些入口。

商业内容露出条件由服务端规则下发（场景为 `crossborder`/`mixed` 且检测到 WooCommerce），与通知规则、公告同一下发机制；`domestic` 场景几乎不露出。插件内不写死露出条件。本任务不实现露出规则客户端，只保证连通性路径不读 entitlement 配额。

叶子按场景与作用域决定接哪个源，不管额度。插件内不出现配额、「配额用尽降级到上游」逻辑；`Services/Entitlements` / `Degrade` 只服务于小工具与服务分发链。Windfonts 是否需要绑定由 Windfonts 平台决定，插件按服务端应答呈现。

`admin_assets` 不规划商业化：4.1 作为免费体验项交付。

## 跨境场景免费体验层

调研 F2/F3/F6/F10/F11 中属于引擎侧的四项。字体与头像后台作用域即 D2 本身（见「三模块门控」）。其余三项如下。UI 呈现进 [M-SCOPE-UI](M-SCOPE-UI.md)，本任务不改 `src/Admin/app/`。

### Heartbeat 分屏节流

设置键（写死）：`wpcy_settings.connectivity.heartbeat`，枚举 `on` \| `off`。

- `on`：仪表盘关闭 Heartbeat；编辑器间隔 60 秒。
- `off`：不挂钩，WordPress 默认。
- 默认：`domestic` = `off`；`crossborder` / `mixed` = `on`。

Filter / 钩子（写死）：

| 名 | 类型 | 行为 |
|----|------|------|
| `wpcy_heartbeat_throttle` | 插件 filter | `apply_filters( 'wpcy_heartbeat_throttle', bool $enabled ): bool`。默认 `$enabled` = 设置键为 `on`。测试注入用。 |
| `admin_enqueue_scripts` | WP action | `$hook_suffix === 'index.php'` 时 `wp_deregister_script( 'heartbeat' )` |
| `heartbeat_settings` | WP filter | 当前屏为编辑器（`$hook_suffix` 为 `post.php` / `post-new.php`，含区块编辑器）时设 `$settings['interval'] = 60` |

实现：`src/Connectivity/Heartbeat/HeartbeatModule.php`。`enabled()` 在恢复模式下为 false。不改前台 Heartbeat。

### 挡仪表盘外部内容

设置键（写死）：`wpcy_settings.connectivity.dashboard_feeds`，枚举 `block` \| `allow`。

- `block`：去掉 WP 新闻/事件 widget，短路核心 dashboard feed 请求。
- `allow`：不挂钩。
- 默认：`domestic` = `allow`；`crossborder` / `mixed` = `block`。
- 按主机表 C 档处理：不挡支付/物流；本项只针对下列目标，不得把其它出站主机加入黑名单。

Filter / 钩子（写死）：

| 名 | 类型 | 行为 |
|----|------|------|
| `wpcy_block_dashboard_feeds` | 插件 filter | `apply_filters( 'wpcy_block_dashboard_feeds', bool $block ): bool`。默认 `$block` = 设置键为 `block`。 |
| `wp_dashboard_setup` | WP action | `remove_meta_box( 'dashboard_primary', 'dashboard', 'side' )` 与 `'normal'` |
| `dashboard_primary_feed` | WP filter | `__return_empty_string` |
| `dashboard_secondary_feed` | WP filter | `__return_empty_string` |
| `pre_http_request` | WP filter | 仅当 URL 主机为 `api.wordpress.org` 且 path 以 `/events/` 开头时短路为 `WP_Error( 'wpcy_dashboard_feed_blocked' )`。其它 `api.wordpress.org` 路径不碰（交给 `WordPressOrgModule`）。 |

实现：`src/Connectivity/DashboardFeeds/DashboardFeedsModule.php`。`enabled()` 在恢复模式下为 false。

### 连接诊断 REST（浏览器测速）

端点（写死）：

- `POST /wpcy/v1/diagnostics/client-probe`
- `GET  /wpcy/v1/diagnostics/client-probe`

权限同 `/diagnostics`（`Permissions::manage_options_read` 读；写用 `manage_options`，与 `POST /diagnostics/run` 相同）。

POST 接收管理员浏览器对允许名单目标的耗时结果。服务端**只存最近一次摘要**，覆盖写，不追加历史。GET 读取该摘要。形状见 [`rest-api.md`](../../specs/rest-api.md) `/diagnostics/client-probe`。

允许名单（写死）：

1. `https://fonts.googleapis.com/css2?family=Roboto:wght@400`
2. `https://secure.gravatar.com/avatar/00000000000000000000000000000000?d=404`
3. 若 `diagnostics.client_probe_url` 非空且为 `https:` URL，其主机加入允许名单

POST body 每条 `url` 的主机必须落在允许名单，否则整单 `wpcy_invalid_schema` 400，不写存储。不在服务端代发这些 URL（避免 SSRF）；只存浏览器上报的 `latency_ms` / `result`。

存储 option（写死）：`wpcy_diagnostics_client_probe`，`autoload=no`，**不是** `wpcy_settings` 的字段。实现：`src/Rest/ClientProbeController.php`（或挂在现有 `DiagnosticsController`），由 `RestModule` 注册。

## 交付物

| 路径 | 职责 |
|------|------|
| `src/Config/Schema.php` | `VERSION = 2`；`profile` 枚举；`public_assets` 改为 `{items,scope}`；`avatar` 改为 `{admin,frontend}`；顶层 `admin_assets` `on\|off`；`connectivity.heartbeat` `on\|off`；`connectivity.dashboard_feeds` `block\|allow`；`diagnostics.client_probe_url`；`site_overrides` 允许 `profile` / `admin_assets` |
| `src/Config/Defaults.php` | domestic 默认，与 config-schema「默认值汇总」逐键一致 |
| `src/Config/Repository.php`（或新建 `src/Config/SchemaMigrator.php` 由 Repository 调用） | 读取 settings / network / overrides 时若 `schema_version < 2` 执行 `upgrade_1_to_2` 并写回，幂等 |
| `src/Config/Profile.php` | 默认矩阵（照抄 D2）；`apply_defaults( string $profile ): array`；切换重置只动矩阵内的连通性键，不动 notice / announcements / diagnostics / recovery / telemetry / data_residency |
| `src/Config/ProfileSuggest.php` | D4 规则；geo 走 `api.wenpai.net`，路径与应答格式待 wenpai-net 侧提供，插件先按 `{ "country": "CN" }` 契约实现并可 mock |
| `src/Connectivity/Scope.php` | `WenPai\ChinaYes\Connectivity\Scope::current(): string` 返回 `'admin'` 或 `'frontend'`。**禁止修改** `src/Core/Scope.php` |
| `src/Connectivity/Heartbeat/HeartbeatModule.php` | `heartbeat=on` 时按上表挂钩；恢复模式不挂钩 |
| `src/Connectivity/DashboardFeeds/DashboardFeedsModule.php` | `dashboard_feeds=block` 时按上表挂钩；恢复模式不挂钩 |
| `src/Connectivity/PublicAssets/PublicAssetsModule.php` | 读 `items` + `scope`；`scope=off` 或不匹配当前请求 → 不改写 |
| `src/Connectivity/Avatar/AvatarModule.php` | 读当前侧的独立值；该侧 `off` → 不改写（保留 Gravatar） |
| `src/Connectivity/WordPressOrg/WordPressOrgModule.php` | 无 admin/frontend 门控；只尊重 `auto` / `off`（crossborder 默认 `off` 由 Profile 写入） |
| `src/Privacy/DataResidency/DataResidencyModule.php` | A 档 `reroute_enabled` 额外要求有效 `profile === 'domestic'`（方案 A + 保险：不看 geo，只看 profile） |
| `src/Migration/Mappers.php` | D3：`admin` token → `admin_assets=on`，不再进 ignored；`frontend` / `bootstrapcdn` 仍 ignored；直接写出 v2 |
| `src/Migration/Report.php` | `admin_assets=on` 时报告文案「后台加速：已保留设置，4.1 起生效」（`__()`，text domain `wp-china-yes`） |
| `src/Rest/ProfileSuggestController.php` + `src/Rest/RestModule.php` | `GET /wpcy/v1/profile/suggest`，权限同 settings |
| `src/Rest/ClientProbeController.php`（或 `DiagnosticsController` 增方法）+ `src/Rest/RestModule.php` | `GET/POST /wpcy/v1/diagnostics/client-probe` |
| `src/Telemetry/Report.php` | `collect()` 增加 `profile`（有效 settings 的值；缺省 `domestic`） |
| `src/Integrations/Windfonts/WindfontsModule.php` | 不改 scope（前台功能）；Profile 切换写入 `modules.windfonts=false` 即可。不读连通性配额（无此配额） |
| `src/Core/Plugin.php` | `create()` 注册 Heartbeat、DashboardFeeds 模块（仅此两项新增；不改其它注册顺序语义） |
| `tests/Unit/Config/SchemaVersion2Test.php` | `upgrade_1_to_2`：数组→对象、单值 avatar→双值、缺 profile→domestic、缺 admin_assets→off、幂等 |
| `tests/Unit/Config/ProfileTest.php` | 三场景矩阵逐格；切换重置；不受影响的键保持 |
| `tests/Unit/Connectivity/ScopeTest.php` | D2 判定：`is_admin` / admin-ajax / REST+nonce 后台 / 前台 REST / WP-CLI / WP-Cron→frontend（cron 先于 `is_admin`） |
| `tests/Unit/Connectivity/Heartbeat/HeartbeatModuleTest.php` | `on`：dashboard 注销 heartbeat、编辑器 interval=60；`off`：不挂钩；filter `wpcy_heartbeat_throttle` 可覆盖 |
| `tests/Unit/Connectivity/DashboardFeeds/DashboardFeedsModuleTest.php` | `block`：去掉 `dashboard_primary`、feed filter 空串、`/events/` 短路；`allow`：不挂钩；支付主机 URL 不短路 |
| `tests/Unit/Privacy/ProfileGateTest.php`（或扩 `RecordIgnoreTest`） | domestic + ingest_ready → A 改道；crossborder / mixed 即使 ingest_ready 也不改道；B 仍 record；domestic 不读 geo |
| `tests/Unit/Rest/ClientProbeTest.php` | POST 合法摘要覆盖写；非法主机 400 不写；GET 返回最近一次；空存储 `{checked_at:null,probes:[]}` |
| `tests/Unit/Config/ProfileSuggestTest.php` | D4 四类：境内→domestic；境外+管理员境内→crossborder；其它→null；geo 失败→null；不返回 IP |
| `tests/Unit/Migration/FixturesTest.php` | 更新 S2 等；新增 3.9 `admincdn_files` 含 `admin` 的 fixture |
| `tests/fixtures/legacy-options/single-3.9-08-admincdn-files-admin.json` | 由 `single-3.9.3-03.json` 复制，仅把 `admincdn_files` 写成 `["admin"]`（或 `["admin","googlefonts"]`）；外层 `_fixture` 注明用途 |
| 现有连通性 / telemetry / REST / Validator / Repository 单测 | 跟着 schema 形状改断言，不得删场景 |
| `docs/dev-plan/verification/m4-upgrade-matrix-2026-09-06.md` | 补跑「待补用例（D3）」后把状态从「由 M-SCOPE-1 补跑」改成命令输出（本任务做；未跑 Studio 则保持该标并在报告写明） |

不改 `src/Admin/app/`。不改决定文件。

## 行为规格

对照 [ADR-004](../../architecture/adr-004-site-profile-and-scope.md)、[`config-schema.md`](../../specs/config-schema.md) D2/D3、[`rest-api.md`](../../specs/rest-api.md) `/profile/suggest` 与 `/diagnostics/client-probe`、[`data-residency-ruleset.md`](../../specs/data-residency-ruleset.md) §9。冲突时以决定原文为准，停下来写进报告，不要改矩阵的值。

### Schema / Repository

- `Schema::VERSION === 2`。identity / migration_backup 的 schema_version **仍为 1**。
- `upgrade_1_to_2` 规则照 config-schema 表，逐行测（含缺 `heartbeat` → `off`、缺 `dashboard_feeds` → `allow`、缺 `client_probe_url` → `""`）。
- 3.x mapper 直接写 v2，不先写 v1。
- `Defaults::settings()` 的 `profile` / `public_assets` / `avatar` / `admin_assets` 等于 domestic 列。

### `Connectivity\Scope::current()`

返回 `'admin'` 或 `'frontend'`。判定照决定 D2 原文，加上统筹拍板 cron 规则：

> `is_admin()`（含 `admin-ajax` / REST 带 `X-WP-Nonce` 的后台请求视为 admin；前台 REST 视为 frontend；WP-CLI 视为 admin）。
>
> cron 的作用域：`WP-CLI` 视为 `admin`；`WP-Cron` 视为 `frontend`（保守：不做任何仅后台的改写；服务器侧取 .org 的行为不受作用域影响）。

写死实现（顺序不可改；cron 必须先于 `is_admin()`，以免后台页面触发的 cron 被当成 admin）：

1. `defined('WP_CLI') && WP_CLI` → `admin`
2. `( defined('DOING_CRON') && DOING_CRON )` 或 `function_exists('wp_doing_cron') && wp_doing_cron()` → `frontend`
3. `function_exists('is_admin') && is_admin()`（含 `admin-ajax`，`DOING_AJAX` 时 `is_admin()` 为 true）→ `admin`
4. REST（`REST_REQUEST` 或 `wp_doing_rest()`）：请求带 `X-WP-Nonce` 且 `wp_verify_nonce( $nonce, 'wp_rest' )` 为真，并且 `wp_get_referer()` 含 `/wp-admin` → `admin`；其余 REST → `frontend`
5. 其它 → `frontend`

`wordpress_org` 不读 `Scope::current()`（服务器侧）。

**禁止**给 `src/Core/Scope.php` 加 `current()`（那是站点 vs 网络）。

### 三模块门控

- **PublicAssets**：`items` 为空或 `scope=off` → `enabled()===false`。`scope=admin` 且 `Scope::current()==='frontend'` → 不改写。`scope=frontend` 且 current 为 admin → 不改写。`scope=both` → 两侧都改写。`items` 仍只改白名单。
- **Avatar**：`get_cravatar_url` 读 `connectivity.avatar.{admin|frontend}`（按 `Scope::current()` 选键）。该侧为 `off` → 返回原 URL（Gravatar 保留）。`weavatar` 仍有效。
- **WordPressOrg**：不读 scope。`wordpress_org=off` 时模块不改源（已有行为）。

`admin_assets`：任何模块都不得因它为 `on` 而改写 URL。4.0 无运行时行为。

Heartbeat / DashboardFeeds 见「跨境场景免费体验层」；不走 `Scope::current()`（只作用于 wp-admin 仪表盘/编辑器，前台不挂钩）。

### DataResidency 方案 A + 保险

`DataResidencyModule::reroute_enabled()` 在现有 `ingest_ready` / `always` 判断之外，增加：有效 settings 的 `profile !== 'domestic'` 则 A 档不改道（返回 false）。B 档 `record` 与 C 档 `ignore` 不读 `profile`。不查询 geo。`profile=domestic` 即使出站 IP 在海外也改道（保险：用户自称国内站以用户为准）。

### Profile

`apply_defaults( 'domestic'|'crossborder'|'mixed' )` 写入（照抄 D2，不得改值）：

| 路径 | `domestic` | `crossborder` | `mixed` |
|------|------------|---------------|---------|
| `connectivity.wordpress_org` | `auto` | `off` | `auto` |
| `connectivity.public_assets.items` | 五项 | 五项 | 五项 |
| `connectivity.public_assets.scope` | `both` | `admin` | `admin` |
| `connectivity.avatar.admin` | `cravatar_cn` | `cravatar_cn` | `cravatar_cn` |
| `connectivity.avatar.frontend` | `cravatar_cn` | `off` | `cravatar_global` |
| `modules.windfonts` | `false` | `false` | `false` |
| `connectivity.heartbeat` | `off` | `on` | `on` |
| `connectivity.dashboard_feeds` | `allow` | `block` | `block` |
| `admin_assets` | `off` | `on` | `on` |

五项 = `["google_fonts","google_ajax","cdnjs","jsdelivr","emoji"]`。`windfonts` 三列都是 `false`（domestic「绑定后可开」= 不默认 true；无配额）。

切换场景：调用 `apply_defaults` 覆盖上表路径；`modules.notice_control`、`announcements`、`diagnostics`、`recovery_mode`、`data_residency`、`apps` **保持原值**。REST PUT 切换由调用方先确认；本任务引擎不弹 UI。`data_residency` 键不随场景改；A 档闸在运行时读 `profile`。

### ProfileSuggest（D4）

- geo：请求 `api.wenpai.net`（完整路径待 wenpai-net；未提供前用可注入的 HTTP 客户端，契约 `{ "country": "CN" }`）。失败 → 不建议。
- 查询参数 `locale`、`timezone`（见 rest-api.md）。不存原值，不把 IP 放进响应。
- 规则：服务器境外 + 管理员境内 → `crossborder`；服务器境内 → `domestic`；其它 → `null`。本规则不产生 `mixed`。
- 「境内」实现（D4 未写死匹配表，本任务书写死以免执行者猜）：`server_country === 'CN'`（不是 `HK`/`MO`/`TW`）。管理员境内 = `locale` 匹配 `/^zh[-_]CN/i`，或 `timezone` 为 `Asia/Shanghai` / `Asia/Chongqing` / `Asia/Urumqi` / `PRC`。
- 不自动写 `profile`。

### REST

- `GET /wpcy/v1/profile/suggest`：权限 `Permissions::manage_options_read`（与 GET `/settings` 同一回调）。响应形状见 rest-api.md。
- GET/PUT `/settings` 自然返回/接受 v2 对象（经 Repository + Validator）。PUT 旧 v1 数组 `public_assets` 或字符串 `avatar` → `wpcy_invalid_schema` 400。
- PUT 带 `profile` 且与当前不同：先 `apply_defaults` 再合并其余字段（其余字段可覆盖刚写入的默认，因为「每项仍可单独改」）。若产品以后要「切换必确认」，确认在 UI；本端点收到不同 `profile` 即视为已确认。
- `GET/POST /wpcy/v1/diagnostics/client-probe`：见「跨境场景免费体验层」与 rest-api.md。POST 不把 body 写入 `wpcy_settings`。

### 迁移 D3

- 3.8 `admincdn` 数组含 `'admin'`，或 3.9 `admincdn_files` 含 `'admin'` → `admin_assets='on'`；token `admin` **不**进 `ignored` / `ignored_reasons`。
- 迁移报告面向用户字符串：`__( '后台加速：已保留设置，4.1 起生效', 'wp-china-yes' )`。CLI JSON 可另有机器字段（如 `notes: ["admin_assets_reserved"]`），但报告文本必须含该中文。
- `frontend`、`bootstrapcdn` 仍 `unsupported_whitelist`。
- `profile='domestic'`。avatar 单值拆成两个相同值。`public_assets.scope='both'`；`items` 仍按 M4-02b（键存在则推导，空则 `[]`）。
- 既有 fixture `single-3.8-02.json`（`admincdn: ["admin"]`）是 S2。再加 `single-3.9-08-admincdn-files-admin.json`。

### Telemetry

`Report::collect()` 增加键 `profile`，值是有效 settings 的 `profile`（字符串 `domestic|crossborder|mixed`）。不在界面露出。`telemetry_version` 仍 `'2.1'`（不加版本，只加字段）。

## 禁区

- 不改 `src/Admin/app/`。
- 不改 `docs/dev-plan/decisions/2026-09-06-site-profile-and-scope.md`。
- 不改 D2 矩阵任何单元格的值（heartbeat / dashboard_feeds 是拍板新增行，不是改旧单元格）。
- 4.0 不对 `admin_assets` 做 URL 改写。
- 不自动切换 `profile`。
- 不改 `src/Core/Scope.php`。
- 不为连通性功能建 `Services/Entitlements` 配额；不把「配额用尽降级到上游」接到 WordPress.org / 公共库 / 头像 / Heartbeat / dashboard feeds。
- 不挡支付/物流主机。
- 不在插件内写死商业内容露出条件。
- 不往 `framework/` 加东西。
- 不写用户可见「遥测」「匿名数据」「隐私开关」。
- 不 push `main` / `master`。
- 测试不读 `.grok-context/`。
- 不装系统级依赖。本机无 Docker：e2e 以分支 CI 为准。

## 验收标准

每条一条命令。贴输出，不要只写「通过」。

1. Schema const 与默认：
   ```bash
   php -r 'require "vendor/autoload.php"; echo \WenPai\ChinaYes\Config\Schema::VERSION, PHP_EOL;'
   vendor/bin/phpunit --filter SchemaVersion2Test
   ```
   期望：打印 `2`；升级用例全绿。

2. 默认矩阵逐格：
   ```bash
   vendor/bin/phpunit --filter ProfileTest
   ```
   期望：三场景表 9 行 × 3 列全断言（含 heartbeat / dashboard_feeds）；切换不碰 `notice_control`。

3. Scope 判定：
   ```bash
   vendor/bin/phpunit --filter 'WenPai\\ChinaYes\\Tests\\Unit\\Connectivity\\ScopeTest'
   ```

4. 三模块门控（含既有白名单 / 头像模式，不得红）：
   ```bash
   vendor/bin/phpunit tests/Unit/Connectivity
   ```

5. 迁移 D3：
   ```bash
   vendor/bin/phpunit --filter FixturesTest
   ```
   期望：S2（`single-3.8-02.json`）`admin_assets=on`，`ignored` 不含 `admin`；新 fixture `single-3.9-08-admincdn-files-admin.json` 同样；`frontend` / `bootstrapcdn` 仍 ignored。报告字符串含「后台加速：已保留设置，4.1 起生效」。

6. ProfileSuggest + REST：
   ```bash
   vendor/bin/phpunit --filter ProfileSuggest
   vendor/bin/phpunit --filter ProfileSuggestController
   ```
   期望：四类 D4；响应无 IP 字段（`rg -n "ip" tests/Unit/Config/ProfileSuggestTest.php` 不得断言回传 IP）；权限拒绝有用例。

7. telemetry：
   ```bash
   vendor/bin/phpunit --filter ReportFieldsTest
   ```
   期望：`collect()` 含 `profile`。

8. 全量质量与前端（未改 JS，lint 仍须绿）：
   ```bash
   composer check
   npm run build
   npm run lint:js
   ```

9. Heartbeat 分屏节流：
   ```bash
   vendor/bin/phpunit --filter HeartbeatModuleTest
   ```
   期望：`on` 时 dashboard 注销 `heartbeat`、编辑器 `interval===60`；`off` 不挂钩；`wpcy_heartbeat_throttle` 返回 false 时即使设置为 on 也不挂钩。

10. 挡仪表盘外部内容：
    ```bash
    vendor/bin/phpunit --filter DashboardFeedsModuleTest
    ```
    期望：`block` 去掉 `dashboard_primary`；`dashboard_primary_feed` 空串；`api.wordpress.org/events/1.0/` 短路；`https://api.stripe.com/` 一类支付 URL 不短路；`allow` 不挂钩。

11. 连接诊断 REST：
    ```bash
    vendor/bin/phpunit --filter ClientProbe
    ```
    期望：POST 合法 body 后 GET 等于该摘要；第二次 POST 覆盖第一次；主机不在允许名单 → 400 且 GET 仍是旧摘要；空存储 `{ "checked_at": null, "probes": [] }`。

12. 驻留方案 A + 保险：
    ```bash
    vendor/bin/phpunit --filter ProfileGate
    ```
    期望：`profile=domestic` + ingest_ready → A 档 URL 被改道；`crossborder` / `mixed` + ingest_ready → 原 URL 不变；B 档仍 record；测试不得 mock geo。

13. 现有 e2e 不红（本机无 Docker → push 分支用 CI）：
   ```bash
   git push -u origin grok/m-scope-1
   gh run list --branch grok/m-scope-1
   gh run view <id> --log-failed
   ```
   禁止 push `main`。CI 未完时报告写「CI run \<id\> 进行中，本地验收如下」。

14. diff 范围：
    ```bash
    git diff --stat main...HEAD
    git status
    ```
    不含 `.grok-context/`，不含 `src/Admin/app/`。

提交前缀：`feat(config):` / `feat(connectivity):` / `feat(migration):` / `feat(rest):` / `feat(telemetry):` / `feat(residency):` / `feat(diagnostics):`。按子系统分笔，不要揉成一个。每个 commit 前 `git status` 确认没把 `.grok-context/` 加进去。

## Definition of Done

执行者在报告里逐条打勾并贴证据。缺一条不得报「完成」：

- [ ] 规格条目 ↔ 实现逐条对照表（`| 规格编号 | 实现位置 文件:行 | 状态 |`），规格里有、实现没有的必须列出并写「未做」。本任务对照表指 **config-schema D2 默认矩阵逐键 ↔ `Profile.php` / `Mappers.php` 逐键**，以及 rest-api `/profile/suggest`、`/diagnostics/client-probe` 字段 ↔ 控制器，data-residency §9 ↔ `reroute_enabled`。
- [ ] 每个状态一张截图（Playwright，命名 `<页面>-<状态>.png`，存 `docs/design/screens/<任务>/`），UI 任务必备；非 UI 任务写「不适用」。
- [ ] 面向用户的字符串：全部中文、在 `admin-ui-spec.md` §4 词表内或已提交词表新增；无英文错误串；无「遥测/隐私/上报」字样。本任务用户可见串只有迁移报告「后台加速：已保留设置，4.1 起生效」。
- [ ] 空状态、错误状态、降级状态有对应实现，不是「空表占位」。本任务：geo 失败 → `suggestion: null`；REST 权限拒绝 → `wpcy_forbidden`；client-probe 无记录 → `{checked_at:null,probes:[]}`；非法主机 → `wpcy_invalid_schema`；`profile≠domestic` 时 A 档不改道（不是空实现）。
- [ ] 测试：新增/修改行为有测试；`composer check` 与 `npm run build`、`npm run lint:js` 通过；CI run id。
- [ ] `git diff --stat` 在允许路径内；`.grok-context/` 未入库。
- [ ] 报告含「没做 / 做不到 / 有疑问」；**报告写完再退出**（等 CI 时先写「CI run <id> 进行中，本地验收如下」）。

对照表模板：

```markdown
| 规格编号 | 实现位置 | 状态 |
|----------|----------|------|
| D2 wordpress_org domestic=auto | src/Config/Profile.php:行 | 已做 |
| D3 admin token → admin_assets=on | src/Migration/Mappers.php:行 | 已做 |
| GET /profile/suggest | src/Rest/ProfileSuggestController.php:行 | 已做 |
| D2 heartbeat crossborder=on | src/Config/Profile.php:行 | 已做 |
| D2 dashboard_feeds mixed=block | src/Config/Profile.php:行 | 已做 |
| POST /diagnostics/client-probe | src/Rest/ClientProbeController.php:行 | 已做 |
| 方案 A crossborder 不改道 | src/Privacy/DataResidency/DataResidencyModule.php:行 | 已做 |
```

## 报告格式

见 `docs/dev/agents.md`。必须含：`git diff --stat` 原样、每条验收命令与输出摘要、没做/做不到/有疑问、`git log --oneline -5`。DoD 七项逐条打勾。
