# M4-02 升级矩阵验证报告

日期：2026-09-06  
分支：`grok/m4-02`  
执行仓：Mac worktree `/Users/feibisi-studio/wt/wpcy-m4-02`  
运行环境：**wenpai VM WordPress Studio**（未用本机 Docker / wp-env）

**M4-02b（同日）**：产品确认 3.8 `admincdn` 键存在（`[]` / `''` / 数组）则 `public_assets` 只由该键（并 3.9 三键）推导，为空不回落五项默认。代码 `bea80ea`。下节「M4-02b 补证」为审查 #2/#3/#7 的命令原文。

Studio CLI：`/home/parallels/.studio/bin/studio` **1.15.0**（`ssh wenpai`）。不是 `~/.local/bin/studio`。

| 站 | 路径 | URL | WP / PHP | 库 |
|----|------|-----|----------|----|
| 单站 | `/home/parallels/Studio/wpcy-40` | `http://localhost:8891` | 7.1 / 8.3.32 Native | SQLite |
| 多站点 | `/home/parallels/Studio/wpcy-40-ms`（本任务 `studio create` 后 `wp core multisite-convert`） | `http://localhost:8892` | 7.1 / 8.3.32 Native | SQLite |

`wp` 一律：`/home/parallels/.studio/bin/studio wp --path <站> …`

4.0 包 Version 头仍是 `3.9.3`（M4-03 才改 rc.1）。下面用「4.0 包」指本分支 `scripts/build-release.sh` 产物：`Requires PHP: 8.0` + `\WenPai\ChinaYes\Core\Plugin::boot()`，无 `framework/`。

---

## 发行 ZIP

| 包 | 来源 | SHA-256 | 备注 |
|----|------|---------|------|
| `wp-china-yes.3.8.zip` | GitHub Release **v3.8** 附件（`prerelease=false`） | `414c8c4c911f3cf55fc01a228076113786cd39352b4f86a1d3285a680a28d8d0` | 发行 ZIP；`unzip -Z1` 无 `.git`/`tests/`/`docs/` |
| `wp-china-yes-3.9.3.zip` | GitHub Release **v3.9.3** 附件（`prerelease=true`，通道已撤回） | `a2ac53599a845822fe4bdc94f04659303e6b856cf29e94172b963de074dede05` | 与 Release `.sha256` 附件一致。任务书写「尚未放行」：通道未切到 3.9.3，但附件存在，按 runbook「GitHub Release 附件」使用，**不是**源码包、**不是** 4.0 工作树 |
| `wp-china-yes-4.0.zip` | 本分支 `bash scripts/build-release.sh`（M4-02 `31760da`） | `8a9ff499dc8cfe853b8cc0b9779a428ba4b6a586d84f50962095521ed354277a` | 仓内文件名仍是 `dist/wp-china-yes-3.9.3.zip` |
| `wp-china-yes-4.0.zip`（M4-02b） | `bea80ea` 后 `bash scripts/build-release.sh` | `e53c8f4348fbe1a6bd870f2cd5ee83b75d5f8218cf44f4138159fda31a03917d` | 装到 `wpcy-40` / `wpcy-40-ms` 跑补证 |

3.8 / 3.9.3 无 `wp wpcy migrate`。每行顺序：**先装 3.x 并写入样本 → 换成 4.0 并启用（首次启动 `Plugin::maybe_migrate_from_legacy` 已 execute）→ 再 dry-run**。与任务书「若 3.9.3 无此命令：先换成 4.0 再 dry-run」一致。

预置只写 fixture 的 `wp_china_yes` 对象（`python3` 抽出），外层 `_fixture` 不入库。

统筹 2026-09-06：3.8 → 4.0 → 停用 → 装回 3.8 为绝大多数站点路径；3.9.3 为次要。本矩阵：S1/S2/N1/N2/N3/D2 用 3.8 ZIP 起步并装回 3.8，再额外装回 3.9.3 核对哈希；S3/D1 用 3.9.3 ZIP 起步并装回 3.9.3（D1 需要 3.9.3 损坏守卫）。

---

## 代码改动（矩阵前已合入 `31760da`）

对照 `docs/dev-plan/verification/3x-options-audit-2026-09-06.md` §5：

