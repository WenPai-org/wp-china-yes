任务书范围已锁定：先读审查报告与相关文件，再按必修四项改。审查 §A/§G 已读完。接着读实现文件并装依赖。按 TDD：先写「Runner 抛异常」测试，再改生产代码。依赖安装与测试夹具并行。依赖还在装。先改测试夹具和失败用例，再动 `Plugin.php`。测试已按预期红：`RuntimeException` 未被捕获。接下来加 try/catch，并修 phpcs 的 `@package`。本地 `composer check` 已绿。按任务书前缀拆成三笔提交再 push。CI run `34013336604` 已入队。等它结束，再从 `wordpress` job 日志里取 `integration-windfonts.sh` 输出。`wordpress` job 在 `integration-windfonts.sh` 失败。先看日志再改脚本，不把断言抄回 smoke。根因是权益门：无 `windfonts=active` 时模块不注册。接着修脚本：写入 transient，并把 `crossorigin` 检查限定在文风标签上。CI 全绿。正在抽取 `wordpress` job 里 `integration-windfonts.sh` 的断言输出。M4-01b 四项必修已提交并推到 `grok/m4-01`。CI run **34013731394** 全绿，`wordpress` job 已跑通 `integration-windfonts.sh`。

## 1. 改了哪些文件（`git diff --stat 13969a3`）

```
 .eslintrc.js                             |  4 --
 .github/workflows/ci.yml                 |  1 +
 phpcs.xml.dist                           |  1 +
 src/Core/Plugin.php                      | 15 ++++++-
 tests/Integration/WindfontsSmokeTest.php | 71 --------------------------------
 tests/Unit/Config/OptionStore.php        |  8 ++++
 tests/Unit/Config/wp-option-stubs.php    |  6 +++
 tests/Unit/Core/PluginCreateTest.php     | 45 ++++++++++++++++++++
 tests/integration-windfonts.sh           | 36 ++++++++++++++--
 wp-china-yes.php                         |  2 +
 10 files changed, 110 insertions(+), 79 deletions(-)
```

`src/` 只动了 `src/Core/Plugin.php`。未 squash 既有提交，未 push `main`/`master`。

## 2. 验收命令与输出摘要

### `composer check`（本机 PHP 8.4.7，exit 0）

- phpcs：无 ERROR/WARNING 行（`phpcs.xml.dist` 已扫 `wp-china-yes.php`；原先缺 `@package`，已补）。
- phpstan：`[OK] No errors`（73/73）
- core suite：**23 tests, 67 assertions**（相对审查时的 22/58，多了迁移失败用例和两条 mapping 断言）
- 其余 suite 均 OK（smoke 1、config 40、connectivity 92、telemetry 3、privacy 11、diagnostics 13、cli 8、rest 24、integrations 20、migration 29、site-binding 12、apps 64、entitlements 11、admin 19）

TDD：`test_boot_survives_migration_throw_and_logs_warning` 在加 try/catch 前失败（`RuntimeException: forced migration failure` 逸出）；加上后 `OK (1 test, 7 assertions)`。

### 分支 CI

```
gh run list --branch grok/m4-01 --limit 3
```

```
completedsuccess  test: seed windfonts entitlement so wp_head CI can register
  CI  grok/m4-01  push  34013731394  4m13s
completedfailure  build: wire windfonts CI, phpcs bootstrap, drop deleted eslint ignores
  CI  grok/m4-01  push  34013336604  4m27s
completedsuccess  build: keep Windfonts smoke on integration-windfonts.sh
  CI  grok/m4-01  push  34011545805  4m5s
```

**绿 run：`34013731394`**（HEAD `b7876fe`）。jobs：quality / frontend / php 8.0–8.4 / plugin-check / e2e / **wordpress** / package 全部 ✓。

`wordpress` job `101433881215` 的 `integration-windfonts.sh` 断言输出：

```
kernel-4.0
settings-ok
<link rel="preconnect" href="https://cn.windfonts.com">
<link rel="stylesheet" type="text/css" href="https://app.windfonts.com/api/css?family=wenfeng-hcszt&#038;subset=full">
assert family=wenfeng-hcszt
assert subset=full
assert Windfonts tags have no crossorigin
integration-windfonts.sh ok
```

第一次接 CI 的 run `34013336604` 在该脚本红：`invalid Windfonts stylesheet output`。原因是 `EntitlementsModule` 把 `windfonts` 标成受限服务，无 `status=active` 缓存时 `enabled()` 为 false，CLI bootstrap 不注册 `wp_head`。随后在脚本里写入 `wpcy_entitlements` transient（`service=windfonts, status=active`），未改其它 `src/` 文件。

### 必修对照

| 项 | 结果 |
|----|------|
| CI `wordpress` job 在 `integration-recovery.sh` 之后跑 `bash tests/integration-windfonts.sh` | `.github/workflows/ci.yml` 已加；未把断言抄回 `wordpress-smoke.sh` |
| `maybe_migrate_from_legacy()` try/catch `\Throwable`，Logger warning 含类名与消息，不写 4.0 option | `src/Core/Plugin.php:121-133`；单测 `test_boot_survives_migration_throw_and_logs_warning` |
| `phpcs.xml.dist` 加 `<file>wp-china-yes.php</file>`，`composer lint` 修引出问题 | 1 个 ERROR：缺 `@package`；已在插件头补 `@package WenPai\ChinaYes` |
| `.eslintrc.js` 去掉已删目录 ignore | 去掉 `framework/` `client/` `assets/` `Service/` |

### 可选

- 首次启动用例已断言 `connectivity.wordpress_org === 'off'`、`connectivity.avatar === 'off'`。
- 删了 `tests/Integration/WindfontsSmokeTest.php`：`phpunit.xml.dist` 没有任何 suite 扫 `tests/Integration/`，`composer test:unit` 永不跑它；真 WP 路径由 `tests/integration-windfonts.sh`（现已进 CI）承担。`CliTest.php` / `RecoveryPageTest.php` 未动。

## 3. 没做 / 做不到 / 有疑问

- 审查建议 2（损坏 option smoke 再引导）和 3（清 cron + `wp_next_scheduled`）不在本任务必修，未改 `wordpress-smoke.sh`。
- 审查建议 8 要把三笔 `build:` squash 进 commit ②：任务书禁止 squash 已有提交，未做。
- `integration-windfonts.sh` 的 `crossorigin` 检查从「整个 `wp_head`」收窄到文风相关标签。WP 7.0.3 的 `wp_head` 含核心 `crossorigin`（script modules），整页扫描会误红。family/subset 仍对完整 `wp_head` 断言。
- 为让脚本在 wp-env 下真能注册模块，测试写入了 `wpcy_entitlements` active 行。无权益时 4.0 Windfonts 本来就不会出 link；这是既有门，不是本任务改的产品行为。
- 相对 `13969a3` 多了一笔 `test:`（`b7876fe`），因为第一次 CI 证明 job 已接线但脚本在权益门下不能过。

## 4. 提交哈希（`git log --oneline -5`）

```
b7876fe test: seed windfonts entitlement so wp_head CI can register
1a381b7 build: wire windfonts CI, phpcs bootstrap, drop deleted eslint ignores
9975e32 test: cover migration throw and lock store/cravatar mapping
0d81e47 fix(core): swallow first-boot migration throws
13969a3 build: keep Windfonts smoke on integration-windfonts.sh
```
