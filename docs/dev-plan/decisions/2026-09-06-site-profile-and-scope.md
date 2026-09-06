# 统筹决定：站点场景（Profile）与功能作用域（Scope）

日期：2026-09-06。决定人：feibisi（产品）/ linuxjoy（统筹）。状态：**已定稿，4.0 首发必需项**。

## 起因

feibisi：4.0 的功能"全是国内用户需求"。跨境电商 / 外贸客户——服务器与访客在海外、只有管理员人在国内——的痛点没有解决，尤其"国内优化加速只应作用于后台"。统筹核实：

- 4.0 规划与 `Connectivity` 模块没有任何后台/前台作用域区分；定稿 §7.1–§7.5 只有一类用户画像。
- 3.8 的 `admincdn` 复选框组区分"后台加速"（`admin`：`is_admin()` 时把 `wp-admin|wp-includes/(css|js)` 改写到 `wpstatic.admincdn.com/{wp_version}/`，前台不动）与"前台加速"（`frontend`）；4.0 没有对应能力，且 M4-02b 把 `admin` token 归入 `ignored`（**本决定撤销该条**）。
- `wpstatic.admincdn.com` 对 7.1 / 6.8.2 / 6.7.1 全路径返回 410 Gone：3.8 的后台加速目前也是坏的。devops 待确认下线时间与原因。

## 决定

### D1 场景（profile）是 4.0 一等概念

`wpcy_settings.profile`（多站点：`wpcy_network_settings.profile`，站点可覆盖）取值：

| 值 | 名称 | 定义 |
|----|------|------|
| `domestic` | 国内站 | 服务器、访客、管理员都在中国大陆 |
| `crossborder` | 跨境 / 外贸站 | 服务器与访客在海外，管理员在中国大陆 |
| `mixed` | 混合站 | 服务器在海外，访客中外都有，管理员在中国大陆 |

- 首次向导**第一步**就是选场景；插件给出**建议值**（见 D4），用户确认。升级站（3.x → 4.0）未选前视为 `domestic`，概览页给一条"确认你的站点场景"提示，不打断。
- 场景只决定**默认组合**；每项功能仍可单独改。切换场景 = 重置连通性各项为该场景默认（改前确认）。

### D2 连通性每项功能带作用域（scope）

`scope` 枚举：`both` / `admin` / `frontend` / `off`。判定：`is_admin()`（含 `admin-ajax` / REST 带 `X-WP-Nonce` 的后台请求视为 admin；前台 REST 视为 frontend；WP-CLI 视为 admin）。

| 功能 | `domestic` 默认 | `crossborder` 默认 | `mixed` 默认 | 备注 |
|------|-----------------|--------------------|--------------|------|
| `connectivity.wordpress_org`（服务器取 .org API / 安装包） | `auto` | `off` | `auto` | 服务器侧行为，无 admin/frontend 之分；`auto` 靠探测决定 |
| `connectivity.public_assets`（Google Fonts / Ajax / CDNJS / jsDelivr / Emoji 改写） | scope `both`，默认五项 | scope `admin`，默认五项 | scope `admin`，默认五项 | 海外访客不改写 |
| `connectivity.avatar` | `cravatar_cn`，scope `both` | admin `cravatar_cn`；frontend `off`（保留 Gravatar） | admin `cravatar_cn`；frontend `cravatar_global` | avatar 的 scope 是**两个独立值** |
| `windfonts` | 绑定后可开 | `off` | `off` | 前台功能；跨境站不默认 |
| `admin_assets`（后台静态资源加速） | `off` | `on` | `on` | **4.1 交付**；4.0 只预留 schema 键与开关位，界面显示"即将提供"，不做任何改写 |
| `notice_control` / `announcements` / 诊断 / 恢复 | 不受场景影响 | 同 | 同 | |
| `telemetry` | 不受场景影响（常开） | 同 | 同 | payload 增加 `profile` 字段 |
| `privacy.data_residency` | 按主机表 | **统筹待定**：服务器在海外时 A 档改道是否仍执行 | 同待定 | 规格任务须提出方案与理由，统筹拍板前保持现状（按主机表） |

### D3 迁移（修正 M4-02b）

- 3.8 `admincdn` 含 `admin`，或 3.9 `admincdn_files` 含 `admin` → 写 `wpcy_settings.admin_assets = 'on'`（4.0 无实现，仅保存意图，4.1 生效），并在迁移报告里列出"后台加速：已保留设置，4.1 起生效"。**不再进 `ignored`。**
- `frontend` token、`bootstrapcdn` 仍进 `ignored`。
- 场景推断：3.x 数据不足以判断服务器位置 → 一律 `domestic` + 概览提示确认；不猜。

### D4 场景建议（检测）

- 服务器：出站 IP 归属地（经 `api.wenpai.net` 的 geo 应答；失败则不建议）。
- 管理员：浏览器 `Accept-Language` / 时区（向导页 JS 采集，不存储原值，只存推断结论）。
- 规则：服务器境外 + 管理员境内 → 建议 `crossborder`；服务器境内 → 建议 `domestic`；其它 → 不建议，让用户选。
- 检测结果只用于向导建议，不自动切换。

### D5 语言

`crossborder` / `mixed` 场景的管理员可能是外籍员工：4.0 后台 en_US 翻译必须完整（进 M4-03 验收）；按 WordPress 用户语言（`get_user_locale()`）显示，不做插件自己的语言开关。

## 影响与任务拆分（由规格任务展开成任务书）

