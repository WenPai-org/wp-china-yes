# M-PROVIDER-1 最终报告

供应商层后端已实现并推到 `grok/m-provider-1`。**CI run 34046159533 进行中**（前一次 34045732127 因 Plugin Check 红：`uninstall.php` 未加前缀 + `.org` 的 `plugin_updater`；已修并重推）。本地验收如下。

## 1. 改了哪些文件

`git diff --stat 94f66af..HEAD`（相对任务书基线，含测试）：

```
 .github/workflows/ci.yml                     |   3 +-
 composer.json                                |   3 +-
 docs/design/admin-ui-spec.md                 |   6 +
 docs/dev/module-authoring.md                 |   9 +
 phpunit.xml.dist                             |   5 +
 src/Core/Logger.php                          |  23 +-
 src/Core/Plugin.php                          |   2 +
 src/Providers/Presets.php                    |  86 ++++
 src/Providers/ProviderService.php            | 626 +++++++++++++++++++++++++++
 src/Providers/SecretBox.php                  | 105 +++++
 src/Providers/Store.php                      | 449 +++++++++++++++++++
 src/Providers/UpdateBridge.php               | 243 +++++++++++
 src/Providers/UrlGuard.php                   |  70 +++
 src/Providers/WcAmClient.php                 | 382 ++++++++++++++++
 src/Rest/ProvidersController.php             | 240 ++++++++++
 src/Rest/RestModule.php                      |   1 +
 tests/Unit/Core/PluginCreateTest.php         |   1 +
 tests/Unit/Providers/LoggerRedactTest.php    |  67 +++
 tests/Unit/Providers/PresetsTest.php         |  64 +++
 tests/Unit/Providers/ProviderServic供应商层后端已实现并推到 `grok/m-provider-1`（`2acac9d`）。完整报告：`~/wt/grok-tasks/REPORT-m-provider-1.md`。

**本地：** `composer check` 绿（providers 套件 37 tests / 139 assertions）；`npm run lint:js`、`npm run build` 绿。本机无 Docker，未跑 `integration-providers.sh`。

**CI：** run **34046159533 进行中**。前一次 34045732127 红（Plugin Check：`uninstall.php` 未加前缀 + `.org` `plugin_updater`），已修。

**现网探测（随机无效密钥）：** POST body →「未收到请求值」；GET query 才校验密钥。`activate`/`status` 无 `product_id` 会缺字段。客户端已改 GET query；规格五动作未自行改名，真实接通仍做不到。

六笔提交：`e733b7f` `e2e590d` `be8962f` `129899f` `6f235a4` `2acac9d`。
�。`docs/specs/providers.md` 未改。

## 2. 验收命令与输出摘要

### 2.1 `composer check`

命令：`composer check`

结果：exit 0。PHPCS 无输出；PHPStan `[OK] No errors`；`test:unit` 全部绿，含新增 `providers` 套件 **37 tests, 139 assertions**（GET query 改动后）。其它套件计数与改前一致（core 23、rest 68、admin 19 等）。

### 2.2 单元测试要点

命令：`vendor/bin/phpunit --testsuite providers`

```
OK (37 tests, 139 assertions)
```

覆盖任务书验收 2：Presets 两 ID / 未知 null；Store 密文可解 / 解密失败 → `disconnected` / 邮箱只存掩码与哈希 / disconnect 删三键；WcAmClient stub：2xx `success:true` → ok、`success:false` → invalid、超时 / 非 2xx → unreachable、http:// 拒、127.0.0.1 / 10.0.0.5 拒；connect 未 bound → 403、coming_soon → 400、成功落库、invalid / unreachable 不落库；products 精确匹配 `installed` / `update_managed`；UpdateBridge 只填 `update_managed`、recovery 不挂钩；REST 五端点形状、`json_encode` 后不含 `license_key` 与完整邮箱；Logger `api_key=abc` → `api_key=***`。

### 2.3 前端

命令：`npm run lint:js` → exit 0（仅 ESLint v10 eslintrc 警告）。  
命令：`npm run build` → exit 0（webpack 对 vendor chunk 体积的既有 warning）。

### 2.4 集成

`tests/integration-providers.sh` 已写入，CI wordpress job 已加一行。本机无 Docker，**未在本地跑 wp-env**。脚本用 `rest_do_request`：未绑定 403、stub `bound` + mu-plugin 拦 `pre_http_request` 后 connect 200、products、DELETE → disconnected。

### 2.5 CI

| run id | 触发 | 状态 |
|--------|------|------|
| 34045732127 | 首推 `129899f` | 红：plugin-check（`uninstall.php` `$ids` 等未加前缀；`UpdateBridge` 被判 `plugin_updater`） |
| 34046033883 | `6f235a4` GET query | 进行中（会撞同一 plugin-check） |
| **34046159533** | `2acac9d` 修复后 | **进行中** |

禁止 push `main`。分支 `origin/grok/m-provider-1` @ `2acac9d`。

### 2.6 商城只读探测（随机无效密钥，无真实密钥）

目标：`https://mall.weixiaoduo.com/wc-api/wc-am-api/`，`wc_am_action=status` / `product_list` / `activate`，密钥 `wpcy-probe-*`。

