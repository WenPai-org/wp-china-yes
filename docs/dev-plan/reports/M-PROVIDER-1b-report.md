# 最终报告：M-PROVIDER-1b

分支 `grok/m-provider-1` HEAD `9583028`。相对 `2acac9d` 追加四笔提交。CI run **34050443293 进行中**，本地验收如下。

规格字面 `wc-am-action` 未改、现网参数是 `wc_am_action`。

## 1. 改了哪些文件

`git diff --stat 2acac9d`：

```
 src/Providers/ProviderService.php            | 125 ++++++++++++--------
 src/Providers/Store.php                      | 158 +++++++++++++++++++++----
 src/Providers/UpdateBridge.php               |   5 +-
 src/Providers/WcAmClient.php                 |   8 +-
 tests/Unit/Providers/LoggerRedactTest.php    |  18 +++
 tests/Unit/Providers/ProviderServiceTest.php | 170 +++++++++++++++++++++++++--
 tests/Unit/Providers/StoreTest.php           |  30 +++++
 tests/Unit/Providers/UpdateBridgeTest.php    | 142 +++++++++++++++++++---
 tests/Unit/Rest/ProvidersControllerTest.php  |   4 -
 uninstall.php                                |   2 +
 10 files changed, 558 insertions(+), 104 deletions(-)
```

无 `.grok-context/`、无 `src/Admin/app/`、无 `docs/specs/providers.md`。

## 2. 验收命令与输出摘要

### 2规格字面 `wc-am-action` 未改、现网参数是 `wc_am_action`。CI run **34050443293 进行中**，本地验收如下。HEAD `9583028`，相对 `2acac9d` 四笔提交。

## 1. 改了哪些文件

`git diff --stat 2acac9d`：

```
 src/Providers/ProviderService.php            | 125 ++++++++++++--------
 src/Providers/Store.php                      | 158 +++++++++++++++++++++----
 src/Providers/UpdateBridge.php               |   5 +-
 src/Providers/WcAmClient.php                 |   8 +-
 tests/Unit/Providers/LoggerRedactTest.php    |  18 +++
 tests/Unit/Providers/ProviderServiceTest.php | 170 +++++++++++++++++++++++++--
 tests/Unit/Providers/StoreTest.php           |  30 +++++
 tests/Unit/Providers/UpdateBridgeTest.php    | 142 +++++++++++++++++++---
 tests/Unit/Rest/ProvidersControllerTest.php  |   4 -
 uninstall.php                                |   2 +
 10 files changed, 558 insertions(+), 104 deletions(-)
```

无 `.grok-context/`、无 `src/Admin/app/`、无 `docs/specs/providers.md`。

## 2. 验收命令与输出摘要

### 2.1 `composer check`

退出码 0。phpstan `[OK] No errors`（95/95）。phpunit 各套件全绿；providers **50 tests / 199 assertions**（本任务前 37/139）。本机 PHP 8.4，约 211s。

### 2.2 前端（DoD）

```
$ npm run lint:js
exit 0  (wp-scripts lint-js src/Admin/app；本任务未改该目录)

$ npm run build
exit 0  webpack compiled with 1 warning (asset size 907.js vendors，既有)
```

### 2.3 必修 A：connect/test 走 `product_list`；activate 仅更新接通

`vendor/bin/phpunit --testsuite providers` → `OK (50 tests, 199 assertions)`。

- `test_connect_success_persists`：一次请求 `wc_am_action=product_list`，不含 `activate`/`status`；`connection=connected`，产品数写入。
- `test_connect_invalid_does_not_persist` / `test_connect_unreachable_does_not_persist`：不落密钥。
- `test_test_three_states`：ok → connected + 写缓存；invalid → invalid；timeout → 503 `wpcy_provider_unreachable`。
- `test_activate_failure_does_not_block_other_product`：product_id=1 activate 失败不写入 response；product_id=2 成功并记入 `activated_products=['2']`。

### 2.4 必修 B：`products()` 门与缓存

