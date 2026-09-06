先按审查技能和任务书做只读核对，再对照 `ca568ace` 之后的 diff 逐项验证。用户指定了审查范围和中文报告格式，我按该要求只读核对，并先拉取提交、任务书和规范。提交信息对应 `M-UI-0`。接下来读任务书、规范和完整 diff。任务书已定位为 M-UI-0。接着读改动文件、对应 spec，并跑验收命令。这是 **M-UI-0**（`ca568ace..87bea08`，分支 `grok/m-ui-0`）。五项交付在代码层都有对应实现，**没有阻断级缺陷**；`composer check` 退出码 0。本审查未改任何文件。

审查范围：`git diff ca568ace4ae6447e1fadcf1fc5985fb7fe0a816b HEAD`（24 files, +806 / −38）。提交五笔：`feat(rest)` / `fix(apps)` / `fix(i18n)` / `feat(migration)` / `fix(admin)`，与任务书前缀要求一致。对照 `docs/dev-plan/tasks/M-UI-0.md`。

---

## A 正确性

五项交付物在 `src/` 里都有实现，任务书表格 1–5 没有缺件；验收命令里 **Studio `<title>` / debug.log、npm、e2e A8、CI run id** 本审查没有复跑。

| # | 任务书 | 实现 | 状态 |
|---|--------|------|------|
| 1 | `GET /residency/ruleset`、`/residency/log` | `src/Rest/RestModule.php:183-211` 注册；`ResidencyController.php`；权限 `manage_options_read`（同 diagnostics，`RestModule.php:166-171` vs `185-199`） | 已做 |
| 2 | 空 source → `unconfigured` | `Index.php:114-116,143-145,194-197,209-211`；`GET /apps` 透传 `AppsController.php:185-189` | 已做 |
| 3 | 面向用户 WP_Error 中文 + 词表 | `src/` 内 `__('…英…')` 已无匹配；§4 追加 16 行（`admin-ui-spec.md:122-137`） | 已做 |
| 4 | `GET /migration/report` + option `wpcy_migration_report` | `RestModule.php:213-221`；`Runner.php:30,106,118-128,198-221` | 已做 |
| 5 | 恢复页 parent / 标题 | `RecoveryPage.php:82-92`：`add_submenu_page('options.php', $title, …)`，$title = `文派叶子 · 恢复模式` | 代码已做；Studio 未验证 |

漏项 / 弱项：

- 任务书第 5 项验收是「Studio `debug.log` 无 Deprecated」和「页面 `<title>` 非空」。单元测试只断言 `add_submenu_page` 的 `page_title` 参数（`PermissionsTest.php:263-271`），不经过 `admin-header.php` 的 `strip_tags($title)`。本审查未进 Studio。
- `source_version` 只读 `wp_china_yes` 顶层 `version` / `plugin_version`（`Runner.php:182-190`）。真实 3.x option 没有这两键；fixtures 把版本放在 `_fixture.plugin_version`。单元测试是人工塞 `'version' => '3.9.3'`（`MigrationReportControllerTest.php:57-61`）。生产升级站会落到 `'3.x'`，与 spec 回退句一致，不是漏实现。
- `rollback()` 删 backup / 4.0 option，**不删** `wpcy_migration_report`（`Runner.php:139-154`）。spec 写的是「最近一次 `execute()`」，严格说不算漏，回滚后界面仍会读到旧报告。
- 预估 ≤500 行，实际 +806。README 上限 1500，未破硬限。
- 任务书要求 `npm run build` / `lint:js`、e2e A8、CI run id。本审查只跑了 `composer check`。A8 本身 mock `index_status: 'unreachable'`（`tests/e2e/apps.spec.js:458-465`），这次 PHP 改动碰不到那条断言。现有 UI 用 `status !== 'ok'`（`Services.js:581`），空 source 变成 `unconfigured` 后，不改 React 也会出「小工具目录暂时不可用」。

---

## B 运行时风险

新路径没有明显 Fatal；PHP 下限已经是 **8.0**，不是 7.4。多站点报告权限和空 source 缓存是主要边角。

**PHP 版本（审查提纲里的 7.4）：** 仓内已声明 8.0，不是 7.4。

```
composer.json:6    "php": ">=8.0"
composer.json:33   "platform": { "php": "8.0.0" }
wp-china-yes.php:13  Requires PHP: 8.0
本机 php -v: PHP 8.4.7
```

