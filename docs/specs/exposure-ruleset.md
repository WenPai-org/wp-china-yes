# 露出规则（推荐清单）

状态：定稿（SPEC-EXPOSURE-1，M-UI-3 前）· 日期：2026-09-11。

本文冻结服务端下发的推荐清单契约：文档形状、Ed25519 验签、拉取与容错、scene × segment 匹配、客户端渲染、隐私边界、发布步骤。实现见 M-UI-3（服务页 SV-11 检测行 / 推荐标）与后续后端任务（与元素隐藏规则引擎同批并入统一下发通道）。不得在本文新增产品决定；空白处标「待定」。

依据（逐节引用，不另作产品判断）：

- [`decisions/2026-09-11-scene-matrix-and-segments.md`](../dev-plan/decisions/2026-09-11-scene-matrix-and-segments.md) 决定 B（画像字段）、决定 C（scene × segment 双键）、决定 E（推荐映射、不阻断、不写死、检测行 + 推荐标）、统筹补充（推荐清单容错 ≤72h）、场景与模块拍板第 4–5 条（本契约时点、下发通道合并）。
- [`decisions/2026-09-11-noise-reduction-and-absorption.md`](../dev-plan/decisions/2026-09-11-noise-reduction-and-absorption.md) §1（服务端不可达沿用最近一份、缓存 ≤72h、界面标注更新时间；推荐清单容错同口径）。
- [`decisions/2026-09-06-core-services-value-and-providers.md`](../dev-plan/decisions/2026-09-06-core-services-value-and-providers.md) D4（未购按露出规则显示「了解 →」）、D5（叶子是渠道；商业内容由服务端规则下发）、P4（`unreachable` 保留 ≤72h 缓存）。
- [`decisions/2026-09-06-site-profile-and-scope.md`](../dev-plan/decisions/2026-09-06-site-profile-and-scope.md) 商业模型节（露出条件服务端下发，插件内不写死）。
- 机制参考：[`apps-manifest-and-bridge.md`](apps-manifest-and-bridge.md) §1.3（规范化 JSON）、`src/Apps/ManifestVerifier.php`（Ed25519 分离验签）、`src/Admin/NoticeControl/NoticeControlModule.php`（transient + daily cron + 源 URL）、`src/Services/Entitlements/EntitlementsModule.php`（1h 新鲜 + 72h 陈旧）。

## 0. 一句话

叶子不在客户端写死推荐名单。文派服务端签发一份带版本、TTL、Ed25519 签名的 JSON；插件按 **scene × segment** 过滤、按 `weight` 排序，画在服务页「可用服务」与向导场景包。验签失败、过期或源不可达时，沿用上一份已验签缓存，最长 72 小时，界面写出更新时间。

## 1. 文档形状

一份文档覆盖全部 scene × segment。客户端本地过滤（取舍见 §3）。

```json
{
  "version": 1,
  "issued_at": "2026-09-11T00:00:00Z",
  "ttl": 86400,
  "kid": "wpcy-ruleset-2026",
  "segments": {
    "direction": ["outbound", "inbound", "local", "content"],
    "platform": ["woocommerce", "none"],
    "locale": ["zh-CN", "zh-HK", "zh-TW", "en"]
  },
  "items": [
    {
      "id": "weixin-pay-woo",
      "type": "plugin",
      "provider": "weixiaoduo-mall",
      "title": "微信支付 for WooCommerce",
      "desc": "给中国买家在店里直接付。",
      "action": "learn",
      "weight": 100,
      "go": "weixin-pay-woo",
      "match": {
        "scene": ["crossborder", "mixed", "inbound"],
        "direction": ["outbound", "inbound"],
        "platform": ["woocommerce"],
        "locale": ["*"]
      }
    }
  ],
  "signature": "base64(ed25519 over canonical JSON without signature field)"
}
```

顶层字段：

