独立审查相对 `2c992f5` 的全部改动；先读任务书与规范，再核对 diff，全程只读。按任务书与规范做独立只读审查；先设工作摘要，再拉提交范围、任务书和 diff。范围很大：97 个文件、覆盖多个 M1 任务。先读任务书与规范，再按任务拆开核对。对照任务书与 spec 逐项核对；先读全部相关任务书和被引用的规格。任务覆盖 M1-02b 到 M1-09b 整段。继续读 spec 与核心实现，并跑 `composer check`。`composer check` 还在跑。同时读核心实现和安全相关代码。继续核对 PHP 7.4 兼容、测试覆盖、Telemetry 与 PublicAssets，并等 `composer check`。`composer check` 已通过。继续核对 WP-CLI 是否污染 3.x、恢复页、测试与 PHP 7.4 细节。多站点写入路径和 REST 测试还要再核对几处高风险点。相对 `2c992f5..HEAD`（`ada57d3`）的改动覆盖 M1-02b～M1-09b 整段免费内核，不是单一任务；`composer check` 退出码 0，但多站点恢复/导出会把子站动作写进网络 option。

审查范围：`git diff --stat 2c992f5 HEAD` → 97 文件，+12192 / −122。对照 `docs/dev-plan/README.md` §0 与任务书 M1-02b、M1-04、M1-05、M1-05b、M1-06、M1-07、M1-09、M1-09b，以及 `docs/dev/coding-standards.md`、`docs/dev/security.md`、`docs/specs/rest-api.md`、`docs/specs/data-residency-ruleset.md`。只读，未改文件。

---

## A 正确性

结论：各任务书的源码交付物基本都在，单元断言与 3.x 镜像/遥测字段对得上；漏在多站点写路径、wp-env/Studio 验收未纳入 `composer check`，以及 M1-05b 仍残留对 `api.wordpress.org` 的驻留路径过滤死代码。

证据：

- 内核接线：`src/Core/Plugin.php:77-110` 注入 `Repository`，注册 WordPressOrg / PublicAssets / Avatar / Telemetry / DataResidency / Diagnostics / Rest；`null_config` 已删除。`tests/Unit/Core/PluginCreateTest.php:24-41` 断言 7 个 id（五模块 + diagnostics + rest）。`ada57d3` 补上 M1-06 漏挂的 `DiagnosticsModule`。
- M1-02b：`composer.json` 无 `setup.class.php`；`wp-china-yes.php:61` 在 v4 `return` 之后 `require_once`；`autoload-guard.php` 中 `WP_CHINA_YES_Setup` 计数为 0。`tests/Unit/Core/KernelSwitchTest.php:73-86` 用子进程 `get_included_files()` 断言 v4 不含 `framework/`。`tests/wordpress-smoke.sh:42-46` 只断言 3.x 仍加载 Setup，并写明 v4 常量无法在 `wp eval` 前定义。
- M1-04：`tests/Unit/Connectivity/WordPressOrg/MirrorUsableTest.php` 方法名覆盖任务书各节（200/octet-stream、404+JSON、200+HTML、WP_Error、探测路径、体积、分源、TTL）。`composer check` 中 legacy `test-wporg-mirror-fallback.php`「---- 29 passed, 0 failed ----」。
- M1-05 / weavatar：`src/Connectivity/Avatar/AvatarModule.php:117,154-155`；`tests/Unit/Connectivity/Avatar/AvatarModeTest.php:171-176`。
- M1-07 端点：`src/Rest/RestModule.php:128-196` 注册 GET/PUT settings、network-settings、GET diagnostics、POST diagnostics/run、POST recovery。`tests/Unit/Rest/PermissionsTest.php:63-147` 覆盖无 cap 403、坏 nonce 403、未知键丢弃 200、非法 action `wpcy_recovery_unknown_action`。
- M1-09b：`tests/fixtures/keys/` 含 `# TEST ONLY`；`RecordIgnoreTest::test_baseline_verifies` 断言 `verified()===true`，无 skip。
- 漏项：
  - 多站点 `Repository::set()` 把非 `connectivity`/`modules` 的键写入网络 option（`src/Config/Repository.php:117-124`），恢复页权限却是 `manage_options`（见 B/D）。
  - `ConfigCommand::export_document()` 在多站点把 `repository->all()`（网络+覆盖合并结果）标成 `wpcy_network_settings`（`src/Cli/ConfigCommand.php:107-109`）。
  - M1-06/M1-07 任务书要求的 wp-env/Studio 命令（`npx wp-env run cli wp wpcy status`、禁用脚本后 POST 恢复页）不在 `composer check` 里；`tests/Integration/CliTest.php:30-32` 与 `RecoveryPageTest.php:34-36` 无 WP 即 skip。仓内有 `docs/dev-plan/verification/m1-studio-2026-09-04.md`，本次未复跑 Studio。
  - `composer.lock` 不在 `2c992f5..HEAD` 的 `--stat` 里（M1-02b 交付物表含 lock）。

