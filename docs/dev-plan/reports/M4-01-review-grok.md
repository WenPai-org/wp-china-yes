先按独立审查流程读规范与 diff，不改任何文件。对照 `78fac71` 收集 diff 与任务书，并登记当前审查状态。任务是 M4-01。接下来对照任务书、规范与关键改动文件逐项核。接着读引导、迁移接线与测试改动，核对任务书交付物。发现 Windfonts 断言可能已退出 CI。接着核对照脚本引用并跑 `composer check`。任务是 **M4-01**（相对 `78fac71`，分支 `grok/m4-01`）。3.x 内核已删、4.0 成为唯一引导；`composer check` 在本机 PHP 8.4.7 上 exit 0。阻断项：Windfonts 的真实 `wp_head` 断言从 CI `wordpress` job 拿走后，替代脚本未接回。

范围：`git diff 78fac71 HEAD` → 141 files, +287 / −18197。四笔提交：`16c8160` `refactor:`，随后三笔 `build:`（`664cfff` `8f589ae` `13969a3`）。任务书要求两笔。`docs/dev-plan/README.md` 不在 diff 内。

---

## A 正确性

**结论：引导、删旧、PHP 8.0 四处、首次启动接线都在；分析 §4 的 Windfonts `wp_head` 从 CI 可跑的 smoke 挪走后，替代脚本不在 CI。**

交付物对照：

| 任务书项 | 证据 |
|----------|------|
| 物理删除 `framework/` `Service/` `client/` `templates/` `assets/`、根 `Plugin.php` `helpers.php` `autoload-guard.php`、`tests/test-*.php` `tests/run-tests.sh` | `ls` 上述路径均为 No such file |
| `wp-china-yes.php` 去 `WPCY_KERNEL`、去 27–51 性能常量、autoload 后 `Core\Plugin::boot()`、Version 仍 3.9.3 | `wp-china-yes.php:7` `Version: 3.9.3`；`wp-china-yes.php:24-27` `require` + `Plugin::boot()` + `return` |
| composer：`psr-4` 只 `src/`、无 `files`、无 PUC、无 `test:legacy`、`php >=8.0` / `platform 8.0.0` | `composer.json:5-6,18-21,32-33,60-64`；`composer run-script test:legacy` → `Script "test:legacy" is not defined` |
| `Plugin::boot()`：textdomain、CLI `require`、`maybe_migrate_from_legacy()` | `src/Core/Plugin.php:80-83,100-121,128-139,147-156` |
| `StatusCommand` 不再默认 `legacy` | `src/Cli/StatusCommand.php:96` `$kernel = 'v4'` |
| `tests/bootstrap-unit.php` 垫 `ABSPATH` / `CHINA_YES_*` | `tests/bootstrap-unit.php:8-26` |
| PHP 下限 8.0 四处 | 插件头 `wp-china-yes.php:13`；`composer.json:6,33`；CI `.github/workflows/ci.yml:13` `['8.0'…'8.4']`；`phpcs.xml.dist:38` `testVersion` `8.0-` |
| 不 bump `4.0.0-rc.1`、不改 README | `CHINA_YES_VERSION` 仍 `3.9.3`；`git diff --stat 78fac71 HEAD -- docs/dev-plan/README.md` 空 |

验收 1（任务书备选精确式）无输出：

```text
grep -rn 'framework/\|WenPai\\ChinaYes\\Service\\\|client/' src/ wp-china-yes.php composer.json
# （空）
```

宽式会命中注释里的 `Service\Avatar` / `Service\Fonts`（`src/Connectivity/Avatar/AvatarModule.php:22`、`src/Integrations/Windfonts/Stylesheet.php:3`），不是 3.x 类依赖。

**漏项（阻断）：** 旧 `tests/wordpress-smoke.sh:33-34` 在 CI `wordpress` job 里断言 `wp_head` 含 `family=wenfeng-hcszt` / `subset=full`、无 `crossorigin`。现文件把这段改成注释指向 `tests/integration-windfonts.sh`（`tests/wordpress-smoke.sh:22`），而 `.github/workflows/ci.yml:208-210` 仍只跑：

```text
bash tests/wordpress-smoke.sh
bash tests/integration-cli.sh
bash tests/integration-recovery.sh
```

`78fac71` 的同一 job 同样没有 `integration-windfonts.sh`。断言从 CI 路径拿走后，替代脚本没有接回来。单元 `StylesheetTest::test_module_print_matches_smoke_assertions`（`tests/Unit/Integrations/Windfonts/StylesheetTest.php:185-212`）直接调 `print_stylesheets()`，不经过 `Plugin::boot()` → `do_action('wp_head')`。

