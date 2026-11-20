# Contributing

## Design dual-stack

Before changing tokens, spacing scales, or shared component semantics, read:

- linuxjoy: `docs/design/wenpai-admin-ui/ADR-dual-stack-design-system-2026-10-24.md`
- linuxjoy: `docs/design/wenpai-admin-ui/DUAL-STACK-REACT-PHP-2026-10-24.md`

Token **source of truth** is the shared design-tokens / wenpai tokens pipeline — not hand-edited values in this repo or in `wenpai-admin-ui`. Keep `--wpcy-*` / `--wenpai-*` numerically aligned via generation.

## Scope of this repo

- React visual / layout primitives for WPCY leaf admin and future React plugin shells.
- Do **not** vendor the full leaf Admin app from `wp-china-yes` until an explicit migrate task.
- Do **not** reimplement the PHP settings kit here.

## PRs

- Prefer small, reviewable commits.
- No force-push to `main`.
- If a change affects both stacks, open linked PRs in this repo and `wenpai-admin-ui` after the token PR lands.