---

## B 运行时风险

结论：单站路径失败回源、模块抛错隔离、探测失败不记 `ok` 都成立；多站点恢复会抬到网络层，WordPress.org 改写超时 30s，驻留 `reroute` 嵌套请求未设 timeout。PHP 7.4 属性类型与 `?callable` 用法合法，未见 `mixed` 原生类型 / `fn` / `?->`。

证据：

- 模块隔离：`src/Core/ModuleRegistry.php:97-119` 对 `contexts()`/`enabled()`/`register()` 包 `Throwable`；`tests/Unit/Core/ModuleRegistryTest.php:103-109` 有 `ThrowingEnabledModule`。
- 镜像失败不改写：`src/Connectivity/WordPressOrg/WordPressOrgModule.php:169-171`；诊断 `Checker::hit()` 传输失败或非 2xx/3xx → `ok=false`（`src/Diagnostics/Checker.php:270-281`），`normalize()` 非法 result 丢弃且 ok 时 suggestion 强制 null（`316-329`）。
- **多站点恢复（阻断）**：`RecoveryActions` 写 `recovery_mode`（`src/Rest/RecoveryActions.php:63-77`）走 `Repository::set()`；该键不在 override 根下，多站点写入 `wpcy_network_settings`（`src/Config/Repository.php:121-124`）。子站 `manage_options` 用户一点恢复，全网 `enabled()` 看到 `recovery_mode=true`（各 ConditionalModule 均读该键）。`disable_rewrites` 的 connectivity 写入则进 `wpcy_site_overrides`，其它子站改写开关不变、却被网络级 recovery 关掉。
- **CLI 导出（阻断）**：`src/Cli/ConfigCommand.php:107-109` 多站点 `NETWORK_SETTINGS => $this->repository->all()`。再 import 会把某子站覆盖写进网络文档。
- 超时：改写后的 `wp_remote_request` 把 timeout 设为 30（`WordPressOrgModule.php:151`，与 3.x `Service/Super.php:165` 相同），高于 `docs/dev/security.md`「超时 ≤ 10s」。探测 3s（`Origins::PROBE_TIMEOUT`）、诊断 5s、遥测 POST 10s。`DataResidencyModule::reroute()`（`247-248`）`wp_remote_request($rewritten)` 无 timeout/sslverify；M1 因 `ingest_ready` 恒假走不到，M3 打开即暴露。
- WordPressOrg 只挂 `admin`+`cron`（`WordPressOrgModule.php:100-105`），与 3.x Super 一致；`wp plugin install` 不会走镜像。
- PHP 7.4：`composer.json` `"php": ">=7.4"`、`platform.php=7.4.33`；插件头 `Requires PHP: 7.4.0`。`?callable` 出现在 `Logger.php:62`、`PublicAssetsModule.php:68`（7.4 合法）。union 只写在注释里（如 `DataResidencyModule.php:45-49`）。
- `src/Cli/wp-cli.php` 在 `composer.json` `autoload.files` 里，3.x 路径只要是 WP-CLI 也会注册 `wp wpcy` 并挂 Site Health（`wp-cli.php:32-39`）。Web 请求因 `WP_CLI` 未定义早退，不 Fatal。

---

## C 规范

