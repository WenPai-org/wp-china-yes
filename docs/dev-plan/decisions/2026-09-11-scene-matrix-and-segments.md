# ADR-004 修订：站点场景矩阵 3→4 与经营画像分层（草案，待 feibisi 批）

日期：2026-09-11。起草：linuxjoy（统筹）。决定人：feibisi（方向已口头认可，本文待批后生效）。
关联：`2026-09-06-site-profile-and-scope.md`（ADR-004）、`2026-09-06-core-services-value-and-providers.md`（D1–D6）。

## 起因

现有三场景（domestic / crossborder / mixed）按"服务器 × 访客 × 管理员"定义，漏掉一类真实用户：
**服务器与管理员在海外、访客主力在中国大陆**（海外店主卖货给中国买家，内贸/进中国）。
同时港澳台人群的繁体字体、语言、支付推荐差异没有挂点。feibisi 提出补齐场景，
以支撑服务端数据推送、用户分层与商业化露出。

## 决定 A：网络场景矩阵 3→4（技术键）

| 场景 | 服务器 | 访客主力 | 管理员 | 接通档案 |
|------|--------|---------|--------|---------|
| domestic 国内站 | 大陆 | 大陆 | 大陆 | 更新镜像 + 公共库/头像全站改写（不变） |
| crossborder 跨境·外贸站 | 海外 | 海外 | 大陆 | 只改后台：字体/头像后台改写、心跳节流、出站屏蔽（不变） |
| **inbound 内贸·进中国站（新增）** | 海外 | 大陆 | 海外 | **只改前台**：字体/头像/Ajax 前台改写给大陆访客；更新直连（服务器在海外）；后台不动 |
| mixed 混合站 | 海外 | 混合 | 大陆 | 后台改写 + 前台按访客位置分流（不变） |

内贸与跨境互为镜像：一个管后台、一个管前台。诊断/线路检查、恢复模式等机制全部复用。

## 决定 B：经营画像（运营键，匿名）

`/binding` 的 profile 增加匿名画像字段（不含任何个人数据，随绑定可清除）：

| 字段 | 枚举（首批） | 来源 |
|------|-------------|------|
| direction | outbound 出海 / inbound 进中国 / local 本地 / content 内容 | 用户选择（向导第 1 步）+ 服务端可修订 |
| platform | woocommerce / none / … | 探测已装插件，用户可改 |
| locale | zh-CN / zh-HK / zh-TW / en / … | 站点语言 + 管理员语言 |

**港澳台**：作为 locale 属性（zh-HK / zh-TW）处理，不开新网络场景（其网络行为归入海外分支）。
升格条件：绑定画像数据中占比显著，再议独立场景或独立档案。

## 决定 C：推送与分层挂点

- 服务端露出规则（机制沿用 ADR-004 的商业内容下发）按 **scene × segment** 双键定向。
- 首批映射：
  - outbound + woocommerce → 微信支付 for WooCommerce、订单微信通知（薇晓朵）
  - inbound → 前台加速包、中国可达性检测（文派服务小工具）、中文字体；（未来）境内 CDN 与合规指引
  - locale zh-HK / zh-TW → 繁体字体服务
  - local / content → 几乎不露出（D5 既定）
- 不提供遥测开关（feibisi 既定决定）；画像仅用于露出与默认组合，不用于差异化限速。

## 决定 D：对在途物的影响

- 规格：`rest-api.md` 的 `/binding` profile 增 direction/platform/locale；场景枚举 +inbound。
- admin-ui-spec：设置页场景卡 4 张（2×2）、内贸版概览与服务页区块定义。
- 原型：`e/v7` 分支已出——场景卡 4 张、`overview-inbound.html`、`services-inbound.html`（内贸版可用服务）。
- 任务：M-SCOPE 系列补 inbound 接通档案；M-UI-3 服务页按 segment 露出；M-STATS 事件文案带场景。
