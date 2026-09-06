# 任务 M-STATS-1：本地计数器、事件日志与 `/stats` `/events` REST（概览页的数据源）

worktree 分支 `grok/m-stats-1`，基于 `main`。预计 diff ≤ 900 行（不含测试）。**本任务不改任何 React/界面文件**（`src/Admin/app/**` 禁区）。与 M-UI-1（前端）并行；两者靠本任务书里的接口契约对齐，契约已写死在 `docs/specs/rest-api.md` §`/stats`、§`/events`，**不得改契约**，做不到就在报告写明。

## 目标

让概览页每一个数字都有真实来源：安装时间、最近 N 天各计数器按日序列、事件日志；并补两处小后端缺口（`profile_confirmed_at`、浏览器测速允许名单）。

## 背景与上下文文件

- 原型与规格：`docs/design/prototypes/e/overview-domestic.html`（看数字长什么样）、`docs/design/admin-ui-spec.md` v2.0 §4.1（OV-08 / OV-09 / OV-10c / OV-12 / OV-14 用到哪些字段）。
- 契约：`docs/specs/rest-api.md` §`/stats`、§`/events`、§`/diagnostics` 的"界面分组"表、§`/diagnostics/client-probe` 允许名单补充。
- 现有代码：`src/Diagnostics/Checker.php`（`run()` / `latest()`，事件的主要来源）、`src/Connectivity/MirrorHealth.php`（镜像健康状态 transient）、`src/Connectivity/WordPressOrg/WordPressOrgModule.php`、`src/Connectivity/PublicAssets/PublicAssetsModule.php`、`src/Connectivity/Avatar/AvatarModule.php`、`src/Connectivity/Heartbeat/HeartbeatModule.php`、`src/Connectivity/DashboardFeeds/DashboardFeedsModule.php`、`src/Migration/`、`src/Rest/RecoveryActions.php`、`src/Rest/SettingsController.php`、`src/Rest/ClientProbeController.php`、`src/Core/Plugin.php::activate()`、`src/Config/Schema.php` / `Defaults.php`。
- 规范：`docs/dev/task-book-template.md`（按其 DoD 报告）、`docs/dev/coding-standards.md`、`docs/dev/module-authoring.md`、`docs/dev/testing.md`、`docs/dev/agent-common-rules.md`。
- `.grok-context/COMMON-RULES.md`。

## 交付物