| 字段 | 类型 | 规则 |
|---|---|---|
| `version` | int | 正整数。增量必须**严格大于**当前生效版本才替换（与 [`data-residency-ruleset.md`](data-residency-ruleset.md) §2 `ruleset_version` 相同）。 |
| `issued_at` | string | UTC ISO 8601。 |
| `ttl` | int | 新鲜窗口，秒。允许 3600–604800。缺省按 86400 读。客户端陈旧窗口另计，见 §2.3，不以本字段突破 72h 上限。 |
| `kid` | string，可选 | `wpcy-ruleset-2026` 或 `wpcy-apps-2026`。存在时按 `kid` 选公钥。缺省不写，用构造器公钥。一套测试钥两个 `kid`（定稿 §7.5b-3；与驻留表 / apps 相同）。 |
| `segments` | object | 本文件词汇表，见 §1.1。不是「这一份只服务某一个组合」。 |
| `items` | array | 推荐条目，最多 50 条。超出的尾部丢弃并记诊断。 |
| `signature` | string | Base64 Ed25519 分离签名，覆盖去掉 `signature` 后的规范化 JSON。 |

不得出现：邮箱、姓名、站点 URL、IP、插件清单、订单、凭据。本文件是规则，不是用户数据（§5）。

### 1.1 `segments`（词汇表）

决定 B 原文：`/binding` 的 profile 增加匿名画像字段（不含任何个人数据，随绑定可清除）。

| 键 | 首批枚举 | 来源（决定 B） |
|---|---|---|
| `direction` | `outbound` 出海 / `inbound` 进中国 / `local` 本地 / `content` 内容 | 用户选择（向导第 1 步）+ 服务端可修订 |
| `platform` | `woocommerce` / `none` | 探测已装插件，用户可改 |
| `locale` | `zh-CN` / `zh-HK` / `zh-TW` / `en` | 站点语言 + 管理员语言 |

`segments.*` 为**数组**，列出本文件承认的取值。服务端日后追加枚举（例如新 `platform`）只改签发文件，不改插件；客户端对未知值：条目 `match` 含该值则该维不命中（该条不进入清单），不得当 `*` 处理。

`scene` 不是 segment。它是网络场景（决定 A：`domestic` / `crossborder` / `inbound` / `mixed`），读 `wpcy_settings.profile`。双键里的 scene 写在条目 `match.scene`（§1.3），不进 `segments`。

`scene=inbound`（内贸·进中国站）与 `direction=inbound`（经营画像·进中国）不是同一个键。

港澳台：locale 属性（`zh-HK` / `zh-TW`），不开新网络场景（决定 B）。

### 1.2 `items[]`

| 字段 | 类型 | 规则 |
|---|---|---|
| `id` | string | 稳定标识，`^[a-z0-9][a-z0-9._-]{0,63}$`。`type=widget` 时必须等于 apps manifest `id`。重复 `id` 只留 `weight` 更高的一条，并列留先出现的。 |
| `type` | string | ∈ `widget` \| `plugin` \| `service`。 |
| `provider` | string | ∈ `wenpai` \| `weixiaoduo-mall` \| `wenpai-marketplace`。后两个与 [`providers.md`](providers.md) §1 的供应商 `id` 相同；文派自研（Windfonts、小工具、文派服务、MotuCloud 作为文派基础资源）用 `wenpai`。禁止第三供应商（决定 D4 / P2）。 |
| `title` | string | 纯文本，1–80 字。客户端原样渲染（服务端是文案源）。 |
| `desc` | string | 纯文本，≤ 200 字。 |
| `action` | string | ∈ `install` \| `learn`。映射见 §4.4。 |
| `weight` | int | 整数 0–1000。大者在前。并列按 `id` 升序。 |
| `go` | string | `/go/{go}` 的 slug，`^[a-z0-9][a-z0-9-]{0,63}$`。商业链一律 `https://wpcy.com/go/{go}`，禁止直写第三方域名（定稿 §5 / §10）。缺省等于 `id`。 |
| `match` | object，可选 | 见 §1.3。缺省 = 四维皆 `*`。 |
| `plugin_slug` | string，可选 | 仅 `type=plugin`：与本站 `get_plugins()` 目录名精确匹配时视为已安装。无此字段则不做已安装判定。 |

`type` 语义：

| `type` | 含义 | 界面落点 |
|---|---|---|
| `widget` | 小工具容器内的工具 | 服务页小工具网格的推荐标；不替代 apps 索引 |
| `plugin` | 可安装的 WordPress 插件 | 可用服务行 |
| `service` | 文派服务或供应商服务（非插件、非小工具） | 可用服务行 |

条目不携带价格、套餐、配额。额度只来自 `/entitlements`（决定：客户端不计算额度、不在本地判断套餐）。

### 1.3 `match`（scene × segment）

决定 C 原文：服务端露出规则按 **scene × segment** 双键定向。

