# HTTP Block 插件分析（Grok 只读，2026-09-06）

> 统筹按语：源码来自 feibisi 未发布的内部插件 http-block-3.zip。本文是并入 4.0 的依据；决定见 docs/dev-plan/decisions/（待 feibisi 拍板三项：三层模型、L2 本站清单、是否设高级档）。

只读分析 HTTP Block，先读背景与插件源码，再给可拍板的并入方案。背景与插件源码并行读，先把范围钉死。用户要求只读，不写计划文件。开始逐文件翻插件。继续读规则引擎、规则类、数据库和 4.0 对照模块。继续读管理界面、性能、测试器和 4.0 对照模块。继续读管理端 AJAX、分组器，并核对 4.0 文档里的广告拦截订阅。HTTP Block（v2.3.1）是一个挂在 `pre_http_request` 上的出站拦截器，和 4.0 已删的 3.x「飞行模式」同类；并入时不能把「用户任意加域名」原样搬进来。下面按 7 节给拍板材料。

---

## 1. 功能清单

插件只拦 **服务器侧** `WP_Http`（`pre_http_request` priority 9999）。没有 `script_loader_src`、没有对 `wp_remote_*` 的第二层过滤、不改前端资源 URL。

### 1.1 用户可见功能

| 名称 | 做什么 | 作用层 | 选项键 | 默认 |
|------|--------|--------|--------|------|
| 自定义屏蔽规则 | 用户加 pattern，命中则返回 `WP_Error('http_request_not_executed')` | `pre_http_request` | `http_block_custom_rules`（site option 数组：pattern / description / tags / created） | `[]` |
| 白名单规则 | 命中则放行，优先级高于黑名单与内置拦 | 同上 | `http_block_whitelist_rules` | `[]` |
| 全局屏蔽模式 | 未命中任何规则的外部请求一律拦（本机/同站除外） | 规则引擎末档 | `http_block_block_all_external` | `false` |
| 来源追踪 | `debug_backtrace` 标插件/主题/核心 | 拦截时可选 | `http_block_enable_source_tracking` | `true` |
| 请求记录 | 自定义表记 url/host/method/status/reason/source/count | shutdown 批量写库 | 表 `{base_prefix}http_block_requests`；设置里 `http_block_settings.log_retention_days=30`、`max_request_records=1000` **未真正执行** | 见下 |
| 概览统计 | 总/拦/放、缓存命中、耗时分布、高频域名 | 管理页读库 | — | — |
| 规则测试 | 输入 URL，走同一引擎 | AJAX `http_block_test_rule` | — | — |
| 智能推荐 | 按库里频次建议加白/审查 | AJAX `http_block_get_recommendations` | — | — |
| 批量操作 | 删/移黑白名单/改标签 | AJAX | 同上两规则 option | — |
| 规则分组视图 | 按服务商/域名折叠显示 | 纯 UI | — | — |
| 导入导出 | JSON/CSV 规则；完整备份 JSON | 管理 POST + REST | 只动自定义规则，不动内置 | — |
| 仪表盘小工具 | 最近 10 条，一键加白/拉黑 | `wp_dashboard_setup` | 写回上述 option | — |
| REST | `/http-block/v1/rules|requests|stats|performance|export|import` | REST | 权限 `manage_network_options` | — |
| 日志清理 cron | 日任务 | cron `http_block_cleanup_logs` | **只裁 `wp-content/http-block.log` 文件，不清理请求表** | 30 天 / 10MB |

设置袋 `http_block_settings`（`enable_logging`、`log_retention_days`、`max_log_size`、`enable_request_logging`、`max_request_records`）在激活时写入，**运行时几乎不读**。`src/http-block/includes/class-http-block-activator.php:98-105`

### 1.2 规则类型与匹配语义

用户输入不选手动类型，由 pattern 形态分流：`src/http-block/includes/class-http-block-rule-engine.php:218-231`

| 类 | 触发 | 匹配语义 | 路径？ |
|----|------|----------|--------|
| `HTTP_Block_Regex_Rule` | pattern 以 `/ # ~ @ %` 开头 | `@preg_match` 对 **host 或完整 URL** | 正则可含路径 |
| `HTTP_Block_Wildcard_Rule` | 含 `*` 或 `?` | `fnmatch(pattern, host)` 或 `fnmatch(pattern, url)` | 可，取决于 pattern |
| `HTTP_Block_Contains_Rule` | 其余（默认，含用户域名） | `strpos(host)` 或 `strpos(url)` **子串** | 子串可命中路径，也会误伤 `notexample.com` 含 `example.com` |
| `HTTP_Block_Exact_Rule` | 无 | 精确等于 host 或 url | — |