| # | 交付 | 路径 | 做法 |
|---|---|---|---|
| 1 | 计数器存储 | 新建 `src/Stats/Counters.php` | 单例式服务（走 `Core\Container`）。`increment( string $counter, int $n = 1 ): void` 只改内存；`shutdown` 钩子一次 `update_option( 'wpcy_stats', …, false )`（无变化不写）。存储结构 `{ "buckets": { "YYYY-MM-DD": { "<counter>": int } } }`，UTC 日；写入时删除 31 天前的桶。计数器名**只允许** rest-api §`/stats` 表里的 10 个，其它名字忽略并记 warning 日志。恢复模式（`recovery_mode=true`）下 `increment` 直接返回 |
| 2 | 计数挂钩 | 新建 `src/Stats/StatsModule.php`（实现 `Core\Module`，id `stats`，contexts 全部）；在各连通性模块里 `do_action( 'wpcy_stats_increment', '<counter>', $n )` | `StatsModule::register()` 监听 `wpcy_stats_increment` 转给 `Counters`。各模块只负责在正确时机 `do_action`：`mirror_downloads` / `mirror_bytes_saved`（WordPressOrg：改写后的 HTTP 响应 2xx；字节取 `Content-Length` 或 body 长度）、`mirror_fallbacks`（MirrorHealth 记为不健康的那一次）、`assets_rewrites_admin/frontend`（PublicAssets：一次输出中至少改写一个 URL，按 `Core\Scope` 当前作用域计 1）、`avatar_rewrites_admin/frontend`（Avatar：`get_avatar_url` 改写一次）、`heartbeat_saved`（Heartbeat：`heartbeat_received` 且当前屏幕已节流到 60s → +3；`load-index.php` 且仪表盘心跳已关闭 → +1；这是**下限估算**，在 PHPDoc 写明）、`dashboard_feeds_blocked`（DashboardFeeds 拦下一次外部内容请求 → +1）。`outbound_blocked`：只登记计数器名，**不**在本任务接 HttpBlock（M-BLOCK-1 在跑，合并后由统筹补一行 `do_action`） |
| 3 | 事件日志 | 新建 `src/Stats/Events.php` | `record( string $type, array $vars = array() ): void`，环形 50 条存 `wpcy_events`（`autoload=false`），`id` 为 ULID（自实现 26 字符 Crockford，不引包）。`title` / `detail` **在此类**按 rest-api §`/events` 模板表用 `__()` 生成并随条目存储（存的是生成后的中文，`{route}` 等已替换）。`tone` 按表。事件来源：`Checker::run()` 前后比对每个**分组**（分组表见 rest-api §`/diagnostics`）的最差状态：首次运行 → `first_check`；ok→fallback → `route_fallback`（镜像组用 `mirror_fallback`）；ok/fallback→down → `route_down`；fallback/down→ok → `route_recovered`（`{minutes}` = 距上次非 ok 事件的分钟数）。`update_check`：WordPressOrg 在更新检查请求完成时（`{version}` 取响应里的最新 core 版本，取不到写当前 WP 版本；`{seconds}` 一位小数；走镜像 tone ok，直连 tone neutral）。`migrated`：`Migration\Runner::execute()` 成功后。`profile_set`：`SettingsController` PUT 导致 `profile` 值改变时（含向导首次写入）。`recovery_entered/exited`：`RecoveryActions`。恢复模式下只记 `recovery_*` |
| 4 | REST | 新建 `src/Rest/StatsController.php`、`src/Rest/EventsController.php`；在 `RestModule` 注册 | 严格按 rest-api §`/stats`（`days` 校验 1–30，非法 → `wpcy_invalid_schema` 400；`series` 每键长度 = `days`、缺桶补 0、日期升序；`totals`；`installed_at`）与 §`/events`（`per_page` 默认 20 上限 50；`type` 过滤）。权限同 `DiagnosticsController`。`installed_at` 读取顺序：`wpcy_installed_at` → 迁移报告 `migrated_at` → 现在（并写入 `wpcy_installed_at`） |
| 5 | 安装时间 | `src/Core/Plugin.php::activate()` | 若 `wpcy_installed_at` 不存在则写 UTC ISO 8601；多站点按站点 |
| 6 | `profile_confirmed_at` | `src/Config/Schema.php`、`Defaults.php`、`SettingsController` | schema 新增顶层 `profile_confirmed_at`：`string`（ISO 8601）或 `null`，默认 `null`；只读——PUT body 里带它则忽略。`SettingsController` 在 PUT body **含 `profile` 键**时（不论值是否变化）写当前时间。`docs/specs/config-schema.md` 加一行说明 |
| 7 | 浏览器测速允许名单 | `src/Rest/ClientProbeController.php` | 允许名单加 `googlefonts.admincdn.com`、`cn.cravatar.com`（rest-api 已写）。既有单元测试更新 |
| 8 | 文档 | `docs/specs/config-schema.md`（第 6 条）、`docs/dev/module-authoring.md`（加一节「计数与事件：用 `wpcy_stats_increment` 与 `Events::record`，不要自己写 option」，≤ 15 行） | 不改 rest-api 契约正文；发现契约做不到 → 报告 |

## 行为规格

- 契约：`docs/specs/rest-api.md` §`/stats`、§`/events`、§`/diagnostics` 分组表。
- 时间：全部 UTC ISO 8601；分桶按 UTC 日。
- 隐私：`/stats`、`/events` 响应**不含** URL、IP、查询串；`{route}` 只用分组人读名；`{host}` 只出现在 `route_fallback` 的 detail 且只能是分组表里的主机。
- 性能：`increment` 与 `record` 不做 I/O；一次请求最多各写一次 option；`GET /stats` 不触发写（除首次写 `installed_at`）。
- 多站点：计数、事件、`installed_at` 均按站点（`switch_to_blog` 安全：用 `get_option`）。

## 统筹补充（2026-09-06 22:40，决定 D1）

