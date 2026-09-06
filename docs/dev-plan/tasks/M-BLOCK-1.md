# 任务 M-BLOCK-1：出站三层引擎（L0 保护主机 / L2 本站拦截清单 / 噪声包 / 诊断 REST）

## 目标

把 [决定 A 节](../decisions/2026-09-06-http-block-merge-and-feature-absorption.md) 写进 Ruleset、`Privacy/SiteBlocklist`、三个 REST 与诊断只读数据；`pre_http_request` 顺序 L0 → L1 → 噪声包 → L2；不改 React。

## 背景与上下文文件

- [`docs/dev-plan/decisions/2026-09-06-http-block-merge-and-feature-absorption.md`](../decisions/2026-09-06-http-block-merge-and-feature-absorption.md)（决定原文，不许改）
- [`docs/dev-plan/verification/http-block-analysis-2026-09-06.md`](../verification/http-block-analysis-2026-09-06.md)
- [`docs/architecture/adr-005-feature-absorption.md`](../../architecture/adr-005-feature-absorption.md)
- [`docs/dev/module-authoring.md`](../../dev/module-authoring.md)（四个插槽、SiteBlocklist 示例、删除路径）
- [`docs/specs/data-residency-ruleset.md`](../../specs/data-residency-ruleset.md) §10（`protected_hosts`、噪声包、三层优先级）
- [`docs/specs/config-schema.md`](../../specs/config-schema.md) `modules.site_blocklist`、`modules.noise_block.enabled`
- [`docs/specs/rest-api.md`](../../specs/rest-api.md) `/site-blocklist`、`/residency/protected`、`/residency/test`
- [`docs/design/admin-ui-spec.md`](../../design/admin-ui-spec.md) §4 词表（「文派服务不可拦截」等）
- [`docs/dev/task-book-template.md`](../../dev/task-book-template.md)
- 现有实现：`src/Privacy/DataResidency/{Ruleset,DataResidencyModule}.php`（`pre_http_request` priority 10）、`src/Privacy/rulesets/baseline.json`、`src/Rest/ResidencyController.php`、`src/Rest/RestModule.php`、`src/Config/{Schema,Defaults,Validator,Repository}.php`、`src/Core/Plugin.php`
- `.grok-context/` 若存在可读；测试不得读它，样本进 `tests/fixtures/` 并提交。

依赖：**M-SCOPE-1 已合入 `main`**。本任务基于合入后的 `main` 开 worktree。M-SCOPE-1 未合不得开工，停下来在报告写明。

工作分支 `grok/m-block-1`。预计 diff ≤ 1500 行。

## 交付物

| 路径 | 职责 |
|------|------|
| `src/Privacy/DataResidency/Ruleset.php` | 解析 `protected_hosts`、`noise_block`；`is_protected( string $host ): bool`；`noise_match( string $host )`；验签失败丢弃增量、沿用内置硬编码清单 |
| `src/Privacy/rulesets/baseline.json` | 基线含硬编码 L0 清单（A3）写成 `protected_hosts`；`noise_block` 可为 `[]`；重签 |
| `src/Privacy/DataResidency/DataResidencyModule.php` | L0 命中：不改道、不 record、不把请求交给后层拦截。`pre_http_request` priority **写死 5**（先于现有 L1 的 10） |
| `src/Privacy/SiteBlocklist/SiteBlocklistModule.php` | Schema 路径 `modules.site_blocklist`；`enabled()` 读该键；`pre_http_request` priority **写死 15**；命中则 `WP_Error( 'wpcy_site_blocklist_blocked' )`；运行时忽略保护主机条 |
| `src/Privacy/SiteBlocklist/Repository.php`（或模块内私有方法） | 读网络级清单；保存校验：上限 20、exact/suffix、拒 L0 |
| `src/Config/Schema.php` / `Defaults.php` / `Validator.php` | `modules.site_blocklist`、`modules.noise_block.enabled`；覆盖袋不含 `site_blocklist` |
| `src/Rest/SiteBlocklistController.php` | `GET/PUT /wpcy/v1/site-blocklist`；权限 `manage_network_options`；PUT 保护主机 → 400 `wpcy_blocklist_protected_host`，message `__( '文派服务不可拦截', 'wp-china-yes' )` |
| `src/Rest/ResidencyController.php`（或并列控制器） | `GET /residency/protected`、`POST /residency/test`，形状见 rest-api.md |
| `src/Rest/RestModule.php` | 注册上述三条 |
| `src/Core/Plugin.php` | `create()` 注册 `SiteBlocklistModule`（仅此项新增） |
| `src/Diagnostics/` 只读数据 | 诊断卡数据源：三层生效清单 + 最近一次 `POST /residency/test` 不持久化（测试端点无存储） |
| `tests/Unit/Privacy/ProtectedHostsTest.php` | 硬编码清单；签名增量追加；验签失败回退内置 |
| `tests/Unit/Privacy/SiteBlocklistTest.php` | 拒保存保护主机；运行时忽略已存保护条；20 条上限；suffix 语义；`enabled=false` 不挂钩 |
| `tests/Unit/Privacy/LayerOrderTest.php` | 同一 URL：L0 放行则 L1/L2 不得拦；L1 reroute 则 L2 不得拦；L1 C ignore 后 L2 可拦；噪声包不拦 L0 |
| `tests/Unit/Rest/SiteBlocklistControllerTest.php` | 权限；400 保护主机；message 中文原文 |
| `tests/Unit/Rest/ResidencyProtectedTest.php` | GET 三层；POST test 各层 action |
| `tests/Unit/Config/` 既有 Schema / Validator / Defaults | 跟着新键改断言，不得删场景 |
| 重签基线 | `php scripts/sign-ruleset.php …` 后 `baseline.json` 的 `verified()===true` |

