#!/usr/bin/env python3
"""Build a deterministic plugin ZIP from the committed Git tree (fleet scaffold)."""

from __future__ import annotations

import argparse
import hashlib
import re
import subprocess
import zipfile
from pathlib import Path

PLUGIN_SLUG = "wp-china-yes"
MAIN_FILE = "wp-china-yes.php"
RUNTIME_ROOTS = ('assets/', 'templates/', 'Service/', 'client/', 'framework/')
RUNTIME_FILES = {'license', 'readme.txt', 'helpers.php', 'Plugin.php', 'LICENSE', 'wp-china-yes.php', 'copyright.txt'}
FORBIDDEN_PREFIXES = (
    "lib/plugin-update-checker/",
    "lib/updatepulse-updater/",
    "tests/",
    "docs/",
    "scripts/",
    ".forgejo/",
    ".github/",
    "node_modules/",
    "vendor/",
)


def git(*args: str) -> bytes:
    return subprocess.check_output(("git", *args))


def is_runtime_path(name: str) -> bool:
    if name.startswith(FORBIDDEN_PREFIXES):
        return False
    if name in RUNTIME_FILES:
        return True
    return any(name.startswith(p) for p in RUNTIME_ROOTS)


def tracked_runtime_files() -> list[str]:
    names: list[str] = []
    for raw in git("ls-tree", "-rz", "--full-tree", "HEAD").split(b"\0"):
        if not raw:
            continue
        metadata, raw_name = raw.split(b"\t", 1)
        _mode, object_type, _oid = metadata.decode("ascii").split()
        name = raw_name.decode("utf-8")
        if object_type != "blob":
            continue
        if is_runtime_path(name):
            names.append(name)
    if MAIN_FILE not in names:
        raise SystemExit(f"candidate zip missing main file {MAIN_FILE}")
    for name in names:
        if name.startswith(FORBIDDEN_PREFIXES):
            raise SystemExit(f"refusing path in zip: {name}")
    return sorted(names, key=lambda n: n.encode("utf-8"))


def header_version() -> str:
    text = git("show", f"HEAD:{MAIN_FILE}").decode("utf-8")
    m = re.search(r"^ \* Version:\s*(.+)$", text, re.M)
    if not m:
        raise SystemExit(f"no Version header in {MAIN_FILE}")
    return m.group(1).strip()


def build(out: Path) -> str:
    version = header_version()
    names = tracked_runtime_files()
    out.parent.mkdir(parents=True, exist_ok=True)
    if out.exists():
        out.unlink()
    h = hashlib.sha256()
    with zipfile.ZipFile(out, "w", compression=zipfile.ZIP_DEFLATED) as zf:
        for name in names:
            data = git("show", f"HEAD:{name}")
            arc = f"{PLUGIN_SLUG}/{name}"
            zf.writestr(arc, data)
            h.update(data)
            h.update(arc.encode())
    sha_path = out.with_suffix(out.suffix + ".sha256")
    digest = h.hexdigest()
    sha_path.write_text(f"{digest}  {out.name}\n", encoding="utf-8")
    print(out)
    print(sha_path)
    print(f"version={version} files={len(names)} sha256={digest}")
    return digest


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument(
        "-o",
        "--output",
        type=Path,
        default=Path("dist") / f"{PLUGIN_SLUG}-candidate.zip",
    )
    args = ap.parse_args()
    build(args.output)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
