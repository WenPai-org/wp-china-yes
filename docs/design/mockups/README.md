# 文派叶子 4.0 后台界面 HTML 原型

静态原型，对应 `docs/design/admin-ui-spec.md`。给 M1-08 / M2-05 / M2-06 实现者与产品方看布局、层级、状态与文案。

## 怎么看

用浏览器直接打开本目录下的 `.html` 文件即可（不必起本地服务器）。每页顶部灰色「原型说明条」写明文件名、规格章节、状态名。

依赖仅 `_shared.css`（相对路径），无 CDN、无字体、无 JS。

## 文件与规格

| 文件 | 规格 | 状态 |
|------|------|------|
| `01-overview.html` | §3.1 | 正常态：三张线路卡 + 公告 3 条，无「需要处理」 |
| `01b-overview-attention.html` | §3.1 | 恢复模式横幅 + 三条「需要处理」+ 公告 |
| `02-connect.html` | §3.2 | DataForm 四组；字体组未绑定禁用 |
| `03-services-unbound.html` | §3.3 | 未绑定空态 |
| `03b-services-bound.html` | §3.3 | 已绑定 + 权益表三种状态 + 小工具四种角标 |
| `03c-services-app-open.html` | §3.3 | 容器打开态：返回条 + mock 工具页 |
| `04-diagnose.html` | §3.4 | 四个 Tab；默认「连接检查」；其余在「其它 Tab 内容」 |
| `05-recovery.html` | §3.5 | WP 原生 `.wrap`；同页下方「已在恢复模式」 |

## 命名约定（对照实现）

- **`<Page>` 顶栏**：`.wpcy-page-header`（页标题 + 右侧主动作）。实现用 `@wordpress/admin-ui` 的 `<Page>`。
- **DataForm**：`.dataform` + `fieldset` 分组。实现用 `@wordpress/dataviews` DataForm。
- **DataViews 表格**：`table.dataviews-table`。实现用 DataViews `layout: table`。
- **DataViews 网格**：`.dataviews-grid` + `.app-card`。实现用 DataViews `layout: grid`。
- **Notice / Button / Snackbar / TabPanel**：class 名贴近 `@wordpress/components` 与 wp-admin。
- 恢复页不用 `<Page>`，只用 WP 原生 `.wrap`。

## 状态词表

界面文字与圆点语义以规格 **§4** 为准，不在本目录另造词。摘要：

| 界面文字 | 视觉 |
|----------|------|
| 国内镜像正常 | 成功绿点 `#00a32a` |
| 已回原始上游 | 琥珀点 |
| 不可用 | 红点 |
| 可用 | 品牌绿点 `#02b930` |
| 本期已用尽 | 琥珀点 + 「获取」 |
| 已到期 | 灰点 + 「获取」 |
| 离线 | 灰角标 |
| 恢复模式已开启 | 概览琥珀 Notice / 恢复页绿 Notice |

每个状态都是圆点（或角标/图标）+ 文字，不裸用颜色。

## 颜色

`_shared.css` 只覆盖规格 §5 允许的一组变量：

```css
--wp-admin-theme-color: #02b930;
--wp-admin-theme-color-darker-10: #03d537;
```

品牌绿只用于：主按钮、链接、当前菜单项高亮、「可用」圆点。

成功态圆点用 wp-admin 默认 `#00a32a`，**不是**品牌绿。原因：规格 §1 第 3 条——绿色不同时承担「成功」语义；成功走 `status.success`。

`#00a32a` / `#dba617` / `#d63638` 是 wp-admin 默认占位，待 brand token 补独立的 `status.success` / `warning` / `danger`。

## 与最终实现的已知差异

- 静态 HTML，无交互（保存、绑定、Tab 切换、关闭公告、打开小工具都不工作）。
- 无 JavaScript；诊断页其它 Tab 用 `hidden` + 下方折叠区展示，实现用 `TabPanel`。
- 样式是 wp-admin 近似，不是像素级复刻，也不是真实 `@wordpress/components` 渲染结果。
- 图标为内联 SVG 简化轮廓（Phosphor regular 意），或 20px 圆角方块占位。
- 数据全是假数据；站点用 `example.com`；公告链接也指向 `example.com`。实现走真实源 URL 与 REST。
- 小工具打开态用带边框区块写「mock 工具页」，不嵌真实 iframe。
- 命令面板（⌘K）未画。

## 发现的问题（规格歧义，未自行改规格）

1. §3.3 未绑定空态只写了权益表「绑定后显示」；小工具段未写空文案。原型两段都用「绑定后显示」。
2. §3.1 线路卡「公共库与头像」主状态是纯文字「N 项已加速」/「部分回退」，规格未要求圆点；原型按字面不加圆点（与双编码原则略有张力）。
3. §3.2 多站点「网络策略，子站可覆盖」未列入原型清单，本目录无单独稿。
4. §3.5 已在恢复模式时是否仍显示两个停用按钮，规格未写死。原型在下半段两态都保留按钮，供审阅。
5. 公告「标题为链接（新窗口）」的真实 URL 域是茶馆/薇晓朵；原型按任务书改用 `example.com`，避免外部资源。
