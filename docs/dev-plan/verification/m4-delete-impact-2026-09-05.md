# M4 删旧影响分析

日期：2026-09-05（命令于 2026-09-06 在 `grok/m4-prep`、基于 `main` `96678ef` 核实）
范围：物理删除 3.x 运行代码后，4.0 是否仍能引导、迁移、测试、打包、发版。
不改代码。PHP 下限不做决定（占位 8.0，开工前由统筹填入）。

先读：`docs/dev-plan/README.md` §2 M4 表、`docs/4.0-rewrite-plan.md` §7 / §10 M4、定稿 §7.1-6 / §7.6、`docs/release/4.0-runbook.md`。

---

## 1. 待删清单

命令：

```bash
for p in framework Service client templates assets Plugin.php helpers.php autoload-guard.php; do
  du -sh "$p"; find "$p" -type f 2>/dev/null | wc -l
done
du -sh tests/test-*.php tests/run-tests.sh tests/wordpress-smoke.sh wp-china-yes.php
ls -d languages 2>/dev/null || echo "NO languages/ directory"
grep -n "assets/" src/Admin/app -r || true
grep -n "wpcy-logo\|qr-banner\|website-banner" src/Admin/app || true
```

实测（`du -sh` + `find -type f`）：

| 路径 | `du -sh` | 文件数 | 处置 |
|------|----------|--------|------|
| `framework/` | 860K | 69（php 60） | 删。Codestar 改名版；含 `framework/languages/zh_CN.{po,mo}` 32K（CSF 文本域，不是插件 `wp-china-yes`） |
| `Service/` | 244K | 25 | 删。含 `Update.php`（唯一 `PucFactory` 调用点） |
| `client/` | 36K | 4 | 删。4.0 报告已在 `src/Telemetry/Report.php` |
| `templates/` | 24K | 4 | 删。3.x 欢迎/关于/维护页 |
| `assets/` | 148K | 6 | 删。见下「图片是否搬」 |
| `Plugin.php` | 8.0K（4190 字节，135 行） | 1 | 删。3.x `new Plugin()` |
| `helpers.php` | 8.0K（5036 字节，137 行） | 1 | 删。`get_settings()` + `field_cdn_base()` 只服务 3.x |
| `autoload-guard.php` | 4.0K（760 字节，32 行） | 1 | 删。只为 CLI 加载 `helpers.php` 垫 `ABSPATH` |
| `tests/test-*.php` | 8 个文件，约 43K 合计（含 `run-tests.sh` / smoke） | 8 | 删。`composer test:legacy` 入口 |
| `tests/run-tests.sh` | 4.0K（371 字节） | 1 | 删 |
| `tests/wordpress-smoke.sh` | 8.0K（4989 字节，49 行） | 1 | **改**，不是整文件删。3.x 断言清单见 §4 |
| `wp-china-yes.php` | 4.0K（3510 字节，85 行） | 1 | **改**：去掉 3.x 引导段与 `WPCY_KERNEL`，直接 `Core\Plugin::boot()` |

三项合计待删目录约 **1.4M**（`du -ch framework Service client templates assets Plugin.php helpers.php autoload-guard.php` → `1.4M total`）。

### `src/Admin/app` 是否用 `assets/` 图片

```
grep -rn 'assets/' src/          → (none)
grep -rn 'wpcy-logo\|qr-banner\|website-banner' src/Admin/app → (none)
```

根部 `assets/images/` 三张图（`wpcy-logo.png` 27K、`qr-banner.jpg` 48K、`website-banner.jpg` 48K）只被 3.x `Service/Setting.php`、`framework/classes/admin-options.class.php`、`templates/welcome-section.php` 引用。**不必搬到 `src/Admin/app/assets/`。** 4.0 后台吃 `build/` 编译产物。

### `wp-china-yes.php` 3.x 引导段（行号）

```
27–51  读 wp_china_yes 写 WP_MEMORY_LIMIT / 修订/自动保存常量（定稿 §7.1-8 已删的 3.x 性能常量）
55–58  WPCY_KERNEL=v4 → Core\Plugin::boot(); return;
60–70  require framework/setup + TranslationManager（3.x 路径）
79–85  register_activation_hook / register_uninstall_hook(3.x Plugin) + new Plugin()
```

