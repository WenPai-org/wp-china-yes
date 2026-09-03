状态：草案（M0）· 2026-09-03 · 依据 linuxjoy 定稿 §7

# 测试

## 分层

### 单元（PHPUnit，`tests/Unit`）

不加载 WordPress。用 **Brain\Monkey** 或最小 stub——**待定（M1）**：二选一在脚手架任务里定，本文件不拍板。

覆盖：schema 校验、URL 白名单替换、镜像故障回源、API 错误映射、迁移映射、`enabled()` 条件。

现状对照：`tests/test-*.php` 是独立 PHP 文件，自建 stub（见 `tests/test-telemetry.php`）。这是 3.9.x 写法，不是 4.0 目标。

### 集成（`@wordpress/env` + WP 测试套件，`tests/Integration`）

加载真实 WP。覆盖：激活、设置保存、Multisite、REST 权限、WP-CLI、模块故障隔离。

现状对照：`package.json` 已有 `@wordpress/env` 11.12.0；`tests/wordpress-smoke.sh` 用 `npx wp-env run cli wp eval` 做烟测。4.0 把这类断言迁进 `tests/Integration`，smoke 可保留为最快门。

### 后台端到端（Playwright，`tests/e2e`）

覆盖：

- 四页（概览 / 连接优化 / 文派服务 / 诊断）——定稿 §7.5a-D 整站 React
- 恢复页 `?page=wpcy-recovery`（无 JS 可关 URL 改写与模块）
- 小工具容器：iframe sandbox + `postMessage` origin 校验（对文派域 mock）

待 M1 引入 Playwright 与 `@wordpress/scripts` 应用后再写具体 spec。

### 合同测试（服务端 mock）

fixtures 在 `tests/fixtures/`。至少：

- manifest 索引（Apps）
- 权益查询（Entitlements）
- 云桥 ingest 健康端点

云桥入库接口未就位前，DataResidency 合同只断言「不改 URL」。CDN sandbox fixtures 属 4.x（方案 §6），4.0 首发不挡。

### 发布包检查

沿用并扩展现有 `scripts/build-release.sh` + CI `Verify release contents`：

- ZIP **不含** `.git`、`tests/`、`docs/`、源码映射、`composer.json`/`lock`、`package.json`
- ZIP **含** 生产 Composer 依赖（`--no-dev`）与 4.0 编译后的 `src/Admin/app` 产物
- SHA-256 对应被测 tag 的 HEAD

## 兼容矩阵

| PHP \ WP | 6.5 | 上一主版本 | 当前稳定版 | 多站点 |
|----------|-----|------------|------------|--------|
| 8.0 | 待 PHP 下限确认后 | 同左 | 同左 | 单独一列 job |
| 8.1 | 要 | 要 | 要 | 要 |
| 8.2 | 要 | 要 | 要 | 要 |
| 8.3 | 要 | 要 | 要 | 要 |
| 8.4 | 要 | 要 | 要 | 要 |

- WordPress 下限 **6.5 已定**（定稿 §7.1-6）
- PHP 8.0 是否进矩阵：**待定**（等 3.9.3 报告 30 天版本盘；底线 8.0，目标 8.1）
- 现状 CI 是 PHP 7.4–8.4 × 单 WP smoke（3.9.x）。4.0 矩阵替换 7.4，多站点单独一列——待 M1 改 workflow（本任务不改 CI）

## 必须阻止发布的失败

照 [`docs/4.0-rewrite-plan.md`](../4.0-rewrite-plan.md) §9.2：

- 任意 Fatal、Warning、Notice、Deprecated
- 镜像失败时无法回原始上游
- 未连接 CDN 账号导致免费功能失效（4.0 首发无托管 CDN；此条对「未绑定 / 无权益」同样成立：基线层必须仍可用）
- 凭据出现在日志、导出或兼容性报告
- 迁移丢失保留设置，或删除旧 option `wp_china_yes`
- 管理员以外用户可修改设置、连接服务或刷新缓存
- 远程 API 失败后仍显示成功
- ZIP 与测试 HEAD 不一致

## 现有 `tests/test-*.php` 的处置

| 线 | 处置 |
|----|------|
| 3.9.x | **保留**独立测试与 `tests/run-tests.sh` |
| 4.0 | 迁到 PHPUnit（`tests/Unit` / `tests/Integration`） |
| 过渡期 | 两者并存，**统一入口仍是 `tests/run-tests.sh`**（先 `php -l`，再跑 `test-*.php`，M1 后再调 PHPUnit） |

不要在 3.9.x 安全修复里改测试框架。

## 本地如何跑（标注脚手架依赖）

```bash
# 现状即可（3.9.x / 过渡期）
composer validate --strict
composer install --no-interaction --prefer-dist
bash tests/run-tests.sh

# wp-env smoke（需 Node 22 + Docker，见 ci.yml wordpress job）
npm ci
npx wp-env start
bash tests/wordpress-smoke.sh
npx wp-env stop

# 发布包
bash scripts/build-release.sh
unzip -tq dist/wp-china-yes-*.zip
```

以下待 M1 脚手架落地：

```bash
composer lint
composer analyse
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --testsuite Integration   # 或 wp-env 内跑
npm run lint
npm run test:e2e          # Playwright
composer audit
npm audit
```
