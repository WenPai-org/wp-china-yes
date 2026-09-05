# 站点绑定与权益

状态：草案（M0）· 来源：linuxjoy 定稿 §7.5a / §7.1a / §7.1c

本文冻结自动匿名绑定、权益查询、配额与降级语义。客户端不计算额度、不解析价格、不存账号密码。不得在本文新增产品决定；空白处标「待定（M0）」。

服务端基座 URL 用常量 `WPCY_SERVICES_API`（默认值 **待定（M0）：license.wenpai.net 或 MotuSnap 服务端**，由授权系统归属拍板后写入常量默认值）。

## 1. 绑定流程（自动匿名，无账号）

同站重装 / 换管理员不叠额；换域名重新挑战。`site_uuid` 存 `wpcy_site_identity`，首次启动生成后稳定。

① 插件 `POST {API}/v1/site-connections`

请求：

```json
{
  "site_url": "https://example.com",
  "site_uuid": "…",
  "plugin_version": "4.0.0"
}
```

响应：

```json
{
  "challenge_id": "…",
  "challenge_token": "…",
  "expires_at": "2026-09-03T12:30:00Z"
}
```

② 插件把 token 存 `wpcy_site_identity.binding`（status=`pending`，写入 `challenge_id`），并在

`GET {site_url}/wp-json/wpcy/v1/binding/challenge?id={challenge_id}`

**公开只读**返回 `{ "challenge_token": "…" }`。仅在 `pending` 状态且未过期时返回；否则 404 / `wpcy_binding_not_pending`。

③ 服务端回站拉取校验，签发 `{ site_hash, credential }`。

④ 插件 `POST {API}/v1/site-connections/{challenge_id}/confirm` 取回并加密存储（`wp_salt('auth')` 派生密钥 + sodium secretbox；不进导出、Site Health、日志）。

⑤ 状态 `bound`。写入 `site_hash`、`bound_at`；清空 `challenge_id`。

撤销：插件 `DELETE /wp-json/wpcy/v1/binding`，并通知服务端（端点路径 **待定（M0）**：是否复用 4.x CDN 合同的 `DELETE /v1/sites/{site_id}/connection`，由授权系统定）。本地 status=`revoked`，清除凭据。

写请求幂等键 `Idempotency-Key`；错误 code 稳定；时间 UTC ISO 8601。

## 2. 权益查询

`GET {API}/v1/sites/{site_hash}/entitlements`

响应：

```json
{
  "entitlements": [
    {
      "id": "wpcy-leaf-motusnap-100",
      "service": "motusnap",
      "tier": "limited-free",
      "status": "active",
      "quota": {
        "limit": 100,
        "used": 12,
        "period": "month",
        "resets_at": "2026-10-01T00:00:00Z"
      }
    }
  ]
}
```

- `status` ∈ `active` \| `exhausted` \| `expired`。
- 缓存 1h（transient）。
- 客户端不计算额度、不解析价格、不存账号密码。插件里不出现任何写死的额度数字；MotuSnap「每月 100 次」是服务端事实，客户端只展示服务端返回的 `quota`。

认证方式（如何把 `credential` 放到请求上）**待定（M0）**：由授权系统给出（Authorization 头或签名查询）。插件只保存加密凭据并按合同附带。

## 3. 降级语义

| 状态 | 行为 |
|---|---|
| `active` | 正常 |
| `exhausted` | 相关模块切回原始上游 / 工具只读 |
| `expired` | 同 exhausted + 显示「获取」（走 `https://wpcy.com/go/{service}`） |
| 服务端不可达 | 沿用最后一次缓存至多 72h，超时按基线层处理 |
| 任何状态 | **都不得让站点功能失效** |

基线层（元数据镜像与更新检查、数据驻留、诊断、Cravatar 基础线路、广告拦截规则、后台本地设置）不需绑定、不受权益状态影响。

受限免费层配额用尽 → 降级回原始上游（慢但可用），永不让站点坏。

不在浏览器端判断套餐。React 应用对 entitlement 只读展示。

## 4. MotuSnap（第一条真实权益）

- 权益 id：`wpcy-leaf-motusnap-100`
- 每月 100 次，只截已绑定站点自己的页面。
- 无权益或 `expired`：文派服务页显示介绍 +「获取」→ `/go/motusnap`。
- `exhausted`：工具只读，数据仍在 `{prefix}wpcy_app_data`。

额度数字不写进插件源码；以本条服务端返回为准。

## 5. 插件侧 REST（转发）

见 `docs/specs/rest-api.md`：`GET /binding`、`POST /binding/start`、`DELETE /binding`、`GET /binding/challenge`、`GET /entitlements`。

## 6. 错误码

服务端错误 code 必须稳定，插件按 code 分支、不解析中文 `message`。下列为插件侧已冻结的映射；服务端若用不同字符串，适配层译成这些码。

| code | 何时 |
|---|---|
| `wpcy_binding_not_pending` | 公开挑战端点在非 pending / 已过期时被拉 |
| `wpcy_apps_entitlement_required` | 付费工具无有效权益（apps 规格） |
| `wpcy_apps_quota_exceeded` | 权益 exhausted 且请求需要配额（apps 规格） |

服务端自身的失败码表 **待定（M0）**：由授权系统给出后，在适配层映射为 `wpcy_` 前缀。
