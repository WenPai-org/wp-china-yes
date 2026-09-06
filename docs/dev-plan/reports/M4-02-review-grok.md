先按独立审查员读任务书、规范和相对 `40de819` 的全部改动，只读、不改文件。已确认范围是 `40de819..HEAD` 两笔提交。接着读任务书、规范和完整 diff。改动只有 3 个文件。接着读任务书、规范、spec 和完整 diff。对照 §5 审计表、Mappers 全文、fixtures 和验收标准，逐项核对。S2 把 `public_assets` 从默认五项改成 `[]`，这是行为变化。对照 fixtures、§7.2 和既有测试断言。接着跑 `composer check`、核哈希，并核对报告里未贴全的验收输出。审查对象是任务 **M4-02**（`31760da` + `f5ade69`，相对 `40de819`）。代码层无阻断缺陷；验证报告八行有哈希，但 S2/N2/N3/D2 命令原文不足，且 3.8 主力样本的 `public_assets` 从五项默认变成 `[]`。

范围：`3 files, +392 / -6`。`composer check` 本机 exit **0**。只读，未改任何文件。

---

## A 正确性

交付物齐（验证报告 + `Mappers` 两处修补 + 5 个 §5 单元断言），八行矩阵的哈希能对上 fixture 离线 `json_encode`；漏项是报告命令原文、`store=proxy` 未进矩阵、以及 hide/内存在 4.0 无目标字段。

**已做（对上任务书）**

| 交付 / 验收 | 证据 |
|---|---|
| 验证报告 | `docs/dev-plan/verification/m4-upgrade-matrix-2026-09-06.md:1` |
| `store=proxy` → `auto` | `src/Migration/Mappers.php:267`；测试 `tests/Unit/Migration/FixturesTest.php:247-258` |
| 3.8 `admincdn=['admin']` 与 `admincdn_files=['admin']` 同一 4.0 值 `[]` | `Mappers.php:192-201,306`；测试 `FixturesTest.php:291-316`；S2 断言从五项改为空 `FixturesTest.php:591` |
| 产品壳 / 幽灵 `enabled_sections` 丢弃 | `Mappers.php:185-188` default；`FixturesTest.php:348-406` |
| 不改 fixtures JSON、不改 `docs/dev-plan/README.md` | `git diff --name-status 40de819 HEAD` 只有上述 3 文件 |
| 提交前缀 | `fix(migration):`、`docs(verify):` |
| D1 非数组当空、不 Fatal | 本 diff 未改 Reader；既有 `LegacyReader.php:31-37` + `Plugin.php:121-133` `catch (\Throwable)` |
| D2 超大无映射键 ignored、不改写 3.x | 报告 `m4-upgrade-matrix-2026-09-06.md:144-152`；`huge_blob` 走 default `feature_removed` |

八行哈希我用 `json_encode` 对 fixture 重算，**八个全等**（含 D1 `"corrupted-string"`、D2 `json_len=1048952` / `keys=16` / `cee7ac6a…`）。这证明样本内容与报告一致，**不能单独证明**经过 WP `option update` 往返。报告里 N1 首次 `plugin install --activate --network` 非法、改成 `install --force` + `activate --network`（`m4-upgrade-matrix-2026-09-06.md:193`）是 Studio 现场痕迹。

**漏项**