```json
"match": {
  "scene": ["crossborder", "mixed"],
  "direction": ["outbound"],
  "platform": ["woocommerce"],
  "locale": ["*"]
}
```

| 键 | 缺省 | 命中 |
|---|---|---|
| `scene` | `["*"]` | 当前 `profile` ∈ 数组，或数组含 `*` |
| `direction` | `["*"]` | 当前画像 `direction` ∈ 数组，或 `*` |
| `platform` | `["*"]` | 有效 `platform` ∈ 数组，或 `*` |
| `locale` | `["*"]` | 当前 `locale` ∈ 数组，或 `*` |

四维**同时**命中才进入清单（AND）。一维内部是任意匹配（OR）。

客户端当前值：

| 维 | 读自 | 规则 |
|---|---|---|
| `scene` | `wpcy_settings.profile` | `domestic` \| `crossborder` \| `inbound` \| `mixed`。未确认前按 `domestic`（ADR-004 升级默认）。 |
| `direction` | 绑定画像；未写则 `null` | `null` 只命中 `*`。不得猜。 |
| `platform` | 绑定画像；未写则按本站插件推断 | 推断：已激活 WooCommerce → `woocommerce`，否则 `none`。用户改过画像则以画像为准（决定 B「用户可改」）。 |
| `locale` | 绑定画像；未写则 `get_user_locale()` 规范化 | 映射：`zh_CN`/`zh-CN` → `zh-CN`；`zh_HK`/`zh-HK` → `zh-HK`；`zh_TW`/`zh-TW` → `zh-TW`；其余 → `en`。港澳台只走 locale，不新开 scene（决定 B）。 |

检测行的 WooCommerce 判定用**本站实时已激活插件**，不用画像（决定 E「检测到 WooCommerce 即识别为电商」；界面词表已冻结）。`match.platform` 仍按上表，避免用户明确改成 `none` 后清单与画像打架。

硬过滤（决定 C「local / content → 商业内容几乎不露出（D5 既定）」+ 决定 E 把 D5 的「几乎不露出」从 `scene=domestic` 改挂到 `direction=local|content`）：

1. `provider=weixiaoduo-mall` 的条目，若当前 `direction` ∈ `{local, content}`，客户端**丢弃**，即使 `match` 误写了 `*`。
2. `scene=domestic` 且实时未检测到 WooCommerce 时，`provider=weixiaoduo-mall` 丢弃（决定 E：国内用户 / 未检测到电商 → 文派自研，不以薇晓朵为主）。
3. `wenpai-marketplace` 在供应商 `coming_soon` 期间不渲染动作按钮（[`providers.md`](providers.md) §1），条目可出现在清单但动作按「即将开放」处理。

MotuCloud：面向**全部场景**的基础资源服务，content/local 人群的主要推荐项（决定 C / 决定 E 核心服务 4→5）。此类条目 `provider=wenpai`，`match` 四维 `*`，`weight` 在 content/local 下应高于其它 `wenpai` 项。接通协议（核心兼容镜像反代 + 自建 API）不在本文件；本文件只决定它是否出现在推荐清单。

## 2. 签名、拉取、验证失败 / 过期 / 不可达

### 2.1 规范化 JSON 与验签

复用 apps manifest / 驻留 ruleset 同一套（`ManifestVerifier`，拍板第 5 条：五类文档统一走 Ed25519 签名规范化 JSON + version/TTL + transient + cron；推荐清单同此通道）。

对去掉 `signature` 字段后的对象：

1. 键按字典序递归排序；
2. UTF-8 编码；
3. 无多余空白（RFC 8259 最短序列化：对象与数组无空格、无尾随换行；数组保序）；
4. 对该字节做 Ed25519 分离签名，结果 Base64 写入 `signature`。

验签：`sodium_crypto_sign_verify_detached`。公钥随插件发布。测试钥与驻留表 / apps 相同（`tests/fixtures/keys/`，**TEST ONLY**）。生产私钥不在本仓；签发见 §6 与定稿 §7.5b-3。

`kid` 未知 → 验签失败（与 `ManifestVerifier::resolve_public_key` 相同：未知 kid 返回空公钥）。

### 2.2 源 URL 与刷新

