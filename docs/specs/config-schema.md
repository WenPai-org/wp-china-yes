# 配置 Schema

状态：草案（M0）· 来源：linuxjoy 定稿 §7.5a / §7.1a / §7.1c；2026-09-06 按 [ADR-004](../architecture/adr-004-site-profile-and-scope.md) 将 `schema_version` 升至 2（`profile`、作用域、`admin_assets` 预留）；同日按 [HTTP Block 并入决定](../dev-plan/decisions/2026-09-06-http-block-merge-and-feature-absorption.md) A 节增加 `modules.site_blocklist`、`modules.noise_block.enabled`（不升 `schema_version`，缺省键读时填默认）。

本文给出四个（加迁移备份共五个）option 的 JSON Schema（draft 2020-12）与读写规则。键名稳定不带版本；演进靠结构内 `schema_version` 与迁移器。不得在本文新增产品决定；空白处标「待定（M0）」。

## 规则

- 读写只经 `Config\Repository`。业务模块不得直接 `get_option()` / `update_option()`。
- 未知键丢弃并记 warning 日志。
- `schema_version` 升级：3.x → 4.0 由 `Migration\Runner` 直接写出当前版本（幂等、可 dry-run）；已有 4.0 option 从低版本升到当前版本由 `Config\Repository` 读取时按步执行升级函数并写回（幂等）。步函数见下节。
- 每个字段有默认值（见各节 `default`）。JSON Schema 的 `default` 是 `profile=domestic` 的默认；其它场景的默认组合见「默认矩阵（D2）」，由 `Profile::apply_defaults()` 写入，不把三套默认写进 JSON Schema。
- 凭据不进导出、Site Health、日志。

## schema_version 升级

当前 `wpcy_settings` / `wpcy_network_settings` / `wpcy_site_overrides` 的 `schema_version` **const 2**（`wpcy_site_identity` 与 `wpcy_migration_backup` 仍为 1，本任务不升）。

### `upgrade_1_to_2`（4.0 v1 option → v2）

输入：`schema_version === 1` 的 settings 对象（`public_assets` 为字符串数组，`avatar` 为单字符串，无 `profile` / `admin_assets`）。输出：`schema_version === 2` 的对象。幂等：对已是 v2 的对象原样返回。

| v1 | v2 | 规则 |
|----|----|------|
| （无 `profile`） | `profile` | `"domestic"`（已有 4.0 安装未选过场景；升级站同此，概览提示确认） |
| `connectivity.public_assets` 数组 | `{ "items": <原数组>, "scope": "both" }` | `scope` 用 domestic 默认 `both`；`items` 保持用户已选，不回填五项 |
| `connectivity.avatar` 字符串 | `{ "admin": <原值>, "frontend": <原值> }` | 旧单值 → 两个值都等于它 |
| （无 `admin_assets`） | `admin_assets` | `"off"`（domestic 默认；3.x `admin` token 的意图由 3.x→4.0 映射写入，不走本函数） |
| （无 `connectivity.heartbeat`） | `connectivity.heartbeat` | `"off"`（domestic 默认） |
| （无 `connectivity.dashboard_feeds`） | `connectivity.dashboard_feeds` | `"allow"`（domestic 默认） |
| （无 `diagnostics.client_probe_url`） | `diagnostics.client_probe_url` | `""` |
| `schema_version` `1` | `2` | |

3.x → 4.0 映射器直接写出 v2，不先写 v1 再升级。3.x `admin` token → `admin_assets=on` 的规则见「3.x → 4.0 映射（D3）」与 [ADR-004](../architecture/adr-004-site-profile-and-scope.md) D3。

`modules.site_blocklist` / `modules.noise_block` 是 v2 上的缺省键：读取时若缺失则填默认（`enabled=true`、`hosts=[]`），**不**升 `schema_version`。无 `upgrade_2_to_3`。

