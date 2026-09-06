先读审查与上下文，再装依赖，按任务书修阻断项。审查已读。开始装依赖并核对实现与测试。继续读测试夹具、编码规范，以及 WordPressOrg 改写失败路径。接着核对 PublicAssets 计数、现有测试夹具，以及 NetworkSettings 的测试写法。实现方案已定：阻断 1 选 (a)，因为回上游发生在 `WordPressOrgModule`。开始改代码。开始改生产路径：阻断 1 选 (a)，在 `WordPressOrgModule` 改写失败回上游时调用 `MirrorHealth::remember()`。继续改其余生产代码与测试。接着补测试、修 PHPDoc，并更新集成脚本。先跑相关 PHPUnit 套件，确认测试能过。任务书要求至少四个提交；阻断已两笔，建议再分两笔。开始跑 `composer check`，同时推分支给 CI。本地 `composer check` 已绿。收集对照表行号，并抽查关键测试输出。本地验收已齐。CI run `34049237287` 仍在跑，按任务书先交报告。M-STATS-1b 已在 `grok/m-stats-1` 追加 4 笔提交（HEAD `f128cd9`）。阻断 1 选择 **(a)**：在 `WordPressOrgModule` 改写请求失败、回原始上游处调用 `MirrorHealth::remember( $host, 'down', Origins::DOWN_TTL )`。理由：契约写的是「镜像不可达、回原始上游一次」，真正回上游的点在 `filter_wordpress_org()`；Checker 的 `fallback` 只写诊断 transient，不走上游请求，记上去会把探测失败当成生产回退。同一次不可达靠 MirrorHealth TTL（healthy→down 才 +1）。

本地 `composer check` 绿。CI run **34049237287** 进行中（HEAD `f128cd94cebac3c8255fe247ca944926746f5af0`）；已完成的 job：`frontend` success、`plugin-check` success（含 `tests/integration-stats.sh`）、`package` success。未等全绿即交报告。

---

## 1. 改了哪些文件（`git diff --stat 51fd4d4`）

```
 src/Connectivity/Avatar/AvatarModule.php           |  34 +++-
 src/Connectivity/Heartbeat/HeartbeatModule.php     |  61 ++++++-
 src/Connectivity/MirrorHealth.php                  |   4 +-
 .../WordPressOrg/WordPressOrgModule.php            |  80 +++++++--
 src/Core/Plugin.php                                |  12 +-
 src/Rest/NetworkSettingsController.php             |  10 +-
 src/Rest/StatsController.php                       |  31 +++-
 src/Stats/Counters.php                             |   1 +
 src/Stats/Events.php                               |  17 +-
 tests/Unit/Connectivity/Avatar/AvatarModeTest.php  |  25 +++
 .../Connectivity/Heartbeat/HeartbeatModuleTest.php |  23 +++
 tests/Unit/Connectivity/HookStore.php              |   8 +
 tests/Unit/Connectivity/wp-error-stub.php          |  13 ++
 tests/Unit/Connectivity/wp-hook-stubs.php          |  28 +++
 tests/Unit/Stats/CountersTest.php                  |   9 +-
 tests/Unit/Stats/EventsTest.php                    |  13 ++
 .../Stats/NetworkSettingsProfileConfirmedTest.php  |  91 ++++++++++
 tests/Unit/Stats/StatsControllerTest.php           |  14 ++
 tests/Unit/Stats/WordPressOrgStatsTest.php         | 198 +++++++++++++++++++++
 tests/Unit/Stats/wp-stats-stubs.php                |  14 ++
 tests/integration-stats.sh                         |  18 +-
 21 files changed, 665 insertions(+), 39 deletions(-)
```

未改 `src/Admin/app/`、`docs/specs/rest-api.md`；未入库 `.grok-context/`。

---

## 2. 验收命令与输出摘要

### `composer check`（本机，exit 0，约 144s）

phpcs 无输出。phpstan：`[OK] No errors`。

| suite | 结果 |
|---|---|
| smoke | OK (1 test, 3 assertions) |
| core | OK (23 tests, 71 assertions) |
| config | OK (85 tests, 428 assertions) |
| connectivity | OK (112 tests, 240 assertions) |
| telemetry | OK (3 tests, 270 assertions) |
| privacy | OK (16 tests, 73 assertions) |
| diagnostics | OK (13 tests, 123 assertions) |
| **stats** | **OK (51 tests, 153 assertions)**（原 42） |
| cli | OK (8 tests, 42 assertions) |
| rest | OK (53 tests, 277 assertions) |
| integrations | OK (20 tests, 46 assertions) |
| migration | OK (45 tests, 707 assertions) |
| site-binding | OK (12 tests, 131 assertions) |
| apps | OK (68 tests, 210 assertions) |
| entitlements | OK (11 tests, 77 assertions) |
| admin | OK (19 tests, 70 assertions) |

### 阻断 / 建议对应测

- `phpunit --testsuite stats`：`OK (51 tests, 153 assertions)`，含 `WordPressOrgStatsTest`（失败→`mirror_fallbacks=1`；TTL 内第二次仍为 1；version-check / zip 2xx 计；plugin-info 不计）、`test_installed_at_from_site_option_on_multisite`、`NetworkSettingsProfileConfirmedTest`、flush 后增量再写一次。
- `phpunit --testsuite connectivity`：`OK (112 tests, 240 assertions)`，含心跳 60 秒内两次只 +3、头像只在 `get_avatar_url` 计数。
- CI `plugin-check` 已跑 `bash tests/integration-stats.sh`，conclusion **success**（隐私检查失败会非 0）。