1. 验收 1 要求每行贴预置命令、dry-run 输出、`option get`。S1/N1/D1 有片段；**S2/N2/N3/D2 只有哈希与一句话摘要**（`m4-upgrade-matrix-2026-09-06.md:77-127,144-152`）。按 `docs/dev/agents.md`「无输出支撑的完成视为未完成」，这几行证据偏薄。
2. 六份 fixture 的 `store` 全是 `off` 或 `wenpai`，**没有 `proxy`**。矩阵没打到验收 6 点名的这条，只靠合成测试 `FixturesTest.php:247`。
3. 验收 6 原文「`hide_*` 任一为真 → 白标菜单隐藏」「内存四项仅在 `performance` 为真时生效」：4.0 schema 无对应字段（`docs/specs/config-schema.md:35-68` 只有 `wordpress_org` / `public_assets` / `avatar` / `notice_control` / `windfonts`）。实现选择丢弃，报告写明（`m4-upgrade-matrix-2026-09-06.md:162,171,189`）。未偷映射到其它 4.0 能力，符合「不把删除功能映射到其它 4.0 能力」。
4. 行为规格里的 `wp wpcy migrate --rollback` Studio 未跑（报告 `m4-upgrade-matrix-2026-09-06.md:190`）；单元 `FixturesTest.php:151-170` 覆盖「rollback 不写 `wp_china_yes`」。
5. DoD 写「CI run id 见 push 后」（`m4-upgrade-matrix-2026-09-06.md:204`），报告内无 run id。
6. 任务书正文仍写「装 3.9.3 ZIP」（`tasks/M4-02.md:40-47`），文首修订改为 3.8 起步并装回 3.8（`tasks/M4-02.md:3`）。执行按文首。这是任务书自相矛盾，不是实现错误。

---

## B 运行时风险

本 diff 不引入 Fatal 路径；真正会改用户站行为的是 3.8 `admincdn=['admin']` → `public_assets=[]`（现网主力样本）。PHP 下限已是 8.0，不是 7.4。

**Fatal / Warning**

- 损坏 option：`exists()` 对非数组仍为 true（`LegacyReader.php:44-52`），`read()` 返回 `[]`（`:36`），首次启动 `execute()` 写入 **schema 默认** 4.0 文档，不碰 `wp_china_yes`。异常被 `Plugin.php:121-133` 吃掉记 warning。D1 报告 `eval_exit=0`、前台 200。此路径本 diff 未改。
- 超大键：`huge_blob` 进 default `ignored`，mapper 只扫键名，不把 1MiB 拷进 4.0 option。4.0 不写回 3.x，故无截断改写。矩阵跑在 Studio **SQLite**（报告 `:12-13`），未测 MySQL `max_allowed_packet`。
- 新代码无未定义下标：`has_v38_admincdn` 先 `array_key_exists`（`Mappers.php:192-193`）。

**边界**

- `admincdn=""` / `[]`：`as_token_list` 对非数组返回 `[]`（`Mappers.php:418-421`），`has_v38_admincdn` 为 false，**不触发**显式映射，仍用 schema 五项默认。N2/N3/S1 如此（`FixturesTest.php:582,618`）。
- `admincdn=['admin']`：**触发**映射，未知 token 剥离后得 `[]`（`Mappers.php:192-201,311-314`）。S2 从「五项默认」改成「全关」（`FixturesTest.php:591`）。
- 同一站 3.8 空数组 vs `['admin']` 对 4.0 公共库加速待遇相反：空 → 五项全开；勾了已删除的 `admin` → 五项全关。3.8 helpers 默认正是 `['admin']`（审计 §2），所以 **绝大多数 3.8 升级站会关掉 google_fonts/cdnjs/emoji 等**，与 4.0 新装默认相反。任务书要求两种 `admin` 形态落到同一 4.0 值，这是按任务做的，但是升级可感知行为变化。
- `store=proxy`：以前 `map_store` 返回 null，键 `invalid_value` ignored，4.0 值仍是默认 `auto`。现在显式 kept→`auto`（`Mappers.php:267`）。**4.0 取值没变**，dry-run 的 kept/ignored 变了。

**多站点**

- 网络走 `map_network` → `wpcy_network_settings`（`Mappers.php:253-255,229`）。N2/N3 的 `admincdn` 为空，不受新触发器影响。子站 `wpcy_settings` ABSENT 与既有 Runner 一致。本 diff 不改 Reader 的 `is_multisite()`。

