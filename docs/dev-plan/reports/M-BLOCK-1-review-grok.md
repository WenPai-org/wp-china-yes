独立审查 `fcc981d` 到 HEAD 的改动。先读任务书、规范和 diff，再逐项核验收。用户指定了审查格式和基线 `fcc981d`，我按任务书与规范自己核，不走通用 review 工作流。任务是 M-BLOCK-1。接着读规格、实现和测试。继续核对 Validator、Plugin 注册、基线签名、PHP 版本声明，以及测试是否假通过。核对几个关键边界，并跑 `composer check`。再核几处容易假通过的测试和 `none/allow` 是否真不可达。对照任务书 `docs/dev-plan/tasks/M-BLOCK-1.md` 与 `fcc981d..HEAD`（3 笔：`59b2375` / `f56512d` / `0d088c0`）做了只读审查。结论：**专用 REST 与 L0/L2 运行时大体可工作，但存在一条可绕过「保存时拒 L0」的写入路径，不能按任务书报完成。**

审查范围：`git diff fcc981d HEAD`，23 文件，+2615 / −19。未改任何文件。

---

## A 正确性

结论：**专用端点与三层过滤的主路径做到了；`PUT /settings`、`PUT /network-settings` 仍可写入 L0 主机，任务书 A2 / rest-api「保存时整单拒绝」只守住了 `/site-blocklist`。**

交付物对照（任务书表格）：

| 交付 | 位置 | 状态 |
|------|------|------|
| Ruleset `is_protected` / `noise_match` / 验签丢增量 | `src/Privacy/DataResidency/Ruleset.php:237` `:308` `:449` | 已做 |
| 基线 L0 + 空噪声包 + 重签 | `src/Privacy/rulesets/baseline.json:44`；本机 `Ruleset::verified()===true` | 已做 |
| L0 prio 5 / L1 10 / 噪声 12 | `DataResidencyModule.php:116` | 钩子在；L0 回调本身是空操作（见 B） |
| SiteBlocklist prio 15、`wpcy_site_blocklist_blocked` | `SiteBlocklistModule.php:117` `:160` | 已做 |
| Schema / Defaults / 覆盖袋去掉 `site_blocklist` | `Schema.php:180` `:458`；`Defaults.php:52` | 已做 |
| GET/PUT `/site-blocklist` | `SiteBlocklistController.php`；`RestModule.php:253` | 已做 |
| GET `/residency/protected`、POST `/residency/test` | `ResidencyController.php:115` `:129` | 已做 |
| `Plugin::create()` 注册模块 | `Plugin.php:209` | 已做 |
| 诊断只读、test 不落库 | `OutboundLayers.php:76` `:124` | 已做 |
| 不改 `src/Admin/app/`、不改决定文件 | `git diff --name-only` | 已做 |

漏项 / 做错：

1. **保存拒 L0 只接在 `SiteBlocklist\Repository::validate()`**（`Repository.php:236`）。`DocumentWriter::put()` 只拦覆盖袋里出现该键（`DocumentWriter.php:61`），**不跑 L0 校验**。单站 `PUT /settings`、多站点 `PUT /network-settings` 的 body 只要 schema 合法，就能把 `api.wenpai.net` 写进 option。运行时会跳过这些行（`SiteBlocklistModule.php:144`），但 GET 仍会把 L0 主机展示成用户清单，违反决定 A2「界面/REST 保存时拒绝」。
2. **`wpcy_settings_network_only_key` 有实现、无测试**（全仓 `tests/` 零命中）。提交说明写了这条，DoD「新增行为有测试」未满足。
3. **`filter_l0` 两条分支都 `return $preempt`**（`DataResidencyModule.php:131-140`），优先级 5 的钩子不改变控制流；L0 实际靠后层自己 `is_protected()`。任务书「不把请求交给后层拦截」在 WP `pre_http_request` 语义下只能靠后层自觉，测试是手工串调，不是钩子顺序。
4. **`POST /residency/test` 对 L1 `reroute` 无视 `ingest_ready`**：`OutboundLayers.php:138-143` 两个分支返回值相同，探测为假时诊断仍报 `action: reroute`，与运行时（不改道、请求仍出站）不一致。rest-api.md 写「按运行时同一顺序判定会被哪一层如何处理」。
5. **噪声包 skip 只进进程内 `noise_skipped`**（`DataResidencyModule.php:230`），`GET /residency/protected` 的 `noise_block.hosts` 不含 skip 原因。任务书「运行时忽略该条，记诊断」没有诊断面。

