按独立审查员做只读审查：先读任务书、规范与相对 `fbda1184` 的全部改动，再逐项验证。用户要求按指定中文分节做独立只读审查，我自己核对照任务书、规范与 diff，不走通用 `/review` 编排。改动对应 **M-SCOPE-1**。接下来读任务书、规范与相关 spec。对照任务书后读 spec 与关键实现，并核对仓内 PHP 版本声明。仓内声明是 PHP 8.0，不是 7.4。继续读 spec 与实现，逐项核对。开始逐文件核对实现与任务书、spec。继续读连通性、REST、迁移与诊断实现。接下来跑 `composer check`，并抽查心跳钩子时序与 entitlement 闸。相对 `fbda1184` 的 7 笔提交对应任务 **M-SCOPE-1**（分支 `grok/m-scope-1`）。本仓声明是 **PHP 8.0**（`wp-china-yes.php:13`、`composer.json` `>=8.0`、`phpcs.xml.dist:39` `testVersion` `8.0-`），不是 7.4。

`composer check` 已跑：exit 0。只读，未改任何文件。

---

## A 正确性

**结论：交付物路径基本齐，D2/D3/REST/驻留闸能对上任务书；禁区里的连通性配额未拆掉，验证矩阵 D3 行仍是「由 M-SCOPE-1 补跑」，e2e/CI 本 diff 无证据。**

对照 `docs/dev-plan/tasks/M-SCOPE-1.md` 交付表：

| 规格 | 实现 | 状态 |
|------|------|------|
| D2 `wordpress_org` domestic=`auto` | `src/Config/Profile.php:102` | 已做 |
| D2 heartbeat crossborder=`on` | `src/Config/Profile.php:130` | 已做 |
| D2 dashboard_feeds mixed=`block` | `src/Config/Profile.php:150` | 已做 |
| D3 admin token → `admin_assets=on` | `src/Migration/Mappers.php:323-325,217-218` | 已做 |
| GET `/profile/suggest` | `src/Rest/ProfileSuggestController.php:53`；路由 `src/Rest/RestModule.php:202-219` | 已做 |
| POST `/diagnostics/client-probe` | `src/Rest/ClientProbeController.php:87` | 已做 |
| 方案 A crossborder 不改道 | `src/Privacy/DataResidency/DataResidencyModule.php:183-186` | 已做 |
| `Schema::VERSION === 2`；identity/backup 仍为 1 | `src/Config/Schema.php:29-31`；`php -r` 打印 `2` | 已做 |
| 不改 `src/Admin/app/`、`src/Core/Scope.php`、决定文件 | `git diff --stat` 无这些路径 | 已做 |
| `docs/dev-plan/verification/m4-upgrade-matrix-2026-09-06.md` D3 补跑 | 该文件:344-346 三行状态仍是「由 M-SCOPE-1 补跑」 | **未做**（任务书允许未跑 Studio 时保留标记，diff 里也没改） |
| 不为连通性建配额 / 不把「配额用尽降级」接到公共库 | `PublicAssetsModule.php:176-177,240-247` 仍走 `wpcy_entitlement_allows` + `'admincdn'` | **未做** |

`git diff --stat fbda1184 HEAD`：51 files，`3640 insertions, 167 deletions`（任务书「预计 ≤ 1500 行」）。

漏项：验证矩阵未改；`npm run build` / `lint:js` / CI run id / `git push` 本审查未跑（任务书验收 8、13）。`frontend` token 进 ignored 只有 bootstrapcdn 的手写用例，没有独立 `frontend` fixture 断言。

---

## B 运行时风险

**结论：PHP 8.0 类型与 ABSPATH 守卫无 Fatal 面；编辑器 Heartbeat 60s 依赖 `admin_enqueue_scripts` 先写入 `$hook_suffix`，与 WP 在 `wp_default_scripts` 里 localize 的时机打架；公共库改写在未绑定站可能被 entitlement 整段关掉。**