**PHP 7.4**

审查题写「本仓仍声明 7.4」。**与仓内事实不符。**

```
wp-china-yes.php:13  Requires PHP: 8.0
composer.json:6      "php": ">=8.0"
composer.json:33     "platform": { "php": "8.0.0" }
```

M4-01 已把下限定为 8.0（总计划 M4-01 行）。本 diff 无 `match` / union / named argument / nullsafe。typed properties、`??`、`Throwable` 在 7.4 可用，但发布包不会在 7.4 上装。

---

## C 规范

本 diff 守 PSR-4 / `strict_types` / 禁区；无新用户文案。

- `Mappers.php:9` `declare(strict_types=1);`，类名与文件名一致，命名空间 `WenPai\ChinaYes\Migration`。
- 新注释英文（`Mappers.php:180,258,290-296`）。Yoda 与文件既有风格一致（`:267`）。
- 未碰 `framework/`。`Mappers` 仍声明「不读不写 WP option」（`Mappers.php:22-23`）；读 `wp_china_yes` 仍只在既有 `LegacyReader`。
- 未直写第三方商业域名。验证报告里的 GitHub Release / `localhost` 不是产品 UI。
- 「遥测」只出现在开发报告 DoD 否定句（`m4-upgrade-matrix-2026-09-06.md:202`），无用户可见文案。
- 未改 `docs/dev-plan/README.md`、未改 fixtures 数据。

---

## D 安全

本 diff 是纯映射与测试，无新攻击面。

- 无新 REST/表单，不涉及 capability / nonce。
- 无远程请求、无凭据、无 SQL、不写插件目录以外。
- `execute()` 仍不写 `wp_china_yes`（`Runner.php:81` 注释 + `:87-100` 只 `backup` + `save_option` 4.0）。
- 备份断言仍排除 `credential` / `bridge`（`FixturesTest.php:120-121`，既有）。
- 输入来自库里的 3.x option，输出经 `Validator::sanitize`（`Mappers.php:230`）。`admin` 不在 `Schema::PUBLIC_ASSETS` 枚举（`Schema.php:31-37`），进不了 4.0 文档。
- D2 1MiB 字段留在 3.x option（autoload 若为 yes，每次请求仍加载）。4.0 不删 3.x，这是既定契约，不是新洞。

---

## E 测试质量

新 5 个用例打到 §5 点名项，`composer check` 全过；有几处假通过，且 `store=proxy` / 混合 token 未覆盖。

**假通过 / 弱断言**

- `test_hide_keys_are_discarded_not_mapped`（`FixturesTest.php:284-285`）：`assertArrayNotHasKey('hide')` / `'brand'` 查的是 4.0 文档**顶层**。schema 顶层本来就没有这两键，即便映射进某个嵌套字段也会绿。真正有用的是 `ignored_reasons === feature_removed`（`:278`）。
- `assertStringNotContainsString('hide_option', $json)`（`:282`）同理：4.0 JSON 不会出现该字符串，除非有人把键名原样写进去。
- `test_memory_keys_discarded_even_when_performance_true` 的 `assertArrayNotHasKey('performance')`（`:342`）同样是顶层空键。
- `test_store_proxy_maps_like_wenpai` 只测 proxy→auto，没有在同一测试里跑 `wenpai` 再 `assertSame`。取值碰巧都是 `auto`，等式靠注释而非断言。
- `test_admincdn_v38_and_files_admin_same_public_assets` 只覆盖「仅 `admin`」。未测 `admincdn=['admin','emoji']`、也未测 3.8 `admincdn` 与 3.9 三键同时存在。
- `covered_by_ignored_key`（`Mappers.php:202-210`）让 v38 的 token `admin` **不出现**在 `ignored[]`，这样 `expected_ignored()` 仍等于 fixture 键差（`FixturesTest.php:556-567`）。若不加这段跳过，S2 的 `test_dry_run_kept_ignored_sets` 会因多出 token `admin` 而红。这是为迁就「ignored = 未 kept 的 fixture 键」而写的，dry-run 对 v38 漏报具体 token。

