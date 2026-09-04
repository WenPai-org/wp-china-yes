# 文派叶子 4.0 后台界面设计规格

状态：定稿 v1.0 · 2026-09-04 · 设计 linuxjoy · 产品 feibisi
用途：HTML 原型（`docs/design/mockups/`）与 M1-08 / M2-05 / M2-06 的实现依据。原型与实现不得偏离本文的信息架构与状态定义；视觉细节以原型为准。

依据：ADR-002（React 应用 + 恢复页）、ADR-003（小工具容器）、`docs/specs/*`、linuxjoy `docs/plans/tokens/wpcy-brand-tokens.json`（品牌 token）、`linuxjoy docs/research/2026-09-03-wordpress-admin-desktop-and-new-apis.md`（官方组件方向）。

## 1. 设计原则

1. **像 WordPress，不像第三方。** 组件全部来自 `@wordpress/components` / `@wordpress/dataviews`，布局用 `@wordpress/admin-ui` 的 `<Page>`；不自造卡片、按钮、开关。品牌只体现在强调色（绿 `accent.primary`）与 Phosphor 图标。
2. **一屏一件事。** 每页首屏只回答一个问题：现在怎样 / 要不要我做什么。说明文字只伴随异常出现，正常项不解释自己。
3. **状态双编码。** 任何状态必有"圆点 + 文字"或"图标 + 文字"，不裸用颜色。绿 = 正常/品牌，琥珀 = 提醒/降级，红 = 故障/破坏性动作；绿色**不同时**承担"成功"语义（成功态用 `status.success`，即使色值接近）。
4. **不露出内部概念。** 界面不出现"遥测""匿名数据""opt-in""entitlement"这类词；用户看到的是"兼容性报告""权益""配额"。
5. **降级可见、失败诚实。** 服务端不可达、配额用尽、模块失败，都在界面上用琥珀/红条说明发生了什么、影响什么、下一步是什么；不假装成功。
6. **一个逃生口。** 恢复页是唯一服务端渲染页，只做两件事，无 JS 可用。

## 2. 信息架构

菜单：一个顶级菜单「文派叶子」（图标：叶子），四个子页 + 一个不在菜单里的恢复页（由「诊断」页和插件列表行内链接到达，菜单项由 PHP 注册但 `parent=null` 隐藏于菜单，直连 `?page=wpcy-recovery` 可达）。

```
文派叶子
├── 概览        ?page=wpcy            首屏：线路状态 · 需要处理 · 公告
├── 连接优化    ?page=wpcy-connect    设置：WordPress.org 源 · 公共库 · 头像 · 字体
├── 文派服务    ?page=wpcy-services   绑定状态 · 权益与配额 · 用量 · 小工具
└── 诊断        ?page=wpcy-diagnose   检查结果 · 被隐藏的通知 · 出站主机记录 · 导出 · 恢复页入口
    (恢复模式)  ?page=wpcy-recovery   服务端渲染
```

命令面板（⌘K）注册：「打开文派叶子概览」「打开连接优化」「打开文派服务」「运行连接诊断」「进入恢复模式」。

路由约束：四页各是一个菜单项，页内 Tab / 子视图只用 hash（`#/tab=…`），不用 History API 改 `?page=`，不访问 `window.top`。在 OpenStation 这类壳里 ⌘K 属于壳；叶子命令在经典后台走 Core 面板，若 `wp.os?.registerCommand` 存在则额外注册（feature detect），不自己抢快捷键。依据：ADR-002 硬约束、linuxjoy `docs/research/2026-09-04-openstation-analysis.md` E.1。

页面骨架（所有 React 页共用）：`<Page>` 顶栏 = 页标题 + 右侧一个主动作（若有）；左侧不再做自建导航（wp-admin 菜单已是导航）；内容区最大宽 1080px，窄列居中；底部无页脚。

## 3. 页面规格

### 3.1 概览 `wpcy`

首屏三段，自上而下：

**A. 恢复模式横幅**（仅 `recovery_mode=true` 时）：琥珀 Notice，「恢复模式已开启：全部 URL 改写与模块已停用。」按钮「退出恢复模式」（REST `POST /recovery {action:exit}`）。

**B. 线路状态卡组**（`Card` × 3，横排，窄屏纵排）：

