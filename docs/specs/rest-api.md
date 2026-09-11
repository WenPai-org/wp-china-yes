# REST API `wpcy/v1`

状态：草案（M0）· 来源：linuxjoy 定稿 §7.5a / §7.1a / §7.1c；2026-09-06 按 [ADR-004](../architecture/adr-004-site-profile-and-scope.md) 更新 `/settings` 字段并新增 `GET /profile/suggest`；同日按 [HTTP Block 并入决定](../dev-plan/decisions/2026-09-06-http-block-merge-and-feature-absorption.md) A 节新增 `/site-blocklist`、`GET /residency/protected`、`POST /residency/test`；同日夜按原型 E 定稿新增 `GET /stats`、`GET /events`、诊断目标界面分组表、浏览器测速允许名单补充（实现任务 M-STATS-1）。

本文列出 4.0 插件对 wp-admin React 应用与小工具宿主暴露的全部端点。apps 合同引用 `docs/specs/apps-manifest-and-bridge.md`，此处不重复字段表。不得在本文新增产品决定；空白处标「待定（M0）」。

命名空间：`wpcy/v1`。基路径：`/wp-json/wpcy/v1`。

## 通用约定

- 所有写请求需 `X-WP-Nonce`（WordPress REST nonce）。
- 错误模型同 apps 规格 §5.5，前缀 `wpcy_`。形状：`{ "code": "wpcy_…", "message": "…", "data": { "status": <http>, "request_id": "…" } }`。
- 响应带 `X-WPCY-Request-Id`。
- 时间 UTC ISO 8601。
- 分页参数 `page` / `per_page`（**仅** `/residency/log`）。默认 `page=1`，`per_page` 默认 20、上限 100。
- 权限未另列时为 `manage_options`。多站点网络设置用 `manage_network_options`。
- schema 校验失败返回 `wpcy_invalid_schema`（HTTP 400）。未知键丢弃并记 warning 日志，不作为错误拒绝整份（与 `docs/specs/config-schema.md` 一致）。
- 不在浏览器端判断套餐。

## 端点

| 方法 | 路径 | 权限 | 说明 |
|---|---|---|---|
| GET / PUT | `/settings` | `manage_options` | 站点设置，schema 校验，返回完整对象（含 `profile`、`admin_assets`、拆分后的 avatar / public_assets.scope） |
| GET / PUT | `/network-settings` | `manage_network_options` | 多站点网络策略，返回完整对象 |
| GET | `/profile/suggest` | `manage_options` | 向导建议场景；不自动切换；不返回 IP 原值 |
| GET | `/diagnostics` | `manage_options` | 最近一次检查结果 |
| POST | `/diagnostics/run` | 同上 | 触发检查，返回结果 |
| GET | `/diagnostics/client-probe` | 同上 | 管理员浏览器测速的最近一次摘要 |
| POST | `/diagnostics/client-probe` | 同上 | 接收浏览器测速结果，只存最近一次摘要 |
| GET | `/residency/ruleset` | 同上 | 当前生效主机表（版本、档位、条目） |
| GET | `/residency/log` | 同上 | B 档记录（主机、数据类别、次数、最近时间；**无正文**） |
| GET | `/residency/protected` | 同上 | L0 / L1 / L2 三层只读视图 |
| POST | `/residency/test` | 同上 | 输入 URL，返回会被哪一层如何处理 |
| GET / PUT | `/site-blocklist` | `manage_network_options` | L2 本站拦截清单；PUT 命中受保护主机返回 400 `wpcy_blocklist_protected_host` |
| GET | `/announcements` | 同上 | 缓存的公告 |
| GET | `/element-hide` | 同上 | 元素隐藏：总开关、规则集版本、issued_at、本月命中次数 |
| POST | `/announcements/{id}/dismiss` | 同上 | 关闭一条公告 |
| GET | `/binding` | 同上 | 绑定状态 |
| POST | `/binding/start` | 同上 | 发起挑战 |
| DELETE | `/binding` | 同上 | 撤销绑定 |
| GET | `/entitlements` | 同上 | 全部权益与配额 |
| GET | `/migration/report` | 同上 | 最近一次 3.x→4.0 迁移报告；无历史 `{ "status": "none" }` |
| GET / POST / DELETE | `/providers`、`/providers/{id}/connect`、`/providers/{id}`、`/providers/{id}/test`、`/providers/{id}/products` | `manage_options` | 供应商层（薇晓朵商城 / 文派集市）。形状、状态与错误码见 [`providers.md`](providers.md)。任何响应不含密钥与完整邮箱 |
| GET | `/stats` | 同上 | 本地计数：最近 N 天（默认 7）每个计数器的按日序列与合计、安装时间。概览"过去 7 天为你处理"与"已运行 N 天"的唯一数据源。见 §`/stats` |
| GET | `/events` | 同上 | 本地事件日志最近 N 条（默认 20，上限 50）：线路切换、更新检查、迁移、场景变更。概览"最近动态"与诊断"记录"的唯一数据源。见 §`/events` |
| POST | `/recovery` | 同上 | `{ "action": "disable_rewrites" \| "disable_modules" \| "exit" }` |
| GET | `/apps` | 同上 | `{ apps, index_status }`，见 apps 规格 §4。`index_status` 含 `unconfigured`（空 source） |
| GET | `/apps/{id}/context` | 同上 + manifest `site:read` | 见 apps 规格 |
| GET | `/apps/{id}/data` | 同上 + `data:read` | 见 apps 规格 |
| GET | `/apps/{id}/data/{key}` | 同上 + `data:read` | 见 apps 规格 |
| PUT | `/apps/{id}/data/{key}` | 同上 + `data:write` | 见 apps 规格 |
| DELETE | `/apps/{id}/data/{key}` | 同上 + `data:delete` | 见 apps 规格 |
| GET | `/apps/{id}/entitlement` | 同上 + `entitlement:read` | 见 apps 规格 |
| POST | `/apps/{id}/go` | 同上 + `go:open` | 见 apps 规格 |

