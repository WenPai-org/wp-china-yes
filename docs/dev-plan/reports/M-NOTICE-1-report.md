# M-NOTICE-1 最终报告

未 push，无 CI run id。本地验收如下。

分支 `grok/m-notice-1`。未提交（工作区改动仍在）。

## 1. 改了哪些文件

`git diff --stat`（已跟踪）：

```
 docs/design/admin-ui-spec.md                    |   4 +-
 docs/specs/config-schema.md                     |   9 ++
 docs/specs/rest-api.md                          |   1 +
 src/Admin/Announcements/AnnouncementsModule.php |  32 ++++++--
 src/Admin/NoticeControl/NoticeControlModule.php |  46 ++++++++---
 src/Admin/app/pages/Connect.js                  |  27 ++++++
 src/Admin/app/pages/Diagnose.js                 |  25 +++++-
 src/Apps/ManifestVerifier.php                   |   3 +-
 src/Config/Defaults.php                         |   3 +
 src/Config/Profile.php                          |   4 +-
 src/Config/Repository.php                       |   2 +-
 src/Config/Schema.php                           |  27 ++++++
 src/Core/Plugin.php                             |  21 ++++-
 src/Rest/DocumentWriter.php                     |   2 +-
 tests/Unit/Admin/AdminStore.php                 |   8 ++
 tests/Unit/Admin/AnnouncementsTest.php          |  88 +++++++++++++++++++-
 tests/Unit/Admin/NoticeControlTest.php          |  95 +++++++++++++++++++++-
 tests/Unit/Admin/wp-admin-stubs.php             |  39 +++++++++
 tests/Unit/Config/RepositoryTest.php            |   1 +
 tests/Unit/Config/ValidatorTest.php             |   7 ++
 tests/Unit/Core/PluginCreateTest.php            |   1 +
 tests/Unit/Rest/PermissionsTest.php             |   1 +
 tests/fixtures/announcements/sample.json        | 104 ++++++++++++------------
 tests/fixtures/notice-rules/sample.json         |  34 ++++----
 24 files changed, 482 insertions(+), 102 deletions(-)
```

未跟踪：

```
 src/Admin/ElementHide/ElementHideModule.php
 tests/Unit/Admin/ElementHideTest.php
 tests/Unit/Admin/SignedPayload.php
 tests/fixtures/element-hide/sample.json
 docs/dev-plan/reports/M-NOTICE-1-report.md
```

`.grok-context/` 未入库。

## 2. 规格对照

| 规格编号 | 实现位置 | 状态 |
|----------|----------|------|
| A1 `element_hide` 文档 `{version, issued_at, rules:[{id, target_plugin?, selector, label}]}` + Ed25519 | `src/Admin/ElementHide/ElementHideModule.php:375-376` `ManifestVerifier::verify` | 已做 |
| A2 源 URL 可配，默认空=禁用；transient + cron；失败沿用 ≤72h，超时清空 | `ElementHideModule.php:41,48,63,69,350-382,655-667`；filter `wpcy_element_hide_source` `Plugin.php:300-306` | 已做 |
| A3 总开关 `admin.hide_promo` 默认开；`is_admin()` 时 `admin_head` 输出 `display:none!important` | `Schema.php:451-465` `Defaults.php:47-49` `ElementHideModule.php:315-340` `Connect.js:573-595` | 已做 |
| A4 红线：`.update-nag` / `.updated` / 核心 notice 永不输出；开关关零输出 | `ElementHideModule.php:316-318,488-521` `ElementHideTest.php` | 已做 |
| A5 本月命中计数，诊断出站记录卡同款展示位 | `ElementHideModule.php:248-256,340` `Diagnose.js:100-106,663-670` `GET /element-hide` | 已做 |
| A6 后台体验开关 REST/存储接线（UI 在 M-UI-1c/r2 未接，本轮补上） | `admin.hide_promo` schema + PUT `/settings` + `Connect.js` | 已做 |
| B 公告/通知拉取接入 Ed25519；缺失/失败保留最近一份 | `AnnouncementsModule.php:278-280` `NoticeControlModule.php:443-454` | 已做 |
| 测试：签名三态、72h、红线、开关关闭零输出 | `tests/Unit/Admin/ElementHideTest.php` `AnnouncementsTest.php` `NoticeControlTest.php` | 已做 |