验收命令（本机已跑，均绿，但不能覆盖上面的写入旁路）：

```
vendor/bin/phpunit --filter SiteBlocklist
OK (11 tests, 37 assertions)

vendor/bin/phpunit --filter LayerOrder
OK (4 tests, 9 assertions)

vendor/bin/phpunit --filter ProtectedHosts
OK (3 tests, 19 assertions)

vendor/bin/phpunit --filter 'ResidencyProtected|ResidencyTest'
OK (7 tests, 31 assertions)
```

---

## B 运行时风险

结论：**专用路径边界大体有守；恢复模式与诊断/运行时分叉、以及第二条写入路径，会在真站上出现「看起来拦了 / 实际没拦」或「不该存下来的主机被存下来」。**

- **Fatal**：新代码无未定义类/无返回。typed property 与 `declare(strict_types=1)` 齐全。`Ruleset::load()` 验签失败时 `document` 保持空数组，`protected_hosts()` 仍合并 `BUILTIN_PROTECTED_HOSTS`（`Ruleset.php:261` `:473`），不会把 L0 放成空。
- **`filter_l0` 空操作**：L0 命中既不 short-circuit、也不打标。后层若漏检就会拦文派域。当前 L1 / 噪声 / L2 都有 `is_protected` 再检，现网暂时自洽。
- **恢复模式**：L2 `enabled()` 读 `recovery_mode`（`SiteBlocklistModule.php:99`）。噪声包在 `DataResidencyModule`（非 `ConditionalModule`）里，`noise_enabled()` 只看 `modules.noise_block.enabled`（`:448`），**恢复模式下仍拦**。config-schema.md:286 写「全部可选模块停用」。
- **多站点**：读写走 `wpcy_network_settings`（`SiteBlocklist/Repository.php:98`）；覆盖袋 schema 不含该键（`Schema.php:180`）；读时 `strip_network_only_keys`（`Config/Repository.php:545`）。子站 `manage_options` PUT `/site-blocklist` → 403（测试有）。子站若改走 `PUT /settings` 带该键 → 400 `wpcy_settings_network_only_key`（`DocumentWriter.php:309`），**无测试**。
- **PHP 版本**：用户说「本仓仍声明 7.4」与仓内事实不符。`composer.json:6` `"php": ">=8.0"`，`wp-china-yes.php:13` `Requires PHP: 8.0`，coding-standards 底线 8.0。本 diff 无 union type / `mixed` 返回以外的 8.1+ 语法；本机 `php=8.4.7` 下 `composer check` 通过。按 8.0 看，无 7.4 兼容债。
- **`note` 长度**：`strlen( $row['note'] ) > 200`（`SiteBlocklist/Repository.php:230`）按字节；JSON Schema `maxLength` 按字符。中文备注约 67 字就会被 400。
- **C 档 `host: *`**：基线永远匹配，`OutboundLayers::test()` 的 `layer=none`（`:172`）在现网 ruleset 下不可达。不是崩溃，是诊断枚举少了一支。

---

## C 规范

结论：**PSR-4 / strict_types / 禁区主项通过；有一处业务类直读 option，以及 `profile_defaults()` 无人消费。**