以下端点不在 wp-admin 鉴权模型内，单独列出：

| 方法 | 路径 | 权限 | 说明 |
|---|---|---|---|
| GET | `/binding/challenge` | 公开只读 | 查询参数 `id={challenge_id}`。仅 `pending` 且未过期时返回 `{ "challenge_token": "…" }`。服务端回站拉取用。见 `docs/specs/entitlements.md` |

## 请求与响应摘要

### `/settings`、`/network-settings`

- GET 返回完整 option 对象（`wpcy_settings` / `wpcy_network_settings`），不含 `wpcy_site_identity.binding.credential`。自 `schema_version` 2 起对象含：
  - `profile`：`domestic` \| `crossborder` \| `mixed`
  - `connectivity.wordpress_org`：`auto` \| `off`
  - `connectivity.public_assets`：`{ "items": [...], "scope": "both"|"admin"|"frontend"|"off" }`（不再是字符串数组）
  - `connectivity.avatar`：`{ "admin": <枚举>, "frontend": <枚举> }`（枚举 `cravatar_cn` \| `cravatar_global` \| `off`；**`weavatar` 已移除**，feibisi 2026-09-06：插件内只保留 Cravatar，非文派服务不出现）。**过渡兼容（到 M-UI 替换连接页为止）**：PUT 接受旧单值字符串，服务端展开为 admin/frontend 同值；响应同时含拆分对象与兄弟键 `avatar_admin` / `avatar_frontend`。M-UI 合入后删除该兼容并改回 400。
  - `admin_assets`：`on` \| `off`（4.0 预留，无运行时行为）
  - `connectivity.heartbeat`：`on` \| `off`
  - `connectivity.dashboard_feeds`：`block` \| `allow`
  - `connectivity.icon_photos`：`{ "enabled": "off"|"on"|"admin", "mirrored_base": "", "native_api_base": "" }`（缺省 `enabled=off`、基址空；服务端就绪前关闭。设置页不放用户字段）
  - `diagnostics.client_probe_url`：string，默认 `""`
  - 其余字段同 `docs/specs/config-schema.md`