- rest-api §`/diagnostics` 分组表新增**服务商**列（WenPai.org / adminCDN / Cravatar），事件模板 `route_fallback` / `route_down` / `route_recovered` 的 detail 用 `{provider}`。把分组 → 服务商 → 成员目标放在**一个**类 `src/Diagnostics/RouteGroups.php`（常量表 + `group_for_target()` + `worst()` 聚合），`Events` 从它取 `{route}` / `{provider}` / `{host}`；后续前端也读这张表（导出到 bootstrap 由 M-UI-1 做，本任务不碰 Admin）。
- 决定原文 `.grok-context/2026-09-06-core-services-value-and-providers.md` D1 / D2。

## 禁区

- 不改 `src/Admin/app/**`、`src/Admin/AdminModule.php`、`src/Admin/RecoveryPage.php`。
- 不改 `docs/specs/rest-api.md` 已写的 `/stats` `/events` 字段与模板文案；不改 `docs/design/**`。
- 不接 HttpBlock / `src/Privacy/**`（M-BLOCK-1 在另一分支）。
- 不新增用户可见英文；事件模板文案原文照抄 rest-api 表，不改字。
- 不 `git push` 到 `main`；只推 `grok/m-stats-1`。

## 验收标准

1. `composer check` 绿；分支 CI 全绿（贴 run id）。
2. 单元测试（`tests/Unit/Stats/`）：`Counters` 分桶 / 31 天滚动 / 未知名忽略 / 恢复模式不计 / shutdown 只写一次；`Events` 环形 50、ULID 长度 26、每种 `type` 的 title/detail 与模板一致（表驱动）；`StatsController` `days` 边界（0、1、30、31、"x"）、缺桶补 0、`installed_at` 三级回退；`EventsController` `per_page` 上限、`type` 过滤；`Checker` 比对产生的事件序列（ok→fallback→ok 三次 run 产生 `first_check`、`route_fallback`、`route_recovered` 且 `{minutes}` 正确）；`SettingsController` PUT 含 `profile` 写 `profile_confirmed_at`、不含则不写、body 带该键被忽略；`ClientProbe` 新增两个主机通过、其它仍拒。
3. 集成：`tests/integration-cli.sh` 或新增 `tests/integration-stats.sh`：wp-env 内触发一次 `wp cron event run wpcy_diagnostics`（或等价）后 `curl /wpcy/v1/stats?days=7` 与 `/events` 形状符合契约（贴 JSON 片段）。
4. 报告贴：`| 计数器 | 挂在哪个钩子 | 文件:行 |` 十行（`outbound_blocked` 写「未接，等 M-BLOCK-1」）；`| 事件 type | 触发点 文件:行 |` 十行。
5. DoD 七项逐条（UI 截图项写"不适用"）。
6. 提交前缀 `feat(stats):` / `feat(rest):` / `feat(config):` / `fix(diagnostics):`；至少分 4 笔。

## Definition of Done

- [ ] 规格条目 ↔ 实现逐条对照表（`| 规格编号 | 实现位置 文件:行 | 状态 |`），规格里有、实现没有的必须列出并写「未做」。
- [ ] 每个状态一张截图（Playwright，命名 `<页面>-<状态>.png`，存 `docs/design/screens/<任务>/`），UI 任务必备；非 UI 任务写「不适用」。
- [ ] 面向用户的字符串：全部中文、在 `admin-ui-spec.md` §5 词表内或已提交词表新增；无英文错误串；无「遥测/隐私/上报」字样。
- [ ] 空状态、错误状态、降级状态有对应实现，不是「空表占位」。
- [ ] 测试：新增/修改行为有测试；`composer check` 与 `npm run build`、`npm run lint:js` 通过；CI run id。
- [ ] `git diff --stat` 在允许路径内；`.grok-context/` 未入库。
- [ ] 报告含「没做 / 做不到 / 有疑问」；**报告写完再退出**（等 CI 时先写「CI run <id> 进行中，本地验收如下」）。

## 报告格式

见 `docs/dev/agents.md`。报告写到 `~/wt/grok-tasks/REPORT-m-stats-1.md`（启动脚本已重定向 stdout）。