| 卡 | 标题 | 主数字/状态 | 副文 | 动作 |
|----|------|-------------|------|------|
| WordPress.org 源 | 「更新与安装包」 | 圆点+「国内镜像正常」/「已回原始上游」/「不可用」 | 最近检查 N 分钟前 · 延迟 N ms | 「详情」→ 诊断 |
| 公共库与头像 | 「前端资源」 | 「N 项已加速」/「部分回退」 | 列出回退项名 | 「设置」→ 连接优化 |
| 文派服务 | 「站点绑定」 | 「已绑定」/「未绑定」/「绑定中」 | 已绑定：可用权益 N 项；未绑定：一句话说明绑定后能用什么 | 「前往」→ 文派服务 |

**C. 需要处理**（列表，最多 5 条，无则整段不渲染）：来源 = 诊断异常、配额用尽、模块失败、有可用升级。每条一行：图标 + 一句人话 + 一个动作按钮。例：「Windfonts 本月配额已用尽，已切回原始字体源。」→「查看权益」。

**D. 公告**（`Admin/Announcements`，最多 5 条，无缓存不渲染）：标题为链接（新窗口）、来源标签（文派茶馆 / 薇晓朵）、日期、右侧「×」关闭。无"更多"链接、无图片。

空状态（首次安装、未跑过诊断）：B 组显示「尚未检查」+ 主动作「立即检查」。

### 3.2 连接优化 `wpcy-connect`

一个 DataForm，分四组，顶栏主动作「保存」（未变更时禁用）。保存成功 Snackbar「已保存」。

| 组 | 字段 | 控件 | 说明文字（仅异常时） |
|----|------|------|------|
| WordPress.org 源 | `connectivity.wordpress_org` | RadioControl：自动（推荐）/ 关闭 | 自动 = 国内镜像优先，不可用时回原始上游 |
| 公共前端库 | `connectivity.public_assets[]` | CheckboxControl 列表：Google Fonts / Google Ajax / CDNJS / jsDelivr / Emoji | 每项右侧圆点显示当前节点状态 |
| 头像 | `connectivity.avatar` | RadioControl：Cravatar 中国 / Cravatar 国际 / 关闭 | — |
| 字体（可选模块） | `modules.windfonts` + 字体列表 | ToggleControl；开启后出现字体族选择（DataViews 列表，来源 API 缓存） | 未绑定站点：提示「绑定后可用配额」并禁用 |

多站点：网络管理员看到同页 + 顶部说明「网络策略，子站可覆盖」；子站管理员看到「已由网络设定」的只读态与「申请覆盖」（若 `allow_site_override`）。

### 3.3 文派服务 `wpcy-services`

三段：

**A. 站点绑定**（`Card`）：
- 未绑定：说明一句「绑定后可使用受限免费服务与小工具，数据保存在本站。」主动作「绑定本站」（自动，无账号）。绑定中：Spinner + 「等待文派服务器验证…」+ 「取消」。
- 已绑定：圆点「已绑定」+ 站点标识后 8 位 + 绑定时间 + 次级动作「解除绑定」（确认对话框，红色破坏性按钮）。
- 服务端不可达：琥珀 Notice「暂时无法连接文派服务，显示的是 N 小时前的状态。」

**B. 权益与配额**（DataViews 表格布局）：列 = 服务 / 状态（圆点+文字：可用 / 已用尽 / 已到期）/ 本期用量（进度条 `used/limit`）/ 重置时间 / 动作（「获取」→ `/go/{service}` 新窗口，仅非 active 时显示）。未绑定：空状态「绑定后显示」。

**C. 小工具**（DataViews 网格布局，卡片 = 图标 + 名称 + 一句描述 + 状态角标）：
- 状态角标：可用（无角标）/ 需权益（琥珀「获取」）/ 只读（灰「已到期」）/ 不可用（灰「离线」）。
- 点击可用工具 → 在本页内打开容器：顶部返回条（← 小工具名 · 版本 · 「在新窗口打开」不提供）+ 沙箱 iframe（高度随 `resize` 消息，上限 4000px）。
- 需权益工具点击 → 侧栏 `Modal`：工具介绍 + 「获取」按钮（`/go/`）。
- 索引不可达：段落级琥珀提示「小工具目录暂时不可用」，其余段正常。

### 3.4 诊断 `wpcy-diagnose`

顶栏主动作「立即检查」（REST `POST /diagnostics/run`，运行中按钮 Spinner）。四段 Tab（`TabPanel`）：