- PUT body 为完整对象或与 schema 兼容的部分对象；服务端按 `docs/specs/config-schema.md` 校验后写入，响应完整对象。PUT `profile` 且请求标明切换场景时，服务端走 `Profile::apply_defaults()` 重置连通性各项为该场景默认（改前由界面确认；本端点不代做确认对话框）。
- 子站覆盖走 `wpcy_site_overrides`，经 `Config\Repository` 合并；本命名空间不另开 overrides 端点。**待定（M0）**：是否需要独立 `GET/PUT /site-overrides`，由实现方在写 `Rest/` 时与产品负责人确认。

### `/profile/suggest`

向导第一步用。权限同 `/settings`（`manage_options`）。**只建议，不写入、不自动切换。**

查询参数（向导页 JS 采集，不存储原值）：

| 参数 | 类型 | 说明 |
|------|------|------|
| `locale` | string，可选，最长 64 | 浏览器 `Accept-Language` 主标签，如 `zh-CN` |
| `timezone` | string，可选，最长 64 | IANA 时区，如 `Asia/Shanghai` |

未传 `locale` 时可用请求头 `Accept-Language` 的第一项作同等 hint。不接受、不回传 IP。

响应：

```json
{
  "suggestion": "domestic",
  "signals": {
    "server_country": "CN",
    "admin_locale_hint": "zh-CN",
    "admin_tz_hint": "Asia/Shanghai"
  }
}
```

| 字段 | 规则 |
|------|------|
| `suggestion` | `domestic` \| `crossborder` \| `mixed` \| `null`。`null` = 不建议，让用户选 |
| `signals.server_country` | geo 得到的 ISO 3166-1 alpha-2（如 `CN`）；失败为 `null`。**不返回 IP 原值** |
| `signals.admin_locale_hint` | 用于推断的 locale 结论（规范化后的参数或请求头）；未提供为 `null` |
| `signals.admin_tz_hint` | 用于推断的时区结论；未提供为 `null` |

建议规则（决定 D4，不得改）：

- 服务器境外 + 管理员境内 → `crossborder`
- 服务器境内 → `domestic`
- 其它（含 geo 失败）→ `null`
- 本规则不产生 `mixed`（`mixed` 只出现在用户手选）
- 「境内」：`server_country === "CN"`（不含 HK / MO / TW）；管理员境内 = `locale` 匹配 `zh-CN` / `zh_CN`，或 `timezone` 为 `Asia/Shanghai` / `Asia/Chongqing` / `Asia/Urumqi` / `PRC`
- 「境外」：`server_country` 非空且不是 `CN`

geo：走 `api.wenpai.net`。接口路径与应答格式**待 wenpai-net 侧提供**；插件先按 `{ "country": "CN" }` 契约实现并可 mock。geo 失败 → `suggestion` 为 `null`，`server_country` 为 `null`。

错误：权限不足 `wpcy_forbidden`（403），与 `/settings` 相同。geo 失败不是错误。

### `/diagnostics`、`/diagnostics/run`

GET `/diagnostics` 返回最近一次检查；POST `/diagnostics/run` 触发一轮检查并返回同一形状。路由在 M1-07 挂载。结果对象由 `Diagnostics\Checker` 冻结，M1-07 原样返回。

响应 JSON：

```json
{
  "targets": [
    {
      "target": "downloads.wenpai.net",
      "result": "ok",
      "latency_ms": 42,
      "checked_at": "2026-09-04T12:00:00Z",
      "suggestion": null
    }
  ]
}
```

`targets` 项 schema（draft 2020-12）：

```json
{
  "type": "object",
  "additionalProperties": false,
  "required": ["target", "result", "latency_ms", "checked_at", "suggestion"],
  "properties": {
    "target": {
      "type": "string",
      "description": "人读名，如 downloads.wenpai.net / cdnjs.admincdn.com"
    },
    "result": {
      "type": "string",
      "enum": ["ok", "fallback", "down"],
      "description": "界面映射：国内镜像正常 / 已回原始上游 / 不可用"
    },
    "latency_ms": {
      "type": ["integer", "null"],
      "description": "探测耗时（毫秒）；未测得时为 null"
    },
    "checked_at": {
      "type": "string",
      "format": "date-time",
      "description": "UTC ISO 8601"
    },
    "suggestion": {
      "type": ["string", "null"],
      "description": "仅 result 不为 ok 时有值；ok 时必须为 null"
    }
  }
}
```