## 1. `wpcy_settings`（站点，`autoload=yes`）

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "$id": "https://wpcy.com/schema/wpcy_settings.json",
  "type": "object",
  "additionalProperties": false,
  "required": [
    "schema_version",
    "profile",
    "connectivity",
    "modules",
    "diagnostics",
    "data_residency",
    "announcements",
    "apps",
    "recovery_mode",
    "admin_assets"
  ],
  "properties": {
    "schema_version": { "type": "integer", "const": 2, "default": 2 },
    "profile": {
      "type": "string",
      "enum": ["domestic", "crossborder", "mixed"],
      "default": "domestic"
    },
    "connectivity": {
      "type": "object",
      "additionalProperties": false,
      "required": ["wordpress_org", "public_assets", "avatar", "heartbeat", "dashboard_feeds"],
      "properties": {
        "wordpress_org": {
          "type": "string",
          "enum": ["auto", "off"],
          "default": "auto"
        },
        "public_assets": {
          "type": "object",
          "additionalProperties": false,
          "required": ["items", "scope"],
          "properties": {
            "items": {
              "type": "array",
              "uniqueItems": true,
              "items": {
                "type": "string",
                "enum": ["google_fonts", "google_ajax", "cdnjs", "jsdelivr", "emoji"]
              },
              "default": ["google_fonts", "google_ajax", "cdnjs", "jsdelivr", "emoji"]
            },
            "scope": {
              "type": "string",
              "enum": ["both", "admin", "frontend", "off"],
              "default": "both"
            }
          }
        },
        "avatar": {
          "type": "object",
          "additionalProperties": false,
          "required": ["admin", "frontend"],
          "properties": {
            "admin": {
              "type": "string",
              "enum": ["cravatar_cn", "cravatar_global", "weavatar", "off"],
              "default": "cravatar_cn"
            },
            "frontend": {
              "type": "string",
              "enum": ["cravatar_cn", "cravatar_global", "weavatar", "off"],
              "default": "cravatar_cn"
            }
          }
        },
        "heartbeat": {
          "type": "string",
          "enum": ["on", "off"],
          "default": "off"
        },
        "dashboard_feeds": {
          "type": "string",
          "enum": ["block", "allow"],
          "default": "allow"
        }
      }
    },
    "modules": {
      "type": "object",
      "additionalProperties": false,
      "required": ["notice_control", "windfonts"],
      "properties": {
        "notice_control": { "type": "boolean", "default": true },
        "windfonts": { "type": "boolean", "default": false },
        "site_blocklist": {
          "type": "object",
          "additionalProperties": false,
          "required": ["enabled", "hosts"],
          "properties": {
            "enabled": { "type": "boolean", "default": true },
            "hosts": {
              "type": "array",
              "maxItems": 20,
              "default": [],
              "items": {
                "type": "object",
                "additionalProperties": false,
                "required": ["host", "match"],
                "properties": {
                  "host": {
                    "type": "string",
                    "minLength": 1,
                    "maxLength": 253,
                    "pattern": "^[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?(\\.[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*$"
                  },
                  "match": { "type": "string", "enum": ["exact", "suffix"], "default": "exact" },
                  "note": { "type": "string", "maxLength": 200, "default": "" }
                }
              }
            }
          }
        },
        "noise_block": {
          "type": "object",
          "additionalProperties": false,
          "required": ["enabled"],
          "properties": {
            "enabled": { "type": "boolean", "default": true }
          }
        }
      }
    },
    "integrations": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "windfonts": {
          "type": "object",
          "additionalProperties": false,
          "properties": {
            "fonts": {
              "type": "array",
              "maxItems": 20,
              "items": {
                "type": "object",
                "additionalProperties": false,
                "required": ["family", "selector"],
                "properties": {
                  "family":   { "type": "string", "pattern": "^[a-z0-9-]{1,64}$" },
                  "subset":   { "type": "string", "enum": ["full", "zh", "zh-common", "en"], "default": "full" },
                  "selector": { "type": "string", "maxLength": 200 },
                  "enable":   { "type": "boolean", "default": true }
                }
              },
              "default": []
            }
          }
        }
      }
    },
    "diagnostics": {
      "type": "object",
      "additionalProperties": false,
      "required": ["scheduled_checks"],
      "properties": {
        "scheduled_checks": { "type": "boolean", "default": true },
        "client_probe_url": {
          "type": "string",
          "maxLength": 2048,
          "default": ""
        }
      }
    },
    "data_residency": {
      "type": "object",
      "additionalProperties": false,
      "required": ["ruleset_version"],
      "properties": {
        "ruleset_version": { "type": "integer", "minimum": 1, "default": 1 }
      }
    },
    "announcements": {
      "type": "object",
      "additionalProperties": false,
      "required": ["dismissed"],
      "properties": {
        "dismissed": {
          "type": "array",
          "items": { "type": "string", "minLength": 1, "maxLength": 128 },
          "maxItems": 100,
          "default": []
        }
      }
    },
    "apps": {
      "type": "object",
      "additionalProperties": false,
      "required": ["disabled"],
      "properties": {
        "disabled": {
          "type": "array",
          "items": { "type": "string", "minLength": 1, "maxLength": 64 },
          "default": []
        }
      }
    },
    "recovery_mode": { "type": "boolean", "default": false },
    "admin_assets": {
      "type": "string",
      "enum": ["on", "off"],
      "default": "off"
    }
  }
}
```

`connectivity.public_assets.items` 必须是 `[google_fonts, google_ajax, cdnjs, jsdelivr, emoji]` 的子集。未知字符串丢弃。

`connectivity.public_assets.scope` 枚举 `both` / `admin` / `frontend` / `off`。判定当前请求是 admin 还是 frontend：`is_admin()`（含 `admin-ajax` / REST 带 `X-WP-Nonce` 的后台请求视为 admin；前台 REST 视为 frontend；WP-CLI 视为 admin；WP-Cron 视为 frontend）。cron / WP-CLI（统筹拍板原文）：`WP-CLI` 视为 `admin`；`WP-Cron` 视为 `frontend`（保守：不做任何仅后台的改写；服务器侧取 .org 的行为不受作用域影响）。实现为共享 `Connectivity\Scope::current()`，见 [ADR-004](../architecture/adr-004-site-profile-and-scope.md) 与 [`docs/dev-plan/tasks/M-SCOPE-1.md`](../dev-plan/tasks/M-SCOPE-1.md)。`wordpress_org` 是服务器侧行为，无 admin/frontend 之分，取值仍是 `auto` / `off`。

连通性全部免费、无配额。本文与其它规格里若出现配额 / 降级字段，**仅服务 / 小工具链使用**（`Services/Entitlements`、小工具），不作用于 `wordpress_org` / `public_assets` / `avatar` / `admin_assets` / `heartbeat` / `dashboard_feeds`。

`connectivity.avatar` 是两个独立值，替代 v1 单值。旧单值 → `admin` 与 `frontend` 都等于它。`weavatar` 仍在枚举内（M0 已关闭的 3.x 映射）。

`admin_assets`：4.0 **预留**，枚举 `on` \| `off`，默认按场景（见 D2 矩阵）。**无运行时行为**——4.0 不做任何后台静态资源改写。界面显示「即将提供」；从 3.x 迁来且值为 `on` 时迁移报告写「后台加速：已保留设置，4.1 起生效」。不规划商业化。

`connectivity.heartbeat`：枚举 `on` \| `off`。`on` = 仪表盘关闭 Heartbeat，编辑器 `heartbeat_settings.interval = 60`。domestic 默认 `off`；`crossborder` / `mixed` 默认 `on`。钩子与 filter 名见 M-SCOPE-1。

`connectivity.dashboard_feeds`：枚举 `block` \| `allow`。`block` = 去掉 WP 新闻/事件 widget，并短路核心 dashboard feed 请求；支付/物流不在本项范围内（主机表 C 档，不挡）。domestic 默认 `allow`；`crossborder` / `mixed` 默认 `block`。

`diagnostics.client_probe_url`：可选 HTTPS 探针 URL，默认 `""`。空则浏览器测速只打允许名单内的固定目标（`fonts.googleapis.com`、Gravatar）。非空时其主机加入 POST `/diagnostics/client-probe` 允许名单。不在 `PUT /settings` 以外的路径写入。

`profile` 枚举、默认、多站点覆盖：

| 值 | 名称 | 定义 |
|----|------|------|
| `domestic` | 国内站 | 服务器、访客、管理员都在中国大陆 |
| `crossborder` | 跨境 / 外贸站 | 服务器与访客在海外，管理员在中国大陆 |
| `mixed` | 混合站 | 服务器在海外，访客中外都有，管理员在中国大陆 |

- 缺省 / 升级站未选：`domestic`。
- 多站点：网络策略写在 `wpcy_network_settings.profile`；子站可在 `wpcy_site_overrides.profile` 覆盖（受 `allow_site_override` 约束，与 `connectivity` / `modules` 相同）。缺省段表示不覆盖。覆盖 `profile` 而不覆盖 `connectivity` / `admin_assets` / `modules.windfonts` 时，读取仍用网络已存的连通性值——场景切换重置只发生在用户确认切换的写入路径（`Profile::apply_defaults()`），不在读取合并时隐式重置。
- 场景只决定默认组合；每项仍可单独改。切换场景 = 重置连通性各项为该场景默认（改前确认）。

`modules.site_blocklist`：**网络级，站点不可覆盖。** 单站写入 `wpcy_settings`；多站点只写 `wpcy_network_settings`，**不**出现在 `wpcy_site_overrides`。子站 PUT 本段 → `wpcy_invalid_schema` 或实现方等价拒绝（不得静默写入覆盖）。`enabled` 默认 `true`。`hosts[]` 上限 **20**；每条 `{ host, match: exact|suffix, note }`。`host` 是主机名，无 scheme、无路径、无端口、无正则、无通配。`note` 可选，最长 200，仅给人读。保存时若任一条命中 L0 受保护主机 → 整单拒绝（REST 见 `PUT /site-blocklist`），不部分写入。不加「配额」字段、不加条数套餐、不加保留天数。

`modules.noise_block.enabled`：噪声拦截包整包开关，默认 `true`。条目不在本 option，在签名 ruleset 的 `noise_block` 数组。用户不可编辑条目。三场景默认皆 `true`；切换场景不改本键。

`recovery_mode = true` 时：全部 URL 改写关闭、全部可选模块停用。退出恢复模式（`recovery_mode = false`）后是否自动恢复改写与模块 **待定（M0）**，见 `docs/specs/rest-api.md` `/recovery`。

## 2. `wpcy_network_settings`（网络）

同 `wpcy_settings` 结构，另加：

```json
{
  "allow_site_override": { "type": "boolean", "default": true }
}
```

`allow_site_override` 为 `false` 时，子站不得写入 `wpcy_site_overrides`；已有覆盖被忽略。网络 option 的 `autoload` 随 WordPress 网络 option 惯例，**待定（M0）**：实现方确认用 `update_site_option` 后的 autoload 行为是否需要显式声明。

## 3. `wpcy_site_overrides`（子站对网络策略的覆盖）

允许 `profile`、`connectivity`、`modules`、`admin_assets`、`recovery_mode`。结构与站点设置对应段相同。不允许这些键与 `schema_version` 以外的其它顶层键。

`modules.site_blocklist` **不得**出现在本 option。覆盖袋的 `modules` 只允许站点可覆盖的模块键（现有 `notice_control` / `windfonts` / `noise_block`）。实现校验：覆盖对象含 `site_blocklist` → 丢弃该键并记 warning，或整段 `wpcy_invalid_schema`（M-BLOCK-1 写死一种并测）。

`recovery_mode` 是站点级：多站点下子站写入本 option，读取时站点值优先于网络 option。即使 `allow_site_override` 为 false，本站 `recovery_mode` 仍生效（恢复模式不是连接/模块覆盖）。

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "$id": "https://wpcy.com/schema/wpcy_site_overrides.json",
  "type": "object",
  "additionalProperties": false,
  "properties": {
    "schema_version": { "type": "integer", "const": 2, "default": 2 },
    "profile": { "$ref": "https://wpcy.com/schema/wpcy_settings.json#/properties/profile" },
    "connectivity": { "$ref": "https://wpcy.com/schema/wpcy_settings.json#/properties/connectivity" },
    "modules": { "$ref": "https://wpcy.com/schema/wpcy_settings.json#/properties/modules" },
    "admin_assets": { "$ref": "https://wpcy.com/schema/wpcy_settings.json#/properties/admin_assets" },
    "recovery_mode": { "$ref": "https://wpcy.com/schema/wpcy_settings.json#/properties/recovery_mode" }
  }
}
```

