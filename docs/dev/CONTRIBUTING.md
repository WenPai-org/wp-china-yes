状态：草案（M0）· 2026-09-03 · 依据 linuxjoy 定稿 §7

# 贡献指南

## 分支模型

| 分支 | 用途 |
|------|------|
| `main` | 4.0 开发主线。**禁止直接推。** 一切经 PR。 |
| `release/3.9` | 3.9.x LTS（只修安全）。名称待定（M0），候选还有 `codex/wpcy-3.9.3-stabilization`。 |
| `feat/<topic>` | 功能 |
| `fix/<topic>` | 缺陷 |
| `grok/<topic>` / `codex/<topic>` | AI 代理任务分支（每任务一个 worktree） |

当前远端默认分支仍是 `master`（3.x 历史）。是否改名为 `main`、何时切，待定（M0）。在切之前，代理仍按任务书指定的基线提交工作，**不要自己把默认分支改掉**。

## 提交信息

[Conventional Commits](https://www.conventionalcommits.org/)：

```text
<type>(<scope>): <摘要>

<正文：写为什么，不写过程流水>
```

- `type`：`feat` | `fix` | `docs` | `refactor` | `test` | `chore` | `build` | `ci` | `perf`
- `scope` 可选，如 `telemetry`、`apps`、`admin`、`connectivity`、`dev`
- 中英文均可；摘要一行说清改了什么，正文写为什么
- 代理任务提交：任务书要求的前缀优先（例如 `docs(dev): …`）

## Pull Request

描述必须包含：

1. **改了什么**（路径级，不要只写“优化”）
2. **为什么**（对应的 spec / ADR / 定稿条目）
3. **怎么验证**：验收命令 + **输出摘要**（贴在 PR 或附件；无输出支撑视为未完成）
4. 关联的 ADR / `docs/specs/*` 路径
5. CI 全绿

**产品范围决定不在 PR 里做。** 改动只要涉及「收不收数据、默认开不开、删不删功能」，必须先对照 linuxjoy 定稿 §7.1；与表不符就停，回去改任务书或定稿，不要在 PR 里自行判断。Codex 8 月方案的产品判断不再单独作为决策来源（定稿 §7.5）。

## 评审

- 至少一人 review 才能合
- AI 代理产出的 PR 由人审：linuxjoy / feibisi
- 评审清单：
  - 正确性（对照任务书与 spec，不对照“感觉更合理”）
  - 安全清单（[security.md](security.md)）
  - 测试（本 PR 能跑的层都跑了，输出在 PR 里）
  - 文档同步（行为变了必须改 `docs/specs/*` 与 CHANGELOG「未发布」段）
  - i18n（用户可见字符串走 `wp-china-yes` text domain）

## 门禁

合入与发版前（脚本名待 M1 脚手架落地，见 [coding-standards.md](coding-standards.md) / [testing.md](testing.md)）：

| 门 | 现状 / 目标 |
|----|-------------|
| CI | 现有 `.github/workflows/ci.yml`：PHP 矩阵 + `tests/run-tests.sh` + 发布包 + wp-env smoke。4.0 矩阵见 testing.md。 |
| Plugin Check | 待 M1 |
| PHPCS | WPCS Extra + Docs，排除项见 coding-standards.md；待 M1 |
| PHPStan | level 6 + phpstan-wordpress；待 M1 |
| ESLint | `@wordpress/eslint-plugin/recommended`；待 M1 |
| 发布包 | `scripts/build-release.sh`：ZIP 不含 `.git` / tests / docs / 源码映射；含生产依赖与（4.0）编译产物 |

## 文档同步

改行为 = 同步：

- 对应 `docs/specs/*`（合同）
- `CHANGELOG.md` 的「未发布」段（人读的变更）
- 若动了模块边界或禁区，同步 `docs/4.0-rewrite-plan.md` / ADR（那是别的任务；本目录作者不要顺手改那些路径）