探测目标：WordPress.org 镜像（`api.wenpai.net`、`downloads.wenpai.net`）、公共库节点（`cdnjs.admincdn.com`、`jsd.admincdn.com`、`googleajax.admincdn.com`、`googlefonts.admincdn.com`）、当前头像线路（`cn.cravatar.com` / `en.cravatar.com`；`connectivity.avatar=off` 时省略）、MotuCloud（`target` 固定为 `motucloud`；`connectivity.icon_photos.enabled=off` 或 `mirrored_base` 为空时省略）。远程失败不得记为 `ok`。

**界面分组（2026-09-06，供概览"线路状态"与 `/events` 的 `{route}` 使用）**。概览不逐个列目标，按下表分组；组状态取成员里**最差**的（`down` > `fallback` > `ok`），组延迟取成员里**最大**的 `latency_ms`，组"最近检查"取最早的 `checked_at`。诊断页仍逐个列目标。

| 组（人读名） | 服务商（品牌原文，决定 D1） | 说明文案 | 成员目标 |
|---|---|---|---|
| WordPress.org 镜像 | WenPai.org | 更新检查与安装包 | `api.wenpai.net`、`downloads.wenpai.net` |
| 公共库源 | adminCDN | Google Fonts、Ajax、jsDelivr、Emoji | `googlefonts.admincdn.com`、`googleajax.admincdn.com`、`jsd.admincdn.com` |
| CDNJS 源 | adminCDN | 备用公共库 | `cdnjs.admincdn.com` |
| Cravatar | Cravatar | 评论头像 · `{host}` | 当前头像线路主机（一个） |
| 图标与图片 | MotuCloud | 核心图标与媒体库图片搜索 | `motucloud`（配置驱动，非固定主机） |

服务商列是界面与事件文案用的常量（`{provider}`），不进 `/diagnostics` 响应；分组与服务商的映射放在一处（`Diagnostics\RouteGroups`），前后端共用同一张表。

`connectivity.wordpress_org=off`（直连）时"WordPress.org 镜像"组不出现在概览线路列表（跨境站默认如此）；`public_assets.scope=off` 时两个公共库组不出现；`avatar` 两侧都 `off` 时 Cravatar 组不出现；`icon_photos.enabled=off` 或基址为空时「图标与图片」组不出现。

### `/diagnostics/client-probe`

管理员从**自己的浏览器**测速（不是服务器出站）。服务端不代发这些 URL。只存最近一次摘要，option 键 `wpcy_diagnostics_client_probe`（`autoload=no`），不进 `wpcy_settings`。

GET 无记录时：

```json
{ "checked_at": null, "probes": [] }
```

GET 有记录 / POST 成功响应：

```json
{
  "checked_at": "2026-09-06T12:00:00Z",
  "probes": [
    { "target": "fonts.googleapis.com", "result": "ok", "latency_ms": 123 },
    { "target": "secure.gravatar.com", "result": "down", "latency_ms": null }
  ]
}
```

POST body：

```json
{
  "probes": [
    { "url": "https://fonts.googleapis.com/css2?family=Roboto:wght@400", "result": "ok", "latency_ms": 123 },
    { "url": "https://secure.gravatar.com/avatar/00000000000000000000000000000000?d=404", "result": "ok", "latency_ms": 80 }
  ]
}
```

| 字段 | 规则 |
|------|------|
| `probes` | 非空数组，最多 8 条 |
| `probes[].url` | HTTPS URL；主机必须在允许名单 |
| `probes[].result` | `ok` \| `down` |
| `probes[].latency_ms` | 正整数或 `null`（`down` 时可为 null） |

