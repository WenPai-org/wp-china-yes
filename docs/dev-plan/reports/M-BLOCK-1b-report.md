先读任务上下文和审查阻断项，再按任务书改代码。审查文档和相关代码先读完，再按阻断项动手。依赖还在装，先把写入路径和相关测试读完。依赖已装好。接着读测试、Config 写入和 REST 规格，确定两笔提交怎么拆。开始改代码：先堵住通用 PUT 写入 L0 的路径，再处理审查里升为必修的建议。接着改建议项，并补 REST 测试。`save_option` 不能对已存 L0 再拒整单，否则改其它设置会被锁死。只在清单相对已存有变化时校验。`save_option` 用规范化后的清单比较，避免无关 PUT 被已存 L0 锁死。补测试：通用 PUT 拒 L0、network_only_key，以及建议 1/2/5/7/8。对齐测试里的赋值，再跑 phpcs。把测试里的 `OptionStore` 用别名，避免长链式赋值。刚才那处替换把 `mar` 打进去了，立刻改回来。把误插入的 `mar` 删掉。`register()` 的 DocBlock 首字母大写会触发 sniff，改成不以小写方法名开头。`$config` 属性现在没人读了，去掉以免 phpstan 报错。`ConfigRepository` 仍作构造参数用，import 保留。接着跑 phpstan 和测试。`composer check` 已绿。按任务书拆成两笔提交，再 push 跑 CI。按阻断项和建议项拆成两笔提交。第二笔提交里 `SiteBlocklistTest.php` 同时含阻断路径（`config_set`）和建议项（note / add_filter）。任务书写了分两个 commit，这条测试文件跨了两边。把 `test_config_set` 挪到第一笔更干净。`SiteBlocklistTest` 同时覆盖阻断路径和建议项，留在第二笔一起提交，报告里写明。已 push。接着查 CI run id。CI 已排队。按任务书先写报告，不等全绿。补验收命令输出和 DoD 证据。`--filter LayerOrder` 跨 suite 会撞上 `KernelSwitchTest` 的空 `add_filter`。改成同时断言 `register()` 源码里的优先级，避免依赖 stub。`--filter LayerOrder` 跨 suite 会撞上空的 `add_filter`。源码优先级断言已经补上，再验证一次。第二笔 CI（`34040587763`）已绿。第三笔 `516ad46` 的 run `34040789846` 还在跑，按任务书先写报告。

# M-BLOCK-1b 最终报告

阻断项已修：所有写入 `modules.site_blocklist` 的路径在落库前走 `SiteBlocklist\Repository::validate()`；任一 L0 host 整单 `WP_Error('wpcy_blocklist_protected_host', '文派服务不可拦截')`，不落库。建议 1/2/3/5/7/8 与 `wpcy_noise_block_blocked` 文档已做。建议 4、6 未做。

CI run **34040789846** 进行中（HEAD `516ad46`）。上一笔 `762e7cb` 的 CI run **34040587763** 已绿。本地验收如下。

## 1. 改了哪些文件

`git diff --stat 0d088c0`：

```
 docs/specs/rest-api.md                            |   3 +-
 src/Config/Repository.php                         |  90 +++++++++++++++++++
 src/Diagnostics/OutboundLayers.php                |  19 ++--
 src/Privacy/DataResidency/DataResidencyModule.php |   9 +-
 src/Privacy/SiteBlocklist/Repository.php          |   8 +-
 src/Rest/DocumentWriter.php                       |  43 +++++++++
 tests/Unit/Privacy/LayerOrderTest.php             |  70 +++++++++++++++
 tests/Unit/Privacy/ProtectedHostsTest.php         |  54 +++++++++++
 tests/Unit/Privacy/SiteBlocklistTest.php          |  74 +++++++++++++++
 tests/Unit/Privacy/wp-http-stubs.php              |  13 ++-
 tests/Unit/Rest/PermissionsTest.php               | 105 +++++++++++++++++++++-
 tests/Unit/Rest/ResidencyProtectedTest.php        |  17 ++++
 12 files changed, 485 insertions(+), 20 deletions(-)
```

未改 `src/Admin/app/`，未入库 `.grok-context/`。

