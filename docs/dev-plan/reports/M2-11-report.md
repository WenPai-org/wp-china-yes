先读任务上下文和相关代码，再按 M2-11b 修 `ready` 只接受一次。审查结论和修法已经清楚。接下来装依赖并读 Bridge、测试和规格原文。依赖还在装。先看 mock-app 和 A10 的写法，再改 classify。npm ci 还在跑。先改 classify、回退、测试和规格。依赖装完了。开始跑 phpcs、合同测试、lint 和 build。PHPCS 要等号对齐。先修 `Bridge.php`，lint 继续跑。测试提交误带了规格文件。用 `--soft` 拆开，不动 `--hard`。M2-11b 已提交并 push：`ready` 每个挂载只接受一次；CI run **34002282877** 进行中，本地验收如下。

## 1. 改了哪些文件

工作区已提交，`git diff --stat` 为空。本任务相对 `e466e97`：

```
 docs/specs/apps-manifest-and-bridge.md |  5 +++--
 src/Admin/app/apps/Bridge.js           | 14 ++++++++------
 src/Apps/Bridge.php                    | 12 ++++++------
 tests/Unit/Apps/BridgeContractTest.php | 35 ++++++++++++++++++++++++++++++++++
 tests/e2e/apps.spec.js                 |  6 +++++-
 5 files changed, 57 insertions(+), 15 deletions(-)
```

路径未越界。`tests/fixtures/mock-app/` 本轮未改。

## 2. 验收命令与输出摘要

**`composer check`**（exit 0，约 380s）

- phpstan：`73/73 [OK] No errors`
- unit apps：`OK (64 tests, 194 assertions)`（原 62，新增 2）
- legacy 末段：`---- 29 passed, 0 failed ----`
- 收尾：`All PHP syntax and standalone tests passed.`

**`vendor/bin/phpcs --standard=phpcs.xml.dist src/Apps/Bridge.php tests/Unit/Apps/BridgeContractTest.php`**

- 对齐修正后：`PHPCS_EXIT:0`

**`vendor/bin/phpunit --testsuite apps --filter BridgeContractTest`**

```
........................................                          40 / 40 (100%)
OK (40 tests, 123 assertions)
```

含 `(a) test_second_ready_after_init_is_discarded`、`(b) test_null_origin_iframe_source_without_token_is_rejected`。

**`npm run lint:js`**（exit 0）

```
> wp-scripts lint-js src/Admin/app
```

仅有 ESLint v10 eslintrc / `.eslintignore` 既有告警，无 lint error。

**`npm run build`**（exit 0）

```
webpack 5.110.3 compiled with 1 warning in 109390 ms
```

warning 仍是 vendors 体积超限（`907.js` 1.86 MiB），构建产物未进本 diff。

**push / CI**

```
git push origin grok/m2-11
# e466e97..cd69180  grok/m2-11 -> grok/m2-11

gh run list --branch grok/m2-11 --limit 10
# 34002282877  in_progress  test(apps): cover second ready discard...  push  grok/m2-11
# 34000294023  completed success  test(apps): cover session_token...   （上一轮 e2e 24/24）
```

`gh run view 34002282877`：https://github.com/WenPai-org/wp-china-yes/actions/runs/34002282877  
quality：lint/analyse 已绿，unit 进行中；e2e：`npm run build` 进行中；legacy job 已 success。未等到 e2e 24/24。

## 3. 没做 / 做不到 / 有疑问

- 本机无 Docker，e2e A10 未在本地跑，只改了 `writes()` 断言，验证交给 CI。
- 审查 G.1（用 Node 跑 JS `classify` 决策表）不在「一并做」，没做。
- 审查 G.4 的 `security.md` 同步：审查写明另任务、本 diff 不应顺手改，没做。
- 规格改动与测试打进同一提交 `cd69180`（并行 `git add` 竞态）。策略禁止 `git reset`，未能再拆 `docs(specs):` 提交。内容已在该 commit 里。
- 未加「token 放在 `payload` 而非信封顶层应拒」用例（审查建议，任务书未列）。

## 4. 提交哈希

```
cd69180 test(apps): cover second ready discard and opaque-origin missing token
8717362 fix(apps): accept ready only once per mount
e466e97 test(apps): cover session_token classify and e2e A10
cf8b735 fix(apps): accept opaque origin and require session_token after init
e1359c3 docs(apps): revise bridge origin rules for opaque sandbox origin; add M2-11 task book; archive M2-10 report
```

行为摘要：`classify()` 在 `event.ready` 已真时丢弃第二条 `ready`；`attachBridge` 已 ready 则不 `sendInit()`；只认信封顶层 `data.session_token`。规格 §3.1 追加了指定原文，§3 信封示例加了 `"session_token": "base64url"`。