允许名单主机：`fonts.googleapis.com`、`secure.gravatar.com`、`www.gravatar.com`、`gravatar.com`；若 `diagnostics.client_probe_url` 为非空 HTTPS URL，其主机一并允许。POST 覆盖写；非法主机或 schema 失败 → `wpcy_invalid_schema` 400，不改存储。权限不足 → `wpcy_forbidden` 403。

`GET /entitlements` 的配额字段**仅服务 / 小工具链使用**；连通性无配额。

### `/residency/ruleset`

返回当前生效 ruleset：`ruleset_version`、`issued_at`、`tiers`（A/B/C 条目）。只读。用户不可编辑。见 `docs/specs/data-residency-ruleset.md`。

### `/residency/log`

B 档记录。每条含：`host`、`data_class`、`count`、`last_seen`。**无正文、无 URL 查询串**。支持 `page` / `per_page`。

### `/residency/protected`

只读三层视图。权限同 `/diagnostics`（`manage_options`）。不接受写入。多站点子站管理员可读、不可改 L2（改走 `/site-blocklist`，需要 `manage_network_options`）。

响应：

```json
{
  "l0": {
    "source": "builtin+signed",
    "hosts": [
      { "host": "wenpai.net", "match": "suffix" },
      { "host": "cravatar.cn", "match": "exact" }
    ]
  },
  "l1": {
    "ruleset_version": 1,
    "tiers": { "A": [], "B": [], "C": [] }
  },
  "l2": {
    "enabled": true,
    "hosts": []
  },
  "noise_block": {
    "enabled": true,
    "hosts": []
  }
}
```

| 字段 | 规则 |
|------|------|
| `l0.hosts` | 硬编码清单 ∪ 已验签增量，去重后的生效清单。不含 `signature` |
| `l0.source` | `builtin` \| `builtin+signed`。验签失败只有内置时为 `builtin` |
| `l1` | 与 `GET /residency/ruleset` 的 `ruleset_version` + `tiers` 相同形状；可省略 `issued_at` |
| `l2` | 当前网络级 `modules.site_blocklist`（`enabled` + `hosts`）。子站只读到网络值 |
| `noise_block.enabled` | `modules.noise_block.enabled` |
| `noise_block.hosts` | 当前生效签名包条目（无则 `[]`）。用户不可编辑 |

不返回完整 URL、不返回请求正文。

### `/residency/test`

输入一条 URL，按运行时同一顺序判定会被哪一层如何处理。不发真实出站请求。权限同 `/diagnostics`（`manage_options`）。

请求：

```json
{ "url": "https://tracking.woocommerce.com/v1" }
```

| 字段 | 规则 |
|------|------|
| `url` | 必填；必须是带 host 的绝对 URL（`http` 或 `https`）。非法 → `wpcy_invalid_schema` 400 |

响应：

```json
{
  "url": "https://tracking.woocommerce.com/v1",
  "host": "tracking.woocommerce.com",
  "layer": "l1",
  "action": "reroute",
  "detail": {
    "tier": "A",
    "match": "exact",
    "enabled_when": "ingest_ready"
  }
}
```

| 字段 | 规则 |
|------|------|
| `layer` | `l0` \| `l1` \| `noise_block` \| `l2` \| `none` |
| `action` | `allow`（L0 放行，或 `layer=none` 默认放行）\| `reroute` \| `record` \| `ignore` \| `block` |
| `detail` | 命中条的只读摘要（host / match / tier）；未命中为 `{}` |

判定顺序与 [`data-residency-ruleset.md`](data-residency-ruleset.md) §10.3 相同。L1 `ignore`（C 档）之后仍可被噪声包或 L2 拦；`layer=none` 且 `action=allow` 表示三层与噪声包都未拦。不把 IP、完整查询串策略以外的字段放进响应；`url` 回显请求值（诊断用途；界面「测一条地址」只展示 host + 层 + 处置）。

### `/site-blocklist`

L2 本站拦截清单。权限 **`manage_network_options`**（单站上拥有该能力的管理员，通常即超级管理员 / 单站管理员经 WordPress 映射；子站 `manage_options` 不够）。GET 读、PUT 写网络级 `modules.site_blocklist`。单站无多站点时：权限仍是 `manage_network_options`；实现把读写落到 `wpcy_settings.modules.site_blocklist`（与网络 option 同一段结构）。