1. `store=proxy` → 与 `wenpai` 相同，写成 `connectivity.wordpress_org=auto`（`Mappers.php:267`）。
2. 3.8 `admincdn` 键存在（不论 `[]` / `''` / 数组）→ `public_assets` 由该键（并 3.9 三键并集）推导；`googlefonts`/`googleajax`/`cdnjs`/`jsdelivr` 进枚举，`admin`/`frontend`/`bootstrapcdn` 及未知 token 进 `ignored`；推导为空则 `[]`，**不**回落五项默认（M4-02b 产品决定）。
3. `hide_*` / 内存四项 / 产品壳 / 幽灵 `enabled_sections`：**丢弃**。4.0 schema 无白标菜单、无 `WP_MEMORY_LIMIT`。未把删除功能映射到其它 4.0 能力。

未改 fixtures JSON 数据，未改 `docs/dev-plan/README.md`。

---

## 八行矩阵

哈希算法：`hash("sha256", wp_json_encode(get_option|get_site_option("wp_china_yes")))`。同一行四个阶段（预置 / 4.0 启用后 / 装回 3.8 / 装回 3.9.3）哈希相同即 option 未被 4.0 改写。

### S1 单站 3.6.2 · `single-3.6.2-01.json`

站：`wpcy-40` `http://localhost:8891`  
3.x ZIP：3.8（样本来自 3.6.2；3.x 只负责持有 option）

```
studio wp --path ~/Studio/wpcy-40 plugin install /tmp/wpcy-m4-02-zips/wp-china-yes.3.8.zip --force
studio wp --path ~/Studio/wpcy-40 plugin activate wp-china-yes
# → 3.8
studio wp --path ~/Studio/wpcy-40 option update wp_china_yes '<wp_china_yes JSON>' --format=json
# Success: Updated 'wp_china_yes' option.
# 哈希 328568b8a07a56af5dca5ebe84fe9063e659c68072e3e753ab81cf19e78ea339
# 键 store,admincdn,cravatar,windfonts,adblock
# 换成 4.0 包并启用后：
studio wp --path ~/Studio/wpcy-40 eval 'echo "eval_ok PHP=".PHP_VERSION;'
# eval_ok PHP=8.3.32   eval_exit=0
studio wp --path ~/Studio/wpcy-40 wpcy migrate --dry-run --format=json
```

dry-run：`kept=["store","cravatar","windfonts","adblock"]` `ignored=["admincdn"]`（`unsupported_whitelist`）。  
`wpcy_settings` 非空：`wordpress_org=off` `avatar=weavatar` **`public_assets=[]`**（M4-02b：`admincdn=[]` 键存在，显式空，不回落五项）。  
`wp_china_yes` 仍在，内容未改。前台 `curl http://localhost:8891/` → **200**。  
装回 3.8 与 3.9.3：哈希均为 `328568b8…`，键集合不变。

M4-02b 复跑 `wpcy migrate --dry-run --format=json`：

```
{"action":"dry-run","kept":["store","cravatar","windfonts","adblock"],"ignored":["admincdn"],"ignored_reasons":{"admincdn":"unsupported_whitelist"},"settings":{"schema_version":1,"connectivity":{"wordpress_org":"off","public_assets":[],"avatar":"weavatar"},"modules":{"notice_control":false,"windfonts":false},"integrations":{"windfonts":{"fonts":[]}},"diagnostics":{"scheduled_checks":true},"data_residency":{"ruleset_version":1},"announcements":{"dismissed":[]},"apps":{"disabled":[]},"recovery_mode":false}}
```

`option get wpcy_settings --format=json`（execute 后）：

```
{"schema_version":1,"connectivity":{"wordpress_org":"off","public_assets":[],"avatar":"weavatar"},"modules":{"notice_control":false,"windfonts":false},"integrations":{"windfonts":{"fonts":[]}},"diagnostics":{"scheduled_checks":true},"data_residency":{"ruleset_version":1},"announcements":{"dismissed":[]},"apps":{"disabled":[]},"recovery_mode":false}
```

### S2 单站 3.8 · `single-3.8-02.json`（3.8 直升路径）

