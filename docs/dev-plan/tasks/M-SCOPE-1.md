# 任务 M-SCOPE-1：站点场景与作用域引擎（Schema v2 / Connectivity 门控 / Profile / REST / 迁移 D3）

## 解决什么问题

4.0 连通性对所有请求一视同仁，跨境站会把国内公共库改写打到海外访客。本任务把 [ADR-004](../../architecture/adr-004-site-profile-and-scope.md) 写进 Schema、Repository、三模块、迁移与 REST，不改 React。

## 不做什么

见下方「禁区」。不改 `src/Admin/app/`；不实现 `admin_assets` 改写；不自动切换场景；不改决定文件；不拍 `privacy.data_residency` 海外是否改道（拍板前按主机表）。

## 目标

`schema_version` 升到 2；连通性三模块按 scope 门控；Profile 默认矩阵与切换重置；`GET /profile/suggest`；撤销 M4-02b 的 `admin→ignored`；telemetry payload 加 `profile`。单元 + 现有 e2e 不红。

## 背景与上下文文件

- [`docs/dev-plan/decisions/2026-09-06-site-profile-and-scope.md`](../decisions/2026-09-06-site-profile-and-scope.md)（决定原文，不许改）
- [`docs/architecture/adr-004-site-profile-and-scope.md`](../../architecture/adr-004-site-profile-and-scope.md)
- [`docs/specs/config-schema.md`](../../specs/config-schema.md)（v2、`upgrade_1_to_2`、D2 矩阵、D3 映射）
- [`docs/specs/rest-api.md`](../../specs/rest-api.md)（`/settings` 字段、`GET /profile/suggest`）
- [`docs/design/admin-ui-spec.md`](../../design/admin-ui-spec.md) §4 词表（迁移报告那一行）
- [`docs/dev/task-book-template.md`](../../dev/task-book-template.md)
- [`docs/dev-plan/verification/m4-upgrade-matrix-2026-09-06.md`](../verification/m4-upgrade-matrix-2026-09-06.md) 末尾「待补用例（D3）」
- 现有实现：`src/Config/{Schema,Defaults,Repository,Validator}.php`（`Schema::VERSION` 现为 1）、`src/Core/Scope.php`（站点 vs 网络，**禁止改写**）、`src/Core/Environment.php`、`src/Connectivity/{PublicAssets,Avatar,WordPressOrg}/`、`src/Migration/Mappers.php`（`admincdn` 整键 `unsupported_whitelist`，token `admin` 进 ignored）、`src/Telemetry/Report.php`、`src/Rest/RestModule.php`
- `.grok-context/` 若存在可读；测试不得读它，样本进 `tests/fixtures/` 并提交。

依赖：**M-UI-0 已合入 `main`**。本任务基于合入后的 `main` 开 worktree。M-UI-0 未合不得开工，停下来在报告写明。

工作分支 `grok/m-scope-1`。预计 diff ≤ 1500 行。

## 交付物