首次启动判定与任务书一致：`wpcy_settings` / `wpcy_network_settings` 的 `get_*option(..., false) === false`，且 `LegacyReader::exists()`（`src/Core/Plugin.php:105-120`）。`activate()` 仍空操作（`src/Core/Plugin.php:231-233`）；无 `register_uninstall_hook`（`src/Core/Plugin.php:247-248,254-260`）。

本审查未跑验收 3（ZIP）、4（e2e）、5（Plugin Check），不能当作已满足。

---

## B 运行时风险

**结论：引导路径在桩环境下能完成 `boot()`；首次启动无 try/catch，损坏 option 的真实 WP 再引导未在 smoke 里验证。本任务 PHP 下限是 8.0，不是 7.4。**

- `KernelSwitchTest` 子进程加载 `wp-china-yes.php`，exit 0，`Core\Plugin` 存在、无 3.x `Plugin`、无 `/framework/`（`tests/Unit/Core/KernelSwitchTest.php:35-47,226-237`）。子进程里 `get_option` 恒返回 default（`tests/Unit/Core/KernelSwitchTest.php:84-87`），`maybe_migrate` 不会真跑。
- `maybe_migrate_from_legacy()` 直接 `( new Runner() )->execute()`（`src/Core/Plugin.php:120`）。`execute()` 抛错会在每次请求 Fatal，模块注册进不去。当前 `LegacyReader::read()` 非数组变 `[]`（`src/Migration/LegacyReader.php:31-36`），Mapper 对空数组走 Defaults，未见必炸点。
- 损坏 option 的 smoke（`tests/wordpress-smoke.sh:10-12`）先 `activate`（boot 已完成），再 `wp option update … corrupted-string`，只测 `LegacyReader::read()`。注释写明 `boot already completed`。单元 `test_first_boot_treats_damaged_legacy_as_empty`（`tests/Unit/Core/PluginCreateTest.php:119-127`）会调 `maybe_migrate`，覆盖的是桩而不是 WP 再引导。
- 多站点：`is_multisite()` 时读/写 `wpcy_network_settings` + `get_site_option('wp_china_yes')`（`src/Core/Plugin.php:105-109`；`tests/Unit/Core/PluginCreateTest.php:132-145`）。与 3.x「多站点用 site_option」一致。单站改多站后仍留在 blog option 的 `wp_china_yes`，3.x 同样读不到，不是本 diff 新引入的。
- 审查清单里的「仓仍声明 7.4」与本 diff 不符。任务书写明不得保留 7.4。四处已是 8.0。本机 `composer check` 跑在 **PHP 8.4.7**，未在 8.0 上复跑。新代码无 `match` / union 属性 / `readonly` / named arguments。`readme.txt:6` 仍 `Requires PHP: 7.4.0`（M4-03 范围；WP 安装拦截看插件头，头已是 8.0）。

---

## C 规范

**结论：`src/` 仍 PSR-4 + `strict_types`；禁区未往 `framework/` 加代码、无新的用户可见「遥测」文案、无新的直写第三方商业链。`wp-china-yes.php` 不在 phpcs 扫描集。**

- `src/Core/Plugin.php:8` `declare(strict_types=1);`；类文件名与 `WenPai\ChinaYes\Core\Plugin` 一致。
- `phpcs.xml.dist:5-6` 只扫 `src/`、`tests/Unit/`，**不扫** `wp-china-yes.php`、`tests/bootstrap-unit.php`、`tests/Integration/`。引导文件的 WPCS 不进 `composer lint`。
- `wp-china-yes.php` 无 `strict_types`（该文件历来不是类文件）。`wp-china-yes.php:30-35` 后台 notice 为未转义、未 i18n 的静态英文 HTML。
- 禁区「业务不读 `wp_china_yes`」：4.0 模块仍不读；仅 `Plugin::maybe_migrate_from_legacy` → `LegacyReader`（任务书要求，对应 `docs/4.0-rewrite-plan.md` §7.1）。
- `framework/` 已删，无 Adapter。`.eslintrc.js:9-13` 仍 ignore 已删的 `framework/` `client/` `assets/` `Service/`。
- 用户文案：diff 无「遥测」「匿名数据」「隐私开关」。内部钩子名 `wpcy_daily_telemetry` 仍在（`tests/wordpress-smoke.sh:17`），不是界面文案。
- 商业链接：本 diff 未新增 `https://wpcy.com/go/` 以外的营销域。