```
studio wp --path ~/Studio/wpcy-40 plugin install /tmp/wpcy-m4-02-zips/wp-china-yes-4.0.zip --force --activate
studio wp --path ~/Studio/wpcy-40 eval 'update_option("wp_china_yes", json_decode(file_get_contents("/tmp/wpcy-m4-02b-out/S2.payload.json"), true)); echo "seed_ok keys=".count(get_option("wp_china_yes"));'
# seed_ok keys=15
studio wp --path ~/Studio/wpcy-40 wpcy migrate --dry-run --format=json
studio wp --path ~/Studio/wpcy-40 option get wpcy_settings --format=json
studio wp --path ~/Studio/wpcy-40 option get wp_china_yes --format=json
```

哈希 `49b4f2acb853f8da560846b94e1ab419bd521d74859aa8d4fdd7be4fd2f6e0ed`（与 M4-02 预置相同）。键含 `admincdn=["admin"]`。

`wpcy migrate --dry-run --format=json`：

```
{"action":"dry-run","kept":["store","cravatar","windfonts","adblock"],"ignored":["admincdn","windfonts_typography","windfonts_list","adblock_rule","plane","plane_rule","monitor","memory","memory_display","custom_name","hide","admin"],"ignored_reasons":{"admincdn":"unsupported_whitelist","windfonts_typography":"feature_removed","windfonts_list":"empty","adblock_rule":"replaced_by_remote","plane":"feature_removed","plane_rule":"feature_removed","monitor":"feature_removed","memory":"feature_removed","memory_display":"feature_removed","custom_name":"feature_removed","hide":"feature_removed","admin":"unsupported_whitelist"},"settings":{"schema_version":1,"connectivity":{"wordpress_org":"off","public_assets":[],"avatar":"cravatar_cn"},"modules":{"notice_control":false,"windfonts":true},"integrations":{"windfonts":{"fonts":[]}},"diagnostics":{"scheduled_checks":true},"data_residency":{"ruleset_version":1},"announcements":{"dismissed":[]},"apps":{"disabled":[]},"recovery_mode":false}}
```

`option get wpcy_settings --format=json`：

```
{"schema_version":1,"connectivity":{"wordpress_org":"off","public_assets":[],"avatar":"cravatar_cn"},"modules":{"notice_control":false,"windfonts":true},"integrations":{"windfonts":{"fonts":[]}},"diagnostics":{"scheduled_checks":true},"data_residency":{"ruleset_version":1},"announcements":{"dismissed":[]},"apps":{"disabled":[]},"recovery_mode":false}
```

`option get wp_china_yes --format=json`（4.0 未改写）：

```
{"store":"off","admincdn":["admin"],"cravatar":"cn","windfonts":"optimize","windfonts_typography":"","windfonts_list":"","adblock":"off","adblock_rule":"","plane":"off","plane_rule":"","monitor":"0","memory":"1","memory_display":["memory_usage","wp_limit","hostname","php_info","cpu_usage","debug_status","mysql_version"],"custom_name":"\u53f6\u5b50","hide":""}
```

`ignored` 含 token `admin`（与键 `admincdn` 并存）。eval_exit=0。装回 3.8 / 3.9.3 哈希均为 `49b4f2ac…`。

### S3 单站 3.9.3 · `single-3.9.3-03.json`（59 键，次要路径）

3.x ZIP：3.9.3。键数 **59**。哈希 `d57baac6652bc59ce64d7478ec4e3f9285eea15e724cf47d0ec26564a93d08b6`。

dry-run：`kept=["store","cravatar","windfonts","windfonts_list","adblock","admincdn_public","admincdn_files","admincdn_dev"]`；**ignored 51**，含删除功能键且未误映射：

| 键 | reason |
|----|--------|
| arkpress, motucloud, fewmail, comments_*, plane*, monitor, memory*, disk*, maintenance_*, performance, wp_memory_limit, wp_max_memory_limit, wp_post_revisions, autosave_interval, hide_option, hide_menu, hide_menu_confirm, enabled_sections, quick_select, custom_rss_*, wordyeah 所在壳 | `feature_removed` |
| adblock_rule | `replaced_by_remote` |
| bridge | `login_state` |
| notice_control | `empty`（样本值为空串） |
| admincdn | `unsupported_whitelist` |

