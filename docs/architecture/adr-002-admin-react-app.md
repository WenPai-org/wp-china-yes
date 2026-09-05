# ADR-002：WP-China-Yes 4.0 整站 React 管理应用

状态：已定 2026-09-03  
日期：2026-09-03  
来源：linuxjoy 定稿 §7.5a-D

## 背景

ADR-001 原决策把后台拆成两套：本地设置走 Settings API 服务端渲染，仅「文派服务」页用 `@wordpress/components` 小应用。该拆分的前提是四页里多数为静态开关。

2026-09-03 晚定稿 §7.5a 把小工具容器、权益配额、固定源公告推进 4.0 首发后，四页中三页（概览含公告、文派服务含绑定/权益/小工具、诊断）为动态内容。继续服务端渲染会变成两套界面。产品方倾向古腾堡 / 站点编辑器风格的原生新界面。

连接优化类插件仍必须有无 JavaScript 逃生口：一键关闭全部 URL 改写与模块。

## 决策

管理界面是一个整站 React 应用，外加一个服务端渲染的恢复页。不再使用旧 `framework/`，也不复制任何通用设置框架。

### React 应用

- 技术栈：`@wordpress/components` + `@wordpress/data` + `@wordpress/api-fetch`，列表（权益、公告、驻留记录、小工具）用 `@wordpress/dataviews`（在 wp-scripts 下从 `@wordpress/dataviews/wp` 导入），设置表单用 DataForm。官方插件页教程已从手写控件转到这两套，跟着走才是"站点编辑器同一套交互"，不只是长得像。
- 页面骨架：普通 `add_menu_page` 页内的 React 应用，布局层用 `@wordpress/admin-ui` 的 `<Page>`（隔离在单独布局组件里，接受其 2.0 提案带来的大版本变动）。**不用 `@wordpress/interface` 做壳**（官方文档仍标 experimental、明示可能 drastic breaking）。**不引入第三方 UI 库**。
- 命令面板：用 `@wordpress/commands` 注册跳到概览 / 文派服务 / 诊断 / 恢复页的命令（WP 7.0 起 ⌘K 在全后台可用）。
- 与 OpenStation（`desktop-mode` 插件，Automattic 维护、用户 opt-in 的多窗口后台壳）的关系：叶子只是普通后台页，用户开启 OpenStation 时自然成为一扇窗口；**不检测、不排斥、不模仿**，不做第二套窗口管理器。依据：`linuxjoy docs/research/2026-09-03-wordpress-admin-desktop-and-new-apis.md`。
- 状态管理：`@wordpress/data` store `wpcy/admin`。
- 所有数据经 REST 命名空间 `wpcy/v1`（见 `docs/specs/rest-api.md`）。
- `wp_localize_script` / `wp_add_inline_script` 只传 nonce、REST root、当前用户能力、初始设置快照。
- 构建：`@wordpress/scripts`；发布包带编译产物；源码进仓。
- token 只覆盖 `--wp-admin-theme-color` 系列与 Phosphor 图标。WP 7.1 起的 Design System token（`--wpds-*`、`@wordpress/theme` 的 `ThemeProvider`）在 4.x 评估接入，4.0 不绑死其变量名。

四页名称不变：

- 概览（含公告）
- 连接优化
- 文派服务（绑定、权益配额、用量、小工具）
- 诊断

不再提供选择显示哪些 section、欢迎营销页、产品入口集合和隐藏菜单功能。

### 恢复页

- PHP 注册的独立菜单项 `?page=wpcy-recovery`。
- 在 `WP_Screen` 下服务端渲染的表单。
- POST + nonce + `manage_options`。
- 动作只有两个：「关闭全部 URL 改写」「停用全部模块」。
- 写入 `wpcy_settings.recovery_mode = true`。
- React 应用检测到该标志显示横幅与「退出恢复模式」。
- 无 JavaScript 可用。仅此一页服务端渲染。

退出恢复模式走 REST `POST /wpcy/v1/recovery`，`action` 为 `exit`（见 `docs/specs/rest-api.md`）。恢复页本身不依赖 React。

## 约束

- 无障碍：所有交互可键盘操作；`@wordpress/components` 默认语义不得覆盖。
- 构建产物进发布包、源码进仓、`npm ci` 可复现。
- bundle 体积预算 300KB gz 以内（首屏）。
- REST 权限：全部 `manage_options`（多站点网络设置 `manage_network_options`）。
- 不在浏览器端判断套餐（entitlement 只读展示）。
- token 只改 CSS 变量。
- React 应用加载失败时，恢复页入口必须仍可达（菜单项由 PHP 注册）。
- 管理页面任何位置不出现面向用户的报告开关或同类文案；报告行为不是设置项。

## 不采用的方案

### Settings API 服务端渲染（原 ADR-001 路线）

2026-09-03 推翻。因小工具容器、权益配额、公告使四页中三页为动态内容，且产品方倾向站点编辑器风格原生界面。原否决全 SPA 的理由（构建、无障碍、REST 权限面）转为本文约束。

### Vue / Svelte / 独立 React 栈

否决。与 wp-admin 设计语言脱节，且会引入双份组件库。

### iframe 嵌套整个后台

否决。无法与 wp-admin 菜单 / 通知集成。

## 后果

正面：

- 四页同一套界面与数据层，动态内容（公告、权益、小工具）不必再做服务端模板。
- 与站点编辑器视觉语言一致，组件来自 WordPress 自身。
- 恢复页把无 JS 逃生口限定为「一键关闭改写与模块」，范围明确。

代价：

- 必须维护 `@wordpress/scripts` 构建链，发布包携带编译产物。
- REST 权限面覆盖全部四页，而不仅是文派服务页。
- 无障碍与 bundle 体积成为发布门禁，而不是可选项。
- 恢复页与 React 应用对 `recovery_mode` 的读写必须保持同一 schema。

## 验收

- 四页由 React 应用渲染；恢复页无 JS 可提交「关闭全部 URL 改写」与「停用全部模块」。
- 禁用 JavaScript 时，恢复页入口仍出现在 PHP 注册的菜单中并可完成上述两个动作。
- `npm ci` 后可复现构建；发布包含编译产物。
- 首屏 bundle ≤ 300KB gz。
- 写请求均校验 `manage_options`（网络设置 `manage_network_options`）与 nonce。
- Playwright 覆盖四页 + 恢复页。
- 浏览器端不根据套餐字段决定功能是否可用；套餐信息只读展示。