1. **编辑器 interval=60 可能从不生效**  
   `HeartbeatModule` 只在 `admin_enqueue_scripts` 里记下 `$hook_suffix`（`src/Connectivity/Heartbeat/HeartbeatModule.php:121-122`），`heartbeat_settings` 再用它（:141-142）。WP 核心在 `wp_default_scripts()` 里对 `heartbeat` 做 `localize`（`vendor/php-stubs/wordpress-stubs/wordpress-stubs.php:136008-136010` 注释：注册时 localize），该过程会跑 `heartbeat_settings`，往往早于本模块的 enqueue 回调，此时 `$hook_suffix === ''`。  
   单测是先 `on_admin_enqueue_scripts('post.php')` 再 filter（`tests/Unit/Connectivity/Heartbeat/HeartbeatModuleTest.php:52-54`），盖不住这条路径。  
   仪表盘 `wp_deregister_script('heartbeat')` 挂在 enqueue 上，这条相对稳。

2. **公共库改写 × entitlement**  
   `Plugin::create()` 仍 `new PublicAssetsModule( $config, $map, $health )`（`src/Core/Plugin.php:198`），`rewrite()` 默认 `apply_filters( 'wpcy_entitlement_allows', true, 'admincdn' )`（`PublicAssetsModule.php:245-247`）。`EntitlementsModule::RESTRICTED_SERVICES` 含 `'admincdn'`（`src/Services/Entitlements/EntitlementsModule.php:62-67`），`shouldUseUpstream` 在状态不是 `active` 时为 true（含未绑定、无该条）（`Degrade.php:78-79`）。前台/后台都会 `register()` 该 filter（`contexts()` = 全部场景）。未绑定站公共库改写会被关掉。单测不挂 entitlements 模块，默认 true，看不出来。

3. **多站点**  
   `site_overrides` 允许 `profile` / `admin_assets`（`Schema.php:176-184`，`Repository.php:519-521`）。覆盖 `profile` 不隐式重置连通性，符合 spec。PUT `/settings` 仍写入 `wpcy_settings`（`SettingsController.php:69-71`），多站点 `Repository::all()` 读的是 network + overrides（`Repository.php:136-146`）——这是原有问题，本次 `DocumentWriter` 加了 profile 切换后，多站点 PUT 切换场景写进一张不会被读的 option。

4. **边界**  
   - Heartbeat / DashboardFeeds：`recovery_mode` → `enabled()===false`（`HeartbeatModule.php:92-94`，`DashboardFeedsModule.php:86-88`）。  
   - DashboardFeeds 只拦 `host===api.wordpress.org` 且 path 以 `/events/` 开头（`DashboardFeedsModule.php:158-166`）；`https://api.stripe.com/` 不拦（测试 :75-78）。  
   - Cron 先于 `is_admin()`（`Scope.php:48-52`）。  
   - `client_probe` 不代发 URL。  
   - PHP 7.4：仓已升 8.0；新代码大量 typed properties（`private Config $config`），本来就不能在 7.4 跑。注释里仍写 “Callable is not a valid PHP 7.4 property type”（`Repository.php:28`，`ProfileSuggest.php:45`）是过期口径。

---

## C 规范

**结论：PSR-4 / `strict_types` / WPCS 门禁绿；未碰 `framework/`、未改 `Core\Scope`、未改 React；用户可见串没有「遥测」；连通性配额钩子与禁区冲突。**

