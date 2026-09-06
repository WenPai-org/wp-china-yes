先按独立审查员只读核对：读任务书与规范，再对 `2a72b488` 到 HEAD 的 diff 逐项验证。任务对应 M-STATS-1。接着核对任务书、规范、diff 与验收命令。继续读规范、契约和核心实现，并拉完整 diff。继续读契约与实现，并核对禁区与验收项。接下来逐文件核对实现与测试，重点看计数器、事件、REST 和挂钩点。接着核对 REST、挂钩、Schema 和测试，并对照任务书逐条验收。继续核对挂钩实现、测试与 PHP 兼容性，并跑 `composer check`。对照任务书 `docs/dev-plan/tasks/M-STATS-1.md` 与 `git diff 2a72b488..HEAD`（40 文件、+3425/−43，6 笔提交）。`composer check` 已在本机跑过，exit 0。未改任何文件。

审查范围：`grok/m-stats-1` @ `cb00386`，相对 `2a72b488`。本仓插件头与 `composer.json` 已是 **PHP >= 8.0**（不是 7.4）。

---

## A 正确性

结论：八项交付物路径都在，REST 形状、`profile_confirmed_at`、测速名单、文档节基本按任务书落地；但 **`mirror_fallbacks` 生产路径永远是 0**，多站点 **`installed_at` 的迁移回退读错 option 作用域**。

证据：

- 交付物 1–8 均有对应文件：`src/Stats/Counters.php`、`StatsModule.php`、`Events.php`、`src/Rest/StatsController.php`、`EventsController.php`、`Plugin::activate()`（`src/Core/Plugin.php:257-284`）、`profile_confirmed_at`（`src/Config/Schema.php:304-308`、`Defaults.php:36`、`SettingsController.php:69-90`）、允许名单（`ClientProbeController.php:44-45`）、`docs/specs/config-schema.md` 与 `docs/dev/module-authoring.md:193-195`。
- `outbound_blocked` 只进 `Counters::NAMES`（`src/Stats/Counters.php:55`），无 HttpBlock 接线，符合任务书。
- **漏项：`mirror_fallbacks`。** 唯一 `do_action( 'wpcy_stats_increment', 'mirror_fallbacks', 1 )` 在 `MirrorHealth::remember()`（`src/Connectivity/MirrorHealth.php:167-172`）。`src/` 里没有任何生产调用方调用 `remember()`（只有 `tests/Unit/Connectivity/WordPressOrg/MirrorHealthHostTest.php`）。Checker 探测失败只写自己的 transient（`Checker.php:137`），不写 `wpcy_mirror_state_*`。运行时该计数恒为 0。
- **漏项：多站点 `installed_at` 第二级回退。** `Runner::persist_report()` 多站点写 `update_site_option`（`src/Migration/Runner.php:214-218`）；`StatsController::migrated_at()` 只 `get_option`（`src/Rest/StatsController.php:130-139`）。升级网络站第一次 `GET /stats` 会写成“现在”，而不是 `migrated_at`。
- WordPressOrg 计数挂在改写后 2xx（`WordPressOrgModule.php:235-249`），比契约「更新检查或安装包」更宽（插件信息 API 也会 +1）。
- `NetworkSettingsController::update_item()`（`src/Rest/NetworkSettingsController.php:68-73`）不剥 `profile_confirmed_at`、不盖戳；任务书只写了 `SettingsController`。

---

## B 运行时风险

结论：无必然 Fatal 的新路径；有几处边界会让数字偏大或事件丢失。PHP 下限是 8.0，不是 7.4。

证据：

