# 3.x `wp_china_yes` 真实配置样本（迁移测试 fixtures）

2026-09-03 从文派 / 菲比斯自有生产站点只读导出，域名与站点专有 URL 已脱敏。
用途：4.0 `Migration` 的 dry-run / 执行 / 回滚测试（见 `docs/4.0-rewrite-plan.md` §7、`docs/dev/testing.md`）。

| 文件 | 3.x 版本 | 作用域 | 说明 |
|------|----------|--------|------|
| `single-3.6.2-01.json` | 3.6.2 | 单站 `option` | 最老样本，仅 5 个键 |
| `single-3.8-02.json` | 3.8 | 单站 | 现网主力版本 |
| `single-3.9.3-03.json` | 3.9.3 | 单站 | 候选版全字段（59 键，含 windfonts_list、adblock_rule 等） |
| `multisite-3.7.1-04.json` | 3.7.1 | 网络 `site_option` | 多站点 |
| `multisite-3.8-05.json` / `-06.json` | 3.8 | 网络 | 多站点主力版本 |

每个文件外层 `_fixture` 是元数据，`wp_china_yes` 是原样 option 值。**不要手改数据部分**；补新样本时同样脱敏。
