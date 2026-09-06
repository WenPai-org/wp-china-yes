# 任务 M-PROVIDER-1：供应商层后端——薇晓朵商城连接、已购产品、更新接通、`/providers*` REST

worktree 分支 `grok/m-provider-1`，基于 `main`（含 `docs/specs/providers.md`、决定 P1–P10、M-BLOCK-1 合入后的 `6dd4dce` 之后）。预计 diff ≤ 1200 行（不含测试）。**本任务不改任何 React/界面文件**（`src/Admin/app/**` 禁区）。与 M-STATS-1（`grok/m-stats-1`）并行，两者都会在 `src/Rest/RestModule.php::register_routes()` 加一行注册与 `docs/specs/rest-api.md` 错误表加行——只加不改，冲突由统筹合并时处理。

## 目标

叶子里出现第二层账户：管理员用购买时的邮箱 + 授权密钥连接**薇晓朵商城**，叶子拉取该账户已购产品、为其中已装在本站的插件接通更新；**文派集市**以 `coming_soon` 占位。规格已冻结在 [`docs/specs/providers.md`](../../specs/providers.md)（存储 §2、REST §3、WC AM 合同 §4），**不得改规格**，做不到就在报告写明。

## 背景与上下文文件

- 规格：`docs/specs/providers.md`（全文）、`docs/specs/rest-api.md`（通用约定、`/providers*` 索引行、错误码表）、`docs/specs/entitlements.md` §1（`/binding` 状态语义，供"先绑文派"闸用）。
- 决定：`.grok-context/2026-09-06-core-services-value-and-providers.md`（D4 / D5 与"统筹拍板" P1–P10）。
- 分析（只读参考，**不要照抄代码**）：`.grok-context/2026-09-06-wpbridge-providers-model.md`（云桥 `WooCommerceVendor` 的 WC AM 用法在 §1.3、§2，风险在 §7）。云桥源码在 `~/wt/wpbridge`，可以读、不能改、不能复制整文件。
- 现有代码：`src/Services/SiteBinding/CredentialStore.php`（`seal()` / `open()`，sodium secretbox；**复用其算法，另派生 purpose**）、`src/Services/SiteBinding/SiteBindingModule.php::snapshot()`（读绑定状态）、`src/Services/Entitlements/Client.php`（出站 HTTP 与缓存的既有写法）、`src/Rest/RestModule.php`、`src/Rest/RestError.php`、`src/Rest/BindingController.php`（控制器风格）、`src/Core/Container.php`、`src/Core/Module.php`、`src/Core/Logger.php`（若有脱敏机制）、`src/Diagnostics/Checker.php`（出站校验 / 内网拒绝的既有函数，若有）。
- 规范：`docs/dev/task-book-template.md`（按其 DoD 报告）、`docs/dev/coding-standards.md`、`docs/dev/module-authoring.md`、`docs/dev/testing.md`、`docs/dev/security.md`、`docs/dev/agent-common-rules.md`、`.grok-context/COMMON-RULES.md`。

## 交付物