| 项 | 合同 |
|---|---|
| 生产 URL | `https://wpcy.com/rulesets/exposure.json` |
| 允许主机 | HTTPS，后缀 ∈ `wpcy.com` / `wenpai.net`（`ManifestVerifier::origin_allowed` / `HOST_SUFFIXES`） |
| 实现门闩 | 模块常量空字符串则不拉生产（与 `NoticeControlModule::PRODUCTION_URL = ''` 相同）。空源 = `unconfigured`，清单为空，**不得**回退到客户端写死名单（决定 E「不在客户端写死」）。 |
| 传输 | `wp_remote_get`，timeout 10s，`sslverify=true`。非 200 / WP_Error / 空 body = 不可达。 |
| cron | 钩子 `wpcy_exposure_rules_refresh`，`daily`（与 `NoticeControlModule::CRON_HOOK` 同频）。 |
| 新鲜缓存 | transient `wpcy_exposure_rules`，TTL = `min(document.ttl, 86400)`。 |
| 陈旧缓存 | transient `wpcy_exposure_rules_stale`，TTL = 259200（72h）。只在新鲜缺失且拉取失败时读取（与 `EntitlementsModule::TTL_STALE` / P4 同口径）。 |
| 附加字段 | 写入缓存时插件附加 `fetched_at`（UTC ISO 8601，本次验签成功的时间）。`fetched_at` **不**进签名、不上送。 |

多站点：按子站 transient；不读网络 option。

拉取**不**带 `site_uuid`、不带画像、不带已装插件列表。本文件是静态签名文档（§3）。

### 2.3 失败行为（验证失败 / 过期 / 不可达）

决定 E 统筹补充原文：文派服务不可达时，服务页沿用最后一份下发的推荐清单（缓存 ≤72 小时，与 P4 的 unreachable 缓存策略同一上限），界面标注更新时间。噪声决定 §1：服务端不可达时沿用最近一份规则集，缓存 ≤72 小时（与 P4 同一上限），界面标注更新时间；推荐清单容错同口径。

| 事件 | 文档处置 | 界面（服务页可用服务） | `status`（§4.1） |
|---|---|---|---|
| 验签失败（签名坏、JSON 非对象、缺必填、`version` 非法） | 整份丢弃，不替换缓存；记诊断 `wpcy_exposure_signature_invalid` | 有 ≤72h 陈旧缓存 → 沿用并写出更新时间；否则空清单，不发明条目 | `invalid`（无缓存）或 `stale` |
| HTTP / DNS / TLS / 非 200（不可达） | 不替换；沿用缓存 | 同上。注意条：「暂时无法连接文派服务，显示的是 {N} 小时前的状态。」+「重试」（SV-04 句式；词表已有） | `unreachable`（无缓存）或 `stale` |
| `issued_at + ttl` 已过，但仍在 `fetched_at + 72h` 内 | 继续用，不丢 | 写出更新时间；不假装新鲜 | `stale` |
| `fetched_at` 距今 > 72h，或两份 transient 都空 | 停用清单 | 空列表。小节说明沿用 SV-11 的「推荐清单由服务端下发并自动更新」。**禁止**回退 `STATIC_CATALOG` 或任何内置商业名单 | `unreachable` 或 `unconfigured` |
| `version` ≤ 当前生效 | 忽略新文档，保留当前 | 不变 | 维持 |

过期时钟：用 `issued_at`（文档声明）与 `fetched_at`（本站成功验签时间）两把尺。72h 上限只看 `fetched_at`，避免服务端把 `ttl` 开到 7 天从而绕过 P4。

`version` 比较只在验签成功之后做。验签失败的文档不得靠更大的 `version` 挤掉旧缓存。

恢复模式：不拉、不渲染推荐清单（与 NoticeControl `recovery_mode` 短路同方向）。连通性不受影响。

## 3. 端点族与分段策略

**选择：单一文档 + 客户端过滤。** 不按 segment 组合拆 URL。

生产只签发：

```
GET https://wpcy.com/rulesets/exposure.json
```

插件侧只读缓存（给 React 用，见 §4.1）：

```
GET /wp-json/wpcy/v1/exposure
```

权限 `manage_options`。不接受查询参数改匹配结果（匹配只用本站 scene / 画像 / 实时插件，避免 URL 被书签或 CDN 缓存成别人的清单）。

### 3.1 取舍理由

| 方案 | 结论 |
|---|---|
| **单一文档 + 客户端过滤（本契约）** | 采用 |
| 按 scene × direction × platform × locale 组合各签一份 | 不用 |