**Exact 从未被 `create_rule_from_pattern` 实例化**，死代码。`includes/rules/class-http-block-exact-rule.php`

优先级（大者先）：内置白名单 100 → 自定义白名单 80 → 自定义黑名单 50 → 内置 URL 正则 45 → WP.org API 路径 40 → 内置域名 contains 30 → 全局策略 0。`class-http-block-rule-engine.php:75-85, 247-251`

内置白名单实际只有 cron：`*/wp-cron.php*`、`*doing_wp_cron*`。`class-http-block-cache.php:208-220`  
README 里的支付宝 / reCAPTCHA / Gravatar / Cloudflare **不是硬编码白名单**，规则说明页写「建议用户自己加」。`admin/class-http-block-admin.php:605-625`

内置黑名单约 100+ 条 contains（许可校验、IP 探测、CDN、云厂商控制台、`woocommerce.com`、`stats.wp.com`、`pixel.wp.com`、`api.freemius.com`，以及 **`updates.weixiaoduo.com`**）。另硬编码拦 `api.wordpress.org` 的 plugins/themes/core/events/translations 等路径，和 5 条 URL 正则（Woo upsells、licence check 等）。`class-http-block-cache.php:66-191`、`class-http-block-rule-engine.php:173-208`

同站 / cron 在 Core 入口直接放行且不记日志。`class-http-block-core.php:41-55, 349-409`

### 1.3 配套

| 配套 | 行为 | 引用 |
|------|------|------|
| 导入 | JSON/CSV，合并或替换；跳过空/超 512 字节/非法正则/重复 | `class-http-block-import-export.php:98-180` |
| 导出 | 规则 JSON/CSV；备份含 rules + 已废弃的 `http_block_settings`/`http_block_stats` | 同文件 `:25-89, 304-317` |
| 日志 | 自定义表，按 `url_hash` upsert 计数；记完整 URL | `class-http-block-database.php:43-65` |
| 统计 | 表级 COUNT/SUM；概览 Top 域名 | `class-http-block-request-repository.php:325-405` |
| 白名单 | 用户可编；内置仅 cron | 见上 |
| 标签 | 规则可选 tags，批量 replace/append | `class-http-block-config.php:828-858` |

---

## 2. 架构与质量

### 2.1 结构（类图级）

```
HTTP_Block（单例，plugins_loaded）
  ├─ HTTP_Block_Core          # pre_http_request @9999
  ├─ HTTP_Block_Rule_Engine   # 单例；Chain：load → sort → host_index → evaluate
  │    ├─ HTTP_Block_Rule_Interface
  │    │    └─ HTTP_Block_Rule_Abstract
  │    │         ├─ Contains / Wildcard / Regex / Exact
  │    └─ HTTP_Block_Cache    # 读 site_option + 内置数组（evaluate 主路径不走 Cache::check_url）
  ├─ HTTP_Block_Request_Repository : Repository_Interface
  ├─ HTTP_Block_Database      # dbDelta 两张表；规则表从未读写
  ├─ HTTP_Block_Activator     # 激活/升级/cron
  ├─ HTTP_Block_Admin / Config / Settings / Help
  ├─ Import_Export / Bulk_Operations / Rule_Tester / Rule_Grouper
  ├─ Dashboard_Widget / REST_API / Performance
```

存储：规则与开关全在 **site option**（多站网络级；单站 `get_site_option` 仍可用）。请求走自定义表 `{base_prefix}http_block_requests`。另建 `{base_prefix}http_block_rules` **从未使用**。`class-http-block-database.php:31, 67-84`

### 2.2 性能路径（每个出站 HTTP）