- **PHP：** `composer.json:6` `"php": ">=8.0"`，`wp-china-yes.php` `Requires PHP: 8.0`，`coding-standards.md:9-12` 目标 8.1、底线 8.0。改动用 typed property / `declare(strict_types=1)`，未见 `match` / `readonly` / enum。按 7.4 审已不适用。
- **`flush()` 丢后续增量：** `Counters::flush()` / `Events::flush()` 第一次就把 `$flushed = true`（`Counters.php:206-222`）。同请求里 shutdown 之后再 `increment` 不会写盘。单元测试把这写成期望（`CountersTest.php:113-127` 第二次 increment 后仍断言 `writes === 1`）。
- **心跳：** `on_heartbeat_received` 每次已发出的 heartbeat +3（`HeartbeatModule.php:185-189`），不是“每分钟一次估算”。编辑器开着会按实际心跳次数累加；任务书允许下限估算，但数字会明显高于“每分钟 3”。
- **头像：** `get_cravatar_url` 同时挂在 `get_avatar_url` / `um_user_avatar_url_filter` / `bp_gravatar_url`（`AvatarModule.php:129-131,161-163`），同一 URL 多过滤器会多次 +1。
- **恢复退出顺序正确：** `recovery_exited` 在把 `recovery_mode` 置 false 之前记录（`RecoveryActions.php:77-79`），否则会被 `Events::record()` 的 recovery 过滤丢掉（`Events.php:149-151`）。
- **Checker 私有 Events：** 未注入时 `Checker::events()` 自建实例（`Checker.php:148-151`）且从不 `flush()`。内核 `Plugin::create()` 注入了同一 `$events`（`Plugin.php:218`），主路径安全；集成脚本自己 `flush()`（`tests/integration-stats.sh:25-28`）。
- **ULID：** `random_bytes(10)`（`Events.php:332`）失败会抛 Exception，未捕获。
- 多站点计数/事件用 `get_option`，与任务书一致；`installed_at` 激活时 `switch_to_blog`（`Plugin.php:258-263`）按站点写。

---

## C 规范

结论：PSR-4 / `strict_types` / 禁区路径干净；`Counters`/`Events`/`StatsController` 直接读写 option，与 coding-standards「经 Repository」冲突，但任务书明确要求。

证据：