**未覆盖**

- 真实 fixture 无 `store=proxy`。
- 3.8 `admincdn=[]`（显式空）vs `['admin']` 的分叉没有独立测试（只靠 S1/S2 两条 fixture 的不同期望）。
- Studio rollback 无集成测试（有单元）。

**`composer check`（本机，exit 0，约 64s）**

```
phpstan  73/73  [OK] No errors
phpunit  smoke 1  core 23  config 40  connectivity 92  telemetry 3
         privacy 11  diagnostics 13  cli 8  rest 24  integrations 20
         migration 34 tests, 508 assertions
         site-binding 12  apps 64  entitlements 11  admin 19
```

`@lint`（phpcs）无违规输出，整体 exit 0。与报告「Migration `OK (34 tests, 508 assertions)`」一致。未跑 `npm`（本 diff 无 JS）。

---

## F 与 spec 的偏差（逐条）

对照 `docs/4.0-rewrite-plan.md` §7.2、`docs/specs/config-schema.md`、审计 §5、任务书修订。

| # | spec / 决定 | 实现 | 判定 |
|---|---|---|---|
| 1 | §7.2 `store`：`wenpai`→`auto`，`off`→`off`；无 `proxy` | `proxy` 与 `wenpai` 同为 `auto`（`Mappers.php:267`） | **有意偏离 §7.2**，任务书验收 6 / 审计 §5「`proxy` 映射 `wenpai`」批准 |
| 2 | §7.2 公共库：「**三键**都缺失用 schema 默认」；三键 = `admincdn_public/files/dev` | 3.8 `admincdn` 非空 token 当作第 4 触发键（`Mappers.php:192-198`）。S2 三键均缺失却得到 `[]` 而非五项默认 | **有意偏离 §7.2**，任务书要求与 `admincdn_files` 含 `admin` 同一 4.0 值 |
| 3 | §7.2 未把 3.8 `admincdn` 列为 `public_assets` 源 | `map_public_assets` 合并 `admincdn` token（`Mappers.php:306`）；支持的 token（如将来出现的 `emoji`）会进 4.0 | 任务批准的扩展；混合 token 无测试 |
| 4 | 审计 §5：`hide_*` 任一为真 → 写入 3.x `hide=true` | 4.0 无白标字段，三键 + `hide` 全 `feature_removed` | 按「不映射已删功能」处理；**未做任务书字面「白标菜单隐藏」** |
| 5 | 审计 §5：内存四项保留值，应用时 `performance==true` | 一律 `feature_removed`，`performance=true` 也不写 | 同上；4.0 已删 `WP_MEMORY_LIMIT`（M4-01） |
| 6 | 审计 §5：`comments_*` / `waimao_*` / `plane*` / `memory*` / `custom_name` 等「保留」 | 4.0 schema 无这些键，fixture 中它们进 ignored | 按 4.0 白名单丢弃，不恢复已删模块；与 §7.2「其他字段不迁移」一致，与审计「保留」字面不一致 |
| 7 | 审计 §5：`notice_control` 直接丢弃 | 有值时仍 `modules.notice_control=true` 且 kept（`Mappers.php:153-157,337-339`） | **本 diff 未改**；S3 样本是空串 → `empty` ignored |
| 8 | 审计 §5：`enabled_sections` 只丢掉幽灵值 | 整个键 `feature_removed`（4.0 无 section 闸） | 比 §5 更粗；4.0 无目标，可接受 |
| 9 | `config-schema.md` `public_assets` 默认五项 | 迁移结果可以是 `[]`；schema 无 `minItems`，合法 | 与 M2-01b「显式空 → `[]`」一致；争议在「3.8 仅 admin 算不算显式空」 |
| 10 | §7.1：业务模块不再读 `wp_china_yes`；迁移可重复、可回滚、dry-run | 本 diff 不破坏该契约 | 符合 |
| 11 | 任务书：3.9.3 尚未放行则停 | 用 GitHub prerelease 附件，SHA 贴出（报告 `:26`） | 按 runbook「Release 附件」；通道未放行，报告已写明 |

