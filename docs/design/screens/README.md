# 视觉验收截图

这份文档解决什么问题：规定视觉验收截图的目录与文件名，避免截图散落在 `tests/e2e/__screenshots__` 或审查员找不到。

执行者按任务把 Playwright 截图放到 `docs/design/screens/<任务>/`，文件名 `<页面>-<状态>.png`。规则见 [`docs/dev/task-book-template.md`](../../dev/task-book-template.md) DoD 与 [`docs/dev/acceptance-sop.md`](../../dev/acceptance-sop.md)。

本目录的 `sop-baseline/` 是 SOP 任务用 Studio 预览站（`http://localhost:8890`）跑 `npm run visual` 的基线。登记表：`tests/visual/screens.json`。

## 不做什么

- 不把 Playwright `test-results/` 失败附件当验收截图。
- 不在本目录放原型 HTML（原型在 `docs/design/prototypes/`）。
