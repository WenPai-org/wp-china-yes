# mock-app

TEST ONLY fixture for the apps bridge. Signed with `tests/fixtures/keys/wpcy-test-ed25519.key`.

| 文件 | 用途 |
|------|------|
| `index.html` | Sandbox 工具页：按序 ready → init/context.get → data.set/get/delete → entitlement.get → go.open → resize |
| `manifest.json` | 已签名 manifest（`id=mock-app`，`tier=free`） |
| `chromeless.html` | 外层 chromeless iframe + 内层 sandbox 工具（双层场景） |

`index.html` 每步往 `#log` 写一行。宿主 iframe 必须 `sandbox="allow-scripts allow-forms"`，无 `allow-same-origin`。
