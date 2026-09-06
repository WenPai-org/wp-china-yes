# 原型 E · 文派叶子 4.0 后台（已认可，实现基线）

认可记录：[`APPROVAL.md`](APPROVAL.md)。规格：[`../../admin-ui-spec.md`](../../admin-ui-spec.md) v2.1。归档版本：**v6**（wpcy-proto `bc67383`，2026-09-07 00:05；含 CSS 去重后的单层 `e.css`，333 行）。视觉值：[`DESIGN-E.md`](DESIGN-E.md)。

## 怎么看

本目录直接用浏览器打开任一 `.html`（样式在 `e.css` 与 `../_shared/shell.css`，无外部依赖，无 CDN；图标是 **RemixIcon line** 的 SVG 内联在页面里（feibisi 2026-09-06 夜定），`remixicon/` 目录存了用到的 47 个源文件供对照，`build.py` 生成时读的是 wpcy-proto 仓 `node_modules/remixicon`；语义名 → 文件名映射是 `build.py` 的 `RI` 字典，React 侧 `@remixicon/react` 同名组件）。右下角深色小条是**原型专用**的状态切换器，不属于产品。截图在 `screens/`（1440 宽，Playwright 生成）。

**唯一生成入口是 `build.py`**（`python3 build.py`，需要同目录 `parts_v3/4/5/6.py` 与 `_shell-head.html`；`compare.py` + `shoot-cleanup.mjs` 是 CSS 改动后做零像素差校验的工具）。所有面向用户的字符串都在 `build.py` 里，它同时是文案清单。改规格先改 `DESIGN-E.md` 与 `e.css`，再重新生成，再更新 wpcy-proto 仓（`~/wt/wpcy-proto/E/`，两处保持一致，以本目录为归档版）。

为什么不是每页一个子目录：19 页共享同一份 `e.css`、同一个壳与同一个生成器，拆目录会打断相对引用并造成 19 份 CSS 副本。本目录等价于 design-sop §4 的"页面子目录"集合。

## 页面 ↔ 状态 ↔ 规格编号

| 文件 | 页面 · 状态 | 规格编号 |
|---|---|---|
| `overview-domestic.html` | 概览 · 国内站正常 | SH-01–09, OV-10/10a, **OV-18**（连通栈：3 已接通 + 1 未启用）, OV-12/12a, OV-13（含 `.prov`）, OV-14, OV-15（整块） |
| `overview-crossborder.html` | 概览 · 跨境站正常（未绑定） | 同上 + OV-11（绑定本站）；OV-18 直连 / 只在后台；OV-13 原始源 ↔ 接通后成对 |
| `overview-degraded.html` | 概览 · 降级（镜像回退） | OV-16, OV-18（已回退行）, OV-13 降级行 |
| `overview-empty.html` | 概览 · 刚安装 | OV-08, SH-04（刚安装） |
| `overview-recovery.html` | 概览 · 恢复模式 | OV-01, OV-18（全部已停用） |
| `overview-upgraded.html` | 概览 · 升级站未确认场景 | OV-09, OV-10a（有下一步条时按钮全次级） |
| `settings.html` | 设置 · 简单（默认） | CO-11, CO-12, CO-13, CO-07, CO-08, CO-09, CO-01 |
| `settings-advanced.html` | 设置 · 高级 | CO-02, CO-03, CO-04, CO-05, CO-14, CO-08, CO-09, CO-01（已保存 ✓） |
| `settings-network.html` | 设置 · 多站点子站只读 | CO-06 |
| `services.html` | 服务 · 未绑定 | SV-01, **SV-12**（供应商锁定态）, SV-10（空态变体） |
| `services-pending.html` | 服务 · 绑定中 | SV-02, SV-12（锁定态） |
| `services-bound.html` | 服务 · 已绑定 | SV-03, **SV-12**（薇晓朵已连接 / 文派集市即将开放）, SV-11（带 `.prov`）, SV-07 |
| `services-unreachable.html` | 服务 · 服务端不可达 | SV-04, SV-10 |
| `diagnose.html` | 诊断（有结果） | DG-01, DG-06（含解读句）, DG-08, DG-04, DG-05 |
| `onboarding.html` … `onboarding-4.html` | 向导 4 步 | WZ-01, WZ-02, WZ-03, WZ-04 |
| `recovery.html` | 恢复模式页 | RC-01, RC-02, RC-03, RC-05 |

## 原型尚未覆盖、实现前必须补的状态

按 [`design-sop.md`](../../../dev/design-sop.md) §2，这些状态在对应任务开工前要先补 HTML 并更新本表：

| 状态 | 规格编号 | 属于任务 | 备注 |
|---|---|---|---|
| 壳：骨架加载态、读取失败提示 | SH-05, SH-06 | M-UI-1 | 组件级，可用一页展示两个区域 |
| 概览：混合站正常 | OV-10（混合文案） | M-UI-1 | 与跨境站只差文案，可不单独画，任务书直接给原文 |
| 概览：浏览器测速空态（跨境站从未测过） | OV-13 | M-UI-1 | 沿用 DG-06 空态样式 |
| 设置：切换场景确认 Modal；保存失败提示；网络管理员视图 | CO-11, CO-01, CO-06 | M-UI-2 | |
| 设置：本站拦截清单收起 / 编辑 / 满 20 / 命中保护 | CO-10 | M-UI-3 | 见 `tasks/M-BLOCK-UI.md` |
| 诊断：测速中、测速失败、出站三层、被隐藏的通知、事件记录、小工具数据列表、无迁移记录 | DG-06, DG-07, DG-03, DG-09, DG-05, DG-08 | M-UI-3 | |
| 恢复页：已在恢复模式 | RC-04 | M-UI-1 | |
| 向导：无建议、绑定等待 | WZ-01, WZ-03 | M-UI-2 | |

## 与 3.x 的关系

主色 `#3858e9` 沿用 3.8；其余不继承 3.x 布局。3.8 参照站：Studio `wpcy-38-ref`。
