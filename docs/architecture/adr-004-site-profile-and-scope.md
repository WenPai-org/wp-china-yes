# ADR-004：站点场景（Profile）与功能作用域（Scope）

状态：已定 2026-09-06  
日期：2026-09-06  
来源：[`docs/dev-plan/decisions/2026-09-06-site-profile-and-scope.md`](../dev-plan/decisions/2026-09-06-site-profile-and-scope.md)（feibisi / linuxjoy 定稿；4.0 首发必需项）

## 解决什么问题

4.0 规划与 `Connectivity` 模块没有任何后台/前台作用域区分；定稿 §7.1–§7.5 只有一类用户画像（国内站）。跨境 / 外贸站——服务器与访客在海外、只有管理员在中国大陆——需要「国内优化只作用于后台」。3.8 的 `admincdn` 复选框组曾经区分后台加速与前台加速；4.0 没有对应能力，且 M4-02b 把 `admin` token 归入 `ignored`。本 ADR 把已定稿的场景与作用域写进架构合同。

## 背景

feibisi：4.0 的功能全是国内用户需求。跨境电商 / 外贸客户的痛点没有解决，尤其「国内优化加速只应作用于后台」。

核实：

- `Connectivity` 三模块（WordPress.org / 公共库 / 头像）对所有请求一视同仁。
- 3.8 `admincdn` 的 `admin` token：`is_admin()` 时把 `wp-admin|wp-includes/(css|js)` 改写到 `wpstatic.admincdn.com/{wp_version}/`，前台不动。M4-02b 将该 token 列入 `ignored`（**本决定撤销**）。
- `wpstatic.admincdn.com` 对 7.1 / 6.8.2 / 6.7.1 全路径返回 410 Gone：3.8 的后台加速目前也是坏的。devops 另开运维任务，不在本 ADR。

## 决定

### 场景（profile）是 4.0 一等概念

`wpcy_settings.profile`（多站点：`wpcy_network_settings.profile`，站点可覆盖）取值：

| 值 | 名称 | 定义 |
|----|------|------|
| `domestic` | 国内站 | 服务器、访客、管理员都在中国大陆 |
| `crossborder` | 跨境 / 外贸站 | 服务器与访客在海外，管理员在中国大陆 |
| `mixed` | 混合站 | 服务器在海外，访客中外都有，管理员在中国大陆 |

- 首次向导第一步就是选场景；插件给出建议值（见下「场景建议」），用户确认。
- 升级站（3.x → 4.0）未选前视为 `domestic`，概览页给一条「确认你的站点场景」提示，不打断。
- 场景只决定默认组合；每项功能仍可单独改。切换场景 = 重置连通性各项为该场景默认（改前确认）。

字段、默认矩阵、升级函数见 [`docs/specs/config-schema.md`](../specs/config-schema.md)。REST 见 [`docs/specs/rest-api.md`](../specs/rest-api.md)。界面词条见 [`docs/design/admin-ui-spec.md`](../design/admin-ui-spec.md) §2 / §4。

### 连通性每项功能带作用域（scope）

`scope` 枚举：`both` / `admin` / `frontend` / `off`。

判定：`is_admin()`（含 `admin-ajax` / REST 带 `X-WP-Nonce` 的后台请求视为 admin；前台 REST 视为 frontend；WP-CLI 视为 admin；WP-Cron 视为 frontend）。

cron / WP-CLI 作用域（统筹拍板原文）：`WP-CLI` 视为 `admin`；`WP-Cron` 视为 `frontend`（保守：不做任何仅后台的改写；服务器侧取 .org 的行为不受作用域影响）。

默认矩阵（照抄决定 D2，不得改值）：

