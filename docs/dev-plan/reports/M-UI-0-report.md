先读任务上下文和规格，再装依赖，不改 UI。任务书和规格先读完，同时装依赖。继续读现有测试、迁移与错误串实现，确认改法。依赖已装好。开始写 residency 控制器、Ruleset 导出和测试。接着改空 source 的 `unconfigured` 状态。刚才 composer 把额外 `--testsuite` 套进了每一套。改成分套直接跑。标题已非空。开始按前缀分提交。M-UI-0 后端五项已提交并 push 到 `grok/m-ui-0`。CI run **34021801112** 进行中，本地验收如下。

## 1. 改了哪些文件

```
 docs/design/admin-ui-spec.md                       |  16 ++
 docs/specs/apps-manifest-and-bridge.md             |   2 +-
 docs/specs/rest-api.md                             |  23 ++-
 src/Admin/AdminModule.php                          |   2 +-
 src/Admin/RecoveryPage.php                         |  14 +-
 src/Apps/DataStore.php                             |   6 +-
 src/Apps/Index.php                                 |  18 ++-
 src/Apps/Registry.php                              |   2 +-
 src/Migration/Runner.php                           |  74 +++++++++
 src/Privacy/DataResidency/Ruleset.php              |  23 +++
 src/Rest/AppsController.php                        |  19 +--
 src/Rest/MigrationReportController.php             |  62 ++++++++
 src/Rest/ResidencyController.php                   | 144 +++++++++++++++++
 src/Rest/RestError.php                             |   6 +-
 src/Rest/RestModule.php                            |  44 +++++-
 src/Services/Entitlements/Client.php               |   2 +-
 src/Services/SiteBinding/ChallengeClient.php       |   4 +-
 src/Services/SiteBinding/SiteBindingModule.php     |   2 +-
 tests/Unit/Apps/DataIsolationTest.php              |  75 +++++++++
 tests/Unit/Apps/VerifyTest.php                     |  28 ++++
 tests/Unit/Rest/MigrationReportControllerTest.php  |  92 +++++++++++
 tests/Unit/Rest/PermissionsTest.php                |   7 +-
 tests/Unit/Rest/ResidencyControllerTest.php        | 170 +++++++++++++++++++++
 .../Services/SiteBinding/ChallengeFlowTest.php     |   9 +-
 24 files changed, 806 insertions(+), 38 deletions(-)
```

未改 `src/Admin/app/**`。未把 `.grok-context/` 加入 git。

## 2. 验收命令与输出摘要

### `composer check`

exit 0。PHPCS 无输出；PHPStan `[OK] No errors`（75/75）；15 个 PHPUnit suite 全绿，含：

- rest 32 tests / 183 assertions（含空 log、分页 20/5/0、`per_page=101` 上限 100、权限拒绝、两条 residency 路由、有/无迁移历史）
- apps 68 tests / 210 assertions（空 source → `unconfigured`；非空不可达 → `unreachable`；AppsController 中文 message）
- migration 39 tests / 593 assertions
- site-binding 12 tests / 131 assertions（`ChallengeClient::unavailable()` message 为「暂时无法连接文派服务，请稍后重试。」）

### `npm run lint:js` / `npm run build`

均 exit 0。lint 仅有 ESLint v10 eslintrc 既有警告。build：`webpack 5.110.3 compiled with 1 warning`（vendor 体积，既有）。

### 分支 CI

```
gh run list --branch grok/m-ui-0
in_progress  fix(admin): register recovery page under options.php so <title> is set  CI  grok/m-ui-0  push  34021801112
```

CI run **34021801112** 进行中，未等全绿。

### 项 1–5 对照

