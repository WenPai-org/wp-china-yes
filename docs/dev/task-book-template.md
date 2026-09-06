# 任务书模板与 Definition of Done

这份文档解决什么问题：任务书缺固定的完成定义时，执行者会把「测试绿 / diff 在范围内 / 有人看过代码」当成完成，而规格条目、每个状态的屏幕、面向用户的文案从未被对照。本文给出任务书必须含的块、必须逐条打勾的 DoD、以及写法规则。统筹按此写任务书；执行者按此写报告。缺一条不得报「完成」。

相关：[`agents.md`](agents.md)、[`design-sop.md`](design-sop.md)、[`acceptance-sop.md`](acceptance-sop.md)、[`copy-guidelines.md`](copy-guidelines.md)、[`agent-common-rules.md`](agent-common-rules.md)。

## 任务书必须含的块

缺一块就停，不要猜：

```markdown
# 任务：<短名>

## 目标
一句话。

## 背景与上下文文件
路径列表（仓内 + `.grok-context/`）。不要联网补资料。

## 交付物
路径级。新建 / 只改哪些。

## 行为规格
对照的 spec / ADR / 定稿条目。UI 任务在此列出规格编号（如 OV-03）与原型路径。

## 禁区
路径、产品决定、文案、git 操作。

## 验收标准
可执行的命令（不只「通过」）。每条都能用一条命令或一张截图证明。

## Definition of Done
（下一节清单，原文贴进任务书）

## 报告格式
见 `docs/dev/agents.md`。
```

执行者不是决策者：任务书写死的范围、命名、禁区不得自行更改。任务书矛盾或做不到 → **停下来在最终报告里写明**，不要绕。

UI 任务额外：先有 [`design-sop.md`](design-sop.md) 的原型与书面认可，否则任务书无效。

## Definition of Done（固定清单）

执行者在报告里逐条打勾并贴证据。缺一条不得报「完成」：

- [ ] 规格条目 ↔ 实现逐条对照表（`| 规格编号 | 实现位置 文件:行 | 状态 |`），规格里有、实现没有的必须列出并写「未做」。
- [ ] 每个状态一张截图（Playwright，命名 `<页面>-<状态>.png`，存 `docs/design/screens/<任务>/`），UI 任务必备；非 UI 任务写「不适用」。
- [ ] 面向用户的字符串：全部中文、在 `admin-ui-spec.md` §4 词表内或已提交词表新增；无英文错误串；无「遥测/隐私/上报」字样。
- [ ] 空状态、错误状态、降级状态有对应实现，不是「空表占位」。
- [ ] 测试：新增/修改行为有测试；`composer check` 与 `npm run build`、`npm run lint:js` 通过；CI run id。
- [ ] `git diff --stat` 在允许路径内；`.grok-context/` 未入库。
- [ ] 报告含「没做 / 做不到 / 有疑问」；**报告写完再退出**（等 CI 时先写「CI run <id> 进行中，本地验收如下」）。

对照表模板：

```markdown
| 规格编号 | 实现位置 | 状态 |
|----------|----------|------|
| OV-03 | src/Admin/app/pages/Overview.js:403 | 已做 |
| DG-04 | — | 未做：诊断出站主机仍是 EmptyTable（见 preview-site-review 第 4 条） |
```

截图由 `npm run visual` 写出，约定见 [`acceptance-sop.md`](acceptance-sop.md) 与 `tests/visual/screens.json`。

## 任务书写法规则

- **给原文，不给范围**：颜色、尺寸、文案、路径写具体值，不写「大约/范围/类似」。错例：「medium 约 18–20px」。对例：「`font-size: 18px`；文案「国内镜像正常」；路径 `src/Admin/app/pages/Overview.js`」。
- **线索标「猜测」**：统筹给的原因分析若未验证，必须写「猜测：」前缀，执行者按证据纠正并在报告写明。
- 任务书里每条验收都要能用一条命令或一张截图证明。不能证明的句子不要写进验收。

来自 [`agents.md`](agents.md)「统筹写任务书的教训」：给原文不给范围；「没做 / 做不到 / 有疑问」是报告里最值钱的一节。

## 报告里的 DoD 怎么贴证据

| DoD 项 | 证据 |
|--------|------|
| 规格对照表 | 报告内表格；「未做」行不得省略 |
| 截图 | `docs/design/screens/<任务>/` 路径列表；非 UI 写「不适用」 |
| 文案 | grep 用户可见字符串的命令输出；对照 §4 词表 |
| 空/错误/降级 | 截图文件名含该状态，或实现位置 + 测试名 |
| 测试与 CI | `composer check` / `npm run build` / `npm run lint:js` 输出摘要；`CI run <id>` 或「未 push，无 CI run id」 |
| diff 范围 | `git diff --stat` 原样；确认无 `.grok-context/` |
| 没做 / 疑问 | 独立一节，逐条原因 |

## 不做什么

- 不把「测试绿」单独当成完成。
- 不把审查代理与执行代理写成同一次运行。
- 不在任务书里用「大约 / 类似 / 参考某某站」代替具体值。
- 不删、不软化本页 DoD 清单；任务书可以加条，不能少条。
- 不要求执行者在任务书自相矛盾时自行拍板。