新代码仍避开 union 类型（`ResidencyController.php:54` 构造函数无类型，注释沿用 7.4 写法），没有 `readonly` / `enum` / `str_contains`。按当前 8.0 下限没有兼容问题。

**Fatal / Warning：**

- `ResidencyController::log_items` 的 `usort` 读 `$a['last_seen']`（`ResidencyController.php:104-110`）。生产路径走 `DataResidencyModule::sanitize_log`（`DataResidencyModule.php:345-364`），四字段必在，空字符串而非 null，PHP 8 不会在 `strcmp` 上 Warning。
- `Ruleset` 文件不可读或验签失败时 `to_rest()` 返回 `ruleset_version=0`、`tiers=[]`（`Ruleset.php:126-139`），HTTP 仍 200，不是 Fatal。
- 分页：`page<1` → 1，`per_page<1` → 20，`>100` → 100（`ResidencyController.php:122-143`）。`(int)'abc'===0` 走默认。无溢出 Fatal。

**多站点：**

- 迁移报告：`is_multisite()` 时写/读 `get_site_option` / `update_site_option`（`Runner.php:119-121,212-215`）。网络执行后报告在网络 option。
- REST 权限仍是 `manage_options_read`（`RestModule.php:218-219`），与任务书「同 diagnostics」一致。多站点子站管理员因此能读到 **网络 settings 快照**（`execute()` 在 MS 下 `save_option(Schema::NETWORK_SETTINGS)`，`Runner.php:104-105`）。diagnostics 不返回这份文档，这是新暴露面。
- B 档 log 仍是 `get_option('wpcy_residency_log')`（`DataResidencyModule.php:326`），按站点、不是网络。REST 新实例从 option 再读，与内核模块内存态分离，同请求内 `record()` 已 `persist_log()`，跨请求一致。

**空 source 缓存：** `refresh()` / `apps()` 在 source 为空时仍 `return $this->cached()`（`Index.php:143-145,209-211`）。默认 source 是 `apply_filters('wpcy_apps_index_source', '')`（`AppsModule.php:66-69`），空 source 的旧 `refresh()` 并不 `store()`，新鲜安装不会有脏 transient。若以前配过 source 又清空，可能同时出现 `unconfigured` + 旧 apps 列表。

---

## C 规范

PSR-4、`strict_types`、WPCS（`composer lint` 退出 0）、禁区均守住；`wp_china_yes` 只出现在迁移 Runner，是任务书第 4 项要求的读，不是 4.0 运行时当配置源。

- 新类 `WenPai\ChinaYes\Rest\{ResidencyController,MigrationReportController}` ↔ `src/Rest/*.php`，一类一文件，均有 `declare(strict_types=1)`。
- 未改 `src/Admin/app/**`；diff 无名 `framework/`。
- 用户可见字符串无「遥测 / 匿名数据 / 隐私 / 上报」。`admin-ui-spec.md:13,139` 只是禁用词表本身。
- 商业跳转仍是 `https://wpcy.com/go/`（`AppsController.php:376`）。`Index::PRODUCTION_URL = 'https://apps.wpcy.com/index.json'` 是既有第一方面，本次只在 `VerifyTest.php:152` 当不可达 fixture，fetcher 短路，不发 HTTP。
- `Runner` 读 `wp_china_yes` 经 `LegacyReader`（`LegacyReader.php:22-34`），不写回。注释写明 `Never writes 4.0 values into wp_china_yes`（`Runner.php:21`）。
- 新 option `wpcy_migration_report` 直接 `update_option`（`Runner.php:219-220`），不经 `Config\Repository`。与既有 `wpcy_migration_backup`（`Backup.php:77-78`）同一模式；`Schema` 未登记该键（备份键有 schema，报告键没有）。
- 模块构造函数仍不注册钩子；路由在 `RestModule::register_routes()`。

---

## D 安全

新端点是只读 + `manage_options`；B 档 log 有四字段消毒；报告不含凭据。多站点用 `manage_options` 读网络 settings 快照是唯一需要产品确认的权限面。

