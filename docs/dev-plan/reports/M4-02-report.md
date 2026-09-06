先读任务书依赖文档和仓库上下文，再装依赖、核对 3.9.3 ZIP 是否存在。先登记工作摘要，并并行读任务书、迁移代码和 3.9.3 发行物来源。M4-01 已在 `main`。继续读迁移代码、Studio 路径和 3.9.3 发行 ZIP。继续读规格、fixtures 和 Studio 环境，同时开始装依赖。Studio 在 Mac 上不在 `~/Studio/wpcy-40`。接着查 ZIP、远程 Studio，以及 `store=proxy` 等映射缺口。3.8 与 3.9.3 的 GitHub Release 附件都在。接下来下载发行 ZIP、连 Studio，并开始装依赖。对照 §5：`store=proxy` 未映射，3.8 的 `admincdn` 与 `admincdn_files.admin` 结果不一致。先补测试再改 `Mappers.php`。开始改 `Mappers.php` 并补 §5 对照断言。wpcy-40 上已是 3.9.3。一边等 `composer check` / `npm run build`，一边把 ZIP 拷到 wenpai 并建多站点。矩阵脚本不能从 `/tmp` 跑。改放到 wenpai 的 `~/bin` 再执行。八行都跑完了。接下来把每行产物拉回来写验证报告。正在写验证报告。八行矩阵在 wenpai Studio 上跑完；`store=proxy` 与 3.8 `admincdn` 已修。验证报告：`docs/dev-plan/verification/m4-upgrade-matrix-2026-09-06.md`。

CI run **34017904879** 进行中，本地验收如下。

---

## 1. 改了哪些文件

```
 .../verification/m4-upgrade-matrix-2026-09-06.md   | 206 +++++++++++++++++++++
 src/Migration/Mappers.php                          |  27 ++-
 tests/Unit/Migration/FixturesTest.php              | 165 ++++++++++++++++-
 3 files changed, 392 insertions(+), 6 deletions(-)
```

不含 `docs/dev-plan/README.md`、不含 fixtures JSON 数据、不含 `.grok-context/`。

两个 commit：`fix(migration):` 然后 `docs(verify):`。

---

## 2. 验收标准：命令与输出摘要

环境：`ssh wenpai`，Studio `/home/parallels/.studio/bin/studio` 1.15.0。单站 `~/Studio/wpcy-40` `http://localhost:8891`；多站点 `~/Studio/wpcy-40-ms` `http://localhost:8892`（本任务 `studio create` + `wp core multisite-convert`）。未用本机 Docker。

**ZIP**

| 包 | 来源 | SHA-256 |
|----|------|---------|
| 3.8 | GitHub Release v3.8 附件 | `414c8c4c911f3cf55fc01a228076113786cd39352b4f86a1d3285a680a28d8d0` |
| 3.9.3 | GitHub Release v3.9.3 附件（`prerelease=true`） | `a2ac53599a845822fe4bdc94f04659303e6b856cf29e94172b963de074dede05`（与 `.sha256` 附件一致） |
| 4.0 | 本分支 `bash scripts/build-release.sh` | `8a9ff499dc8cfe853b8cc0b9779a428ba4b6a586d84f50962095521ed354277a`（头仍写 Version 3.9.3；`Requires PHP: 8.0` + `Core\Plugin::boot()`） |

3.x 无 `wp wpcy migrate`：先换成 4.0 启用（首次启动已 execute），再 dry-run。

哈希：`hash("sha256", wp_json_encode(get_option/get_site_option("wp_china_yes")))`。