## 2. 验收命令与输出摘要

### 开工前

`composer install --no-interaction`：Nothing to install。`npm ci`：added 1862 packages，exit 0。

### 阻断：通用 PUT 拒 L0 + 子站 network-only

```
vendor/bin/phpunit --testsuite rest --filter 'PermissionsTest|ResidencyProtected'
OK (34 tests, 179 assertions)
```

新增用例：

- `test_put_settings_protected_host_is_400_and_option_unchanged`：单站 `PUT /settings` 带 `api.wenpai.net` → `wpcy_blocklist_protected_host` 400，option 不变。
- `test_put_network_settings_protected_host_is_400_and_option_unchanged`：多站点 `PUT /network-settings` 同上。
- `test_subsite_put_settings_site_blocklist_is_network_only_key`：子站 `PUT /settings` 带 `modules.site_blocklist` → `wpcy_settings_network_only_key` 400。

```
vendor/bin/phpunit --filter SiteBlocklist
OK (13 tests, 43 assertions)
```

含 `test_config_set_rejects_protected_host`（`Repository::set()` 拒 L0）。

### 建议 1：ingest_ready=false

```
vendor/bin/phpunit --filter 'ResidencyProtected|ResidencyTest'
OK (8 tests, 35 assertions)
```

`test_residency_test_ingest_not_ready_falls_through`：`layer=l1`，`action=allow`（不是 reroute），`detail.enabled_when=ingest_ready`。

### 建议 2 / 7：恢复模式噪声 + 优先级

```
vendor/bin/phpunit --filter LayerOrder
OK (6 tests, 18 assertions)

vendor/bin/phpunit --testsuite privacy --filter LayerOrder
OK (6 tests, 22 assertions)
```

`test_recovery_mode_disables_noise`：`recovery_mode=true` 时 `noise_enabled()===false`，噪声钩子不拦。`test_register_priorities_are_l0_5_l1_10_noise_12_l2_15`：读 `register()` 源码里的 5/10/12/15。

### 建议 5 / 7 / 8

```
vendor/bin/phpunit --filter ProtectedHosts
OK (4 tests, 25 assertions)
```

`test_verified_increment_is_merged`：验签通过后 `extra.example` 并入，`api.wenpai.net` 仍保护，`verified()===true`。

`test_note_max_length_is_utf8_characters`：200 个「文」通过，201 个 → `wpcy_invalid_schema`。

`test_enabled_false_does_not_register`：`ModuleRegistry::boot()` 后 `$GLOBALS['wpcy_privacy_filters']` 为空（privacy suite 内）。

### composer check

```
composer check
phpstan: [OK] No errors (87/87)
phpcs:   随 check，exit 0
unit:    smoke 1, core 23, config 84, connectivity 112, telemetry 3,
         privacy 33, diagnostics 13, cli 8, rest 64, integrations 20,
         migration 43, site-binding 12, apps 68, entitlements 11, admin 19
         全部 OK
exit 0（约 127s）
```

该次 check 跑在第三笔测试提交之前。第三笔只改 `LayerOrderTest.php`，随后 `phpcs` + `--filter LayerOrder` 绿。

### CI

```
gh run list --branch grok/m-block-1 --limit 8
in_progress  test: assert L0/L1/noise/L2 priorities from register() source  34040789846
completed    success  fix(residency): match test/runtime for ingest…        34040587763
completed    success  feat(rest): add site-blocklist…                       34035832300
```

禁止 push `main`。分支 `grok/m-block-1` 已 `git push -u origin grok/m-block-1`。

## 3. 没做 / 做不到 / 有疑问