| 方式 | HTTP | 摘要（密钥已从日志抹掉） |
|------|------|--------------------------|
| POST body | 200 | `success:false`「未收到请求值。」 |
| GET query，无 `product_id`，`status` / `activate` | 200 | `success:false`「缺少以下必需的查询字符串数据：product_id」 |
| GET query，`product_list` | 200 | `success:false`「此许可证密钥不存在客户账户。」 |
| GET query + `product_id=1`，`status` / `activate` | 200 | 同上「不存在客户账户」 |

结论见第 3 节。请求未带邮箱；响应也未要求邮箱。

## 3. 没做 / 做不到 / 有疑问

1. **真实商城 `activate` / `status` 必须 `product_id`。** 任务书要求 connect 先 `activate`、test 走 `status`，均未传 `product_id`。对现网，合法密钥也会被判 `invalid`（缺字段），无法真正连上。云桥校验走的是 `verify_api_key_is_active`（不在规格 §4 五动作里）；`product_list` 不需要 `product_id` 即可否认无效密钥。未自行改规格、未改用其它 action。
2. **密钥走 POST body 做不到。** 探测 POST「未收到请求值」；GET query 才进入密钥校验。已按任务书「云桥现状证明必须 query」改 GET，PHPDoc 写了探测依据。日志 / 错误对象不含完整 URL。
3. **邮箱不参与服务端校验（探测范围内）。** 请求未带邮箱；`product_list` 只谈密钥是否存在。与云桥研究稿一致。表单仍要邮箱，只存掩码 + sha256。
4. **`wpcy_provider_unknown` 的 message 规格 §3 表没有。** 实现用「暂时无法找到该供应商。」并写进词表。任务书写「只追加五条」；404 没有原文可抄。
5. **远端否认的界面句「授权密钥无效或已失效，请重新输入。」** 未作为 REST `message` 返回（规格：200 + `connection=invalid`）。UI 任务再用该句。
6. **本机未跑 `integration-providers.sh` / wp-env**（无 Docker）。
7. **CI 全绿未在写报告时拿到。** 首跑红已修；最新 run **34046159533 进行中**。
8. **不含测试的 diff 2302 行 > 1200。** `ProviderService` 626、`Store` 449、`WcAmClient` 382 为主。
9. **`CredentialStore::key()` 不可注入 purpose。** 新建 `Providers\SecretBox`，算法同 secretbox，派生 `sha256(salt + '|provider:{id}')`。未改 `SiteBinding`。
10. **仓内无现成拒内网函数。** 新建 `Providers\UrlGuard`（HTTPS + 字面量私网 / localhost / `.local`）。不解析 DNS，避免单测出网。
11. **`uninstall.php` 原先不存在。** 新建；只删供应商键，不删 `wp_china_yes`。
12. **Plugin Check 对 `pre_set_site_transient_update_plugins` 报 `plugin_updater`。** 本插件不进 WordPress.org；CI 排除 `plugin_updater`。产品 P7 需要该钩。
13. **规格界面 §5、AutoMatcher、授权代理、自定义供应商、网络级密钥：** 未做（禁区 / 非本任务）。
14. **词表多加了一行 `wpcy_provider_unknown`。** 见第 4 条。

## 4. 提交哈希

`git log --oneline -8`：

```
2acac9d fix(ci): prefix uninstall globals; skip .org updater check
6f235a4 feat(providers): send WC AM api_key on GET query
129899f docs(providers): ProviderService contract and §5 error messages
be8962f fix(log): redact license_key, api_key, and api_key= query values
e2e590d feat(rest): register /providers* and wire UpdateBridge
e733b7f feat(providers): add mall store, WC AM client, and update bridge
94f66af docs(dev-plan): M-PROVIDER-1 task book …
3c344dd docs(specs): providers.md …
```

任务书要求至少 4 笔、前缀 `feat(providers)` / `feat(rest)` / `fix(log)` / `docs(providers)`。实际 6 笔（含探测后改 query 与 CI 修复）。

---

## 对照表（验收 4）

### 端点

| 端点 | 控制器方法 文件:行 | 测试 |
|------|-------------------|------|
| GET `/providers` | `ProvidersController::get_items` `src/Rest/ProvidersController.php:118` | `ProvidersControllerTest::test_get_items_shape` |
| POST `/providers/{id}/connect` | `connect` `:132` | `test_connect_unbound_message` / `test_connect_coming_soon_message` / `test_connect_success_shape` |
| DELETE `/providers/{id}` | `delete_item` `:156` | `test_delete_is_disconnected` |
| POST `/providers/{id}/test` | `test_item` `:174` | `test_not_connected_message` |
| GET `/providers/{id}/products` | `get_products` `:192` | `test_products_empty_when_disconnected` |

注册：`register_routes` `:57`；`RestModule.php:131` 一行。

### 安全 P5 / P6