- `test_products_timeout_then_second_call_keeps_cache`：connected → 超时 → 第二次仍 1 项，connection 保持 `unreachable`。
- `test_products_invalid_still_returns_purchased`：invalid 仍返回已购项。

### 2.5 必修 C

- 建议 4：`test_empty_package_is_not_injected`、`test_not_newer_version_is_not_injected`、`test_http_package_is_not_injected`。
- 建议 3：`test_instance_is_sealed`、`test_plaintext_instance_is_migrated`。
- 建议 5：`read_instance()` 走 `Store::read_instance()`（`src/Providers/ProviderService.php:212`），不再 `get_option`。
- 建议 6：`uninstall.php:9` `declare(strict_types=1);`。
- 建议 7：`test_connect_bad_email_is_invalid_schema`、`test_connect_empty_key_is_invalid_schema`、`test_test_three_states`、`test_recovery_mode_inject_is_noop`、`test_url_context_drops_query`。

### 2.6 只读真机探测（随机无效密钥，无真实密钥）

目标 `https://mall.weixiaoduo.com/wc-api/wc-am-api/`，`wc_am_action=product_list`，密钥 `wpcy-probe-*`（未打印）。经 `WcAmClient::product_list()`：

```
KIND invalid
OK false
SUCCESS false
RESULT_HAS_KEY no
RESULT_HAS_FULL_QUERY no
LOG_COUNT 0
LOG_HAS_KEY no
LOG_HAS_API_KEY_EQ no
LOG_HAS_FULL_URL no
ERR 此许可证密钥不存在客户账户。
```

`product_list` 路径判 `invalid`；结果对象与 logger 均无完整 URL、无密钥。invalid 路径不写 warning，故 `LOG_COUNT 0`。

### 2.7 CI

```
$ git push -u origin grok/m-provider-1
2acac9d..9583028  grok/m-provider-1 -> grok/m-provider-1

$ gh run list --branch grok/m-provider-1 --limit 1
in_progress  test: instance migration…  CI  grok/m-provider-1  push  34050443293
```

未 push `main`。写报告时 run **34050443293** 进行中（head `9583028`）。

## 3. 没做 / 做不到 / 有疑问

1. **建议 1（UrlGuard 解析 IP 后再判私网）未做。** 任务标可选；单测不出网约束下未加 DNS。`UrlGuard` 仍只拦字面量私网 / localhost / `.local`。
2. **规格字面 `wc-am-action` 未改。** 代码保持下划线 `wc_am_action`（`src/Providers/WcAmClient.php:183`）。统筹改规格。
3. **`activated_products[]` 写在 `wpcy_providers.items.{id}`。** 任务写 `wpcy_providers.items.{id}.activated_products[]`；公开 REST `public_item()` 不回该字段。规格 §2 表原先没有该键，未改规格。
4. **建议 8（源码行数预算）未动。** 本任务只追加，未压缩既有体积。
5. **本机无 Docker，未跑 `tests/integration-providers.sh`。** mock 仍认 `wc_am_action` query，connect 改 `product_list` 后脚本应仍可用。
6. **`UpdateBridge` 在 `unreachable`/`invalid` 时仍不挂钩更新**（`is_connected()` 仍要求 `connected`）。任务只改 `products()` 缓存门，未要求改更新钩。
7. **CI 全绿未在写报告时拿到。** run 34050443293 进行中。

## 4. 提交哈希

`git log --oneline -5`：

```
9583028 test: instance migration, package guards, logger url redact
7ea5d5e test: product_list three-state, schema, and products cache
15a046e fix(providers): guard update packages and declare uninstall types
ce255b1 fix(providers): connect and test via product_list
2acac9d fix(ci): prefix uninstall globals; skip .org updater check
```

四笔：A（`ce255b1`）/ 更新包+uninstall（`15a046e`）/ A+B 测（`7ea5d5e`）/ B+C 测（`9583028`）。

## 5. 规格 ↔ 实现对照（providers.md.v2 §2 / §3 / §4）