| # | 交付 | 路径 | 做法 |
|---|---|---|---|
| 1 | 预置表 | 新建 `src/Providers/Presets.php` | 常量表：`weixiaoduo-mall`（薇晓朵商城，`available`，`https://mall.weixiaoduo.com`）、`wenpai-marketplace`（文派集市，`coming_soon`，`api_url` 为 `''`）。`get( string $id ): ?array`、`ids(): array`。名称用 `__()` |
| 2 | 存储 | 新建 `src/Providers/Store.php` | 按 `providers.md` §2：`wpcy_providers`（公开摘要）、`wpcy_secure_provider_{id}_license_key`（用 `CredentialStore` 同算法，key 派生加 purpose `provider:{id}`——若 `CredentialStore::key()` 不可注入 purpose，新建 `Providers\SecretBox` 复制其 30 行并加 purpose，报告写明）、`wpcy_secure_provider_{id}_instance`（UUID v4）、transient `wpcy_provider_{id}_products`（15 分钟；`unreachable` 时允许沿用 ≤ 72 小时）。全部 `autoload=false`、按站点。邮箱只存 `email_masked` + `email_hash`（sha256），**不存明文** |
| 3 | WC AM 客户端 | 新建 `src/Providers/WcAmClient.php` | 按 `providers.md` §4：`activate` / `deactivate` / `status` / `update` / `product_list`；HTTPS only、`sslverify=true`、超时 10s、拒内网主机（复用仓内既有校验函数；没有就新建 `Providers\UrlGuard`）；密钥**优先 POST body**，若响应表明只接受 query（或云桥现状证明必须 query），改 query 但**任何日志与错误对象不得含完整 URL**——在 PHPDoc 写明取舍与依据。返回 `{ ok: bool, kind: 'ok'|'invalid'|'unreachable', data: array }` |
| 4 | 服务 | 新建 `src/Providers/ProviderService.php` | `list()`、`connect( $id, $email, $key )`（闸：`SiteBindingModule::snapshot()['status'] === 'bound'`；`coming_soon` 拒；先 `activate`，成功再落库）、`disconnect( $id )`（`deactivate` 尽力而为、必删本地）、`test( $id )`、`products( $id )`（缓存；标 `installed` = 与 `get_plugins()` 目录名精确匹配；`update_managed` 同）。所有分支的 `connection` 值变更与 `last_checked_at` 写回 `wpcy_providers` |
| 5 | 更新接通 | 新建 `src/Providers/UpdateBridge.php`（`Core\Module`，id `providers`，contexts 全部） | 钩 `pre_set_site_transient_update_plugins`：对 `products()` 中 `update_managed=true` 的项调 `update` action，用 `package` / `new_version` 填 `response[$plugin_file]`；只在 `connected` 时挂钩；恢复模式下不挂钩。**不做**标题猜 slug、不做 AutoMatcher |
| 6 | REST | 新建 `src/Rest/ProvidersController.php`；`RestModule::register_routes()` 加一行 | 严格按 `providers.md` §3 五个端点、错误码与 message；`{id}` 枚举校验；响应**不含**密钥 / 完整邮箱 / instance。message 用 `__()`，原文照抄 §3 表 |
| 7 | 日志脱敏 | `src/Core/Logger.php`（或仓内 logger）| 对 `license_key` / `api_key` 键与 URL query 中的 `api_key=` 值脱敏为 `***`。若 logger 已有脱敏表，只加键；没有则加最小实现 |
| 8 | 卸载 | `uninstall.php`（若存在）| 删除 `wpcy_providers`、`wpcy_secure_provider_*`、`wpcy_provider_*` transient |
| 9 | 文档 | `docs/specs/rest-api.md` 错误表已有五行不改；`docs/dev/module-authoring.md` 加一节「供应商层：用 `ProviderService`，不要直接读 `wpcy_secure_*`」（≤ 12 行）；`docs/design/admin-ui-spec.md` §5 词表**只追加**五条 message 行（§3 表） | 不改规格正文；做不到 → 报告 |

## 行为规格

- 规格 = `docs/specs/providers.md` §2 / §3 / §4 / §6；拍板 P1–P10。
- 安全（P5 / P6）：密钥只在 `wpcy_secure_provider_*`；REST 任何响应不含密钥、完整邮箱、instance；日志不含密钥与含 `api_key` 的完整 URL；解密失败 = `disconnected`（fail-closed），**不得**把空串 / 占位符发出站。
- 闸（P3）：未 `bound` 不接受 `connect`（403 `wpcy_provider_binding_required`）。
- 多站点（P8）：按子站 option；不读写网络 option。
- 隔离（P9）：不碰 `wenpai-updater` 路径、不碰 `/binding` 与 `wpcy_site_identity`。

## 禁区