GET 响应：

```json
{
  "enabled": true,
  "hosts": [
    { "host": "telemetry.example.com", "match": "exact", "note": "" }
  ]
}
```

PUT body 与 GET 相同形状。校验：

- `hosts` 超过 20 → `wpcy_invalid_schema` 400
- `match` 不是 `exact` \| `suffix` → `wpcy_invalid_schema` 400
- `host` 含路径、scheme、端口、`*`、正则元字符，或不符合主机名 → `wpcy_invalid_schema` 400
- 任一条命中 L0 受保护主机（硬编码 ∪ 签名增量）→ **400** `wpcy_blocklist_protected_host`，`message` 用词表原文「文派服务不可拦截」。整单不写入
- 权限不足 → `wpcy_forbidden` 403

成功响应完整对象（与 GET 相同）。不提供导入导出专用端点；本站清单随现有设置导出（若 export 已含 `modules`）。

### `/announcements`

返回缓存中尚未关闭、最多 5 条的公告列表。格式见 `docs/specs/announcements.md`。无缓存时返回 `{ "generated_at": null, "items": [] }`，不返回错误。

### `/announcements/{id}/dismiss`

把 `id` 追加进 `wpcy_settings.announcements.dismissed`。未知 id 仍接受（幂等）。

### `/binding`

返回 `{ "status": "unbound"|"pending"|"bound"|"revoked"|"failed", "site_hash": "…"|null, "bound_at": "…"|null }`。不含 `credential`、不含 `challenge_token`。

### `/binding/start`

发起挑战。插件侧再向服务端 `POST {WPCY_SERVICES_API}/v1/site-connections`，并在同一请求内尝试 `confirm()`。确认成功时响应绑定快照 `{ "status": "bound", "site_hash": "…", "bound_at": "…" }`；否则 `{ "status": "pending", "challenge_id": "…", "expires_at": "…" }`，并由一次性 cron（60s，最多 10 次）重试，耗尽后 `failed`。流程见 entitlements 规格。

### `DELETE /binding`

撤销本站绑定，清除加密凭据，状态 `revoked`。

### `/entitlements`

返回服务端权益列表的缓存副本（最多 1h）。形状见 entitlements 规格。服务端不可达时返回最后一次缓存；无缓存返回空数组，不让站点功能失效。

### `/migration/report`

GET 最近一次 `Runner::execute()` 结果。权限同 `/diagnostics`（`manage_options`）。

有迁移历史时返回 `Report::to_array()`（`kept` / `ignored` / `ignored_reasons` / `settings`）加上：

| 字段 | 说明 |
|---|---|
| `migrated_at` | UTC ISO 8601 |
| `source_version` | 3.x 版本；`wp_china_yes` 内无版本字段时为 `3.x` |
| `ignored` | 未映射的 3.x 键列表（与 `to_array().ignored` 相同） |

无迁移历史（option `wpcy_migration_report` 不存在或为空）返回：

```json
{ "status": "none" }
```

报告存 `wpcy_migration_report`，与 `wpcy_migration_backup` 同级，键名不含版本号。

### `/stats`

2026-09-06 按原型 E 定稿新增（[`docs/design/admin-ui-spec.md`](../design/admin-ui-spec.md) v2.0 §3.1 OV-12 / OV-13）。GET，权限 `manage_options`。查询参数 `days`：整数，默认 `7`，允许 `1`–`30`；非法值 → `wpcy_invalid_schema` 400。

数据来自插件**本地**计数器（`Stats\Counters`），不依赖服务端、不出站。按 UTC 自然日分桶，每个站点一份（多站点每子站独立），保留最近 31 天，更早的桶滚动删除。