| 行 | 预置 | dry-run kept / ignored | 4.0 option | `wp_china_yes` 哈希 | 装回 |
|----|------|------------------------|------------|---------------------|------|
| S1 | 3.8 ZIP + `single-3.6.2-01.json` | kept 4（store/cravatar/windfonts/adblock）；ignored `admincdn` | `wpcy_settings` 非空，`wordpress_org=off`，`avatar=weavatar`，public_assets 五项默认 | 四阶段 `328568b8…` | 3.8 与 3.9.3 键未变；eval_exit=0；前台 200 |
| S2 | 3.8 ZIP + `single-3.8-02.json` | kept 4；ignored 11 | `public_assets=[]`（`admincdn=['admin']`） | `49b4f2ac…` | 同上 |
| S3 | 3.9.3 ZIP + 59 键 | kept 8；ignored 51（arkpress/motucloud/hide_*/performance/内存四项/enabled_sections 等 `feature_removed`，未误映射） | `wordpress_org=auto` `public_assets=[]` | `d57baac6…` | 装回 3.9.3 哈希相同 |
| N1 | 3.8 ZIP + site_option `multisite-3.7.1-04.json` | kept 4；ignored 9 | **`wpcy_network_settings` 非空**（`allow_site_override=true`）；子站 `wpcy_settings` ABSENT_OK | `4d03c20e…` | 3.8 / 3.9.3 相同 |
| N2 | `multisite-3.8-05.json` | kept 4；ignored 10 | 同上 network | `a4371f71…` | 相同 |
| N3 | `multisite-3.8-06.json`（admincdn 空串） | 同 N2 | 空串不触发清空，public_assets 五项默认 | `d85413ac…` | 相同 |
| D1 | `update_option("wp_china_yes","corrupted-string")` → `string:corrupted-string` | kept=[] ignored=[]（Reader 当空） | 默认 schema；option 仍是 `"corrupted-string"` | `127aa4d9…` | 4.0 `eval_ok PHP=8.3.32` exit 0；前台 200；装回 3.9.3 `reinstall_ok`，仍 `<string:16>` |
| D2 | 3.8-02 + 1MiB `huge_blob`：`serialize_len=1049159 json_len=1048952 keys=16` | ignored 含 `huge_blob` | 非空 | `cee7ac6a…` 四阶段相同（未被截断） | 键仍含 `huge_blob` |

**验收 4** `composer check` exit 0（phpstan 73/73；Migration `OK (34 tests, 508 assertions)`）。`npm run build` 0；`npm run lint:js` 0。

**验收 5** 提交前缀：`fix(migration):` / `docs(verify):`。

**验收 6（§5 对照）** 见报告内表。`store=proxy → auto` 已修。3.8 `admincdn=['admin']` 与 `admincdn_files` 含 `admin` 的 4.0 `public_assets` 均为 `[]`。hide / 内存四项：4.0 无目标字段，丢弃（未映射到其它能力）。

---

## 3. 没做 / 做不到 / 有疑问

1. 4.0 头 Version 仍是 3.9.3（M4-03 才改 rc.1）。
2. 3.9.3 Release 为 prerelease（通道撤回）。用的是 Release 附件，不是源码包、不是 4.0 树。
3. `hide_*` 合并为白标藏菜单、内存四项闸在 `performance`：4.0 schema 无对应项；按禁区丢弃。测试已钉死不写入。
4. Studio 未单跑 `wp wpcy migrate --rollback`（装回不依赖它）。单元测试已覆盖 rollback 不写 `wp_china_yes`。
5. 未改 `ci.yml`；本机无 Docker。CI run `34017904879` 进行中。
6. N1 第一次因 `plugin install --network` 非法参数失败；改为 `install --force` + `activate --network` 后 N1–D2 跑完。

---

## 4. 提交哈希

```
f5ade69 docs(verify): record M4-02 3.8/3.9.3 to 4.0 upgrade matrix
31760da fix(migration): map store=proxy and 3.8 admincdn to the same 4.0 values
40de819 docs(dev-plan): M4-01 merged; archive reports and independent review
5a80e1c Merge branch 'grok/m4-01'
b7876fe test: seed windfonts entitlement so wp_head CI can register
```

已 `git push -u origin grok/m4-02`。未 push `main`。

---

## DoD

- [x] 规格 ↔ 实现对照表：报告 §5 表；hide/内存两行标「未做写入」。
- [x] 截图：非 UI，不适用。
- [x] 用户可见字符串：未新增；无「遥测/隐私/上报」。
- [x] 空/错误/降级：D1 当空、D2 ignored，无 Fatal。
- [x] 测试：新增 5 个 §5 用例；`composer check` 0；`npm run build` 0；`npm run lint:js` 0；CI run **34017904879** 进行中。
- [x] diff 范围合法；无 `.grok-context/`。
- [x] 没做/疑问：第 3 节。