1. Core：cron / 同站短路（多站会 `get_sites(1000)` + 对象缓存 1h）。`class-http-block-core.php:385-401`
2. `evaluate`：进程内 `md5(url)` 缓存最多 1000 条。`class-http-block-rule-engine.php:284-363`
3. 号称 host 索引：用 `/([a-z0-9-]+\.[a-z]{2,})/i` 从 pattern 抽主机。对 `api.example.com` 抽成 `api.example`，与请求 host `api.example.com` 对不上 → **多数走全表扫描**。`class-http-block-rule-engine.php:265-272`
4. 命中则 `debug_backtrace(..., 50)`（来源开时对允许的请求也做）。`class-http-block-core.php:74-78, 172-175`
5. 入队，10 条或 shutdown 时 **逐条** `SELECT url_hash` + INSERT/UPDATE（不是真批量 INSERT）。`class-http-block-core.php:329-337`、`class-http-block-request-repository.php:182-223`
6. **每次评估** `update_site_option('http_block_performance_metrics')` 和 samples。`class-http-block-performance.php:52-60, 114-117, 217-222` —— 高流量站等于每个外部请求写 option，比拦截本身更贵。

`HTTP_Block_Cache::check_url`（另一套 1 分钟 URL 缓存）**没有任何调用方**，与引擎双轨。`class-http-block-cache.php:232-277`

### 2.3 安全

| 点 | 事实 | 引用 |
|----|------|------|
| 能力 | 网络菜单 / 多数 AJAX / REST = `manage_network_options`；仪表盘小工具 = `manage_options` | `class-http-block-admin.php:43, 61` vs `class-http-block-dashboard-widget.php:33, 277` |
| 单站 | 只挂 `network_admin_menu`，单站 **无设置页**；REST 在单站几乎无人有 `manage_network_options` | `class-http-block-admin.php:23, 56`；`class-http-block-rest-api.php:132-134` |
| nonce | AJAX 有 referer；Settings 表单 `options.php` 写的是博客 option，读的是 site option，全局开关保存路径是断的 | `class-http-block-admin.php:491-497`；`class-http-block-settings.php:165` |
| SQL | 列表查询用 `$wpdb->prepare`；`SHOW TABLES LIKE` 未 prepare（表名来自 prefix） | `class-http-block-request-repository.php:53, 156` |
| 正则 DoS | 长度 >512 改成 `/^$/`；非法正则降级；**无超时、无回溯限制** | `class-http-block-regex-rule.php:45-56` |
| XSS | 仪表盘刷新把 `request.url` 拼进 HTML | `class-http-block-dashboard-widget.php:148-151` |
| 缓存误伤 | `clear_url_cache()` 无参时 `wp_cache_flush()` 整站对象缓存 | `class-http-block-cache.php:362-368` |
| 激活 | `opcache_reset()` | `class-http-block-activator.php:204-206` |

### 2.4 i18n / 版本

- Text domain `http-block`，`load_plugin_textdomain` 指向 `/languages`，仓库 **无 languages 目录**。`http-block.php:15-16, 190-196`
- 界面中英混杂（导入成功句是英文）。`class-http-block-import-export.php:165`
- 要求：WP 5.0+ / PHP 7.4+ / `Network: true`。`http-block.php:11-14`  
  4.0 基线是 PHP 8.0+、WP 6.0+，并入必须升。

### 2.5 缺陷与坑（建议并入时丢掉或重写）

1. **与 4.0 连通性对撞**：内置拦 `api.wordpress.org/*`、`jsdelivr.net`、`cdnjs.cloudflare.com`、`woocommerce.com`、`stats.wp.com`、`pixel.wp.com`。叶子要把这些改道或镜像，HTTP Block 在 9999 上可能把已经镜像完的结果改成 Error。`class-http-block-rule-engine.php:173-191`；`class-http-block-cache.php:101-127`
2. **拦文派自己的更新**：`updates.weixiaoduo.com` 在内置黑名单。`class-http-block-cache.php:96`
3. **contains 误伤**：`woocommerce.com` 会命中 `xxx-woocommerce.com` 子串。`class-http-block-contains-rule.php:32-38`
4. **索引名不副实**，见 2.2。
5. **请求表不清理**：`delete_old()` 存在，cron 不调；`max_request_records` 不执行。`class-http-block-activator.php:323-347` vs `class-http-block-request-repository.php:423`
6. **激活把 db_version 写成 1.0**，覆盖 `create_tables` 的 2.1。`class-http-block-activator.php:139` vs `class-http-block-database.php:86`
7. **Settings API 与 site option 分裂**，全局模式开关在「工具」页很可能存不进实际读取的键。
8. **无测试、无 PHPStan、注释中文**，和叶子 4.0 规范不一致。
9. README 自相矛盾：正文写支持导入导出，FAQ 写暂不支持。`README.md:175-184, 258-259`