理由（对照已拍板机制，不另开产品口）：

1. **组合爆炸。** 决定 A 四场景 × 决定 B 四 direction × platform（首批 2，可增）× locale（首批 4）在首批已是数十份；每份都要作者、评审、签名、发布（§6）。通知规则、驻留表、apps 索引都是一份签名文档（拍板第 5 条通道合并）。推荐清单走同一形状。
2. **检测信号在本站。** 决定 E：本站插件环境 + 网络场景 + 画像，三者合成清单。WooCommerce 是否激活只有站点知道。若把 platform 编进 URL，拉取本身会把经营画像泄漏给 CDN / 日志；决定 B 要求画像匿名、不含个人数据，静态 GET 不带查询是同一条边界。
3. **过滤本就发生在客户端。** 硬过滤（local/content 去商业、domestic 无 Woo 去薇晓朵）必须在本站执行，服务端按 URL 分段也挡不住一份签错的文档。一份文档 + 客户端硬过滤更短。
4. **容错更简单。** 一份签名、一份 transient、一份 72h 陈旧。按组合拆 URL 时，画像一变就要另拉；失败时「上一份」可能是另一个组合，和「沿用最后一份下发」的口径不一致。
5. **不阻断。** 决定 E：默认界面只展示推荐清单，其它供应商产品用户可自行安装。清单短（≤50），一份 JSON 体积可接受。

日后条目远超 50、签发方主动要拆文件：另开决定，不在本文预留第二 URL 族。

## 4. 客户端渲染契约

挂点（决定 E）：服务页「可用服务」顶部检测说明行 + 推荐标；onboarding 场景包同源。词表已在 [`admin-ui-spec.md`](../design/admin-ui-spec.md) §0b 第 7、9 条与 §5「M-UI-1c v2.2」登记。M-UI-3 按本文件换掉 `Services.js` 的 `STATIC_CATALOG`。

### 4.1 插件 REST `GET /wpcy/v1/exposure`

```json
{
  "status": "ok",
  "version": 1,
  "issued_at": "2026-09-11T00:00:00Z",
  "fetched_at": "2026-09-11T08:12:00Z",
  "detect": {
    "woocommerce": true,
    "scene": "inbound",
    "direction": "inbound",
    "platform": "woocommerce",
    "locale": "zh-CN"
  },
  "items": []
}
```

| 字段 | 规则 |
|---|---|
| `status` | `ok`（新鲜缓存且未过 `issued_at+ttl`）\| `stale`（正在用陈旧缓存）\| `unreachable`（不可达且无可用缓存）\| `invalid`（最近一次拉到的文档验签失败且无可用缓存）\| `unconfigured`（源常量为空） |
| `version` / `issued_at` / `fetched_at` | 来自当前生效缓存；无缓存时三者为 `null` |
| `detect` | 本站当前匹配键。不含 URL、邮箱、IP、`site_uuid`、插件 slug 列表。`woocommerce` 为实时已激活判定（检测行用它，不用画像）。 |
| `items` | 已按 §1.3 过滤、已按 §2.3 硬过滤、已按 `weight` 降序。每项含 `id, type, provider, title, desc, action, weight, go`，外加计算字段 `recommended`（bool，§4.3）。不含 `signature`、不含未命中条目。 |

无缓存时：`items: []`，HTTP 200，不把下发失败变成 REST 4xx（与 `/entitlements`、`/announcements` 空列表同方向）。

### 4.2 检测行

仅服务页「可用服务」顶部。不进设置、不进诊断。

| 条件 | 文案（词表已冻结） |
|---|---|
| `detect.woocommerce === true` | 检测到 WooCommerce —— 按电商推荐**薇晓朵** |
| 否则 | 未检测到 WooCommerce —— 按「{场景}」推荐**文派服务** |

`{场景}` 用人读名：国内站 / 跨境 / 外贸站 / 内贸 · 进中国站 / 混合站（admin-ui-spec 场景卡）。

行下说明（SV-11 已有）：推荐清单由服务端下发并自动更新 · 其他供应商的产品也可自行安装使用。

`status` ∈ `stale` \| `unreachable` \| `invalid` 时，在检测行旁或卡顶标注更新时间：相对时间词表（刚刚 / {n} 小时前 / …），数据用 `fetched_at`。无 `fetched_at` 则只显示 SV-04 注意条，不造时间。