- 命名空间 `WenPai\ChinaYes\`，一类一文件，全部 `declare(strict_types=1)`。
- 构造函数不挂钩子；`register()` 里 `add_filter`。
- **禁区**：diff 不含 `src/Admin/app/`、`framework/`、决定文件、`.grok-context/`；无 `HttpBlock` / `http-block`；无用户可见「遥测」「匿名数据」；REST 用户串只有「文派服务不可拦截」（`SiteBlocklist/Repository.php:343`），text domain `wp-china-yes`。
- 未读 `wp_china_yes` option。
- 第三方主机出现在噪声跳过名单 `cdnjs.cloudflare.com` / `cdn.jsdelivr.net`（`Ruleset.php:93`），是拦截豁免不是商业外链，与「直写第三方商业域名」不是同一条。
- **擦边**：`SiteBlocklist\Repository::load_option()` 直接 `get_option` / `get_site_option`（`:309`），为了保存时不要合并覆盖袋。coding-standards「业务模块不直接 get_option」被破例。
- `SiteBlocklistModule::profile_defaults()`（`:78`）按 module-authoring 声明了，`Profile::apply_to()` 不读它（`Profile.php:85` 只动 `windfonts`）。声明在、接线无，符合「不得偷偷写 option」，但是死代码。
- WPCS：`composer check` 含 `phpcs`，exit 0。

---

## D 安全

结论：**专用 REST 的 cap + nonce 形状正确；L0 校验没进通用 PUT，是本 diff 最大的授权/完整性漏洞（不是 RCE）。**

- `/site-blocklist` 读：`current_user_can( required_cap() )`（`SiteBlocklistController.php:106`）。写：cap + `Permissions::nonce_ok`（`:124`）。多站点 `manage_network_options`，单站 `manage_options`（`:140`），与决定拍板第 5 条一致（规格表仍写单站也要 network cap，实现跟拍板）。
- `/residency/protected` 用 `manage_options_read`；`/residency/test` 用 `manage_options_write`（带 nonce）（`RestModule.php:235` `:247`）。与「权限同 `/diagnostics`」一致。
- 输入：host 走 schema pattern + 拒 `*` `/` `:`（`SiteBlocklist/Repository.php:216`）。无 SQL、无写插件目录、无用户可控远程 URL（test 端点不发请求）。
- 凭据：未碰 `binding.credential`。
- 远程：本 diff 未新增出站；既有 reroute 仍 `timeout 10` + `sslverify true`（`DataResidencyModule.php:358`）。
- **缺口**：通用设置 PUT 无 L0 校验（见 A）。有 `manage_options` 的人可以不走 `/site-blocklist` 把保护主机写进清单。运行时不拦 L0，所以不是「拦文派」，是污染配置与诊断展示。

---

## E 测试质量

结论：**`composer check` 全绿，但几条验收测试名过实，关键旁路零覆盖。**

本机 `composer check`（约 148s，exit 0）摘要：

```
phpstan: [OK] No errors  (87/87)
phpcs:   随 check 第一段，exit 0（成功时无告警）
unit:    smoke 1, core 23, config 84, connectivity 112, telemetry 3,
         privacy 28, diagnostics 13, cli 8, rest 60, integrations 20,
         migration 43, site-binding 12, apps 68, entitlements 11, admin 19
         全部 OK
