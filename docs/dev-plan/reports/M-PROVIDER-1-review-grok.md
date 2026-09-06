先按独立审查流程读任务书、规范和相对 `94f66afc` 的完整 diff，不改任何文件。这是 M-PROVIDER-1。接着读任务书、规范和 specs，并拉完整 diff。继续读实现、REST 规格、既有绑定/加密路径，并拉完整 diff。继续核对测试、CI、绑定加密实现，并跑 `composer check`。核对 `composer check` 是否包含 phpcs，并确认 `products()` 在 `unreachable` 后的第二跳行为。相对 `94f66afc` 的改动对应任务 **M-PROVIDER-1**（供应商层后端）。交付物基本齐，但 `products()` 把 `invalid`/`unreachable` 当成未连接，P4 的 72h 缓存只在当次失败生效。`composer check` 退出码 0。本审查只读，未改文件。

审查范围：`git diff 94f66afc7f7f56d41c775c7fb52b1f17e024f019 HEAD`（29 files, +3649/−3），分支 `grok/m-provider-1` @ `2acac9d`。对照 `docs/dev-plan/tasks/M-PROVIDER-1.md`、`docs/specs/providers.md`、`docs/dev/coding-standards.md`、`docs/dev/security.md`。

---

## A 正确性

九项交付物都落地，五端点与闸（未 bound / coming_soon / 成功落库 / invalid 与 unreachable 不落密钥）与任务书一致；**漏了 `GET /products` 在 `invalid`/`unreachable` 下沿用缓存**，任务书要求的 schema 400 与 72h 缓存测试也没有。

交付对照：

| # | 任务书 | 实现 | 状态 |
|---|---|---|---|
| 1 | `Presets.php` 两 ID | `src/Providers/Presets.php:43-84` | 有 |
| 2 | `Store` + `SecretBox` purpose `provider:{id}` | `Store.php`、`SecretBox.php:101-103` | 有；instance 明文见 F |
| 3 | `WcAmClient` 五 action、HTTPS、10s、sslverify | `WcAmClient.php:40-46,190-199` | 有；密钥走 GET query（PHPDoc 写了 2026-09-06 探测） |
| 4 | `ProviderService` 闸 + 精确匹配 | `ProviderService.php:113-195,581-597` | 闸正确；`products()` 见阻断 |
| 5 | `UpdateBridge` id `providers` | `Plugin.php:223`、`UpdateBridge.php:73-109` | 有；恢复模式 `register()` 不挂钩 |
| 6 | 五 REST + 一行注册 | `RestModule.php:131`、`ProvidersController.php:57-106` | 有 |
| 7 | Logger 脱敏 | `Logger.php:80,126-128,195-198` | 有 |
| 8 | `uninstall.php` | `uninstall.php:24-51` | 有；多站点循环子站 |
| 9 | module-authoring 一节 + 词表五行 | `module-authoring.md:331-338`、`admin-ui-spec.md:226-231` | 有；`providers.md` 未改 |

证据：

- 未 bound → 403：`ProviderService.php:118-123`；测试 `ProviderServiceTest.php:48-55`。
- coming_soon → 400：`ProviderService.php:125-130`。
- invalid 不落密钥：`ProviderService.php:157-165`；`ProviderServiceTest.php:99-112`。
- unreachable 不落密钥、503：`ProviderService.php:143-154`；`ProviderServiceTest.php:117-127`。
- 精确匹配：`ProviderService.php:590-597`；`ProviderServiceTest.php:132-158`。
- **漏项**：`products()` 要求 `connection === 'connected'`，否则直接空数组：

```293:297:src/Providers/ProviderService.php
		if ( 'connected' !== $item['connection'] || ! is_string( $key ) || '' === $key ) {
			return array(
				'products' => array(),
			);
		}
```

当次 `product_list` 失败会先写成 `unreachable` 再吐缓存（`ProviderService.php:339-349`）；**下一次** GET（以及 `UpdateBridge::is_connected()`，`ProviderService.php:409-411`）视为未连接。P4 / 规格 §5「沿用缓存 / 已购项只读」不成立。无对应测试。

- 任务书验收「邮箱格式、密钥空 → 400 `wpcy_invalid_schema`」代码在 `ProviderService.php:134-136`，**无测试**。
- 源码-only 约 +2302 行，任务书「≤ 1200 行（不含测试）」超了。
- 本审查未跑 `tests/integration-providers.sh`（要 wp-env/Studio）。脚本本身覆盖 GET 形状、未绑定 403、stub bound 200、products、DELETE。

---

## B 运行时风险

