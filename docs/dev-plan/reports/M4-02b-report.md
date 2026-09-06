先读任务书、审查文档和相关 skill，再改 `admincdn` 映射。HEAD 已在 `f5ade69`。接着读 mapper、测试和矩阵文档，并装依赖。接着读测试、fixture 和 Studio 操作方式，同时装 npm。开始改 mapper 和测试：空 `admincdn` 视为显式 `[]`，unknown token 一律进 `ignored`。接着改测试、加 `store=proxy` fixture，并更新 hide/内存断言。`composer check` 已绿。先提交代码与测试，再上 Studio。一边等前端构建，一边写 Studio 验证脚本并打 4.0 包。`/tmp` 脚本被拦了，改成通过 stdin 在 wenpai 上跑。回滚已在同一次请求里删掉 `wpcy_settings`。接着补跑 S1/N1 的 dry-run，避免矩阵和单元断言打架。开始改矩阵文档：补 S2/N2/N3/D2 原文、`store=proxy` 行和 rollback。3.8 `admincdn` 键存在时（`[]` / `''` / 数组）`public_assets` 只由该键（及并存的 3.9 三键）推导，为空不回落五项默认。两笔提交已 push，CI **34019965572** 全绿。

## 1. 改了哪些文件

`git diff --stat f5ade69`：

```
 .../verification/m4-upgrade-matrix-2026-09-06.md   | 186 ++++++++++++++++++---
 src/Migration/Mappers.php                          |  25 +--
 tests/Unit/Migration/FixturesTest.php              |  91 ++++++++--
 tests/fixtures/legacy-options/README.md            |   1 +
 .../legacy-options/single-3.9-07-store-proxy.json  | 149 +++++++++++++++++
 5 files changed, 400 insertions(+), 52 deletions(-)
```

未改已有 fixtures JSON 数据；未改 `docs/dev-plan/README.md`；`.grok-context/` 未入库。未 push `main`。

两笔提交：

- `bea80ea` `fix(migration): treat present 3.8 admincdn as explicit public_assets=[]`
- `e0f324f` `docs(verify): record M4-02b empty admincdn, store=proxy, and rollback`

## 2. 验收命令与输出摘要

### `composer check`

exit **0**。phpstan `73/73 [OK] No errors`；phpcs 无违规。PHPUnit 全套绿，Migration：

```
OK (39 tests, 593 assertions)
```

（相对 M4-02 的 34 tests / 508 assertions：多了 proxy fixture 的 4 条 dataProvider + 混合 token 1 条。）

`npm run lint:js` exit 0；`npm run build` exit 0。

### 单元：空 `admincdn` / S2 token / 混合 token

`vendor/bin/phpunit --testsuite migration`：

```
OK (39 tests, 593 assertions)
```

| 样本 | `public_assets` | `ignored` |
|------|-----------------|-----------|
| S1 `admincdn=[]` | `[]` | 键 `admincdn` |
| S2 `['admin']` | `[]` | 键 `admincdn` + token `admin` |
| N2 `[]` / N3 `''` / N1 `''` | `[]` | 键 `admincdn` |
| 合成 `['admin','googlefonts','jsdelivr','bootstrapcdn']` | `['google_fonts','jsdelivr']` | `admin`、`bootstrapcdn`（及键 `admincdn`） |
| `single-3.9-07-store-proxy.json` | `[]` | 同 S3；`wordpress_org=auto` |

hide / 内存测试改为断言 `modules` / `connectivity` 键集合等于 `Schema::definition(SETTINGS)`，不再断言顶层没有 `hide`。

### Studio（wenpai，`/home/parallels/.studio/bin/studio`，站 `wpcy-40` / `wpcy-40-ms`）

4.0 包 SHA-256 `e53c8f4348fbe1a6bd870f2cd5ee83b75d5f8218cf44f4138159fda31a03917d`。

S2 dry-run 摘要：`kept=["store","cravatar","windfonts","adblock"]`，`ignored` 含 `admincdn` 与 token `admin`，`public_assets=[]`。`option get wp_china_yes` 哈希仍 `49b4f2acb853f8da560846b94e1ab419bd521d74859aa8d4fdd7be4fd2f6e0ed`。

N2 / N3：`public_assets=[]`（不再五项默认）；子站 `option get wpcy_settings` → `Error: Could not get 'wpcy_settings' option. Does it exist?`。哈希仍 `a4371f71…` / `d85413ac…`。

D2：`serialize_len=1049159 json_len=1048952 keys=16`；ignored 含 `huge_blob` 与 `admin`；哈希仍 `cee7ac6a55c599941c1b171597fb2023cd9c44fcbf1334970865f7d3dd2e423c`。

S07 `store=proxy`：`wpcy_settings.connectivity.wordpress_org=auto`；`wp_china_yes.store` 仍是 `proxy`。

Rollback 同请求：

