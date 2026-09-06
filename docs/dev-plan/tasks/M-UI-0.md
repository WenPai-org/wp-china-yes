# 任务 M-UI-0：产品化阶段的后端前置（非 UI）

worktree 分支 `grok/m-ui-0`，基于 `main`（M4-02 已合，`4674986` 之后）。预计 diff ≤ 500 行。**本任务不改任何 React/界面文件**（`src/Admin/app/` 禁区）；UI 代码冻结到原型定稿。

## 背景

预览站走查（`docs/dev-plan/verification/preview-site-review-2026-09-06.md`，两次）挖出的九加二条差距里，有几条根子在后端：规格写了的 REST 没注册、状态语义让界面永远进不到某个态、面向用户的错误串是英文、迁移报告没有可读取的接口。产品化 UI 一旦开工就需要这些就位。先读上述走查、`docs/specs/rest-api.md`、`docs/dev/copy-guidelines.md`、`docs/dev/task-book-template.md`（本任务按其 DoD 报告）。

## 交付物

| # | 缺口 | 做法 | 验收 |
|---|------|------|------|
| 1 | `GET /wpcy/v1/residency/ruleset`、`GET /wpcy/v1/residency/log` 规格有、未注册 | 在 `src/Rest/` 新增控制器，按 `rest-api.md` §`/residency/*` 的字段、分页（`page`/`per_page` 默认 20 上限 100）、权限（同 `diagnostics`）实现；数据源用 `src/Privacy/DataResidency/` 现有 ruleset 与 B 档记录存储；**不返回正文**，只有主机/类别/次数/最近时间 | 单元测试覆盖：无记录空列表、分页边界、权限拒绝；REST 索引含两条路由 |
| 2 | 小工具索引：空 `source` 被记为 `ok`，界面永远不出"目录暂时不可用" | `src/Apps/Index.php::refresh()`：空 source → `index_status = 'unconfigured'`（新枚举值），与 `ok`/`unreachable`/`invalid` 并列；`GET /apps` 的 `index_status` 透传；`AppsController` 文档注释更新；`docs/specs/rest-api.md` 与 `docs/specs/apps-manifest-and-bridge.md` 相应处加 `unconfigured` 一行（只加枚举说明，不改其它正文） | 单元：空 source → `unconfigured`；非空不可达 → `unreachable`；e2e A8 仍绿 |
| 3 | `ChallengeClient::unavailable()` 等面向用户的英文错误串 | 全仓 `rg -n "new WP_Error\(" src/` 逐条检查 message：凡可能到界面的，改为 `__()` 中文，按 `copy-guidelines.md` 模板"暂时无法 <做什么>，<下一步>"，并把每条登记进 `docs/design/admin-ui-spec.md` §4 词表（只允许在词表追加行）。`code` 不变 | 单元测试对 `ChallengeClient` 与 `AppsController` 的错误 message 断言中文；报告贴改动清单表 `| code | 旧 message | 新 message |` |
| 4 | 迁移报告只在 CLI 与 `wpcy_migration_backup` option，界面读不到 | 新增 `GET /wpcy/v1/migration/report`（权限同 `diagnostics`）：返回 `Report::to_array()` 的最近一次结果 + `migrated_at` + `source_version`（3.x 版本，若 `wp_china_yes` 内有）+ `ignored` 列表；无迁移历史返回 `{ "status": "none" }`。`Runner` 若尚未持久化报告，补存进 `wpcy_migration_backup` 同级键 `wpcy_migration_report`（**不加版本号进键名**） | 单元：有/无历史两态；`rest-api.md` 加该端点一节 |
| 5 | 恢复页 `<title>` 为空触发 PHP 8.2 Deprecated（`add_submenu_page( null, … )`） | `src/Admin/RecoveryPage.php`：父 slug 传空串或用 `add_options_page`/隐藏菜单的规范做法，页面标题传"文派叶子 · 恢复模式" | Studio 站 `debug.log` 无该 Deprecated；页面 `<title>` 非空 |

## 禁区

不改 `src/Admin/app/**`；不改现有 e2e 断言以外的测试期望；不改 3.x 相关（已删）；不新增用户可见英文；不 push `main`。

## 验收

1. `composer check` 绿；`npm run build` / `npm run lint:js` 绿；分支 CI 全绿（贴 run id）。
2. DoD 七项逐条（UI 截图项写"不适用"，但第 5 项要贴恢复页 `<title>` 的 curl 片段）。
3. 规格 ↔ 实现对照表按上表 1–5 编号。
4. 提交前缀 `feat(rest):` / `fix(apps):` / `fix(i18n):` / `feat(migration):` / `fix(admin):`；至少按上表分 3 笔。
