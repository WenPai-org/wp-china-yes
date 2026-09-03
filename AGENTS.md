状态：草案（M0）· 2026-09-03 · 依据 linuxjoy 定稿 §7

# WP-China-Yes（文派叶子）

WordPress 中国连接优化插件，也是文派服务的站点接入端。仓：`WenPai-org/wp-china-yes`。

两条产品线（定稿 §7.1b），不是同一产品的两个版本：

- **4.0**：运营与变现的新品；开发主线（目标分支 `main`）。当前阶段 **M0**：冻结合同与文档，不写业务功能。
- **3.9.x**：旧品 LTS，只修安全。维护分支待定（M0）：`codex/wpcy-3.9.3-stabilization` 或 `release/3.9`。

## 先读

1. `docs/4.0-rewrite-plan.md`
2. `docs/architecture/adr-00*.md`
3. `docs/dev/coding-standards.md`
4. 本次改动相关的 `docs/specs/*`

再读 `docs/dev/CONTRIBUTING.md` 与任务指定的上下文（常在 `.grok-context/`，勿提交）。

## 禁区

- 不往 `framework/` 加东西（4.0 删除，不做 Adapter）
- 不做产品范围决定（收不收 / 默认开不开 / 删不删 → 对照定稿 §7.1，不符就停）
- 不写用户可见的「遥测」「匿名数据」类文案（报告常开，界面不露出）
- 不直写第三方商业域名；一律 `https://wpcy.com/go/…`
- 不 push `main` / `master`；不发布（不打生产 tag、不传 Release）
- 不把已删功能用隐藏 section 留着；不在本地判断套餐

## 任务书模式

每个任务含：目标、约束、验收命令、禁区。执行者按任务书做，不自行改范围或命名。

完成报告必须贴命令输出。无输出支撑的「完成」视为未完成。任务书矛盾或做不到：写进报告，不要绕。

细则：[`docs/dev/agents.md`](docs/dev/agents.md)。
