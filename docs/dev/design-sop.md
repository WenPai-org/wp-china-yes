# 设计门禁 SOP

这份文档解决什么问题：4.0 后台被评为毛坯，原因之一是没有设计门禁——UI 被当成技术模块排期，没有经产品认可的原型就开始写代码。本文把统筹已定的门禁写成仓内规则：任何面向用户的界面改动，必须先有 HTML 原型，经产品负责人（feibisi）书面认可后，才允许拆任务、写代码。

反面教材：[`docs/dev-plan/verification/preview-site-review-2026-09-06.md`](../dev-plan/verification/preview-site-review-2026-09-06.md) 九条差距（概览绑定卡写死、英文错误串、空表占位、规格有实现没有等）。那些页面当时没有按本门禁走完「每个状态 + 全文案 + 书面认可 + 带编号规格」，验收也没有对着屏幕看。

相关：[`admin-ui-spec.md`](../design/admin-ui-spec.md)、[`task-book-template.md`](task-book-template.md)、[`acceptance-sop.md`](acceptance-sop.md)、[`copy-guidelines.md`](copy-guidelines.md)。

## 1. 谁必须走这扇门

任何**面向用户的界面**改动必须先走本门禁。包括：

- 后台页面（概览、连接优化、文派服务、诊断、恢复页）
- 通知（Notice、Snackbar、横幅）
- 向导
- 恢复页（服务端渲染也算界面）

**WP-CLI 输出不算**面向用户的界面，改 CLI 文案不走本门禁。

没有产品负责人书面认可记录的 UI 任务书**无效**。执行者见到无效任务书应停，在报告里写明，不要写代码。

## 2. 原型必须覆盖的内容

原型必须覆盖该界面的**每个状态**：

| 状态 | 含义 | 例（反面教材） |
|------|------|----------------|
| 空 | 从未有过数据、首次安装 | 概览「尚未检查」；权益「绑定后显示」 |
| 正常 | 依赖可用、数据齐全 | 概览三卡绿点 / 「N 项已加速」 |
| 加载 | 请求进行中 | 绑定中 Spinner；「立即检查」按钮 busy |
| 错误 | 本次操作失败 | 保存失败；绑定 503 |
| 降级 | 依赖服务不可用，仍显示上次或受限能力 | 「暂时无法连接文派服务，显示的是 N 小时前的状态。」 |
| 多站点差异 | 网络策略 / 子站覆盖（如适用） | 连接优化「已由网络设定」只读态 |

每个状态都要画出，缺一个不算覆盖。

原型必须包含**全部面向用户的文案（原文）**，不是「此处放说明」。按钮、空态、错误、Notice、表格空文案、确认对话框，全部写成最终中文。词表与语气见 [`copy-guidelines.md`](copy-guidelines.md) 与 [`admin-ui-spec.md`](../design/admin-ui-spec.md) §4。

## 3. 原型怎么做

- 只用品牌 token（`docs/design/` 下的 token 文件）与 Phosphor 图标。当前仓内可抄录的值在 `docs/design/mockups/_shared.css`（规格 §5 允许的 `--wp-admin-theme-color` / `--wp-admin-theme-color-darker-10`）以及实现侧 `src/Admin/app/tokens.css`。不得另造色值、不得引入品牌字体、不得用 WordPress 后台以外的壳。
- WordPress 后台壳内呈现：左菜单 + 顶栏。恢复页用 WP 原生 `.wrap`，不用 React `<Page>`。
- 静态 HTML，目的是让产品负责人看到布局、层级、状态与文案。不追求像素级，但状态与原文必须是最终稿。
- 图标：Phosphor `regular`，20px；页图标对照规格 §5（概览 Leaf、连接优化 Plugs、文派服务 SquaresFour、诊断 Stethoscope、恢复 LifeBuoy）。

现有静态稿在 [`docs/design/mockups/`](../design/mockups/)。本门禁生效之后，经认可的原型按第 4 节归档，不再把 `mockups/` 当认可记录。

## 4. 认可、归档、编号

1. 产品负责人（feibisi）书面认可：在 issue、任务书、或提交说明里出现明确的「认可」及日期、原型路径。记入该页 `APPROVAL.md`。
2. 认可后归档到 `docs/design/prototypes/<页面>/`，例如 `docs/design/prototypes/overview/`。目录内放每个状态的 HTML、本页 README、`APPROVAL.md`。
3. 与之对应的规格条目写进 [`docs/design/admin-ui-spec.md`](../design/admin-ui-spec.md)，**每条规格带编号**（如 `OV-03`），供验收对照。编号规则见该文件 §4「编号规则」。
4. 任务书必须引用：原型路径、认可记录路径与日期、规格编号列表。缺任一项，任务书无效。

书面认可记录模板（`APPROVAL.md`）：

```markdown
# 认可记录：<页面>

- 产品负责人：feibisi
- 日期：YYYY-MM-DD
- 原型：docs/design/prototypes/<页面>/
- 规格编号：OV-01, OV-02, …
- 原话：（从 issue / 任务书抄录，不要改写）
```

## 5. 原型与规格、实现的关系

原型与规格是同一事物的两个表现。

- 实现与二者任一不符：视觉以原型为准，行为以规格为准。
- 原型与规格冲突：先改文档再改代码，不许用代码「折中」。
- 实现不得用「占位 / TODO / EmptyTable」交付用户可见区域（见 [`agent-common-rules.md`](agent-common-rules.md)）。诊断页「出站主机记录」空表占位是反面教材第 4 条。

## 6. 执行者检查单（动手前）

- [ ] 本任务改的是面向用户的界面（不是 WP-CLI）
- [ ] `docs/design/prototypes/<页面>/` 有每个状态的 HTML，文案是原文
- [ ] `APPROVAL.md` 有 feibisi 书面认可的日期与原话
- [ ] `admin-ui-spec.md` 里每条相关规格已编号
- [ ] 任务书列出了规格编号，颜色 / 尺寸 / 文案 / 路径是具体值

缺一项就停，不要写代码。

## 不做什么

- 不把 WP-CLI、日志、REST 错误码当作用户界面来走本门禁。
- 不在本 SOP 里改 [`admin-ui-spec.md`](../design/admin-ui-spec.md) 的规格正文；编号规则已写在该文件 §4 之后，存量编号只在那一节登记。
- 不把 `docs/design/mockups/` 里未经本门禁认可的旧稿当成新 UI 任务的依据。
- 不代替产品负责人认可；执行者与统筹都不得口头「看起来行」就算过门。
- 不在没有原型的情况下用 e2e 绿代替视觉验收。
