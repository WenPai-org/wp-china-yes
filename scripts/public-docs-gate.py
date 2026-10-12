#!/usr/bin/env python3
"""Thin wrapper: prefer linuxjoy wenpai-public-docs-gate, else local minimal."""

from __future__ import annotations

import runpy
import sys
from pathlib import Path

CWD = Path(__file__).resolve().parents[1]
CANDIDATES = [
    Path.home() / "Projects/linuxjoy/scripts/wenpai/wenpai-public-docs-gate.py",
    Path("/Users/feibisi-studio/Projects/linuxjoy/scripts/wenpai/wenpai-public-docs-gate.py"),
]


def main() -> int:
    for gate in CANDIDATES:
        if gate.is_file():
            sys.argv = [str(gate), "--cwd", str(CWD), *sys.argv[1:]]
            runpy.run_path(str(gate), run_name="__main__")
            return 0
    # Minimal fallback: docs/ may only contain README.md
    docs = CWD / "docs"
    if not docs.exists():
        print("public-docs-gate: PASS (no docs/)")
        return 0
    bad = []
    for p in docs.rglob("*"):
        if p.is_dir():
            continue
        rel = p.relative_to(CWD).as_posix()
        if rel != "docs/README.md":
            bad.append(rel)
    for name in ("APPROVAL.md", "HANDOFF.md", "CLAUDE.md", "MEMORY.md"):
        if (CWD / name).exists():
            bad.append(name)
    if bad:
        print("public-docs-gate: FAIL")
        for b in bad:
            print(f"  {b}")
        return 1
    print("public-docs-gate: PASS")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
