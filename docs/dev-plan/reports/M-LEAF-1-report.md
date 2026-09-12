# M-LEAF-1 最终报告

未 push，无 CI run id。本地验收如下。

分支 `grok/m-leaf-1`，基于 `main` @ `8c95a1e`。

## 1. 改了哪些文件

`git diff --stat main`：

```
 docs/dev-plan/reports/M-LEAF-1-report.md        |  （本报告）
 docs/specs/rest-api.md                          |   2 +-
 scripts/lint-naming-keep.json                   |  （新）
 scripts/lint-naming.php                         |  （新）
 src/Admin/ElementHide/ElementHideModule.php     | 223 ++++++++++++++++++++++++---
 tests/Unit/Admin/AdminStore.php                 |   8 +
 tests/Unit/Admin/ElementHideTest.php            | 228 +++++++++++++++++++++++++++-
 tests/Unit/Admin/wp-admin-stubs.php             |  30 ++++
 tests/Unit/Config/wp-option-stubs.php           |  13 ++
```

`.grok-context/` 未入库。

## 2. 规格对照

| 规格编号 | 实现位置 | 状态 |
|----------|----------|------|
| §3.6 version 严格大于才替换；相等/更小丢弃，记 `stale_version` | `ElementHideModule.php` `refresh()` 验签后比较；诊断字段 `stale_version` | 已做 |
| §3.6 `schema_version` 存在且主版本 `>1` 整份不采用 | `schema_acceptable()`；缺省视为 v1 通过 | 已做 |
| §3.6 `protocol` 存在且非 `adblock-v1` 不采用 | `protocol_acceptable()`；缺省视为 `adblock-v1` 通过 | 已做 |
| §3.6 `ttl` 缺省 86400；陈旧上限仍看本地 `fetched_at` 硬顶 72h | `DEFAULT_TTL=86400`；`TTL=259200` 未改 | 已做 |
| §4.1 频率：每日 cron 一次 | `wp_schedule_event(..., 'daily', CRON_HOOK)` 未改 | 已做（既有，一致） |
| §4.1 管理端重试节流 ≥1h | `retry()` + option `wpcy_element_hide_last_fetch` | 已做（原先无节流） |
| §4.1 超时 10s；校验证书 | `timeout=>10` `sslverify=>true` 未改；确认无 `sslverify=false` | 已做（既有，一致） |
| §4.1 失败沿用上一份；超 72h 清空 | `keep_or_clear()` / `within_stale_window()` 未改 | 已做（既有，一致） |
| §4.1 请求不带身份；URL 无参数 | `is_anonymous_https_source()` 拒绝 query/user/pass；HTTP `headers`/`cookies` 空数组 | 已做 |
| §4.1 源 URL 默认空=禁用；常量预留 `https://wpcy.com/rulesets/element-hide.json` | `PRODUCTION_URL` 预留；`Plugin.php` filter 默认仍 `''` | 已做 |
| §4.5 命名审计脚本 + keep-list | `scripts/lint-naming.php` `scripts/lint-naming-keep.json` | 已做 |
| §4.5 全仓命中修复 | 浏览器面无禁词标识；命中均为规则选择器样例，进 keep-list | 已做（无需改名） |
| 诊断端点字段只增不改名 | `stale_version` 新增；既有 `enabled/version/issued_at/fetched_at/hits` 未改名 | 已做 |

## 3. 验收命令与输出

### 基线（动手前）

`vendor/bin/phpunit --testsuite admin`：

```
OK (37 tests, 114 assertions)
```

`composer test:unit`：全套绿（admin 当时 37）。

### `composer check`

exit 0。PHPStan `[OK] No errors`（106/106）。PHPUnit 全套绿。admin 现为 **49 tests, 148 assertions**。全仓合计 694 tests。

### `npm run build`

exit 0。`webpack 5.110.3 compiled with 2 warnings`（entrypoint size / runtimeChunk，既有）。

### `npm run lint:js`

exit 0（仅 ESLint v10 eslintrc 既有警告）。

### `php scripts/lint-naming.php`

```
lint-naming: clean
```

exit 0。

故意命中演示：