- **Capability：** `/residency/*`、`/migration/report` 均 `Permissions::manage_options_read`（`RestModule.php:185-220`）。与 diagnostics GET 相同。写操作未新增。
- **Nonce：** 读路径不验 `X-WP-Nonce`，与既有 GET `/diagnostics` 相同（`Permissions.php:34-40`）。`security.md` 要求写操作 capability + nonce。Cookie + CORS 下 GET 泄漏面与旧诊断接口同级。
- **Sanitize：** `page` / `per_page` `(int)` + 上下限（`ResidencyController.php:122-143`）。恢复页 POST 仍 `sanitize_key` + `check_admin_referer`（`RecoveryPage.php:109-110`），本次只改文案。
- **Escape：** 恢复页标题走 `__()` / `esc_html__()`（`RecoveryPage.php:83,138`）。REST JSON 不 HTML 输出。
- **凭据：** `Report::to_array()` 注释「No credentials」（`Report.php:18,111-124`）。`settings` 是 4.0 配置文档，不是 `wpcy_site_identity.binding.credential`。`update_option(..., false)` 关闭 autoload（`Runner.php:220`）。
- **远程请求：** 无新 `wp_remote_*`。Index 既有调用仍 `timeout => 10, sslverify => true`（`Index.php:302-308`）。
- **B 档 log 不回正文：** `sanitize_log` 只留四字段（`DataResidencyModule.php:355-360`）。测试故意写入 `'body' => 'must-not-leak'` 后断言 keys 只有四项（`ResidencyControllerTest.php:81-84,165`）。控制器本身不再投影一次，依赖模块消毒。
- **ruleset REST：** `to_rest()` 只返回 `ruleset_version` / `issued_at` / `tiers`（`Ruleset.php:135-139`），不含 `signature`、`_comment`。`tiers` 含 A 档 `target`（基线里有 `updates.wenpai.net`），admin 只读，与 spec「档位、条目」一致。

---

## E 测试质量

`composer check` 绿；新测覆盖任务书点名的空列表 / 分页上下限 / 权限拒绝 / 有无迁移历史 / 空 source；有几处弱断言和未覆盖分支，没有「断言恒真」的假通过。

**本审查命令：**

```
composer check    # exit 0，约 169s
composer lint     # exit 0，无输出
./vendor/bin/phpunit --testsuite rest --filter 'ResidencyControllerTest|MigrationReportControllerTest'
                  # OK (8 tests, 41 assertions)
```

`composer check` 摘要（phpstan `[OK] No errors`；各 suite 全绿）：

| suite | 结果 |
|-------|------|
| phpstan | No errors（75 files） |
| smoke 1 / core 23 / config 40 / connectivity 92 / telemetry 3 / privacy 11 / diagnostics 13 / cli 8 | OK |
| rest **32** tests, 183 assertions | OK |
| integrations 20 / migration 39 / site-binding 12 / apps **68** / entitlements 11 / admin 19 | OK |

任务书点名的覆盖：

- 无记录空列表：`ResidencyControllerTest.php:46-54`
- 分页默认 20、cap 100、越界空页、`per_page=0`：`77-108`（只数条数，不断言第二页是哪 5 个 host）
- 权限拒绝：`113-134`（只测 deny，不测有 `manage_options` 时放行）
- REST 索引含两条 residency + migration：`139-148` 与 `PermissionsTest.php:250-252`
- 空 source → `unconfigured`：`VerifyTest.php:137-144`、`DataIsolationTest.php:335-340`
- 非空不可达 → `unreachable`：`VerifyTest.php:149-160`
- ChallengeClient / AppsController 中文 message：`ChallengeFlowTest.php:218-233`、`DataIsolationTest.php:115-172`
- 迁移有/无历史：`MigrationReportControllerTest.php:45-77`

弱项 / 未覆盖：

- `test_ruleset_has_version_tiers_without_signature` 不断言 `ruleset_version > 0` 或 `verified()`（`ResidencyControllerTest.php:59-71`）。验签失败时空文档也能过。基线验签由 privacy suite 另证。
- 无「无 `version` 键 → `source_version === '3.x'`」测试。
- 无 rollback 后 GET 报告形态测试。
- `/migration/report` 无独立权限测试（与 residency 共用同一 callback）。
- `Client::unavailable()` 中文无单测（任务书只点名 ChallengeClient 与 AppsController）。
- 未跑 `npm run build` / `lint:js` / e2e。

---

## F 与 spec 的偏差

