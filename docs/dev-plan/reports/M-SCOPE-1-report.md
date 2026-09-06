先读完整任务书和开工前必读文件，再装依赖。任务书已完整读完。先设工作摘要，再读上下文、规格和现有实现。composer 还在装。同时读规格和现有实现。规格和现有代码已经对上。开始改 Schema v2，并并行写新类。接着写测试、夹具，并改现有断言。单元测试全绿。接着跑 lint、phpstan 和前端构建。lint / phpstan / 前端都绿。按子系统分笔提交，再 push 用 CI 验证。M-SCOPE-1 已写进 `grok/m-scope-1` 并 push；**CI run 3402712431 进行中**，本地验收如下。

## 1. 改了哪些文件

`git diff --stat main...HEAD`：

```
 src/Config/Defaults.php                            |  19 +-
 src/Config/Profile.php                             | 158 +++++++++++++++
 src/Config/ProfileSuggest.php                      | 215 ++++++++++++++++++++
 src/Config/Repository.php                          |  37 +++-
 src/Config/Schema.php                              | 106 ++++++++--
 src/Config/SchemaMigrator.php                      | 117 +++++++++++
 src/Connectivity/Avatar/AvatarModule.php           |  31 ++-
 .../DashboardFeeds/DashboardFeedsModule.php        | 168 ++++++++++++++++
 src/Connectivity/Heartbeat/HeartbeatModule.php     | 147 ++++++++++++++
 .../PublicAssets/PublicAssetsModule.php            |  62 +++++-
 src/Connectivity/Scope.php                         |  91 +++++++++
 src/Core/Plugin.php                                |   6 +-
 src/Diagnostics/Checker.php                        |  29 ++-
 src/Migration/Mappers.php                          |  35 +++-
 src/Migration/Report.php                           |  18 +-
 src/Migration/Runner.php                           |   1 +
 src/Privacy/DataResidency/DataResidencyModule.php  |  32 ++-
 src/Rest/ClientProbeController.php                 | 222 +++++++++++++++++++++
 src/Rest/DocumentWriter.php                        |  29 +++
 src/Rest/ProfileSuggestController.php              |  65 ++++++
 src/Rest/RecoveryActions.php                       |  16 +-
 src/Rest/ResidencyController.php                   |  10 +-
 src/Rest/RestModule.php                            |  55 ++++-
 src/Telemetry/Report.php                           |  17 ++
 tests/Unit/Config/MultisiteReadOrderTest.php       |  77 ++++---
 tests/Unit/Config/ProfileSuggestTest.php           | 127 ++++++++++++
 tests/Unit/Config/ProfileTest.php                  | 137 +++++++++++++
 tests/Unit/Config/RepositoryTest.php               |  34 ++--
 tests/Unit/Config/SchemaVersion2Test.php           | 158 +++++++++++++++
 tests/Unit/Config/ValidatorTest.php                |  63 ++++--
 tests/Unit/Connectivity/Avatar/AvatarModeTest.php  |  28 ++-
 .../DashboardFeeds/DashboardFeedsModuleTest.php    | 142 +++++++++++++
 .../Connectivity/Heartbeat/HeartbeatModuleTest.php | 129 ++++++++++++
 tests/Unit/Connectivity/HookStore.php              |  20 +-
 .../Connectivity/PublicAssets/WhitelistTest.php    |  65 +++++-
 tests/Unit/Connectivity/ScopeHarness.php           |  63 ++++++
 tests/Unit/Connectivity/ScopeTest.php              | 119 +++++++++++
 tests/Unit/Connectivity/scope-function-stubs.php   |  73 +++++++
 tests/Unit/Connectivity/wp-error-stub.php          |  62 +++++-
 tests/Unit/Connectivity/wp-hook-stubs.php          |  43 +++-
 tests/Unit/Core/PluginCreateTest.php               |   5 +-
 tests/Unit/Migration/FixturesTest.php              |  78 +++++---
 tests/Unit/Migration/wp-option-stubs.php           |  13 ++
 tests/Unit/Privacy/ProfileGateTest.php             | 154 ++++++++++++++
 tests/Unit/Rest/ClientProbeTest.php                | 162 +++++++++++++++
 tests/Unit/Rest/MigrationReportControllerTest.php  |  16 ++
 tests/Unit/Rest/PermissionsTest.php                |  65 +++++-
 tests/Unit/Rest/ProfileSuggestControllerTest.php   | 124 ++++++++++++
 tests/Unit/Rest/ResidencyControllerTest.php        |   9 +
 tests/Unit/Telemetry/ReportFieldsTest.php          |   3 +-
 .../single-3.9-08-admincdn-files-admin.json        | 152 ++++++++++++++
 51 files changed, 3640 insertions(+), 167 deletions(-)
```

`git status`：working tree clean；无 `.grok-context/`，无 `src/Admin/app/`。

## 2. 验收命令与输出摘要

**1. Schema const 与默认**

