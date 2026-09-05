# 文派叶子 4.0 开发总计划（任务序列）

状态：定稿 v1.0 · 2026-09-04 · 统筹 linuxjoy · 产品 feibisi
用途：**代理按本表顺序领任务，不需要再问人。** 每个任务一份任务书在 `tasks/`，含目标、输入、交付物、禁区、验收命令。做完出报告，统筹验收后合并 `main`。

先读：`docs/4.0-rewrite-plan.md` → `docs/architecture/adr-00[123]*.md` → `docs/dev/coding-standards.md` → `docs/dev/agents.md` → 本任务对应的 `docs/specs/*`。

## 0. 工作方式（不变量）

- 分支：每个任务一个 worktree，分支 `grok/<task-id>`（或 `codex/<task-id>`），基于 `main` 最新。**不 push `main`**，统筹合并。
- 一个任务 = 一个 PR 大小（≤ 1500 行 diff 为宜）。大了拆。
- 报告格式见 `docs/dev/agents.md`：`git diff --stat`、每条验收命令与输出、没做/做不到/有疑问、提交哈希。
- 任何"收不收数据 / 默认开不开 / 删不删功能"的判断不在任务里做；对照 `docs/4.0-rewrite-plan.md` §12，不符就停并写进报告。
- 3.x 运行代码在 M4 之前**必须继续能跑**：`composer test:legacy`（29 个独立测试）是每个任务的固定门禁。
- 服务端尚未就位的能力（云桥 ingest、license-server 绑定、生产签名密钥、`apps.wpcy.com` 索引、公告聚合端点）：**模块存在、对 mock 开发、默认不启用**。不等服务端。
- **运行环境分工**（沿用文派 2026-08-18 既定分工，见 linuxjoy `docs/tools/wordpress-studio/wenpai-playbook.md`）：
  - **本地开发、代理自测、里程碑出口演示、3.9.3 升级烟测 → WordPress Studio**（开发机 wenpai VM；Mac 也有 Studio 1.15）。**CLI 路径是 `~/.studio/bin/studio`（1.15.0）**——`~/.local/bin/studio` 是 Electron 图形程序，无 DISPLAY 会直接退出，别用。建站要带 `--file-access all-files`，否则 Native PHP 只读站点目录、读不到 symlink 进来的插件检出。实机验证记录：`docs/dev-plan/verification/m1-studio-2026-09-04.md`（v4 下 `framework/` 零加载已在真实 WP 上成立）。4.0 站点固定为 `~/Studio/wpcy-40`（插件目录 symlink 到任务 worktree），命令一律 `studio start --path ~/Studio/wpcy-40 --skip-browser`、`studio wp --path ~/Studio/wpcy-40 <wp-cli>`、`studio stop --path …`。需要多站点或 MySQL 场景另起 `~/Studio/wpcy-40-ms` / `wpcy-40-mysql`（Studio 支持 MySQL 运行时，参照既有 `m31-mysql` 站）。Playwright 本地跑时 `BASE_URL` 指向 Studio 站。
  - **CI（GitHub Actions）→ 保留 wp-env**：Actions 跑不了 Studio；`.wp-env.json` 与 `tests/wordpress-smoke.sh` 只作为 CI 门闸存在，任何人不在本地用 wp-env。Playwright 在 CI 里指向 wp-env。
  - 任务书里凡写"wp-env 断言"的，代理在本地用 Studio 执行同样的 `wp` 命令即可，语义一致。

## 1. 里程碑