- 新类均 `declare(strict_types=1)`，命名空间 `WenPai\ChinaYes\`，一类一文件。`composer check` 含 phpcs，exit 0。
- 禁区：diff 不含 `src/Admin/app/**`、`src/Admin/AdminModule.php`、`RecoveryPage.php`、`src/Privacy/**`、`docs/specs/rest-api.md`、`docs/design/**`、`framework/`。未读 `wp_china_yes` 作为 4.0 配置源。
- 用户可见事件文案在 `Events::render()`（`Events.php:368-509`），中文，无「遥测」「匿名数据」。
- 商业 go 链接未新增。`admincdn.com` / `cravatar.com` / `wenpai.net` 出现在分组表与测速名单，来自 rest-api 契约，不是广告外链。
- `module-authoring.md` 新节 4 行（任务 ≤ 15 行）。
- 提交前缀 `feat(stats):` / `feat(rest):` / `feat(config):` / `fix(diagnostics):` / `test(stats):`，6 笔 ≥ 4。
- 业务模块直写 option：`Counters.php:217-221`、`Events.php:299`、`StatsController.php:148-150`。任务书授权，仍违反 `coding-standards.md:74` 字面规则。
- diff 3425 行（含测试），任务书「预计 ≤ 900 不含测试」——过程超标，不是功能错。

---

## D 安全

结论：`GET /stats` `/events` 权限与诊断只读相同；写路径仍走既有 cap+nonce。测速名单只扩两个主机。有一处只读字段可被网络 PUT 写入。

证据：

- 注册：`permission_callback => Permissions::manage_options_read`（`RestModule.php:337,354`），与 `/diagnostics` 相同（`RestModule.php:195`）。`manage_options_read` 查 `current_user_can( 'manage_options' )`（`Permissions.php:34-40`）。GET 不验 nonce，与现有只读诊断一致。
- `days` 非法 → `wpcy_invalid_schema` 400（`StatsController.php:63-66`）。`per_page` 封顶 50（`EventsController.php:84-98`）。
- `profile_confirmed_at` 在 `SettingsController` 从 body `unset`（`SettingsController.php:69-70`），服务端盖戳。`PUT /network-settings` 不 unset（`NetworkSettingsController.php:68-73`），只读约定可被网络管理员绕过。
- `/events` 的 `latest()` 只返回 id/at/type/tone/title/detail（`Events.php:194-201`），不把内部 `group`/`host` 送出。`/stats` 只有日期与整数。
- Client-probe 仍是名单校验、服务端不代发（既有，本任务只加 `googlefonts.admincdn.com`、`cn.cravatar.com`）。
- 无新凭据、无新远程请求（Checker 探测是既有路径）。WordPressOrg 改写请求仍 `timeout ≤ 10`、`sslverify true`（`WordPressOrgModule.php:169-176`）。

---

## E 测试质量

结论：任务书点名的 Stats 单测都有且能过；挂钩层（除 Checker 事件）基本没测；集成脚本对隐私的 URL 检查是空操作。`composer check` 绿。

**`composer check`（本机，exit 0，约 331s）：**

```
phpstan: [OK] No errors
stats suite: OK (42 tests, 137 assertions)
全 16 套 PHPUnit：smoke 1 / core 23 / config 85 / connectivity 110 / telemetry 3 /
privacy 16 / diagnostics 13 / stats 42 / cli 8 / rest 53 / integrations 20 /
migration 45 / site-binding 12 / apps 68 / entitlements 11 / admin 19
```

phpcs 无输出、整体 exit 0。未跑 CI，无 run id。

证据：

- 有：分桶 / 31 天 / 未知名 / 恢复不计 / flush 一次（`CountersTest.php`）；环形 50、ULID 26、模板表驱动（`EventsTest.php:93-221`）；days 0/1/30/31/`x`（`StatsControllerTest.php:72-79`）；`installed_at` 三级（同文件 103-129）；`per_page` 与 `type`（`EventsControllerTest.php`）；Checker 三次 run（`CheckerEventsTest.php:58-81`）；PUT profile 盖戳（`SettingsProfileConfirmedTest.php`）；新主机通过、其它拒（`ClientProbeTest.php:200-244`）。
- **假通过：** `CountersTest::test_flush_writes_once` 在 flush 后再 increment，断言仍只写一次——把数据丢失当成“只写一次”通过。
- **假通过：** `tests/integration-stats.sh:64-67` 扫到 `http://`/`https://`/`?` 后 `pass`，从不失败。
- **未覆盖：** `HeartbeatModule` +3/+1、`PublicAssetsModule::count_once`、`AvatarModule::count_rewrite`、`DashboardFeedsModule::count_block`、`WordPressOrgModule::count_mirror_response`、`MirrorHealth::remember` 生产未调用。没有多站点 `installed_at` 测试。
- 集成未跑 `wp cron event run wpcy_diagnostics_check`，而是直接 `new Checker`（`integration-stats.sh:24-28`）；任务书允许“或等价”。脚本会清 `recovery_mode` 与 diagnostics transient（`cb00386`），避免被前序脚本污染。

---

## F 与 spec 的偏差

结论：`/stats` `/events` 字段与事件模板跟 `docs/specs/rest-api.md` 正文一致；偏差在挂钩语义、隐私附注、多站点回退。

| 规格 | 实现 | 状态 |
|---|---|---|
| `/stats` days 1–30，非法 400；series 长度=days，缺桶 0，升序；10 个计数器 | `StatsController.php:110-124`，`Counters::snapshot()` | 符合 |
| `installed_at`：option → `migrated_at` → 现在并写入 | 单站符合；多站点 `migrated_at` 在 site_option，读 `get_option` | **偏差** |
| 恢复模式不计；shutdown 一次写；autoload=false | `Counters.php:136-137,217-221` | 符合 |
| `outbound_blocked` 本任务不接 HttpBlock | 只登记名 | 符合（任务授权） |
| `mirror_fallbacks`：镜像不可达回上游一次 | 挂在无人调用的 `remember()` | **偏差** |
| `mirror_downloads`：更新检查或安装包 2xx | 所有改写后 2xx（含 API 元数据） | **偏差** |
| `heartbeat_saved`：15s→60s 每分钟 +3；仪表盘关闭每分钟 +1 | 每次 `heartbeat_received` +3；每次 `load-index.php` +1 | **偏宽**（任务书写法接近实现） |
| `/events` 模板 title/detail/tone | `Events::render()` + `EventsTest` 表驱动 | 符合 |
| 隐私：`{host}` 只出现在 `route_fallback` detail | 模板表用 `{provider}` 无 `{host}`；实现跟模板表，响应也不含 host | 隐私节 vs 模板表互相矛盾，实现跟模板 |
| `profile_confirmed_at` 只读，PUT 含 `profile` 即盖戳 | `SettingsController` 符合；`NetworkSettingsController` 不盖戳、不忽略客户端值 | **部分偏差** |
| client-probe 加两主机 | `ClientProbeController.php:44-45` | 符合 |
| 不改 rest-api 契约正文 | diff 无 `docs/specs/rest-api.md` | 符合 |
| RouteGroups 一张表 + `group_for_target` + `worst` | `src/Diagnostics/RouteGroups.php` | 符合 |
| `{route}` 用人读组名 | `Events.php:256` 用 `$group['label']` | 符合 |

---

## G 清单

### 阻断

1. **`mirror_fallbacks` 生产永不增加**  
   `src/Connectivity/MirrorHealth.php:167-172`。`remember()` 无生产调用。  
   修法：在真正判定镜像不健康并回上游处 `do_action`（候选：`WordPressOrgModule` 改写失败走原上游；或 `Checker::probe_target` 得到 `fallback` 后调 `MirrorHealth::remember($host, 'down', $ttl)`）。加一条断言 `remember` 或改写失败会让 `wpcy_stats` 出现该键。

2. **多站点 `installed_at` 读不到迁移时间**  
   `src/Rest/StatsController.php:130-139` vs `src/Migration/Runner.php:214-218`。  
   修法：与 `Runner::stored_report()` 相同，多站点 `get_site_option( Runner::REPORT_OPTION )`。补单测：只存在 site_option 时 `GET /stats` 仍返回该 `migrated_at`。

### 建议

1. `WordPressOrgModule.php:245`：`mirror_downloads` / `mirror_bytes_saved` 限制在 version-check 与包下载，不要对所有 2xx 改写计数。  
2. `HeartbeatModule.php:185-189`：按会话/分钟节流，避免每次 `heartbeat_received` +3。  
3. `AvatarModule.php:129-131`：只在 `get_avatar_url` 上计数。  
4. `NetworkSettingsController.php:68-73`：同样 `unset(profile_confirmed_at)`；网络 PUT 含 `profile` 时盖戳。  
5. `CountersTest.php:113-127`：不要把 flush 后丢失增量当成通过；集成脚本 `integration-stats.sh:64-67` 发现 URL/`?` 应失败。  
6. 给连通性 `do_action` 各补一条单元测试。  
7. `Events::generate_ulid()` 捕获 `random_bytes` 失败。

### 确认无误

- `GET /stats`、`GET /events` 注册、days 边界、series 补 0、权限与诊断只读相同。  
- 事件 10 种 type 的中文模板与 rest-api 表一致（`EventsTest` 表驱动）。  
- Checker `ok→fallback→ok` 得到 `first_check` / `route_fallback` / `route_recovered`，`{minutes}=12`。  
- `profile_confirmed_at` 默认 null；站点 PUT 含 `profile` 盖戳；body 带该键被忽略。  
- client-probe 新两主机通过，`en.cravatar.com` 仍 400。  
- 恢复模式：计数跳过，事件只留 `recovery_*`；退出事件在清标志前写入。  
- `outbound_blocked` 未接 HttpBlock。  
- 未改 Admin UI、Privacy、rest-api 契约、design。  
- `composer check` 本机绿（phpstan + 16 套 PHPUnit）。CI run 未在本审查中验证。