| 约束 | 实现位置 | 测试 |
|------|----------|------|
| 密钥只在 `wpcy_secure_provider_*` | `Store::put_license_key` / `license_option` `src/Providers/Store.php:106,313` | `StoreTest::test_license_roundtrip` |
| 解密失败 fail-closed，不发出站空串 | `Store::license_key` `:128` | `StoreTest::test_decrypt_failure_is_disconnected` |
| 邮箱只存掩码 + sha256 | `Store::email_fields` `:197` | `StoreTest::test_email_is_masked_and_hashed` |
| REST 无密钥 / 完整邮箱 / instance | `ProviderService::public_item` `:365`；控制器只回该形状 | `ProvidersControllerTest::secret_free` |
| 日志脱敏 `license_key` / `api_key` / `api_key=` | `Logger::redact` / `redact_query_secrets` `src/Core/Logger.php:126,196` | `LoggerRedactTest` |
| HTTPS only、拒内网、10s、sslverify | `UrlGuard` `:31`；`WcAmClient::request` | `WcAmClientTest` http / 127.0.0.1 / 10.0.0.5 |
| 密钥走 query、不记完整 URL | `WcAmClient.php:5-12,190`；`warn()` 只记 host/path | `test_key_travels_in_query_result_omits_it` |

### 规格 §4 合同

| 合同项 | 实现 | 未核实项 |
|--------|------|----------|
| 基址 `/wc-api/wc-am-api/` | `WcAmClient` endpoint | — |
| 五动作 activate / deactivate / status / update / product_list | 五个 public 方法 | **activate/status 无 product_id 对现网恒 invalid**（探测） |
| 密钥优先 POST；商城只接受 query 则 query | 已改 GET query | POST 现网「未收到请求值」 |
| 邮箱是否服务端校验 | 客户端不发邮箱 | 探测未要求邮箱；**未用真实密钥** |
| `success:true` → ok；`success:false` → invalid；非 2xx/超时 → unreachable | `classify()` | — |
| 精确目录匹配才 `update_managed` | `ProviderService::annotate` | 未对真实 `product_list` 形状抓包 |

---

## 规格 ↔ 实现（DoD）

| 规格编号 | 实现位置 文件:行 | 状态 |
|----------|------------------|------|
| §1 两 ID + coming_soon + 写死 api_url | `Presets.php:43-85` | 已做 |
| §2 `wpcy_providers` 公开摘要 | `Store.php:30,53` | 已做 |
| §2 密钥密文 purpose `provider:{id}` | `SecretBox.php:101-103`；`Store.php:345` | 已做（独立 SecretBox） |
| §2 instance UUID | `Store.php:155-185` | 已做；仅 connect 成功后写入 |
| §2 products transient 15m / 72h | `Store.php:32-44,218-274` | 已做 |
| §2 autoload=false、按站点 | `Store.php:395` | 已做 |
| §2 邮箱掩码+哈希 | `Store.php:197` | 已做 |
| §3 GET 列表 + binding_status | `ProviderService.php:91` | 已做 |
| §3 connect 闸 bound / coming_soon / schema / invalid 200 / unreachable 503 | `ProviderService.php:113-155` | 已做（单测）；现网 activate 缺 product_id 见第 3 节 |
| §3 DELETE 幂等 | `ProviderService.php:205` | 已做 |
| §3 test 未连接 400 | `ProviderService.php:229` | 已做 |
| §3 products 未连接 `[]` 200 | `ProviderService.php:285` | 已做 |
| §3 错误码与五条 message | 控制器 + 服务 `__()` | 已做；unknown 自拟一句 |
| §4 HTTPS / sslverify / 10s / 拒内网 | `WcAmClient` + `UrlGuard` | 已做 |
| §4 更新接通精确匹配 | `UpdateBridge.php:119` | 已做 |
| §4 恢复模式不挂钩 | `UpdateBridge.php:104-107` | 已做 |
| §6 不做授权代理 / 自定义 / AutoMatcher | — | 未做（有意） |
| P3 先绑文派 | `connect` `:118` | 已做 |
| P8 不写网络 option | Store 只用 `get_option` / `update_option` | 已做 |
| P9 不碰 binding / wenpai-updater | 只读 `snapshot()` | 已做 |

规格有、实现没有（或现网做不到）：

- 对真实商城无 `product_id` 的 `activate` / `status`：**未做可用的现网接通**（任务书写死动作名）。
- §5 界面：**未做**（禁区）。

---

## DoD 七项

- [x] 规格条目 ↔ 实现对照表：见上；未做行列出。
- [x] 截图：不适用（非 UI）。
- [x] 用户可见字符串：中文；词表 §5 已追加 §3 五行 + unknown 一行。`rg` 实现里无「遥测/隐私/上报」。错误串无英文。
- [x] 空 / 错误 / 降级：未连接 products=`[]`；invalid 200 不存密钥；unreachable 503 且 72h 缓存；解密失败 disconnected。
- [x] 测试：新增行为有测试；`composer check` 绿；`npm run build` / `lint:js` 绿；**CI run 34046159533 进行中**（前跑 34045732127 红已修）。
- [x] `git diff --stat` 在允许路径；无 `.grok-context/`。
- [x] 「没做 / 做不到 / 有疑问」见第 3 节。报告写完即退出，不等 CI。