- 不改 `src/Admin/app/**`、`src/Admin/AdminModule.php`、`src/Admin/RecoveryPage.php`。
- 不改 `docs/specs/providers.md` 正文（发现做不到 → 报告）。
- 不改 `src/Services/SiteBinding/**` 的行为（只读 `snapshot()`；若必须改 `CredentialStore` 以注入 purpose，只加可选参数、默认行为不变，并有测试证明旧密文仍可解）。
- 不实现授权代理、自定义供应商、网络级共享密钥、AutoMatcher（`providers.md` §6）。
- 不新增用户可见英文；不写"遥测 / 匿名数据 / 隐私"字样。
- 不 `git push` 到 `main`；只推 `grok/m-provider-1`。

## 验收标准

1. `composer check` 绿；分支 CI 全绿（贴 run id）。
2. 单元测试（`tests/Unit/Providers/`、`tests/Unit/Rest/ProvidersControllerTest.php`）：`Presets` 两个 ID 与 404；`Store` 密钥密文可解 / 解密失败 → `disconnected` / 邮箱只存掩码与哈希 / `disconnect` 删三键；`WcAmClient` 用 stub HTTP：2xx `success:true` → `ok`、`success:false` → `invalid`、超时 / 非 2xx → `unreachable`、HTTP 目标 → 拒绝、内网主机 → 拒绝；`ProviderService::connect` 未 bound → 403 code、`coming_soon` → 400 code、成功落库、`invalid` 不落库、`unreachable` 不落库；`products()` 精确匹配 `installed` / `update_managed`；`UpdateBridge` 只对 `update_managed` 项填 transient、恢复模式不挂钩；`ProvidersController` 五端点形状、响应经 `json_encode` 后 `rg -c "license_key|@example.com" == 0`（用完整邮箱做断言）、错误码与 message 原文；Logger 脱敏 `api_key=abc` → `api_key=***`。
3. 集成（`tests/integration-providers.sh` 或并入现有 wp-env 脚本）：`curl GET /providers` 形状；`POST /providers/weixiaoduo-mall/connect` 在未绑定时 403；在 stub 绑定为 `bound` 且 WC AM 指向本地 mock 时 200 且 `connection=connected`；`GET /providers/weixiaoduo-mall/products`；`DELETE` 后 `disconnected`。mock 用 wp-env 内的 `mu-plugin` 拦 `pre_http_request`。
4. 报告贴：`| 端点 | 控制器方法 文件:行 | 测试 |` 五行；`| 安全约束 P5/P6 | 实现位置 | 测试 |` 表；`| 规格 §4 合同项 | 实现 | 未核实项 |`（邮箱是否参与服务端校验、密钥能否走 body 两条按实际探测结果写；探测只对 `https://mall.weixiaoduo.com` 做**只读** `status` 请求，用随机无效密钥，不得用真实密钥）。
5. DoD 七项逐条（UI 截图项写"不适用"）。
6. 提交前缀 `feat(providers):` / `feat(rest):` / `fix(log):` / `docs(providers):`；至少分 4 笔。

## Definition of Done

- [ ] 规格条目 ↔ 实现逐条对照表（`| 规格编号 | 实现位置 文件:行 | 状态 |`），规格里有、实现没有的必须列出并写「未做」。
- [ ] 每个状态一张截图（Playwright，命名 `<页面>-<状态>.png`，存 `docs/design/screens/<任务>/`），UI 任务必备；非 UI 任务写「不适用」。
- [ ] 面向用户的字符串：全部中文、在 `admin-ui-spec.md` §5 词表内或已提交词表新增；无英文错误串；无「遥测/隐私/上报」字样。
- [ ] 空状态、错误状态、降级状态有对应实现，不是「空表占位」。
- [ ] 测试：新增/修改行为有测试；`composer check` 与 `npm run build`、`npm run lint:js` 通过；CI run id。
- [ ] `git diff --stat` 在允许路径内；`.grok-context/` 未入库。
- [ ] 报告含「没做 / 做不到 / 有疑问」；**报告写完再退出**（等 CI 时先写「CI run <id> 进行中，本地验收如下」）。

## 报告格式

见 `docs/dev/agents.md`。报告写到 `~/wt/grok-tasks/REPORT-m-provider-1.md`（启动脚本已重定向 stdout）。