`wpcy_settings`：`wordpress_org=auto` `public_assets=[]` `avatar=cravatar_cn`，含 3 条 windfonts。eval_exit=0，前台 200。装回 3.9.3 哈希 `d57baac6…`。

### S07 单站 `store=proxy` · `single-3.9-07-store-proxy.json`（审查 #3）

由 `single-3.9.3-03.json` 复制，仅 `store` 改为 `proxy`。预置后 `wp_china_yes` 哈希 `0b46185dc76259f69c6dd738956660fcb76cbf743f9b06d82d30af4a6ea717aa`。

```
studio wp --path ~/Studio/wpcy-40 wpcy migrate --dry-run --format=json
studio wp --path ~/Studio/wpcy-40 option get wpcy_settings --format=json
```

dry-run `kept` 同 S3 八键；`settings.connectivity.wordpress_org=auto`（与 `wenpai` 相同）。`option get wpcy_settings`：

```
{"schema_version":1,"connectivity":{"wordpress_org":"auto","public_assets":[],"avatar":"cravatar_cn"},"modules":{"notice_control":true,"windfonts":true},"integrations":{"windfonts":{"fonts":[{"family":"wenfeng-albbpht","subset":"full","selector":"a:not([class]),p,h1,h2,h3,h4,h5,h6,ul,ol,li,button,blockquote,pre,code,table,th,td,label,b,i:not([class]),em,small,strong,sub,sup,ins,del,mark,abbr,dfn,span:not([class])","enable":true},{"family":"wenfeng-syhtcjk","subset":"full","selector":"a:not([class]),p,h1,h2,h3,h4,h5,h6,ul,ol,li,button,blockquote,pre,code,table,th,td,label,b,i:not([class]),em,small,strong,sub,sup,ins,del,mark,abbr,dfn,span:not([class])","enable":false},{"family":"wenfeng-ibmps","subset":"full","selector":"a:not([class]),p,h1,h2,h3,h4,h5,h6,ul,ol,li,button,blockquote,pre,code,table,th,td,label,b,i:not([class]),em,small,strong,sub,sup,ins,del,mark,abbr,dfn,span:not([class])","enable":false}]}},"diagnostics":{"scheduled_checks":true},"data_residency":{"ruleset_version":1},"announcements":{"dismissed":[]},"apps":{"disabled":[]},"recovery_mode":false}
```

`option get wp_china_yes` 仍是 `"store":"proxy"`（4.0 未改写 3.x）。

### N1 多站点 3.7.1 · `multisite-3.7.1-04.json` → **site_option**

站：`wpcy-40-ms` `http://localhost:8892`

```
studio wp --path ~/Studio/wpcy-40-ms plugin activate wp-china-yes --network
studio wp --path ~/Studio/wpcy-40-ms site option update wp_china_yes '…' --format=json
# Success: Updated 'wp_china_yes' site option.
# 哈希 4d03c20e7d129680ac0d622b169e81f319b1893428babf13e4e0580bd0f50eed
```

4.0 启用后：`wpcy_network_settings` 非空（含 `allow_site_override=true`）；子站 `wpcy_settings` **ABSENT_OK**。  
dry-run kept 同 S1 四键。**`public_assets=[]`**（`admincdn=""` 键存在）。eval_exit=0，前台 200。装回 3.8 / 3.9.3 哈希 `4d03c20e…`。

M4-02b 复跑 dry-run：

```
{"action":"dry-run","kept":["store","cravatar","windfonts","adblock"],"ignored":["admincdn","windfonts_list","windfonts_typography","adblock_rule","plane","plane_rule","monitor","hide","custom_name"],"ignored_reasons":{"admincdn":"unsupported_whitelist","windfonts_list":"empty","windfonts_typography":"feature_removed","adblock_rule":"replaced_by_remote","plane":"feature_removed","plane_rule":"feature_removed","monitor":"feature_removed","hide":"feature_removed","custom_name":"feature_removed"},"settings":{"schema_version":1,"connectivity":{"wordpress_org":"off","public_assets":[],"avatar":"weavatar"},"modules":{"notice_control":false,"windfonts":false},"integrations":{"windfonts":{"fonts":[]}},"diagnostics":{"scheduled_checks":true},"data_residency":{"ruleset_version":1},"announcements":{"dismissed":[]},"apps":{"disabled":[]},"recovery_mode":false,"allow_site_override":true}}
```