无新的必炸 Fatal；多站点按子站 option；PHP 下限是 **8.0 不是 7.4**。真正的运行时问题是 unreachable 之后更新钩与产品列表一起停。

- **仓声明**：`composer.json:6` `"php": ">=8.0"`，`wp-china-yes.php:13` `Requires PHP: 8.0`，M4-01 已把下限钉在 8.0。用户说的「仍声明 7.4」与仓内事实不符。新代码无 `match` / union 属性 / named arguments；typed property（`private Store $store`）在 7.4 已有，按 8.0 无兼容债。本机 `php=8.4.7`。
- **unreachable 后更新停**：`UpdateBridge`「只在 connected 时挂钩」按字面执行（`UpdateBridge.php:136-138` + `is_connected()`）。一次 `product_list` 失败把 connection 写成 `unreachable` 后，后续 `pre_set_site_transient_update_plugins` 不再填包。密钥仍在（P4「不清密钥」做到了），只是桥不再跑。
- **UrlGuard 不解析 DNS**：`UrlGuard.php:18-20,58` 对主机名直接 `return true`。规格 §4「拒绝解析到内网地址的主机」。`api_url` 写死 `https://mall.weixiaoduo.com`、用户不可改，SSRF 面窄。
- **sodium 缺失**：`SecretBox::seal()` 返回 null → connect 503（`ProviderService.php:168-173`），不是 Fatal。
- **多站点**：`Store` 只用 `get_option`/`update_option`，无 `get_site_option`。`uninstall.php:40-51` 对 `get_sites()` 逐站删。当前站会删两次，无害。
- **`instance()` 每次 products/test 都会 `put_instance` 再写一遍 option**（`Store.php:155-158`）。
- **UpdateBridge 不比较版本**：只要 mall 给了 `new_version` 或 `package` 就写入 `response[]`（`UpdateBridge.php:164-175`），含空 package + 有版本、或 new_version ≤ 已装。

---

## C 规范

PSR-4 / `strict_types` / 禁区路径守住；`uninstall.php` 无 `strict_types`；直写商城域名与模块直接 `get_option` 是任务书授权的例外。