逐条相对 `docs/specs/rest-api.md`、`apps-manifest-and-bridge.md`、`data-residency-ruleset.md`、任务书。实现与本次写入 spec 的正文基本同稿，下列是正文未写死或与 UI 规格不一致之处。

1. **`/residency/log` 包成 `{ "items": [...] }`**（`ResidencyController.php:87-91`）。`rest-api.md:121-123` 只规定每条四字段和分页参数，没有信封。诊断页尚未接线。
2. **无 `X-WP-Total` / `total`。** spec 只要求 `page` / `per_page`（`rest-api.md:15,123`）。超过 20 条时客户端只能翻到空页才知道结束。
3. **log 无 `disposition`。** spec 四字段不含它；`Diagnose.js:281-308` 空表列了「处置」。本任务不改 `src/Admin/app/`，DG-04 接线上来要对字段。
4. **`source_version`：** spec 写「`wp_china_yes` 内无版本字段时为 `3.x`」（`rest-api.md:157-158`）。实现按字面做了；真实 3.x option 没有该字段，界面将长期显示 `3.x`。
5. **`ignored` 重复说明：** spec 表既写 `Report::to_array()` 已含 `ignored`，又单列 `ignored`（`rest-api.md:153-159`）。实现 `array_merge` 一次，无重复键。
6. **`unconfigured`：** `rest-api.md:38` 与 `apps-manifest-and-bridge.md:157` 已补枚举，与 `Index.php` 一致。空目录仍是 `ok` + `apps: []`，空 source 才是 `unconfigured`。无偏差。
7. **权限同 diagnostics：** spec / 任务书都写 `manage_options`。多站点下报告 body 是网络 settings，与「同 diagnostics」字面一致、与数据范围不完全同级（见 B/D）。

---

## G 三清单

### 阻断

无。没有必须停合入的正确性或安全缺陷。

### 建议

1. **`rollback()` 同时删 `wpcy_migration_report`**（或 GET 在 backup 不存在时返回 `{status:none}`）。现在回滚后 REST 仍返回上次 `execute()` 的 `settings` 快照。改 `Runner.php:139-154`：在 `backup->delete()` 旁 `delete_option` / `delete_site_option(self::REPORT_OPTION)`；补一条 FixturesTest。
2. **`ResidencyController::log_items` 再投影四字段**，不要只靠 `sanitize_log`。`ResidencyController.php:99-114` 对每行只取 `host/data_class/count/last_seen` 再返回。
3. **多站点 `/migration/report`：** 任务书锁了 `manage_options`。若产品不希望子站管理员读网络 settings，应改 spec 为 `manage_network_options` 并换 `manage_network_read`。当前按任务书实现，不要 silently 改权限。
4. **合入前补 Studio 证据：** `studio wp`/`curl` 恢复页 HTML 含 `<title>…文派叶子 · 恢复模式…`，`debug.log` 无 `strip_tags(null)`。单元测试不够。
5. **补测：** 无 `version` 键 → `source_version==='3.x'`；ruleset `ruleset_version>=1`；分页第二页 host 集合；`Client::unavailable()` 中文。
6. **`DataStore` JSON 编码失败** 仍用「请检查键名」（`DataStore.php:232-237`）。错误码也是 `wpcy_apps_key_invalid`。这是旧码换中文，不是新逻辑，但文案对不上。另开文案/码表再改。

### 确认无误

- 两条 residency 路由和 `/migration/report` 已挂 `wpcy/v1`，权限回调与 GET `/diagnostics` 相同（`RestModule.php:163-221`）。
- 空索引源从 `ok` 改为 `unconfigured`；现有 `Services.js:581` 的 `status !== 'ok'` 不改 UI 也会出琥珀 Notice。
- 面向用户的 `WP_Error` / `wp_die` 已无英文；`src/` 内 `__('` 拉丁文无匹配；词表已追加。
- 恢复页 hidden parent 从 `null` 改为 `options.php`，是 WP 隐藏设置页的常规做法，能消除 `add_submenu_page(null, …)` 在 PHP 8.2 上的 `strip_tags(null)`。
- B 档 log 四字段、无正文；ruleset REST 无 signature。
- 未改 `src/Admin/app/`、未碰 `framework/`、无用户可见「遥测」类文案。
- `composer check` 退出 0（phpstan 无错，lint 无输出，单元测试全绿）。
