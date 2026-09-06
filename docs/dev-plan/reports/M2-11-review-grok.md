先读审查技能与任务书，再核对相对 `e1359c3930aad63e73f60987cc9ea24e2934d9d3` 的全部改动。按独立审查员做：先读 reviewer 人设与任务书，再拉 diff 和提交信息。提交对应 M2-11。接着读任务书、规范和改动文件。对照规格与完整源码核对 `classify`、令牌路径和测试覆盖。这是 **M2-11**（`cf8b735` + `e466e97`，相对 `e1359c3`）。任务书清单基本写齐，`composer check` 退出码 0；**第二次 `ready` 会把 `session_token` 再发给已导航的第三方页，规格里这条控制的目的不成立**，作阻断。

审查范围：`git diff e1359c3930aad63e73f60987cc9ea24e2934d9d3 HEAD`（6 文件，+285/−14）。只读，未改任何文件。

---

## A 正确性

任务书交付物按清单都写了，PHP 合同与 mock-app 回带令牌对得上；**`ready` 在已 `init` 之后仍再发令牌**，与 §3.1 写明的威胁模型冲突。e2e/CI 本审查未跑。

任务书 `docs/dev-plan/tasks/M2-11.md` 四条落地对照：

| 交付 | 结果 |
|------|------|
| PHP `classify`：`origin` 为 `"null"` 或 `entry_origin` | 有。`src/Apps/Bridge.php:234-239`、`:364` |
| 32 字节 token、`init.payload.session_token`、之后信封顶层、`ready` 例外 | 有。`Bridge.js:83-94`、`:424-435`、`:269-289`；PHP `:380-403` |
| 合同测试 6 类 | 有。`BridgeContractTest.php:277-361` |
| e2e A10 + mock-app 按钮/`?bad_token=1` | 用例写了。`apps.spec.js:516-528`，`mock-app/index.html:14,23-25,54-57,121-124` |
| 不改 outbound `'*'`、不加 `allow-same-origin` | 仍是 `postMessage( message, '*' )`（`Bridge.js:395`），`IFRAME_SANDBOX = 'allow-scripts allow-forms'` |
| 路径不越界 | `git diff --stat` 仅这 6 个文件；规格已在基线 `e1359c3` 改过，本 diff 不含 `docs/specs/` |

漏项 / 未验证：

1. **第二次 `ready` 重发 `init`**（见 B/D/G）。`classify` 在 `type === 'ready'` 时直接 `return { action: 'init' }`，不看 `event.ready`：

```265:267:src/Admin/app/apps/Bridge.js
	if ( type === 'ready' ) {
		return { action: 'init' };
	}
```

```380:382:src/Apps/Bridge.php
		if ( 'ready' === $type ) {
			return array( 'action' => 'init' );
		}
```

`attachBridge` 每次 `init` 都 `sendInit()`，令牌是挂载时那一枚（`Bridge.js:376,545-548`）。iframe 内导航不走 `Services.js:464-487` 的 `useEffect`，不会换桥、不会换令牌。

2. 验收「分支 CI e2e 24 条 + run id」：本审查未 `push`、未见 run id。A10 只存在于源码。

3. 任务书本地还写了 `npm run lint:js`、`npm run build`，未跑。

提交前缀符合任务书：`fix(apps):` / `test(apps):`。

---

## B 运行时风险

无新的 PHP Fatal 语法；多站点与本 diff 无关。**第二次 `ready` 是运行时安全洞，不是 Warning。** 本机 `composer check` 跑在 PHP 8.4.7，仓声明仍是 7.4。

- `composer.json`：`"php": ">=7.4"`，`platform.php = 7.4.33`；`readme.txt`：`Requires PHP: 7.4.0`。
- 新增 PHP：`string` 参数/返回值（7.1+）、`hash_equals`（5.6+）、无 `match`/union/named args/`str_contains`。静态看 7.4 能解析。
- `session_token_valid` 先拒空串再 `hash_equals`（`Bridge.php:251-256`）。若只调 `hash_equals('', '')`，PHP 会返回 true；空令牌被拦住了。
- `createSessionToken` 依赖 `crypto.getRandomValues` + `btoa`（`Bridge.js:83-94`）。WP 下限 6.5 的管理端浏览器都有；缺 `crypto` 会在 `attachBridge` 抛错，整桥挂掉，不会默默用弱令牌。
- 多站点：不碰 option / 表 / capability，无网络/子站分支。
- JS `sessionTokenValid` 用 `===`（`Bridge.js:112`），PHP 用 `hash_equals`。运行时走 JS；宿主页上测时序不现实，合同不一致而已。