### CI

```
git push -u origin grok/m-stats-1
# 51fd4d4..f128cd9  grok/m-stats-1 -> grok/m-stats-1

gh run list --branch grok/m-stats-1 --limit 1
# in_progress  test: persist post-flush increments…  34049237287
```

`gh run view 34049237287`：status `in_progress`；frontend / plugin-check / package 已 success。php / quality 矩阵未全部结束。禁止 push `main`，未 push。

---

## 3. 没做 / 做不到 / 有疑问

- **建议 6**（每个连通性 `do_action` 补单测）：可选，未做全。本任务补了 WordPressOrg / Heartbeat / Avatar；PublicAssets、DashboardFeeds 的 `do_action` 未单测。
- **`Events::generate_ulid()` 的 `random_bytes` Exception**：实现已 `try/catch` 并回退 `wp_generate_password( 16, false )` 派生 10 字节，长度仍 26。无法在不劫持 PHP 内建函数的情况下稳定触发 `random_bytes` 失败；单测只验证正常路径 26 字符 + 回退编码材料长度为 10。
- 跨 suite 一次 `phpunit --filter …` 会撞 stub（`E_STRICT` / 缺 `wpcy_stats`）。`composer test:unit` 分 suite 跑，全部绿。
- **`outbound_blocked`** 仍只登记名，未接 HttpBlock（任务禁区）。
- 本机无 Docker；`integration-stats.sh` 未在本机跑，证据来自 CI plugin-check 该步 success。

---

## 4. 提交哈希（`git log --oneline -5`）

```
f128cd9 test: persist post-flush increments and fail privacy leaks
31e02ae fix(stats): throttle heartbeat and count avatar only on get_avatar_url
63da614 fix(rest): read installed_at from site_option on multisite
53b9827 fix(stats): record mirror_fallbacks on rewrite failure
51fd4d4 test(stats): also clear stored diagnostics so the run counts as first_check
```

相对 `51fd4d4` 四笔：`fix(stats):` ×2、`fix(rest):` ×1、`test:` ×1。

---

## DoD

- [x] 规格条目 ↔ 实现对照表（见下）。
- [x] 每个状态一张截图：不适用（非 UI）。
- [x] 面向用户字符串：事件模板仍在 `Events::render()`，中文；`grep` `src/Stats` / `src/Connectivity` 无「遥测」「匿名数据」。
- [x] 空/错误/降级：`days` 非法仍 400；镜像失败回 `$preempt`；恢复模式仍不计。
- [x] 测试：新增行为有测；`composer check` 绿；CI run **34049237287** 进行中。未改前端，未跑 `npm run build` / `lint:js`（CI frontend job 已 success）。
- [x] `git diff --stat` 在允许路径；`.grok-context/` 未入库。
- [x] 「没做 / 做不到 / 有疑问」已写；报告写完再退出。

### 规格 ↔ 实现（rest-api §`/stats` 十计数器、§`/events` 十事件）

| 规格编号 | 实现位置 | 状态 |
|----------|----------|------|
| `/stats` `mirror_downloads` | `src/Connectivity/WordPressOrg/WordPressOrgModule.php:265`（仅 version-check 或 `downloads.wordpress.org` `.zip` 2xx） | 已做 |
| `/stats` `mirror_bytes_saved` | 同文件 `:267-269` | 已做 |
| `/stats` `assets_rewrites_admin` / `_frontend` | `src/Connectivity/PublicAssets/PublicAssetsModule.php:187-190` | 已做（本任务未改语义） |
| `/stats` `avatar_rewrites_admin` / `_frontend` | `src/Connectivity/Avatar/AvatarModule.php:131,158-159,201-208`（只在 `get_avatar_url`） | 已做 |
| `/stats` `heartbeat_saved` | `src/Connectivity/Heartbeat/HeartbeatModule.php:193-200,208-226`（每 user+screen 每分钟最多一次 +3；下限估算） | 已做 |
| `/stats` `dashboard_feeds_blocked` | `src/Connectivity/DashboardFeeds/DashboardFeedsModule.php:163` | 已做（本任务未改） |
| `/stats` `outbound_blocked` | `src/Stats/Counters.php:55` 仅登记名 | 未接 HttpBlock（任务授权） |
| `/stats` `mirror_fallbacks` | `WordPressOrgModule.php:193-196` → `MirrorHealth.php:169-173` | 已做（选择 a） |
| `/stats` 十键 series | `src/Stats/Counters.php:46-57`；REST `src/Rest/StatsController.php:70-78` | 已做 |
| `/stats` `installed_at` 多站点回退 | `src/Rest/StatsController.php:133-155`（`get_site_option( Runner::REPORT_OPTION )`） | 已做 |
| `/events` `first_check` | `src/Stats/Events.php:57,380-401` | 已做 |
| `/events` `route_recovered` | `:58,403-417` | 已做 |
| `/events` `mirror_fallback` | `:59,419-424` | 已做 |
| `/events` `route_fallback` | `:60,426-439` | 已做 |
| `/events` `route_down` | `:61,441-454` | 已做 |
| `/events` `update_check` | `:62,456-476` | 已做 |
| `/events` `migrated` | `:63,478-492` | 已做 |
| `/events` `profile_set` | `:64,494-506` | 已做 |
| `/events` `recovery_entered` | `:65,508-513` | 已做 |
| `/events` `recovery_exited` | `:66,515-520` | 已做 |