缺省段表示不覆盖。合并规则：网络默认 ← 本站覆盖（`profile` / `connectivity` / `modules` / `admin_assets` 仅当 `allow_site_override` 为 true；`recovery_mode` 始终合并）。

## 4. `wpcy_site_identity`（`autoload=no`）

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "$id": "https://wpcy.com/schema/wpcy_site_identity.json",
  "type": "object",
  "additionalProperties": false,
  "required": ["schema_version", "site_uuid", "binding"],
  "properties": {
    "schema_version": { "type": "integer", "const": 1, "default": 1 },
    "site_uuid": { "type": "string", "format": "uuid" },
    "binding": {
      "type": "object",
      "additionalProperties": false,
      "required": ["status"],
      "properties": {
        "status": {
          "type": "string",
          "enum": ["unbound", "pending", "bound", "revoked", "failed"],
          "default": "unbound"
        },
        "site_hash": { "type": ["string", "null"], "default": null },
        "credential": { "type": ["string", "null"], "default": null },
        "bound_at": { "type": ["string", "null"], "format": "date-time", "default": null },
        "challenge_id": { "type": ["string", "null"], "default": null }
      }
    }
  }
}
```

`site_uuid` 首次启动生成，之后稳定。同站重装 / 换管理员不换 UUID（见 entitlements 规格）。

`binding.credential`：**加密存储**。用 `wp_salt('auth')` 派生密钥 + sodium secretbox（`sodium_crypto_secretbox`）。密文以 Base64 写入 option。不进导出、Site Health、日志、REST `/binding` 响应。明文不得出现在 PHP error log。

`challenge_id` 仅 `pending` 时有值；过期或完成后清空。challenge token 本身不进本 option 的长期字段（短时可进 transient）。**待定（M0）**：token 存 option 还是 transient，由 `Services/SiteBinding` 作者定，须满足「pending 且未过期才能被公开挑战端点读出」。

## 5. `wpcy_migration_backup`

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "$id": "https://wpcy.com/schema/wpcy_migration_backup.json",
  "type": "object",
  "additionalProperties": false,
  "required": ["schema_version", "from_version", "migrated_at", "legacy_hash", "ignored_fields"],
  "properties": {
    "schema_version": { "type": "integer", "const": 1, "default": 1 },
    "from_version": { "type": "string" },
    "migrated_at": { "type": "string", "format": "date-time" },
    "legacy_hash": { "type": "string" },
    "ignored_fields": {
      "type": "array",
      "items": { "type": "string" },
      "default": []
    }
  }
}
```