- 新类一类一文件，命名空间 `WenPai\ChinaYes\`，文件头 `declare(strict_types=1);` + `ABSPATH`。  
- `phpcs.xml.dist` `testVersion` `8.0-`；`composer check` 含 lint，exit 0。  
- 禁区「不读/不写 `wp_china_yes`」：迁移仍**读** 3.x option（`Mappers.php:85`），不写——与既有 Migration 合同一致，本次未把 4.0 写回该键。  
- `src/Core/Scope.php` diff 为空。  
- 商业链接：本次未新增 `weixiaoduo.com` 一类域名。`ProfileSuggest::GEO_URL = 'https://api.wenpai.net/v1/geo'`（`ProfileSuggest.php:27`）是任务允许的 wenpai-net 占位。Avatar 资料链 `weavatar.com` / `cravatar.com`（`AvatarModule.php:205-210`）是原样，不是本 diff 新写。  
- 用户可见：迁移报告 `__( '后台加速：已保留设置，4.1 起生效', 'wp-china-yes' )`（`Report.php:129`）。REST 错码走已有中文（`RestError.php:87,101`）。Telemetry 只在 payload，界面无「遥测」。  
- 违规：`PublicAssetsModule.php:153` 注释仍写 “Exhausted quota”；`entitlement_allows()` 仍在。任务书禁区与商业前提（「三层作废」「插件内不出现配额用尽降级」）未清。

---

## D 安全

**结论：新 REST 有 cap + 写 nonce；client-probe 无服务端代发；profile/suggest 不回 IP。client_probe_url 把任意 https 主机加进允许名单是规格写死的，GET 读 option 未再投影。**

- GET `/profile/suggest`、GET client-probe：`Permissions::manage_options_read`（`RestModule.php:192,208`）→ `current_user_can( 'manage_options' )`（`Permissions.php:36`）。  
- POST client-probe：`manage_options_write`（`RestModule.php:197`）= cap + `X-WP-Nonce` / `wp_rest`（`Permissions.php:52-58,109-115`）。  
- POST 非法主机整单 `wpcy_invalid_schema` 400，不写 option（`ClientProbeController.php:101-104`；测试 :101-127）。  
- 存储键 `wpcy_diagnostics_client_probe`，`autoload=no`（:32,113-114）。不进 `wpcy_settings`。  
- `https:` 前缀用 `0 !== stripos( $url, 'https:' )` 拒绝非 HTTPS（:173）。不 `wp_remote_*` 用户 URL。  
- geo：`wp_remote_get` timeout 5、`sslverify` true、固定 `GEO_URL`（`ProfileSuggest.php:159-164`）。失败当 null，不把 IP 放进数组（:82-88）。  
- `diagnostics.client_probe_url` 非空 https 时主机并入允许名单（`ClientProbeController.php:148-156`）——与 `docs/specs/rest-api.md:215` 一致；能被 PUT `/settings` 写入的人已经是管理员。  
- GET `stored()` 原样返回 option 里的 `probes`（:125-140），缺字段投影。有 `manage_options` 才能读。  
- 驻留日志 REST 投影四字段（`ResidencyController` diff）：`host/data_class/count/last_seen`。  
- 凭据：本次未改加密路径。Telemetry `collect()` 只加 `profile` 字符串（`Report.php:95,138-146`）。

---

## E 测试质量

**结论：`composer check` 全绿；关键路径有测试，但 Heartbeat 时序、client_probe_url 扩名单、`scope=frontend` 打在 admin、entitlement 与 PublicAssets 联路都没盖住。**

`composer check`（本机 PHP 8.4.7，约 144s，**exit 0**）摘要：

```
[OK] No errors                    # PHPStan 83/83
OK (1 test)                       # smoke
OK (23 tests, 68 assertions)      # core
OK (82 tests, 419 assertions)     # config
OK (112 tests, 239 assertions)    # connectivity
OK (3 tests, 270 assertions)      # telemetry
OK (16 tests, 73 assertions)      # privacy
OK (13 tests, 123 assertions)     # diagnostics
OK (8 tests, 42 assertions)       # cli
OK (45 tests, 240 assertions)     # rest
OK (20 tests, 46 assertions)      # integrations
OK (43 tests, 697 assertions)     # migration
OK (12 tests, 131 assertions)     # site-binding
OK (68 tests, 210 assertions)     # apps
OK (11 tests, 77 assertions)      # entitlements
OK (19 tests, 70 assertions)      # admin
```

假通过 / 未覆盖：

- Heartbeat：`test_off_does_not_enable` 不调用 `register()`，钩子空来自 `setUp`（`HeartbeatModuleTest.php:64-70`）。interval 用例先手动 `on_admin_enqueue_scripts`，不模拟 `wp_default_scripts` 先行 localize。  
- Client-probe：无 `diagnostics.client_probe_url` 扩名单、无 >8 条、无 `http://`、无 `latency_ms` 浮点。权限测试调的是通用 `manage_options_read`，不是路由绑错会红。  
- PublicAssets：有 `scope=admin` + frontend 请求（`WhitelistTest.php:283-298`），**没有** `scope=frontend` + admin 请求。仍保留 `test_keeps_origin_when_entitlement_exhausted`（:179-199），和任务书禁区对着干。  
- ProfileGate：缺 config 只测 `reroute_enabled()`（`ProfileGateTest.php:93-101`），没走 `filter_pre_http_request`。未 mock geo，符合任务书。  
- Schema v2：幂等只对已是 v2 的文档 `assertSame`（`SchemaVersion2Test.php:107-126`），没有「v1 升两次」字节级幂等。  
- D4：四类 + HK + `zh_CN` + geo 失败 + 无 `ip` 键（`ProfileSuggestTest.php`）扎实。