结论：`src/` 均 `declare(strict_types=1)`、PSR-4、`WenPai\ChinaYes\`；未改 `framework/` 业务、未读 `wp_china_yes` 作为 4.0 配置源；用户可见文案无「遥测」「匿名数据」。有两处 3.x 过滤器/标记名残留，头像说明直链 `weavatar.com` / `cravatar.com`。

证据：

- 禁区读 option：`wp-china-yes.php:28` 仍读 `wp_china_yes`（3.x 内存常量，v4 早退前执行）。v4 模块不读该键。`src/Core/Plugin.php:135-138` 激活明确不写该 option。
- 过滤器名：`src/Connectivity/MirrorHealth.php:101` `apply_filters( 'wp_china_yes_mirror_probe_targets', … )`；`MirrorProbe.php:137` `'_wp_china_yes' => true`（对齐 3.x 自身探测标记）。
- 文案：`src/` 无「遥测」「匿名数据」。Site Health 标签为「文派叶子」（`SiteHealth.php:89-90`）。内部 id `telemetry`、cron `wpcy_daily_telemetry`、字段 `telemetry_version` 仅代码/报文。
- 第三方域：头像资料链 `https://weavatar.com`、`https://cravatar.com`（`AvatarModule.php:204-210`），与 3.x `Service/Avatar.php` 一致，不是 `wpcy.com/go/…` 导流。镜像主机 `*.wenpai.net` / `*.admincdn.com` 是功能目标。
- WPCS：`phpcs.xml.dist` `testVersion=7.4-`；`composer check` 含 lint，phpstan「[OK] No errors」。
- 模块构造不挂钩：抽查 Rest/Diagnostics/Avatar/Telemetry 的 `register()` 才 `add_action`。

---

## D 安全

结论：REST 写路径有 cap + `X-WP-Nonce`，恢复页表单有 per-action nonce 与 `esc_html`/`esc_url`；凭据不进 settings GET / CLI export。阻断是子站 `manage_options` 可改网络级 `recovery_mode`。诊断与遥测远程请求 `sslverify=true`；WordPressOrg 改写与驻留 reroute 未显式设 `sslverify`（依赖 WP 默认 true）。

证据：

- Cap + nonce：`src/Rest/Permissions.php:52-59,90-96,109-116`。错误码 `wpcy_forbidden` / `wpcy_invalid_schema` / `wpcy_recovery_unknown_action`（`RestError.php:83-118`），形状含 `data.status` 与 `data.request_id`。
- 恢复页：`RecoveryPage.php:103-108` `current_user_can('manage_options')` + `check_admin_referer`；输出 `esc_html__` / `esc_attr` / `esc_url`（`126-139,166-167`）。
- PUT 经 Validator：`DocumentWriter.php:55-69`；未知键丢弃，类型错误 400。
- 凭据：`Repository::export()` 剥 `binding.credential`（`154-158`）；CLI 再剥 `credential/password/token/email/...`（`ConfigCommand.php:151-165`）。GET `/settings` 返回 `repository->all()`，不含 identity。
- **子站写网络 recovery**：见 B。REST `/recovery` 用 `manage_options_write`（`RestModule.php:188`），不是 `manage_network_options`。
- 远程：Telemetry `timeout=10, sslverify=true`（`TelemetryModule.php:137-145`）；Checker `timeout=5, sslverify=true`（`Checker.php:260-264`）。MirrorProbe / WordPressOrg 改写未写 `sslverify`。Ruleset 验签失败闭表（`Ruleset.php:255-256`）。B 档 log 只留 host/data_class/count/last_seen（`DataResidencyModule.php:195-218,339-357`）。
- 测试密钥入仓且标明 TEST ONLY（`tests/fixtures/keys/README.md:1-3`）。生产钥未出现。

---

## E 测试质量

结论：`composer check` 全绿；模块套件分进程避免 stub 碰撞是真隔离。集成 PHPUnit 在无 WP 时 skip，wp-env 脚本未进门禁，存在「单元绿、实机未跑」缺口。未见把实现抄进断言的假通过，但 `NoDoubleRewriteTest` 用未验签 Ruleset。