不复制敏感凭据。`legacy_hash` 是旧 `wp_china_yes` 的摘要，用于检测迁移后旧 option 是否被改过。

## 默认值汇总

下表是 JSON Schema `default`（即 `profile=domestic` 的新安装默认）。其它场景由「默认矩阵（D2）」覆盖。

| 路径 | 默认 |
|---|---|
| `schema_version` | `2`（`wpcy_site_identity` / `wpcy_migration_backup` 仍为 `1`） |
| `profile` | `"domestic"` |
| `connectivity.wordpress_org` | `"auto"` |
| `connectivity.public_assets.items` | `["google_fonts","google_ajax","cdnjs","jsdelivr","emoji"]` |
| `connectivity.public_assets.scope` | `"both"` |
| `connectivity.avatar.admin` | `"cravatar_cn"` |
| `connectivity.avatar.frontend` | `"cravatar_cn"` |
| `connectivity.heartbeat` | `"off"` |
| `connectivity.dashboard_feeds` | `"allow"` |
| `admin_assets` | `"off"` |
| `modules.notice_control` | `true` |
| `modules.windfonts` | `false` |
| `modules.site_blocklist.enabled` | `true` |
| `modules.site_blocklist.hosts` | `[]` |
| `modules.noise_block.enabled` | `true` |
| `diagnostics.scheduled_checks` | `true` |
| `diagnostics.client_probe_url` | `""` |
| `data_residency.ruleset_version` | `1` |
| `announcements.dismissed` | `[]` |
| `apps.disabled` | `[]` |
| `recovery_mode` | `false` |
| `allow_site_override`（仅网络） | `true` |
| `binding.status` | `"unbound"` |

