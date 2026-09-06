# M-STATS-1 最终报告

执行者：Grok。分支 `grok/m-stats-1`。CI run **34044167999** 进行中（第二次 push `300d37c`），本地验收如下。第一次 push 的 run 34044061248 已被第二次覆盖。

## 1. 改了哪些文件

`git diff --stat 2a72b48..HEAD` 原样：

```
 .github/workflows/ci.yml                           |   1 +
 composer.json                                      |   1 +
 docs/dev/module-authoring.md                       |   4 +
 docs/specs/config-schema.md                        |   7 +
 phpunit.xml.dist                                   |   3 +
 src/Config/Defaults.php                            |  23 +-
 src/Config/Repository.php                          |   2 +-
 src/Config/Schema.php                              |  40 +-
 src/Connectivity/Avatar/AvatarModule.php           |  27 +-
 .../DashboardFeeds/DashboardFeedsModule.php        |  13 +
 src/Connectivity/Heartbeat/HeartbeatModule.php     |  29 +
 src/Connectivity/MirrorHealth.php                  |   4 +
 .../PublicAssets/PublicAssetsModule.php            |  24 +
 .../WordPressOrg/WordPressOrgModule.php            | 195 ++++++-
 src/Core/Plugin.php                                |  42 +-
 src/Diagnostics/Checker.php                        |  27 +-
 src/Diagnostics/RouteGroups.php                    | 168 ++++++
 src/Migration/Runner.php                           |  28 +-
 src/Rest/ClientProbeController.php                 |   2 +
 src/Rest/DocumentWriter.php                        |  11 +-
 src/Rest/EventsController.php                      | 100 ++++
 src/Rest/RecoveryActions.php                       |  16 +
 src/Rest/RestModule.php                            |  91 +++-
 src/Rest/SettingsController.php                    |  49 +-
 src/Rest/StatsController.php                       | 152 ++++++
 src/Stats/Counters.php                             | 330 ++++++++M-STATS-1 已提交到 `grok/m-stats-1`。CI run **34044167999** 进行中；本地 `composer check` 绿（含 stats 42 tests），报告在 `~/wt/grok-tasks/REPORT-m-stats-1.md`。

五笔提交：`18dd699` feat(stats) → `1c70cc5` feat(rest) → `f2230d8` feat(config) → `5a52482` fix(diagnostics) → `300d37c` feat(config)。未 push `main`。

本机无 Docker，`tests/integration-stats.sh` 未在本地跑，已挂进 wordpress job。`outbound_blocked` 未接 HttpBlock（等 M-BLOCK-1）。生产 diff 约 2100 行（不含测试），超过任务书预计 900 行。
38 ++++++++++++
 tests/Unit/Stats/RouteGroupsTest.php               |  66 +++
 tests/Unit/Stats/SettingsProfileConfirmedTest.php  |  91 ++++
 tests/Unit/Stats/StatsControllerTest.php           | 144 +++++
 tests/Unit/Stats/wp-stats-stubs.php                |  93 ++++
 tests/integration-stats.sh                         |  98 ++++
 40 files changed, 3414 insertions(+), 43 deletions(-)
```

不含 `.grok-context/`。不含 `src/Admin/app/**`。生产代码（不含 `tests/`）约 2100 行，超过任务书「预计 diff ≤ 900 行（不含测试）」；原因见第 3 节。

## 2. 验收标准：命令与输出摘要

### 2.1 `composer check` 绿

命令：`composer check`

摘要（本地，commit `5a52482` 时跑通；其后 `300d37c` 只改 `SettingsController` 一处，`phpstan` 对该文件 `[OK] No errors`，`SettingsProfileConfirmedTest` 3/3）：

- phpcs：exit 0（无输出）
- phpstan：`[OK] No errors`（89/89）
- PHPUnit 全 suite：

| suite | 结果 |
|-------|------|
| smoke | OK (1 test, 3 assertions) |
| core | OK (23 tests, 71 assertions) |
| config | OK (85 tests, 428 assertions) |
| connectivity | OK (110 tests, 236 assertions) |
| telemetry | OK (3 tests, 270 assertions) |
| privacy | OK (16 tests, 73 assertions) |
| diagnostics | OK (13 tests, 123 assertions) |
| stats | OK (42 tests, 137 assertions) |
| cli | OK (8 tests, 42 assertions) |
| rest | OK (53 tests, 277 assertions) |
| integrations | OK (20 tests, 46 assertions) |
| migration | OK (45 tests, 707 assertions) |
| site-binding | OK (12 tests, 131 assertions) |
| apps | OK (68 tests, 210 assertions) |
| entitlements | OK (11 tests, 77 assertions) |
| admin | OK (19 tests, 70 assertions) |