| 里程碑 | 目标 | 门禁 |
|--------|------|------|
| **M1 · 新内核 + 免费能力**（M1-01…M1-10） | `src/` 内核跑通，四页 React 后台 + 恢复页可用，免费连接优化全部由新模块承担，遥测与驻留记录由新模块发出 | 完全不加载 `framework/` 的代码路径存在（旧路径仍在但由开关切换）；断网 / 镜像故障 / 未绑定下免费能力可用；e2e 覆盖四页 + 恢复页 |
| **M2 · 迁移 + 服务客户端**（M2-01…M2-08） | 3.x 配置迁移（dry-run / 执行 / 回滚），站点绑定与权益（对 mock），小工具容器（对 mock manifest + mock 工具页），广告拦截与公告模块，Windfonts，发布链带编译产物 | 六份真实 fixtures 迁移通过且删除功能不误映射；桥接合同测试通过；发布 ZIP 含 `build/` 不含源码 |
| **M3 · 服务端接通**（M3-01…M3-05，含服务端任务） | license-server 绑定/权益端点、云桥 Tracker/Tracks ingest、`apps.wpcy.com` 索引与签名、公告聚合端点、生产密钥 | 真实绑定跑通一站；A 档改道对真实 ingest 打通（`ingest_ready` 探测为真才启用）；MotuSnap 权益真实下发 |
| **M4 · 删旧 + RC + 发版**（M4-01…M4-04） | 物理删除 `framework/`、`Service/`、`client/`、旧模板；RC；升级说明；分发链切到云桥 | 发布包不含旧路径；3.9.x → 4.0 → 停用 → 3.9.x 矩阵通过；恢复页可用 |

3.9.3 LTS 发版（P1）与本计划并行，见 `docs/release/3.9.3-runbook.md`。

## 2. 任务序列

依赖列里的任务必须已合并 `main`。「并行组」相同的任务可同时派出。

### M1