| 功能 | `domestic` 默认 | `crossborder` 默认 | `mixed` 默认 | 备注 |
|------|-----------------|--------------------|--------------|------|
| `connectivity.wordpress_org`（服务器取 .org API / 安装包） | `auto` | `off` | `auto` | 服务器侧行为，无 admin/frontend 之分；`auto` 靠探测决定 |
| `connectivity.public_assets`（Google Fonts / Ajax / CDNJS / jsDelivr / Emoji 改写） | scope `both`，默认五项 | scope `admin`，默认五项 | scope `admin`，默认五项 | 海外访客不改写 |
| `connectivity.avatar` | `cravatar_cn`，scope `both` | admin `cravatar_cn`；frontend `off`（保留 Gravatar） | admin `cravatar_cn`；frontend `cravatar_global` | avatar 的 scope 是两个独立值 |
| `windfonts` | 绑定后可开 | `off` | `off` | 前台功能；跨境站不默认 |
| `admin_assets`（后台静态资源加速） | `off` | `on` | `on` | **4.1 交付**；4.0 只预留 schema 键与开关位，界面显示「即将提供」，不做任何改写 |
| `connectivity.heartbeat`（仪表盘关心跳、编辑器 60s） | `off` | `on` | `on` | 跨境免费体验层；4.0 交付 |
| `connectivity.dashboard_feeds`（挡 WP 新闻/事件 widget 与 dashboard feed） | `allow` | `block` | `block` | 跨境免费体验层；不挡支付/物流 |
| `notice_control` / `announcements` / 诊断 / 恢复 | 不受场景影响 | 同 | 同 | |
| `telemetry` | 不受场景影响（常开） | 同 | 同 | payload 增加 `profile` 字段 |
| `privacy.data_residency` | 按主机表；A 档 `ingest_ready` 才改道 | 不执行 A 档改道；B 档记录仍做；C 档不碰 | 同 `crossborder` | 方案 A + 保险，见下「商业前提」与 [`data-residency-ruleset.md`](../specs/data-residency-ruleset.md) |

头像不用单一 `scope` 键：`connectivity.avatar.admin` 与 `connectivity.avatar.frontend` 为两个独立枚举值。旧单值迁移：两个值都等于它。

`admin_assets` 是顶层键 `wpcy_settings.admin_assets`（枚举 `on` \| `off`），不是 `connectivity` 子键。4.0 无运行时行为。可进 `wpcy_site_overrides`（统筹拍板接受）。

`privacy.data_residency`（统筹拍板原文）：采用方案 A + 保险。A 档改道跟 `profile` 闸：`domestic` 维持现状（`ingest_ready` 才改道）；`crossborder` / `mixed` 不执行 A 档改道，B 档记录仍做，C 档不碰。保险：`profile=domestic` 时即使 geo 判定服务器在海外也改道（用户自称国内站以用户为准）。理由：A 档要挡的是"中国站数据出境"，不是"海外站回中国"；海外服务器绕国内云桥增加失败面，与叶子要解的问题方向相反。闸在运行时读 `profile`，切换场景不改 `data_residency` 设置键。

### 迁移（修正 M4-02b）

- 3.8 `admincdn` 含 `admin`，或 3.9 `admincdn_files` 含 `admin` → 写 `wpcy_settings.admin_assets = 'on'`（4.0 无实现，仅保存意图，4.1 生效），并在迁移报告里列出「后台加速：已保留设置，4.1 起生效」。**不再进 `ignored`。**
- `frontend` token、`bootstrapcdn` 仍进 `ignored`。
- 场景推断：3.x 数据不足以判断服务器位置 → 一律 `domestic` + 概览提示确认；不猜。

### 场景建议（检测）

- 服务器：出站 IP 归属地（经 `api.wenpai.net` 的 geo 应答；失败则不建议）。
- 管理员：浏览器 `Accept-Language` / 时区（向导页 JS 采集，不存储原值，只存推断结论）。
- 规则：服务器境外 + 管理员境内 → 建议 `crossborder`；服务器境内 → 建议 `domestic`；其它 → 不建议，让用户选。
- 检测结果只用于向导建议，不自动切换。
- REST：`GET /wpcy/v1/profile/suggest`，权限同 settings。不返回 IP 原值。

### 语言

`crossborder` / `mixed` 场景的管理员可能是外籍员工：4.0 后台 en_US 翻译必须完整（进 M4-03 验收）；按 WordPress 用户语言（`get_user_locale()`）显示，不做插件自己的语言开关。

## 商业前提

连通性全部免费、无配额、无套餐。此前「基线 / 受限免费 / 付费」三层作废；`Services/Entitlements` 里不再为连通性功能建配额，只承载第三方/文派服务（Windfonts、wei-*、小工具）的权益。

叶子是接入客户端与分发渠道，不卖功能。商业价值 = 识别跨境 WooCommerce 店（`profile` + telemetry 的 Woo 字段）→ 在其后台经小工具容器分发文派付费产品（wei-* 支付/登录/通知/发票、微小朵服务）→ `/go/` 成交。小工具容器首批内容是这些入口。Windfonts、Cravatar、公共库等资源的限流由各平台自己做；叶子按场景与作用域决定接哪个源，不管额度。插件内不出现配额、「配额用尽降级到上游」逻辑；`Services/Entitlements` / `Degrade` 只服务于小工具与服务分发链。

商业内容露出条件由服务端规则下发（场景为 `crossborder`/`mixed` 且检测到 WooCommerce），与通知规则、公告同一下发机制；`domestic` 场景几乎不露出。插件内不写死露出条件。