| 规格编号 | 实现位置 | 状态 |
|----------|----------|------|
| §2 `wpcy_providers` | `src/Providers/Store.php:30,53-68` | 已做；本任务加 `activated_products`（公开 REST 不回） |
| §2 `wpcy_secure_provider_{id}_license_key` secretbox purpose `provider:{id}` | `src/Providers/Store.php:106-146,394-396`；`src/Providers/SecretBox.php:101-103` | 已做（既有） |
| §2 `wpcy_secure_provider_{id}_instance` | `src/Providers/Store.php:155-221,405-406` 现 secretbox；明文迁移 `189-202` | 已做 |
| §2 transient 15min / unreachable ≤72h | `src/Providers/Store.php:37-44,323-336`；读侧 `src/Providers/ProviderService.php:310-376` | 已做（本任务修读侧门） |
| §2 邮箱 mask+sha256 | `src/Providers/Store.php:277-288` | 已做（既有） |
| §3 GET `/providers` | `src/Rest/ProvidersController.php:58-66`；`src/Providers/ProviderService.php:91-100` | 已做 |
| §3 POST connect 闸 / schema / invalid 不存 / 503 不存 | `src/Providers/ProviderService.php:116-196` | 已做；校验改 `product_list`（§4 修正） |
| §3 DELETE 幂等 disconnected | `src/Providers/ProviderService.php:206-219` | 已做 |
| §3 POST test 三态 | `src/Providers/ProviderService.php:230-285` | 已做；走 `product_list` |
| §3 GET products 未连接 `[]` | `src/Providers/ProviderService.php:310-313`（仅 disconnected 或无密钥） | 已做 |
| §3 响应不含密钥/全邮箱/instance | `public_item()` `src/Providers/ProviderService.php:387-402` | 已做 |
| §4 基址 `/wc-api/wc-am-api/` | `src/Providers/WcAmClient.php:175` | 已做 |
| §4 请求参数名 `wc-am-action` | 实现 `wc_am_action` `src/Providers/WcAmClient.php:183` | **字面未改规格**；现网下划线 |
| §4 连接/测试用 `product_list` | `src/Providers/ProviderService.php:144,248` | 已做 |
| §4 `success:true` + 产品数组（可空）→ connected | `src/Providers/WcAmClient.php:229-237` + connect `159-196` | 已做 |
| §4 `success:false` → invalid | 同上 | 已做 |
| §4 网络失败 → unreachable | 同上 | 已做 |
| §4 `activate` 只在更新接通对具体 `product_id` | `src/Providers/ProviderService.php:448-457,527-545`；记 `Store::mark_activated` | 已做 |
| §4 密钥 GET query；日志无完整 URL | `src/Providers/WcAmClient.php:194-211,331-348`；`src/Core/Logger.php:140-143,205-218` | 已做 |
| §4 HTTPS / sslverify / 10s | `src/Providers/WcAmClient.php:198-200` | 已做 |
| §4 拒内网主机 | `src/Providers/UrlGuard.php:31-58` **不 DNS 解析** | 部分：字面量；建议 1 未做 |
| §4 / P7 精确目录匹配 | `ProviderService::annotate`；`src/Providers/UpdateBridge.php:144-147` | 已做 |
| §4 更新包 HTTPS + UrlGuard + version `>` | `src/Providers/UpdateBridge.php:166-170` | 已做（本任务） |
| §6 授权代理 / 自定义供应商 / 网络密钥 | — | 未做（禁区） |

## 6. DoD

- [x] 规格对照表：上一节。未做行：§4 参数名字面、UrlGuard DNS、§6 禁区。
- [x] 截图：不适用（非 UI）。
- [x] 用户可见字符串：本任务未新增 `__()`；无「遥测/隐私/上报」。
- [x] 空/错误/降级：disconnected → `[]`；invalid 缓存只读；unreachable 72h 缓存；schema 400；test 三态。测试名见 §2.3–2.5。
- [x] 测试：新增行为有测试；`composer check` 退出 0；`npm run build` / `npm run lint:js` 退出 0；**CI run 34050443293 进行中**。
- [x] `git diff --stat 2acac9d` 在允许路径；`.grok-context/` 未入库。
- [x] 「没做 / 做不到 / 有疑问」见第 3 节。报告写完再退出。
