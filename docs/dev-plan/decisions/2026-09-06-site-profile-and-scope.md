
## 补充决定（2026-09-06 晚，商业模型；见 linuxjoy 定稿 §7.1c）

- **连通性全部免费、无配额、无套餐**。此前"基线 / 受限免费 / 付费"三层作废；`Services/Entitlements` 里不再为连通性功能建配额，只承载第三方/文派服务（Windfonts、wei-*、小工具）的权益。
- **叶子是渠道，不卖功能**：商业价值 = 识别跨境 WooCommerce 店（`profile` + telemetry 的 Woo 字段）→ 在其后台经小工具容器分发文派付费产品（wei-* 支付/登录/通知/发票、微小朵服务）→ `/go/` 成交。小工具容器首批内容是这些入口。
- **商业内容露出条件由服务端规则下发**（场景为 `crossborder`/`mixed` 且检测到 WooCommerce），与通知规则、公告同一下发机制；`domestic` 场景几乎不露出。插件内不写死露出条件。
- **admin_assets 不规划商业化**：4.1 作为免费体验项交付。
- **跨境场景免费体验层**并入 M-SCOPE-1 范围（调研 F2/F3/F6/F10/F11：字体与头像后台作用域、Heartbeat 分屏节流、挡仪表盘外部内容、从管理员浏览器测速的连接诊断）。M-SCOPE-0 若已完成，ADR-004 与 M-SCOPE-1 任务书需按本节补一节"商业前提"，由统筹在审查时核。
- 调研出处：linuxjoy `docs/research/2026-09-06-crossborder-wp-admin-grok.md`。
