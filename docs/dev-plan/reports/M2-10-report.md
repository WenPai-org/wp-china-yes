先读任务上下文和失败日志，同时装依赖。工作树已在 `grok/m2-10`。接着读失败日志、任务书和相关源码，并装依赖。A3/A8 是严格模式定位器撞到重复文本。继续读 A9 日志和相关源码。失败日志里 A8 实际已渲染，是严格模式撞到无障碍区域。接着读 Bridge、Services、mock-app 和 A9 相关代码。A9 的 120s 超时很像 `about:blank` 套 wp-admin 被 `X-Frame-Options` 拦住。先用 Playwright 验证 sandbox 的 `event.origin`，再决定 A5 怎么改。实证结果：sandbox 下 `event.origin` 是 `"null"`，用 URL 作 `targetOrigin` 的 `postMessage` 到不了子 iframe；`event.source === iframe.contentWindow` 仍成立。开始按这个根因改。本地检查已过。按任务前缀拆成两次提交，再推到 `grok/m2-10`。PHP / quality 已绿，e2e 还在跑。等 CI 通知。frontend 已绿。e2e 仍在跑，继续等。A3/A8/A9 已过。A5 桥通了，但 `data.set`/`data.delete` 回了 `wpcy_apps_unknown_app`。接着查 REST 转发和 mock 拦截。根因是 `@wordpress/api-fetch` 把 PUT/DELETE 改成带 `X-HTTP-Method-Override` 的 POST，mock 没认这个头，请求打到了真实 REST。A3/A5/A8/A9 已在 CI `e2e` 全绿。最终 run **33998403286**，Playwright **23 passed**（原日志里的 27 是 23 条 + 4 次 retry）。

## 1. 改了哪些文件

```
 src/Admin/app/apps/Bridge.js           | 26 ++++++++++++++++++++++++--
 src/Admin/app/pages/Services.js        |  4 +++-
 tests/Unit/Apps/BridgeContractTest.php | 25 +++++++++++++++++++++++++
 tests/e2e/apps.spec.js                 | 32 +++++++++++++++++++++++++-------
 4 files changed, 77 insertions(+), 10 deletions(-)
```

## 2. 验收标准：命令与输出摘要

### 2.1 CI `e2e` 全绿

| run id | 结果 |
|---|---|
| 33997588224 | A3/A8/A9 过；A5 仍失败：`data.set`/`data.delete` → `wpcy_apps_unknown_app`（桥已通） |
| 33998080973 | A5 过；A7 失败：`writes()` 在工具自己的 `data.set` 期间被加一 |
| **33998403286** | **success**，job `e2e` success |

`gh run view 33998403286 --json jobs`：quality / frontend / php 7.4–8.4 / plugin-check / wordpress / package / **e2e** 均为 `success`。

e2e 逐条（job `101392832546`）：

```
Running 23 tests using 1 worker
  ✓  1 A1: 未绑定打开文派服务
  ✓  2 A2: mock 绑定成功
  ✓  3 A3: 权益表三态夹具
  ✓  4 A4: 加载 mock 工具
  ✓  5 A5: 读写删数据
  ✓  6 A6: 无权益工具点击
  ✓  7 A7: 跨 origin 消息
  ✓  8 A8: 索引不可达
  ✓  9 A9: 双层 iframe
  ✓ 10–23 chromeless / commands / connect / diagnose / kernel / overview / recovery / services
  23 passed (1.5m)
```

任务书写的「27/27」对应上次失败时 23 条 + A3/A5/A8/A9 各 retry 一次。这次无 retry，就是全绿。

### 2.2 本地 `composer check` / `npm run build` / `npm run lint:js`

`composer check`：exit 0。phpstan `[OK] No errors`；各 PHPUnit suite OK（含 apps 56 tests、rest 24 tests）；standalone PASS。

`npm run lint:js`：exit 0。

`npm run build`：exit 0。`webpack 5.110.3 compiled with 1 warning`（vendor 体积，原有）。

`npm run format:check`：exit 0。

### 2.3 `git diff --stat` 范围

限于 `tests/e2e/`、`src/Admin/app/apps/`、`src/Admin/app/pages/Services.js`、`tests/Unit/Apps/`。未改 `Service/`、`client/`、`framework/`，未 push `main`。

### 2.4 提交前缀

`fix(apps):` 一条；`test(e2e):` 三条。

## 源码 bug vs 测试写错

**源码（A5）** — `Bridge.js` / `Services.js`

sandbox 无 same-origin token 时，工具页 `event.origin` 是字符串 `"null"`，且 `postMessage(msg, entryOrigin)` 送不进 iframe。Playwright 实证：`event.source === iframe.contentWindow` 仍为真。修法：

- inbound：`event.source` 必须是该 iframe；origin 为 entry origin **或** `"null"`
- outbound：只对 `iframe.contentWindow` 发，`targetOrigin` 用 `'*'`（opaque origin 无法匹配 URL）
- 先挂 listener 再设 `iframe src`，避免 miss `ready`

**测试**

| 用例 | 原因 |
|---|---|
| A3 | `getByText('已到期')` 撞权益表 + 小工具角标。改为 `getByRole('row').filter({ hasText: 'admincdn' })` |
| A8 | 琥珀提示**已经渲染**；严格模式撞 Notice + `#a11y-speak-polite`。改为 `getByLabel('小工具').getByText(...)` |
| A9 | `setContent` 在 `about:blank` 上套 wp-admin，被 `X-Frame-Options: SAMEORIGIN` 挡住，等到 120s。先 `goto(BASE_URL+'/')` 再嵌 iframe |
| A5 mock | `@wordpress/api-fetch` 把 PUT/DELETE 改成 POST + `X-HTTP-Method-Override`，mock 只认原动词，请求打到真实 REST，`mock-app` 不在签名索引里 → `wpcy_apps_unknown_app` |
| A7 | 上述 mock 修好后，工具自己的 `data.set` 会计入 `writes()`。等 log 出现 `set ok` 再采样 |

## 3. 没做 / 做不到 / 有疑问

- **`src/Apps/Bridge.php` 未改**（不在允许路径）。PHP `classify()` 仍要求 origin === entry origin，不认 `"null"`。运行时走的是 JS。合同测试用 JS 源码断言 +「同源且 source 是 iframe」的 PHP 用例。
- **`src/Rest/AppsController.php` 未改**。`unknown_app` 来自 mock 没拦到 PUT/DELETE，不是权限判断写错。
- **`tests/fixtures/mock-app/` 未改**。
- 任务书把 A8 写成「琥珀提示未渲染」——CI 日志是已渲染、定位器撞无障碍区域。
- 任务书把 A5 主因写成「同源被当成 parent」。实测 iframe 消息 `isParent=false`；真正挡路的是 opaque origin `"null"` 和 URL `targetOrigin`。
- 未把 outbound `postMessage` 的 `'*'` 换成 URL origin：unique-origin sandbox 下 URL origin 匹配不上。消息只发给该 `WindowProxy`，inbound 仍校验 `event.source`。
- 验收写 27/27，Playwright 套件是 23 条（无 retry 即全绿）。

## 4. 提交哈希

```
5dca61a test(e2e): wait for mock-app set before A7 write count
bcb839f test(e2e): honor X-HTTP-Method-Override in apps REST mock
da577cc test(e2e): scope A3/A8 locators and load A9 from same origin
11f3e92 fix(apps): accept unique-origin sandbox messages via event.source
ea37e16 Merge branch 'grok/m2-09'
```