### N2 多站点 3.8 · `multisite-3.8-05.json`

```
studio wp --path ~/Studio/wpcy-40-ms site option update wp_china_yes … --format=json
# 本轮用 eval + file_get_contents 写入 fixture 的 wp_china_yes
studio wp --path ~/Studio/wpcy-40-ms wpcy migrate --dry-run --format=json
studio wp --path ~/Studio/wpcy-40-ms site option get wpcy_network_settings --format=json
studio wp --path ~/Studio/wpcy-40-ms site option get wp_china_yes --format=json
studio wp --path ~/Studio/wpcy-40-ms option get wpcy_settings --format=json
```

哈希 `a4371f71e595b2358d79044381748b6737eb64c30c805ba4eb4947b3a8010f6b`（与 M4-02 四阶段相同）。

`wpcy migrate --dry-run --format=json`：

```
{"action":"dry-run","kept":["store","cravatar","windfonts","adblock"],"ignored":["admincdn","windfonts_list","windfonts_typography","adblock_rule","plane","plane_rule","monitor","memory","hide","custom_name"],"ignored_reasons":{"admincdn":"unsupported_whitelist","windfonts_list":"empty","windfonts_typography":"feature_removed","adblock_rule":"replaced_by_remote","plane":"feature_removed","plane_rule":"feature_removed","monitor":"feature_removed","memory":"feature_removed","hide":"feature_removed","custom_name":"feature_removed"},"settings":{"schema_version":1,"connectivity":{"wordpress_org":"off","public_assets":[],"avatar":"weavatar"},"modules":{"notice_control":false,"windfonts":false},"integrations":{"windfonts":{"fonts":[]}},"diagnostics":{"scheduled_checks":true},"data_residency":{"ruleset_version":1},"announcements":{"dismissed":[]},"apps":{"disabled":[]},"recovery_mode":false,"allow_site_override":true}}
```

`site option get wpcy_network_settings --format=json`：

```
{"schema_version":1,"connectivity":{"wordpress_org":"off","public_assets":[],"avatar":"weavatar"},"modules":{"notice_control":false,"windfonts":false},"integrations":{"windfonts":{"fonts":[]}},"diagnostics":{"scheduled_checks":true},"data_residency":{"ruleset_version":1},"announcements":{"dismissed":[]},"apps":{"disabled":[]},"recovery_mode":false,"allow_site_override":true}
```

`site option get wp_china_yes --format=json`：

```
{"store":"off","admincdn":[],"cravatar":"weavatar","windfonts":"off","windfonts_list":[],"windfonts_typography":[],"adblock":"off","adblock_rule":[],"plane":"off","plane_rule":[],"monitor":true,"memory":true,"hide":false,"custom_name":"WP-China-Yes"}
```

子站 `option get wpcy_settings`：`Error: Could not get 'wpcy_settings' option. Does it exist?`（ABSENT_OK）。`admincdn=[]` → **`public_assets=[]`**（M4-02b，不再五项默认）。

### N3 多站点 3.8 变体 · `multisite-3.8-06.json`（`admincdn` 空串）

命令同 N2（fixture 换成 `multisite-3.8-06.json`）。哈希 `d85413acd5e29c33f23f10b18439c8c4d48664eb16c0643e7ba63b9099ce78ee`。

`wpcy migrate --dry-run --format=json`：

```
{"action":"dry-run","kept":["store","cravatar","windfonts","adblock"],"ignored":["admincdn","windfonts_list","windfonts_typography","adblock_rule","plane","plane_rule","monitor","memory","hide","custom_name"],"ignored_reasons":{"admincdn":"unsupported_whitelist","windfonts_list":"empty","windfonts_typography":"feature_removed","adblock_rule":"replaced_by_remote","plane":"feature_removed","plane_rule":"feature_removed","monitor":"feature_removed","memory":"feature_removed","hide":"feature_removed","custom_name":"feature_removed"},"settings":{"schema_version":1,"connectivity":{"wordpress_org":"off","public_assets":[],"avatar":"weavatar"},"modules":{"notice_control":false,"windfonts":false},"integrations":{"windfonts":{"fonts":[]}},"diagnostics":{"scheduled_checks":true},"data_residency":{"ruleset_version":1},"announcements":{"dismissed":[]},"apps":{"disabled":[]},"recovery_mode":false,"allow_site_override":true}}
```

