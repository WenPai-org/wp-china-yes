# 供应商（Providers）

状态：草案（2026-09-06 夜）· 来源：决定 [`2026-09-06-core-services-value-and-providers.md`](../dev-plan/decisions/2026-09-06-core-services-value-and-providers.md) D4 / D5 与"统筹拍板" P1–P10；分析依据 linuxjoy `docs/research/2026-09-06-wpbridge-providers-model.md`（R-PROVIDERS-0，读的是文派云桥插件 `wpbridge` 1.3.0）。实现任务 M-PROVIDER-1（后端）与 M-UI-3（服务页 SV-11 / SV-12）。不得在本文新增产品决定；空白处标「待定」。

## 0. 一句话

供应商是站点管理员用**购买时的邮箱 + 授权密钥**连上的商店账户；连接后叶子展示该账户已购的服务 / 产品，并为其中已装在本站的插件接通更新。它是**独立于**文派站点身份（`/binding`）的第二层；先绑文派，再连供应商。叶子不做授权代理、不做自定义供应商。

## 1. 预置供应商

| `id` | 名称（品牌原文） | `status` | `api_url`（写死，用户不可改） | 连接方式 |
|---|---|---|---|---|
| `weixiaoduo-mall` | 薇晓朵商城 | `available` | `https://mall.weixiaoduo.com` | WooCommerce API Manager（`wc-am-api`）：`instance` + `product_id`/`api_key`，见 §4 |
| `wenpai-marketplace` | 文派集市 | `coming_soon` | 待定（开放时补） | 待定；开放前界面只显示「即将开放」，无表单、无按钮 |

`{id}` 枚举只允许这两个；其它 → 404 `wpcy_provider_unknown`。不接受用户新增供应商，不接受自定义 URL（决定 D4 排除云桥的 `custom-bridge-api`）。

## 2. 存储

全部按**站点**存（多站点每子站独立，`get_option` / `update_option`；不做网络级共享密钥）。`autoload=false`。

| 键 | 内容 |
|---|---|
| `wpcy_providers` | `{ "schema_version": 1, "items": { "<id>": { "connection": "disconnected"\|"connected"\|"invalid"\|"unreachable", "email_masked": "a***@example.com"\|null, "email_hash": "<sha256>"\|null, "connected_at": ISO\|null, "last_checked_at": ISO\|null, "product_count": int\|null } } }`。**不含**密钥、不含完整邮箱 |
| `wpcy_secure_provider_{id}_license_key` | 授权密钥密文。算法与 `wpcy_site_identity.binding.credential` 相同（sodium secretbox / 现有加密基元），派生 purpose 不同（`provider:{id}`）。解密失败 = 视为未连接（fail-closed），**不得**把占位符或空串发出站 |
| `wpcy_secure_provider_{id}_instance` | WC AM `instance` 标识（UUID v4），首次连接生成后稳定；断开时删除 |
| `wpcy_provider_{id}_products` | transient，已购产品缓存副本，TTL 15 分钟；`unreachable` 时允许沿用至多 72 小时（与权益不可达策略同方向） |

不写进 `wpcy_settings`、`wpcy_network_settings`、`wpcy_site_identity`。导出（诊断报告、Site Health）不含以上 `wpcy_secure_*` 键。

## 3. REST（`wpcy/v1`）

通用约定同 [`rest-api.md`](rest-api.md)：写请求 `X-WP-Nonce`；错误形状 `{ code, message, data: { status, request_id } }`，前缀 `wpcy_`；权限 `manage_options`；时间 UTC ISO 8601。**任何响应不含 `license_key`、完整邮箱、`instance`。**

| 方法 | 路径 | 说明 |
|---|---|---|
| GET | `/providers` | 预置列表 + 连接摘要 + `binding_status`（透传 `/binding` 的 `status`）。形状见下 |
| POST | `/providers/{id}/connect` | body `{ "email": "…", "license_key": "…" }`。`binding_status ≠ bound` → 403 `wpcy_provider_binding_required`；`status = coming_soon` → 400 `wpcy_provider_coming_soon`；schema 不符（邮箱格式、密钥空）→ 400 `wpcy_invalid_schema`；远端否认 → 200 但 `connection = invalid`（不存密钥）；远端不可达 → 503 `wpcy_provider_unreachable`（不存密钥）；成功 → 存密文与 instance，`connection = connected`，返回与 GET 单项同形对象 |
| DELETE | `/providers/{id}` | 断开：删密钥、instance、产品缓存；`connection = disconnected`；幂等 |
| POST | `/providers/{id}/test` | 现场校验密钥；更新 `connection` 为 `connected` / `invalid` / `unreachable` 与 `last_checked_at`；未连接 → 400 `wpcy_provider_not_connected` |
| GET | `/providers/{id}/products` | 已购产品缓存副本。项：`{ "product_id", "title", "slug", "installed": true\|false\|null, "update_managed": bool }`。未连接 → `{ "products": [] }`（200，不报错） |

`GET /providers`：

