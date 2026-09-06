先读任务上下文、审查阻断项和当前分支状态，再按两处 CI 红分别修。审查文件很长，先定位「阻断」节，同时读任务上下文和两处失败相关代码。审查文件里没搜到「阻断」，接着看 REST 设置与头像读写，并核对 CI 失败细节。任务书猜测和现有测试对得上：PUT 单值 avatar 现在会判 schema 无效。接着读 REST、Validator 和审查阻断节。审查里有两条阻断，和两处 CI 红一并修。先读相关实现和测试。依赖已装好。开始改代码：先修 Scope 的 unslash，再处理 REST 头像兼容和审查阻断项。依赖已装好。开始改 Scope、REST 头像兼容，以及审查里的两处阻断。`present_legacy_avatar` 写坏了。REST 响应用字符串兼容冻结页，拆分值留在存储里；bootstrap 同样处理。`put()` 用了已投影的 `site_document()`，单值 avatar 会进校验。改成用存储文档做合并底稿。PUT 合并已改成用存储文档。接着重跑相关测试，再跑 `composer check`。PHPCS 禁止测试里写 `$GLOBALS['pagenow']`。改成走 `get_current_screen` 桩，避免覆盖 WordPress 全局。测试改走 `get_current_screen` 桩，不再给 `$GLOBALS['pagenow']` 赋值。`composer check` 已绿。按任务书拆成两个 commit：connectivity 与 REST。第二个提交只含 REST 文件。接着提交并 push。已推送。列出这次 CI run，不等它结束，先写最终报告。两处 CI 红已修并 push：`plugin-check` 的 unslash，以及冻结连接页写单值 `connectivity.avatar` 导致 PUT 400。审查阻断两项一并修了。CI run **34032056857** 进行中，本地验收如下。

## 1. 改了哪些文件

`git diff --stat d13d45f`：

```
 src/Admin/AdminModule.php                          |  3 +-
 src/Connectivity/Heartbeat/HeartbeatModule.php     | 29 ++++++-
 .../PublicAssets/PublicAssetsModule.php            | 51 +++---------
 src/Connectivity/Scope.php                         |  6 +-
 src/Rest/DocumentWriter.php                        | 90 ++++++++++++++++++++++
 src/Rest/SettingsController.php                    |  2 +-
 src/Services/Entitlements/EntitlementsModule.php   |  1 -
 .../Connectivity/Heartbeat/HeartbeatModuleTest.php | 18 ++++-
 tests/Unit/Connectivity/HookStore.php              |  8 ++
 .../Connectivity/PublicAssets/WhitelistTest.php    | 26 -------
 tests/Unit/Connectivity/scope-function-stubs.php   | 12 +++
 tests/Unit/Connectivity/wp-hook-stubs.php          | 15 ++++
 tests/Unit/Rest/AdminModuleTest.php                |  3 +
 tests/Unit/Rest/PermissionsTest.php                | 51 +++++++++++-
 14 files changed, 232 insertions(+), 83 deletions(-)
```

未改 `src/Admin/app/`。未加入 `.grok-context/`。

## 2. 验收命令与输出摘要

**原因核验（e2e E3）**  
任务书猜测成立。冻结页 `src/Admin/app/store/index.js` PUT `connectivity.avatar` 为字符串；schema v2 要求 `{admin, frontend}` 对象。原测试 `test_put_v1_shapes_are_schema_error` 把字符串 avatar 判成 `wpcy_invalid_schema` 400，保存失败，刷新后 radio 不是 off。

**plugin-check / unslash**  
`src/Connectivity/Scope.php:71-75`：`sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) )`，无 phpcs:ignore。

**旧单值写入**  
PUT 字符串枚举展开为 admin/frontend 同值，写入仍是 v2 对象。HTTP/bootstrap 因 JSON 同一键不能同时是字符串和对象，故 `connectivity.avatar` 为字符串（两侧不同时取 admin），拆分值在 `connectivity.avatar_admin` / `connectivity.avatar_frontend`。冻结页只读字符串键。

**`composer check`**（exit 0）：