```
$ printf '%s\n' '<?php echo "<style id=\"ad-banner\">";' > /tmp/wpcy-ad-banner-demo.php
$ php scripts/lint-naming.php /tmp/wpcy-ad-banner-demo.php; echo HIT:$?
/tmp/wpcy-ad-banner-demo.php:0: ad in path "/tmp/wpcy-ad-banner-demo.php"
/tmp/wpcy-ad-banner-demo.php:0: banner in path "/tmp/wpcy-ad-banner-demo.php"
lint-naming: 2 hit(s)
HIT:1
$ rm -f /tmp/wpcy-ad-banner-demo.php
$ php scripts/lint-naming.php
lint-naming: clean
```

自测：

```
$ php scripts/lint-naming.php --self-test
lint-naming self-test: hit sample + keep-list allow + no admin/upload/loading false positive
```

exit 0。

### 版本协商演示（相等/更小保留旧缓存）

```
$ vendor/bin/phpunit --testsuite admin --filter 'test_equal_version_is_discarded|test_smaller_version_is_discarded|test_greater_version_is_adopted'
PHPUnit 9.6.19 by Sebastian Bergmann and contributors.

...                                                                 3 / 3 (100%)

Time: 00:00.010, Memory: 6.00 MB

OK (3 tests, 10 assertions)
```

- `test_greater_version_is_adopted`：v1 → v2 采用新文档
- `test_equal_version_is_discarded`：v1 → v1 保留 `issued_at=2026-09-11T00:00:00Z`，`stale_version=true`
- `test_smaller_version_is_discarded`：v3 → v2 保留 v3，`stale_version=true`

既有 M-NOTICE-1 测试（验签、72h、红线、开关关闭零输出）仍绿。

### `git log --oneline -5`

见提交后输出。

## 4. §4.1 审计明细

| 项 | 动手前 | 本轮 |
|----|--------|------|
| 频率 | 每日 cron 已有 | 未改 |
| 管理端重试节流 | 无 | `retry()` + `wpcy_element_hide_last_fetch`，1h 内第二次不拉 |
| 超时 10s | 已有 | 未改 |
| `sslverify` | `true` | 未改；显式 `headers=>[]` `cookies=>[]` |
| 失败沿用 / 72h 清空 | 已有 | 未改 |
| 身份 / URL 无参数 | 未断言 | 含 `?` 的源 URL 不发请求 |
| 源 URL 默认空 | 空常量 + filter 默认 `''` | 常量改为预留 URL；**运行时默认仍空**（filter 默认 `''`，不把常量当默认源） |

## 5. §4.5 命名审计

扫描：全仓 PHP/JS 路径；PHP 字符串中的 enqueue 句柄、`<style id=` / `<script id=`、`register_rest_route` 路由；JS DOM id/class；fixtures 里的规则 `id`/`class`/`selector`。

词素切分：`ad`/`ads`/`adblock`/`advert`/`banner`/`sponsor`/`promo`/`promotion`/`guanggao`、前缀 `gg_`。`admin`/`upload`/`loading` 不误伤（自测覆盖）。

浏览器面命中：**无**。`<style id="wpcy-element-hide">` 保持。PHP 类名、option/transient 名未改（服务端内部不受限）。

keep-list 放行的均为规则**选择器样例**（fixture / 单测里的 `woo-promo-notice`、`.acme-promo-banner` 等拦截目标）以及 3.x `adblock_rule` 遗留选择器（4.0 不迁移该键）。

## 6. 没做 / 做不到 / 有疑问

- 管理端「重试」目前没有 UI 按钮（禁区：不改 M-UI-1c/r2 版式）。节流做在 `ElementHideModule::retry()`；cron 仍走 `refresh()`（每日一次，不套 1h 节流）。UI/REST 接线不在本任务。
- 源 URL 运行时默认仍为空。`PRODUCTION_URL` 只是预留常量，`Plugin::create()` 仍 `apply_filters( 'wpcy_element_hide_source', '' )`，不会连生产。
- `composer.json` 没有 `test` 脚本，等价门是 `composer test:unit` / `composer check`。
- 未 push（`agents.md` 禁止代理 push），无 CI run id。
- 截图：非 UI 任务，不适用。

## Definition of Done

- [x] 规格对照表
- [x] 截图：不适用
- [x] 用户可见串：未改 UI 文案
- [x] 空/失败：验签失败保留上一份；超 72h 清空；version 非递增记 `stale_version` 且保留旧缓存
- [x] `composer check` / `npm run build` / `lint:js` 绿；新增单测绿
- [x] `git diff --stat` 在允许路径内
- [x] 报告含没做