未跑：`npm run build`、`npm run lint:js`、e2e/CI。

---

## F 与 spec 的偏差

**结论：D2 矩阵与 REST 形状按 spec 抄了；偏差集中在配额闸、geo 路径占位、Heartbeat 实现细节、验证矩阵文档。**

| 规格 | 偏差 |
|------|------|
| 任务书 / config-schema 商业前提：连通性无配额、不把「配额用尽降级」接到公共库 | `PublicAssetsModule` 仍 filter `'admincdn'`；`EntitlementsModule::RESTRICTED_SERVICES` 仍含 `admincdn` |
| `docs/specs/rest-api.md:115` geo 路径「待 wenpai-net」 | 实现写死 `https://api.wenpai.net/v1/geo`（`ProfileSuggest.php:27`）。任务书允许占位，路径是猜的 |
| 任务书 Heartbeat：`heartbeat_settings` 在编辑器屏 `interval=60` | 实现绑 `$this->hook_suffix`，不读 `$GLOBALS['pagenow']` / `get_current_screen()` |
| config-schema `upgrade_1_to_2` 幂等 | 函数对 `schema_version>=2` 原样返回；v1 文档先升再升依赖 Validator 填默认，单测没盖第二次升级 |
| rest-api `client_probe_url` 为非空 HTTPS 时主机加入允许名单 | 实现有；**测试无** |
| 任务书交付：改 `m4-upgrade-matrix` D3 状态为命令输出 | 文件:344-346 仍「由 M-SCOPE-1 补跑」 |
| rest-api PUT v1 `public_assets` 数组 / 字符串 `avatar` → 400 | 有（`PermissionsTest.php:138-157`）。空数组 `[]` 在 Validator 里不当 list（`Validator.php:367-370`），可能被当成空对象再填默认，与「数组即 400」不完全同一条 |
| data-residency §9：domestic + `ingest_ready` 才改道 | `reroute_enabled` 先看 profile（`DataResidencyModule.php:184`）；`Plugin.php:207` 生产仍 `ingest_ready=false`（M1-09 冻结，符合「模块在、默认不启用」） |
| D3：`frontend` / `bootstrapcdn` 仍 ignored | `bootstrapcdn` 有用例（`FixturesTest.php:343-344`）；`frontend` token 无对等断言 |
| 上报字段：`telemetry_version` 仍 `2.1`，只加 `profile` | 符合（`Report.php:29,95,105`） |

决定文件未改。D2 九行三列与 `Profile::matrix()` 一致（`ProfileTest.php:70-103`）。