```json
{
  "installed_at": "2026-07-31T06:12:00Z",
  "days": 7,
  "from": "2026-08-31",
  "to": "2026-09-06",
  "series": {
    "mirror_downloads": [ { "date": "2026-08-31", "value": 4 }, … ],
    "mirror_bytes_saved": [ … ],
    "assets_rewrites_admin": [ … ],
    "assets_rewrites_frontend": [ … ],
    "avatar_rewrites_admin": [ … ],
    "avatar_rewrites_frontend": [ … ],
    "heartbeat_saved": [ … ],
    "dashboard_feeds_blocked": [ … ],
    "outbound_blocked": [ … ],
    "mirror_fallbacks": [ … ]
  },
  "totals": {
    "mirror_downloads": 42,
    "mirror_bytes_saved": 1288490188,
    …
  }
}
```

| 计数器 | 单位 | 何时 +1（或 +N） |
|---|---|---|
| `mirror_downloads` | 次 | 一次更新检查或安装包下载经国内镜像完成（`Connectivity\WordPressOrg` 改写生效且响应 2xx） |
| `mirror_bytes_saved` | 字节 | 同上，累加响应体字节数（`Content-Length` 或实际读取） |
| `assets_rewrites_admin` / `assets_rewrites_frontend` | 次 | 一次输出中 `Connectivity\PublicAssets` 改写了至少一个 URL，按作用域计（一次页面输出记 1，不按 URL 数） |
| `avatar_rewrites_admin` / `avatar_rewrites_frontend` | 次 | `get_avatar_url` 被改写一次，按作用域计 |
| `heartbeat_saved` | 次 | 心跳节流使一次原本会发出的 `admin-ajax` 心跳没有发出（服务端按 `heartbeat_settings` 过滤的间隔差估算：仪表盘关闭 = 每分钟 1 次；编辑器 15s→60s = 每分钟 3 次；只在该页面会话存活期间累计，实现方式见 M-STATS-1 任务书） |
| `dashboard_feeds_blocked` | 次 | 一次仪表盘外部内容请求（新闻 / 活动 / 插件推荐）被 `Connectivity\DashboardFeeds` 拦下 |
| `outbound_blocked` | 次 | `HttpBlock` L2 本站清单或噪声包拦下一次出站请求 |
| `mirror_fallbacks` | 次 | 镜像不可达、回原始上游一次 |

`series` 每个键都是长度 = `days` 的数组，缺桶补 `0`，日期升序。`totals` 是同一区间求和。`installed_at`：首次激活时写入 `wpcy_installed_at`（UTC ISO 8601）；3.x 升级站若无该值，用迁移报告的 `migrated_at`；两者都没有则用本端点第一次被调用的时间写入。**不返回**任何 URL、主机名、IP。

写入策略：请求内累计在内存，`shutdown` 时一次 `update_option( 'wpcy_stats' )`（`autoload=false`）；无变化不写。WP-Cron 请求同样计入。恢复模式下不计数。

### `/events`

GET，权限 `manage_options`。查询参数 `per_page`：默认 `20`，上限 `50`；`type`：可选，按下表过滤。

```json
{
  "events": [
    {
      "id": "01J9…",
      "at": "2026-09-06T06:02:11Z",
      "type": "mirror_fallback",
      "tone": "warn",
      "title": "WordPress.org 镜像不可达，已回原始上游",
      "detail": "每 1 分钟重试，恢复后自动切回"
    }
  ]
}
```

`title` / `detail` 由服务端按下表模板生成（`__()`，用户可见文案只在这一处），界面**不得**自己拼句子；`tone` 只有 `ok` / `warn` / `neutral` 三值。

