# Synthetic 3.x `wp_china_yes` fixtures

These files are **synthetic**. They were written by hand for
`tests/Unit/Migration/FixturesTest.php` and are not exports from any real
site. They reproduce the *shape* of 3.x option payloads (CSF radio,
switcher, checkbox and group fields) and the edge cases the 4.0 migration
must handle. All hosts are `example.com` / `example.org` placeholders; there
are no real site URLs, e-mail addresses, credentials or keys.

Each file has two top-level keys:

- `_fixture`: test metadata (`synthetic`, `version`, `scope`, `note`).
  `scope: "network"` installs the payload as a network `site_option`.
- `wp_china_yes`: the 3.x option value exactly as `LegacyReader` would read it.

| File | 3.x | Scope | Covers |
|------|-----|-------|--------|
| `single-3.6.2-01.json` | 3.6.2 | single | `store=off`, 3.x `admincdn=[admin]`, Windfonts and notice blocking off |
| `single-3.8-02.json` | 3.8 | single | `admincdn=[]`, Windfonts on with empty list, empty `notice_control`, telemetry + memory keys |
| `single-3.9.3-03.json` | 3.9.3 | single | `store=wenpai`, keyed all-empty `admincdn_public`, `admincdn_files=""`, three Windfonts rows, **no telemetry keys** |
| `multisite-3.7.1-04.json` | 3.7.1 | network | `admincdn=[admin]`, hide / white-label keys |
| `multisite-3.8-05.json` | 3.8 | network | `admincdn=""`, empty Windfonts list, telemetry keys |
| `multisite-3.8-06.json` | 3.8 | network | `cravatar=weavatar`, `windfonts=""`, performance + memory keys |
| `single-3.9-07-store-proxy.json` | 3.9.x | single | `store=proxy` (maps like `wenpai`), keyed all-empty `admincdn_dev`, telemetry keys |
| `single-3.9-08-admincdn-files-admin.json` | 3.9.x | single | `admincdn_files=[admin]` only (admin static rewrite dropped in 4.0) |

Constraints the expectations in `FixturesTest` rely on:

- `public_assets.items` must map to `[]`, so admincdn lists only carry
  `admin` or empty values (no unknown tokens such as `bootstrapcdn`).
- Kept keys are fixed per file (`expected_kept()`); every other key in
  `wp_china_yes` is expected in the ignored list.
- `adblock_rule` selectors are interception targets; only the three 3.9
  files are on the `scripts/lint-naming-keep.json` list for `promo`, so other
  files must not use banned naming tokens in `selector` / `id` / `class`.

When adding a fixture, keep it synthetic and use placeholder values only.