## 默认矩阵（D2）

照抄 [决定 D2](../dev-plan/decisions/2026-09-06-site-profile-and-scope.md)。`Profile::apply_defaults( $profile )` 按此表写入；切换场景前确认。不得改值。

| 功能 | `domestic` 默认 | `crossborder` 默认 | `mixed` 默认 | 备注 |
|------|-----------------|--------------------|--------------|------|
| `connectivity.wordpress_org`（服务器取 .org API / 安装包） | `auto` | `off` | `auto` | 服务器侧行为，无 admin/frontend 之分；`auto` 靠探测决定 |
| `connectivity.public_assets`（Google Fonts / Ajax / CDNJS / jsDelivr / Emoji 改写） | scope `both`，默认五项 | scope `admin`，默认五项 | scope `admin`，默认五项 | 海外访客不改写 |
| `connectivity.avatar` | `cravatar_cn`，scope `both` | admin `cravatar_cn`；frontend `off`（保留 Gravatar） | admin `cravatar_cn`；frontend `cravatar_global` | avatar 的 scope 是两个独立值 |
| `windfonts` | 绑定后可开（是否绑定由 Windfonts 平台决定，插件按服务端应答呈现；连通性无配额） | `off` | `off` | 前台功能；跨境站不默认 |
| `admin_assets`（后台静态资源加速） | `off` | `on` | `on` | **4.1 交付**；4.0 只预留 schema 键与开关位，界面显示「即将提供」，不做任何改写 |
| `connectivity.heartbeat`（仪表盘关心跳、编辑器 60s） | `off` | `on` | `on` | 跨境免费体验层；filter 名见 M-SCOPE-1 |
| `connectivity.dashboard_feeds`（挡 WP 新闻/事件 widget 与 dashboard feed） | `allow` | `block` | `block` | 跨境免费体验层；不挡支付/物流 |
| `notice_control` / `announcements` / 诊断 / 恢复 | 不受场景影响 | 同 | 同 | |
| `modules.site_blocklist` | `enabled=true`，`hosts=[]` | 同 | 同 | 网络级；站点不可覆盖；切换场景不改本键 |
| `modules.noise_block.enabled` | `true` | 同 | 同 | 切换场景不改本键 |
| `telemetry` | 不受场景影响（常开） | 同 | 同 | payload 增加 `profile` 字段 |
| `privacy.data_residency` | 按主机表；A 档 `ingest_ready` 才改道 | 不执行 A 档改道；B 档记录仍做；C 档不碰 | 同 `crossborder` | 方案 A + 保险，原文见下 |

