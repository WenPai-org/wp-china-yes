# 任务 M-UI-1：产品化 UI 第一步——壳、组件集、概览页（六态）、恢复页

worktree 分支 `grok/m-ui-1`，基于 `main`（含 `docs/design/prototypes/e/` 与 `admin-ui-spec.md` v2.0）。预计 diff ≤ 2500 行（不含截图）。与后端任务 M-STATS-1（`grok/m-stats-1`，提供 `/stats` `/events`）**并行**：本任务按契约开发，用 REST mock 验收，不等它合并。

设计门禁（`docs/dev/design-sop.md`）四件齐备：原型 `docs/design/prototypes/e/`；认可 `docs/design/prototypes/e/APPROVAL.md`（feibisi，2026-09-06）；规格编号见下；本任务书。**门禁缺任一项本任务书无效**——四件都在，动手前请逐一打开确认。

## 目标

把 4.0 后台从"毛坯"换成原型 E：新的页头 + 横向标签 + 居中容器 + 页脚（四页共用），一套按 `DESIGN-E.md` 实现的 React 组件集，概览页六个状态全部按规格接真实 / 契约数据，恢复页按 RC-01–05 重做。设置 / 服务 / 诊断三页**本任务只换壳不换内容**（它们的重做是 M-UI-2 / M-UI-3）。

## 背景与上下文文件

- **原型（视觉唯一依据）**：`docs/design/prototypes/e/overview-domestic.html`、`overview-crossborder.html`、`overview-degraded.html`、`overview-empty.html`、`overview-recovery.html`、`overview-upgraded.html`、`recovery.html`；样式 `e.css`、壳 `_shell-head.html`、`../_shared/shell.css`；截图 `screens/`。文案与部件源码 `build.py` / `parts_v4.py` / `parts_v5.py`（部件 HTML 结构照抄成 React）。
- **视觉值**：`docs/design/prototypes/e/DESIGN-E.md` §1–§4（布局、字阶、颜色、组件）与 §5.1 硬规则。
- **行为（唯一依据）**：`docs/design/admin-ui-spec.md` v2.0 §1、§2、§3（SH-01–08）、§4.1（OV-01、OV-08、OV-09、OV-10/10a/10b/10c、OV-11、OV-12/12a、OV-13、OV-14、OV-15、OV-16）、§4.6（RC-01–05）、§5 词表、§7 token、§8 无障碍。
- **数据契约**：`docs/specs/rest-api.md` §`/settings`（`profile`、`profile_confirmed_at`、`connectivity.*`、`recovery_mode`）、§`/diagnostics`（含**界面分组表**）、§`/diagnostics/client-probe`、§`/stats`、§`/events`、§`/binding`、§`/migration/report`、§`/recovery`。
- 现有代码：`src/Admin/app/`（`index.js`、`routing.js`、`components/PageShell.js`、`pages/Overview.js`、`store/index.js`、`style.css`、`tokens.css`）、`src/Admin/AdminModule.php`、`src/Admin/RecoveryPage.php`、`tests/visual/`（`screens.json` 支持 `rest-mock`）、`tests/e2e/overview.spec.js`、`tests/e2e/recovery.spec.js`。
- 规范：`docs/dev/task-book-template.md`、`docs/dev/acceptance-sop.md`、`docs/dev/copy-guidelines.md`、`docs/dev/coding-standards.md`、`docs/dev/agent-common-rules.md`；`.grok-context/COMMON-RULES.md`。

## 交付物