---

## C 规范

改动落在允许路径内，未见禁区命中；WPCS/PHPStan 本审查通过。

- PSR-4：无新类。`Bridge.php` 有 `declare(strict_types=1);`（`:13`）。
- 注释/DocBlock 英文。
- `phpcs` 对 `src/Apps/Bridge.php`、`tests/Unit/Apps/BridgeContractTest.php` 退出码 0。
- 禁区：diff 不读 `wp_china_yes`、不碰 `framework/`、生产代码不直写第三方商业域（测试用 `evil.example` / `third-party.example`）、无「遥测」「匿名数据」用户文案。
- outbound `'*'` 与接受 `"null"` 和 `docs/dev/security.md:179-203` 原文冲突，但是任务书第 23 行和 spec §3.1 **要求保持**，不算本任务违规。

---

## D 安全

`event.source` 仍先于 origin/token；nonce 仍不进 iframe。**令牌挡不住「沙箱内导航后再发 `ready`」。**

仍成立：

- `source_is_parent` / `source_is_iframe` 在 origin 之前（PHP `:351-356`，JS `:235-240`）。
- 工具拿不到 WP nonce：`test_host_js_does_not_put_nonce_in_envelope` 仍断言 `\bnonce\b` 为零（`BridgeContractTest.php:468-471`）。
- 错误回包走 `postError` → `iframe.contentWindow.postMessage`，不向 `parent`/`top` 打（`Bridge.js:388-396,541-543`）。
- 令牌用 `crypto.getRandomValues` 32 字节，base64url 去 padding。

不成立的那条：

规格原文（`docs/specs/apps-manifest-and-bridge.md:114`）：

> 目的：工具页在沙箱内自行导航到第三方页面后，第三方仍是同一个 `event.source`，但拿不到 token。`ready` 是唯一不需要 token 的工具消息。

无 `allow-same-origin` 时，iframe 内 `location.href = 'https://evil.example/…'` 后：

- `event.source === iframe.contentWindow` 仍真（WindowProxy 跨导航不变）
- `event.origin` 仍是 `"null"`（仍是 unique-origin sandbox）→ `origin_allowed` 通过
- 新文档发 `{wpcy:1,type:'ready'}` → `sendInit()` 把同一枚 `session_token` 交给第三方
- 之后 `data.set` 带该 token 会走 REST

合同测试 `test_same_source_third_party_origin_without_token_is_rejected`（`BridgeContractTest.php:344-361`）把 origin 改成 `https://third-party.example`，走的是 **origin_mismatch**，不是真实沙箱导航（origin 仍为 `"null"`）。

capability/sanitize/escape/远程请求：本 diff 不新增 REST 路由或输出。

---

## E 测试质量

PHP 合同用例能红，不是假通过；JS `classify` 几乎只靠源码字符串；A10 未在本审查执行。`composer check` 绿。

能红的：

- `event()` 给非 `ready` 默认带 `test-session-token`（`BridgeContractTest.php:514-533`）。漏改 helper 时，原有 `data.get`/`resize` 会变 `session_invalid`。
- `test_missing_session_token_is_rejected` / `test_wrong_session_token_is_rejected` 断言 `ERR_SESSION_INVALID` 与 `request_id`。
- A10：错误 token 的 `data.set` 带 `req-bad-token`；若宿主静默丢弃或误放行，log 不会出现 `session_invalid`（`mock-app/index.html:54-57,142-147`）。

偏弱 / 未覆盖：

- JS 行为：`strpos( $js, 'function createSessionToken' )` 等（`BridgeContractTest.php:366-373,479-482`），改坏 `classify` 分支仍可能绿。运行时只靠 e2e。
- 无「`event.ready === true` 时再来一条 `ready`」用例。
- 无「origin 仍为 `"null"` + 无 token」（真实沙箱导航）；缺 token 用例用的是 `https://apps.wpcy.com`。
- 无「token 放在 `payload` 而非信封顶层」应拒。
- A10 不查 `writes()`（对比 A7）。`action === 'error'` 会 `return` 不转发（`Bridge.js:541-543`），逻辑上够，断言偏窄。
- `#send-bad-token` 按钮无 e2e，只测了 `?bad_token=1`。

**`composer check` 摘要**（本机 PHP 8.4.7，退出码 0，约 356s）：