```
php -r 'require "vendor/autoload.php"; echo \WenPai\ChinaYes\Config\Schema::VERSION, PHP_EOL;'
```

该命令因 `Schema.php` 的 `ABSPATH` 守卫直接 `exit`，无输出。带 `ABSPATH`：

```
php -r 'define("ABSPATH","."); require "vendor/autoload.php"; echo \WenPai\ChinaYes\Config\Schema::VERSION, PHP_EOL;'
# 2
```

```
vendor/bin/phpunit --filter SchemaVersion2Test
# OK (6 tests, 26 assertions)
```

**2. 默认矩阵**

```
vendor/bin/phpunit --filter ProfileTest
# OK (29 tests, 174 assertions)
```

三场景 9 行 × 3 列全断言；切换不碰 `notice_control`。

**3. Scope**

```
vendor/bin/phpunit --filter 'WenPai\\ChinaYes\\Tests\\Unit\\Connectivity\\ScopeTest'
# OK (8 tests, 8 assertions)
```

**4. 三模块门控**

```
vendor/bin/phpunit tests/Unit/Connectivity
# OK (112 tests, 239 assertions)
```

**5. 迁移 D3**

```
vendor/bin/phpunit --filter FixturesTest
# OK (43 tests, 697 assertions)
```

S2 `single-3.8-02.json`：`admin_assets=on`，`ignored` 不含 `admin`；新 fixture `single-3.9-08-admincdn-files-admin.json` 同样；`frontend` / `bootstrapcdn` 仍 ignored。报告 `messages` 含「后台加速：已保留设置，4.1 起生效」。

**6. ProfileSuggest + REST**

```
vendor/bin/phpunit --filter ProfileSuggest
# OK (12 tests, 30 assertions)
vendor/bin/phpunit --filter ProfileSuggestController
# OK (5 tests, 14 assertions)
```

`rg -n "ip" tests/Unit/Config/ProfileSuggestTest.php` 只有 `assertArrayNotHasKey( 'ip', … )`，不断言回传 IP。权限拒绝有用例。

**7. telemetry**

```
vendor/bin/phpunit --testsuite telemetry --filter ReportFieldsTest
# OK (2 tests, 98 assertions)
```

`collect()` 含 `profile`，缺省 `domestic`。不带 `--testsuite` 的 `--filter ReportFieldsTest` 会撞 suite 桩（`phpunit.xml.dist` 已写明），见第 3 节。

**8. 质量与前端**

```
composer lint          # exit 0，无输出
composer analyse       # [OK] No errors（83 files）
npm run build          # webpack compiled with 1 warning（vendor 体积，既有）
npm run lint:js        # exit 0
```

各 `--testsuite` 单元均绿（config 82 / core 23 / rest 45 / migration 43 / connectivity 112 / telemetry 2 / privacy 16 / diagnostics 13 / cli 8 / admin 19 / apps 68 / entitlements 11 / site-binding 12 / integrations 20）。未再串跑一次 `composer check` 整包，成分与之相同。

**9. Heartbeat**

```
vendor/bin/phpunit --filter HeartbeatModuleTest
# OK (4 tests, 10 assertions)
```

**10. DashboardFeeds**

```
vendor/bin/phpunit --filter DashboardFeedsModuleTest
# OK (5 tests, 10 assertions)
```

`block` 去掉 `dashboard_primary`；feed 空串；`/events/1.0/` 短路；`https://api.stripe.com/` 不短路；`allow` 不挂钩。

**11. ClientProbe**

```
vendor/bin/phpunit --filter ClientProbe
# OK (5 tests, 17 assertions)
```

**12. 驻留方案 A + 保险**

```
vendor/bin/phpunit --filter ProfileGate
# OK (5 tests, 9 assertions)
```

未 mock geo。

**13. CI**

```
git push -u origin grok/m-scope-1
# * [new branch] grok/m-scope-1 -> grok/m-scope-1
gh run list --branch grok/m-scope-1
# in_progress  feat(diagnostics): read split avatar admin/frontend modes  CI  grok/m-scope-1  push  3402712431
```

未 push `main`。CI run **3402712431** 进行中。

**14. diff 范围**：见第 1 节；不含 `.grok-context/`，不含 `src/Admin/app/`。

## 3. 没做 / 做不到 / 有疑问