```json
{
  "binding_status": "bound",
  "providers": [
    { "id": "weixiaoduo-mall", "name": "薇晓朵商城", "status": "available", "connection": "connected",
      "email_masked": "a***@example.com", "connected_at": "2026-09-06T12:00:00Z",
      "last_checked_at": "2026-09-06T12:05:00Z", "product_count": 2 },
    { "id": "wenpai-marketplace", "name": "文派集市", "status": "coming_soon", "connection": "disconnected",
      "email_masked": null, "connected_at": null, "last_checked_at": null, "product_count": null }
  ]
}
```

错误码（补进 rest-api.md 错误表）：`wpcy_provider_unknown` 404、`wpcy_provider_binding_required` 403、`wpcy_provider_coming_soon` 400、`wpcy_provider_not_connected` 400、`wpcy_provider_unreachable` 503。用户可见 message 按 `copy-guidelines.md` 模板「暂时无法 X，Y」，登记进 `admin-ui-spec.md` §5：

| code | message |
|---|---|
| `wpcy_provider_binding_required` | 暂时无法连接供应商，请先绑定本站。 |
| `wpcy_provider_coming_soon` | 文派集市即将开放，现在还不能连接。 |
| `wpcy_provider_not_connected` | 暂时无法测试连接，请先连接该供应商。 |
| `wpcy_provider_unreachable` | 暂时无法连接薇晓朵商城，请稍后重试。 |
| 远端否认（`connection = invalid`） | 授权密钥无效或已失效，请重新输入。 |

## 4. 与薇晓朵商城的合同（WooCommerce API Manager）

以云桥 `WooCommerceVendor` 的现网用法为事实（研究稿 §1.3）；叶子实现 `Providers\WcAmClient`：

- 基址 `https://mall.weixiaoduo.com/wc-api/wc-am-api/`；请求 `wc-am-action` ∈ `activate` / `deactivate` / `status` / `update` / `product_list`（云桥实际用到的子集）。
- 参数：`instance`（本站 UUID）、`api_key`（授权密钥）、`product_id`、`object`（站点 URL）。**密钥放置**：优先 POST body；若商城只接受 query（云桥现状），规格在此写明：允许 query，但日志与错误对象**不得**记完整 URL（P6）。**待定**：商城是否在服务端使用邮箱做二次校验（云桥代码里邮箱只存本地）。
- 校验成功的判定：响应 JSON `success: true`（或 `status_check = active`）；`success: false` + 业务错误 → `invalid`；HTTP 非 2xx / 超时 / DNS / TLS 失败 → `unreachable`。
- 出站：HTTPS only，`sslverify = true`，拒绝解析到内网地址的主机，超时 10 秒，不重试写操作。
- 更新接通（4.0 最小，P7）：`product_list` 返回的项与本站 `get_plugins()` 目录名**精确**匹配才接管：钩 `pre_set_site_transient_update_plugins`，用 `update` action 的 `package` / `new_version` 填 `response`；不匹配的只展示（`update_managed = false`）。不做标题猜 slug、不做 `AutoMatcher`。

## 5. 界面（服务页 SV-12 / SV-11；视觉见原型 `services-*.html`）

| 连接状态 | 卡片 | 可用服务列表 |
|---|---|---|
| `binding ≠ bound` | 「未连接」灰 pill + `.scope`「绑定本站后可连接」，无表单 | — |
| `disconnected` | 「未连接」+ 元信息「用购买时的邮箱和授权密钥连接」+ 次按钮「连接」→ Modal（邮箱、授权密钥；主按钮「连接」；错误 message 见 §3） | 无供应商项 |
| `connected` | 主色外圈；「已连接」绿 pill；「账户 {email_masked} · 连接于 {日期} · 已购 {n} 项产品在"可用服务"里」；元信息「授权密钥只保存在本站，已加密」；幽灵「断开」→ 确认 Modal | 每项：名称 + `.prov` 供应商 + 说明；状态 已安装 / 未安装 / 未购买；动作 管理 / 安装（唯一主按钮）/ 了解 → |
| `invalid` | 「授权失效」注意 pill + 「重新连接」→ 同 Modal（预填掩码不可见） | 已购项只读，标「授权失效」 |
| `unreachable` | 卡顶注意提示「暂时无法连接薇晓朵商城，显示的是 {N} 小时前的状态。」+「重试」 | 沿用缓存 |
| 文派集市 | 始终 `is-soon` 灰卡「即将开放」+ 幽灵「了解文派集市 →」 | — |

## 6. 不做什么（P7 / 研究稿 6.3）

授权代理（拦截商业插件的授权请求）；自定义供应商 / Bridge API Hub；把密钥写进 `/binding` 或 `wpcy_settings`；网络级共享密钥；云桥 Pro 门控与下载限额；`AutoMatcher` 式模糊匹配；用户可见"遥测 / 匿名数据"类文案。

## 7. 待定

- 文派集市 `api_url` 与协议（开放时补）。
- 商城是否用邮箱做服务端校验；密钥能否走 POST body（需与薇晓朵确认，影响 P6 的日志约束强度）。
- 已购产品的到期 / 订阅字段（4.x 提醒功能依赖）。