不改 `src/Admin/app/`。不改决定文件。不迁 HTTP Block 源码。无迁移映射（原插件未发布）。

## 行为规格

对照决定 A1–A6、[`data-residency-ruleset.md`](../../specs/data-residency-ruleset.md) §10、[`config-schema.md`](../../specs/config-schema.md)、[`rest-api.md`](../../specs/rest-api.md)。冲突时以决定原文为准，停下来写进报告。

### L0 并入 Ruleset

- 硬编码清单写死（A3）：`wenpai.net` suffix、`wpcy.com` suffix、`cravatar.cn` exact、`cravatar.com` exact、`admincdn.com` suffix。云桥 ingest 主机：规格标待定（M0）；本任务硬编码数组留空常量——**不得发明 FQDN**。未定时 `updates.wenpai.net` 已由 `wenpai.net` suffix 覆盖。`cn.cravatar.com` 不在 A3 原文；不要自行改成 suffix。
- 签名增量 `protected_hosts[]` 与硬编码 **并集**；增量不能删掉硬编码。
- 验签失败：丢弃增量，`is_protected` 只看硬编码（加上一份已验签缓存，若现有 Ruleset 已有该语义则沿用）。
- 匹配：`exact` = 整主机相等（大小写不敏感）；`suffix` = 主机等于该值或其子域（`example.com` 匹配 `example.com` 与 `a.example.com`，不匹配 `notexample.com`）。

### L1

不改 A/B/C 条目语义。`profile` 闸 A 档仍按 M-SCOPE-1（方案 A + 保险）。L0 命中的请求：**不**走 A reroute / B record（永远放行，后层不得推翻）。

### 噪声包

- 条目来自 ruleset `noise_block`。用户键 `modules.noise_block.enabled`。
- `enabled=false`：不拦。
- 命中 L0 或 L1 A/B 已处理 → 不拦。
- 条目若 host 落在 L0 或明显 `.org` / 已知 CDN（`cdnjs.cloudflare.com`、`cdn.jsdelivr.net`）→ 运行时忽略该条，记诊断，不拦。
- `pre_http_request` 挂在 DataResidency 内或独立小类，priority **写死 12**（L1 的 10 与 L2 的 15 之间）。

### L2 `Privacy/SiteBlocklist`

- `id()` = `privacy.site_blocklist`。
- 网络级：多站点读/写 `wpcy_network_settings.modules.site_blocklist`；单站读/写 `wpcy_settings.modules.site_blocklist`。不进 `wpcy_site_overrides`。
- `hosts` 上限 20。`match` 仅 `exact` \| `suffix`。无正则、无通配、无路径。
- 保存：任一条 `Ruleset::is_protected( $host )` → 拒绝整单。
- 运行时：已存条若保护 → 静默跳过该条（仍处理其余条）。
- 命中 block：`WP_Error( 'wpcy_site_blocklist_blocked' )`，message 面向日志/诊断可用英文 code；**不得**把该英文画到 UI（UI 不在本任务）。
- `enabled=false` 或恢复模式：不挂钩。
- 不能 reroute、不能写 target URL。