向导「可能还需要」步（admin-ui-spec §0b 第 9 条）读同一 `items`，不另拉。过滤已在 REST 做完。该步动作只有「了解 →」与「跳过，以后再说」，不做安装（向导不打断，决定 E 界面挂点 + 跨境店场景包口径）。

### 4.3 推荐标与排序

1. 排序：`weight` 降序；并列 `id` 升序。检测行的主推荐供应商（Woo → 薇晓朵，否则文派）**不**再把对方条目沉底——权重由服务端写死，客户端不因检测结果改序。决定 E 的「置顶」通过签发方给主推荐条目更高 `weight` 实现（首批映射见 §4.5）。
2. 推荐标：`recommended=true` 的行在名称旁画 `.tag-rec`「推荐」。
3. `recommended` 计算（只用于标，不用于删除）：
   - 检测行主推荐供应商的条目为 true：Woo → `provider=weixiaoduo-mall`；非 Woo → `provider=wenpai`。
   - MotuCloud（`id` 稳定为 `motucloud`）在 content/local 下为 true（决定 C：content/local 的主要推荐项），即使检测行主推荐是薇晓朵。
   - 其它 false。不阻断：无推荐标的条目仍渲染（决定 E）。

已连接供应商的已购产品进入可用服务，是 [`providers.md`](providers.md) 的列表，不是本文件。两份列表按 `id` / `plugin_slug` 去重：已购行保留供应商状态（已安装 / 未安装 / 管理 / 安装），推荐标若命中仍可画。

### 4.4 默认安装 vs 了解

| `action` | 未购 / 未装 | 已购未装（仅 `plugin` 且供应商已连接、产品在已购列表） | 已启用 / 已安装 |
|---|---|---|---|
| `learn` | 幽灵「了解 →」→ `https://wpcy.com/go/{go}` 新窗口 | 同左（了解不升级成安装） | 「设置」或「管理」（SV-11） |
| `install` | 未购：仍「了解 →」（D4「未购的按服务端露出规则显示了解 →」；叶子不替用户装未购商业插件） | 主按钮「安装」（SV-11 唯一主按钮） | 「设置」/「管理」 |
| `install` 且 `type=widget` | 绑定后在小工具网格打开；未绑定走 SV-01 空态 | — | 打开容器（SV-08） |
| `install` 且 `type=service`、`provider=wenpai` | 免费文派服务：主按钮「启用」或「设置」（Windfonts 等按既有模块开关）；不要「购买」 | — | 「设置」 |

一页一个主按钮（设计规格 §1.3）。可用服务表同时只有一个「安装」主按钮：位于排序后的第一条 `action=install` 且当前可装的行；其余可装行用次按钮「安装」。

`type=widget` 的条目不在可用服务表再画一行小工具卡；只给 apps 网格打推荐标。可用服务表只渲染 `plugin` 与 `service`。

禁止：直链 `mall.weixiaoduo.com` 或其它商业域；在按钮旁写套餐名、价格、配额数字。

### 4.5 首批映射（签发方必须按此写 `match` / `weight`，客户端不写死）

决定 C / E 原文，抄在这里供签发对照。插件**不得**把本表做成 fallback 名单。

| 条件 | 条目（人读名） | `provider` | 建议 `action` | 建议 `weight` |
|---|---|---|---|---|
| `platform=woocommerce`（电商） | 微信支付 for WooCommerce、订单微信通知 | `weixiaoduo-mall` | `learn`（未购） | 100、90 |
| `scene=inbound` 或 `direction=inbound` | 前台加速包、中国可达性检测（文派服务小工具）、中文字体 | `wenpai` | `install` / `learn` 按类型 | 80–70 |
| `locale` ∈ `{zh-HK, zh-TW}` | 繁体字体服务 | `wenpai` | `install` | 75 |
| 全部 scene | 图标与图片（MotuCloud） | `wenpai` | `install` | 60；在 `direction` ∈ `{local, content}` 时 95 |
| `direction` ∈ `{local, content}` | 商业内容几乎不露出 | — | — | 不得签发 `weixiaoduo-mall` |
| `scene=domestic` 且未检测到电商 | 文派自研（Windfonts、小工具、文派服务） | `wenpai` | 按类型 | 50–80 |
| inbound 未来项 | 境内 CDN 与合规指引 | `wenpai` | `learn` | 待签发；4.0 文档可缺 |