---

## G 清单

### 阻断

1. **公共库改写仍走连通性配额闸（任务书禁区）**  
   - 位置：`src/Connectivity/PublicAssets/PublicAssetsModule.php:176-177,240-247`；`src/Services/Entitlements/EntitlementsModule.php:62-67`  
   - 问题：未绑定 / `admincdn` 非 `active` 时 `rewrite()` 直接回原 URL；与 M-SCOPE-1「不为连通性建配额」「不把配额用尽降级接到公共库」冲突。  
   - 修法：`PublicAssetsModule` 删掉 `entitlement_allows` 参数与 `rewrite()` 里那次判断；`RESTRICTED_SERVICES` 去掉 `'admincdn'`。Windfonts / 小工具配额不动。删掉 `WhitelistTest::test_keeps_origin_when_entitlement_exhausted`。

2. **编辑器 Heartbeat 60s 依赖错误的屏判断**  
   - 位置：`src/Connectivity/Heartbeat/HeartbeatModule.php:38,121-142`  
   - 问题：`heartbeat_settings` 常在 `wp_default_scripts` localize 时触发，此时 `$this->hook_suffix` 仍是 `''`，`interval=60` 不会写上。  
   - 修法：在 `filter_heartbeat_settings` 读 `$GLOBALS['pagenow']`（或 `get_current_screen()->base`）是否为 `post.php` / `post-new.php`，不要依赖 enqueue 回调的成员变量。单测改为不先调 `on_admin_enqueue_scripts` 也能把 interval 设成 60。

### 建议

- `HeartbeatModule` / `DashboardFeedsModule` 的 `off`/`allow` 单测应断言「即使误调 `register()` 也不该……」或接受「只测 `enabled()`」并在测试名写明，避免看起来像钩子断言。  
- 补：`client_probe_url` 扩名单；`scope=frontend` 在 admin 请求不改写；迁移 token `frontend` 进 ignored；v1 文档 `upgrade_1_to_2` 两次。  
- GET client-probe 读存储时投影为 `{target,result,latency_ms}`，丢掉多余键。  
- `locale` / `timezone` 查询参数做长度上限再回 `signals`。  
- 多站点 PUT `/settings` 应写 overrides/network 而不是无人读的 `wpcy_settings`（原有债，本次 profile 切换会让它更明显）。  
- 过期注释「PHP 7.4 property type」与仓内 8.0 声明不一致，顺手改掉。  
- 按任务书补跑或在报告写明：`m4-upgrade-matrix` D3、`npm run build`、CI。

### 确认无误

- `Schema::VERSION === 2`，identity/backup 仍为 1；`Defaults::settings()` 与 domestic 列一致。  
- D2 九行 × 三列与 `Profile::apply_to` 不碰 `notice_control` / announcements / diagnostics / recovery / data_residency / apps。  
- D3：`admin` 不再进 `ignored`；`admin_assets=on`；报告中文「后台加速：已保留设置，4.1 起生效」；新 fixture `tests/fixtures/legacy-options/single-3.9-08-admincdn-files-admin.json`。  
- `Connectivity\Scope::current()` 顺序：CLI → cron → `is_admin` → REST+nonce+`/wp-admin` referer → frontend。  
- 4.0 无任何模块因 `admin_assets=on` 改写 URL。  
- A 档 `profile !== domestic` 不改道；B 档 crossborder 仍 record；不查 geo。  
- `/profile/suggest` 不写 profile、响应无 `ip`；PUT 换 profile 先 `apply_defaults` 再合并。  
- client-probe 覆盖写、非法主机 400 不写、空存储 `{checked_at:null,probes:[]}`。  
- Telemetry `profile` + `telemetry_version` `'2.1'`。  
- 未改 `src/Admin/app/`、`src/Core/Scope.php`、决定文件、`framework/`。  
- `composer check` exit 0。