| Tab | 内容 | 控件 |
|-----|------|------|
| 连接检查 | 每个目标一行：目标名 / 结果圆点 / 延迟 / 最近检查 / 建议（仅异常） | DataViews 表格 |
| 被隐藏的通知 | NoticeControl 按规则隐藏的第三方通知：来源插件 / 匹配规则 / 首次隐藏 / 次数；说明「核心更新、安全与站点健康通知永不隐藏」 | DataViews 表格 |
| 出站主机记录 | DataResidency B 档记录：主机 / 数据类别 / 次数 / 最近时间 / 处置（记录中 / 已改道国内）；**不显示 URL 查询串与正文** | DataViews 表格 + 顶部一句「主机表由文派发布，用户不可编辑」 |
| 数据与恢复 | 「导出诊断报告」（JSON，不含凭据）· 「按小工具导出/删除数据」（DataViews 列表：工具 / 条目数 / 大小 / 动作）· 「进入恢复模式」（链接到 `?page=wpcy-recovery`，红色次级按钮） | Buttons |

### 3.5 恢复模式 `wpcy-recovery`（服务端渲染）

WP 原生 `wrap` 容器：`<h1>` 「文派叶子 · 恢复模式」；一段说明「如果后台样式错乱或站点无法访问，可在此一键停用所有 URL 改写与模块。此页不依赖 JavaScript。」两个表单按钮（各自 nonce）：

- 「关闭全部 URL 改写」（`button-primary`）
- 「停用全部模块」（`button-secondary`）

已在恢复模式时显示：绿色 Notice「恢复模式已开启」+ 按钮「退出恢复模式」。页底链接「返回概览」。禁止在此页放任何其它设置。

## 4. 状态与文案词表

| 内部状态 | 界面文字 | 视觉 |
|----------|----------|------|
| mirror ok | 国内镜像正常 | 绿点 |
| mirror fallback | 已回原始上游 | 琥珀点 |
| mirror down & upstream down | 不可用 | 红点 |
| entitlement active | 可用 | 绿点 |
| entitlement exhausted | 本期已用尽 | 琥珀点 + 「获取」 |
| entitlement expired | 已到期 | 灰点 + 「获取」 |
| services unreachable | 暂时无法连接文派服务 | 琥珀 Notice |
| recovery_mode | 恢复模式已开启 | 琥珀 Notice（概览）/ 绿 Notice（恢复页） |
| binding pending | 等待验证 | Spinner |
| app offline | 离线 | 灰角标 |

禁用词：遥测、匿名数据、opt-in、entitlement、SaaS、套餐（用「权益」）、Pro。

## 5. 视觉 token 映射

- 强调色：`--wp-admin-theme-color` ← `semantic.accent.primary`（`#02b930`）；hover ← `semantic.accent.primaryHover`（`#03d537`）。**只覆盖这一组变量**；其余用 wp-admin 默认。
- 状态色：成功 `status.success`、提醒 `status.warning`、危险 `status.danger`（token 里暂引灰阶，M0 待设计评审补独立色；原型先用 wp-admin 默认的 `#00a32a` / `#dba617` / `#d63638` 作占位并在原型注明）。
- 图标：Phosphor `regular`，20px；页图标：概览 `Leaf`、连接优化 `Plugs`、文派服务 `SquaresFour`、诊断 `Stethoscope`、恢复 `LifeBuoy`。
- 字体、字号、间距：全部 wp-admin 默认，不引入品牌字体。

## 6. 响应式与无障碍

- ≥ 1080px：卡组横排；< 782px（wp-admin 折叠断点）：纵排，DataViews 自动切列表布局。
- 所有交互键盘可达；焦点环不覆盖；对话框 `Modal` 有标题与关闭；表格行动作可 Tab 到。
- 文案默认简体中文，`__()` 包裹；英文由翻译平台出。

## 7. 原型清单（`docs/design/mockups/`）

| 文件 | 内容 |
|------|------|
| `01-overview.html` | 概览：正常态 |
| `01b-overview-attention.html` | 概览：恢复模式横幅 + 三条「需要处理」+ 公告 |
| `02-connect.html` | 连接优化：DataForm 四组 |
| `03-services-unbound.html` | 文派服务：未绑定空态 |
| `03b-services-bound.html` | 文派服务：已绑定 + 权益表（三种状态）+ 小工具网格（四种角标） |
| `03c-services-app-open.html` | 文派服务：容器打开态（mock 工具） |
| `04-diagnose.html` | 诊断：四个 Tab（默认连接检查） |
| `05-recovery.html` | 恢复模式页（原生 wrap 样式） |
| `README.md` | 如何看原型、与实现的对应关系、已知与最终实现的差异 |

原型为**静态 HTML**，用近似 wp-admin 的样式（可内联一份精简的 wp-admin 变量与组件样式），不引第三方框架；目的是让实现者与产品方看到布局、层级、状态与文案，不追求像素级。