`php vendor/bin/phpunit --testsuite stats`：`OK (42 tests, 137 assertions)`。

### 2.2 单元测试（任务书第 2 条逐项）

`php vendor/bin/phpunit --testsuite stats` 覆盖：

- Counters：分桶 / 31 天滚动 / 未知名忽略并 warning / 恢复模式不计 / flush 只写一次（`CountersTest`）
- Events：环形 50、ULID 长度 26、每种 type 的 title/detail 与 rest-api 模板一致（表驱动 `EventsTest::type_templates`）
- StatsController：days 0/1/30/31/"x"、缺桶补 0、installed_at 三级回退
- EventsController：per_page 上限 50、type 过滤
- Checker 三次 run：ok→fallback→ok 产生 `first_check`、`route_fallback`、`route_recovered`，`{minutes}` = 12（`CheckerEventsTest` + `EventsTest::test_checker_ok_fallback_ok_sequence`）
- SettingsController：PUT 含 `profile` 写 `profile_confirmed_at`、不含则不写、body 带该键被忽略（`SettingsProfileConfirmedTest` 3 tests）
- ClientProbe：新增两主机通过、其它仍拒（`ClientProbeTest::test_new_allow_list_hosts_pass` / `test_other_hosts_still_rejected`，rest suite 53 绿）

### 2.3 集成

本机无 Docker，未跑 `tests/integration-stats.sh`。脚本已加入 `.github/workflows/ci.yml` wordpress job。CI run **34044167999** 进行中。脚本在 wp-env 内 `Checker::run()` + `Events::flush()` 后 `rest_do_request( GET /wpcy/v1/stats?days=7 )` 与 `/events`，断言 10 个计数器、series 长度 = 7、日期升序、events 首条 `first_check`、id 长度 26。

### 2.4 计数器挂钩表

| 计数器 | 挂在哪个钩子 | 文件:行 |
|--------|--------------|---------|
| `mirror_downloads` | `pre_http_request` 改写后响应 2xx | `src/Connectivity/WordPressOrg/WordPressOrgModule.php:245` |
| `mirror_bytes_saved` | 同上，Content-Length 或 body 长度 | `src/Connectivity/WordPressOrg/WordPressOrgModule.php:248` |
| `assets_rewrites_admin` | `style_loader_src` / `script_loader_src` / emoji，一次输出计 1，`Scope::current()===admin` | `src/Connectivity/PublicAssets/PublicAssetsModule.php:187-190` |
| `assets_rewrites_frontend` | 同上，frontend | `src/Connectivity/PublicAssets/PublicAssetsModule.php:187-190` |
| `avatar_rewrites_admin` | `get_avatar_url` 改写一次，admin | `src/Connectivity/Avatar/AvatarModule.php:177-180` |
| `avatar_rewrites_frontend` | 同上，frontend | `src/Connectivity/Avatar/AvatarModule.php:177-180` |
| `heartbeat_saved` | `heartbeat_received` 且编辑器已节流 60s → +3；`load-index.php` 仪表盘关闭 → +1（下限估算，PHPDoc 已写） | `src/Connectivity/Heartbeat/HeartbeatModule.php:189` / `:201` |
| `dashboard_feeds_blocked` | `pre_http_request` 拦 `api.wordpress.org/events/` | `src/Connectivity/DashboardFeeds/DashboardFeedsModule.php:163` |
| `outbound_blocked` | 未接，等 M-BLOCK-1 | — |
| `mirror_fallbacks` | `MirrorHealth::remember()` 从健康记为 `down` 的那一次 | `src/Connectivity/MirrorHealth.php:171` |

### 2.5 事件 type 触发点