| # | 命令/证据 | 摘要 |
|---|---|---|
| 1 | `./vendor/bin/phpunit --testsuite rest --filter Residency` | 空列表 `items=[]`；25 条默认 20、page=2 为 5、page=3 为空、`per_page=101` 仍 25（上限 100）；无 cap 时 403 `wpcy_forbidden`；路由含 `/residency/ruleset` 与 `/residency/log` |
| 2 | `./vendor/bin/phpunit --testsuite apps --filter 'unconfigured\|unreachable'` | 空 source → `unconfigured`；fetcher 返回空串 → `unreachable`；`GET /apps` 透传。e2e A8 未在本机跑（无 Docker）；A8 仍 mock `unreachable`，断言未改 |
| 3 | apps + site-binding 上述测试 | 见下表。`code` 未改 |
| 4 | `./vendor/bin/phpunit --testsuite rest --filter MigrationReport` | 无历史 `{status: none}`；execute 后含 `kept/ignored/ignored_reasons/settings/migrated_at/source_version`；`wp_china_yes.version=3.9.3` 时 `source_version=3.9.3` |
| 5 | Studio `wpcy-40-preview` curl | 见下 |

恢复页 curl 片段（`http://localhost:8890/wp-admin/admin.php?page=wpcy-recovery`，studio-auto-login 后）：

```html
<title>文派叶子 · 恢复模式 &lsaquo; WPCY 4.0 预览站 &#8212; WordPress</title>
```

h1 仍是「文派叶子 · 恢复模式」。本次请求后 `debug.log` **没有新的** `strip_tags(): Passing null`（最后一条仍是 `06-Sep-2026 04:53:24 UTC`，早于本次 08:22 访问）。

空串父 slug 试过：Deprecated 没了，但 `<title>` 仍空（`get_admin_page_title()` 找不到 submenu）。按任务允许的隐藏菜单做法改成父 slug `options.php` 后标题才有值。

### WP_Error 改动清单

| code | 旧 message | 新 message |
|---|---|---|
| `wpcy_binding_unavailable` | Site binding is not available. | 暂时无法连接文派服务，请稍后重试。 |
| `wpcy_binding_start_failed` | Site binding request failed. | 暂时无法完成站点绑定，请稍后重试。 |
| `wpcy_entitlements_unavailable` | Quota status is not available. | 暂时无法读取权益配额，请稍后重试。 |
| `wpcy_binding_not_pending` | No pending site-binding challenge. | 暂时无法验证站点绑定，请重新发起绑定。 |
| `wpcy_forbidden` | You are not allowed to access this resource. | 暂时无法访问该内容，请确认你有管理权限。 |
| `wpcy_invalid_schema` | The request body does not match the settings schema. | 暂时无法保存设置，请检查填写内容后重试。 |
| `wpcy_recovery_unknown_action` | Unknown recovery action. | 暂时无法执行恢复操作，请刷新页面后重试。 |
| `wpcy_apps_unknown_app` | Unknown app. | 暂时无法打开该小工具，请刷新目录后重试。 |
| `wpcy_apps_key_invalid`（404） | The requested data key was not found for this app. | 暂时无法读取该数据，请检查后重试。 |
| `wpcy_apps_key_invalid`（400） | The data key is invalid. | 暂时无法保存该数据，请检查键名后重试。 |
| `wpcy_apps_payload_too_large` | The request body exceeds 64KB. | 暂时无法保存该数据，内容超过 64KB。 |
| `wpcy_apps_forbidden_permission` | This app is not allowed to use that permission. | 暂时无法完成该操作，该小工具没有相应权限。 |
| `wpcy_apps_entitlement_required` | This app requires an entitlement. | 暂时无法使用该小工具，请先获取权益。 |
| `wpcy_apps_quota_exceeded` | The entitlement quota is exhausted. | 暂时无法使用该小工具，本期配额已用尽。 |

另：`RecoveryPage` / `AdminModule` 的 `wp_die( 'Forbidden.' )` 改为对应中文（不是 `WP_Error`，但是用户可见英文）。

## 3. 没做 / 做不到 / 有疑问