| ID | 任务 | 依赖 | 并行组 | 主要交付 | 验收要点 |
|----|------|------|--------|----------|----------|
| M1-01 | 仓根脚手架 | — | — | composer PSR-4 `src/`、phpcs/phpstan/phpunit、`@wordpress/scripts` 构建链、CI `quality`/`frontend`、发布脚本排除项 | `composer check` 与 `npm run build` 通过；legacy 29 仍过；ZIP 含 `build/` 不含源码 |
| M1-02 | Core 内核 | M1-01 | A | `src/Core/{Plugin,Container,Module,ConditionalModule,ModuleRegistry,Environment,Scope,Logger}`；`wp-china-yes.php` 加 `WPCY_KERNEL=v4` 常量开关（默认关，开则走新内核、不加载旧 `Plugin.php`） | 单元测试：注册顺序、依赖解析、单模块抛异常不阻断其它、场景过滤（admin/frontend/rest/cli/cron）；开关关时行为与 3.x 完全一致 |
| M1-02b | v4 路径不加载 `framework/`（Composer `files` → 3.x 显式 require） | M1-02 | B | `composer.json` 去掉 setup.class.php 的 `files` 加载，3.x 路径显式 require，顺序不变；v4 `get_included_files()` 无 `framework/` | 见 `tasks/M1-02b.md`；legacy 29 仍过 |
| M1-03 | Config | M1-01 | A | `src/Config/{Schema,Repository,Validator,Defaults}`，四个 option 键与 `schema_version`，未知键丢弃记 warning | 按 `docs/specs/config-schema.md` 逐字段测试；多站点网络/覆盖读取顺序测试 |
| M1-04 | Connectivity/WordPressOrg | M1-02, M1-03 | B | 元数据源与包源分离、失败回原上游、状态码+类型+体积校验、状态缓存 TTL；**行为对齐 3.9.3 `tests/test-wporg-mirror-fallback.php` 与 `test-mirror-health-fallback.php` 的全部断言** | 把这两份 3.x 测试的场景改写为 PHPUnit 并全过；旧测试仍过 |
| M1-05 | Connectivity/PublicAssets + Avatar | M1-02, M1-03 | B | 公共库白名单替换（节点故障保留原 URL）、Emoji 可选、Cravatar cn/global/off | 单元：白名单外不改写；节点不可用不改写；头像三种模式 |
| M1-05b | 内核接线：Repository 实现 `Core\Config`、五模块注册进 `Plugin::create()`、`weavatar`、审查建议 | M1-02b, M1-04, M1-05, M1-09 | C | 见 `tasks/M1-05b.md`；**唯一可改 `src/Core/Plugin.php` 的 C 组任务** | v4 打开后五模块工作；`PluginCreateTest` |
| M1-09b | 测试签名密钥对 + `scripts/sign-ruleset.php` + 重签驻留基线（M1-09 只入了公钥，基线改动后无法重签） | M1-05b | C | 见 `tasks/M1-09b.md` | privacy suite 无 skip；基线 `verified()===true` |
| M1-06 | Diagnostics + Site Health + WP-CLI | M1-04, M1-05 | C | 连接诊断（目标、结果、延迟、最近检查、建议）、Site Health 段、`wp wpcy status|doctor|config export|import` | wp-env 集成：CLI 输出 JSON schema；Site Health 段出现 |
| M1-07 | REST `wpcy/v1` 基础 + 恢复页 | M1-03, M1-06 | C'（M1-06 合并后） | `/settings` `/network-settings` `/diagnostics` `/diagnostics/run` `/recovery`；PHP 恢复页 `?page=wpcy-recovery` | 按 `docs/specs/rest-api.md`；权限/nonce/schema 校验测试；恢复页无 JS 可关全部改写（wp-env 断言） |
| M1-08 | 后台 React 应用（外壳 + 三页） | M1-07 | D | `src/Admin/app/`：store `wpcy/admin`、`<Page>` 布局、四页路由、命令面板注册；概览（公告占位）、连接优化（DataForm）、诊断（DataViews）；文派服务页占位 | 按 `docs/design/admin-ui-spec.md` 与原型；`npm run build` 体积 ≤ 300KB gz；键盘可达 |
| M1-09 | Telemetry + DataResidency（记录模式） | M1-02, M1-03 | B | `src/Telemetry`（移植 `client/class-site-health.php` 全部字段，常开无开关）；`src/Privacy/DataResidency`：内置基线 ruleset JSON、验签（测试公钥）、**只实现 `record` 与 `ignore`**，`reroute` 代码在但 `enabled_when=ingest_ready` 探测为假时不启用 | 报文与 3.9.3 `tests/test-telemetry*.php` 字段一致；B 档记录不含正文/查询串；A 档在 ingest 不可达时不改写 |
| M1-11 | M1 后端独立审查修复（多站点 `recovery_mode` 作用域、`config export` 三段、超时/sslverify、恢复页重定向、删旧常量） | M1-07 | D | 见 `tasks/M1-11.md`；审查报告 `verification/m1-backend-review-2026-09-04.md` | 新增多站点测试 PASS |
| M1-10 | e2e（Playwright） | M1-08 | — | 四页 + 恢复页 + 命令面板；CI `e2e` job（wp-env） | 全绿；截图存 `tests/e2e/__screenshots__` |

M1 出口：`WPCY_KERNEL=v4` 打开时，站点在 wp-env 下完成安装 → 设置 → 诊断 → 恢复 → 退出恢复全流程，旧 `framework/` 未被加载（用 `get_included_files()` 断言）。

### M2

