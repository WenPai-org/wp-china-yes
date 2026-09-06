先读任务书、上下文和现有实现，再装依赖。冲突以统筹拍板为准：Cravatar 用 suffix，覆盖袋含 site_blocklist 时整段 400。继续读规格和现有实现。冲突按统筹拍板处理：Cravatar 两域 suffix、覆盖袋 400、权限按 `is_multisite` 分流。开始装依赖并写实现。依赖已装好。按统筹拍板实现：Cravatar 两域 suffix、覆盖袋 400、权限按 `is_multisite` 分流。接下来改 DataResidencyModule、Config 和 SiteBlocklist。Repository 里那段网络级路径判断写乱了，先清掉。DocumentWriter 里那句用户可见文案可能不在词表里。任务书只允许「文派服务不可拦截」。改成无用户文案的 schema 错误，或只在内部拒绝。覆盖袋 400 走 REST code `wpcy_settings_network_only_key`，message 用现有 invalid_schema 中文。L2 需跳过 L1 已认领的 A/B 主机。接着写诊断数据源、REST 和 Plugin 注册。更新 baseline.json，并写测试。接下来写测试，并给 http stubs 加上 WP_Error。补 Config 测试，重签基线，跑单测。PHP 常量必须在属性之前。把 Ruleset 的常量挪到属性前面。先修 phpcs 报错。phpcs 过了。修 phpstan 的 isset 问题。phpstan 卡在 phpdoc 的 isset。把返回类型放宽，或直接用字段。composer check 已通过。等 npm 完成后分笔提交。按子系统分三笔提交：residency、config、rest。npm build 改了 assets，但任务不改前端，不提交这些产物。三笔提交完成。推分支跑 CI，同时收齐本地验收输出。CI 已在跑。任务要求报告写完再退出，不等 CI。再核几处行号写对照表。# M-BLOCK-1 最终报告

CI run `34035832300` 进行中，本地验收如下。

分支 `grok/m-block-1` 已 push（未 push `main`）。三笔提交：`feat(residency):` / `feat(config):` / `feat(rest):`。

## 1. 改了哪些文件

`git diff --stat main...HEAD`：

```
 src/Config/Defaults.php                           |   7 +
 src/Config/Repository.php                         |  29 ++
 src/Config/Schema.php                             |  86 ++++-
 src/Core/Plugin.php                               |   2 +
 src/Diagnostics/OutboundLayers.php                | 240 ++++++++++++++
 src/Privacy/DataResidency/DataResidencyModule.php | 124 +++++++-
 src/Privacy/DataResidency/Ruleset.php             | 244 ++++++++++++++
 src/Privacy/SiteBlocklist/Repository.php          | 370 ++++++++++++++++++++++
 src/Privacy/SiteBlocklist/SiteBlocklistModule.php | 185 +++++++++++
 src/Privacy/rulesets/baseline.json                |  25 +-
 src/Rest/DocumentWriter.php                       |  34 ++
 src/Rest/ResidencyController.php                  |  76 ++++-
 src/Rest/RestModule.php                           |  58 +++-
 src/Rest/SiteBlocklistController.php              | 145 +++++++++
 tests/Unit/Config/RepositoryTest.php              |   3 +
 tests/Unit/Config/ValidatorTest.php               |  50 +++
 tests/Unit/Core/PluginCreateTest.php              |   1 +
 tests/Unit/Privacy/LayerOrderTest.php             | 201 ++++++++++++
 tests/Unit/Privacy/ProtectedHostsTest.php         | 113 +++++++
 tests/Unit/Privacy/SiteBlocklistTest.php          | 191 +++++++++++
 tests/Unit/Privacy/wp-http-stubs.php              | 101 ++++++
 tests/Unit/Rest/ResidencyProtectedTest.php        | 205 ++++++++++++
 tests/Unit/Rest/SiteBlocklistControllerTest.php   | 144 +++++++++
 23 files changed, 2615 insertions(+), 19 deletions(-)
```

`git status`：`On branch grok/m-block-1` / `nothing to commit, working tree clean`。不含 `.grok-context/`、不含 `src/Admin/app/`、不含决定文件。

## 2. 验收命令与输出摘要

### 1. 保护主机拒保存

```
vendor/bin/phpunit --filter SiteBlocklist
OK (11 tests, 37 assertions)
```

含 `Save rejects protected hosts`（`api.wenpai.net` / `wpcy.com` → `wpcy_blocklist_protected_host`，message「文派服务不可拦截」，option 未改）、`Runtime ignores protected rows`、`Max twenty hosts rejected`、`Suffix matches subdomain not lookalike`。

