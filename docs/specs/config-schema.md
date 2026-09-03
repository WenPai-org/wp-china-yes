# 配置 Schema

状态：草案（M0）· 来源：linuxjoy 定稿 §7.5a / §7.1a / §7.1c

本文给出四个（加迁移备份共五个）option 的 JSON Schema（draft 2020-12）与读写规则。键名稳定不带版本；演进靠结构内 `schema_version` 与迁移器。不得在本文新增产品决定；空白处标「待定（M0）」。

## 规则

- 读写只经 `Config\Repository`。业务模块不得直接 `get_option()` / `update_option()`。
- 未知键丢弃并记 warning 日志。
- `schema_version` 升级由 `Migration\Runner` 按步执行、幂等、可 dry-run。
- 每个字段有默认值（见各节 `default`）。
- 凭据不进导出、Site Health、日志。

## 1. `wpcy_settings`（站点，`autoload=yes`）

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "$id": "https://wpcy.com/schema/wpcy_settings.json",
  "type": "object",
  "additionalProperties": false,
  "required": [
    "schema_version",
    "connectivity",
    "modules",
    "diagnostics",
    "data_residency",
    "announcements",
    "apps",
    "recovery_mode"
  ],
  "properties": {
    "schema_version": { "type": "integer", "const": 1, "default": 1 },
    "connectivity": {
      "type": "object",
      "additionalProperties": false,
      "required": ["wordpress_org", "public_assets", "avatar"],
      "properties": {
        "wordpress_org": {
          "type": "string",
          "enum": ["auto", "off"],
          "default": "auto"
        },
        "public_assets": {
          "type": "array",
          "uniqueItems": true,
          "items": {
            "type": "string",
            "enum": ["google_fonts", "google_ajax", "cdnjs", "jsdelivr", "emoji"]
          },
          "default": ["google_fonts", "google_ajax", "cdnjs", "jsdelivr", "emoji"]
        },
        "avatar": {
          "type": "string",
          "enum": ["cravatar_cn", "cravatar_global", "weavatar", "off"],
          "default": "cravatar_cn"
        }
      }
    },
    "modules": {
      "type": "object",
      "additionalProperties": false,
      "required": ["notice_control", "windfonts"],
      "properties": {
        "notice_control": { "type": "boolean", "default": true },
        "windfonts": { "type": "boolean", "default": false }
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
        "scheduled_checks": { "type": "boolean", "default": true }
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
    "recovery_mode": { "type": "boolean", "default": false }
  }
}
```

`public_assets` 必须是 `[google_fonts, google_ajax, cdnjs, jsdelivr, emoji]` 的子集。未知字符串丢弃。

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

只允许 `connectivity`、`modules` 两段。结构与站点设置对应段相同。不允许 `schema_version` 以外的其它顶层键。

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "$id": "https://wpcy.com/schema/wpcy_site_overrides.json",
  "type": "object",
  "additionalProperties": false,
  "properties": {
    "schema_version": { "type": "integer", "const": 1, "default": 1 },
    "connectivity": { "$ref": "https://wpcy.com/schema/wpcy_settings.json#/properties/connectivity" },
    "modules": { "$ref": "https://wpcy.com/schema/wpcy_settings.json#/properties/modules" }
  }
}
```

缺省段表示不覆盖。合并规则：网络默认 ← 本站覆盖（仅当 `allow_site_override` 为 true）。

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
          "enum": ["unbound", "pending", "bound", "revoked"],
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

| 路径 | 默认 |
|---|---|
| `schema_version` | `1` |
| `connectivity.wordpress_org` | `"auto"` |
| `connectivity.public_assets` | `["google_fonts","google_ajax","cdnjs","jsdelivr","emoji"]` |
| `connectivity.avatar` | `"cravatar_cn"` |
| `modules.notice_control` | `true` |
| `modules.windfonts` | `false` |
| `diagnostics.scheduled_checks` | `true` |
| `data_residency.ruleset_version` | `1` |
| `announcements.dismissed` | `[]` |
| `apps.disabled` | `[]` |
| `recovery_mode` | `false` |
| `allow_site_override`（仅网络） | `true` |
| `binding.status` | `"unbound"` |