**`composer check` 摘要**（退出码 0，约 72s）：

| 步骤 | 结果 |
|------|------|
| phpcs | 无报错输出 |
| phpstan | `[OK] No errors`（42/42） |
| unit smoke | OK 1 test |
| core | OK 20 tests, 65 assertions |
| config | OK 38 tests, 173 assertions |
| connectivity | OK 91 tests, 199 assertions |
| telemetry | OK 3 tests, 265 assertions |
| privacy | OK 11 tests, 59 assertions |
| diagnostics | OK 13 tests, 123 assertions |
| cli | OK 6 tests, 25 assertions |
| rest | OK 18 tests, 77 assertions |
| legacy | `tests/run-tests.sh`：形态清理 14、mirror-health 38、standalone PASS、telemetry 字段 PASS、wporg 29；收束句 `All PHP syntax and standalone tests passed.` |

未覆盖 / 假绿：

- `tests/Integration/CliTest.php:30-32`、`RecoveryPageTest.php:34-36`：`composer test:unit` 的 phpunit.xml 不含 Integration 目录，这两份即使纳入也会 skip。
- `tests/integration-cli.sh` / `integration-recovery.sh` 不在 composer scripts。`integration-recovery.sh:10-18` 直接 `RecoveryActions::apply()`，不是「禁用脚本后 POST 表单」。
- `NoDoubleRewriteTest.php:55`：`new Ruleset(null, null, false)` 跳过验签。
- REST 权限测试用 `tests/Unit/Rest/wp-rest-stubs.php` 的 `WP_REST_Request`，不经 `register_rest_route` 调度；路由表本身无「打一次假服务器」测试。
- `phpunit.xml.dist:3-6` 写明必须分 suite 跑，裸 `phpunit` 会因全局 stub 碰撞失败。

---

## F 与 spec 的偏差

结论：REST 端点形状、恢复 exit 只清标志、B 档不记正文、A 档 ingest 假时不改 URL，与 `rest-api.md` / `data-residency-ruleset.md` 一致；下列为逐条偏差。

| # | spec / 任务书 | 实现 | 判定 |
|---|----------------|------|------|
| 1 | `rest-api.md` `/recovery` 与 `config-schema.md`：`recovery_mode` 属站点 `wpcy_settings` | 多站点写入 `wpcy_network_settings`（`Repository.php:121-124`） | 偏差，见阻断 |
| 2 | `security.md` 写操作：多站点网络设置用 `manage_network_options` | `/recovery` 与恢复页只用 `manage_options` | 偏差，见阻断 |
| 3 | `security.md` 远程 timeout ≤ 10s、显式 `sslverify=true` | WordPressOrg 改写 timeout=30；Probe/reroute 未写 sslverify | 偏差 |
| 4 | `data-residency-ruleset.md` §6 表仍列 `api.wordpress.org` 为 A 档；同页又写「由 Connectivity 负责，不进驻留主机表」 | `baseline.json` 无该主机；`Ruleset.php:47-58,161-163` 仍保留路径白名单 | 文档自相矛盾；代码按 M1-05b |
| 5 | M1-05b：同一 update-check URL 只被改写一次 | 基线已无该主机，双模块不会抢；`Ruleset::match` 仍 special-case 该主机 | 行为达标，残留死代码 |
| 6 | M1-07 / ADR-002：隐藏菜单 `parent=null` | `RecoveryPage.php:83-84` `add_submenu_page( '', … )` | 空串 vs null |
| 7 | `rest-api.md` 写请求需 `X-WP-Nonce` | 写回调有；GET 不校验 nonce（与 WP REST 惯例一致） | 可接受 |
| 8 | `/recovery` exit 不得自行恢复模块 | `RecoveryActions.php:75-77` 只 `recovery_mode=false` | 符合 |
| 9 | 诊断 schema：`suggestion` 在 ok 时必须 null | `Checker::normalize` 强制（`327-328`） | 符合 |
| 10 | M1-09 初始 A 档含 `api.wordpress.org` | M1-05b 已从基线删除，任务书后改 | 有意，文档表未改干净 |
| 11 | 商业链接 `https://wpcy.com/go/…` | 头像帮助直链 cravatar/weavatar | 功能域，不是导流 |
| 12 | M1-06 CLI 仅 cli 场景 | `wp-cli.php` 经 Composer files 在 3.x CLI 也注册 | 超出 v4 接线 |
| 13 | M1-02b 含 `composer.lock` | 本段 diff 无 lock | 漏交付 |

