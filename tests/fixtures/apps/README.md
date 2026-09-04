# TEST ONLY signed apps fixtures

Contract fixtures for M2-04. Signed with `tests/fixtures/keys/wpcy-test-ed25519.key` (TEST ONLY).

| File | Role |
|------|------|
| `index.valid.json` | Signed index with motusnap, noteboard, paidtool |
| `index.invalid-signature.json` | Same body, bad index signature |
| `index.one-invalid-app.json` | Signed index; one child manifest has a bad signature |
| `motusnap.valid.json` | Full permissions, limited-free |
| `noteboard.valid.json` | `data:read` only, free |
| `paidtool.valid.json` | paid + entitlement |
| `motusnap.invalid-signature.json` | motusnap body, bad signature |

Do not point the plugin at production `https://apps.wpcy.com/index.json` by default.