### 2. 运行时忽略已存保护条

```
vendor/bin/phpunit --filter 'runtime.*protect|ignores_protected'
OK (1 test, 3 assertions)
```

`Runtime ignores protected rows`：option 里已有 `api.wenpai.net` 时对该 URL 不返回 Error；同单其它条仍拦。

### 3. 顺序

```
vendor/bin/phpunit --filter LayerOrder
OK (4 tests, 9 assertions)
```

`L0 allow overrides l2` / `L1 reroute overrides l2` / `L1 c ignore then l2 may block` / `Noise does not block wenpai net`。

### 4. 多站点权限

```
vendor/bin/phpunit --filter SiteBlocklistController
OK (6 tests, 17 assertions)
```

`Multisite requires manage network options`：无 `manage_network_options` → 403；子站仅 `manage_options` 不能 PUT。单站 `manage_options` 可读（统筹拍板按 `is_multisite()` 分流）。

### 5. 20 条上限

```
vendor/bin/phpunit --filter 'max.*20|twenty'
OK (3 tests, 9 assertions)
```

21 条 → `wpcy_invalid_schema` 400，不写入。

### 6. suffix 语义

```
vendor/bin/phpunit --filter 'suffix'
OK (2 tests, 5 assertions)
```

`example.com` suffix 命中 `a.example.com`，不命中 `notexample.com`。顺带跑到 Windfonts 一条无关测。

### 7. 验签失败回退

```
vendor/bin/phpunit --filter ProtectedHosts
OK (3 tests, 19 assertions)
```

坏签名增量不合并；硬编码 `wenpai.net` 仍保护。

### 8. REST 三端点形状

```
vendor/bin/phpunit --filter 'ResidencyProtected|ResidencyTest'
OK (7 tests, 31 assertions)
```

GET 三层；POST test：L0 allow / L1 reroute / L2 block / noise_block block / 非法 URL 400。

`vendor/bin/phpunit --filter SiteBlocklistController` 同上第 4 条。

### 9. 全量质量与前端

`composer check`：exit 0（phpcs / phpstan / 全套单测绿）。

`npm run build`：webpack compiled with 1 warning（既有 vendor 体积，未改 JS）。

`npm run lint:js`：exit 0。

### 10. diff 范围

见第 1 节。路径均在允许范围内。

### 11. CI

```
git push -u origin grok/m-block-1
* [new branch] grok/m-block-1 -> grok/m-block-1

gh run list --branch grok/m-block-1
in_progress  feat(rest): add site-blocklist and residency protected/test endpoints  CI  grok/m-block-1  push  34035832300
```

未跑 `gh run view --log-failed`（run 尚未结束）。禁止 push `main`，未 push。

## 3. 没做 / 做不到 / 有疑问

1. **任务书硬编码 Cravatar 写 exact，统筹拍板写 suffix。** 按拍板：`cravatar.cn` / `cravatar.com` 均为 suffix（`Ruleset.php:63-70`）。任务书 A3 原文与拍板冲突，未自行改成 exact。
2. **任务书「覆盖袋不含 site_blocklist」vs 拍板「整段 400 `wpcy_settings_network_only_key`」。** 两处都做了：`Schema::site_overrides()` 去掉该键；PUT 覆盖袋含该键 → 400 `wpcy_settings_network_only_key`（message 复用既有「暂时无法保存设置…」，未新造用户可见串）。
3. **rest-api.md 写 `/site-blocklist` 权限一律 `manage_network_options`；拍板按 `is_multisite()` 分流。** 按拍板：多站点 `manage_network_options`，单站 `manage_options`。
4. **云桥 ingest 主机。** `Ruleset::CLOUD_BRIDGE_INGEST_HOSTS = array()` + TODO，未发明 FQDN。
5. **预计 diff ≤ 1500 行，实际 +2615。** 主要是测试与 Repository/OutboundLayers 文档注释。未砍测试。
6. **未改 `.github/workflows/ci.yml`。** 任务说改了才用 CI 回路；本任务未改 workflow，仍 push 分支做验证。
7. **未执行删除路径。** 任务书写明实现时不执行删除。
8. **未迁 HTTP Block 源码、未改 React、未改决定文件、无迁移映射。**
9. **`POST /residency/test` 对 L1 A 且 ingest 未就绪仍报 `action=reroute`。** 与 rest-api.md 示例一致（detail 含 `enabled_when`）；运行时 L1 此时不改 URL，后层仍不得拦（LayerOrder 已测）。
10. **噪声包命中返回 `WP_Error('wpcy_noise_block_blocked')`。** 任务书只写死 L2 的 code；噪声包 code 未在 rest-api 错误表列出。