| 路径 | 职责 |
|------|------|
| `src/Config/Schema.php` | `VERSION = 2`；`profile` 枚举；`public_assets` 改为 `{items,scope}`；`avatar` 改为 `{admin,frontend}`；顶层 `admin_assets` `on\|off`；`site_overrides` 允许 `profile` / `admin_assets` |
| `src/Config/Defaults.php` | domestic 默认，与 config-schema「默认值汇总」逐键一致 |
| `src/Config/Repository.php`（或新建 `src/Config/SchemaMigrator.php` 由 Repository 调用） | 读取 settings / network / overrides 时若 `schema_version < 2` 执行 `upgrade_1_to_2` 并写回，幂等 |
| `src/Config/Profile.php` | 默认矩阵（照抄 D2）；`apply_defaults( string $profile ): array`；切换重置只动矩阵内的连通性键，不动 notice / announcements / diagnostics / recovery / telemetry / data_residency |
| `src/Config/ProfileSuggest.php` | D4 规则；geo 走 `api.wenpai.net`，路径与应答格式待 wenpai-net 侧提供，插件先按 `{ "country": "CN" }` 契约实现并可 mock |
| `src/Connectivity/Scope.php` | `WenPai\ChinaYes\Connectivity\Scope::current(): string` 返回 `'admin'` 或 `'frontend'`。**禁止修改** `src/Core/Scope.php` |
| `src/Connectivity/PublicAssets/PublicAssetsModule.php` | 读 `items` + `scope`；`scope=off` 或不匹配当前请求 → 不改写 |
| `src/Connectivity/Avatar/AvatarModule.php` | 读当前侧的独立值；该侧 `off` → 不改写（保留 Gravatar） |
| `src/Connectivity/WordPressOrg/WordPressOrgModule.php` | 无 admin/frontend 门控；只尊重 `auto` / `off`（crossborder 默认 `off` 由 Profile 写入） |
| `src/Migration/Mappers.php` | D3：`admin` token → `admin_assets=on`，不再进 ignored；`frontend` / `bootstrapcdn` 仍 ignored；直接写出 v2 |
| `src/Migration/Report.php` | `admin_assets=on` 时报告文案「后台加速：已保留设置，4.1 起生效」（`__()`，text domain `wp-china-yes`） |
| `src/Rest/ProfileSuggestController.php` + `src/Rest/RestModule.php` | `GET /wpcy/v1/profile/suggest`，权限同 settings |
| `src/Telemetry/Report.php` | `collect()` 增加 `profile`（有效 settings 的值；缺省 `domestic`） |
| `src/Integrations/Windfonts/WindfontsModule.php` | 不改 scope（前台功能）；Profile 切换写入 `modules.windfonts=false` 即可 |
| `tests/Unit/Config/SchemaVersion2Test.php` | `upgrade_1_to_2`：数组→对象、单值 avatar→双值、缺 profile→domestic、缺 admin_assets→off、幂等 |
| `tests/Unit/Config/ProfileTest.php` | 三场景矩阵逐格；切换重置；不受影响的键保持 |
| `tests/Unit/Connectivity/ScopeTest.php` | D2 判定：`is_admin` / admin-ajax / REST+nonce 后台 / 前台 REST / WP-CLI |
| `tests/Unit/Config/ProfileSuggestTest.php` | D4 四类：境内→domestic；境外+管理员境内→crossborder；其它→null；geo 失败→null；不返回 IP |
| `tests/Unit/Migration/FixturesTest.php` | 更新 S2 等；新增 3.9 `admincdn_files` 含 `admin` 的 fixture |
| `tests/fixtures/legacy-options/single-3.9-08-admincdn-files-admin.json` | 由 `single-3.9.3-03.json` 复制，仅把 `admincdn_files` 写成 `["admin"]`（或 `["admin","googlefonts"]`）；外层 `_fixture` 注明用途 |
| 现有连通性 / telemetry / REST / Validator / Repository 单测 | 跟着 schema 形状改断言，不得删场景 |
| `docs/dev-plan/verification/m4-upgrade-matrix-2026-09-06.md` | 补跑「待补用例（D3）」后把状态从「由 M-SCOPE-1 补跑」改成命令输出（本任务做；未跑 Studio 则保持该标并在报告写明） |

不改 `src/Admin/app/`。不改决定文件。

## 行为规格

对照 [ADR-004](../../architecture/adr-004-site-profile-and-scope.md)、[`config-schema.md`](../../specs/config-schema.md) D2/D3、[`rest-api.md`](../../specs/rest-api.md) `/profile/suggest`。冲突时以决定原文为准，停下来写进报告，不要改矩阵的值。

### Schema / Repository

- `Schema::VERSION === 2`。identity / migration_backup 的 schema_version **仍为 1**。
- `upgrade_1_to_2` 规则照 config-schema 表，逐行测。
- 3.x mapper 直接写 v2，不先写 v1。
- `Defaults::settings()` 的 `profile` / `public_assets` / `avatar` / `admin_assets` 等于 domestic 列。