| `type` | `tone` | `title` 模板 | `detail` 模板 |
|---|---|---|---|
| `first_check` | ok | 首次线路检查完成 | `{ok}` 条线路全部正常 / `{ok}`/`{total}` 条线路正常 |
| `route_recovered` | ok | `{route}` 恢复，已切回 | `{provider}` 中断 `{minutes}` 分钟，期间走原始上游，访客不受影响 |
| `mirror_fallback` | warn | WordPress.org 镜像不可达，已回原始上游 | WenPai.org 镜像暂时不可达，每 1 分钟重试，恢复后自动切回 |
| `route_fallback` | warn | `{route}` 不可达，已回原始上游 | `{provider}` 暂时不可达，恢复后自动切回 |
| `route_down` | warn | `{route}` 不可达 | `{provider}` 暂时不可达，该项已暂停改写，恢复后自动继续 |
| `update_check` | ok / neutral | 完成 WordPress `{version}` 更新检查 | 经国内镜像，耗时 `{seconds}` 秒（ok）/ 直连 WordPress.org，耗时 `{seconds}` 秒（neutral） |
| `migrated` | neutral | 插件更新到 `{version}` | 从 `{source_version}` 迁移 `{kept}` 项设置 |
| `profile_set` | neutral | 已按「`{profile_label}`」配置 | 更新走国内镜像，前端资源与头像走国内节点（domestic）/ 后台资源只在后台加速，更新直连 WordPress.org（crossborder / mixed） |
| `recovery_entered` | warn | 已进入恢复模式 | 全部 URL 改写与模块已停用 |
| `recovery_exited` | ok | 已退出恢复模式 | 设置已恢复 |

`{route}` 取诊断目标的人读名（见 §`/diagnostics` 的分组表，如「CDNJS 源」）。存储：环形缓冲 50 条，`wpcy_events`（`autoload=false`），`id` 为 ULID。恢复模式下仍记录 `recovery_*`，其余事件不产生。

### `/diagnostics/client-probe` 允许名单补充（2026-09-06）

为让"从你的浏览器测速"能对照"原始源 vs 改写目标"，允许名单在原有四个主机之外增加：`googlefonts.admincdn.com`、`cn.cravatar.com`。界面把 `fonts.googleapis.com` 与 `googlefonts.admincdn.com`、`secure.gravatar.com` 与 `cn.cravatar.com` 成对展示。

### `/recovery`

Body：`{ "action": "disable_rewrites" | "disable_modules" | "exit" }`。

| `action` | 行为 |
|---|---|
| `disable_rewrites` | 关闭全部 URL 改写，并置 `recovery_mode = true` |
| `disable_modules` | 停用全部可选模块，并置 `recovery_mode = true` |
| `exit` | `recovery_mode = false`。退出后是否自动恢复此前关闭的改写 / 模块 **待定（M0）**：由产品负责人定；未定前实现不得自行恢复，只清标志 |

恢复页（`?page=wpcy-recovery`）用服务端表单 POST 完成前两个动作，不依赖本端点。本端点供 React 应用使用（含退出恢复模式）。

## 错误码（本命名空间通用）

apps 专用码见 apps 规格 §5.5。此处列出跨端点码：

| code | HTTP | 何时 |
|---|---|---|
| `wpcy_invalid_schema` | 400 | PUT body 不符合 config schema |
| `wpcy_forbidden` | 403 | 能力不足或 nonce 无效 |
| `wpcy_recovery_unknown_action` | 400 | `/recovery` 的 `action` 不是三个枚举值之一 |
| `wpcy_binding_not_pending` | 409 | 公开挑战端点在非 pending / 已过期时被拉 |
| `wpcy_provider_unknown` | 404 | `/providers/{id}` 的 id 不在预置枚举 |
| `wpcy_provider_binding_required` | 403 | 未绑定文派即尝试连接供应商 |
| `wpcy_provider_coming_soon` | 400 | 对 `coming_soon` 供应商发起连接 |
| `wpcy_provider_not_connected` | 400 | 未连接即 `test` |
| `wpcy_provider_unreachable` | 503 | 供应商远端不可达（连接 / 测试时） |
| `wpcy_blocklist_protected_host` | 400 | `PUT /site-blocklist`、`PUT /settings`、`PUT /network-settings` 的某条 host 命中 L0 受保护主机 |
| `wpcy_noise_block_blocked` | — | 噪声包命中；`pre_http_request` 返回该码，不落库 |

其它业务码随模块补进，前缀必须 `wpcy_`。**待定（M0）**：诊断、驻留、绑定失败的完整 code 表由各模块作者在实现前补进本文。`wpcy_blocklist_protected_host` 已定，message 必须是「文派服务不可拦截」。