注意：27–51 在内核开关**之前**，当前 v4 路径也会执行这组已删功能。M4-01 必须整段拿掉，不得留。

`languages/` 仓根不存在（`NO languages/ directory`）。

---

## 2. `src/` 对 3.x 的引用

指定命令：

```bash
grep -rn "WenPai\\\\ChinaYes\\\\Service\|helpers.php\|get_settings()\|WP_CHINA_YES\|framework/" src/
```

完整输出（12 行，退出码 0）：

```
src/Core/Plugin.php:30:use WenPai\ChinaYes\Services\Entitlements\EntitlementsModule;
src/Core/Plugin.php:31:use WenPai\ChinaYes\Services\SiteBinding\SiteBindingModule;
src/Apps/CachedEntitlements.php:15:use WenPai\ChinaYes\Services\Entitlements\EntitlementsModule;
src/Services/SiteBinding/CredentialStore.php:11:namespace WenPai\ChinaYes\Services\SiteBinding;
src/Services/SiteBinding/ChallengeClient.php:11:namespace WenPai\ChinaYes\Services\SiteBinding;
src/Services/SiteBinding/SiteBindingModule.php:11:namespace WenPai\ChinaYes\Services\SiteBinding;
src/Services/Entitlements/Degrade.php:11:namespace WenPai\ChinaYes\Services\Entitlements;
src/Services/Entitlements/EntitlementsModule.php:11:namespace WenPai\ChinaYes\Services\Entitlements;
src/Services/Entitlements/Client.php:11:namespace WenPai\ChinaYes\Services\Entitlements;
src/Services/Entitlements/Client.php:14:use WenPai\ChinaYes\Services\SiteBinding\ChallengeClient;
src/Rest/EntitlementsController.php:15:use WenPai\ChinaYes\Services\Entitlements\EntitlementsModule;
src/Rest/BindingController.php:15:use WenPai\ChinaYes\Services\SiteBinding\SiteBindingModule;
```