- **M-SCOPE-0（文档）**：ADR-004、`docs/specs/config-schema.md`（新增 `profile`、各项 `scope`、`admin_assets` 预留）、`rest-api.md`（settings 字段、`GET /profile/suggest`）、`admin-ui-spec.md` 只在 §4 词表加词与在 §2 信息架构加"场景"条目（正文编号规则见 design-sop）、`4.0-rewrite-plan.md` 相应节、`M4-02` 矩阵补 D3 用例。
- **M-SCOPE-1（引擎）**：Schema + Repository 迁移（`schema_version` +1）、`Connectivity` 三模块按 scope 门控、`Profile` 服务（默认矩阵、切换重置）、`ProfileSuggest`（D4）、REST、迁移映射修正、单元 + e2e。
- **M-UI（产品化）**：向导第一步场景选择、概览"确认场景"提示、连接优化页每项作用域的呈现——**进设计门禁，随原型 C 一起出**。
- **M4-03**：en_US 完整性验收；`admin_assets` 文案"即将提供"。
- **devops**：`wpstatic.admincdn.com` 410 的原因与 4.1 复活/替代方案（另开运维任务，不在插件仓）。

## 不做什么

- 不做 WooCommerce 跨境支付/物流；不做插件内语言切换；4.0 不实现 `admin_assets` 改写；不自动切换场景。
## 补充决定（2026-09-06 晚，商业模型；见 linuxjoy 定稿 §7.1c）

- **连通性全部免费、无配额、无套餐**。此前"基线 / 受限免费 / 付费"三层作废；`Services/Entitlements` 里不再为连通性功能建配额，只承载第三方/文派服务（Windfonts、wei-*、小工具）的权益。
- **叶子是渠道，不卖功能**：商业价值 = 识别跨境 WooCommerce 店（`profile` + telemetry 的 Woo 字段）→ 在其后台经小工具容器分发文派付费产品（wei-* 支付/登录/通知/发票、微小朵服务）→ `/go/` 成交。小工具容器首批内容是这些入口。
- **商业内容露出条件由服务端规则下发**（场景为 `crossborder`/`mixed` 且检测到 WooCommerce），与通知规则、公告同一下发机制；`domestic` 场景几乎不露出。插件内不写死露出条件。
- **admin_assets 不规划商业化**：4.1 作为免费体验项交付。
- **跨境场景免费体验层**并入 M-SCOPE-1 范围（调研 F2/F3/F6/F10/F11：字体与头像后台作用域、Heartbeat 分屏节流、挡仪表盘外部内容、从管理员浏览器测速的连接诊断）。M-SCOPE-0 若已完成，ADR-004 与 M-SCOPE-1 任务书需按本节补一节"商业前提"，由统筹在审查时核。
- 调研出处：linuxjoy `docs/research/2026-09-06-crossborder-wp-admin-grok.md`。

## 补充（2026-09-06 晚，feibisi）：叶子只是接入客户端

Windfonts、Cravatar、公共库等资源的限流由各平台自己做；叶子只是接入客户端，按场景与作用域决定接哪个源，**不管额度**。当前资源跑在 cybercdn 上，用量可控。因此：插件内不出现配额、"配额用尽降级到上游"逻辑；`Services/Entitlements` / `Degrade` 只服务于小工具与服务分发链；词表删除"绑定后可用配额"一类文案（Windfonts 是否需要绑定由 Windfonts 平台决定，插件按服务端应答呈现）。各平台限流策略另议。

## 统筹拍板（2026-09-06 晚，回应 M-SCOPE-0 报告的疑问）

- **`privacy.data_residency`：采用方案 A + 保险**。A 档改道跟 `profile` 闸：`domestic` 维持现状（`ingest_ready` 才改道）；`crossborder` / `mixed` 不执行 A 档改道，B 档记录仍做，C 档不碰。保险：`profile=domestic` 时即使 geo 判定服务器在海外也改道（用户自称国内站以用户为准）。理由：A 档要挡的是"中国站数据出境"，不是"海外站回中国"；海外服务器绕国内云桥增加失败面，与叶子要解的问题方向相反。
- **cron 的作用域**：`WP-CLI` 视为 `admin`；`WP-Cron` 视为 `frontend`（保守：不做任何仅后台的改写；服务器侧取 .org 的行为不受作用域影响）。
- **D4 匹配表**：接受 M-SCOPE-0 写死的规则（`server_country === "CN"` 不含 HK/MO/TW；管理员境内 = locale `/^zh[-_]CN/i` 或时区 `Asia/Shanghai|Asia/Chongqing|Asia/Urumqi|PRC`）；`mixed` 不被建议、只能手选，接受。
- **`admin_assets` 进 `wpcy_site_overrides`**：接受。
- **不加 `profile_confirmed` 键**：接受，以"有迁移备份且从未 PUT `profile`"判断。
- **M-SCOPE-1 任务书须补两节**（由 M-SCOPE-0b 完成）："商业前提"（本文件"补充决定"两节：连通性无配额、叶子是接入客户端与渠道、露出规则服务端下发）与"跨境场景免费体验层"（调研 F2/F3/F6/F10/F11 中属于引擎侧的：字体与头像后台作用域即 D2 本身；Heartbeat 分屏节流；挡仪表盘外部内容；从管理员浏览器测速的连接诊断 REST 端点。UI 呈现进 M-SCOPE-UI）。§4 词表删除"绑定后可用配额"一类文案。