---

## 3. 与 4.0 的重叠与映射

对照仓：`wpcy-m-scope-1`（含 M-SCOPE-1 的 DataResidency / DashboardFeeds / Heartbeat / NoticeControl）。

| HTTP Block 功能 | 对照 | 建议模块 |
|-----------------|------|----------|
| 拦 `api.wordpress.org` 更新/info | **已有且方向相反**：`Connectivity/WordPressOrg` 改道到 `api.wenpai.net`（priority 100） | **不要移植** |
| 拦 `api.wordpress.org/events` | **已有**：`DashboardFeeds` 只拦 `/events/`，并摘新闻 widget | 已覆盖 |
| 拦 `stats.wp.com` / `pixel.wp.com` | **已有**：DataResidency A 档 reroute（domestic + ingest_ready） | 已覆盖；不拦死、要改道 |
| `woocommerce.com` / Freemius / 许可 | **部分**：DataResidency B 档 record，不拦 | 继续 B 档；不要改成用户可拦 |
| CDNJS / jsDelivr | **已有且方向相反**：`PublicAssets` 改写到 `*.admincdn.com` | **不要移植黑名单** |
| 后台广告/通知隐藏 | **不是一回事**：`NoticeControl` 藏 DOM 通知，规则服务端下发 | 广告拦截仍走 NoticeControl |
| Heartbeat | 无对应 | 已有 `Heartbeat`，无关 |
| 用户自定义拦/放任意域名 | **4.0 明确删除**：飞行模式「用户不可自行加域名」 | 见下「两层模型」 |
| 全局屏蔽一切外部请求 | 4.0 无；飞行模式的极端形态 | **不进 4.0** |
| 完整 URL 请求日志 + 来源回溯 | 4.0 无。B 档只记 host/data_class/count/last_seen，不记 URL | 诊断可展示 **host 计数**；完整 URL 日志不默认开 |
| 规则测试 / 推荐 / 分组 / 仪表盘拉黑 | 4.0 无 | 测试可进诊断；推荐/分组/小工具 **不移植** |
| 导入导出规则 | 4.0 有站点设置导入导出，无拦截规则包 | 若保留本站清单，走现有 config export，不单独做 |

### 自定义规则 vs「不给用户加任意域名入口」

**冲突，如果把 HTTP Block 的黑白名单当成 DataResidency 的编辑器。**  
定稿原文：主机表固定、用户不可加域名；改道 = 挡出国并给国内应答，禁止复制再放行。`.grok-context/data-residency-ruleset.md:7, 131-135`；`docs/4.0-rewrite-plan.md:123-124, 154`

**解法（研究员建议，两层，禁止合流）：**

| 层 | 谁维护 | 能做什么 | 不能做什么 |
|----|--------|----------|------------|
| L0 受保护主机 | 文派签名 ruleset + 客户端硬编码兜底 | 永远放行（叶子、许可、Cravatar、admincdn、云桥） | 用户规则命中也无效 |
| L1 驻留主机表 | 文派签名，现有 A/B/C | A 改道 / B 记录 / C 忽略 | 用户不可编辑；不可当拦截器 |
| L2 本站拦截清单 | 用户（可选） | **只能 block** | 不能 reroute、不能 allow 掉 L0/L1、不能加「改道目标」 |

优先级（先到先定，后续层不得推翻）：**L0 放行 → L1 驻留（改道/记录）→ L2 本站 block → 默认放行。**  
L2 不是「任意域名入口」：没有 target URL，没有白名单覆盖文派服务，没有全局 deny-all。

与 NoticeControl「广告拦截规则订阅」同构的是 **L1 的拦截包增量**（文派维护的 block 条，例如许可心跳噪声），不是用户订阅第三方规则列表。

---

## 4. 「文派自己的链接和服务不可禁用」

### 4.1 推荐方案：签名保护清单 + 保存时拒绝 + 运行时再挡一层

**清单内容（基线硬编码 ∪ 签名增量）：**  
`*.wenpai.net`、`license.wenpai.net`、`*.wpcy.com`、`cravatar.cn` / Cravatar 全球线、`*.admincdn.com`、云桥 ingest 主机、`api.wenpai.net`、`downloads.wenpai.net`、`updates.wenpai.net`、`ts.wenpai.net`。匹配语义与驻留表相同：`exact` / `suffix`，不做用户正则。

