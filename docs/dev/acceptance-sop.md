# 验收 SOP

这份文档解决什么问题：过去验收只验代码（测试绿 / diff 范围 / 代码审查），没有一环是对着屏幕看的，「完成」没有定义。本文规定三层验收：执行者自验、独立审查（含视觉）、统筹终审；里程碑另加产品负责人在预览站看。UI 任务三层全过才合并；非 UI 任务过第 1、2 层。

相关：[`task-book-template.md`](task-book-template.md)、[`design-sop.md`](design-sop.md)、[`copy-guidelines.md`](copy-guidelines.md)、[`agents.md`](agents.md)、视觉脚本 `tests/visual/`。

反面教材：[`preview-site-review-2026-09-06.md`](../dev-plan/verification/preview-site-review-2026-09-06.md) 九条——其中第 4 条「出站主机记录是空表占位」、第 2 条英文错误串、第 5 条规格有实现没有，都是只验代码、不看屏幕的结果。

## 分层

| 层 | 谁 | UI 任务 | 非 UI 任务 |
|----|----|---------|------------|
| 1. 执行者自验 | 写代码的那次运行 | 必过 | 必过 |
| 2. 独立审查（Grok 只读） | **另一个**代理，不得是写代码的那次运行 | 代码 + 视觉 | 代码 |
| 3. 统筹终审 | linuxjoy | 看截图与审查表，不看 diff；预览站亲自点一遍 | 看审查表；不看 diff 亦可 |
| 4. 产品负责人验收 | feibisi | 里程碑级（不是每任务），在预览站看；批注回到设计门禁重走 | 里程碑级 |

UI 任务：第 1、2、3 层全过才合并。非 UI 任务：第 1、2 层过即可合并（第 3 层仍由统筹决定是否抽看）。

## 第 1 层：执行者自验

[`task-book-template.md`](task-book-template.md) 的 DoD 全部打勾 + 证据。缺一条不得报「完成」。

截图命令（Studio 预览站，本机无 Docker）：

```bash
BASE_URL=http://localhost:8890 WP_USERNAME=admin WP_PASSWORD=wpcy-preview npm run visual
```

产出在 `docs/design/screens/<run>/`，文件名 `<页面>-<状态>.png`。`screens.json` 登记页面 URL、状态准备步骤、文件名。状态准备允许用 WP-CLI 或 REST 造数据（例如把绑定状态写成 bound / 让镜像健康为 degraded）。造不出来的状态在 `screens.json` 写「需 fixture」，并在报告列出。

报告写完再退出。等 CI 时先写「CI run \<id\> 进行中，本地验收如下」，不要空等。

## 第 2 层：独立审查（Grok 只读）

代码审查走现有 `review.sh` 流程（`~/wt/grok-tasks/review.sh`：只读、换代理、对照任务书与 spec）。**加上**视觉验收。

审查员不得是写代码的那次运行。

### 视觉验收表

对着原型（`docs/design/prototypes/<页面>/` 或认可过的稿）和带编号规格，逐页逐状态看截图，输出：

```markdown
| 规格编号 | 原型 | 截图 | 差异 | 阻断/建议 |
|----------|------|------|------|-----------|
| OV-03 | docs/design/prototypes/overview/正常.html | docs/design/screens/<任务>/overview-正常.png | 文派服务卡写死未绑定 | 阻断 |
```

文案逐条对 [`admin-ui-spec.md`](../design/admin-ui-spec.md) §4 词表与 [`copy-guidelines.md`](copy-guidelines.md)。英文错误串、禁用词、「占位」空表，一律阻断。

视觉验收的判定：

- 视觉与原型不符 → 阻断（实现改到与原型一致，或先改原型并重新认可）
- 行为与规格不符 → 阻断（实现改到与规格一致，或先改规格）
- 原型与规格冲突 → 阻断，先改文档再改代码（[`design-sop.md`](design-sop.md) 第 5 节）

代码审查分节仍按 `review.sh`：正确性 / 运行时 / 规范 / 安全 / 测试 / 与 spec 的偏差 / 阻断·建议·有意不改。

## 第 3 层：统筹终审

- 看截图与审查表，**不看 diff**。
- UI 任务在预览站（WordPress Studio）亲自点一遍。站点约定见 [`docs/dev-plan/README.md`](../dev-plan/README.md) §0（本地 Studio；CLI 用 `~/.studio/bin/studio` 或本机 Studio.app 的 CLI，不要用会抢 DISPLAY 的 GUI 包装）。
- 预览站账号以该站 `STUDIO.md` / 任务书为准。当前 4.0 预览站示例：`http://localhost:8890`，`admin` / `wpcy-preview`。

统筹终审要看的不是「代码像不像」，而是：屏幕上的状态与文案是否等于原型和规格。

## 第 4 层：产品负责人验收

里程碑级，不是每任务。在预览站看。他的批注回到 [`design-sop.md`](design-sop.md) 重走：改原型 → 书面认可 → 改规格编号条目 → 再拆任务。

## 合并门闩

| 条件 | UI | 非 UI |
|------|----|-------|
| DoD 全勾 + 证据 | 要 | 要 |
| 独立审查无阻断 | 要 | 要 |
| 视觉表逐页逐状态 | 要 | 不适用 |
| 统筹看过截图 / 点过预览站 | 要 | 抽看 |
| 产品负责人点过预览站 | 里程碑 | 里程碑 |
| 已 push / CI 绿 | 由统筹合并前确认；执行者未 push 时报告写明 | 同左 |

## 不做什么

- 不让写代码的那次运行「再审一遍自己」代替第 2 层。
- 不把 `composer check` 绿当成视觉已过。
- 统筹终审不拿 diff 当主证据；产品负责人不看 diff。
- 不在本 SOP 改 `review.sh` 的仓库外脚本；仓内只规定要它加上视觉表。
- 不把第 4 层做成每任务门闩。
