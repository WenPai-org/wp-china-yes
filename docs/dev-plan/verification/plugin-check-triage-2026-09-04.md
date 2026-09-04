先读任务上下文和仓库现状，再改 nonce 流程与 Plugin Check 处置。继续读任务上下文、相关源码和 CI 配置。先装依赖，并查 Plugin Check 的准确 check slug；同时核对 Studio / wp-env 能不能跑。依赖已装好。接着查 Plugin Check 的准确 slug，并核对 nonce 失败原因。本机没有 Docker。改在 wp-env 缓存和 Studio 里找 Plugin Check 的准确 slug。任务要求不能猜 slug。先从本地 Studio / wp-cli 缓存里找 Plugin Check 源码。从 Plugin Check 源码里取准确 slug，不靠猜。slug 已从本机 Plugin Check 的 `Default_Check_Repository` 核对。开始改脚本、CI 排除项和代码。PHPCS 把内联 `prepare()` 当成未转义 SQL。改回中间变量并加上 ignore。`composer check` 和 `npm run build` 已过。本机没有 Docker，试 Studio 跑 recovery 脚本。CI 红项已按任务书改完：recovery nonce 同进程、目录政策类 Plugin Check 排除、质量/安全类修代码、插件头与 readme 统一。工作树相对 `51f2747` 共 10 个文件、+123/−64。

## 1. 改了哪些文件

`git diff --stat 51f2747`：

```
 .github/workflows/ci.yml                     |  7 ++++
 readme.txt                                   |  4 +-
 scripts/build-release.sh                     |  3 +-
 src/Apps/DataStore.php                       | 12 +++---
 src/Cli/wp-cli.php                           | 12 +++---
 src/Services/SiteBinding/ChallengeClient.php |  2 +-
 src/Telemetry/Report.php                     | 56 ++++++++++++++++++----------
 tests/Unit/Telemetry/wp-telemetry-stubs.php  | 27 ++++++++++++++
 tests/integration-recovery.sh                | 31 +++++++++------
 wp-china-yes.php                             | 33 ++++++++--------
 10 files changed, 123 insertions(+), 64 deletions(-)
```

未改 `RecoveryPage.php`、`Service/`、`client/`、`framework/`、`src/Admin/app/`。未 push。

## 2. 验收命令与输出摘要

### `composer check`

退出码 **0**。摘要：

- phpcs：无输出（通过）
- phpstan：`[OK] No errors`（73/73）
- PHPUnit 各 suite 全绿（smoke 1、core 20、config 40、connectivity 92、telemetry 3、privacy 11、diagnostics 13、cli 8、rest 24、integrations 18、migration 28、site-binding 9、apps 53、entitlements 9、admin 17）
- legacy `tests/run-tests.sh`：`All PHP syntax and standalone tests passed.`（形态一/镜像/兼容性报告等均为 PASS）

### `npm run build`

退出码 **0**。`webpack 5.110.3 compiled with 1 warning`（既有 vendor 体积告警 `907.js` 1.86 MiB），产物含 `index.js` / `style-index.css`。

### `bash tests/integration-recovery.sh`

**本机未跑通。** 原因：无 Docker / colima，`npx wp-env start` 起不来；`/Users/feibisi-studio/Studio/wpcy` 里的 `wp-china-yes` 是旧 3.x 树，不是本 worktree，未拿它当 4.0 恢复页测。

脚本里的等价 `wp eval`（同一进程）：

```php
wp_set_current_user( 1 );
if ( ! current_user_can( "manage_options" ) ) {
	$users = get_users( array( "role" => "administrator", "number" => 1 ) );
	if ( empty( $users ) ) { throw new Exception( "no administrator" ); }
	wp_set_current_user( $users[0]->ID );
}
$_SERVER["REQUEST_METHOD"] = "POST";
$_POST = array(
	"wpcy_recovery_action" => "disable_rewrites",
	"wpcy_recovery_nonce"  => wp_create_nonce( "wpcy_recovery_disable_rewrites" ),
);
$_REQUEST = $_POST;
add_filter( "wp_redirect", "__return_false" );
$page->handle_post();
```

随后 `wp option get wpcy_settings --format=json` 断言 `recovery_mode === true`。未改 `RecoveryPage.php`。

### 范围

diff 只在交付物内（脚本 / CI / 发布打包 / 4.0 源码 / 插件头 / readme / 对应单元 stub）。

## 3. 没做、做不到、有疑问

- **`wp plugin list-checks` 未在 wp-env 里跑**：本机无 Docker。slug 来自本机已装 Plugin Check 的 `Default_Check_Repository::register_default_checks()`（`list-checks` 读的同一张表）：`offloading_files`、`trademarks`、`plugin_readme`、`file_type`。不是猜的。
- **`wp core version` 未在 wp-env 里跑**：`.wp-env.json` 钉的是 `WordPress/WordPress#7.0.3`。Plugin Check 发现原文是 `Tested up to: 7.0 < 7.1`。头改成 **7.1**（跟发现里的当前稳定版，不是 7.0.3 取整后的 7.0）。若 CI 上 Plugin Check 拉到的稳定版不是 7.1，这条还会红。
- **`Domain Path`**：仓内没有 `languages/`；4.0 `src/` 没有 `load_plugin_textdomain`；3.x 用 `Service/TranslationManager.php` 加载 `languages/*.mo`（禁区未改）。选择从头里删掉 `Domain Path: /languages`，而不是往发布包塞空目录。
- **`file_type` 整类排除**：`hidden_files`（`src/.gitkeep`）已从 `build-release.sh` 排除；`missing_composer_json_file` 是发布包故意不含 `composer.json`。两者同属 `file_type`，无法只排除其中一个 code。其余 `file_type` 条目（压缩包/phar 等）一并排除。
- **`plugin_readme` 整类排除**：含 `outdated_tested_upto_header`、`readme_mismatched_header_requires_php`、中文 readme 语言。`Tested up to` / `Requires PHP` 仍按插件头类改过，不依赖该 check。

