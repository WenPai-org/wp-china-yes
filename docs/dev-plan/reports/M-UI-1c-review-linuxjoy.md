# M-UI-1c 统筹评审（linuxjoy，2026-09-11）

基线：grok/m-ui-1c @ 8fc94ba（Grok 自报未 commit，统筹评审后代为提交）。

## 通过
§0b 1–11 项主体实现；截图 9 张；composer check / npm build / lint:js 绿；文案口径自查过（无"改写后台"）；inbound 进 Schema/Profile；状态页入口、检测行、推荐标、5 步向导在。

## 缺口（按性质分两类）

### A. 规格滞后导致（统筹责任，规格已补，进修正轮）
1. **头像未合并**：Schema/Profile/Connect/UI 仍是 avatar_admin + avatar_frontend 两键。决定（noise-reduction 文档同日补充）：合并为单一 avatar 键全站生效，迁移映射 3.x 两键 → 单键。
2. **CO-14 后台加速未全撤**：Connect.js 仍有字段、Report.php 仍写"4.1 起生效"、Overview.js 文案"只在后台加速"。决定：整体作废（易致后台样式问题）。
3. **MotuCloud 第 5 核心服务缺失**：Overview 锚点仍 n/4、矩阵无「图标与图片 MotuCloud」行。决定：核心服务清单 4→5。

### B. Grok 自报的环境/范围限制（接受，另列）
4. e2e 未跑（Studio 拷贝限制）；1024 截图、recovery-已开启 未拍。
5. 后台语言跟随管理员：仅存键不改语言（locale 钩子后端另列）。
6. 可用服务静态清单 + TODO（服务端 API 未就绪）。
7. 帮助 URL 用 wpcy.com/go/support 占位（论坛地址未定）。

## 处置
- 缺口 1–3 派修正轮（run-ci-loop，同工作树）；缺口 4–7 进 M-UI-1d 或后端任务。
- 合并：修正轮绿 + Studio 截图对照通过后并 main。