`admin_assets` 不规划商业化：4.1 作为免费体验项交付。

跨境场景免费体验层（Heartbeat 分屏节流、挡仪表盘外部内容、从管理员浏览器测速的连接诊断）并入 [M-SCOPE-1](../dev-plan/tasks/M-SCOPE-1.md)；UI 呈现进 [M-SCOPE-UI](../dev-plan/tasks/M-SCOPE-UI.md)。设置键与默认见 [`config-schema.md`](../specs/config-schema.md)。

## 约束

- 决定本身不许改。异议进任务报告「有疑问」，不在实现里改默认矩阵。
- 4.0 不实现 `admin_assets` 改写；schema 与界面开关位必须预留。
- 不自动切换场景。
- 管理页面任何位置不出现面向用户的「遥测」「匿名数据」「隐私开关」；报告 payload 可含 `profile`，界面不露出报告开关。
- 第三方商业域名一律 `https://wpcy.com/go/…`。
- `privacy.data_residency` 采用方案 A + 保险（见上）；本 ADR 不改主机表条目，只闸 A 档是否执行。
- 连通性无配额；不为 WordPress.org / 公共库 / 头像 / `admin_assets` 建权益项。

## 备选方案与放弃理由

### 只做两档（`domestic` / `crossborder`，不要 `mixed`）

放弃。混合站（服务器在海外、访客中外都有、管理员在中国大陆）的头像需要后台 `cravatar_cn`、前台 `cravatar_global`。两档无法表达这组默认；产品明确三种画像。

### 不做 profile 只做 scope

放弃。没有场景默认时，跨境站长必须对每一项手选 `admin` / `frontend` / `both` / `off`，首次配置成本高且容易把前台公共库改写漏开到海外访客。profile 提供默认可单独改，是一等概念，不是 scope 的别名。

### 用 IP 自动切换

放弃。出站 IP 不能代表访客；反向代理 / CDN 会让服务器归属地误判；自动切换会在用户不知情时改写前台。决定 D4：检测只用于向导建议，不自动切换。

## 后果

正面：

- 国内站默认与今天 4.0 行为一致（公共库前后台都改写、头像 `cravatar_cn`）。
- 跨境 / 混合站默认不改写海外访客的公共库；头像后台走国内线路。
- 3.8 勾过「后台加速」的意图保留到 `admin_assets=on`，不再进 `ignored`。
- 向导第一步就能选对默认组合，每项仍可单独改。

代价：

- `schema_version` 从 1 升到 2：`public_assets` 由数组改为含 `items` + `scope` 的对象；`avatar` 由单值改为 `admin` / `frontend` 两个值。已有 4.0 v1 option 必须走升级函数。
- `src/Core/Scope.php` 已占用「站点 vs 网络」含义；请求级 admin/frontend 判定不能改写该类（实现落在 `Connectivity\Scope::current()`，见 M-SCOPE-1）。
- 后台 en_US 翻译成为 M4-03 门禁。
- `admin_assets` 4.0 只是死键：用户打开开关也不会改写，必须用「即将提供」说清楚。
- geo 依赖 `api.wenpai.net`；路径与应答格式待 wenpai-net 侧提供，插件先按 `{ "country": "CN" }` 契约 mock。

## 验收

- `docs/specs/config-schema.md` 含 `profile`、`connectivity.public_assets.scope`、`connectivity.avatar.admin` / `frontend`、`admin_assets`、`connectivity.heartbeat`、`connectivity.dashboard_feeds`、`schema_version` const 2 与 `upgrade_1_to_2` 描述；默认矩阵与上表逐格一致；`privacy.data_residency` 为方案 A + 保险。
- `docs/specs/rest-api.md` 含 settings 字段更新、`GET /profile/suggest`、`GET/POST /diagnostics/client-probe`。
- `docs/specs/data-residency-ruleset.md` 含「与站点场景的关系」。
- `docs/design/admin-ui-spec.md` §2 有场景条目，§4 含本决定全部界面用词，无「绑定后可用配额」。
- 引擎实现见 [`docs/dev-plan/tasks/M-SCOPE-1.md`](../dev-plan/tasks/M-SCOPE-1.md)；界面实现进设计门禁，需求见 [`docs/dev-plan/tasks/M-SCOPE-UI.md`](../dev-plan/tasks/M-SCOPE-UI.md)。

## 不做什么

- 不做 WooCommerce 跨境支付 / 物流。
- 不做插件内语言切换。
- 4.0 不实现 `admin_assets` 改写。
- 不自动切换场景。
- 不改决定文件原文。
- 不把 `wpstatic.admincdn.com` 410 的运维结论写进插件仓（另开 devops 任务）。