| 事件 type | 触发点 文件:行 |
|-----------|----------------|
| `first_check` | `Checker::run()` 首次（before 空）→ `Events::record_checker_transition` `src/Stats/Events.php:229`；调用点 `src/Diagnostics/Checker.php:138` |
| `route_fallback` | 分组 ok→fallback（非 wordpress_org 组）`src/Stats/Events.php:263-264` |
| `mirror_fallback` | 分组 wordpress_org ok→fallback `src/Stats/Events.php:263-264` |
| `route_down` | 分组 ok/fallback→down `src/Stats/Events.php:271` |
| `route_recovered` | 分组 fallback/down→ok，`{minutes}` 距上次非 ok `src/Stats/Events.php:278-279` |
| `update_check` | 镜像 2xx 的 core version-check `src/Connectivity/WordPressOrg/WordPressOrgModule.php:255`；直连 `http_response` `src/Connectivity/WordPressOrg/WordPressOrgModule.php:216` |
| `migrated` | `Migration\Runner::execute()` 成功后 `src/Migration/Runner.php:238` |
| `profile_set` | `SettingsController` PUT 含 `profile` 且值变或首次确认 `src/Rest/SettingsController.php:114` |
| `recovery_entered` | `RecoveryActions::apply` disable_rewrites / disable_modules `src/Rest/RecoveryActions.php:66` / `:73` |
| `recovery_exited` | `RecoveryActions::apply` exit（先记事件再清标志）`src/Rest/RecoveryActions.php:78` |

### 2.6 `npm run build` / `npm run lint:js`

- `npm run lint:js`：exit 0（`wp-scripts lint-js src/Admin/app`）
- `npm run build`：exit 0，`webpack 5.110.3 compiled with 1 warning`（vendor 体积超限，既有，非本任务改动）

### 2.7 CI

```
gh run list --branch grok/m-stats-1
in_progress  feat(config): keep PUT body type check…  CI  grok/m-stats-1  push  34044167999
```

禁止 push `main`。只推了 `grok/m-stats-1`。

## 3. 没做 / 做不到 / 有疑问

1. **生产 diff 超过 900 行（不含测试）**：任务书写「预计 diff ≤ 900 行（不含测试）」。实现含 Counters/Events/RouteGroups/REST 两控制器/StatsModule/各连通性挂钩/文档，生产约 2100 行。未自行砍范围。
2. **本机无 Docker，集成脚本未在本地跑**：按任务书用 CI 做验证回路。`tests/integration-stats.sh` 已进 wordpress job。报告写完时 CI 未结束。
3. **`outbound_blocked` 未接 HttpBlock**：任务书写明等 M-BLOCK-1；计数器名已登记在 `Counters::NAMES`。
4. **`route_fallback` 的 `{host}`**：rest-api 表 detail 已改为 `{provider}`（D1）。实现按现契约用 `{provider}`。`{host}` 只作为内部 vars 传给 Events，不进 title/detail。分组表里的主机校验在 `RouteGroups::group_for_target()`。
5. **`profile_set` 不只在「值改变」时记**：任务书「`profile` 值改变时（含向导首次写入）」。实现：PUT 含 `profile` 键且（值变 **或** `profile_confirmed_at` 仍为空）才记事件；stamp 本身只要 body 含 `profile` 键就写（与任务书第 6 条一致）。
6. **WordPressOrg 直连 `update_check`**：任务书只写「更新检查请求完成时」。模块 `enabled()` 在 `wordpress_org=off` 时不注册，跨境直连不会记 `update_check`。已在 `http_response` 观察直连 version-check，但该钩子只在模块 `register()` 之后存在。跨境站默认 `wordpress_org=off` → 本任务不记直连更新检查。写在这里，不自行改 enabled()。
7. **`MirrorHealth::remember()` 生产路径**：当前仓内没有任何运行时调用 `remember()`（只有测试）。`mirror_fallbacks` 挂钩已就位；若生产从不 `remember('down')`，该计数器会一直为 0。未另接 Checker 探测结果（任务书写「MirrorHealth 记为不健康的那一次」）。
8. **`GET /stats` days 缺省**：查询缺省 / 空 → 7；非法（0、31、"x"）→ `wpcy_invalid_schema` 400。REST 路由 `args.days` 声明 `type=integer`，WP 核心可能在进入 callback 前把 `"x"` 丢掉；控制器仍校验。
9. **未改 `docs/specs/rest-api.md` 契约正文**（禁区）。
10. **`npm run build` 的 vendor 体积 warning** 既有，非本任务。

## 4. 提交哈希

`git log --oneline -6`：