- **CI 未等全绿**：run `34021801112` 进行中。
- **e2e A8 本机未跑**：无 Docker。A8 继续 mock `index_status: unreachable`，未改 e2e 断言。
- **现界面仍不会因空 source 出琥珀条**：本任务不改 `src/Admin/app/**`。前端现在认 `unreachable`，不认新枚举 `unconfigured`。后端已透传，UI 产品化再接。
- **已迁移过、没有 `wpcy_migration_report` 的站**：GET 返回 `{status: none}`。只在新的 `Runner::execute()` 写入；不从旧 `wpcy_migration_backup` 拼一份假 `Report::to_array()`。
- **`wp_china_yes` 一般没有版本字段**：`source_version` 读 `version` / `plugin_version`，没有则 `3.x`（与 backup `from_version` 相同）。
- **diff 806 插入 / 38 删除，超过任务「预计 ≤ 500 行」**：多在新控制器和单元测试；未为压行数删测试。
- **补充规则写「分两个 commit」，任务正文写「至少按上表分 3 笔」**：按任务正文做了 5 笔（五个前缀各一）。
- **禁区「不改现有 e2e 断言以外的测试期望」**：`PermissionsTest` 里恢复页 parent 从 `null` 改成 `options.php`，否则与实现不一致。这是行为变更带来的必要改动。
- **通用 DoD「3.x 选项审计 §5 ↔ Mappers.php 逐键」**：本任务未改 `Mappers.php`，对照表按任务 1–5 编号。
- **`debug.log` 里仍有 textdomain 过早加载 Notice**（WP 6.7+）：预览走查已记，不在本任务范围。
- **未把 `wpcy_migration_report` 写进 `Schema` 五键**：与 `wpcy_residency_log` 一样作为旁路 option；任务要求键名不加版本号，未扩 config schema。

## 4. 提交哈希

```
87bea08 fix(admin): register recovery page under options.php so <title> is set
669503a feat(migration): persist last report and expose GET /migration/report
8f060e3 fix(i18n): use Chinese user-facing WP_Error messages
df3a5bb fix(apps): treat empty index source as unconfigured
ad75bcc feat(rest): register residency ruleset and log endpoints
```

## 规格 ↔ 实现对照表

| 规格编号 | 实现位置 | 状态 |
|----------|----------|------|
| 1 `/residency/ruleset` `/residency/log` | `src/Rest/ResidencyController.php:67,81`；注册 `src/Rest/RestModule.php:182,191`；`Ruleset::to_rest()` `src/Privacy/DataResidency/Ruleset.php:127` | 已做 |
| 2 `index_status=unconfigured` | `src/Apps/Index.php:141,194,205`；透传 `src/Rest/AppsController.php:188`；规格 `docs/specs/rest-api.md:38`、`docs/specs/apps-manifest-and-bridge.md:157` | 已做（UI 未接该枚举） |
| 3 面向用户 WP_Error 中文 | `ChallengeClient.php:237,344`、`AppsController.php` 各 `RestError::make`、`RestError.php:86,101,116`；词表 `docs/design/admin-ui-spec.md:122-137` | 已做 |
| 4 `GET /migration/report` | `src/Rest/MigrationReportController.php:53`；持久化 `src/Migration/Runner.php:114,183`；规格 `docs/specs/rest-api.md:149` | 已做 |
| 5 恢复页 `<title>` | `src/Admin/RecoveryPage.php:85` 父 slug `options.php`，标题「文派叶子 · 恢复模式」 | 已做（空串不够，改用任务允许的 `options.php`） |
| 3.x 审计 §5 ↔ `Mappers.php` | — | 未做：本任务不改映射 |

## DoD

- [x] 规格条目 ↔ 实现对照表：见上。未做项已列出。
- [x] 每个状态一张截图：不适用（非 UI）。项 5 用 curl `<title>` 代替。
- [x] 面向用户字符串：中文、已追加 §4 词表；无新增用户可见英文；无「遥测/隐私/上报」。
- [x] 空/错误/降级：空 residency log；`unconfigured`；无迁移 `{status:none}`；绑定/小工具错误中文。诊断出站主机 UI 仍是 EmptyTable（本任务只提供 REST，不改 React）。
- [x] 测试：新增行为有单元测试；`composer check` 与 `npm run build`、`npm run lint:js` 通过；CI run `34021801112` 进行中。
- [x] `git diff --stat` 在允许路径内；`.grok-context/` 未入库。
- [x] 「没做 / 做不到 / 有疑问」已写；报告写完再退出。