- **Studio D3 矩阵**：本机无 Studio / Docker，未改 `m4-upgrade-matrix-2026-09-06.md` 末尾「由 M-SCOPE-1 补跑」三行（S2 / D2 / S08）。单元已覆盖 S2 与 S08；D2 超大 `huge_blob` 未另造 Studio 样本。
- **任务书 `php -r` 打印 VERSION**：原句不 `define('ABSPATH')`，命中 `exit`，无 `2`。加上 `define("ABSPATH",".")` 后打印 `2`。
- **`--filter ReportFieldsTest` 不带 `--testsuite telemetry`**：suite 桩冲突，`wpcy_daily_telemetry` 断言失败；`--testsuite telemetry` 下 2 tests / 98 assertions 绿。`phpunit.xml.dist` 已说明跨 suite 不能同进程。
- **预计 diff ≤ 1500 行**：实际 `+3640 / −167`（51 files）。新增模块、REST、升级测试与既有断言改形状占了大部分。未自行砍范围。
- **geo 路径**：wenpai-net 未给合同，插件用可注入客户端 + 常量 `https://api.wenpai.net/v1/geo`，契约 `{ "country": "CN" }`。
- **`WordPressOrgModule` 既有 `wpcy_entitlement_allows` 包改写闸**：任务要求连通性不读配额。未删该既有 callable（删了会掉既有用例）。新 Heartbeat / DashboardFeeds / PublicAssets 运行时路径不读 entitlement。
- **M-UI-0 审查建议 1/2/5 已做**：`Runner::rollback()` 删 `wpcy_migration_report`；`log_items` 只投影四字段；补测 `source_version==='3.x'`、第二页 host 集合。`ChallengeClient::unavailable()` 中文在 `ChallengeFlowTest.php` 已有断言，未再复制一条。
- **决定 vs 任务书**：无矩阵单元格冲突。client-probe 允许名单按 `rest-api.md` 含 `www.gravatar.com` / `gravatar.com`（任务书只写了两条 URL）。

## 4. 提交哈希

```
d13d45f feat(diagnostics): read split avatar admin/frontend modes
a4d7cce feat(telemetry): include profile in the compatibility report
a6cc111 feat(rest): add profile suggest and client-probe endpoints
3737794 feat(migration): map 3.x admin token to admin_assets and emit v2
5dbedc1 feat(connectivity): gate rewrites by request scope and add heartbeat feeds
```

再往前两笔：`497c49c feat(residency): …`、`6ebe455 feat(config): …`。共 7 笔，均未包含 `.grok-context/`。

---

## Definition of Done

- [x] 规格 ↔ 实现对照表

| 规格编号 | 实现位置 | 状态 |
|----------|----------|------|
| D2 wordpress_org domestic=auto | `src/Config/Profile.php:102` | 已做 |
| D2 wordpress_org crossborder=off | `src/Config/Profile.php:121` | 已做 |
| D2 wordpress_org mixed=auto | `src/Config/Profile.php:140` | 已做 |
| D2 public_assets.items 五项 × 三列 | `src/Config/Profile.php:29-35,104-109,123-128,142-147` | 已做 |
| D2 public_assets.scope both/admin/admin | `src/Config/Profile.php:107,126,145` | 已做 |
| D2 avatar.admin 三列 cravatar_cn | `src/Config/Profile.php:109,128,147` | 已做 |
| D2 avatar.frontend cn/off/cravatar_global | `src/Config/Profile.php:110,129,148` | 已做 |
| D2 windfonts 三列 false | `src/Config/Profile.php:114-116,133-135,152-154` | 已做 |
| D2 heartbeat off/on/on | `src/Config/Profile.php:111,130,149` | 已做 |
| D2 dashboard_feeds allow/block/block | `src/Config/Profile.php:112,131,150` | 已做 |
| D2 admin_assets off/on/on | `src/Config/Profile.php:100,119,138` | 已做 |
| D3 admin token → admin_assets=on，不进 ignored | `src/Migration/Mappers.php:323-324,217-218` | 已做 |
| D3 报告中文 | `src/Migration/Report.php:129` | 已做 |
| GET /profile/suggest | `src/Rest/ProfileSuggestController.php:53` | 已做 |
| POST /diagnostics/client-probe | `src/Rest/ClientProbeController.php:87` | 已做 |
| GET /diagnostics/client-probe 空信封 | `src/Rest/ClientProbeController.php:73-75,125-140` | 已做 |
| 方案 A crossborder 不改道 | `src/Privacy/DataResidency/DataResidencyModule.php:183-185` | 已做 |
| telemetry profile | `src/Telemetry/Report.php:95` | 已做 |

- [x] 截图：非 UI，不适用。
- [x] 用户可见串：仅迁移报告「后台加速：已保留设置，4.1 起生效」（`admin-ui-spec.md` §4 已有）。无「遥测/隐私/上报」。
- [x] 空/错误/降级：geo 失败 → `suggestion: null`；权限拒绝 → `wpcy_forbidden`；client-probe 无记录 → `{checked_at:null,probes:[]}`；非法主机 → `wpcy_invalid_schema`；`profile≠domestic` 时 A 档不改道。
- [x] 测试：新行为有测试；`composer lint` / `composer analyse` / `npm run build` / `npm run lint:js` 绿。CI run **3402712431** 进行中。
- [x] `git diff --stat` 在允许路径内；`.grok-context/` 未入库。
- [x] 本节「没做 / 做不到 / 有疑问」已列；报告写完即退出（不等 CI）。