**更新与防篡改：** 并进现有 Ed25519 ruleset（新键 `protected_hosts` 或独立 kid `wpcy-protect-2026`），规范化 JSON + 签名；验签失败丢弃增量、沿用内置。公钥与驻留表同一套发布流程。`.grok-context/data-residency-ruleset.md:41-43, 126-131`

**用户规则命中保护主机：** **拒绝保存并提示**（表单/REST 400，文案写明「文派服务不可拦截」）。运行时若旧数据或直接写 option：**静默忽略该条**（双重门，防绕过 UI）。不要只做静默：用户会以为规则生效，排障成本高。

**多站点：** 网络管理员独写 L2 与保护清单的「只读展示」；子站管理员只读诊断、不能改网络规则（与现有 `wpcy_network_settings` / 站点覆盖同一边界）。L0 不允许站点覆盖。HTTP Block 现状是网络 option + 子站仪表盘却用 `manage_options` 能改网络规则，这条必须改掉。`class-http-block-dashboard-widget.php:277-315`

### 4.2 方案对比

| | A 推荐：拒保存 + 运行时忽略 | B 仅静默忽略 + 界面说明 | C 保护清单可被白名单覆盖（HTTP Block 现状） |
|--|---------------------------|-------------------------|-----------------------------------------------|
| 用户预期 | 保存失败，原因清楚 | 规则在列表里但不生效 | 可关掉文派更新/头像/CDN |
| 防篡改 | UI + 运行时 + 签名 | 仅运行时 | 无 |
| 排障 | 好 | 差（「我加了为什么还请求」） | 会把叶子打残 |
| 多站 | 网络写、子站只读 | 同左需另定 | 子站 `manage_options` 可改网络规则 |
| 与驻留表 | 同签名通道 | 同 | 冲突 |

**替代（不推荐）：** 把保护主机做成用户可见、可关的「系统白名单」（HTTP Block 规则说明页就是这个思路，`admin/class-http-block-admin.php:621-624`）。产品要求是不可禁用，不能做成可删白名单。

---

## 5. 免费 / 高级切分建议（研究员建议，统筹拍板）

前提：连通性无配额、叶子不卖功能、商业露出走服务端；「高级」若采用，应是 **服务/权益上的运维能力**，不是把拦截本身做成付费墙。若统筹坚持「4.0 不卖功能」，把下表「高级」整列改成 **不做**。

| 功能 | 建议桶 | 理由 |
|------|--------|------|
| L0 受保护主机（不可关） | **服务端规则下发** | 产品硬约束；签名增量，不收费、不开放编辑 |
| L1 驻留 A/B/C | **免费·所有场景**（A 档仍跟 profile 闸） | 已定；合规不能收费 |
| 文派维护的「噪声拦截包」（许可心跳、已知无用 API，**不含** .org / CDN / 文派主机） | **服务端规则下发** | 与 NoticeControl 广告规则同构：文派维护、用户只开/关整包，不编辑条目 |
| L2 本站拦截清单（只 block，有上限） | **免费·所有场景**（建议上限 20 条，仅 exact/suffix 主机，无正则） | 国内站也有「某个插件乱请求」；条数上限是防自伤和正则 DoS，不是套餐 |
| 正则 / 通配 / 路径规则 | **高级（经服务/权益）** 或 **不做** | 误伤面大，和「不给任意入口」擦边；若 4.0 不卖功能 → 不做 |
| 全局屏蔽一切外部请求 | **不做** | 会掐死支付、物流、验证码、叶子自己的探测 |
| 完整 URL + 来源回溯日志 | **不做**（默认）；诊断页 host 计数 | 与驻留「不记 URL/查询串」同政策；backtrace 贵 |
| 日志保留 | 诊断 host 计数跟随驻留 log option，无单独 30 天表 | 自定义表 + cron 失效，不要搬 |
| 导入导出拦截规则 | **不做** 独立功能；本站清单进现有设置导出 | 降低变成「规则市场」的面 |
| 规则订阅（第三方列表） | **不做**；只要文派签名包 | 第三方列表 = 任意域名入口 |
| 多站点统一策略 | **免费·所有场景** | 4.0 已有网络默认 + 站点覆盖；L2 只允许网络层 |
| 规则测试（诊断里测一条 URL 会不会被 L1/L2 拦） | **免费·所有场景** | 排障需要，成本低 |
| 智能推荐 / 仪表盘拉黑 / 分组 UI | **不做** | 会诱导用户把任意 host 写进 L2；仪表盘权限还弱于网络管理 |
| Heartbeat / DashboardFeeds | 已是 **免费·仅跨境体验层** | 不属 HTTP Block |