---

## D 安全

**结论：本 diff 无新的 REST/表单写路径；首次启动写 option 不接受请求体；未把 3.x uninstall 搬来删 `wp_china_yes`。**

- 无新 capability/nonce 面。`maybe_migrate` 在任意请求（含未登录前台）上 `Runner::execute()` → `update_option` / `update_site_option`。输入是库里已有的 `wp_china_yes`，不是 `$_POST`。升级后第一次前台命中会写库，与任务书「第一次启动」一致。
- `grep -n 'register_uninstall_hook\|delete_option.*wp_china_yes' src/Core/Plugin.php wp-china-yes.php` 无输出。回装 3.9.x 的旧 option 仍在。
- `activate()` 不写 `wp_china_yes`（`src/Core/Plugin.php:231-233`；`tests/Unit/Core/PluginCreateTest.php:69-75` 用正则断言源码无 `update_option('wp_china_yes'`）。
- 凭据/远程请求：本 diff 未改 `CredentialStore`、未改 `wp_remote_*`。`TelemetryModule::ENDPOINT`（`src/Telemetry/TelemetryModule.php:39`）是既有代码。
- `load_plugin_textdomain` 带 `phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound`（`src/Core/Plugin.php:138-139`）。`wp plugin check` 是否认这行，本审查未跑 Plugin Check，不能签字验收 5。

---

## E 测试质量

**结论：`composer check` 全过；首次启动测试不锁映射结果；损坏 option / Windfonts 的真实 WP 路径偏弱。**

本机（PHP 8.4.7，`composer check`，exit 0）：

| 步 | 结果 |
|----|------|
| phpcs | `composer lint -- --report=summary` 无 ERROR/WARNING 行 |
| phpstan | `[OK] No errors`（73/73） |
| smoke | 1 test, 3 assertions |
| core | 22 tests, 58 assertions |
| config 40 / connectivity 92 / telemetry 3 / privacy 11 / diagnostics 13 / cli 8 / rest 24 / integrations 20 / migration 29 / site-binding 12 / apps 64 / entitlements 11 / admin 19 | 均 OK |

假通过 / 未覆盖：

1. **首次启动不断言映射值。** `test_first_boot_migrates_when_settings_absent`（`tests/Unit/Core/PluginCreateTest.php:80-91`）只断言 `wpcy_settings` / backup 键存在，以及 3.x `store` 仍为 `'off'`。`update_option('wpcy_settings', [])` 也能过。`Runner::execute()` 的映射由既有 `FixturesTest` 覆盖，Plugin 层没锁 `connectivity.wordpress_org === 'off'`。smoke 同样只查键存在、旧 option 未被改写（`tests/wordpress-smoke.sh:25-26`）。
2. **损坏 option smoke 不重新 boot**（见 B）。`class_exists` 在已加载进程里恒真。
3. **兼容性报告 smoke 变弱。** 分析 §4 要求写 4.0 option 后清 cron、钩子仍在。现实现是 `has_action`（`tests/wordpress-smoke.sh:16-17`），不清 cron、不查 `wp_next_scheduled`。`TelemetryModule::register()` 每次 boot 都会 `add_action`（`src/Telemetry/TelemetryModule.php:110-116`），该断言几乎不会红。
4. **`tests/Integration/WindfontsSmokeTest.php` 不在 `phpunit.xml.dist` 任何 testsuite**（integrations 扫的是 `tests/Unit/Integrations`）。`composer test:unit` 永不跑它。去掉 `WPCY_KERNEL` skip 对门禁无影响。
5. **`test_module_print_matches_smoke_assertions` 以 `$this->assertTrue( true )` 收尾**（`tests/Unit/Integrations/Windfonts/StylesheetTest.php:211`，既有）。真正检查是前面的 `fail()`。

e2e：`requireV4Kernel` 已换成 `requireCoreKernel`（`tests/e2e/helpers.js:77-87`；`tests/e2e/global-setup.js:8-9`）。CI e2e 已去掉 `wp config set WPCY_KERNEL v4`（`.github/workflows/ci.yml:231-234`）。本审查未跑 Playwright。

---

## F 与 spec / 任务书的偏差

**结论：行为规格（§7.1 首次启动、不写回旧 option、PHP 8.0）对得上；过程与几条分析表有偏差。**

