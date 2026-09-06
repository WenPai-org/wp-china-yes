# 数据驻留主机表

状态：草案（M0）· 来源：linuxjoy 定稿 §7.5a / §7.1a / §7.1c；2026-09-06 按 [HTTP Block 并入决定](../dev-plan/decisions/2026-09-06-http-block-merge-and-feature-absorption.md) A 节增加 `protected_hosts`、噪声拦截包与三层优先级。

本文冻结 `Privacy/DataResidency` 的版本化主机表。政策来源：`linuxjoy docs/ops/wenpai-leaf-telemetry-reroute.md` §4a / §5。用户不可编辑；诊断页只读展示。不得在本文新增产品决定；空白处标「待定（M0）」。L0 / 噪声包字段来自已定稿决定 A 节，不是本文自行加的产品决定。

原则：改道 = 挡出国并在国内给出客户端接受的应答；禁止复制一份再放行。每一档的门禁是「国内替代应答是否就位」。

## 1. 文档形状

```json
{
  "ruleset_version": 3,
  "issued_at": "2026-09-03T00:00:00Z",
  "tiers": {
    "A": [
      {
        "host": "tracking.woocommerce.com",
        "match": "exact",
        "action": "reroute",
        "target": "https://updates.wenpai.net/ingest/woo-tracker",
        "enabled_when": "ingest_ready"
      }
    ],
    "B": [
      {
        "host": "rest.akismet.com",
        "match": "suffix",
        "action": "record",
        "data_class": "comments"
      }
    ],
    "C": [
      { "host": "*", "action": "ignore" }
    ]
  },
  "protected_hosts": [
    { "host": "wenpai.net", "match": "suffix" },
    { "host": "wpcy.com", "match": "suffix" },
    { "host": "cravatar.cn", "match": "exact" },
    { "host": "cravatar.com", "match": "suffix" },
    { "host": "admincdn.com", "match": "suffix" }
  ],
  "noise_block": [
    {
      "host": "example-license-heartbeat.invalid",
      "match": "exact",
      "note": "placeholder; production hosts from signed increment"
    }
  ],
  "signature": "…"
}
```

插件内置一份基线 ruleset（随版本发布）。云桥可下发更高 `ruleset_version` 的签名增量；验签失败丢弃，沿用当前有效（内置或上一份已验签增量）。`protected_hosts` 与 `noise_block` 同此：验签失败丢弃增量、沿用内置（硬编码清单 ∪ 上一份已验签增量）。不新增规则类型、不另起 kid。

签名算法与 apps manifest 相同：Ed25519，规范化 JSON（键字典序、UTF-8、无多余空白），覆盖去掉 `signature` 后的对象。公钥可与 apps 共用或独立。**待定（M0）**：驻留 ruleset 与 apps 索引是否共用同一把 Ed25519 公钥，由安全 / 发布流程定。

## 2. 字段

| 字段 | 规则 |
|---|---|
| `ruleset_version` | 正整数；增量必须严格大于当前生效版本才应用 |
| `issued_at` | UTC ISO 8601 |
| `tiers.A` / `B` / `C` | 数组；按文档顺序匹配，先 A 后 B 后 C |
| `host` | 主机名；C 档 `"*"` 表示其余全部 |
| `match` | `exact`（整主机相等）或 `suffix`（`host` 为后缀，如 `rest.akismet.com` 匹配该主机及其子域）。缺省 `exact` |
| `action` | `reroute` \| `record` \| `ignore` |
| `target` | 仅 `reroute`：改写后的绝对 URL（HTTPS） |
| `enabled_when` | 仅 `reroute`：见 §3 |
| `data_class` | 仅 `record`：见 §5 |
| `kid` | 可选 string；`wpcy-ruleset-2026` 或 `wpcy-apps-2026`。存在时按 `kid` 选公钥。缺省不写。一套测试钥两个 `kid`（定稿 §7.5b-3） |
| `signature` | Base64 Ed25519 |
| `protected_hosts` | 数组。L0 受保护主机（签名增量）。每条 `{ "host": string, "match": "exact"\|"suffix" }`。`match` 缺省 `exact`。与驻留表同一套匹配语义。与客户端硬编码清单（§10）并集生效 |
| `noise_block` | 数组。文派噪声拦截包条目。每条 `{ "host": string, "match": "exact"\|"suffix", "note"?: string }`。**不含** `.org` / CDN / 文派主机（那些走 L0 或 Connectivity，不得出现在本数组）。用户不可编辑条目；整包开关键是设置 `modules.noise_block.enabled`（见 [`config-schema.md`](config-schema.md)），不是本文件字段 |