- **建议 4（`filter_l0` 空钩子）**：未做。任务书标可选，要求「做则做全」。现网 L1/噪声/L2 各自再检 `is_protected()`，空钩子不改控制流。要真正 short-circuit 会改 `pre_http_request` 语义，超出本任务「不自行拍板」。
- **建议 6（噪声 skip 原因进 `GET /residency/protected`）**：未做。同上，可选。`noise_skipped()` 仍是进程内数组，REST 不返回。
- **任务书写「分两个 commit」**：实际三笔。前两笔按阻断 / 建议拆。`--filter LayerOrder` 跨 suite 会加载 `KernelSwitchTest` 的空 `add_filter`，第三笔 `516ad46` 把优先级断言改成读 `register()` 源码。`SiteBlocklistTest.php` 同时含 `set()` 拒 L0（阻断）与 note/`add_filter`（建议），整文件在第二笔。
- **`save_option` 对已存、未改的 L0 行不拒整单**：已存污染清单改其它键仍可保存；运行时仍跳过这些行。新写入或清单变化仍拒。任务书「任一 host `is_protected()` → 整单不落库」按「本次写入的清单」理解。
- **3.x 选项审计 §5 ↔ `Mappers.php` 逐键**：本任务不改迁移映射，对照表标未做。
- **`npm run build` / `npm run lint:js`**：本任务未改 JS，未跑。
- **CI run 34040789846**：写报告时仍 `in_progress`，未贴 `--log-failed`。

## 4. 提交哈希

`git log --oneline -5`：

```
516ad46 test: assert L0/L1/noise/L2 priorities from register() source
762e7cb fix(residency): match test/runtime for ingest, recovery, UTF-8 notes
ff45a15 fix(rest): reject L0 hosts on generic settings PUT
0d088c0 feat(rest): add site-blocklist and residency protected/test endpoints
f56512d feat(config): add site_blocklist and noise_block schema keys
```

## DoD

- [x] 规格 ↔ 实现对照表（本任务范围；§5 ↔ Mappers 未改）

| 规格编号 | 实现位置 | 状态 |
|----------|----------|------|
| A2 / rest-api：保存命中 L0 → 400，整单不写 | `src/Rest/DocumentWriter.php:80`；`src/Config/Repository.php:119` `:281`；`src/Privacy/SiteBlocklist/Repository.php:242` | 已做 |
| 审查建议 3：子站 PUT `/settings` 带 `modules.site_blocklist` → `wpcy_settings_network_only_key` | `src/Rest/DocumentWriter.php:62`；`tests/Unit/Rest/PermissionsTest.php:405` | 已做 |
| 建议 1：去掉重复 reroute；ingest 未就绪走实际处置 + `enabled_when` | `src/Diagnostics/OutboundLayers.php:138` `:164`；`tests/Unit/Rest/ResidencyProtectedTest.php:94` | 已做 |
| 建议 2：`recovery_mode` 关噪声包 | `src/Privacy/DataResidency/DataResidencyModule.php:448` | 已做 |
| 建议 5：`note` 用 `mb_strlen(..., 'UTF-8')` | `src/Privacy/SiteBlocklist/Repository.php:233` | 已做 |
| 建议 7：`enabled=false` 未 `add_filter`；`LayerOrderTest` 读 priority | `tests/Unit/Privacy/SiteBlocklistTest.php:164`；`tests/Unit/Privacy/LayerOrderTest.php:122` | 已做 |
| 建议 8：验签通过的增量被合并 | `tests/Unit/Privacy/ProtectedHostsTest.php:74` | 已做 |
| rest-api 错误表 `wpcy_noise_block_blocked` | `docs/specs/rest-api.md:401` | 已做 |
| 建议 4：`filter_l0` 空钩子 | — | 未做：可选 |
| 建议 6：噪声 skip 出口到 GET `/residency/protected` | — | 未做：可选 |
| 3.x 选项审计 §5 ↔ `Mappers.php` 逐键 | — | 未做：本任务不改迁移映射 |

- [x] 每个状态一张截图：不适用（非 UI）。
- [x] 面向用户字符串：仍用词表「文派服务不可拦截」；无「遥测/隐私/上报」。`grep` 本 diff 无这些词作为用户文案。
- [x] 空/错误/降级：保护主机 400；子站 network-only 400；ingest 未就绪报实际 `allow`；恢复模式噪声关闭。
- [x] 测试：新增行为有测试；`composer check` exit 0（第三笔前提交）；CI run **34040789846** 进行中，**34040587763** 已绿。
- [x] `git diff --stat` 在允许路径内；`.grok-context/` 未入库。
- [x] 没做 / 做不到 / 有疑问：见第 3 节。报告写完再退出。