---

## G 三清单

### 阻断

1. **子站恢复写入网络 `recovery_mode`，权限却是 `manage_options`**  
   - 位置：`src/Config/Repository.php:117-124`，`src/Rest/RecoveryActions.php:63-77`，`src/Admin/RecoveryPage.php:103`，`src/Rest/RestModule.php:188`  
   - 现象：多站点下子站管理员 POST 恢复页或 `/recovery`，全网 ConditionalModule 因 `recovery_mode` 停改写。  
   - 修法：`recovery_mode` 保持站点级——多站点写入 `wpcy_settings`（或扩 `wpcy_site_overrides` 允许该键），不要走 `NETWORK_SETTINGS`；若产品坚持网络级恢复，则 REST/页面改 `manage_network_options`，并禁止子站 `manage_options` 触发。

2. **`wp wpcy config export` 多站点把合并后的有效配置标成网络 option**  
   - 位置：`src/Cli/ConfigCommand.php:107-109`  
   - 现象：export → import 会把某子站 `connectivity`/`modules` 覆盖写进网络文档。  
   - 修法：网络段用 `get_site_option( Schema::NETWORK_SETTINGS )`（经 Validator），覆盖段单独导出 `wpcy_site_overrides`；不要用 `Repository::all()`。

### 建议

1. WordPressOrg 改写 `timeout=30` 与 Probe/reroute 补上 `sslverify => true`、reroute 补 `timeout <= 10`（`WordPressOrgModule.php:151`，`DataResidencyModule.php:247-248`）。  
2. 恢复页 `handle_post` 成功后 `wp_safe_redirect` + `exit`，避免刷新重复 POST（`RecoveryPage.php:98-111`）。  
3. `add_submenu_page` 第一个参数改 `null`，与 ADR-002 / 任务书一致（`RecoveryPage.php:83`）。  
4. 把 `tests/integration-cli.sh`、`integration-recovery.sh` 挂进 CI 或 `composer check` 的可选项；恢复脚本改为真正 POST `?page=wpcy-recovery`，不要只调 `RecoveryActions::apply()`。  
5. `Ruleset` 删除已无主机的 `API_WORDPRESS_ORG_PATH_PREFIXES`（`Ruleset.php:47-58,161-163,349-356`）；spec §6 表删掉或标明「已迁 Connectivity」。  
6. `MirrorHealth` 过滤器改名 `wpcy_mirror_probe_targets`，避免 4.0 继续暴露 `wp_china_yes_*`（`MirrorHealth.php:101`）。  
7. WordPressOrg `contexts()` 是否加 `cli`（`wp plugin install` 走镜像）——3.x 也没有，要加需任务书。  
8. 补 `composer.lock`（若 `files` 变更导致 content-hash 过期）。

### 确认无误

- v4 早退不加载 `framework/`（`KernelSwitchTest` + `wp-china-yes.php:56-61`）。  
- 元数据/包源分离、失败回源、TTL、体积/类型校验与 3.9.3 两份独立测试 29+38 全过。  
- PublicAssets 白名单外不改写；节点 down 保留原 URL；`public.admincdn.com` 不在 `AssetMap::table()`。  
- Avatar 四档含 weavatar；`off` 不挂钩。  
- 兼容性报告常开、无 UI 开关；Woo 块无订单正文/邮箱/state。  
- 驻留：ingest 假时 Tracker URL 不改写；B 档无 query/body；C 档 `*` ignore。基线已验签，`kid=wpcy-ruleset-2026`。  
- REST 错误前缀 `wpcy_`，响应头 `X-WPCY-Request-Id`。exit 恢复只清标志。  
- `composer check`：lint / phpstan / 201 个 PHPUnit（分 suite）/ legacy 29 独立测试，退出码 0。