运维类薇晓朵产品（决定 E「微信支付、订单通知、运维」）有签发再出现，不在插件里预埋名称。

## 5. 隐私边界

决定 B：画像匿名，不含任何个人数据，随绑定可清除。决定 C：不提供遥测开关；画像仅用于露出与默认组合，不用于差异化限速。定稿 §7.1-1：报告常开，界面不露出「遥测 / 匿名数据」类文案。绑定是匿名站点标识（[`entitlements.md`](entitlements.md) §1：`site_uuid` 存 `wpcy_site_identity`，同站重装不换）。

| 允许 | 禁止 |
|---|---|
| 本站用 `site_uuid` 把绑定与画像存在**本机** option，供 §1.3 匹配 | 拉 `exposure.json` 时在 URL、Header、Body 携带 `site_uuid`、站点 URL、邮箱、IP、已装插件列表 |
| 画像三字段 `direction` / `platform` / `locale`（枚举） | 管理员姓名、账号、订单、顾客、精确地理 |
| 界面检测行写「检测到 WooCommerce」 | 把插件 slug 全表送出站 |
| 诊断只读：当前 `version`、`fetched_at`、`status`、命中条数 | 用户可见「遥测」「匿名数据」「上报」 |
| 解除绑定：画像可清（决定 B） | 用画像做限速、配额、功能门（决定 C） |

local / content：商业内容几乎不露出（§1.3 硬过滤）。MotuCloud 与文派自研仍可出现。

本文件条目不含个人数据。签名文档是全局规则，不是「给某站定制的一份」。服务端若按绑定修订画像（决定 B「服务端可修订」），修订走绑定 API，不走本 URL。

## 6. 服务端发布流程

文字步骤。生产私钥不在本仓（定稿 §7.5b-3：devops 在 feicode-prod 生成，license-server 的签名服务保管）。

1. **作者。** 文派运营按 §4.5 与当期活动起草 `items`（id / type / provider / title / desc / action / weight / go / match）。不写第三方域名，只写 `go` slug。对照硬过滤：local/content 不进薇晓朵；MotuCloud 全场景保留。
2. **评审。** 产品（feibisi）过商业映射与文案；统筹（linuxjoy）过双键、`provider` 枚举、与 D4/D5/决定 E 是否冲突。不通过不签发。
3. **版本。** `version` 加一（必须严格递增）。`issued_at` 现时 UTC。`ttl` 默认 86400。`segments` 词汇表只增不删首批枚举。
4. **签名。** 在 feicode-prod 对去掉 `signature` 的规范化 JSON 做 Ed25519 分离签名，`--kid wpcy-ruleset-2026`（或轮换期的 `wpcy-apps-2026`）。测试环境可用：
   ```bash
   php scripts/sign-ruleset.php path/to/exposure.json tests/fixtures/keys/wpcy-test-ed25519.key --kid wpcy-ruleset-2026
   ```
   测试钥**禁止**用于生产。现有 `sign-ruleset.php` 面向驻留表；推荐清单规范化规则相同，M-UI-3 后端任务可复用该脚本或给同一入口加文件类型，不改算法。
5. **发布。** 把签好的 JSON 放到 `https://wpcy.com/rulesets/exposure.json`（HTTPS，证书有效）。CDN 缓存短于文档 `ttl`。发布后抽查：无 `signature` 则客户端整份丢弃。
6. **生效。** 站点 cron 24h 内拉取；管理员打开服务页可「重试」触发一次刷新（与 SV-04 同按钮）。验签失败不得覆盖。
7. **回滚。** 重新签发更高 `version` 的上一份内容（或空 `items: []`）。不得靠「删文件让客户端 404」清清单——404 走不可达，客户端会沿用 72h 陈旧。

轮换公钥：新 `kid` 先随插件版本发布公钥，再签发带新 `kid` 的文档。旧文档在 72h 陈旧窗口内仍用旧钥可验。

## 7. 与相邻合同

