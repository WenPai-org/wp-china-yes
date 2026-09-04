# 小工具 Manifest 与桥接合同

状态：草案（M0）· 来源：linuxjoy 定稿 §7.5a / §7.1a / §7.1c

本文冻结 4.0 小工具容器的 manifest、索引、`postMessage` 信封、REST `wpcy/v1/apps/*` 与错误码。实现见 ADR-003。不得在本文新增产品决定；空白处标「待定（M0）」。

## 1. Manifest（单个工具）

```json
{
  "schema_version": 1,
  "id": "motusnap",
  "name": { "zh_CN": "墨图截图", "en_US": "MotuSnap" },
  "description": { "zh_CN": "…", "en_US": "…" },
  "icon": "https://apps.wpcy.com/motusnap/icon.svg",
  "entry_url": "https://apps.wpcy.com/motusnap/",
  "version": "1.2.0",
  "min_plugin_version": "4.0.0",
  "tier": "limited-free",
  "entitlement": "wpcy-leaf-motusnap-100",
  "permissions": ["site:read", "data:read", "data:write", "entitlement:read", "go:open"],
  "go_service": "motusnap",
  "issued_at": "2026-09-03T12:00:00Z",
  "signature": "base64(ed25519 over canonical JSON without signature field)"
}
```

### 1.1 字段

| 字段 | 类型 | 规则 |
|---|---|---|
| `schema_version` | int | 当前为 `1` |
| `id` | string | 工具稳定标识，与 REST `{id}`、数据表 `app_id` 相同 |
| `name` / `description` | object | 至少含 `zh_CN`；`en_US` 可选 |
| `icon` | string | HTTPS URL，主机须在白名单后缀内 |
| `entry_url` | string | HTTPS URL；iframe `src`；origin 校验基准 |
| `version` | string | 工具自身 semver，发插件版本不随之变化 |
| `min_plugin_version` | string | 低于此版本的插件不显示该工具 |
| `tier` | string | ∈ `free` \| `limited-free` \| `paid` |
| `entitlement` | string \| null | `free` 时必须为 `null`；其余为权益 id |
| `permissions` | string[] | 见 §1.2；未列出的权限即拒 |
| `go_service` | string | `/go/{go_service}` 的 slug |
| `issued_at` | string | UTC ISO 8601 |
| `signature` | string | 对去掉 `signature` 字段后的规范化 JSON 做 Ed25519 分离签名，再 Base64 |

验签：`sodium_crypto_sign_verify_detached`。公钥随插件发布。验签失败则该工具不显示。

### 1.2 `permissions` 枚举

只有下列六项。写清每个权限对应的桥接消息与 REST。未声明即拒。

| permission | 含义 | 桥接 `type` | REST |
|---|---|---|---|
| `site:read` | 站点上下文 | `context.get` | `GET /apps/{id}/context` |
| `data:read` | 读本工具数据 | `data.get`、`data.list` | `GET /apps/{id}/data`、`GET /apps/{id}/data/{key}` |
| `data:write` | 写本工具数据 | `data.set` | `PUT /apps/{id}/data/{key}` |
| `data:delete` | 删本工具数据 | `data.delete` | `DELETE /apps/{id}/data/{key}` |
| `entitlement:read` | 读本工具权益摘要 | `entitlement.get` | `GET /apps/{id}/entitlement` |
| `go:open` | 打开商业导流 | `go.open` | `POST /apps/{id}/go` |

`ready`、`resize` 不要求 permission。宿主发出的 `init` / `result` / `error` 不要求 permission。

### 1.3 规范化 JSON（验签双方必须一致）

对去掉 `signature` 字段后的对象：

1. 键按字典序递归排序；
2. UTF-8 编码；
3. 无多余空白（RFC 8259 最短序列化：对象与数组无空格、无尾随换行）；
4. 对该字节序列做 Ed25519 分离签名，结果 Base64 写入 `signature`。

## 2. 索引

`GET https://apps.wpcy.com/index.json` 返回：

```json
{
  "schema_version": 1,
  "generated_at": "2026-09-03T12:00:00Z",
  "apps": [ "<manifest>…" ],
  "signature": "…"
}
```

- 插件每日拉取一次，缓存 transient `wpcy_apps_index`（24h）。
- 整份索引验签失败：整份丢弃并保留上一份有效缓存。
- 单个 manifest 验签失败：该工具不显示，其余工具不受影响。
- 索引签名覆盖去掉 `signature` 后的规范化 JSON（规则同 §1.3）。