| 条 | spec / 任务书 | 实际 |
|----|----------------|------|
| 两个 commit，禁止揉成一个；验收 6「可见两个 commit」 | `docs/dev-plan/tasks/M4-01.md:19,107-108` | 四笔：`16c8160` + 三笔 `build:`。前缀合法，数量不符。后三笔是 ZIP 拒绝 / textdomain / Windfonts 脚本，本可进 commit ② |
| 分析 §4 Windfonts 改走 `integration-windfonts.sh` | `docs/dev-plan/verification/m4-delete-impact-2026-09-05.md:177` | 脚本在，CI `wordpress` job 不跑它 |
| 分析 §4 兼容性报告：清 cron 后再断言 | 同上 §4 行 14–19 | 只 `has_action`，不清 cron |
| `files` 空数组或删键 | 任务书 composer 段 | 键已删，允许 |
| 不改 Version 3.9.3 | 任务书 | 遵守 |
| 不改 `docs/dev-plan/README.md` | 任务书禁区 | 遵守 |
| `load_plugin_textdomain(…, '/languages')` | 任务书 + 分析 §5 | `src/Core/Plugin.php:133-139` 有调用；仓根无 `languages/`（分析已记录，pot 归 M4-03） |
| `readme.txt` Requires PHP | 任务书 PHP 四处不含 readme | `readme.txt:6` 仍 `7.4.0`，与插件头 8.0 不一致 |
| 审查模板「PHP 7.4 兼容」 | 用户清单 | 与本任务书冲突；执行者按 8.0 改是对的 |
| config-schema 五键 / 未知键丢弃 | `docs/specs/config-schema.md` | 本 diff 不改 Schema；迁移走既有 `Runner`+`Validator` |

---

## G 清单

### 阻断

1. **CI 不再验证 Windfonts `wp_head`（family / subset / 无 crossorigin）**  
   - 文件：`.github/workflows/ci.yml:208-210`；`tests/wordpress-smoke.sh:22`；`tests/integration-windfonts.sh:38-47`  
   - 原因：3.x smoke 该断言在 CI `wordpress` job 内；现只留注释，替代脚本不在 job 里。  
   - 修法：在 `wordpress` job 的 `integration-recovery.sh` 之后加一行 `bash tests/integration-windfonts.sh`。不要把同一断言再抄回 `wordpress-smoke.sh`。

### 建议

1. **首次启动测试锁映射。** `tests/Unit/Core/PluginCreateTest.php:80-91` 与 `tests/wordpress-smoke.sh:26` 增加 `connectivity.wordpress_org === 'off'`、`connectivity.avatar === 'off'`（对应写入的 `store`/`cravatar`）。  
2. **损坏 option smoke 要再引导。** `tests/wordpress-smoke.sh:10-12`：写入损坏值后用新的 `wp eval`（新进程）再 `Plugin::boot()` / `class_exists`，不要在已 boot 的进程里只读 `LegacyReader`。  
3. **兼容性报告 smoke 回到「清 cron + `wp_next_scheduled`」。** 对照分析 §4 与旧 `wordpress-smoke.sh` 第 14–19 行语义；`has_action` 不够。  
4. **`maybe_migrate_from_legacy` 包一层失败处理。** `src/Core/Plugin.php:120`：`execute()` 失败记 Logger warning，不要让升级站整站 Fatal。  
5. **phpcs 扫引导文件。** `phpcs.xml.dist` 增加 `<file>wp-china-yes.php</file>`。  
6. **`tests/Integration/WindfontsSmokeTest.php` 要么纳入会跑 WP 的 suite，要么删掉，避免「去掉 skip 即已覆盖」的错觉。**  
7. **`.eslintrc.js:9-13` 去掉已删目录的 ignore。**  
8. 验收 3/4/5（ZIP、e2e、Plugin Check）本审查未跑；合并前按任务书补命令输出。四笔 commit 与「可见两个」不符，若统筹卡字面量，把三笔 `build:` squash 进 commit ②。

### 确认无误

- 3.x 目录与根文件、PUC、`test:legacy`、`WPCY_KERNEL` 开关、3.x `new Plugin()` / uninstall 已不在运行路径。  
- 首次启动条件：4.0 option 缺省（`false`）且 `wp_china_yes` 存在 → `Runner::execute()`；已有 4.0 option 不重跑；不写回 `wp_china_yes`。  
- PHP 8.0 四处与任务书一致（插件头 / composer require+platform / CI 矩阵 / phpcs `testVersion`）。  
- `composer check` 本机 exit 0（phpcs 静默、phpstan OK、上列 PHPUnit suite 全绿）。  
- `git diff 78fac71 HEAD -- docs/dev-plan/README.md` 为空。
