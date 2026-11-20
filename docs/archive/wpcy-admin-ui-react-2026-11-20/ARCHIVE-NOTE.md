# 归档说明：`@wenpai/wpcy-admin-ui`（React 版）

- **归档日期**：2026-11-20
- **原因**：`wpcy 4.0` 后台 UI 已按路线 A（见 `linuxjoy/docs/plans/wpcy-4.0/2026-11-20-route-a-redesign.md`）切换为统一 PHP 套件 `lib/wenpai-admin-ui`（`v0.11.3`）+ 轻量原生 JS `assets/js/wpcy-admin.js`。
- **长期约定**：保留本目录归档与 `tokens.json` 双目标生成（`--wenpai-*` / `--wpcy-*`），所有 REST `/wpcy/v1/*` 契约与 PHP 后端逻辑均与 UI 壳解耦。未来待 PHP kit 彻底成熟稳定后，如决定回归 React 或开发对等品 `@wenpai/wpcy-admin-ui`，可基于本归档与 REST 契约重启。
