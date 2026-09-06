#!/usr/bin/env python3
"""Pixel-compare same-named PNGs in two directories.

Usage: python3 E/compare.py <before-dir> <after-dir> <diff-dir>
Exit 0 if every page is identical; 1 if any page differs.
"""
from __future__ import annotations

import os
import sys

import numpy as np
from PIL import Image


def load_rgba(path: str) -> np.ndarray:
    img = Image.open(path).convert("RGBA")
    return np.asarray(img)


def main() -> int:
    if len(sys.argv) != 4:
        print("usage: python3 E/compare.py <before-dir> <after-dir> <diff-dir>", file=sys.stderr)
        return 2
    before_dir, after_dir, diff_dir = sys.argv[1], sys.argv[2], sys.argv[3]
    os.makedirs(diff_dir, exist_ok=True)

    before_names = sorted(n for n in os.listdir(before_dir) if n.endswith(".png"))
    after_names = sorted(n for n in os.listdir(after_dir) if n.endswith(".png"))
    if before_names != after_names:
        print("filename mismatch:")
        print("  before:", before_names)
        print("  after:", after_names)
        return 1

    any_diff = False
    for name in before_names:
        a = load_rgba(os.path.join(before_dir, name))
        b = load_rgba(os.path.join(after_dir, name))
        if a.shape != b.shape:
            print(f"{name}: size {a.shape} vs {b.shape}")
            any_diff = True
            h = max(a.shape[0], b.shape[0])
            w = max(a.shape[1], b.shape[1])
            pad_a = np.zeros((h, w, 4), dtype=np.uint8)
            pad_b = np.zeros((h, w, 4), dtype=np.uint8)
            pad_a[: a.shape[0], : a.shape[1]] = a
            pad_b[: b.shape[0], : b.shape[1]] = b
            a, b = pad_a, pad_b
        diff_mask = np.any(a != b, axis=2)
        n_diff = int(diff_mask.sum())
        n_total = int(diff_mask.size)
        pct = (100.0 * n_diff / n_total) if n_total else 0.0
        print(f"{name}: {n_diff} px different ({pct:.4f}%) of {n_total}")
        if n_diff:
            any_diff = True
            vis = np.zeros_like(a)
            vis[..., 0] = 255
            vis[..., 3] = np.where(diff_mask, 255, 0).astype(np.uint8)
            Image.fromarray(vis, "RGBA").save(os.path.join(diff_dir, name))

    return 1 if any_diff else 0


if __name__ == "__main__":
    raise SystemExit(main())