```

假通过 / 未覆盖：

| 测试 | 问题 |
|------|------|
| `test_enabled_false_does_not_register` | 只断言 `enabled()` 返回值，**从未调用 `register()`**，也不查 `ModuleRegistry::boot`（`SiteBlocklistTest.php:164`） |
| `LayerOrderTest` | 手工 `filter_l0 → L1 → noise → L2`，**不验证 `add_filter` 优先级 5/10/12/15** |
| `test_signed_increment_appends_without_removing_builtin` | `new Ruleset( $path, null, false )` 关闭验签，测的是「不验签时合并」，不是「验签通过的增量」 |
| `ResidencyProtectedTest::controller()` | `ingest_ready = true` 写死（`:190`），ingest 为假时的 `reroute` 诊断无测 |
| `wpcy_settings_network_only_key` | **零测试** |
| PUT `/settings` 写入 L0 | **零测试** |
| 噪声 `.org` / CDN skip | **零测试**（`tests/Unit/Privacy` 无 `noise_skip`） |
| `layer=none` | **零测试** |
| 恢复模式 × 噪声包 | **零测试** |

`test_save_rejects_protected_hosts`、`test_runtime_ignores_protected_rows`、`test_max_twenty_hosts_rejected`、suffix、坏签名回退、REST 中文 400，这些是真断言，不是空壳。

---

## F 与 spec 的偏差

逐条（决定原文优先于任务书/规格表，任务书自己也这么写）：

| 规格 | 实现 | 判定 |
|------|------|------|
| 决定 A3 + 拍板 1：cravatar 两域 **suffix** | `Ruleset.php:64-69` `match => suffix`；测试断言 `cn.cravatar.com` 保护 | 跟拍板。任务书正文与 `data-residency-ruleset.md` §10.1 表仍写 **exact**，规格文档未改 |
| 拍板 2：云桥 ingest 空常量、不发明 FQDN | `CLOUD_BRIDGE_INGEST_HOSTS = array()` `:85` | 符合 |
| 拍板 5：单站 `manage_options` | `required_cap()` | 跟拍板。`rest-api.md:311` 仍写单站也是 `manage_network_options` |
| 拍板 6：`schema_version` 保持 2 | `Schema::VERSION = 2` | 符合 |
| 拍板 8：覆盖袋含 `site_blocklist` → 400 `wpcy_settings_network_only_key` | `DocumentWriter.php:61-66` | 代码有，测试无 |
| A2 / rest-api：保存命中 L0 → 400，整单不写 | 仅 `/site-blocklist` | **偏离**（通用 PUT） |
| rest-api `/residency/test` 与运行时同一顺序 | ingest 未就绪仍报 `reroute` | **偏离** |
| rest-api：`layer=none` 且 `action=allow` = 都未拦 | 基线 C `*` 使 none 不可达 | 现网行为合理，枚举未测 |
| config-schema：覆盖袋 modules 允许 `noise_block`，不允许 `site_blocklist` | `Schema::modules( false )` 仍含 `noise_block` | 符合 |
| config-schema：`recovery_mode` 停可选模块 | 噪声包不停 | **偏离** |
| config-schema host `maxLength` 253 / note 200 字符 | host 用正则；note 用 `strlen` 字节 | note **偏离** |
| 任务书：噪声命中 L0/.org/CDN 记诊断 | 内存数组，REST 不返回 | **偏离** |
| 任务书预计 diff ≤ 1500 | `2615 insertions` | 过程偏离，不是行为 bug |

Cravatar suffix：任务书写 exact，并写「冲突时以决定原文为准」。实现选拍板，方向对；规格表没跟上，审查不能当成实现错误。

---

## G 清单

### 阻断

1. **通用设置 PUT 可写入 L0 主机**  
   - 文件：`src/Rest/DocumentWriter.php:56`（`put()` 无 L0 校验）；`src/Config` Validator 不知 L0；对比 `src/Privacy/SiteBlocklist/Repository.php:236`  
   - 修法：所有写入 `modules.site_blocklist` 的路径（`DocumentWriter::put` 在 sanitize 之后、`Repository::set` / `save_option`）调用 `SiteBlocklist\Repository::validate()`（或抽出同一函数）。任一 `is_protected($host)` → 整单 `WP_Error( 'wpcy_blocklist_protected_host', '文派服务不可拦截' )`，不 `save_option`。补测：单站 `PUT /settings`、多站点 `PUT /network-settings` 带 `api.wenpai.net` 必须 400 且 option 不变。

### 建议

1. **`OutboundLayers::test()` 删除重复 reroute 分支**（`OutboundLayers.php:138-143`）。ingest 未就绪时应继续走噪声/L2，或 `action` 改为实际处置（放行）并在 `detail` 标明 `enabled_when`。补测 `ingest_ready=false`。  
2. **恢复模式下关掉噪声包**：`noise_enabled()` 增加 `recovery_mode` 判断，与 L2 一致。  
3. **给 `wpcy_settings_network_only_key` 补 REST 测试**（子站 PUT `/settings` 带 `modules.site_blocklist`）。  
4. **`filter_l0` 要么删掉空钩子，要么真正打标**（例如静态/请求级「本 URL 已 L0」），避免后层漏检。  
5. **`note` 用 `mb_strlen( ..., 'UTF-8' )`**（`SiteBlocklist/Repository.php:230`）。  
6. **噪声 skip 原因进 `GET /residency/protected`**（或诊断卡字段），现在 `noise_skipped()` 没有出口。  
7. **改测试名或补断言**：`test_enabled_false_does_not_register` 应断言未 `add_filter`；`LayerOrderTest` 至少读 `register()` 的 priority 参数或挂到 stub 的 hook 表。  
8. **给验签通过的增量**一条测试（现在 append 用例 `verify=false`）。

### 确认无误

- L0 硬编码五条 + 空 ingest 常量；增量只能追加；坏签名不合并、`wenpai.net` 仍保护（`ProtectedHostsTest` 19 断言）。  
- 基线已重签：`verified()===true`，`protected_source()===builtin+signed`。  
- L2：20 条 → `wpcy_invalid_schema` 400；suffix 命中子域、不命中 `notexample.com`；已存 L0 行运行时忽略；`enabled=false` 时 `enabled()` 为假。  
- `/site-blocklist` 保护主机 400，message 精确等于「文派服务不可拦截」；多站点无 network cap → 403。  
- 三条 REST 已注册；`Plugin::create()` 含 `privacy.site_blocklist`。  
- 不碰 React、framework、决定文件；无 HttpBlock 品牌；无「遥测」类用户文案。  
- `composer check` exit 0。
