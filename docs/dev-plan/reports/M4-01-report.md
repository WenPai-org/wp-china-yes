先读任务依据和引导文件，再装依赖，按两个 commit 执行删除与门禁改动。任务书已钉死范围。接下来读影响分析与引导文件，同时装依赖。分支已在 `grok/m4-01`。继续读引导、composer、CI 和测试相关文件。依赖还在装。我先改源码和删 3.x 目录，装完再跑 `composer update`。依赖已装好。开始删 3.x 并改引导、composer、CI 和测试。Commit ① 已记下。接下来跑发布打包，并按验收命令检查 ZIP 内容。验收命令里的 `Plugin.php` 会命中 4.0 的 `src/Core/Plugin.php`。我会在打包脚本和 CI 里加上锚定到已删 3.x 路径的检查。`plugin-check` 和 `wordpress` 红了。先看失败日志再改。两处 CI 失败：Plugin Check 把任务要求的 `load_plugin_textdomain` 判成 WARNING；smoke 用 `wp_next_scheduled` 断言，分析原文要的是钩子还在。开始改这两处。Plugin Check 已绿。wordpress job 仍红，接着看新的失败日志。3.x 运行代码已从 `grok/m4-01` 物理删除，4.0 `Core\Plugin` 是唯一引导路径。CI run [34011545805](https://github.com/WenPai-org/wp-china-yes/actions/runs/34011545805) `conclusion: success`。

## 1. 改了哪些文件

相对开工时 `main`（`78fac71`）`git diff --stat 78fac71`：

```
 .github/workflows/ci.yml                       |   17 +-
 Plugin.php                                     |  135 --
 Service/…（25 个文件，整目录删除）
 assets/…（6 个文件，整目录删除）
 autoload-guard.php                             |   32 -
 client/…（4 个文件，整目录删除）
 composer.json                                  |   21 +-
 composer.lock                                  |   65 +-
 framework/…（整目录删除）
 helpers.php                                    |  137 --
 phpcs.xml.dist                                 |    7 +-
 phpstan.neon.dist                              |    3 -
 scripts/build-release.sh                       |    4 +
 scripts/sign-ruleset.php                       |    3 +
 src/Cli/StatusCommand.php                      |    8 +-
 src/Cli/wp-cli.php                             |    4 +-
 src/Core/Plugin.php                            |   84 +-
 templates/…（4 个文件，整目录删除）
 tests/Integration/WindfontsSmokeTest.php       |    6 +-
 tests/Unit/Cli/CliTest.php                     |    1 +
 tests/Unit/Core/KernelSwitchTest.php           |   89 +-
 tests/Unit/Core/PluginCreateTest.php           |   85 ++
 tests/bootstrap-unit.php                       |   20 +
 tests/e2e/global-setup.js                      |    6 +-
 tests/e2e/helpers.js                           |   19 +-
 tests/e2e/kernel-no-framework.spec.js          |    7 +-
 tests/integration-windfonts.sh                 |    9 +-
 tests/run-tests.sh                             |   15 -
 tests/test-*.php（8 个 3.x 独立测试删除）
 tests/wordpress-smoke.sh                       |   49 +-
 wp-china-yes.php                               |   72 +-
 141 files changed, 287 insertions(+), 18196 deletions(-)
```

`git diff --stat origin/main` **不含** `docs/dev-plan/README.md`。`origin/main` 在开工后多了 M4-02 文档（`docs/dev-plan/tasks/M4-02.md`、`docs/dev-plan/verification/3x-options-audit-2026-09-06.md`）；本分支未改这两份，也未 rebase（`git rebase` 被策略拒绝）。

未提交 `.grok-context/`。

## 2. 验收命令与输出摘要

### 1. 无 3.x `framework/` / `Service\` / `client/`

```
grep -rn "framework/\|Service\\\\\|client/" src/ wp-china-yes.php composer.json
```

有输出（4 行注释，4.0 对照 3.x `Service\Avatar` / `Service\Fonts`，不是类依赖）：

```
src/Connectivity/Avatar/AvatarModule.php:22: * Module id connectivity.avatar. weavatar rewrites to weavatar.com (3.x Service\Avatar).
src/Connectivity/Avatar/AvatarModule.php:29: * Copied from Service\Avatar::replace_avatar_url $sources.
src/Integrations/Windfonts/Stylesheet.php:3: * Windfonts CSS URL builder and head markup. Port of Service\Fonts.
src/Integrations/Windfonts/Stylesheet.php:30: * Preconnect host from Service\Fonts::load_windfonts.
```

任务书备用命令：

```
grep -rn 'framework/\|WenPai\\ChinaYes\\Service\\\|client/' src/ wp-china-yes.php composer.json
```

无输出，exit 1。`Services\`（带 s）保留。

### 2. `composer check`

退出码 0。已无 `test:legacy`。

| 步骤 | 摘要 |
|------|------|
| phpcs (`composer lint`) | 无输出 |
| phpstan | `[OK] No errors`（73/73） |
| smoke | OK (1 test, 3 assertions) |
| core | OK (22 tests, 58 assertions) |
| config | OK (40 tests, 184 assertions) |
| connectivity | OK (92 tests, 206 assertions) |
| telemetry | OK (3 tests, 265 assertions) |
| privacy | OK (11 tests, 64 assertions) |
| diagnostics | OK (13 tests, 123 assertions) |
| cli | OK (8 tests, 42 assertions) |
| rest | OK (24 tests, 136 assertions) |
| integrations | OK (20 tests, 46 assertions) |
| migration | OK (29 tests, 420 assertions) |
| site-binding | OK (12 tests, 127 assertions) |
| apps | OK (64 tests, 194 assertions) |
| entitlements | OK (11 tests, 77 assertions) |
| admin | OK (19 tests, 70 assertions) |

### 3. 发布包

`npm run build && bash scripts/build-release.sh` 退出码 0。

```
dist/wp-china-yes-3.9.3.zip
f06881508f7eafdfad8929f8285e8517efd3d1a5415b351214029f7ee761cd8e  wp-china-yes-3.9.3.zip
```

ZIP 603K。`unzip -Z1 dist/*.zip | grep -E 'framework/|Service/|client/|templates/|assets/|Plugin.php|helpers.php|autoload-guard.php|yahnis-elsts'` 命中 **一行**：`wp-china-yes/src/Core/Plugin.php`（4.0 内核，必须留）。3.x 根文件 / 目录 / PUC 不在包内。`wp-china-yes/build/index.js` 在。

Commit ② 把锚定检查写进 `scripts/build-release.sh` 与 CI `package` job（不误伤 `src/Core/Plugin.php`）。CI `package` 在 HEAD 上 success。

### 4. e2e

本机无 Docker。CI `e2e` job run 34011545805：

```
24 passed (1.5m)
```

### 5. Plugin Check

CI job 输出：

```
Success: Checks complete. No errors found.
Plugin Check: no findings
```

`--exclude-checks=offloading_files,trademarks,plugin_readme,file_type` 保留。已撤 `framework,Service,client,templates,assets` 与根部三文件排除。

### 6–7. 提交与 README

见第 4 节。`git diff --stat origin/main` 不含 `docs/dev-plan/README.md`。

`wordpress` job 末行：

```
WordPress 4.0 activation, compatibility-report, fonts, first-boot migration and no-framework smoke tests passed.
integration-cli.sh ok
integration-recovery.sh ok
```

## PHP 下限 8.0（四处）

| 位置 | 值 |
|------|-----|
| `wp-china-yes.php:13` | `Requires PHP: 8.0` |
| `composer.json:6` / `:33` | `"php": ">=8.0"` / `"platform": { "php": "8.0.0" }` |
| `.github/workflows/ci.yml:13` | `php: ['8.0', '8.1', '8.2', '8.3', '8.4']`（无 7.4） |
| `phpcs.xml.dist:38` | `testVersion` `8.0-` |

Version 字符串仍是 `3.9.3`（M4-03 才钉 rc.1）。

## 首次启动「尚未迁移」判定

`src/Core/Plugin.php` `maybe_migrate_from_legacy()`（约 100–120 行）：

- 单站：`get_option( 'wpcy_settings', false ) === false` **且** `LegacyReader::exists()`（`wp_china_yes` 在库里，含空数组）→ `Runner::execute()`，写 `wpcy_settings` + `wpcy_migration_backup`。
- 多站点：同样用 `wpcy_network_settings` / `get_site_option('wp_china_yes')`。
- 4.0 option 已存在（`false !== $stored`）：不覆盖、不重跑。
- 损坏非数组 `wp_china_yes`：`LegacyReader::read()` 当 `[]`，不 Fatal，仍不写回旧键。
- `activate()` 仍是 no-op；不注册 uninstall。

## 3. 没做 / 做不到 / 有疑问

1. **验收 grep 1 不是空**：命中 4.0 注释里的 `Service\Avatar` / `Service\Fonts`。按任务书改用精确命令，两条都贴了。
2. **验收 unzip 的 `Plugin.php` 无锚**：命中 `src/Core/Plugin.php`。3.x 根 `Plugin.php` 已不在包内。Commit ② 用锚定正则。
3. **任务书要两个 commit，实际四个**：`16c8160` `refactor:` + `664cfff` `build:` 是规定的两份。后面两个 `build:`（`8f589ae`、`13969a3`）是 CI 迭代：Plugin Check 对任务要求的 `load_plugin_textdomain` 报 WARNING；smoke 里复制的 Windfonts `wp_head` 在 CLI 上失败，改回分析 §4「走 `integration-windfonts.sh`」。未 force-push、未把两份规定 commit 揉成一份。
4. **`scripts/sign-ruleset.php` 垫了 `ABSPATH`**：删 `autoload-guard.php` 后 CLI 签名脚本 `require` 到 `Ruleset.php` 会静默 `exit`（`SignRulesetScriptTest` 红）。任务书未列该文件，不加则门禁假绿。
5. **未把 Windfonts 断言放进 `wordpress` job**：分析 §4 指定现有 `tests/integration-windfonts.sh`。该 job 仍只跑 smoke + `integration-cli.sh` + `integration-recovery.sh`（与改前一致）。
6. **未改 Version / 未改 `docs/dev-plan/README.md` / 未 push `main`**。
7. **`origin/main` 已到 `b69726a`（M4-02 文档）**：本分支基于开工时的 `78fac71`。rebase 被拒。合 PR 时会看到那两份文档在本分支上「缺失」，不是本任务删的。

## 4. 提交哈希

```
13969a3 build: keep Windfonts smoke on integration-windfonts.sh
8f589ae build: keep textdomain load and assert telemetry hook
664cfff build: reject deleted 3.x paths in the release zip
16c8160 refactor: remove 3.x kernel and boot 4.0 only
78fac71 docs(dev-plan): set 4.0 PHP floor to 8.0 in M4-01 (decision 2026-09-06)
```

CI：https://github.com/WenPai-org/wp-china-yes/actions/runs/34011545805 （HEAD `13969a3`，全绿）。