| 合同 | 关系 |
|---|---|
| [`providers.md`](providers.md) | 已购列表与连接状态。本文件不管密钥、不管更新接管。`weixiaoduo-mall` / `wenpai-marketplace` 与其 `id` 相同。 |
| [`apps-manifest-and-bridge.md`](apps-manifest-and-bridge.md) | 小工具能否出现由索引验签决定。本文件的 `type=widget` 只打推荐标，不替代索引。 |
| [`entitlements.md`](entitlements.md) | 配额与降级。本文件不带 quota。 |
| [`announcements.md`](announcements.md) | 概览公告，另一份源。拍板第 5 条将公告补签名，与本通道合并，但不共用本 URL。 |
| [`data-residency-ruleset.md`](data-residency-ruleset.md) | 同一验签算法与 kid 空间；主机表不是推荐清单。 |
| [`admin-ui-spec.md`](../design/admin-ui-spec.md) SV-11 / SV-04 / 向导「可能还需要」 | 界面词与检测行；行为以本文 §4 为准。 |
| ADR-004 商业前提 | 「插件内不写死露出条件」由本文落实。决定 E 修订了「domestic 几乎不露出」：无 Woo 的国内站推荐文派自研；有 Woo 则以薇晓朵为主。几乎不露出的是 `direction=local\|content` 的商业项。 |

## 8. 不做什么

- 不在插件内写死推荐名单或按 scene 写死 if/else 商业包（决定 E）。
- 不按 segment 拆 URL（§3）。
- 不把本文件当授权代理、不当应用商店、不显示价格与套餐（D5）。
- 不新增第三个 `provider`。
- 不做按访客动态分流（场景拍板第 1 条）；本文件与前台换源无关。
- 不提供报告开关，不在界面写「遥测 / 匿名数据」。
- 不 push `main`；本文件只定契约，不实现模块。

## 9. 待定

- 生产源常量何时从空字符串改为 §2.2 URL：M3 / 与公告、通知规则补签名同批（拍板第 5 条）。
- `scripts/sign-ruleset.php` 是否扩成通用签发入口：实现任务定，算法不变。
- inbound「境内 CDN 与合规指引」的 `id` / `go` / 文案：产品有签发再写入文档，4.0 允许缺。
- MotuCloud 转接 WordPress Photos：feibisi 2026-09-11 口径为「后续考虑」，本契约不预埋。

## 10. 决定对照（零冲突）

| 决定出处 | 本文节 | 如何落实 |
|---|---|---|
| 场景修订 决定 A：四场景含 `inbound` | §1.1 `scene`、§1.3 | `match.scene` 承认四值；与 `direction=inbound` 分键 |
| 场景修订 决定 B：direction / platform / locale | §1.1、§5 | 词汇表与来源照抄；画像匿名、随绑定可清；港澳台只走 locale |
| 场景修订 决定 C：scene × segment 双键；首批映射；MotuCloud 全场景；local/content 几乎不露出；无遥测开关 | §1.3、§4.5、§5 | 四维 AND；硬过滤丢商业项；MotuCloud `match *`；画像不做限速 |
| 场景修订 决定 E：检测信号三合成；电商→薇晓朵；国内/无电商→文派；不阻断；不写死；检测行+推荐标；核心服务 4→5 | §4.2–§4.5、§2.2 空源 | 实时 Woo 判检测行；签发表不进客户端 fallback；无推荐标仍渲染 |
| 场景修订 统筹补充：容错 ≤72h、标注更新时间 | §2.3 | 陈旧 transient 259200；界面用 `fetched_at` |
| 场景修订 拍板 4：M-UI-3 前定本稿 | 文首 | 本文即该契约 |
| 场景修订 拍板 5：Ed25519 + version/TTL + transient + cron，复用 ManifestVerifier | §2.1–§2.2 | 规范化 JSON 与 NoticeControl / Entitlements 同形 |
| 噪声决定 §1：不可达沿用最近一份 ≤72h；推荐清单同口径 | §2.3 | 验签失败 / 不可达 / 过期三表 |
| 供应商 D4：未购显示「了解 →」；先绑文派再连供应商 | §4.4 | `learn` 与未购的 `install` 都走 `/go/` |
| 供应商 D5 / ADR-004 商业前提：规则服务端下发，插件不写死 | §0、§3、§8 | 单一签名文档；空源不回退静态名单 |
| 供应商 P2：只有两个预置供应商 | §1.2 `provider` | 第三值不合法 |
| 供应商 P4：unreachable ≤72h | §2.2 陈旧缓存 | 与权益同一 TTL_STALE |
| 站点场景商业模型：domestic 几乎不露出 | §1.3、§7 | 被决定 E 修订：几乎不露出改挂 `direction=local\|content`；`scene=domestic` 无 Woo 只去薇晓朵、保留文派自研。ADR-004 原文不改，解释写在 §7 |