## 3. `enabled_when`

枚举：

| 值 | 含义 |
|---|---|
| `always` | 始终改写 |
| `ingest_ready` | 云桥对该 `target` 的健康端点返回 200 才启用 |

A 档默认 `ingest_ready`——这是「云桥没接口前不改 URL」的机制化表达。健康端点 URL 与探测频率 **待定（M0）**：由云桥给出合同后写入 `Privacy/DataResidency`。未就绪时该条目标按「不改写」处理，不得回落到 `record` 或放行后再复制。

## 4. `action`

| 值 | 行为 |
|---|---|
| `reroute` | `pre_http_request` 改目的地，原厂收不到 |
| `record` | 放行，只记 `host` / `data_class` / `count` / `last_seen`，**不记正文、不记 URL 查询串** |
| `ignore` | 不拦、不记正文 |

C 档 `host: "*"` 的 `ignore` 吃掉未命中 A/B 的请求。支付网关、物流、验证码、用户明确配置的第三方 API 落在这里。

## 5. `data_class`

枚举：`telemetry`、`comments`、`licensing`、`connection`、`stats`。

入库字段筛选仍以 `linuxjoy docs/ops/wenpai-leaf-telemetry-reroute.md` §5 为准（评论正文、顾客联系方式、许可密钥、精确地理、订单正文、Automattic 侧身份不进库；管理员邮箱、订单金额经 Tracker 改道后留国内入库）。本 ruleset 不携带字段白名单；字段筛在云桥入库层。

## 6. 初始 A / B / C 内容（定稿 §7.1a）

基线 `ruleset_version`：**待定（M0）** 发版时冻结为内置整数；下列条目必须出现在首发基线。`target` 主机与路径在云桥合同未定时用占位，启用仍受 `ingest_ready` 约束。`api.wordpress.org` 由 Connectivity 负责，不进驻留主机表；政策表里的「已改」仅为现状说明。

### A · 纯上报（`action: reroute`，`enabled_when: ingest_ready`）

| host | match | target（占位） | 说明 |
|---|---|---|---|
| `api.wordpress.org` | `exact` | `https://api.wenpai.net/`（路径按原请求保留） | **已迁 Connectivity**（`WordPressOrgModule`）。不进驻留主机表，基线无此主机 |
| `tracking.woocommerce.com` | `exact` | `https://updates.wenpai.net/ingest/woo-tracker` | Woo Tracker |
| `pixel.wp.com` | `exact` | **待定（M0）**：云桥 Tracks ingest URL | Tracks |
| `stats.wp.com` | `exact` | **待定（M0）**：云桥 Tracks ingest URL | Tracks |
| Jetpack Stats 埋点主机 | **待定（M0）** | 云桥 ingest | 定稿写「Jetpack Stats 埋点」；具体主机名与 target 由实现对照现网请求、与云桥合同后补进本表 |

`api.wordpress.org` **已迁 Connectivity**：改写范围由 `WordPressOrgModule` 负责（version-check / plugins/update-check / themes/update-check）。驻留 `Ruleset` 不再对该主机做路径白名单。

### B · 功能型（`action: record`）