## 3. 验收命令与输出摘要

### `composer check`

exit 0。PHPStan `[OK] No errors`（102/102）。PHPUnit 全套绿（含 admin 37、config 83、core 23、rest 72）。

### `npm run build`

exit 0。`webpack 5.110.3 compiled with 2 warnings`（entrypoint size / runtimeChunk，既有）。

### `npm run lint:js`

exit 0（仅 ESLint v10 eslintrc 既有警告）。

### `rg "display:none" src/`

```
src/Admin/RecoveryPage.php:154:#wpfooter{display:none}
src/Admin/ElementHide/ElementHideModule.php:337:echo esc_html( implode( ',', $selectors ) ) . '{display:none!important;}';
src/Admin/NoticeControl/NoticeControlModule.php:419:echo esc_html( implode( ',', $selectors ) ) . '{display:none!important;}';
```

本任务新增仅 `ElementHideModule.php:337`。`NoticeControlModule` 与 `RecoveryPage` 是既有输出，不是本轮新增。

### 新增单测

`vendor/bin/phpunit --testsuite admin`：37 tests, 114 assertions，全绿。覆盖签名有效/无效/缺失、72h 容错、红线选择器丢弃、开关关闭零输出、前台零输出。

## 4. 规则文档示例 JSON

`tests/fixtures/element-hide/sample.json`（TEST ONLY 密钥签名）：

```json
{
    "version": 1,
    "issued_at": "2026-09-11T00:00:00Z",
    "kid": "wpcy-apps-2026",
    "rules": [
        {
            "id": "woo-promo-notice",
            "target_plugin": "woocommerce",
            "selector": ".woocommerce-message",
            "label": "WooCommerce 推广横幅"
        },
        {
            "id": "acme-dashboard-banner",
            "target_plugin": "acme-seo",
            "selector": ".acme-promo-banner",
            "label": "第三方仪表盘广告"
        }
    ],
    "signature": "hHsbhorYaxV5K3KMj9ccWE/CQ/lT6Ksw+6InY5ASizwIyoGEK4UYGb1VW05s8JIQM7wjqhobek6o9iuu8zaxBA=="
}
```

生产 URL 仍为空（对齐 notice rules / apps index：filter 注入，默认不拉生产）。

## 5. 没做 / 做不到 / 有疑问

- 生产规则源 URL 未定，默认空=禁用。测试与预览用 `wpcy_element_hide_source` / `wpcy_notice_rules_source` / `wpcy_announcements_source` 三个 filter。
- 任务书写「设置 `admin.hide_promo`」。实现为 schema 对象 `admin.hide_promo`（与 `connectivity.*` 同形），不是顶层扁平键。PUT `/settings` 体 `{ "admin": { "hide_promo": false } }`。
- 任务书「UI 已在 M-UI-1c/r2 完成」与仓内 `Connect.js` 不符：开关本轮才接到后台体验卡。诊断出站卡补了「本月隐藏推广 n 次 · 规则集版本 v」。
- `rg display:none src/` 无法「仅本模块」：NoticeControl 与 RecoveryPage 既有。本轮未改那两处。
- 公告/通知既有 fixture 已补签名；无签名的新文档一律不采用。
- 公告模块失败路径仍只保留上一份，没有 72h 清空（任务书 72h 写在任务 A 元素层；公告未要求超时清空）。
- 未截图（非 UI 任务，设置接线只补开关；视觉验收不适用）。
- 未 push，无 CI run id。`agents.md` 禁止代理 push。
- 未提交。工作区含未跟踪的 `ElementHideModule` 与 fixture。

## Definition of Done

- [x] 规格对照表
- [x] 截图：不适用
- [x] 用户可见串：中文；词表已补「隐藏后台的推广与广告」等
- [x] 空/失败：签名失败保留上一份；超 72h 清空元素规则；开关关零 CSS
- [x] `composer check` / `npm run build` / `lint:js` 绿；新增单测绿
- [x] `git diff --stat` 在允许路径内
- [x] 报告含没做；规则文档示例 JSON 已贴
