# M-LOCALE-1 最终报告

执行者：Grok。分支 `grok/m-locale-1`。未 push。本地 `composer check` 绿。

## 1. 改了哪些文件

`git diff --stat`（含未跟踪，提交后）：

```
 src/Config/Defaults.php                                   |  11 +-
 src/Connectivity/AdminLocale/AdminLocaleModule.php        | 143 ++++
 src/Core/Plugin.php                                       |   2 +
 src/Migration/Mappers.php                                 |  11 +
 tests/Unit/Config/ProfileTest.php                         |  13 +
 tests/Unit/Config/RepositoryTest.php                      |   2 +
 tests/Unit/Config/ValidatorTest.php                       |  24 +
 tests/Unit/Connectivity/AdminLocale/AdminLocaleModuleTest.php | 199 +++++
 tests/Unit/Connectivity/HookStore.php                     |   8 +
 tests/Unit/Connectivity/wp-hook-stubs.php                 |  12 +
 tests/Unit/Core/PluginCreateTest.php                      |   1 +
 tests/Unit/Migration/FixturesTest.php                     |  35 +
```

不含 `.grok-context/`。不含 `src/Admin/app/**`（开关 UI 已在 M-UI-1c）。

## 2. 开关开 / 关行为对照

| 条件 | 后台 `is_admin()` | 前台 |
|------|-------------------|------|
| `admin_locale_follow=true`（默认） | `locale` / `determine_locale` 返回 `get_user_locale()` | 不注册模块；即便回调被调用也原样返回站点 locale |
| `admin_locale_follow=false` | `enabled()` 为 false，registry 不挂钩，WordPress 默认行为 | 同左，零干预 |
| `recovery_mode=true` | 同关：不挂钩 | 同关 |
| 场景切换 | `Profile::apply_to()` 不改此键，全场景共用 | — |
| 3.x `waimao_*` | 不迁移，不写入 4.0 文档 | — |

实现路径：

- 开：`AdminLocaleModule::enabled()` 为 true 且 `contexts() = [admin]` → `register()` 挂 `locale` 与 `determine_locale`（`src/Connectivity/AdminLocale/AdminLocaleModule.php:93-110`）。回调再以 `is_admin()` 二次门闩（同文件 `:125-126`），空用户 locale 不改写（`:137-138`）。`get_user_locale()` 可能再进 `get_locale()`，用 `$resolving` 防重入（`:121-123,133-135`）。
- 关：`enabled()` 返回 false（`:99`），内核 `ModuleRegistry::boot()` 跳过 `register()`，零过滤。

## 3. 验收命令与输出摘要

### 3.1 `composer check`

exit 0。phpcs 无输出。PHPStan `[OK] No errors`（102/102）。PHPUnit 全 suite：

| suite | 结果 |
|-------|------|
| smoke | OK (1 test, 3 assertions) |
| core | OK (23 tests, 70 assertions) |
| config | OK (85 tests, 410 assertions) |
| connectivity | OK (123 tests, 260 assertions) |
| telemetry | OK (3 tests, 270 assertions) |
| privacy | OK (33 tests, 146 assertions) |
| diagnostics | OK (13 tests, 123 assertions) |
| stats | OK (51 tests, 153 assertions) |
| cli | OK (8 tests, 42 assertions) |
| rest | OK (72 tests, 364 assertions) |
| integrations | OK (20 tests, 46 assertions) |
| migration | OK (46 tests, 711 assertions) |
| site-binding | OK (12 tests, 131 assertions) |
| apps | OK (68 tests, 210 assertions) |
| entitlements | OK (11 tests, 77 assertions) |
| admin | OK (19 tests, 70 assertions) |
| providers | OK (50 tests, 199 assertions) |

### 3.2 开 = 后台跟随用户、前台不变

```
vendor/bin/phpunit --testsuite connectivity --filter AdminLocale
OK (11 tests, 20 assertions)
```

含 `test_on_admin_follows_user_locale`（`en_US` → `zh_CN`）、`test_on_frontend_leaves_site_locale`、`test_apply_filters_locale_in_admin`、`test_apply_filters_locale_on_frontend`。

### 3.3 关 = 默认行为

同 suite：`test_off_does_not_enable`、`test_off_register_not_called_leaves_default`（`locale` / `determine_locale` 不入钩子袋）。

### 3.4 与 3.x `waimao_*` 无冲突

```
vendor/bin/phpunit --testsuite migration --filter waimao
OK (1 test, 19 assertions)
```

`waimao_enable` / `waimao_language_split` / `waimao_admin_language` / `waimao_frontend_language` / `waimao_auto_detect` 全部 ignored、`feature_removed`；4.0 `admin_locale_follow` 仍为默认 true；settings JSON 不含 `waimao` / `zh_CN`。

### 3.5 `rg "waimao" src/` 仅 Migration

```
src/Migration/Mappers.php:204:case 'waimao':
src/Migration/Mappers.php:205:case 'waimao_enable':
src/Migration/Mappers.php:206:case 'waimao_language_split':
src/Migration/Mappers.php:207:case 'waimao_admin_language':
src/Migration/Mappers.php:208:case 'waimao_frontend_language':
src/Migration/Mappers.php:209:case 'waimao_auto_detect':
```

## 4. 规格对照

| 规格 | 实现位置 | 状态 |
|------|----------|------|
| 开：后台用 `get_user_locale()` | `AdminLocaleModule.php:107-141` | 已做 |
| 仅 admin 上下文 | `contexts()` `:81-83`；回调 `is_admin()` `:125-126` | 已做 |
| 前台站点语言不变 | 不列 frontend；回调非 admin 原样返回 | 已做 |
| 关：零干预 | `enabled()` false → 不 `register()` `:99` | 已做 |
| 与场景无联动 | `Profile::apply_to()` 不覆盖该键 `Profile.php:75-79` | 已做 |
| 不做前台语言改写 | 无 frontend locale 过滤 | 已做 |
| 默认开 | `Schema.php:433-436`；`Defaults.php:46` | 已做 |
| 3.x `waimao_*` 不迁 | `Mappers.php:204-213` | 已做 |

截图：不适用（非 UI）。

## 5. 没做 / 做不到 / 有疑问

- 未改 `docs/specs/config-schema.md`：该文件仍缺 `admin_locale_follow`（M-UI-1c 已在 `Schema.php` 加键，规格仓滞后）。本任务按任务书接线，不补规格仓。
- 未做前台语言改写（任务明确不做）。
- 未迁 `waimao_*` 旧值到 `admin_locale_follow`（任务明确不迁）。
- REST AJAX 在 `is_admin()` 为 true 时会跟随用户 locale（与 WP 后台 AJAX 一致）。`Environment::REST` 场景不注册本模块；纯 REST 请求不改 locale。
- 未跑 `npm run build` / e2e / 视觉（无前端改动）。
- 未 push；无 CI run id。

## Definition of Done

- [x] 规格条目 ↔ 实现对照表
- [x] 截图：不适用
- [x] 无新增用户可见字符串
- [x] 关 = 零干预（不挂钩）；空用户 locale 保持传入值
- [x] 测试：`composer check` 绿
- [x] `git diff` 在允许路径；`.grok-context/` 未入库
- [x] 报告含没做；写完再退出