| host | match | data_class | 何时才得改成 reroute |
|---|---|---|---|
| `rest.akismet.com` | `suffix` | `comments` | 文派评论 / 反垃圾专用产品有等价应答 |
| Jetpack 连接 / 模块 API 主机 | **待定（M0）**：对照现网补主机名 | `connection` | 文派有等价应答 |
| Woo.com helper 许可与更新 | **待定（M0）**：对照现网补主机名 | `licensing` | 不替代，长期记录 |
| Freemius | **待定（M0）**：对照现网补主机名 | `licensing` | 不替代，长期记录 |
| EDD Software Licensing | **待定（M0）**：对照现网补主机名 | `licensing` | 不替代，长期记录 |

Jetpack Stats 若现网与连接 API 同主机，按路径分档，不得把功能 API 误放入 A。路径分档规则 **待定（M0）**。

### C · 不碰

```json
{ "host": "*", "action": "ignore" }
```

覆盖支付网关、物流、验证码、用户明确配置的第三方 API。不拦、不记正文。

## 7. 下发与生效

1. 插件启动加载内置基线。
2. 定期（建议 24h，**待定（M0）** 精确 cron）向云桥拉取增量。拉取 URL **待定（M0）**。
3. 验签失败：丢弃，记诊断，不替换。
4. `ruleset_version` 不大于当前：忽略。
5. 诊断页只读展示当前生效版本、档位、条目、各 `reroute` 条的 `ingest_ready` 状态；另展示 L0 / L1 / L2 三层只读视图（见 REST `GET /residency/protected`）。
6. 用户不可加域名、不可改 L1 条目。L2 本站清单是独立设置键，上限 20，见 config-schema；不可改 L0 / `noise_block` 条目。

`wpcy_settings.data_residency.ruleset_version` 记录当前生效版本号，供诊断与支持对照。

## 8. 重签命令

规范化规则与 §1 / apps manifest §1.3 相同：去掉 `signature` 后键字典序、UTF-8、无多余空白，对该字节做 Ed25519 分离签名，Base64 写回 `signature`。`--kid` 写入载荷（可选字段，缺省 `wpcy-ruleset-2026`）。

测试密钥（**TEST ONLY，禁止用于生产**）：

```bash
php scripts/sign-ruleset.php src/Privacy/rulesets/baseline.json tests/fixtures/keys/wpcy-test-ed25519.key --kid wpcy-ruleset-2026
```

生产签发见 linuxjoy 定稿 §7.5b-3（devops 在 feicode-prod 生成，不在本仓执行）。

## 9. 与站点场景的关系

统筹拍板原文：采用方案 A + 保险。A 档改道跟 `profile` 闸：`domestic` 维持现状（`ingest_ready` 才改道）；`crossborder` / `mixed` 不执行 A 档改道，B 档记录仍做，C 档不碰。保险：`profile=domestic` 时即使 geo 判定服务器在海外也改道（用户自称国内站以用户为准）。理由：A 档要挡的是"中国站数据出境"，不是"海外站回中国"；海外服务器绕国内云桥增加失败面，与叶子要解的问题方向相反。

闸在 `Privacy/DataResidency` 运行时读有效 `profile`，不改主机表条目，不新增 `data_residency` 设置键。切换场景不改本 option。实现见 [M-SCOPE-1](../dev-plan/tasks/M-SCOPE-1.md)；合同见 [ADR-004](../architecture/adr-004-site-profile-and-scope.md)。

## 10. 受保护主机（L0）与噪声拦截包

来源：[HTTP Block 并入决定](../dev-plan/decisions/2026-09-06-http-block-merge-and-feature-absorption.md) A1–A5。本节省略产品判断，只冻结合同。

### 10.1 `protected_hosts`（L0）

永远放行。用户规则（L2）命中无效。多站点不允许站点覆盖。

**匹配语义**与驻留表一致：`exact`（整主机相等）或 `suffix`（`host` 为后缀，匹配该主机及其子域）。不做用户正则、无通配、无路径。

**基线硬编码清单**（决定 A3 原文，客户端内置，即使签名增量缺失也生效）：