分层维度结论：不要用「条数/保留天数/导入导出」做付费阶梯（和「不卖功能」打架）。用 **能力是否开放编辑** 切：文派表免费下发、用户表极窄且免费、其余不做。

---

## 6. 迁移到 4.0 的工作量

| 模块 | 规模 | 依赖 |
|------|------|------|
| 产品/ADR：推翻或收窄「飞行模式删除」，写入两层模型 + 保护主机 | **S**（文档） | 统筹拍板；不拍则代码不应做 L2 |
| L0 `protected_hosts` 并进签名 ruleset + 硬编码兜底 + 验签 | **M** | 现有 `Privacy/DataResidency/Ruleset`；服务端下发 **新字段**（不必新规则类型 action，可同文档加键） |
| L1 不动，只保证 filter 顺序：保护 → 驻留 →（可选）L2 | **S** | M-SCOPE-1 合入（profile 闸 A 档已在 DataResidency） |
| L2 `Privacy/SiteBlocklist`（或挂 DataResidency 旁，**不要**新品牌 `HttpBlock`）主机 exact/suffix、上限、拒保护主机 | **M** | Schema + Repository；网络设置；REST 走现有 Settings |
| 诊断：只读展示 L0/L1/L2 + 规则测试 | **S–M** | 现有 Diagnose / ResidencyController |
| 文派噪声拦截包（签名 block 条） | **M** | 服务端 ruleset 增量；云桥/发布流程 |
| **明确不移植**：内置 100+ 黑名单、全局模式、请求表、Performance option 写入、REST 独立命名空间、仪表盘小工具、推荐、分组、contains/regex 用户入口 | **S**（删/忽略） | 无 |
| 从 HTTP Block 站迁规则 | **不需要**（从未发布） | — |
| 测试（保护主机、顺序、profile 闸、多站） | **M** | PHPUnit 风格与 M-SCOPE-1 一致 |
| UI（诊断只读 + 可选 20 条主机清单） | **M** | M-SCOPE-UI / 设计门禁 |

合计：文档 S + 引擎 M×2 + UI M。不建议单开 `Privacy/HttpBlock` 品牌——名字会让人以为是「用户防火墙」，和已删飞行模式撞车。

---

## 7. 没做 / 做不到 / 有疑问

**没做**

- 未跑插件、无 PHPUnit（插件本身也没有测试目录）。
- 未改文件、未 git。
- 未核对云桥合同里保护主机的最终 FQDN 列表（admincdn / 云桥 ingest 以 devops 为准）。
- 未读 3.x `Super.php` 飞行模式源码，只据 4.0 文档定性「同类删除项」。

**做不到（技术/政策）**

- 不能把 HTTP Block 内置黑名单原样并入：会拆 4.0 镜像与改道。
- 不能在 `pre_http_request` 拦前台浏览器第三方脚本（插件从未做 `script_loader_src`）。
- 不能既「用户随便加域名」又「可审计的固定主机表」——必须两层。
- 单站 HTTP Block 管理界面本身就是残的，没有可复用的 UI 资产。

**有疑问（需统筹拍板）**

1. **4.0-rewrite-plan §4.3 已删飞行模式。** 本次是「有限度复活 L2（20 条、只拦、不能改道）」还是「只做 L0+L1 签名包、用户零编辑」？研究员倾向：**用户零编辑也能交差**（保护清单 + 文派噪声包 + 诊断只读）；L2 是产品加分，不是 4.0 首发必需。
2. L2 若做，算不算「卖功能」？建议不算，且不要进 Entitlements。
3. `updates.weixiaoduo.com` 是否仍是生产更新主机，必须进 L0。
4. 驻留 B 档是否扩展为「诊断可见的全部出站 host 计数」（仍不记 URL）——这是 HTTP Block 日志里唯一值得留的信号，但是否扩大隐私面要产品点头。
