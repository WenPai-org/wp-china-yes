状态：草案（M0）· 2026-09-03 · 依据 linuxjoy 定稿 §7

# 开发文档入口

本文档集给 4.0 的人与 AI 代理用。3.9.x 安全修复仍以仓内现有测试与 CI 为准，不按本文脚手架执行。

## 阅读顺序

1. [CONTRIBUTING.md](CONTRIBUTING.md) — 分支、提交、PR、评审、门禁
2. [coding-standards.md](coding-standards.md) — PHP / JS / CSS 风格、禁区、本地检查
3. [module-authoring.md](module-authoring.md) — 如何写一个 Module
4. [testing.md](testing.md) — 分层测试、矩阵、发布阻断项
5. [security.md](security.md) — capability / nonce / 凭据 / 沙箱 / 日志
6. [release.md](release.md) — semver、发版、分发、回滚

代理协议：[agents.md](agents.md)，仓根入口 [`AGENTS.md`](../../AGENTS.md)。

本地一键检查：`composer check && npm run lint:js && npm run build`

产品合同不在本目录：先读 [`docs/4.0-rewrite-plan.md`](../4.0-rewrite-plan.md)，再读 `docs/architecture/adr-00*.md` 与相关 `docs/specs/*`。

## 4.0 与 3.9.x

两条产品线，不是一个产品的两个版本（定稿 §7.1b）。

| 线 | 用途 | 维护位置 |
|----|------|----------|
| **4.0** | 运营与变现的新品；开发主线 | `main`（禁止直接推，见 CONTRIBUTING） |
| **3.9.x** | 旧品 LTS，**只修安全** | 待定（M0）：`codex/wpcy-3.9.3-stabilization` 或 `release/3.9` |

3.9.3 发版本身是 P1，不在本目录任务范围内。4.0 迁移器只搬保留设置，不承诺 3.x 功能面还在。

## 源码结构速览

4.0 目标结构见 [`docs/4.0-rewrite-plan.md`](../4.0-rewrite-plan.md) §5.1（`src/` 内核、`WenPai\ChinaYes\` PSR-4）。当前 3.x 仍是仓根 `Service/`、`client/`、`framework/`、`Plugin.php`；4.0 **不**往 `framework/` 加代码，也不把 3.x 文件名风格追溯进 `src/`。

现况可对照：

- `composer.json`：PSR-4 `WenPai\ChinaYes\` 目前映射仓根 `./`（4.0 改为 `src/`，待 M1）
- `.github/workflows/ci.yml`：PHP 7.4–8.4 矩阵、`tests/run-tests.sh`、发布包、wp-env smoke
- `tests/test-*.php`：独立 PHP 测试（3.9.x 保留；4.0 迁 PHPUnit，见 testing.md）
- `scripts/build-release.sh`：打 ZIP，排除 `.git` / tests / docs / scripts

M1 才落脚手架配置文件（`phpcs.xml.dist` 等）；本目录只给建议片段，不创建那些文件。