| ID | 任务 | 依赖 | 并行组 | 主要交付 | 验收要点 |
|----|------|------|--------|----------|----------|
| M2-01 | Migration | M1-03 | E | `src/Migration/{Runner,LegacyReader,Mappers,Report,Backup}`；dry-run / 执行 / 回滚；`wp wpcy migrate --dry-run` | 六份 `tests/fixtures/legacy-options/*.json` 全部：dry-run 报告列出保留/忽略字段；执行后新 option 通过 schema；旧 option 不删；回滚可用 |
| M2-02 | Services/SiteBinding | M1-07 | E | 挑战流程客户端（对 mock license-server）、REST `/binding*`、公开只读 `/binding/challenge`、凭据加密存储 | 按 `docs/specs/entitlements.md`；凭据不进导出/日志；过期挑战不响应 |
| M2-03 | Services/Entitlements | M2-02 | F | 权益查询、1h 缓存、72h 兜底、降级钩子供模块查询 | 状态机测试：active/exhausted/expired/unreachable 四态下模块行为 |
| M2-04 | Apps 内核 | M1-07 | E | `src/Apps/{Registry,ManifestVerifier,DataStore,Index}`；表 `{prefix}wpcy_app_data`；REST `/apps*`；测试密钥 `tests/fixtures/keys/`（TEST ONLY） | 按 `docs/specs/apps-manifest-and-bridge.md`；验签通过/失败；越权拒；数据隔离；key 规则；64KB 上限 |
| M2-05 | Apps 桥接 + 文派服务页 UI | M2-03, M2-04, M1-08 | G | 宿主侧 postMessage 桥（origin/nonce/permission 裁剪）、`tests/fixtures/mock-app/` 一个 mock 工具页；文派服务页：绑定状态、权益配额、用量、小工具网格与容器 | 合同测试：每条消息类型；跨 origin 丢弃；e2e：加载 mock 工具、读写数据、无权益显示"获取" |
| M2-06 | NoticeControl + Announcements | M1-08 | F | `src/Admin/NoticeControl`（规则 JSON 下发、铁律：不隐藏核心通知、诊断页可查）；`src/Admin/Announcements`（固定源 JSON、24h 缓存、逐条关闭）；概览页 UI | 单元：核心通知永不匹配；关闭状态持久；离线不渲染错误 |
| M2-07 | Integrations/Windfonts | M1-05 | F | 移植 3.9.3 Windfonts（API 目录 + 缓存，参数与 CORS 修复保留） | 对齐 3.9.3 smoke 里 Windfonts 断言 |
| M2-09 | M2 独立审查修复（绑定 confirm 入口、`wpcy_entitlement_allows` 接线、NoticeControl 白名单铁律、expired 只读、A1–A9 e2e、12 条建议） | M2-05, M2-06 | — | 见 `tasks/M2-09.md`；审查 `verification/m2-services-review-2026-09-05.md` | 五阻断各有测试 |
| M2-08 | 发布链 | M1-01 | — | 版本号三处同步脚本、CHANGELOG 段生成、Plugin Check 进 CI、ZIP 含 `build/`、SHA-256 附件、`docs/release/4.0-runbook.md` | CI 出的 ZIP 装进 wp-env 激活成功 |

### M3（服务端与接通；插件侧任务标 P，服务端任务标 S）

| ID | 任务 | 仓 | 主要交付 |
|----|------|----|----------|
| M3-S1 | 绑定与权益端点 | `WenPai-org/license-server` | `/v1/site-connections*`、`/v1/sites/{hash}/entitlements`、MotuSnap 权益类型、签名服务（`kid` 两把） |
| M3-S2 | Tracker/Tracks ingest | `WenPai-org/wenpai-bridge` | 筛选入库 + 成功应答 + 健康端点（`ingest_ready`） |
| M3-S3 | 小工具索引与公告聚合 | wpcy.com（`wpcy-site` 插件 + `apps.wpcy.com`） | `index.json` 签发流程、`/wp-json/wpcy/v1/announcements`、广告规则 JSON |
| M3-P1 | 接真实服务端 | 插件 | `WPCY_SERVICES_API` 指生产、生产公钥入包、A 档 `enabled_when` 生效 |
| M3-P2 | 一站真实试点 | 插件 + 运营 | 文派自有站绑定、MotuSnap 权益下发、A 档改道观察一周 |

### M4

