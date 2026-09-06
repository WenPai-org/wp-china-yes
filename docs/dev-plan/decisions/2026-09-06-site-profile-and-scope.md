
## 补充决定（2026-09-06 晚，商业模型；见 linuxjoy 定稿 §7.1c）

- **连通性全部免费、无配额、无套餐**。此前"基线 / 受限免费 / 付费"三层作废；`Services/Entitlements` 里不再为连通性功能建配额，只承载第三方/文派服务（Windfonts、wei-*、小工具）的权益。
- **叶子是渠道，不卖功能**：商业价值 = 识别跨境 WooCommerce 店（`profile` + telemetry 的 Woo 字段）→ 在其后台经小工具容器分发文派付费产品（wei-* 支付/登录/通知/发票、微小朵服务）→ `/go/` 成交。小工具容器首批内容是这些入口。
- **商业内容露出条件由服务端规则下发**（场景为 `crossborder`/`mixed` 且检测到 WooCommerce），与通知规则、公告同一下发机制；`domestic` 场景几乎不露出。插件内不写死露出条件。
- **admin_assets 不规划商业化**：4.1 作为免费体验项交付。
- **跨境场景免费体验层**并入 M-SCOPE-1 范围（调研 F2/F3/F6/F10/F11：字体与头像后台作用域、Heartbeat 分屏节流、挡仪表盘外部内容、从管理员浏览器测速的连接诊断）。M-SCOPE-0 若已完成，ADR-004 与 M-SCOPE-1 任务书需按本节补一节"商业前提"，由统筹在审查时核。
- 调研出处：linuxjoy `docs/research/2026-09-06-crossborder-wp-admin-grok.md`。

## 补充（2026-09-06 晚，feibisi）：叶子只是接入客户端

Windfonts、Cravatar、公共库等资源的限流由各平台自己做；叶子只是接入客户端，按场景与作用域决定接哪个源，**不管额度**。当前资源跑在 cybercdn 上，用量可控。因此：插件内不出现配额、"配额用尽降级到上游"逻辑；`Services/Entitlements` / `Degrade` 只服务于小工具与服务分发链；词表删除"绑定后可用配额"一类文案（Windfonts 是否需要绑定由 Windfonts 平台决定，插件按服务端应答呈现）。各平台限流策略另议。

## 统筹拍板（2026-09-06 晚，回应 M-SCOPE-0 报告的疑问）

- **`privacy.data_residency`：采用方案 A + 保险**。A 档改道跟 `profile` 闸：`domestic` 维持现状（`ingest_ready` 才改道）；`crossborder` / `mixed` 不执行 A 档改道，B 档记录仍做，C 档不碰。保险：`profile=domestic` 时即使 geo 判定服务器在海外也改道（用户自称国内站以用户为准）。理由：A 档要挡的是"中国站数据出境"，不是"海外站回中国"；海外服务器绕国内云桥增加失败面，与叶子要解的问题方向相反。
- **cron 的作用域**：`WP-CLI` 视为 `admin`；`WP-Cron` 视为 `frontend`（保守：不做任何仅后台的改写；服务器侧取 .org 的行为不受作用域影响）。
- **D4 匹配表**：接受 M-SCOPE-0 写死的规则（`server_country === "CN"` 不含 HK/MO/TW；管理员境内 = locale `/^zh[-_]CN/i` 或时区 `Asia/Shanghai|Asia/Chongqing|Asia/Urumqi|PRC`）；`mixed` 不被建议、只能手选，接受。
- **`admin_assets` 进 `wpcy_site_overrides`**：接受。
- **不加 `profile_confirmed` 键**：接受，以"有迁移备份且从未 PUT `profile`"判断。
- **M-SCOPE-1 任务书须补两节**（由 M-SCOPE-0b 完成）："商业前提"（本文件"补充决定"两节：连通性无配额、叶子是接入客户端与渠道、露出规则服务端下发）与"跨境场景免费体验层"（调研 F2/F3/F6/F10/F11 中属于引擎侧的：字体与头像后台作用域即 D2 本身；Heartbeat 分屏节流；挡仪表盘外部内容；从管理员浏览器测速的连接诊断 REST 端点。UI 呈现进 M-SCOPE-UI）。§4 词表删除"绑定后可用配额"一类文案。