```
phpstan: [OK] No errors  (73/73)
phpcs: 对改动 PHP 单独再跑 exit=0
unit: smoke 1, core 20, config 40, connectivity 92, telemetry 3,
      privacy 11, diagnostics 13, cli 8, rest 24, integrations 20,
      migration 29, site-binding 12, apps 62, entitlements 11, admin 19
legacy 末段: ---- 29 passed, 0 failed ----
All PHP syntax and standalone tests passed.
```

未跑：`npm run lint:js`、`npm run build`、Playwright e2e。

---

## F 与 spec 的偏差

逐条（spec：`docs/specs/apps-manifest-and-bridge.md`）：

| spec | 实现 | 判定 |
|------|------|------|
| §3.1 `event.source === iframe.contentWindow` 为身份 | JS `:528`，PHP 先查 `source_is_iframe` | 符合 |
| §3.1 origin ∈ {`"null"`, entry_url origin} | `originAllowed` / `origin_allowed` | 符合 |
| §3.1 `postMessage(..., '*')` 且禁止对 parent/top 用 `'*'` | `:395`；无 `parent.postMessage` | 符合 |
| §3.1 `init.payload.session_token`，之后信封顶层 | `sendInit` 放 payload；inbound 读 `data.session_token` | 符合 |
| §3.1 `ready` 免 token | `:265-267` / PHP `:380-382` | 字面符合 |
| §3.1 目的：导航后第三方拿不到 token | 再发 `ready` 会拿到 | **偏离目的** |
| §3.1a `ready` 之后才处理其它消息 | `!ready && type !== 'ready'` → discard | 符合 |
| §3.2 `init` 字段含 `session_token` | `:427-434` | 符合 |
| §5 `wpcy_apps_session_invalid` | 常量 + classify + A10 期望子串 | 符合 |
| §3 信封示例 JSON（`:94-101`）无 `session_token` | 运行时非 `ready` 必带 | 文档示例落后；任务书只要求 SDK 段，仓内无「SDK」段 |
| `security.md:193-203` 禁止 `'*'`、按 origin 白名单 | 本任务明确保持 `'*'` + `"null"` | 相对 security.md 偏离，任务书授权；security.md 未改（不在允许路径） |
| classify 额外认 `event.session_token` | PHP `:387-388`，JS `:272-273` | spec 只认信封顶层；运行时 `onMessage` 不传该字段，PHP 合同比 spec 松 |

---

## G 清单

### 阻断

1. **第二次 `ready` 重发 `session_token`**  
   文件：`src/Admin/app/apps/Bridge.js:265-267`、`:545-548`；`src/Apps/Bridge.php:380-382`  
   修法：
   - `classify`：`type === 'ready'` 且 `event.ready` 已为真 → `discard`（不要 `init`）。
   - `attachBridge`：`if ( ready ) return;` 再 `sendInit()`（双保险）。
   - 合同测试：已 `ready` 的第二条 `ready` 断言 `discard`；补 origin 仍为 `"null"`、无 token 的 `data.set` → `ERR_SESSION_INVALID`。
   - 规格补一句：`ready` 每个挂载只接受一次。不改这条就把 §3.1 令牌当成摆设。

### 建议

1. `BridgeContractTest.php:366-373` 对 JS 的 `strpos` 不能代替执行 `classify`。有 Node 测试再跑一遍 JS 决策表。
2. 删掉 `event.session_token` 回退（`Bridge.php:387-388`，`Bridge.js:272-273`），只认信封顶层。
3. A10 加上对 `mockApps` `writes()` 不变的断言（同 A7）。
4. §3 信封示例 JSON 补上 `session_token`；`security.md` 小工具表与 §3.1 同步（另任务，本 diff 不应顺手改）。
5. 合并前统筹跑分支 CI e2e，贴 run id。本审查没有这条证据。

### 确认无误

- `"null"` origin + 合法 token → `rest`/`init`（`BridgeContractTest.php:277-285`）。
- 陌生 origin → `wpcy_apps_origin_mismatch`（`:290-296`，与旧用例 `:214-221` 重复但结论对）。
- 缺/错 token → `wpcy_apps_session_invalid`（`:301-327`）。
- 无 token 的 `ready` → `init`（`:332-337`）。
- sandbox 仍无 `allow-same-origin`；outbound 仍 `'*'`。
- mock-app 从 `init.payload` 记下 token，后续信封顶层回带（`index.html:34-47,116-118`）。
- 路径、提交前缀、`composer check`（本机 8.4.7）通过；legacy 末段 29 passed。