### `pre_http_request` 顺序

写死 priority：

| 层 | priority |
|----|----------|
| L0（保护，在 DataResidency 内先判定） | 5 |
| L1 驻留（现有） | 10（保持） |
| 噪声包 | 12 |
| L2 本站清单 | 15 |

后层不得推翻前层。测：L0 主机即使出现在 L2 列表也放行。

### REST

按 rest-api.md 原文形状。`PUT /site-blocklist` message 必须是词表「文派服务不可拦截」，text domain `wp-china-yes`。

### 诊断只读数据

`GET /residency/protected` 即诊断卡数据源。不建请求表、不记 URL、不 cron。`POST /residency/test` 无存储。

### 迁移

无。原插件未发布。不读 `http_block_*` option。

### 删除路径（任务书必写，实现本任务时不执行删除）

整块移除时：

1. 删 `src/Privacy/SiteBlocklist/`
2. Schema / Defaults / Validator 去掉 `modules.site_blocklist`、`modules.noise_block`
3. Ruleset 去掉 `protected_hosts` / `noise_block` 解析（或留字段忽略）
4. `RestModule` 去掉 `/site-blocklist`、`/residency/protected`、`/residency/test`
5. `Plugin::create()` 去掉模块
6. 词表 CO-10 / DG-07 与 §4 七词（由文档任务另删）
7. 无 cron、无自定义表、无 transient 专键（若本任务加了测试 transient 一并删）
8. 测试目录 `tests/Unit/Privacy/SiteBlocklist*`、`ProtectedHostsTest`、`LayerOrderTest`、对应 REST 测

## 禁区

- 不改 `src/Admin/app/`。
- 不改 `docs/dev-plan/decisions/2026-09-06-http-block-merge-and-feature-absorption.md`。
- 不迁移 HTTP Block 源码；不把内置 100+ 黑名单抄进基线。
- 不给用户正则 / 通配 / 路径 / 全局屏蔽。
- 不设付费墙、不加配额字段、不进 Entitlements。
- 不新起 `HttpBlock` 品牌（类名、option、REST 命名空间都不准出现 `http-block` / `HttpBlock`）。
- 不建独立请求日志表、不 `debug_backtrace`、不每请求写 performance option。
- 不另起 REST 命名空间。
- 不在模块内判断「是不是国内站」。
- 不写用户可见「遥测」「匿名数据」「隐私开关」。
- 不 push `main` / `master`。
- 测试不读 `.grok-context/`。
- 不装系统级依赖。本机无 Docker：e2e 以分支 CI 为准。

## 验收标准

每条一条命令。贴输出，不要只写「通过」。

1. 保护主机拒保存：
   ```bash
   vendor/bin/phpunit --filter SiteBlocklist
   ```
   期望：写入 `api.wenpai.net` / `wpcy.com` 失败；message 含「文派服务不可拦截」；option 未改。

2. 运行时忽略已存保护条：
   ```bash
   vendor/bin/phpunit --filter 'runtime.*protect|ignores_protected'
   ```
   期望：option 里已有 L0 主机时 `pre_http_request` 对该 URL 不返回 Error。

3. 顺序：
   ```bash
   vendor/bin/phpunit --filter LayerOrder
   ```
   期望：L0 放行压过 L2；L1 A reroute 压过 L2；L1 C 后 L2 可拦；噪声包不拦 `wenpai.net`。

4. 多站点权限：
   ```bash
   vendor/bin/phpunit --filter SiteBlocklistController
   ```
   期望：无 `manage_network_options` → 403；子站 `manage_options` 不能 PUT。

5. 20 条上限：
   ```bash
   vendor/bin/phpunit --filter 'max.*20|twenty'
   ```
   期望：21 条 → `wpcy_invalid_schema` 400，不写入。

6. suffix 语义：
   ```bash
   vendor/bin/phpunit --filter 'suffix'
   ```
   期望：`example.com` suffix 命中 `a.example.com`，不命中 `notexample.com`。