| # | 交付 | 路径 | 做法 |
|---|---|---|---|
| 1 | 样式基座 | `src/Admin/app/tokens.css`（重写）、`src/Admin/app/style.css`（重写） | `tokens.css` = `e.css` 的 `:root` 变量块，选择器改为 `.wpcy-app`；`style.css` = `e.css` 其余全部规则原样移植（类名不改），外加 `#wpcontent`/`#wpfooter` 两条与 `_shared/shell.css` 无关。**不**保留旧 style.css 里的任何规则。禁止 `!important` 除了覆盖 `@wordpress/components` 的 Modal / Button 默认外观时 |
| 2 | 图标 | 新建 `src/Admin/app/ui/icons.js` | `build.py` `ICON` 字典 + `parts_v3.MORE_ICONS` + `chevron`/`up` 全部转为 React 组件 `<Icon name="globe" />`（`dangerouslySetInnerHTML` 禁止；用 JSX 路径）。SH-07 |
| 3 | 组件集 | 新建 `src/Admin/app/ui/` 下：`Card.js`（`.card` / `.card.tight` / `card-head` / `card-foot`、`Tile`）、`Pill.js`、`Btn.js`（primary / secondary / ghost / danger，基于 `@wordpress/components` `Button` 取键盘行为，外观由 `.btn*` 类）、`Scope.js`、`Toggle.js`（必须带 `label` 文字，`aria-checked`）、`Notice.js`（info / warn / warn.act）、`Sec.js`（小节标题 + 右侧说明 + 动作）、`Hero.js`（可折叠、ring、facts、summary bar；折叠状态读写 user meta `wpcy_overview_hero_collapsed` 经 `/wp/v2/users/me`）、`StatArea.js`（含 `area()` SVG 生成，空态 `v.none`）、`Routes.js`、`Timeline.js`、`Resources.js`（`.resources2` 分组 chips）、`Next.js`（下一步条）、`Empty.js`、`Skeleton.js`（SH-05）、`LoadError.js`（SH-06）、`relTime.js`（相对时间口径 OV-10c）。每个组件 props 与原型 HTML 结构一一对应 |
| 4 | 壳 | `src/Admin/app/components/PageShell.js`（重写）、`src/Admin/app/index.js`、`src/Admin/AdminModule.php` | SH-01–04：页头（品牌 + 横向标签 + 帮助 / 反馈）、`.wpcy-main.wpcy-wrap`、页脚（版本 + 已运行 N 天 / 刚安装 + 更新日志 + wpcy.com）。标签是普通 `<a href="admin.php?page=…">`。`AdminModule::add_pages()` 标签文字：连接优化 → **设置**、文派服务 → **服务**（slug 不改）。`bootstrap_payload()` 加 `links: { help, feedback, changelog, site, resources: { <key>: url } }`（PHP 常量表，url 先用 `https://wpcy.com/docs/`、`https://wpcy.com/feedback/`、`https://wpcy.com/changelog/`、`https://wpcy.com/`、资源组每个 chip 一个 key，值先全部指向 `https://wpcy.com/`——**这是占位 URL，报告里必须列出让统筹填**）。`register_meta( 'user', 'wpcy_overview_hero_collapsed', [ 'type'=>'boolean', 'single'=>true, 'show_in_rest'=>true, 'auth_callback'=>… ] )`。菜单图标换成原型的叶子（`_shell-head.html` 里 `.wpcy-mark` 的 path） |
| 5 | 概览页 | `src/Admin/app/pages/Overview.js`（重写）、`src/Admin/app/store/index.js`（扩） | 六态全部按 §4.1：数据 `GET /diagnostics`（分组 → OV-10b 环 / OV-13 列表）、`GET /stats?days=14`（前 7 后 7 → OV-12 / OV-12a / OV-08）、`GET /events?per_page=4`（OV-14）、`GET /binding`（OV-11 ②）、`GET /migration/report`（OV-09）、`GET /diagnostics/client-probe`（跨境 OV-13）、settings 来自 bootstrap（`profile`、`profile_confirmed_at`、`recovery_mode`、`connectivity`）。每个请求独立失败 → 该区域 SH-06，其它照常；404（M-STATS-1 未合并时 `/stats` `/events` 不存在）等同读取失败。跨境站 OV-10a 主按钮触发 DG-06 一次：本任务只需跳诊断页并带 hash `#probe`（诊断页读 hash 自动测速在 M-UI-3 做；本任务在报告写明） |
| 6 | 其它三页换壳 | `pages/Connect.js`、`pages/Services.js`、`pages/Diagnose.js` | 只把外层换成新 `PageShell`（h1 + lede 用 `.wpcy-page-head`），内容不动；确保 e2e `connect/services/diagnose/apps/commands` 仍绿。旧 `<Page>`（`@wordpress/admin-ui`）不再使用，可从依赖移除（若其它地方无引用） |
| 7 | 恢复页 | `src/Admin/RecoveryPage.php` | 服务端渲染按 `recovery.html`：内联样式（只含用到的规则，≤ 120 行 CSS），RC-01–05，RC-04 已在恢复模式态。表单 POST + nonce 不变。`<title>`「文派叶子 · 恢复模式」 |
| 8 | 视觉验收 | `tests/visual/screens.json`（重写 overview 条目）、`docs/design/screens/m-ui-1/` | 用 `rest-mock` 为六个概览态 + 恢复页两态各写一条：`overview-国内正常.png`、`overview-跨境正常.png`、`overview-降级.png`、`overview-刚安装.png`、`overview-恢复模式.png`、`overview-升级站.png`、`overview-读取失败.png`（mock `/stats` 500）、`overview-骨架.png`（mock 延迟 5s 后截）、`recovery-默认.png`、`recovery-已开启.png`。mock 数据用原型里的数字（42 / 18,430 / 3,210 等）以便与 `prototypes/e/screens/` 逐图对照。1440 宽 |
| 9 | e2e | `tests/e2e/overview.spec.js`（重写）、`tests/e2e/recovery.spec.js`（更新断言） | 断言改为新结构：页头四标签、Hero 标题文案、六态各自的关键文字（用 `page.route` mock）、折叠后 localStorage/meta 写入、主按钮唯一（`page.locator('.btn-primary')` count ≤ 1）。其它 spec 不改期望 |
| 10 | 词表 | `docs/design/admin-ui-spec.md` §5.2 | 只允许**追加**行（实现中发现规格漏写的字符串）；不改已有行 |