```
{"had_settings_before":true,"ok":true,"settings_after_false":true,"backup_after_false":true,"legacy_before":"49b4f2acb853f8da560846b94e1ab419bd521d74859aa8d4fdd7be4fd2f6e0ed","legacy_after":"49b4f2acb853f8da560846b94e1ab419bd521d74859aa8d4fdd7be4fd2f6e0ed","legacy_unchanged":true}
```

CLI：`{"ok":true,"action":"rollback"}`。下一轮 `studio wp` 会因 first-boot 再 execute（`src/Core/Plugin.php:101`），不把 4.0 写进 `wp_china_yes`。

完整 JSON 在 `docs/dev-plan/verification/m4-upgrade-matrix-2026-09-06.md`。

### CI

```
gh run list --branch grok/m4-02 --limit 1
completedsuccess  docs(verify): record M4-02b …  CI  grok/m4-02  push  34019965572  4m29s
```

`gh run watch 34019965572 --exit-status` exit 0。jobs：quality、php 8.0–8.4、frontend、e2e、plugin-check、wordpress、package 全绿。

## 3. 没做 / 做不到 / 有疑问

1. **任务书混合 token 期望写成 `['googlefonts','jsdelivr']`**：4.0 枚举是 `google_fonts` / `jsdelivr`。单元用枚举值，未把 3.x token 名写进 `public_assets`。
2. **任务书点名 S1/N2/N3 改断言；N1 同样是 `admincdn=""`**。dataProvider 一并改成 `[]`，否则红。Studio 已补跑 S1/N1 dry-run。
3. **CLI rollback 之后立刻再跑 `option get wpcy_settings` 会看到选项又在**：`maybe_migrate_from_legacy` 在下一进程再 execute。删除证据取自 **同一次** `eval` 里 `rollback()` 之后立刻 `get_option(..., false)===false`。
4. **未改 `.github/workflows/ci.yml`**（本 diff 不需要）；本机无 Docker，未跑 wp-env。
5. **4.0 包 Version 头仍是 3.9.3**（M4-03 才改 rc.1）。
6. **hide/内存仍无 4.0 目标字段**，继续丢弃；测试改为扫 schema 键集合。
7. 任务书列了 `test:` 前缀；本轮按 M4-02 原结构把测试放进 `fix(migration):`，文档单独 `docs(verify):`，共两笔。

## 4. 提交哈希

```
e0f324f docs(verify): record M4-02b empty admincdn, store=proxy, and rollback
bea80ea fix(migration): treat present 3.8 admincdn as explicit public_assets=[]
f5ade69 docs(verify): record M4-02 3.8/3.9.3 to 4.0 upgrade matrix
31760da fix(migration): map store=proxy and 3.8 admincdn to the same 4.0 values
40de819 docs(dev-plan): M4-01 merged; archive reports and independent review
```

全哈希：`e0f324f3f798f0516e99cc9f82b8d47cbcaa8030`、`bea80ea38a94b919bb7c16d729883b37465cec19`。

## DoD

- [x] 规格条目 ↔ 实现对照表（3.x 选项审计 §5 ↔ `Mappers.php` 逐键）：

| 规格编号 | 实现位置 | 状态 |
|----------|----------|------|
| 壳键 arkpress/motucloud/…/monitor/waimao | `src/Migration/Mappers.php:185-188` default `feature_removed` | 已做 |
| hide_* / hide | 同上丢弃；测试扫 schema 键集合 `FixturesTest.php:assert_modules_connectivity_keys_match_schema` | 未做写入：4.0 无白标字段 |
| store=`proxy` → wenpai/`auto` | `Mappers.php:267`；fixture `single-3.9-07-store-proxy.json`；Studio S07 | 已做 |
| 3.8 `admincdn` 键存在 → 只由该键（并 3.9 三键）推导，空则 `[]` | `Mappers.php:192-214,294` `array_key_exists` | 已做 |
| `googlefonts`/`googleajax`/`cdnjs`/`jsdelivr` → 枚举；`admin`/`frontend`/`bootstrapcdn`/未知 → ignored | `PUBLIC_ASSET_MAP` + unknown 循环 `Mappers.php:201-204` | 已做 |
| 无 admincdn 且无 3.9 三键 → 五项默认 | `test_public_assets_default_when_admincdn_keys_absent` | 已做 |
| 内存四项 / performance | `feature_removed`；键集合等于 schema | 未做写入：4.0 无内存常量 |

- [x] 截图：非 UI，不适用。
- [x] 面向用户字符串：本任务未加用户可见文案；无「遥测/隐私/上报」。
- [x] 空/错误/降级：空 `admincdn` → `[]`；D2 `huge_blob` ignored；rollback 删 4.0 option、不改 3.x。
- [x] 测试：`composer check` 0；`npm run build` 0；`npm run lint:js` 0。CI run **34019965572** success。
- [x] `git diff --stat f5ade69` 允许路径内；无 `.grok-context/`；无 `docs/dev-plan/README.md`；无已有 fixtures JSON 数据改动。
- [x] 没做 / 疑问：第 3 节。