- 命名空间 `WenPai\ChinaYes\Providers\` → `src/Providers/`，一类一文件。
- 新 PHP 均有 `declare(strict_types=1);`。**例外**：`uninstall.php:1-11` 无。
- 禁区：diff 无 `src/Admin/app/**`、无 `framework/`、无 `SiteBinding` 行为改动、无 `docs/specs/providers.md`。`rg` 新代码无 `wp_china_yes`、无「遥测/匿名数据/隐私」。
- **直写** `https://mall.weixiaoduo.com`（`Presets.php:76`）：与 coding-standards「商业链接走 wpcy.com/go」冲突，但是规格 §1 写死的 API 基址，不是用户可见外链。
- **模块直接 `get_option`**：`Store.php:54,409`、`ProviderService.php:496`。任务要求独立 option、不进 `wpcy_settings`；与「经 Config\Repository」冲突，任务书优先。但 `ProviderService::read_instance()` 绕过刚写的 module-authoring「不要直接读 `wpcy_secure_*`」。
- WPCS：`composer check` 退出 0（phpcs 成功时常无输出）。

---

## D 安全

写路径有 `manage_options` + nonce；密钥加密、REST 不回密钥/全邮箱/instance；出站 HTTPS + sslverify + 10s。缺口：主机名不解析、更新包 URL 未再校验、密钥在 query。

- **Capability / nonce**：读 `Permissions::manage_options_read`（`ProvidersController.php:64`）；写 `manage_options_write`（同文件 74、84、94）→ cap + `X-WP-Nonce`（`Permissions.php:52-60,109-115`）。
- **Sanitize**：email 经 `is_email`/`FILTER_VALIDATE_EMAIL`（`ProviderService.php:472-478`）；`license_key` 只 `trim`，不回显，进 secretbox。
- **凭据**：`SecretBox` 与 `CredentialStore` 同算法，key = `sha256(wp_salt('auth') . '|provider:' . $id)`（`SecretBox.php:101-103`）。空串拒绝（`Store.php:107-109`）。解密失败 fail-closed（`Store.php:128-142`，`StoreTest.php:53-68`）。
- **REST 泄漏**：`public_item()` 无密钥/全邮箱/instance（`ProviderService.php:365-380`）；`ProvidersControllerTest::secret_free` 用完整邮箱断言。
- **日志**：`Logger.php:195-198` 把 `api_key=`/`license_key=` 打成 `***`；`WcAmClient::warn()` 只记 host/path（`WcAmClient.php:327-348`）。
- **远程**：`timeout 10`、`sslverify true`、GET query 带 `api_key`（`WcAmClient.php:190-199`）。规格允许 query，禁止记完整 URL——客户端本身不把 URL 丢进 logger；若 WP HTTP API / 代理记完整 URL，P6 仍暴露。PHPDoc 写了取舍（`WcAmClient.php:5-12`）。
- **更新包**：`package` 原样写入 transient（`UpdateBridge.php:164-175`），无 HTTPS/`UrlGuard`。mall 被劫持或响应被改可变成任意下载 URL。
- **导出**：`ConfigCommand` 只导出 settings/identity，不含 `wpcy_secure_provider_*`（本 diff 未改导出；新 option 也未挂进 Repository）。Site Health 未加这些键。

---

## E 测试质量

`composer check` 绿；providers 套件在测「快乐路径 + 闸」，**假通过风险在未覆盖的缓存/schema/test 端点分支**。

本审查执行（只读）：

```text
$ composer check
exit 0
phpstan: [OK] No errors  (95/95)
phpunit suites: 全部 OK
  smoke 1, core 23, config 87, connectivity 110, telemetry 3,
  privacy 33, diagnostics 13, cli 8, rest 68, integrations 20,
  migration 45, site-binding 12, apps 68, entitlements 11,
  admin 19, providers 37 tests / 139 assertions
本机 PHP 8.4.7；耗时 257s
```

未覆盖 / 假通过：

- **无** `products()` 在 `unreachable`/`invalid` 下返回缓存的测试 → A 的 bug 不会红。
- **无** `connect` 空密钥 / 坏邮箱 → `wpcy_invalid_schema`。
- **无** `test()` 的 connected/invalid/unreachable。
- `UpdateBridgeTest` 只断言 `register()` 不挂钩（`UpdateBridgeTest.php:71-81`），不测恢复模式下直接调 `inject()`。
- `secret_free` 用 `preg_match('/license_key|alice@example.com/')` 且 `assertStringNotContainsString('instance')`（`ProvidersControllerTest.php:204-208`）：测的是公开 JSON，不是 option 袋；连成功后 option 里仍有密文，测试不会读到明文邮箱，过关合理。
- `WcAmClientTest::test_key_travels_in_query_result_omits_it` 只断言 **result JSON** 不含密钥；stub 的 `BindingStore::$requests[0]['url']` 含 `api_key=`，符合「日志不得含完整 URL」，但没断言 logger 记录。
- 集成脚本未在本审查执行。CI run id 本审查未见。

---

## F 与 spec 的偏差

逐条（`docs/specs/providers.md` + P1–P10）：

| 规格 | 实现 | 偏差 |
|---|---|---|
| §1 两 ID、mall URL 写死、集市 `coming_soon`+空 `api_url` | `Presets.php:70-84` | 无 |
| §2 `wpcy_providers` 形状、autoload=false、不进 settings/identity | `Store.php:53-68,392-396` | 无 |
| §2 密钥 secretbox + purpose `provider:{id}` | `SecretBox.php:101-103` | 无 |
| §2 `wpcy_secure_provider_{id}_instance` | `Store.php:185-187` **明文** UUID | 键名 `secure`，未封箱。规格未强制加密 instance |
| §2 transient 15min 新鲜 / unreachable ≤72h | TTL 写入 72h、新鲜用 `fetched_at`（`Store.php:243-256,266-277`） | 新鲜窗口实现对；**读取侧**见下 |
| §2 邮箱只存 mask+sha256 | `Store.php:197-207` | 无 |
| §3 GET `/providers` 形状 + `binding_status` | `ProviderService.php:91-100` | 无 |
| §3 POST connect：403 / 400 coming_soon / 400 schema / invalid 200 不存密钥 / 503 不存密钥 | `ProviderService.php:113-195` | schema 路径无测试；其余有 |
| §3 DELETE 幂等 disconnected | `ProviderService.php:205-218`；`ProvidersControllerTest.php:158-166` | 无 |
| §3 POST test 未连接 400 | `ProviderService.php:235-241` | 有测试；成功/失败无测试 |
| §3 GET products：未连接 `[]`；项含 installed/update_managed | `ProviderService.php:293-297,581-597` | **「未连接」被扩成一切非 connected**。§5 invalid「已购项只读」、unreachable「沿用缓存」做不到 |
| §3 响应不含密钥/全邮箱/instance | `public_item()` | 无 |
| §3 五条 message 原文 | 四处 `__()` + invalid 不走错误码（200+`connection`） | 与表一致；`wpcy_provider_unknown` 文案「暂时无法找到该供应商。」不在 §3 表，在 admin-ui-spec:226 |
| §4 基址 `/wc-api/wc-am-api/` | `WcAmClient.php:171` | 无 |
| §4 请求参数名 `wc-am-action` | 实现 `wc_am_action`（`WcAmClient.php:179`） | **参数名与规格字面不同**。提交 `6f235a4` 称 GET query 探到真实密钥错误，说明下划线名被商城接受；规格未改（任务禁止改规格） |
| §4 密钥优先 POST body，商城只收 query 则允许 | 五 action 全 GET query（`WcAmClient.php:5-12,190-194`） | 与探测一致；规格正文仍写「优先 POST」 |
| §4 `success:true` / `status_check=active` → ok | `WcAmClient.php:229-237` | 无 |
| §4 HTTPS、sslverify、10s、拒内网 | `WcAmClient.php:194-196` + `UrlGuard` | **主机名不 DNS 解析** |
| §4 / P7 精确目录匹配，无 AutoMatcher | `annotate()` + `UpdateBridge` | 无 |
| P3 先 bound | `ProviderService.php:118` | 无 |
| P5 REST/日志 | 见 D | 无实质泄漏；query 出站仍带密钥 |
| P6 | 见上 | query + 无 DNS |
| P8 子站 option | `Store::write_option` | 无 |
| P9 不碰 wenpai-updater / binding | diff 无那些路径 | 无 |
| §6 不做授权代理/自定义供应商/网络密钥 | 无对应代码 | 无 |
| 待定：邮箱是否参与服务端校验 | `activate()` **不传 email** | 未核实，符合待定；应写进实现报告 |
| 待定：密钥能否走 body | 探测：body「未收到请求值」，query 有业务错误 | 实现选 query |

---

## G 阻断 / 建议 / 确认无误

### 阻断

1. **`products()` 把 `invalid`/`unreachable` 当成未连接，72h 缓存只活一次**  
   - 位置：`src/Providers/ProviderService.php:293-297`（门），`339-349`（当次失败才读缓存），`409-411`（`is_connected`）。  
   - 修法：门改为「无密钥或 `connection === 'disconnected'` → `[]`」。`connected`/`invalid`/`unreachable`：新鲜缓存直接返回；过期则拉远端；失败时 `invalid` 仍可返回缓存（只读），`unreachable` 且 `products_stale_ok` 返回缓存。不要在失败路径里把 connection 改掉后再用同一函数的门把自己踢出去。补测：connected → 一次 timeout → 第二次 `products()` 仍非空；`invalid` 仍返回已购项。

### 建议

1. **UrlGuard 对主机名放行**（`UrlGuard.php:58`）。`api_url` 写死故风险低。若要贴规格，对解析后的 IP 走 `FILTER_FLAG_NO_PRIV_RANGE`（或 `wp_http_validate_url`），单测继续只测 IP 字面量。  
2. **`wc-am-action` vs `wc_am_action`**：实现与探测用下划线，规格用连字符。任务禁止改规格，报告里应写「规格字面未改、现网参数是 `wc_am_action`」。  
3. **instance 明文进 `wpcy_secure_*`**（`Store.php:185-187`）。不是密钥，但和键名不符；可同样 secretbox，或改名（改名要动规格，本任务不能做）。  
4. **更新 `package` 未过 UrlGuard**（`UpdateBridge.php:164-175`）。空 package 仍写入 `response[]`。应要求 HTTPS URL，且 `version_compare( $new, $installed, '>' )`。  
5. **`ProviderService::read_instance()` 直接 `get_option`**（`ProviderService.php:496`），与刚加的 module-authoring 相反；改走 `Store::peek_instance()`。  
6. **`uninstall.php` 补 `declare(strict_types=1);`**。  
7. 补测：`wpcy_invalid_schema`、`test()` 三态、恢复模式下 `inject()` 直接调用、logger 对 `url` 上下文。  
8. 源码 diff ~2300 行，超出任务书 1200 行预算（测试另计）。

### 确认无误

- 五端点注册、`{id}` 枚举 404、bound 闸 403、coming_soon 400、invalid/unreachable **首次 connect** 不落密钥。  
- 邮箱只存 `a***@domain` + sha256；REST 公开对象无密钥/全邮箱/instance。  
- Logger 将 `api_key=abc` 打成 `api_key=***`（`LoggerRedactTest.php:26-32`）。  
- `UpdateBridge` 只填 `update_managed`；`register()` 在 `recovery_mode` 不挂钩。  
- 不改 `providers.md`、不改 `src/Admin/app/**`、不改 SiteBinding 行为、不碰 `framework/`、无「遥测」类用户文案。  
- `composer check` 退出 0（phpstan 无错，含 providers 在内的单元套件全绿）。