---

## G 清单

### 阻断

无。mapper 两处改动与任务书 2026-09-06 修订一致；schema 合法；`composer check` 绿；未改 fixtures / README；无 Fatal 新路径。

### 建议

1. **3.8 主力升级后公共库加速全关** — `FixturesTest.php:591`、`Mappers.php:192-201`。S2（现网主力）`public_assets` 从 `Schema::PUBLIC_ASSETS` 改为 `[]`。若产品期望 3.8 站升级后仍享受 4.0 默认五项，当前实现相反。需要产品书面确认「仅 admin 的 3.8 站 = 显式关闭公共库」。
2. **补报告命令原文** — `m4-upgrade-matrix-2026-09-06.md:77-127,144-152`。S2/N2/N3/D2 补上 `wpcy migrate --dry-run --format=json` 与 `option get wpcy_settings|wpcy_network_settings` 的实际输出，不要只留哈希。
3. **`store=proxy` 进矩阵或合成 Studio 行** — 六份 fixture 都没有 proxy（全仓 `tests/fixtures/legacy-options/*.json` 的 store 只有 `off`/`wenpai`）。单元测试不够代替验收 6 点名项。
4. **收紧 hide/内存测试** — `FixturesTest.php:284-285,342`：不要断言 4.0 顶层没有 `hide`/`brand`/`performance`（本来就没有）。改为扫 `wp_json_encode($report->settings())` 的键路径，或断言 `modules` / `connectivity` 的键集合等于 schema。
5. **v38 ignored 漏 token** — `Mappers.php:202-210`。`admincdn=['jquery']` 时 dry-run 只有键 `admincdn`，没有 token `jquery`。修法：unknown token 始终进 `ignored`（可与键并存），并改 `expected_ignored()` 显式列出，不要用「fixture 键 − kept」一刀推导。
6. **3.8 `admincdn=[]` 与 `['admin']` 分叉** — 空数组仍五项默认，`['admin']` 变 `[]`。若要把 3.8 `admincdn` 当第 4 键，按 §7.2「任一存在且合并为空则 `[]`」，`has_v38_admincdn` 应改成 `array_key_exists('admincdn', $legacy)`，不要要求 token 非空。当前 S1/N2/N3 会从五项变成 `[]`，需同时改 fixture 断言。
7. **Studio 补跑一次 rollback** — 任务行为规格有；报告 `:190` 承认没跑。命令：`studio wp --path ~/Studio/wpcy-40 wpcy migrate --rollback` 后 `option get wp_china_yes` 哈希不变、`wpcy_settings` 删除。
8. **补 CI run id** — 报告 `:204`。

### 确认无误

- `proxy` 与 `wenpai` 都映射 `connectivity.wordpress_org=auto`（`Mappers.php:267`）。
- 3.8 `admincdn=['admin']` 与 `admincdn_files=['admin']` 的 4.0 `public_assets` 均为 `[]`（`FixturesTest.php:305-309`）。
- 产品壳、幽灵 `enabled_sections`、`hide_*`、内存四项未写入 4.0 文档。
- 未改 fixtures JSON 数据、未改 `docs/dev-plan/README.md`、未碰 `framework/`。
- 4.0 不写 `wp_china_yes`；D1 损坏串、D2 `huge_blob` 在报告哈希下四阶段相同，且与离线 `json_encode` 一致。
- 无用户可见「遥测」文案；无新第三方域名。
- 提交前缀符合验收 5。
- 本机 `composer check` exit 0；Migration `34 tests, 508 assertions`。
