# 数据驻留主机表

状态：草案（M0）· 来源：linuxjoy 定稿 §7.5a / §7.1a / §7.1c

本文冻结 `Privacy/DataResidency` 的版本化主机表。政策来源：`linuxjoy docs/ops/wenpai-leaf-telemetry-reroute.md` §4a / §5。用户不可编辑；诊断页只读展示。不得在本文新增产品决定；空白处标「待定（M0）」。

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
  "signature": "…"
}
```

插件内置一份基线 ruleset（随版本发布）。云桥可下发更高 `ruleset_version` 的签名增量；验签失败丢弃，沿用当前有效（内置或上一份已验签增量）。

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
5. 诊断页只读展示当前生效版本、档位、条目、各 `reroute` 条的 `ingest_ready` 状态。
6. 用户不可加域名、不可改条目。

`wpcy_settings.data_residency.ruleset_version` 记录当前生效版本号，供诊断与支持对照。

## 8. 重签命令

规范化规则与 §1 / apps manifest §1.3 相同：去掉 `signature` 后键字典序、UTF-8、无多余空白，对该字节做 Ed25519 分离签名，Base64 写回 `signature`。`--kid` 写入载荷（可选字段，缺省 `wpcy-ruleset-2026`）。

测试密钥（**TEST ONLY，禁止用于生产**）：

```bash
php scripts/sign-ruleset.php src/Privacy/rulesets/baseline.json tests/fixtures/keys/wpcy-test-ed25519.key --kid wpcy-ruleset-2026
```

生产签发见 linuxjoy 定稿 §7.5b-3（devops 在 feicode-prod 生成，不在本仓执行）。
