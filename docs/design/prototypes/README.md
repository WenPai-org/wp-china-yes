# 已认可原型归档

这份文档解决什么问题：告诉执行者经产品负责人认可的 HTML 原型放在哪、每个页面子目录要有什么。规则见 [`docs/dev/design-sop.md`](../../dev/design-sop.md)。

经产品负责人（feibisi）书面认可的 HTML 原型放在本目录的子目录：`docs/design/prototypes/<页面>/`。

本目录在门禁生效时建立。旧静态稿在 [`../mockups/`](../mockups/)，那些文件**不是**本门禁的认可记录。

**当前已认可的原型：[`e/`](e/)**（方向 E，2026-09-06，19 页，含 `APPROVAL.md` 与页面 ↔ 规格编号表）。共享壳样式在 [`_shared/shell.css`](_shared/shell.css)。

每个页面子目录至少含：

- 每个状态一份 HTML（空 / 正常 / 加载 / 错误 / 降级 / 多站点如适用），文案为原文
- `APPROVAL.md`（谁、何时、原话、规格编号）
- `README.md`（页面、规格编号列表、与 `admin-ui-spec.md` 的对应）

## 不做什么

- 不把 [`../mockups/`](../mockups/) 里的旧稿当成已认可原型。
- 不在本目录写实现代码或改规格正文。