`*.wenpai.net`（含 `license.` / `api.` / `downloads.` / `updates.` / `ts.`）、`*.wpcy.com`、`cravatar.cn`、`cravatar.com`、`*.admincdn.com`、云桥 ingest 主机。

写成 ruleset 条目时：

| host | match | 覆盖 |
|------|-------|------|
| `wenpai.net` | `suffix` | `wenpai.net` 及其子域，含 `license.wenpai.net` / `api.wenpai.net` / `downloads.wenpai.net` / `updates.wenpai.net` / `ts.wenpai.net` |
| `wpcy.com` | `suffix` | `wpcy.com` 及其子域 |
| `cravatar.cn` | `exact` | A3 原文；仅该主机 |
| `cravatar.com` | `exact` | A3 原文无 `*.`。`cn.cravatar.com` / `en.cravatar.com` 不在本条；是否另加 suffix 条见报告疑问 |
| `admincdn.com` | `suffix` | `admincdn.com` 及其子域 |
| 云桥 ingest 主机 | **待定（M0）**：以 devops / 云桥合同 FQDN 为准，写入硬编码数组 | 与 `target` 主机一致 |

签名增量可**追加**条目，不得删除硬编码清单。验签失败：丢弃增量，沿用内置（硬编码 ∪ 上一份已验签增量）。公钥与驻留表同一套发布流程。

保护主机命中的表现（决定 A2 原文）：界面/REST 保存 L2 时**拒绝并提示**「文派服务不可拦截」；运行时对已存数据再过滤一层（静默忽略），防绕过 UI。

### 10.2 噪声拦截包（`noise_block`）

签名下发的 block 条（许可心跳、已知无用 API；**不含** `.org` / CDN / 文派主机）。与 NoticeControl 广告规则同一下发机制：文派维护条目，用户只能整包开关。

用户开关键：`modules.noise_block.enabled`（boolean，默认 `true`）。`false` 时本包全部不拦；`true` 时按条目 `exact` / `suffix` 命中则 block。用户不可增删改条目。

基线内置 `noise_block` 可为 `[]`；生产条目由签名增量提供。增量里若误含 L0 主机或 `.org` / CDN 主机，运行时忽略该条并记诊断（不得拦文派自己、不得拆镜像）。

### 10.3 三层优先级与命中行为（决定 A1 / A2 原文）

| 层 | 维护者 | 能力 | 边界 |
|----|--------|------|------|
| **L0 受保护主机** | 文派签名下发（`protected_hosts`，并入现有 Ed25519 ruleset）+ 客户端硬编码兜底 | 永远放行 | 用户规则命中无效；多站点不允许站点覆盖 |
| **L1 数据驻留表** | 文派签名下发（现有 A/B/C） | A 改道（跟 profile 闸）/ B 记录 / C 忽略 | 用户不可编辑；不可当拦截器 |
| **L2 本站拦截清单** | 用户（可选，仅网络管理员/单站管理员） | 只能 block | 仅 `exact` / `suffix` 主机匹配；上限 **20** 条；无正则、无通配、无路径；不能改道；不能放行 L0/L1 |

优先级：**L0 放行 → L1 驻留 → L2 拦截 → 默认放行**；后层不得推翻前层。噪声包不是第四层产品层（A1 只有三层）：它是签名 block 条，判定位置在 L0 / L1 未认领该请求之后、与 L2 之前；不得推翻 L0 放行，不得拦 L1 已 reroute / record 的主机。

`pre_http_request` 挂载顺序必须保证：L0 先于 L1 先于噪声包先于 L2。L1 C `ignore` 是「不拦、不记正文」，不是终态放行，L2 仍可拦。L2 实现见 [`config-schema.md`](config-schema.md) `modules.site_blocklist` 与 [M-BLOCK-1](../dev-plan/tasks/M-BLOCK-1.md)。

保护主机命中：保存时拒绝（REST 400 `wpcy_blocklist_protected_host`，message「文派服务不可拦截」）；运行时静默忽略已存的违规条。