## 4. 提交哈希

`git log --oneline -5`：

```
2b8bf5d fix(plugin-check): prefix globals, prepare SQL, unslash, headers
7aac80b ci: exclude WordPress.org directory-policy Plugin Check slugs
22fc411 ci: generate recovery nonce in the same wp eval as handle_post
51f2747 build: revert use-memo-one root dependency (ERESOLVE vs React 19 on CI); resolve it via webpack alias to @wordpress/compose's copy; regenerate lock
53503d1 ci: drop .npmrc (broke peer install), Plugin Check scans the built release package via wp-env override, tolerant JSON decode in integration-cli.sh
```

---

## 23 条发现处置表

| file:line | code | 处置 |
|---|---|---|
| `src/.gitkeep:0` | `hidden_files` | 排除-`file_type`（.org 打包）；并在 `scripts/build-release.sh` 排除 `.gitkeep` |
| `readme.txt:0` | `outdated_tested_upto_header` | 排除-`plugin_readme`；头部仍改为 `Tested up to: 7.1` |
| `readme.txt:0` | `readme_mismatched_header_requires_php` | 排除-`plugin_readme`；`Requires PHP` 统一为 `7.4.0` |
| `readme.txt:0` | `readme_short_description_non_official_language` | 排除-`plugin_readme`（readme 中文是产品决定） |
| `readme.txt:0` | `readme_description_non_official_language` | 排除-`plugin_readme` |
| `readme.txt:0` | `trademarked_term` | 排除-`trademarks`（名称含 wp，不经 .org 分发） |
| `src/Diagnostics/Checker.php:194` | `OffloadedContent` | 排除-`offloading_files`（国内 CDN 改写是产品功能） |
| `src/Diagnostics/Checker.php:195` | `OffloadedContent` | 排除-`offloading_files` |
| `src/Diagnostics/Checker.php:196` | `OffloadedContent` | 排除-`offloading_files` |
| `src/Connectivity/PublicAssets/AssetMap.php:38,41,44–50,53` | `OffloadedContent` ×10 | 排除-`offloading_files` |
| `src/Migration/Mappers.php:38` | `OffloadedContent` | 排除-`offloading_files` |
| `composer.json:0` | `missing_composer_json_file` | 排除-`file_type`（发布包按设计不含 composer.json） |
| `wp-china-yes.php:0` | `plugin_header_nonexistent_domain_path` | 头部修正：删除 `Domain Path`（无 `languages/`，4.0 不加载 .mo） |
| `wp-china-yes.php:0` | `trademarked_term` ×2 | 排除-`trademarks` |
| `wp-china-yes.php:28,32` | `PrefixAllGlobals.NonPrefixedVariableFound` | 修复-`$settings` → `$wpcy_settings`（`2b8bf5d`） |
| `wp-china-yes.php:38` | `DiscouragedFunctions`（`ini_set`） | 修复-保留调用，phpcs:ignore 说明 bootstrap 时尚无 WP 替代（`2b8bf5d`） |
| `src/Telemetry/Report.php:102` | `MissingUnslash` | 修复-`wp_unslash( $_SERVER['SERVER_SOFTWARE'] )`（`2b8bf5d`） |
| `src/Telemetry/Report.php:333,488,514` | `DirectQuery` / `NoCaching` | 修复-已 `prepare` 的直查加 phpcs:ignore，说明兼容性报告一次性聚合不缓存（`2b8bf5d`） |
| `src/Telemetry/Report.php:679` | `DirectDB.UnescapedDBParameter` | 修复-表名 `esc_sql` 后再插值（`2b8bf5d`） |
| `src/Services/SiteBinding/ChallengeClient.php:94` | `DynamicHooknameFound` | 修复-`apply_filters( 'wpcy_services_api', $base )` 静态名；常量 `FILTER_API` 仍为同一字符串（`2b8bf5d`） |
| `src/Cli/wp-cli.php:32,33` | `PrefixAllGlobals.NonPrefixedVariableFound` | 修复-`$wpcy_config` / `$wpcy_checker`（`2b8bf5d`） |
| `src/Apps/DataStore.php:160,317` | `DirectDB.UnescapedDBParameter` | 修复-`esc_sql( table_name() )` + prepare；get_col/get_var 行 phpcs:ignore（`2b8bf5d`） |

CI `plugin-check` 命令现为：

`--exclude-checks=offloading_files,trademarks,plugin_readme,file_type`，并保留原 `--exclude-directories/--exclude-files`。