7. 验签失败回退：
   ```bash
   vendor/bin/phpunit --filter ProtectedHosts
   ```
   期望：坏签名增量不合并；硬编码 `wenpai.net` 仍保护。

8. REST 三端点形状：
   ```bash
   vendor/bin/phpunit --filter 'ResidencyProtected|ResidencyTest'
   vendor/bin/phpunit --filter SiteBlocklistController
   ```

9. 全量质量与前端（未改 JS，lint 仍须绿）：
   ```bash
   composer check
   npm run build
   npm run lint:js
   ```

10. diff 范围：
    ```bash
    git diff --stat main...HEAD
    git status
    ```
    不含 `.grok-context/`，不含 `src/Admin/app/`，不含决定文件。

11. 本机无 Docker → push 分支用 CI：
    ```bash
    git push -u origin grok/m-block-1
    gh run list --branch grok/m-block-1
    gh run view <id> --log-failed
    ```
    禁止 push `main`。CI 未完时报告写「CI run \<id\> 进行中，本地验收如下」。

提交前缀：`feat(residency):` / `feat(config):` / `feat(rest):`。按子系统分笔，不要揉成一个。每个 commit 前 `git status` 确认没把 `.grok-context/` 加进去。

## Definition of Done

执行者在报告里逐条打勾并贴证据。缺一条不得报「完成」：

- [ ] 规格条目 ↔ 实现逐条对照表（`| 规格编号 | 实现位置 文件:行 | 状态 |`），规格里有、实现没有的必须列出并写「未做」。本任务对照表指 **data-residency-ruleset §10 三层 + `protected_hosts` / `noise_block` ↔ `Ruleset.php` / `SiteBlocklistModule.php` 逐键**，以及 rest-api `/site-blocklist`、`/residency/protected`、`/residency/test` ↔ 控制器，config-schema `modules.site_blocklist` / `modules.noise_block.enabled` ↔ Schema。
- [ ] 每个状态一张截图（Playwright，命名 `<页面>-<状态>.png`，存 `docs/design/screens/<任务>/`），UI 任务必备；非 UI 任务写「不适用」。
- [ ] 面向用户的字符串：全部中文、在 `admin-ui-spec.md` §4 词表内或已提交词表新增；无英文错误串；无「遥测/隐私/上报」字样。本任务用户可见串只有 REST message「文派服务不可拦截」。
- [ ] 空状态、错误状态、降级状态有对应实现，不是「空表占位」。本任务：空清单 `hosts: []`；保护主机 400；验签失败沿用内置；权限 403；21 条 400；`POST /residency/test` 非法 URL 400。
- [ ] 测试：新增/修改行为有测试；`composer check` 与 `npm run build`、`npm run lint:js` 通过；CI run id。
- [ ] `git diff --stat` 在允许路径内；`.grok-context/` 未入库。
- [ ] 报告含「没做 / 做不到 / 有疑问」；**报告写完再退出**（等 CI 时先写「CI run <id> 进行中，本地验收如下」）。

对照表模板：

```markdown
| 规格编号 | 实现位置 | 状态 |
|----------|----------|------|
| A1 L0 永远放行 | src/Privacy/DataResidency/Ruleset.php:行 | 已做 |
| A3 硬编码 wenpai.net suffix | src/Privacy/DataResidency/Ruleset.php:行 | 已做 |
| modules.site_blocklist.hosts maxItems 20 | src/Config/Schema.php:行 | 已做 |
| PUT /site-blocklist 400 wpcy_blocklist_protected_host | src/Rest/SiteBlocklistController.php:行 | 已做 |
| GET /residency/protected | src/Rest/ResidencyController.php:行 | 已做 |
| POST /residency/test | src/Rest/ResidencyController.php:行 | 已做 |
| modules.noise_block.enabled | src/Config/Schema.php:行 | 已做 |
| 验签失败沿用内置 | src/Privacy/DataResidency/Ruleset.php:行 | 已做 |
```

## 报告格式

见 `docs/dev/agents.md`。必须含：`git diff --stat` 原样、每条验收命令与输出摘要、没做/做不到/有疑问、`git log --oneline -5`。DoD 七项逐条打勾。