`site option get wpcy_network_settings --format=json`：

```
{"schema_version":1,"connectivity":{"wordpress_org":"off","public_assets":[],"avatar":"weavatar"},"modules":{"notice_control":false,"windfonts":false},"integrations":{"windfonts":{"fonts":[]}},"diagnostics":{"scheduled_checks":true},"data_residency":{"ruleset_version":1},"announcements":{"dismissed":[]},"apps":{"disabled":[]},"recovery_mode":false,"allow_site_override":true}
```

`site option get wp_china_yes --format=json`：

```
{"store":"off","admincdn":"","cravatar":"weavatar","windfonts":"off","windfonts_list":[],"windfonts_typography":[],"adblock":"off","adblock_rule":[],"plane":"off","plane_rule":[],"monitor":true,"memory":true,"hide":false,"custom_name":"WP-China-Yes"}
```

`admincdn=""` → **`public_assets=[]`**。子站 `wpcy_settings` 同样 ABSENT。

### D1 损坏 option

```
studio wp --path ~/Studio/wpcy-40 eval 'update_option("wp_china_yes","corrupted-string"); echo gettype(get_option("wp_china_yes")).":".get_option("wp_china_yes");'
# string:corrupted-string
# 换成 4.0：
studio wp --path ~/Studio/wpcy-40 eval 'echo "eval_ok PHP=".PHP_VERSION;'
# eval_ok PHP=8.3.32   eval_exit=0
curl http://localhost:8891/   # 200，无 PHP Fatal
```

dry-run：`kept=[]` `ignored=[]`（Reader 非数组当空）。`wpcy_settings` 为 schema 默认。  
`option get wp_china_yes` 仍是 `"corrupted-string"`（4.0 未改写）。  
装回 3.9.3：`eval` 输出 `reinstall_ok`，键仍 `<string:16>`，哈希 `127aa4d9848cc70579b038641c386e60c597d48bf2bc9d7820bc4cab36b42e8f` 未变。

### D2 超大 option

在 `single-3.8-02` 上追加 `huge_blob` = 1MiB `X`：

```
studio wp --path ~/Studio/wpcy-40 eval 'echo "serialize_len=".strlen(maybe_serialize(get_option("wp_china_yes")))." json_len=".strlen(wp_json_encode(get_option("wp_china_yes")))." keys=".count(get_option("wp_china_yes"));'
# serialize_len=1049159 json_len=1048952 keys=16
studio wp --path ~/Studio/wpcy-40 wpcy migrate --dry-run --format=json
studio wp --path ~/Studio/wpcy-40 option get wpcy_settings --format=json
```

`wpcy migrate --dry-run --format=json`：

```
{"action":"dry-run","kept":["store","cravatar","windfonts","adblock"],"ignored":["admincdn","windfonts_typography","windfonts_list","adblock_rule","plane","plane_rule","monitor","memory","memory_display","custom_name","hide","huge_blob","admin"],"ignored_reasons":{"admincdn":"unsupported_whitelist","windfonts_typography":"feature_removed","windfonts_list":"empty","adblock_rule":"replaced_by_remote","plane":"feature_removed","plane_rule":"feature_removed","monitor":"feature_removed","memory":"feature_removed","memory_display":"feature_removed","custom_name":"feature_removed","hide":"feature_removed","huge_blob":"feature_removed","admin":"unsupported_whitelist"},"settings":{"schema_version":1,"connectivity":{"wordpress_org":"off","public_assets":[],"avatar":"cravatar_cn"},"modules":{"notice_control":false,"windfonts":true},"integrations":{"windfonts":{"fonts":[]}},"diagnostics":{"scheduled_checks":true},"data_residency":{"ruleset_version":1},"announcements":{"dismissed":[]},"apps":{"disabled":[]},"recovery_mode":false}}
```

`option get wpcy_settings --format=json`：