来源白名单：初始主机 `apps.wpcy.com`。签名索引可扩展 `entry_url` 主机，但必须 HTTPS，且后缀 ∈ `.wpcy.com` / `.wenpai.net`。

## 3. postMessage 信封

```json
{
  "wpcy": 1,
  "type": "data.get",
  "request_id": "uuid",
  "payload": { "key": "settings" }
}
```

- `wpcy` 必须为 `1`，否则丢弃。
- `request_id`：工具发出的请求为 UUID；宿主 `result` / `error` 原样回传。宿主主动 `init` 可无 `request_id`。
- 非本信封结构的消息丢弃，不回 `error`。

### 3.1 origin 校验

- 宿主只接受 `event.origin === new URL(manifest.entry_url).origin`。
- 工具只接受 `event.origin === 宿主 origin`（宿主在 `init` 里告知；工具页面也可用 `document.referrer` 校验）。
- 不匹配则丢弃，错误码 `wpcy_apps_origin_mismatch`（仅当消息已通过信封校验、能回 `error` 时才回；跨 origin 且无法确认来源时静默丢弃）。
- **宿主 origin 在启动时快照进闭包**（`const HOST_ORIGIN = window.location.origin`），之后不再读可能被改写的 `location`。
- **宿主校验 `event.source === iframe.contentWindow`**：同 origin 的其它窗口、同页第二个工具实例的消息一律丢弃。
- 宿主页自身可能运行在别的壳（如 OpenStation 的 chromeless iframe）内：宿主**不向 `window.parent` / `window.top` 发送任何桥接消息**，也不把来自 `window.parent` 的消息当作工具消息处理。

（以上三条借鉴 WordPress/openstation `src/window/iframe-bridge.ts` 的做法，GPL-2.0-or-later，按思想重写不拷代码；分析见 linuxjoy `docs/research/2026-09-04-openstation-analysis.md` E.2。）

### 3.1a 超时与重试

- 宿主转发 REST 超时 10s；超时回 `error`，`code = wpcy_apps_host_timeout`（HTTP 504 语义）。
- `data.set` / `data.delete` / `go.open` **不自动重试**（非幂等或有副作用）；`context.get` / `data.get` / `data.list` / `entitlement.get` 由工具自行决定是否重发。
- `ready` 之前工具发出的其它消息宿主丢弃；宿主在收到 `ready` 后立即发 `init`，工具应在 `init` 后再开始请求。

### 3.2 消息表

| 方向 | `type` | `payload` 字段 | permission | 对应 REST | 错误码 |
|---|---|---|---|---|---|
| 工具 → 宿主 | `ready` | （空） | 无 | 无；宿主随即发 `init` | — |
| 工具 → 宿主 | `context.get` | （空） | `site:read` | `GET /apps/{id}/context` | `wpcy_apps_forbidden_permission`、`wpcy_apps_unknown_app` |
| 工具 → 宿主 | `data.get` | `key` | `data:read` | `GET /apps/{id}/data/{key}` | `wpcy_apps_forbidden_permission`、`wpcy_apps_key_invalid`、`wpcy_apps_unknown_app` |
| 工具 → 宿主 | `data.set` | `key`、`value`（JSON） | `data:write` | `PUT /apps/{id}/data/{key}` | 同上 + `wpcy_apps_payload_too_large` |
| 工具 → 宿主 | `data.delete` | `key` | `data:delete` | `DELETE /apps/{id}/data/{key}` | `wpcy_apps_forbidden_permission`、`wpcy_apps_key_invalid` |
| 工具 → 宿主 | `data.list` | （空） | `data:read` | `GET /apps/{id}/data` | `wpcy_apps_forbidden_permission` |
| 工具 → 宿主 | `entitlement.get` | （空） | `entitlement:read` | `GET /apps/{id}/entitlement` | `wpcy_apps_forbidden_permission`、`wpcy_apps_entitlement_required`、`wpcy_apps_quota_exceeded` |
| 工具 → 宿主 | `go.open` | （空，或 `utm` 对象，宿主可忽略自建 UTM） | `go:open` | `POST /apps/{id}/go` | `wpcy_apps_forbidden_permission` |
| 工具 → 宿主 | `resize` | `height`（px，整数） | 无 | 无；只允许高度，上限 4000px，超出截断为 4000 | — |
| 宿主 → 工具 | `init` | `app_id`、`locale`、`plugin_version`、`host_origin`、`context` 摘要 | 无 | 无 | — |
| 宿主 → 工具 | `result` | 与请求对应的业务对象；外层另带 `request_id` | 无 | — | — |
| 宿主 → 工具 | `error` | 外层 `request_id`、`code`、`message` | 无 | — | — |