| ID | 任务 | 主要交付 |
|----|------|----------|
| M4-01 | 删旧（`tasks/M4-01.md`；影响分析 `verification/m4-delete-impact-2026-09-05.md`） | 物理删除 `framework/`、`Service/`、`client/`、`templates/`、`assets/`（先搬 `src/Admin/app` 用到的图）、旧 `Plugin.php`、`helpers.php`、`autoload-guard.php`、3.x 独立测试与 `WPCY_KERNEL` 开关；composer 去 `./` 映射与 `files`、去 `plugin-update-checker`；**把 `Migration\Runner` 接进内核首次启动**（否则升级站吃默认值）；`load_plugin_textdomain`；`tests/bootstrap-unit.php` 垫 `ABSPATH`；PHP 下限占位 8.0 由统筹开工时填；**撤掉 CI `plugin-check` job 对旧目录/旧文件的排除项**（2026-09-04 为让门禁只守 4.0 代码而加，Plugin Check 在旧代码里发现的文本域/转义/直接访问问题随删除一并消失） |
| M4-02 | 升级矩阵（`tasks/M4-02.md`） | 3.9.x → 4.0 → 停用 → 3.9.x；单站/多站点；损坏 option |
| M4-03 | RC 与文档（`tasks/M4-03.md`） | 升级说明、移除功能说明、readme.txt、官网 changelog/news 文案（交产品侧） |
| M4-04 | 发版（`tasks/M4-04.md`） | 按 `docs/dev/release.md`；分发切云桥；`plat-api` 停止返回 3.x 以外版本 |

## 3. 验收人清单（统筹用）

每个任务合并前：① 跑报告里的每条验收命令；② `composer test:legacy` 仍 29 过（M4 前）；③ diff 不越出任务书路径；④ 对照 §12 无产品决定被偷改；⑤ 文档同步（specs / CHANGELOG 未发布段）。

## 4. 待定项跟踪（M0 遗留，不阻塞 M1）

见 `docs/4.0-rewrite-plan.md` §12 与各 spec 的「待定（M0）」；统筹在 M2 开工前逐条关闭。

已由统筹关闭（2026-09-04，起草任务书时暴露）：

| 项 | 决定 |
|----|------|
| 3.x `cravatar = weavatar` | 4.0 `connectivity.avatar` 枚举**保留** `weavatar`（`cravatar_cn` / `cravatar_global` / `weavatar` / `off`），按 `docs/4.0-rewrite-plan.md` §7.2 原表映射，不改用户头像源。`docs/specs/config-schema.md` 已补。 |
| 3.x `adblock` | `off` → `modules.notice_control=false`；其它值 → `true`；`adblock_rule` 自定规则**不迁**（4.0 规则由 wpcy.com 下发），迁移报告记 `ignored`。 |
| 3.x 显式清空的 `admincdn*` 数组 | 视为用户明确选择，迁成空数组，**不**回填 schema 默认五项。 |
| 3.x `windfonts_list` | 迁入 `integrations.windfonts.fonts[]`（`family` / `subset` / `selector` / `enable`），迁移时对 API 字体目录重新校验，目录里不存在的条目记 `ignored`。`docs/specs/config-schema.md` 已补 `integrations.windfonts` 结构。 |
| 提交前缀 | 允许列表补 `feat(diagnostics): `、`feat(integrations): `、`feat(residency): `。 |
| M1-06 / M1-07 并行 | M1-07 依赖 M1-06，改为 M1-06 合并后再派。 |
| 3.x `admincdn_public` 未在 §7.2 映射表 | **映射进 `connectivity.public_assets`**（3.9.x 公共库勾选实际存于此键：`googlefonts→google_fonts`、`googleajax→google_ajax`、`cdnjs`、`jsdelivr`、`emoji`，其它值 ignored），与 `admincdn_files`/`admincdn_dev` 合并去重；三键都缺失 → schema 默认；任一存在但合并结果为空 → `[]`（显式清空）。否则只勾了 public 的站迁移后公共库加速全关（M2-01 报告发现）。任务 M2-01b。 |
| 退出恢复模式后是否自动恢复改写/模块 | **不自动恢复**：`exit` 只清 `recovery_mode`，用户在连接优化页手动开回。理由：恢复模式的触发原因（样式错乱/站点不可访问）未必已消除，自动开回可能立刻复现。React 概览横幅退出后提示「改写与模块仍处于关闭，前往连接优化开启」。 |