```
{"schema_version":1,"connectivity":{"wordpress_org":"off","public_assets":[],"avatar":"cravatar_cn"},"modules":{"notice_control":false,"windfonts":true},"integrations":{"windfonts":{"fonts":[]}},"diagnostics":{"scheduled_checks":true},"data_residency":{"ruleset_version":1},"announcements":{"dismissed":[]},"apps":{"disabled":[]},"recovery_mode":false}
```

ignored 含 `huge_blob`（`feature_removed`）与 token `admin`。`wp_china_yes` 哈希 `cee7ac6a55c599941c1b171597fb2023cd9c44fcbf1334970865f7d3dd2e423c`，键仍含 `huge_blob`（**未被截断改写**）。eval_exit=0。

### Rollback（审查 #7）

站：`wpcy-40`，样本 S2。`wp wpcy migrate --rollback --format=json`：

```
{"ok":true,"action":"rollback"}
```

同请求内 `Runner::rollback()` 后立刻读 option（避免下一轮 boot 的 `maybe_migrate_from_legacy` 再写入）：

```
{"had_settings_before":true,"ok":true,"settings_after_false":true,"backup_after_false":true,"legacy_before":"49b4f2acb853f8da560846b94e1ab419bd521d74859aa8d4fdd7be4fd2f6e0ed","legacy_after":"49b4f2acb853f8da560846b94e1ab419bd521d74859aa8d4fdd7be4fd2f6e0ed","legacy_unchanged":true}
```

`wp_china_yes` 哈希不变；`wpcy_settings` 与 `wpcy_migration_backup` 在 rollback 返回时已被删。随后任意新的 `studio wp` 会因 3.x option 仍在而再次 first-boot execute（`Plugin.php:101-122`），这是既定引导，不是 rollback 写回 3.x。

---

## §5 键迁移表 ↔ Mappers.php

| §5 键 | 决定 | 实现 | 状态 |
|-------|------|------|------|
| arkpress, motucloud, fewmail, bisheng, deerlogin, woocn, lelms, wapuu, yoodefender, docs, wordyeah, monitor, waimao | 直接丢弃 | `Mappers.php:185-188` default `feature_removed` | 已做 |
| disable_all_notices, notice_control, notice_method | 直接丢弃 | 同上；`notice_control` 有值时仍只开 `modules.notice_control`（既有 adblock 规则），空串 `empty` | 已做（未映射到其它能力） |
| hide_option / hide_menu / hide_menu_confirm 任一为真 → 白标菜单隐藏 | 合并到 3.x `hide` | **4.0 schema 无白标/藏菜单字段**（`AdminModule` 始终注册 `wpcy`）。三键 + `hide` 全部 `feature_removed`，不写 4.0 | 未做写入：无 4.0 目标；测试 `test_hide_keys_are_discarded_not_mapped` |
| hide | 3.x 真源 | 同上，丢弃 | 已做（丢弃） |
| hide_elements.hide_copyright | 直接丢弃 | `hide_elements` `feature_removed` | 已做 |
| enable_custom_rss 及 RSS 三键 | 直接丢弃 | default | 已做 |
| quick_select | 直接丢弃 | default | 已做 |
| enabled_sections 幽灵值 forums/forms/panel/domain/sms/chat/translate/ecosystem | 从数组丢掉 | 整个 `enabled_sections` `feature_removed`（4.0 无 section 闸） | 已做 |
| store=`proxy` | 映射 `wenpai` | `map_store`：`proxy` 与 `wenpai` → `auto`（`Mappers.php:267`） | 已做 |
| admincdn_files 含 `admin` 与 3.8 `admincdn` | 同一 4.0 值 | 两者 `public_assets` 均不含 `admin`；仅含 `admin` 时均为 `[]`（`Mappers.php:192-214,289+`） | 已做 |
| 3.8 `admincdn` 键存在（`[]`/`''`/数组） | 只由该键（并 3.9 三键）推导；空则 `[]`，不回落默认 | `array_key_exists('admincdn')` 即触发；S1/N1/N2/N3 → `[]`；S2 ignored 含 token `admin` | 已做（M4-02b） |
| 无 admincdn 且无 3.9 三键 | schema 默认五项 | `test_public_assets_default_when_admincdn_keys_absent` | 已做 |
| wp_memory_limit 等四项仅 performance 为真时生效 | 保留值并闸 | **4.0 已删内存常量**（M4-01）。四项 + `performance` 一律 `feature_removed`，performance=true 也不写入 | 未做写入：无 4.0 目标；测试 `test_memory_keys_discarded_even_when_performance_true` |
| comments_* / cravatar / windfonts* / adblock / store / bridge… 保留列 | 保留或按 §7.2 | store/cravatar/windfonts/windfonts_list/adblock/admincdn_* 仍 kept；其余删除功能 ignored | 已做（按 4.0 白名单，不恢复已删功能） |