`init.payload.context` 摘要字段与 `GET /apps/{id}/context` 相同，但仅在 manifest 含 `site:read` 时填充；否则为空对象。

工具永远拿不到 REST nonce。宿主页持有 nonce，把桥接请求转发为 REST。

`resize` 只允许高度，上限 4000px。宽度由容器决定。宿主对 `resize` 做 **200ms 防抖**（取窗口内最后一个值）；工具侧建议用 `ResizeObserver` 而不是定时器。

## 4. REST `wpcy/v1/apps/*`

WordPress 能力一律 `manage_options`。此外按 manifest `permissions[]` 裁剪；未声明即 `wpcy_apps_forbidden_permission`。

| 方法 | 路径 | 权限 | 说明 |
|---|---|---|---|
| GET | `/apps` | `manage_options` | 已验签的 manifest 列表（含每个工具的权益状态摘要） |
| GET | `/apps/{id}/context` | 同上 + `site:read` | `site_url`、`wp_version`、`locale`、`is_multisite`、`user_can`（布尔映射）、`active_plugins`（slug 列表） |
| GET | `/apps/{id}/data` | + `data:read` | key 列表 |
| GET | `/apps/{id}/data/{key}` | + `data:read` | 单条 |
| PUT | `/apps/{id}/data/{key}` | + `data:write` | body JSON ≤ 64KB |
| DELETE | `/apps/{id}/data/{key}` | + `data:delete` | |
| GET | `/apps/{id}/entitlement` | + `entitlement:read` | `{ status, quota:{limit,used,period,resets_at} }` |
| POST | `/apps/{id}/go` | + `go:open` | 返回 `{ url: "https://wpcy.com/go/{go_service}?utm_source=wpcy-plugin&utm_medium=app&utm_campaign={id}" }`，宿主再 `window.open` |

`{key}` 规则：`^[a-z0-9_.-]{1,64}$`。不匹配返回 `wpcy_apps_key_invalid`。

`user_can` 为布尔映射，键名与能力名相同（至少含 `manage_options`）。不返回角色列表、不返回邮箱。

`GET /apps/{id}/entitlement` 的 `status` ∈ `active` \| `exhausted` \| `expired`，语义见 `docs/specs/entitlements.md`。无对应权益且 `tier` 为 `paid` 时返回 `wpcy_apps_entitlement_required`。

每个工具的数据 key 数量与总大小上限建议 500 key / 5MB，**待定（M0）**：由产品负责人与实现方在 M0 定死。单条 PUT body 上限已定为 64KB。

### 4.1 数据表

```sql
CREATE TABLE {prefix}wpcy_app_data (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  app_id VARCHAR(64) NOT NULL,
  data_key VARCHAR(64) NOT NULL,
  data_json LONGTEXT NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY app_key (app_id, data_key)
);
```

多站点：表按子站前缀。工具 A 读不到工具 B（`app_id` 隔离）。卸载插件时用户选择是否删除本表。

## 5. 错误码

统一形状：

```json
{
  "code": "wpcy_apps_forbidden_permission",
  "message": "…",
  "data": { "status": 403, "request_id": "…" }
}
```

`message` 给人读，插件与工具不得靠解析中文消息分支。桥接 `error` 的 `code` 与 REST `code` 相同。

| code | HTTP | 何时 |
|---|---|---|
| `wpcy_apps_unknown_app` | 404 | `{id}` 不在已验签列表 |
| `wpcy_apps_signature_invalid` | 400 | 单工具或索引验签失败（索引级：整份丢弃，本码记日志） |
| `wpcy_apps_forbidden_permission` | 403 | manifest 未声明所需 permission |
| `wpcy_apps_entitlement_required` | 403 | 付费工具无有效权益 |
| `wpcy_apps_quota_exceeded` | 403 | 权益 `exhausted` 且请求需要配额 |
| `wpcy_apps_key_invalid` | 400 | `{key}` 不符 `^[a-z0-9_.-]{1,64}$` |
| `wpcy_apps_payload_too_large` | 413 | PUT body > 64KB，或超过工具总大小上限 |
| `wpcy_apps_origin_mismatch` | 403 | `event.origin` 与 manifest `entry_url` origin 不一致 |
| `wpcy_apps_host_timeout` | 504 | 宿主转发 REST 超过 10s 未返回 |

通用约定（与 `docs/specs/rest-api.md` 相同）：写请求需 `X-WP-Nonce`；响应带 `X-WPCY-Request-Id`；时间 UTC ISO 8601。