## 4. 提交哈希

```
0d088c0 feat(rest): add site-blocklist and residency protected/test endpoints
f56512d feat(config): add site_blocklist and noise_block schema keys
59b2375 feat(residency): add L0 protected hosts and noise pack to Ruleset
fcc981d docs(decisions): remove weavatar from avatar enum; migration maps to cravatar_cn
c3eee00 docs: M-SCOPE-1 merged; archive reports/review; REST spec — legacy avatar string transitional compat, weavatar removed
```

---

## Definition of Done

- [x] 规格条目 ↔ 实现逐条对照表（见下）。
- [x] 截图：不适用（非 UI 任务）。
- [x] 用户可见串：仅 REST message「文派服务不可拦截」（`wp-china-yes`）。无「遥测/隐私/上报」。
- [x] 空/错/降级：空清单 `hosts: []`；保护主机 400；验签失败沿用内置；权限 403；21 条 400；非法 URL 400。
- [x] 测试：新增行为有测；`composer check`、`npm run build`、`npm run lint:js` 本地绿；CI run id `34035832300`（进行中）。
- [x] `git diff --stat` 在允许路径内；`.grok-context/` 未入库。
- [x] 含「没做 / 做不到 / 有疑问」；报告写完再退出。

| 规格编号 | 实现位置 | 状态 |
|----------|----------|------|
| A1 L0 永远放行 | `src/Privacy/DataResidency/DataResidencyModule.php:116-118`（priority 5）/ `:163` | 已做 |
| A3 硬编码 wenpai.net suffix | `src/Privacy/DataResidency/Ruleset.php:54-58` | 已做 |
| A3 wpcy.com suffix | `Ruleset.php:59-62` | 已做 |
| A3 cravatar.cn / cravatar.com suffix（拍板） | `Ruleset.php:63-70` | 已做 |
| A3 admincdn.com suffix | `Ruleset.php:71-74` | 已做 |
| 云桥 ingest 空常量 | `Ruleset.php:85` | 已做 |
| `protected_hosts` 并集 / 增量不能删硬编码 | `Ruleset.php:259-275` | 已做 |
| 验签失败沿用内置 | `Ruleset.php:264`（未 verified 不合并增量） | 已做 |
| `noise_block` 解析 / `noise_match` | `Ruleset.php:309-362` | 已做 |
| 噪声包 priority 12 | `DataResidencyModule.php:118` | 已做 |
| `modules.noise_block.enabled` | `src/Config/Schema.php:468-481` | 已做 |
| `modules.site_blocklist.hosts maxItems 20` | `src/Config/Schema.php:519` | 已做 |
| 覆盖袋不含 site_blocklist | `Schema.php:180` 用 `modules(false)` | 已做 |
| 覆盖袋含该键 400 `wpcy_settings_network_only_key` | `src/Rest/DocumentWriter.php:61-67` | 已做 |
| L2 `id()` = `privacy.site_blocklist` | `SiteBlocklistModule.php:64` | 已做 |
| L2 priority 15 | `SiteBlocklistModule.php:125` | 已做 |
| L2 `WP_Error('wpcy_site_blocklist_blocked')` | `SiteBlocklistModule.php:168` | 已做 |
| 保存拒 L0 | `src/Privacy/SiteBlocklist/Repository.php:228-229,339-347` | 已做 |
| 运行时忽略已存保护条 | `Repository.php:164-168` | 已做 |
| PUT `/site-blocklist` 400 `wpcy_blocklist_protected_host` | `src/Rest/SiteBlocklistController.php:78` | 已做 |
| GET `/residency/protected` | `src/Rest/ResidencyController.php:115` | 已做 |
| POST `/residency/test` | `src/Rest/ResidencyController.php:129` | 已做 |
| RestModule 注册三条 | `src/Rest/RestModule.php:223-268` | 已做 |
| Plugin 注册 SiteBlocklistModule | `src/Core/Plugin.php:209` | 已做 |
| 诊断只读 / test 无存储 | `src/Diagnostics/OutboundLayers.php` | 已做 |
| schema_version 维持 2 | `Schema.php:29` 未改 | 已做 |