---

## composer check / 前端

改了 PHP。M4-02b `composer check` exit **0**（phpcs + phpstan 73/73 OK + 全部 PHPUnit suites，其中 Migration `OK (39 tests, 593 assertions)`）。  
`npm run build` exit 0；`npm run lint:js` exit 0（未改 JS）。

未改 `.github/workflows/ci.yml`。push `grok/m4-02` 用既有 CI 验证；run id 见本任务最终报告。

---

## 没做 / 做不到 / 有疑问

1. **4.0 包 Version 字符串仍是 3.9.3**：M4-03 才改 `4.0.0-rc.1`。矩阵用 `Requires PHP: 8.0` + `Core\Plugin::boot()` 区分 LTS 3.9.3 包。
2. **3.9.3 GitHub Release 为 prerelease**（通道撤回）。ZIP 是 Release 附件，SHA 与 `.sha256` 附件一致。未用源码包、未用 4.0 树冒充 LTS。
3. **`hide_*` → 白标藏菜单、内存四项闸在 performance**：4.0 无对应字段；按「不把删除功能映射到其它 4.0 能力」丢弃。若产品要 4.0 藏菜单或写 `WP_MEMORY_LIMIT`，需另拍 schema，本任务不改产品映射表。
4. **Studio rollback 已补跑**（M4-02b）：CLI `{"ok":true}`；同请求内 `wpcy_settings` 已删、`wp_china_yes` 哈希不变。下一轮 `studio wp` 会因 first-boot 再 execute，属既定引导。
5. **Mac 本机无 `~/Studio/wpcy-40`、无 `~/.studio/bin/studio`**：全部在 wenpai VM 上跑。`studio` 别名 `ssh studio` 那台机无 Studio CLI。
6. **未改 CI yaml**；本机无 Docker，未跑 wp-env。
7. **N1 首次失败**：`plugin install --activate --network` 不是 WP-CLI 合法参数。改为 `install --force` + `activate --network` 后 N1–D2 跑完。
8. **Backup `legacy_hash` 与本报告 `wp_json_encode` SHA-256 不是同一算法**（Backup 用自己的 `Backup::hash`）。同行前后用同一 `wp_json_encode` SHA 比较，四阶段一致。
9. **任务书点名 S1/N2/N3 改断言；N1 同样有 `admincdn=""`**，一并改成 `[]`，否则 dataProvider 红。Studio 已复跑 S1/N1 dry-run。
10. **任务书混合 token 期望写 `['googlefonts','jsdelivr']`**：4.0 枚举是 `google_fonts` / `jsdelivr`。单元断言用枚举值。

---

## Definition of Done

- [x] 规格条目 ↔ 实现对照表：见上节 §5 表；无 4.0 目标的两行标「未做写入」。
- [x] 截图：非 UI，不适用。
- [x] 面向用户字符串：本任务未加用户可见文案；无「遥测/隐私/上报」。
- [x] 空/错误/降级：D1 损坏当空、D2 超大 ignored，均无 Fatal。
- [x] 测试：M4-02b `FixturesTest` 更新 S1/N2/N3 空 `admincdn`、S2 token `admin`、混合 token、hide/内存键集合、`single-3.9-07-store-proxy.json`；`composer check` 0；`npm run build` 0。CI run id 见最终报告。
- [x] `git diff --stat` 允许路径内；无 `.grok-context/`；无 `docs/dev-plan/README.md`；未改已有 fixtures JSON 数据（只新增 `single-3.9-07-store-proxy.json`）。
- [x] 没做 / 疑问：上一节。