```
300d37c feat(config): keep PUT body type check when stamping profile_confirmed_at
5a52482 fix(diagnostics): allow googlefonts.admincdn.com and cn.cravatar.com probes
f2230d8 feat(config): add read-only profile_confirmed_at stamp
1c70cc5 feat(rest): GET /stats and /events plus installed_at fallback
18dd699 feat(stats): local counters, event log, and increment hooks
2a72b48 docs(specs): route groups carry provider brand (D1); events detail uses {provider}; M-STATS-1 adds RouteGroups class
```

五笔（≥ 4）：`feat(stats):` / `feat(rest):` / `feat(config):` ×2 / `fix(diagnostics):`。

## 5. 规格 ↔ 实现对照表

| 规格编号 | 实现位置 | 状态 |
|----------|----------|------|
| rest-api §/stats `days` 1–30，非法 `wpcy_invalid_schema` 400 | `src/Rest/StatsController.php:109-125` | 已做 |
| rest-api §/stats 10 个计数器 series 长度 = days、缺桶 0、日期升序、totals | `src/Stats/Counters.php:171-196`；`src/Rest/StatsController.php:62-80` | 已做 |
| rest-api §/stats `installed_at` 三级回退 | `src/Rest/StatsController.php:87-101`；激活写入 `src/Core/Plugin.php:275-283` | 已做 |
| rest-api §/stats 存储 `{buckets:{YYYY-MM-DD:{counter:int}}}` UTC，31 天滚动，shutdown 一次写 | `src/Stats/Counters.php:206-224` | 已做 |
| rest-api §/stats 恢复模式不计 | `src/Stats/Counters.php:136-138` | 已做 |
| rest-api §/stats 未知计数器忽略 | `src/Stats/Counters.php:140-149` | 已做 |
| rest-api §/events per_page 默认 20 上限 50、type 过滤 | `src/Rest/EventsController.php:67-99` | 已做 |
| rest-api §/events 环形 50、ULID 26、autoload=false | `src/Stats/Events.php:145-174` / `:316-348` | 已做 |
| rest-api §/events 十种 type 模板与 tone | `src/Stats/Events.php:360-507` | 已做 |
| rest-api §/events 恢复模式只记 recovery_* | `src/Stats/Events.php:148-150` | 已做 |
| rest-api §/diagnostics 分组表 + 服务商列 | `src/Diagnostics/RouteGroups.php:46-78` | 已做 |
| rest-api §/diagnostics 组最差状态 / 最大延迟 / 最早 checked_at | `src/Diagnostics/RouteGroups.php:108-164` | 已做 |
| rest-api §/diagnostics/client-probe 允许名单加两主机 | `src/Rest/ClientProbeController.php:39-47` | 已做 |
| config-schema `profile_confirmed_at` string\|null 默认 null，PUT 忽略 | `src/Config/Schema.php:304-308`；`src/Rest/SettingsController.php:69-70` | 已做 |
| PUT 含 profile 键写当前时间 | `src/Rest/SettingsController.php:83-84` | 已做 |
| 权限同 DiagnosticsController（manage_options_read） | `src/Rest/RestModule.php:337` / `:350` | 已做 |
| 响应不含 URL / IP / 查询串 | StatsController / EventsController 投影字段固定 | 已做 |
| `{route}` 用人读组名；`{provider}` 用品牌原文 | `src/Stats/Events.php:255-258` | 已做 |
| `outbound_blocked` | 只登记名 | 未做：等 M-BLOCK-1 |

## 6. Definition of Done

- [x] 规格条目 ↔ 实现逐条对照表：见上节。规格有、实现没有的已列「未做」。
- [x] 每个状态一张截图：不适用（非 UI 任务）。
- [x] 面向用户的字符串：事件 title/detail 照抄 rest-api 中文模板，经 `__()`；无「遥测/隐私/上报」。`grep` 用户可见新串均在 `Events::render()` 与 Settings 场景名「国内站 / 跨境 / 外贸站 / 混合站」（config-schema / admin-ui-spec 已有）。
- [x] 空 / 错误 / 降级：`/stats` 缺桶补 0；`/events` 空列表 `{events:[]}`；days 非法 400 `wpcy_invalid_schema`；恢复模式不计 / 只记 recovery_*。不是空表占位。
- [x] 测试：新增 Unit/Stats 42 + ClientProbe 2 + PluginCreate 激活断言；`composer check` 绿；`npm run build` 绿；`npm run lint:js` 绿；CI run **34044167999** 进行中。
- [x] `git diff --stat` 在允许路径内；`.grok-context/` 未入库。
- [x] 报告含「没做 / 做不到 / 有疑问」；报告写完再退出。
