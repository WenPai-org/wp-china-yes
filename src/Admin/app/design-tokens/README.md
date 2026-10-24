# Design tokens (pinned consumer copy)

**Machine source of truth** is [`wenpai-admin-ui`](https://github.com/WenPai-org/wenpai-admin-ui) `tokens/tokens.json` (kit). This directory holds a **pinned consumer snapshot** for 文派叶子 (`wp-china-yes`) so the Admin app can generate `--wpcy-*` CSS without a live kit checkout.

| Field | Value |
|-------|-------|
| Kit tip pinned | `c879826cba22bc94dbeff80373ce361270b3f054` (`v0.3.2-54-gc879826`, PR #42 dual-stack) |
| `$version` | `0.3.2` |
| Align consumer | `wpcy-admin-ui@c7fd441` (`src/tokens.css` from kit `css/wpcy-tokens.css`) |

JSON keeps kit group prefixes (`--wenpai-*`) and scope `.wenpai-app` as the shared source shape. The leaf generator remaps to `--wpcy-*` / `.wpcy-app`.

## Bump (change a color / space / radius)

1. Change the value in **kit** `tokens/tokens.json` (not here first).
2. In kit: `python3 scripts/build-tokens.py --target all` (or `--target wpcy`) and land kit CI.
3. Copy the new kit `tokens/tokens.json` over this file; update the pin table above (SHA / describe).
4. In this repo: `python3 scripts/build-wpcy-tokens.py` (rewrites `src/Admin/app/tokens.css`).
5. Optionally sync `wpcy-admin-ui` `src/tokens.css` from kit `css/wpcy-tokens.css`.
6. Open a leaf PR; CI runs `tokens:check`.

Do **not** hand-edit `src/Admin/app/tokens.css` (GENERATED). Do **not** invent leaf-only values in this JSON without bumping kit first (transitional exception: pin lag only).