## 行为规格

- 编号：SH-01, SH-02, SH-03, SH-04, SH-05, SH-06, SH-07, SH-08, OV-01, OV-08, OV-09, OV-10, OV-10a, OV-10b, OV-10c, OV-11, OV-12, OV-12a, OV-13, OV-14, OV-15, OV-16, RC-01, RC-02, RC-03, RC-04, RC-05。全部在 `admin-ui-spec.md` v2.0，原文照做。
- 硬规则（`DESIGN-E.md` §5.1）：一页一个主按钮；环与线路列表同口径；事实条不换行；页头 / 页脚链接不重复。
- 文案：只用 §5 词表；数字千分位；相对时间口径 OV-10c；`mirror_bytes_saved` 人读：< 1 GB 显示 `N MB`（整数），否则 `N.N GB`。
- 无障碍：§8；Hero 折叠按钮 `aria-expanded`；环 `role="img" aria-label="线路健康 100"`。
- 视觉对照：每张 `docs/design/screens/m-ui-1/*.png` 与 `docs/design/prototypes/e/screens/` 同名态并排看，允许差异：字体渲染、面积图曲线（数据不同）、相对时间文字；不允许差异：布局、间距、颜色、文案、组件形状。

## 禁区

- 不改 `src/Rest/**`、`src/Stats/**`、`src/Config/**`、`src/Connectivity/**`、`src/Privacy/**`（后端在 M-STATS-1 / M-BLOCK-1 / M-SCOPE-1c）。
- 不改 `pages/Connect.js` / `Services.js` / `Diagnose.js` 的内容区（只换壳）；不做设置 / 服务 / 诊断 / 向导的新界面。
- 不改 `docs/design/prototypes/**`、`DESIGN-E.md`、`admin-ui-spec.md` 正文（词表追加除外）；原型与规格冲突 → 停，报告写明。
- 不引新 npm 依赖（图标、图表、日期库都不要；面积图用 `parts_v5.area()` 的算法自绘）。
- 不出现英文用户可见字符串；不出现「暂无数据 / 加载中 / Loading」。
- 不 `git push` 到 `main`；只推 `grok/m-ui-1`。

## 验收标准

1. `npm run build`、`npm run lint:js`、`npm run lint:css`、`composer check` 绿；分支 CI 全绿（贴 run id）。
2. `npm run visual` 产出第 8 条的 10 张图到 `docs/design/screens/m-ui-1/`，报告贴文件列表。
3. `npm run test:e2e` 全绿（含未改的 spec）。
4. `rg -n "Loading|加载中|暂无数据" src/Admin/app` 为空；`rg -n "btn-primary" src/Admin/app/pages/Overview.js` 处能说明一页最多一个。
5. `curl -s 'http://localhost:8888/wp-admin/admin.php?page=wpcy-recovery'`（wp-env，登录 cookie）含「文派叶子 · 恢复模式」与「只关闭 URL 改写」「停用全部模块」。
6. 规格 ↔ 实现对照表按上面编号列表逐条（27 行），「未做」行不得省。
7. DoD 七项逐条。
8. 提交前缀 `feat(admin-ui):` / `refactor(admin):` / `test(visual):` / `test(e2e):`；至少分 5 笔（样式基座、组件集、壳、概览、恢复页）。

## Definition of Done

- [ ] 规格条目 ↔ 实现逐条对照表（`| 规格编号 | 实现位置 文件:行 | 状态 |`），规格里有、实现没有的必须列出并写「未做」。
- [ ] 每个状态一张截图（Playwright，命名 `<页面>-<状态>.png`，存 `docs/design/screens/<任务>/`），UI 任务必备；非 UI 任务写「不适用」。
- [ ] 面向用户的字符串：全部中文、在 `admin-ui-spec.md` §5 词表内或已提交词表新增；无英文错误串；无「遥测/隐私/上报」字样。
- [ ] 空状态、错误状态、降级状态有对应实现，不是「空表占位」。
- [ ] 测试：新增/修改行为有测试；`composer check` 与 `npm run build`、`npm run lint:js` 通过；CI run id。
- [ ] `git diff --stat` 在允许路径内；`.grok-context/` 未入库。
- [ ] 报告含「没做 / 做不到 / 有疑问」；**报告写完再退出**（等 CI 时先写「CI run <id> 进行中，本地验收如下」）。

## 报告格式

见 `docs/dev/agents.md`。报告写到 `~/wt/grok-tasks/REPORT-m-ui-1.md`。报告末尾单列「统筹要填的占位 URL」（第 4 条 links）与「留给 M-UI-2 / M-UI-3 的接口」（如 `#probe` hash）。