判断：**全部是 4.0 `WenPai\ChinaYes\Services\`（带 s）命名空间，不是 3.x `Service\`。** 模式里的 `Service` 无词界，假阳性。可忽略。

补核（精确）：

```
grep -rn 'WenPai\\ChinaYes\\Service\\' src/  → 空
grep -rn 'helpers.php' src/                  → 空
grep -rn 'get_settings' src/                 → 空
grep -rn 'WP_CHINA_YES' src/                 → 空
grep -rn 'framework/' src/                   → 空
grep -rn 'client/' src/                      → 空
grep -rn 'class-site-health\|wenpai-bridge-client' src/ → 空
```

| 核点 | 结论 |
|------|------|
| `src/Migration/LegacyReader.php` | 只 `get_option` / `get_site_option('wp_china_yes')`，不 `use` 3.x 类。损坏非数组 → `[]`。 |
| `src/Telemetry/Report.php` | 不 `require client/`。文件头注释写「Ported from 3.x Site Health」，可忽略。 |
| `\WenPai\ChinaYes\get_settings` | `src/` 无调用者。调用点全在 `Service/*.php`、`client/wenpai-bridge-client.php`、`tests/test-*.php`、`tests/wordpress-smoke.sh`。删 `helpers.php` 对 4.0 运行时无影响。 |
| `autoload-guard.php` 删后 `composer.json` `files` | 见 §3。PHPStan 已有 `tests/phpstan-bootstrap.php` 定义 `ABSPATH` / `CHINA_YES_*`。PHPUnit `tests/bootstrap-unit.php` **只** `require vendor/autoload.php`，今天靠 guard 垫 `ABSPATH`；删 guard 后 `src/**` 的 `if ( ! defined( 'ABSPATH' ) ) { exit; }` 会在 `class_exists(Core\Plugin)`（`tests/Unit/SmokeTest.php`）处直接 `exit`。M4-01 必须把常量垫进 `tests/bootstrap-unit.php`。 |

`src/` 里大量「3.x」字样是注释 / `legacy_hash` 字段名 / Windfonts 行为对照，**不是类依赖**。`src/Cli/StatusCommand.php:96` `$kernel = 'legacy'` 在常量删除后会变成默认值——M4-01 必须改，否则 `wp wpcy status` 在唯一 4.0 路径上仍报 `legacy`。

---

## 3. `composer.json`

现状 `autoload`：

```json
"psr-4": { "WenPai\\ChinaYes\\": [ "src/", "./" ] },
"files": [ "autoload-guard.php", "helpers.php", "src/Cli/wp-cli.php" ]
```

`require`：

```json
"php": ">=7.4",
"yahnis-elsts/plugin-update-checker": "^5.2"
```

`config.platform.php` = `7.4.33`。`scripts.check` = lint + analyse + test:unit + **test:legacy**。

### 删旧后

- `psr-4` 只留 `"src/"`，去掉 `"./"`。
- `files`：任务书要求**空**。当前第三项 `src/Cli/wp-cli.php` 是 **4.0** CLI 注册（`wp wpcy status|doctor|config|migrate`），不是 3.x。若直接清空 `files` 且不改加载点，WP-CLI 命令消失。M4-01 必须在 `Core\Plugin::boot()` 的 CLI 场景 `require` 该文件（或等价注册），然后 `files` 才能为空。
- `require.php` / `platform.php` / `phpcs.xml.dist` `testVersion`：下限占位 **8.0**，开工前由统筹填入（定稿 §7.1-6）。本分析不改这些值。

### `yahnis-elsts/plugin-update-checker`

```
grep -rn 'PucFactory\|plugin-update-checker' src/ wp-china-yes.php  → (none)
唯一调用：Service/Update.php:7,17  PucFactory::buildUpdateChecker('https://api.wenpai.net/china-yes/version-check', …)
composer.lock：v5.5
du -sh vendor/yahnis-elsts/plugin-update-checker → 660K（114 文件）
```

4.0 更新检查走云桥（`docs/4.0-rewrite-plan.md`、定稿 §7.1-7、`docs/dev/release.md`、`docs/release/4.0-runbook.md` §6）。`src/` 无人用 PUC。**列入删除**（`composer.json` `require` 与 lock）。

发布包体积：见 §7。当前 ZIP 内 PUC 压缩后 **约 369K 未压缩 / 计入 472K 待删压缩载荷的一部分**。本机 `php -l` 打包时 PUC 在 PHP 8.4.7 上打出 4 条 `Deprecated`（隐式 nullable）。删掉后打包 `php -l` 不再扫到该库。

---

## 4. 测试门禁替换

### `composer test:legacy` / `check`

```
"test:legacy": "bash tests/run-tests.sh",
"check": ["@lint", "@analyse", "@test:unit", "@test:legacy"]
```

M4-01：删 `test:legacy` 脚本与 8 个 `tests/test-*.php`；`check` 改为 lint + analyse + test:unit。`phpcs.xml.dist` 的 `exclude-pattern`（`framework/` `client/` `Service/` `templates/` `tests/test-*.php`）与 `phpstan.neon.dist` 的 `excludePaths`（`framework` `client` `Service`）目录将不存在，一并撤。

### `tests/wordpress-smoke.sh` 3.x 断言 → 4.0

| 行 | 现状 3.x 断言 | 4.0 替换 |
|----|---------------|----------|
| 8 | `CHINA_YES_VERSION === "3.9.3"` | M4-01 只断言常量已定义且引导的是 `Core\Plugin`。版本钉 `4.0.0-rc.1` 归 **M4-03** |
| 10–12 | 损坏 option 下 `\WenPai\ChinaYes\get_settings()` 仍为 array | 损坏 `wp_china_yes` 不 Fatal；`LegacyReader::read()` 返回 `[]`；`Core\Plugin::boot()` 可完成 |
| 14–19 | `wpcy_daily_telemetry` 在 `bridge=false` 仍调度 | 保留语义：`TelemetryModule` 常开。改写 option 为 4.0 `wpcy_settings`（或不写 3.x 键）后再清 cron、断言钩子仍在 |
| 21–24 | `Service\Maintenance` 方法存在 + `template_redirect` | **删除该断言**（维护模式已删）。改为 `class_exists(Maintenance)` 为假 |
| 26–29 | `memory` → `admin_footer_text` | **删除**（Memory 已删） |
| 31–34 | 写 `wp_china_yes` windfonts 后 `wp_head` 含 family/subset、无 crossorigin | 改走 4.0 option / 已有 `tests/integration-windfonts.sh`（去掉 `WPCY_KERNEL=v4` 前置） |
| 36–40 | 语言分离 / WebP 不损坏 option | **删除**（定稿 §7.1-8） |
| 42–43 | `class_exists("WP_CHINA_YES_Setup")` | **取反**：类不存在；`get_included_files()` 不含 `/framework/` |
| 45–46 | 注释：v4 不能用 `wp eval` 定义常量 | 删注释；不再需要 `WPCY_KERNEL` |

`wordpress` job 现状（`.github/workflows/ci.yml:191-211`）：`needs: php`，跑 smoke + `integration-cli.sh` + `integration-recovery.sh`，**不**设 `WPCY_KERNEL`，因此今天测的是 3.x 路径。M4-01 后该 job 自然走 4.0。`integration-cli.sh` 已是 4.0 CLI；`StatusCommand` 的 `kernel` 字段见 §2。

`e2e` job 现状：`wp config set WPCY_KERNEL v4`；`tests/e2e/helpers.js` `requireV4Kernel()` 在常量未定义时**整套拒绝**。M4-01 必须改 helpers / `global-setup.js` / `kernel-no-framework.spec.js` / CI 该步，否则 e2e 必红。

`php` job 现状：矩阵 `7.4, 8.0, 8.1, 8.2, 8.3, 8.4`，步骤只有 `bash tests/run-tests.sh`。删 legacy 后该 job 无测试。M4-01：改为 `composer test:unit`（或与 `quality` 合并，由执行时对照当时 workflow，不得发明不存在的 job 名）。**7.4 行是否删除：下限由统筹在 M4-01 开工时填入，占位 8.0。** 未填入前不得自行砍 7.4。

### Plugin Check 排除项撤销

现状（`ci.yml` 注释写明 M4-01 撤）：

```
--exclude-directories=tests,docs,scripts,node_modules,vendor,dist,.github,framework,Service,client,templates,assets
--exclude-files=helpers.php,Plugin.php,autoload-guard.php
```

M4-01 从 directories 去掉 `framework,Service,client,templates,assets`，从 files 去掉三份根文件。`.org` 目录政策四类 `--exclude-checks=offloading_files,trademarks,plugin_readme,file_type` **保留**（产品仓不走 .org，理由见 `docs/dev-plan/verification/plugin-check-triage-2026-09-04.md`）。验收：零 ERROR/WARNING。

---

## 5. 插件头与元数据

`wp-china-yes.php` 头与 `readme.txt` 现状：

| 字段 | 现值 | 4.0 目标 | 时机 |
|------|------|----------|------|
| `Version` / `CHINA_YES_VERSION` / `package.json` `version` / `Stable tag` | 3.9.3 | **4.0.0-rc.1** | **M4-03**（`scripts/sync-version.sh`） |
| `Requires at least` | 4.9 | **6.5**（定稿 §7.1-6 已定） | M4-03 与头同步；`phpcs.xml.dist` 已是 `minimum_supported_wp_version=6.5` |
| `Requires PHP` | 7.4.0 | **待定**，占位 8.0 | 统筹填入后写头 / `composer.json` / phpcs `testVersion` / CI 矩阵 |
| `Tested up to` | 7.1 | 发版当天核稳定版 | M4-03 / M4-04 |

`readme.txt` 仍写 3.9.3 稳定性与「设置 → WPCY.COM」。`readme.md` 功能列表含飞行模式、赞助商区，与定稿冲突，4.0 段由 **M4-03** 重写（移除功能说明、迁移说明、最低版本）。`CHANGELOG.md`「未发布」为空，最新段是 `v3.9.3`。`changelog.txt` 停在 3.8.1，M4-03 决定是否切段或停更，本分析不拍。

### `languages/` 与 `load_plugin_textdomain`

```
grep -rn load_plugin_textdomain src/ → (none)
Plugin.php:70 与 Service/TranslationManager.php:33 是仅有的两处加载
src/ 内 text domain `wp-china-yes` 约 180 处 __() / esc_html__()（PHP + JS）
仓根无 languages/
docs/dev/coding-standards.md：仓内只放 languages/*.pot，不提交手工 .po/.mo
```

3.x `TranslationManager` 删后，4.0 **没有** `load_plugin_textdomain`。WP 6.5+ 对 slug=text domain 的插件可 JIT 加载社区翻译，但自有 `languages/*.mo` 仍要显式加载。M4-01 在 `Core\Plugin::boot()`（或 Admin 模块 `register()`）补 `load_plugin_textdomain( 'wp-china-yes', false, dirname( plugin_basename( CHINA_YES_PLUGIN_FILE ) ) . '/languages' )`。M4-03 用 `wp i18n make-pot` 生成 `languages/wp-china-yes.pot`。禁止把 `framework/languages/zh_CN.*` 当 4.0 真源。

---

## 6. 迁移器与 `wp_china_yes`

| 项 | 核实 |
|----|------|
| 3.x option 删代码后仍在库里 | 是。`LegacyReader::OPTION = 'wp_china_yes'`；`Runner` 明确不写、不删旧键。4.0 业务读 `wpcy_settings` / `wpcy_network_settings`（`Schema`）。 |
| `wpcy_migration_backup` | `Schema::MIGRATION_BACKUP`；`Backup::write/read/delete` 已实现。执行迁移时写入 `from_version` / `migrated_at` / `legacy_hash` / `ignored_fields`。 |
| 映射 | `Mappers` 按 §7.2 + M2-01b（含 `admincdn_public`）。六份 `tests/fixtures/legacy-options/*.json` 仍在。 |
| **首次启动路径** | **不完整。** `docs/4.0-rewrite-plan.md` §7.1：「4.0 第一次启动只读 `wp_china_yes`，生成新 schema」。`Plugin::create()` **未**注册 Migration 模块；`Core\Plugin::activate()` 是 no-op；只有 CLI `wp wpcy migrate`。升级用户若只启用插件、不跑 CLI，会吃 `Defaults`（镜像 `auto`、公共库全开等），**3.x 里关掉的开关会丢**。M4-01 必须接线：4.0 option 尚不存在且 `wp_china_yes` 存在 → `Runner::execute()`；已有 4.0 option 不重跑。这是方案 §7.1 的实现，不是新产品决定。 |
| 回滚到 3.9.x | 成立，前提是 **不**把 3.x `Plugin::uninstall()`（`delete_option('wp_china_yes')`）搬进 4.0。当前 v4 在 `return` 之前，根本没注册 3.x uninstall。M4-01 不得新增删除 `wp_china_yes` 的 uninstall。用户：停用 4.0 → 装回 3.9.x ZIP → 启用，3.9.x 只读原 option；4.0 键留库无害（`docs/release/4.0-runbook.md` §7）。`Runner::rollback()` 只删 4.0 option + backup，不修 3.x 结构。 |
| 损坏 option | `LegacyReader` 非数组 → `[]`。`wp-china-yes.php` 27–31 的 3.x 守卫随引导段删除后，由 Reader / Repository 承担。 |
| 超大 option | fixtures 最大 `single-3.9.3-03.json` 5.1K / 59 键。超大样本由 M4-02 人工构造，本仓无现成文件。 |

---

## 7. 发布包对比

命令：`npm ci && npm run build && bash scripts/build-release.sh`（本机 PHP 8.4.7，Node v23.11.0）。退出码 0。

```
dist/wp-china-yes-3.9.3.zip          1.1M  (1167536 bytes)
dist/wp-china-yes-3.9.3.zip.sha256
1979a072a0f84a6d61c3e3a7291d7a3d041ab7ef8e5f7cef91b22badee47a25b  wp-china-yes-3.9.3.zip
entries: 428
files (not dirs): 327
uncompressed (unzip -l): 4246694 bytes
```

含 `wp-china-yes/build/index.js`。ZIP 内 `composer.json` 只出现在 PUC 子树（发布脚本已删仓根 composer.json）。

`unzip -Z1 | grep -v` 估算删 `framework/` `Service/` `client/` `templates/` `assets/` `Plugin.php` `helpers.php` `autoload-guard.php` `vendor/yahnis-elsts/` 之后：

| | 文件数 | 未压缩 | zip 内压缩载荷 |
|--|--------|--------|----------------|
| 现在 | 327 | 4,246,694 | 1,066,882 |
| 待删 | 225 | 1,494,916 | 483,101 |
| 估留 | 102 | 2,751,778 | **583,781（约 570 KiB）** |

待删细分（ZIP 内未压缩）：framework 720,821 / Service 206,116 / client 25,651 / templates 13,055 / assets 140,975 / 三份根 PHP 9,986 / PUC 378,312。

留下的大头是 `build/`（2,217,296 未压缩，已 minify，压缩收益小）。估删旧后 ZIP **约 0.6–0.7M**（570K 载荷 + zip 目录开销），比现在 1.1M 少约 **0.47M 压缩**。`dist/` 已被 `.gitignore`，本分析不提交 ZIP。

---

## 8. 风险清单

| # | 风险（一句话） | 缓解 |
|---|----------------|------|
| 1 | 删 `autoload-guard.php` 后 PHPUnit 未定义 `ABSPATH`，`src/` 守卫 `exit` | `tests/bootstrap-unit.php` 定义 `ABSPATH` 与 `CHINA_YES_*`（PHPStan 已有 `tests/phpstan-bootstrap.php`，不必改 bootstrap 路径） |
| 2 | `tests/Unit/*` 引用 `CHINA_YES_VERSION` | `tests/phpstan-bootstrap.php` 已是 `4.0.0-dev`；`tests/Unit/Telemetry/wp-telemetry-stubs.php` 为 `3.9.3-test`（测试桩，不是 3.x 类）。M4-03 改版本时顺手或保留测试专用字符串 |
| 3 | `KernelSwitchTest` 断言 `new Plugin()` 与 `WPCY_KERNEL` | M4-01 重写：引导只 `Core\Plugin::boot()`，源码无 `WPCY_KERNEL`、无 3.x `new Plugin()` |
| 4 | e2e `requireV4Kernel()` 在常量消失后整套失败 | M4-01 改 `tests/e2e/helpers.js` / `global-setup.js` / `kernel-no-framework.spec.js` / CI `wp config set WPCY_KERNEL` |
| 5 | `StatusCommand` 默认 `kernel=legacy` | M4-01 改为恒为 4.0 内核标识（沿用现有 JSON 键，不发明字段） |
| 6 | 首次启动不跑 `Runner::execute()`，升级站吃 Defaults | M4-01 接线，见 §6；M4-02 矩阵验收 |
| 7 | 误把 3.x `uninstall` 搬来会删 `wp_china_yes`，装回 3.9.x 设置没了 | M4-01 禁区：不注册删除旧 option 的 uninstall |
| 8 | `wp-china-yes.php` 27–51 在 v4 下仍写已删常量 | M4-01 删除该段 |
| 9 | `composer files` 清空导致 CLI 不注册 | `Core\Plugin::boot()` CLI 场景加载 `src/Cli/wp-cli.php` |
| 10 | `php` job 只跑 `run-tests.sh`，删后空转 | 改为 `composer test:unit`；7.4 矩阵占位 8.0、统筹填入 |
| 11 | Plugin Check 仍 exclude 已删路径 | 撤销 §4 所列旧目录/文件排除 |
| 12 | PUC 在 PHP 8.4 `php -l` 报 Deprecated，且 4.0 不用 | 从 `require` 删除 |
| 13 | 无 `load_plugin_textdomain`，自有翻译不加载 | M4-01 补加载；M4-03 出 pot |
| 14 | M4-02 装回需要 **3.9.3 发行 ZIP** | 3.9.3 尚未放行则 M4-02 阻塞，不得用 `main` 源码树冒充 LTS 包 |
| 15 | README M4 表与本分析/任务书表述不完全同（M4-01 未列 `assets/` `helpers.php` `autoload-guard.php`；M4-04「plat-api 停止返回 3.x 以外版本」） | **不改 README**（任务禁区）。语义上 plat-api 只服务 3.9.x，与「保持 3.9.3」一致；删旧清单以本分析 + M4-01 任务书为准 |

---

## 与 README 总表

`docs/dev-plan/README.md` §2 M4 四行仍无任务书链接（本批补 `tasks/M4-01.md`…`M4-04.md`）。总表正文未改。