写入对应：

| 路径 | `domestic` | `crossborder` | `mixed` |
|------|------------|---------------|---------|
| `connectivity.wordpress_org` | `auto` | `off` | `auto` |
| `connectivity.public_assets.items` | 五项 | 五项 | 五项 |
| `connectivity.public_assets.scope` | `both` | `admin` | `admin` |
| `connectivity.avatar.admin` | `cravatar_cn` | `cravatar_cn` | `cravatar_cn` |
| `connectivity.avatar.frontend` | `cravatar_cn` | `off` | `cravatar_global` |
| `modules.windfonts` | `false`（绑定后可开，不默认 true；无配额文案） | `false` | `false` |
| `connectivity.heartbeat` | `off` | `on` | `on` |
| `connectivity.dashboard_feeds` | `allow` | `block` | `block` |
| `admin_assets` | `off` | `on` | `on` |

「五项」= `["google_fonts","google_ajax","cdnjs","jsdelivr","emoji"]`。`notice_control` / `announcements` / 诊断 / 恢复 / `telemetry` / `privacy.data_residency` / `modules.site_blocklist` / `modules.noise_block` 切换场景时不改设置键。`privacy.data_residency` 采用方案 A + 保险（运行时按 `profile` 闸 A 档，不改主机表），见 [`data-residency-ruleset.md`](data-residency-ruleset.md)。

`privacy.data_residency`（统筹拍板原文）：采用方案 A + 保险。A 档改道跟 `profile` 闸：`domestic` 维持现状（`ingest_ready` 才改道）；`crossborder` / `mixed` 不执行 A 档改道，B 档记录仍做，C 档不碰。保险：`profile=domestic` 时即使 geo 判定服务器在海外也改道（用户自称国内站以用户为准）。理由：A 档要挡的是"中国站数据出境"，不是"海外站回中国"；海外服务器绕国内云桥增加失败面，与叶子要解的问题方向相反。

## 3.x → 4.0 映射（D3）

在 [`docs/4.0-rewrite-plan.md`](../4.0-rewrite-plan.md) §7.2 之上追加（撤销 M4-02b 的 `admin→ignored`）：

| 3.x | 4.0 | 规则 |
|-----|-----|------|
| （无场景数据） | `profile` | 一律 `"domestic"`；概览提示确认；不猜 |
| `cravatar` 单值 | `connectivity.avatar.admin` 与 `connectivity.avatar.frontend` | 两个值都等于映射后的枚举值（`cn`→`cravatar_cn` 等，与既有 `AVATAR_MAP` 相同） |
| `admincdn_public` / `admincdn_files` / `admincdn_dev` / 3.8 `admincdn` 中的白名单 token | `connectivity.public_assets.items` | 与 M4-02b 相同：键存在则推导，空则 `[]`，不回落五项；`scope` 写 domestic 默认 `both` |
| 3.8 `admincdn` 含 `admin`，或 3.9 `admincdn_files` 含 `admin` | `admin_assets` | `"on"`；迁移报告列出「后台加速：已保留设置，4.1 起生效」；**token `admin` 不再进 `ignored`** |
| `frontend` token、`bootstrapcdn` | — | 仍进 `ignored`（`unsupported_whitelist`） |

无 `admin` token 时 `admin_assets` 用 domestic 默认 `"off"`。