### `Connectivity\Scope::current()`

返回 `'admin'` 或 `'frontend'`。判定照决定 D2 原文：

> `is_admin()`（含 `admin-ajax` / REST 带 `X-WP-Nonce` 的后台请求视为 admin；前台 REST 视为 frontend；WP-CLI 视为 admin）。

写死实现：

1. `defined('WP_CLI') && WP_CLI` → `admin`
2. `function_exists('is_admin') && is_admin()`（含 `admin-ajax`，`DOING_AJAX` 时 `is_admin()` 为 true）→ `admin`
3. REST（`REST_REQUEST` 或 `wp_doing_rest()`）：请求带 `X-WP-Nonce` 且 `wp_verify_nonce( $nonce, 'wp_rest' )` 为真，并且 `wp_get_referer()` 含 `/wp-admin` → `admin`；其余 REST → `frontend`
4. 其它 → `frontend`

猜测：D2 未写 cron。按与 WP-CLI 相同处理（`wp_doing_cron()` → `admin`）。有反证写进报告，不要自行改 D2。

**禁止**给 `src/Core/Scope.php` 加 `current()`（那是站点 vs 网络）。

### 三模块门控

- **PublicAssets**：`items` 为空或 `scope=off` → `enabled()===false`。`scope=admin` 且 `Scope::current()==='frontend'` → 不改写。`scope=frontend` 且 current 为 admin → 不改写。`scope=both` → 两侧都改写。`items` 仍只改白名单。
- **Avatar**：`get_cravatar_url` 读 `connectivity.avatar.{admin|frontend}`（按 `Scope::current()` 选键）。该侧为 `off` → 返回原 URL（Gravatar 保留）。`weavatar` 仍有效。
- **WordPressOrg**：不读 scope。`wordpress_org=off` 时模块不改源（已有行为）。

`admin_assets`：任何模块都不得因它为 `on` 而改写 URL。4.0 无运行时行为。

### Profile

`apply_defaults( 'domestic'|'crossborder'|'mixed' )` 写入（照抄 D2，不得改值）：

| 路径 | `domestic` | `crossborder` | `mixed` |
|------|------------|---------------|---------|
| `connectivity.wordpress_org` | `auto` | `off` | `auto` |
| `connectivity.public_assets.items` | 五项 | 五项 | 五项 |
| `connectivity.public_assets.scope` | `both` | `admin` | `admin` |
| `connectivity.avatar.admin` | `cravatar_cn` | `cravatar_cn` | `cravatar_cn` |
| `connectivity.avatar.frontend` | `cravatar_cn` | `off` | `cravatar_global` |
| `modules.windfonts` | `false` | `false` | `false` |
| `admin_assets` | `off` | `on` | `on` |

五项 = `["google_fonts","google_ajax","cdnjs","jsdelivr","emoji"]`。`windfonts` 三列都是 `false`（domestic「绑定后可开」= 不默认 true）。

切换场景：调用 `apply_defaults` 覆盖上表路径；`modules.notice_control`、`announcements`、`diagnostics`、`recovery_mode`、`data_residency`、`apps` **保持原值**。REST PUT 切换由调用方先确认；本任务引擎不弹 UI。

### ProfileSuggest（D4）

- geo：请求 `api.wenpai.net`（完整路径待 wenpai-net；未提供前用可注入的 HTTP 客户端，契约 `{ "country": "CN" }`）。失败 → 不建议。
- 查询参数 `locale`、`timezone`（见 rest-api.md）。不存原值，不把 IP 放进响应。
- 规则：服务器境外 + 管理员境内 → `crossborder`；服务器境内 → `domestic`；其它 → `null`。本规则不产生 `mixed`。
- 「境内」实现（D4 未写死匹配表，本任务书写死以免执行者猜）：`server_country === 'CN'`（不是 `HK`/`MO`/`TW`）。管理员境内 = `locale` 匹配 `/^zh[-_]CN/i`，或 `timezone` 为 `Asia/Shanghai` / `Asia/Chongqing` / `Asia/Urumqi` / `PRC`。
- 不自动写 `profile`。