```
[OK] No errors
OK (1 test, 3 assertions)      # smoke
OK (23 tests, 68 assertions)   # core
OK (82 tests, 419 assertions)  # config
OK (112 tests, 239 assertions) # connectivity
OK (3 tests, 270 assertions)   # telemetry
OK (16 tests, 73 assertions)   # privacy
OK (13 tests, 123 assertions)  # diagnostics
OK (8 tests, 42 assertions)    # cli
OK (47 tests, 255 assertions)  # rest
OK (20 tests, 46 assertions)   # integrations
OK (43 tests, 697 assertions)  # migration
OK (12 tests, 131 assertions)  # site-binding
OK (68 tests, 210 assertions)  # apps
OK (11 tests, 77 assertions)   # entitlements
OK (19 tests, 70 assertions)   # admin
```

新增/改写的单测：`test_put_legacy_avatar_string_expands_both_sides`、`test_put_invalid_avatar_string_is_schema_error`、`test_editor_interval_from_screen_without_enqueue`。删 `test_keeps_origin_when_entitlement_exhausted`。

**CI**  
push `grok/m-scope-1` → run **34032056857**（HEAD `e192041`），status `in_progress`。前次红 run 34027124310。本机无 Docker，e2e / plugin-check 只走 CI。

## 3. 没做 / 做不到 / 有疑问

- CI 尚未结束：run 34032056857 进行中，不能写「分支 CI 全绿」。
- JSON 无法在同一键同时返回字符串和对象；拆分值用兄弟键 `avatar_admin` / `avatar_frontend`。冻结页不读这两键。
- `docs/specs/rest-api.md` 仍写 PUT v1 字符串 avatar → 400；本任务未改规格文档。实现按任务书接受合法枚举字符串。
- 审查「建议」未做（client_probe_url 扩名单、`scope=frontend` 在 admin 不改写、迁移 `frontend` token、v1 两次升级、PHP 7.4 注释）。只修了「阻断」两节。
- 本任务未改 3.x 映射表 / `Mappers.php`。

## 4. 提交哈希

```
e192041 fix(rest): accept legacy connectivity.avatar string on PUT
bc696df fix(connectivity): unslash REST nonce, drop public-assets quota, editor Heartbeat via screen
d13d45f feat(diagnostics): read split avatar admin/frontend modes
a4d7cce feat(telemetry): include profile in the compatibility report
a6cc111 feat(rest): add profile suggest and client-probe endpoints
```

两个 commit：`fix(connectivity):` / `fix(rest):`。未 push `main`。

---

## DoD

- [x] 规格条目 ↔ 实现（本任务范围；3.x §5 ↔ Mappers 本任务未改）

| 规格编号 | 实现位置 | 状态 |
|----------|----------|------|
| plugin-check MissingUnslash on `HTTP_X_WP_NONCE` | `src/Connectivity/Scope.php:71-75` | 已做 |
| PUT 旧单值 `connectivity.avatar` 展开 admin/frontend 同值 | `src/Rest/DocumentWriter.php:194-216` | 已做 |
| 响应兼容冻结页（字符串 + 拆分） | `src/Rest/DocumentWriter.php:231-254`；bootstrap `src/Admin/AdminModule.php:298` | 已做（拆分走兄弟键，见上） |
| 审查阻断：公共库无配额闸 | `src/Connectivity/PublicAssets/PublicAssetsModule.php` 删 `entitlement_allows`；`EntitlementsModule.php` `RESTRICTED_SERVICES` 去 `admincdn` | 已做 |
| 审查阻断：Heartbeat 编辑器 60s | `src/Connectivity/Heartbeat/HeartbeatModule.php:136-175`（`pagenow` / `get_current_screen` / hook_suffix） | 已做 |
| 3.x 选项审计 §5 ↔ Mappers.php 逐键 | — | 未做：本任务不改迁移映射 |

- [x] 每个状态一张截图：不适用（非 UI）。
- [x] 面向用户字符串：本任务未新增用户可见文案。
- [x] 空/错误/降级：非法 avatar 字符串仍 `wpcy_invalid_schema`（`test_put_invalid_avatar_string_is_schema_error`）。
- [x] 测试：见上 `composer check`。CI run **34032056857** 进行中。未跑 `npm run build` / `npm run lint:js`（未改 JS）。
- [x] `git diff --stat` 在允许路径内；无 `.grok-context/`。
- [x] 本节「没做 / 疑问」已写；报告写完再退出。
