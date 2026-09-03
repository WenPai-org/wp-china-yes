# REST API `wpcy/v1`

状态：草案（M0）· 来源：linuxjoy 定稿 §7.5a / §7.1a / §7.1c

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
| GET / PUT | `/settings` | `manage_options` | 站点设置，schema 校验，返回完整对象 |
| GET / PUT | `/network-settings` | `manage_network_options` | 多站点网络策略，返回完整对象 |
| GET | `/diagnostics` | `manage_options` | 最近一次检查结果 |
| POST | `/diagnostics/run` | 同上 | 触发检查，返回结果 |
| GET | `/residency/ruleset` | 同上 | 当前生效主机表（版本、档位、条目） |
| GET | `/residency/log` | 同上 | B 档记录（主机、数据类别、次数、最近时间；**无正文**） |
| GET | `/announcements` | 同上 | 缓存的公告 |
| POST | `/announcements/{id}/dismiss` | 同上 | 关闭一条公告 |
| GET | `/binding` | 同上 | 绑定状态 |
| POST | `/binding/start` | 同上 | 发起挑战 |
| DELETE | `/binding` | 同上 | 撤销绑定 |
| GET | `/entitlements` | 同上 | 全部权益与配额 |
| POST | `/recovery` | 同上 | `{ "action": "disable_rewrites" \| "disable_modules" \| "exit" }` |
| GET | `/apps` | 同上 | 见 apps 规格 |
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

- GET 返回完整 option 对象（`wpcy_settings` / `wpcy_network_settings`），不含 `wpcy_site_identity.binding.credential`。
- PUT body 为完整对象或与 schema 兼容的部分对象；服务端按 `docs/specs/config-schema.md` 校验后写入，响应完整对象。
- 子站覆盖走 `wpcy_site_overrides`，经 `Config\Repository` 合并；本命名空间不另开 overrides 端点。**待定（M0）**：是否需要独立 `GET/PUT /site-overrides`，由实现方在写 `Rest/` 时与产品负责人确认。

### `/diagnostics`、`/diagnostics/run`

- 结果对象字段（目标、结果、延迟、最近检查时间、修复建议）与诊断模块合同一致。**待定（M0）**：结果 JSON 的逐字段 schema 由 `Diagnostics` 模块作者在 M0 补进本文或独立附件。

### `/residency/ruleset`

返回当前生效 ruleset：`ruleset_version`、`issued_at`、`tiers`（A/B/C 条目）。只读。用户不可编辑。见 `docs/specs/data-residency-ruleset.md`。

### `/residency/log`

B 档记录。每条含：`host`、`data_class`、`count`、`last_seen`。**无正文、无 URL 查询串**。支持 `page` / `per_page`。

### `/announcements`

返回缓存中尚未关闭、最多 5 条的公告列表。格式见 `docs/specs/announcements.md`。无缓存时返回 `{ "generated_at": null, "items": [] }`，不返回错误。

### `/announcements/{id}/dismiss`

把 `id` 追加进 `wpcy_settings.announcements.dismissed`。未知 id 仍接受（幂等）。

### `/binding`

返回 `{ "status": "unbound"|"pending"|"bound"|"revoked", "site_hash": "…"|null, "bound_at": "…"|null }`。不含 `credential`、不含 `challenge_token`。

### `/binding/start`

发起挑战。插件侧再向服务端 `POST {WPCY_SERVICES_API}/v1/site-connections`。响应 `{ "status": "pending", "challenge_id": "…", "expires_at": "…" }`。流程见 entitlements 规格。

### `DELETE /binding`

撤销本站绑定，清除加密凭据，状态 `revoked`。

### `/entitlements`

返回服务端权益列表的缓存副本（最多 1h）。形状见 entitlements 规格。服务端不可达时返回最后一次缓存；无缓存返回空数组，不让站点功能失效。

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

其它业务码随模块补进，前缀必须 `wpcy_`。**待定（M0）**：诊断、驻留、绑定失败的完整 code 表由各模块作者在实现前补进本文。