### REST

- `GET /wpcy/v1/profile/suggest`：权限 `Permissions::manage_options_read`（与 GET `/settings` 同一回调）。响应形状见 rest-api.md。
- GET/PUT `/settings` 自然返回/接受 v2 对象（经 Repository + Validator）。PUT 旧 v1 数组 `public_assets` 或字符串 `avatar` → `wpcy_invalid_schema` 400。
- PUT 带 `profile` 且与当前不同：先 `apply_defaults` 再合并其余字段（其余字段可覆盖刚写入的默认，因为「每项仍可单独改」）。若产品以后要「切换必确认」，确认在 UI；本端点收到不同 `profile` 即视为已确认。

### 迁移 D3

- 3.8 `admincdn` 数组含 `'admin'`，或 3.9 `admincdn_files` 含 `'admin'` → `admin_assets='on'`；token `admin` **不**进 `ignored` / `ignored_reasons`。
- 迁移报告面向用户字符串：`__( '后台加速：已保留设置，4.1 起生效', 'wp-china-yes' )`。CLI JSON 可另有机器字段（如 `notes: ["admin_assets_reserved"]`），但报告文本必须含该中文。
- `frontend`、`bootstrapcdn` 仍 `unsupported_whitelist`。
- `profile='domestic'`。avatar 单值拆成两个相同值。`public_assets.scope='both'`；`items` 仍按 M4-02b（键存在则推导，空则 `[]`）。
- 既有 fixture `single-3.8-02.json`（`admincdn: ["admin"]`）是 S2。再加 `single-3.9-08-admincdn-files-admin.json`。

### Telemetry

`Report::collect()` 增加键 `profile`，值是有效 settings 的 `profile`（字符串 `domestic|crossborder|mixed`）。不在界面露出。`telemetry_version` 仍 `'2.1'`（不加版本，只加字段）。

## 禁区

- 不改 `src/Admin/app/`。
- 不改 `docs/dev-plan/decisions/2026-09-06-site-profile-and-scope.md`。
- 不改 D2 矩阵任何单元格的值。
- 4.0 不对 `admin_assets` 做 URL 改写。
- 不自动切换 `profile`。
- 不改 `src/Core/Scope.php`。
- 不往 `framework/` 加东西。
- 不写用户可见「遥测」「匿名数据」「隐私开关」。
- 不 push `main` / `master`。
- 测试不读 `.grok-context/`。
- 不装系统级依赖。本机无 Docker：e2e 以分支 CI 为准。

## 验收标准

每条一条命令。贴输出，不要只写「通过」。

1. Schema const 与默认：
   ```bash
   php -r 'require "vendor/autoload.php"; echo \WenPai\ChinaYes\Config\Schema::VERSION, PHP_EOL;'
   vendor/bin/phpunit --filter SchemaVersion2Test
   ```
   期望：打印 `2`；升级用例全绿。

2. 默认矩阵逐格：
   ```bash
   vendor/bin/phpunit --filter ProfileTest
   ```
   期望：三场景表 7 行 × 3 列全断言；切换不碰 `notice_control`。

3. Scope 判定：
   ```bash
   vendor/bin/phpunit --filter 'WenPai\\ChinaYes\\Tests\\Unit\\Connectivity\\ScopeTest'
   ```

4. 三模块门控（含既有白名单 / 头像模式，不得红）：
   ```bash
   vendor/bin/phpunit tests/Unit/Connectivity
   ```

