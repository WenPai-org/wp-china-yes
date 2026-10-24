# wpcy-admin-ui

文派叶子（WPCY）后台的 **React 设计系统实现包**（双栈方案 B）。

## 定位

| | |
|---|---|
| **是** | 叶子 / 未来可迁 React 的插件后台共用的 React 组件与视觉层 |
| **不是** | PHP 壳 kit（那是 [`wenpai-admin-ui`](https://github.com/WenPai-org/wenpai-admin-ui)） |
| **不是** | token 真源（真源为共享 design-tokens / wenpai tokens；本仓只消费生成物） |

同一套 token 与组件语义，两套技术实现：

- **PHP**：`wenpai-admin-ui`（设置型插件壳）
- **React**：本仓 `wpcy-admin-ui`（叶子整站后台；壳层可逐步迁入本包）

设计决策见 linuxjoy ADR：`docs/design/wenpai-admin-ui/ADR-dual-stack-design-system-2026-10-24.md`  
抽取清单：`EXTRACT-from-wpcy-ui-ux-2026-10-24.md`

## 当前状态（EXTRACT CTRL P2c）

已从叶子 `wp-china-yes` Admin（tip `d31bf26`，含 `#174`/`#175`）抽出可复用表面：

| 组 | ID | 导出 |
|----|-----|------|
| SHELL | 01–13 | `PageShell`（tabs/`pageTitle`/`pageLede`/`pageAside`/`notices`/`footerLeft`/`footerLinks`/`wizard`；默认 pageHead→notices；`.mode` 不双包；label 可为 ReactNode）；CSS：顶栏/品牌/Tab/wrap/页头/帮助链/页脚/栅格/节头 `Sec`/mode/向导/Snackbar 槽 |
| CARD | 01–12 | `Card`/`CardHead`/`CardFoot`/`Tile`/`Pill`/`Next`/`Notice`/`Empty`/`Saved`/`Scope`/`SimpleRow`/`SectionTitle` |
| CTRL | 01–05 (+06 companion)；12；16；17 | `Btn`；`Seg`/`SegItem`；`Toggle`；`Choice`/`ChoiceCards`；`Field`/`Hint`/`Opts`/`Opt`；`Spin`；`StatusDot`；`LoadError` |
| P2-charts | StatArea / Area | `StatArea`；`Area`（surface API）；另导出 helper `areaPath`（路径计算，非 surface） |

**未抽**：CARD-13/14/21 kit-later；CARD-15–20 **仅叶子自留（不抽）**；SHELL-14/15/16 **不抽**；CTRL-07+（chk-grid 等）；产品仓 import 变薄 / CARD-06 核验另 PR。

路由 / `wpcy/admin` store **不进包**——用 props 注入（`tabs`、`homeHref`、`notices`、`footerLinks`…）。

### 使用

```js
import '@wenpai/wpcy-admin-ui/tokens.css';
import '@wenpai/wpcy-admin-ui/p2a-surfaces.css';
// P2-charts (StatArea / Area): required — package does not auto-bundle this CSS.
import '@wenpai/wpcy-admin-ui/p2-charts.css';
import {
  PageShell, Btn, Card, CardHead, CardFoot, Tile,
  Pill, Notice, Empty, Next, Scope, Sec, Saved, SimpleRow, SectionTitle,
  Toggle, Seg, SegItem, Field, Hint, Choice, ChoiceCards,
  Spin, StatusDot, LoadError,
  StatArea, Area, areaPath,
} from '@wenpai/wpcy-admin-ui';

// 根节点必须带 .wpcy-app（PageShell 已挂）；短类 (.card/.btn/…) 仅在其下生效。
// P2-charts: surface API 为 StatArea + Area；areaPath 为可选 helper（路径计算）。
// Consumers must import `./p2-charts.css` (or `@wenpai/wpcy-admin-ui/p2-charts.css`,
// or another package CSS entry that includes it) whenever using StatArea / Area.
```

Accent 必须为 `#3858e9`（见 `tokens.css`）。禁止 stub `#2271b1`。  
不引入第三方 UI 库；交互原语可用 `@wordpress/components`（peer，可选）。

## 包名

npm：`@wenpai/wpcy-admin-ui`（私有；未发布到公共 registry 前仅 git/workspace 引用）。

## License

GPL-2.0-or-later（与 WordPress / 叶子插件一致）。

## Tokens

`src/tokens.css` is **generated** from wenpai-admin-ui `tokens/tokens.json` via `python3 scripts/build-tokens.py --target wpcy` (copy `css/wpcy-tokens.css`). Do not hand-edit production values. Accent must be `#3858e9` (never stub `#2271b1`).