5. 迁移 D3：
   ```bash
   vendor/bin/phpunit --filter FixturesTest
   ```
   期望：S2（`single-3.8-02.json`）`admin_assets=on`，`ignored` 不含 `admin`；新 fixture `single-3.9-08-admincdn-files-admin.json` 同样；`frontend` / `bootstrapcdn` 仍 ignored。报告字符串含「后台加速：已保留设置，4.1 起生效」。

6. ProfileSuggest + REST：
   ```bash
   vendor/bin/phpunit --filter ProfileSuggest
   vendor/bin/phpunit --filter ProfileSuggestController
   ```
   期望：四类 D4；响应无 IP 字段（`rg -n "ip" tests/Unit/Config/ProfileSuggestTest.php` 不得断言回传 IP）；权限拒绝有用例。

7. telemetry：
   ```bash
   vendor/bin/phpunit --filter ReportFieldsTest
   ```
   期望：`collect()` 含 `profile`。

8. 全量质量与前端（未改 JS，lint 仍须绿）：
   ```bash
   composer check
   npm run build
   npm run lint:js
   ```

9. 现有 e2e 不红（本机无 Docker → push 分支用 CI）：
   ```bash
   git push -u origin grok/m-scope-1
   gh run list --branch grok/m-scope-1
   gh run view <id> --log-failed
   ```
   禁止 push `main`。CI 未完时报告写「CI run \<id\> 进行中，本地验收如下」。

10. diff 范围：
    ```bash
    git diff --stat main...HEAD
    git status
    ```
    不含 `.grok-context/`，不含 `src/Admin/app/`。

提交前缀：`feat(config):` / `feat(connectivity):` / `feat(migration):` / `feat(rest):` / `feat(telemetry):`。按子系统分笔，不要揉成一个。每个 commit 前 `git status` 确认没把 `.grok-context/` 加进去。

## Definition of Done

执行者在报告里逐条打勾并贴证据。缺一条不得报「完成」：

- [ ] 规格条目 ↔ 实现逐条对照表（`| 规格编号 | 实现位置 文件:行 | 状态 |`），规格里有、实现没有的必须列出并写「未做」。本任务对照表指 **config-schema D2 默认矩阵逐键 ↔ `Profile.php` / `Mappers.php` 逐键**，以及 rest-api `/profile/suggest` 字段 ↔ 控制器。
- [ ] 每个状态一张截图（Playwright，命名 `<页面>-<状态>.png`，存 `docs/design/screens/<任务>/`），UI 任务必备；非 UI 任务写「不适用」。
- [ ] 面向用户的字符串：全部中文、在 `admin-ui-spec.md` §4 词表内或已提交词表新增；无英文错误串；无「遥测/隐私/上报」字样。本任务用户可见串只有迁移报告「后台加速：已保留设置，4.1 起生效」。
- [ ] 空状态、错误状态、降级状态有对应实现，不是「空表占位」。本任务：geo 失败 → `suggestion: null`；REST 权限拒绝 → `wpcy_forbidden`。
- [ ] 测试：新增/修改行为有测试；`composer check` 与 `npm run build`、`npm run lint:js` 通过；CI run id。
- [ ] `git diff --stat` 在允许路径内；`.grok-context/` 未入库。
- [ ] 报告含「没做 / 做不到 / 有疑问」；**报告写完再退出**（等 CI 时先写「CI run <id> 进行中，本地验收如下」）。

对照表模板：

```markdown
| 规格编号 | 实现位置 | 状态 |
|----------|----------|------|
| D2 wordpress_org domestic=auto | src/Config/Profile.php:行 | 已做 |
| D3 admin token → admin_assets=on | src/Migration/Mappers.php:行 | 已做 |
| GET /profile/suggest | src/Rest/ProfileSuggestController.php:行 | 已做 |
```

## 报告格式

见 `docs/dev/agents.md`。必须含：`git diff --stat` 原样、每条验收命令与输出摘要、没做/做不到/有疑问、`git log --oneline -5`。DoD 七项逐条打勾。
