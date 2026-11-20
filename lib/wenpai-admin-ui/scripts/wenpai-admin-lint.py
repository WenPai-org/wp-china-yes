#!/usr/bin/env python3
"""Lint a wenpai-admin-ui kit tree (and, with --plugin-root, product admin pages).

0 css/php files → SKIP, never PASS.
0 prototype html / 0 plugin templates → SKIP that subcheck; do not treat the skip as PASS.
"""
from __future__ import annotations

import argparse
import json
import re
import sys
from html.parser import HTMLParser
from pathlib import Path

TEMPLATE_SKIP_PARTS = ("gutenberg", "block-editor", "list-table")

# Raw colors. Only token CSS generated from tokens/tokens.json may hold them.
TOKENS_CSS = "wenpai-tokens.css"
WPCY_TOKENS_CSS = "wpcy-tokens.css"
_COMMENT = re.compile(r"/\*.*?\*/", re.S)
_URL = re.compile(r"url\([^)]*\)", re.I)
_DECL = re.compile(r"([a-z-]+)\s*:\s*([^;{}]+)")
_HEX = re.compile(r"#[0-9a-fA-F]{3,8}\b")
_FUNC = re.compile(r"\b(?:rgba?|hsla?)\(", re.I)
_FALLBACK = re.compile(r"var\(\s*--[a-z0-9-]+\s*,\s*#[0-9a-fA-F]{3,8}\b", re.I)


def raw_colors(text: str) -> list[tuple[int, str, str]]:
    """Return (line, property, value) for declarations that carry a raw color.

    Selectors such as #wpbody-content are ignored: only text inside { } that
    looks like `prop: value` is checked. url(...) payloads (data-URI chevrons)
    and comments are blanked first, keeping line numbers.
    """
    def blank(m: re.Match) -> str:
        return re.sub(r"[^\n]", " ", m.group(0))

    clean = _URL.sub(blank, _COMMENT.sub(blank, text))
    hits: list[tuple[int, str, str]] = []
    depth = 0
    i = 0
    n = len(clean)
    while i < n:
        ch = clean[i]
        if ch == "{":
            depth += 1
        elif ch == "}":
            depth = max(0, depth - 1)
        elif depth > 0:
            m = _DECL.match(clean, i)
            if m and (i == 0 or not (clean[i - 1].isalnum() or clean[i - 1] in "-_")):
                prop, value = m.group(1), m.group(2)
                if not prop.startswith("--") and (_HEX.search(value) or _FUNC.search(value)):
                    line = clean.count("\n", 0, i) + 1
                    hits.append((line, prop, value.strip()))
                i = m.end()
                continue
        i += 1
    return hits


# Rule spacing-token: margin / padding / gap / inset values must use the
# spacing scale (tokens/tokens.json group "space") instead of hand-written px.
SPACING_RULE = "spacing-token"
_SPACING_PROP = re.compile(r"^(?:margin|padding|inset)(?:-[a-z-]+)?$|^(?:row-|column-)?gap$")
_LENGTH = re.compile(r"(?<![\w.#-])(-?)(\d*\.?\d+)(px|rem|em)\b")
_FUNC_CALL = re.compile(r"[a-z-]+\([^()]*\)", re.I)
_TOKEN_LENGTH = re.compile(r"^\s*(\d*\.?\d+)(px|rem)\s*$")
# Same shape as stylelint's own disable comments, with a required `-- reason`.
_DISABLE = re.compile(r"/\*\s*wenpai-lint-disable-(next-line|line)\s+([a-z, -]+?)\s*(?:--\s*(.*?))?\s*\*/")
ROOT_FONT_PX = 16.0  # wp-admin leaves <html> at the browser default; rem is resolvable, em is not.


def _px(num: str, unit: str) -> float:
    return float(num) * (ROOT_FONT_PX if unit == "rem" else 1.0)


def spacing_scale(root: Path) -> tuple[dict[float, str], str]:
    """Map px value → token var name, read from the kit's own token sources.

    The kit repo has tokens/tokens.json. A vendored copy only ships the CSS
    generated from it, so fall back to the `--wenpai-space-*` lines there.
    Returns ({}, "") when neither exists; callers must then SKIP the rule.
    """
    scale: dict[float, str] = {}
    src = root / "tokens" / "tokens.json"
    if src.is_file():
        group = json.loads(src.read_text(encoding="utf-8")).get("groups", {}).get("space", {})
        prefix = group.get("prefix", "--wenpai-space-")
        for key, spec in group.get("tokens", {}).items():
            m = _TOKEN_LENGTH.match(str(spec.get("value", "")))
            if m:
                scale.setdefault(_px(m.group(1), m.group(2)), f"{prefix}{key}")
        return scale, "tokens/tokens.json"
    css = root / "css" / TOKENS_CSS
    if css.is_file():
        for m in re.finditer(r"(--wenpai-space-[\w-]+)\s*:\s*(\d*\.?\d+)(px|rem)\s*;", css.read_text(errors="replace")):
            scale.setdefault(_px(m.group(2), m.group(3)), m.group(1))
        return scale, f"css/{TOKENS_CSS}"
    return scale, ""


def _css_declarations(text: str) -> list[tuple[int, str, str]]:
    """(line, property, value) for every declaration inside { }; comments blanked."""
    def blank(m: re.Match) -> str:
        return re.sub(r"[^\n]", " ", m.group(0))

    clean = _URL.sub(blank, _COMMENT.sub(blank, text))
    out: list[tuple[int, str, str]] = []
    depth = 0
    i = 0
    n = len(clean)
    while i < n:
        ch = clean[i]
        if ch == "{":
            depth += 1
        elif ch == "}":
            depth = max(0, depth - 1)
        elif depth > 0:
            m = _DECL.match(clean, i)
            if m and (i == 0 or not (clean[i - 1].isalnum() or clean[i - 1] in "-_")):
                out.append((clean.count("\n", 0, i) + 1, m.group(1), m.group(2).strip()))
                i = m.end()
                continue
        i += 1
    return out


def _exemptions(text: str, rule: str) -> tuple[set[int], list[int]]:
    """Lines exempted from `rule`, and lines whose exemption has no reason."""
    lines: set[int] = set()
    bare: list[int] = []
    for no, line in enumerate(text.splitlines(), 1):
        for m in _DISABLE.finditer(line):
            rules = {r.strip() for r in m.group(2).split(",")}
            if rule not in rules:
                continue
            target = no + 1 if m.group(1) == "next-line" else no
            if not (m.group(3) or "").strip():
                bare.append(no)
                continue
            lines.add(target)
    return lines, bare


def spacing_hits(text: str, scale: dict[float, str]) -> list[tuple[int, str, str, str]]:
    """(line, property, written, suggestion) for on-scale hand-written spacing.

    Not reported: 0, auto, %, em (depends on the element's font size), values
    off the scale, custom properties, and anything inside a function call
    (calc / var / min / max / clamp): those are computed, not hand-written
    scale steps. Negative on-scale values are reported; the kit spells them
    calc(var(--wenpai-space-N) * -1).
    """
    exempt, bare = _exemptions(text, SPACING_RULE)
    hits: list[tuple[int, str, str, str]] = [(no, "", "", "exemption without a reason") for no in bare]
    for line, prop, value in _css_declarations(text):
        if prop.startswith("--") or not _SPACING_PROP.match(prop) or line in exempt:
            continue
        if value.count(")") > value.count("("):
            continue  # an @supports (gap: …) condition, not a declaration
        flat = value
        while True:
            nxt = _FUNC_CALL.sub(lambda m: " " * len(m.group(0)), flat)
            if nxt == flat:
                break
            flat = nxt
        for m in _LENGTH.finditer(flat):
            if m.group(3) == "em":
                continue
            px = _px(m.group(2), m.group(3))
            name = scale.get(px)
            if not name:
                continue
            use = f"calc(var({name}) * -1)" if m.group(1) else f"var({name})"
            hits.append((line, prop, m.group(0), use))
    return sorted(hits)


def spacing_offscale(text: str, scale: dict[float, str]) -> list[str]:
    """Hand-written px/rem spacing lengths that are not on the scale (report only)."""
    out: list[str] = []
    for _line, prop, value in _css_declarations(text):
        if prop.startswith("--") or not _SPACING_PROP.match(prop) or value.count(")") > value.count("("):
            continue
        flat = value
        while True:
            nxt = _FUNC_CALL.sub(lambda m: " " * len(m.group(0)), flat)
            if nxt == flat:
                break
            flat = nxt
        for m in _LENGTH.finditer(flat):
            if m.group(3) != "em" and _px(m.group(2), m.group(3)) not in scale and float(m.group(2)) != 0:
                out.append(m.group(0))
    return out


def _spacing_finding(rel: str, line: int, prop: str, written: str, use: str, kind: str) -> dict:
    if not prop:
        return {"file": f"{rel}:{line}", "kind": kind, "rule": SPACING_RULE, "why": f"wenpai-lint-disable {SPACING_RULE} needs `-- <reason>`"}
    return {"file": f"{rel}:{line}", "kind": kind, "rule": SPACING_RULE, "why": f"hand-written {written} in {prop}; use {use}", "needle": written}


# Rule inline-style: prototypes/**/*.html must not grow style="…" attributes.
INLINE_RULE = "inline-style"
INLINE_BASELINE = "prototypes/inline-style-baseline.json"
_DATA_WIDTH_PROPS = {"width", "inline-size"}


class _StyleAttrs(HTMLParser):
    """Collect (line, style) of style attributes outside <svg> subtrees."""

    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.hits: list[tuple[int, str]] = []
        self._svg = 0

    def _record(self, attrs: list) -> None:
        for name, value in attrs:
            if name == "style" and value is not None:
                self.hits.append((self.getpos()[0], value))

    def handle_starttag(self, tag: str, attrs: list) -> None:
        if self._svg:
            self._svg += tag == "svg"
            return
        self._record(attrs)
        if tag == "svg":
            self._svg = 1

    def handle_startendtag(self, tag: str, attrs: list) -> None:
        if not self._svg:
            self._record(attrs)

    def handle_endtag(self, tag: str) -> None:
        if tag == "svg" and self._svg:
            self._svg -= 1


def _data_width_only(style: str) -> bool:
    props = [d.split(":", 1)[0].strip().lower() for d in style.split(";") if d.strip()]
    return bool(props) and all(p in _DATA_WIDTH_PROPS for p in props)


def inline_styles(text: str) -> list[int]:
    """Lines of style="…" attributes that are not data widths."""
    parser = _StyleAttrs()
    parser.feed(text)
    parser.close()
    return [line for line, style in parser.hits if not _data_width_only(style)]


def lint_inline_styles(root: Path, htmls: list[Path]) -> tuple[list[dict], list[str]]:
    """Findings for files above their baseline count, plus notes for files below it."""
    base_file = root / INLINE_BASELINE
    baseline: dict[str, int] = {}
    if base_file.is_file():
        baseline = {k: int(v) for k, v in json.loads(base_file.read_text(encoding="utf-8")).get("files", {}).items()}
    findings: list[dict] = []
    notes: list[str] = []
    for f in htmls:
        rel = f.relative_to(root).as_posix()
        lines = inline_styles(f.read_text(errors="replace"))
        allowed = baseline.get(rel, 0)
        if len(lines) > allowed:
            for line in lines:
                findings.append({"file": f"{rel}:{line}", "kind": "HTML", "rule": INLINE_RULE, "why": f"inline style attribute ({len(lines)} in file, baseline {allowed}); use a kit class"})
        elif len(lines) < allowed:
            notes.append(f"{rel}: {len(lines)} inline style(s), baseline {allowed}; lower it in {INLINE_BASELINE}")
    for rel in sorted(set(baseline) - {f.relative_to(root).as_posix() for f in htmls}):
        notes.append(f"{rel}: in {INLINE_BASELINE} but the file is gone; drop the entry")
    return findings, notes


def skip(reason: str) -> int:
    print(f"SKIP {reason}")
    return 0


def lint_kit(root: Path, vendored: bool = False) -> tuple[list[dict], int, int]:
    findings: list[dict] = []
    css_dir = root / "css"
    php_dir = root / "php"
    css_files = sorted(css_dir.glob("*.css")) if css_dir.is_dir() else []
    php_files = sorted(php_dir.glob("*.php")) if php_dir.is_dir() else []

    banned_css = (
        (":root", "token on :root"),
        (".wpcy-", "old wpcy prefix"),
        (".wpsss-", "product selector in kit"),
        (".wpslug-", "product selector in kit"),
        (".hero-w", "leaf hero"),
        (".eco", "leaf eco block"),
    )
    tokens_css = css_dir / TOKENS_CSS
    # A vendored kit older than 0.3 has no tokens file and still carries raw
    # colors; that is the kit's debt, not the plugin's. Only judge copies that
    # ship tokens (or the kit repo itself).
    check_colors = (not vendored) or tokens_css.is_file()
    if css_files and not tokens_css.is_file() and not vendored:
        findings.append({"file": str(tokens_css), "kind": "CSS", "why": "missing generated tokens file (run scripts/build-tokens.py)"})
    for f in css_files:
        text = f.read_text(errors="replace")
        if check_colors and f.name != TOKENS_CSS:
            for line, prop, value in raw_colors(text):
                findings.append({"file": f"{f}:{line}", "kind": "CSS", "why": f"raw color in {prop}; use a --wenpai-* token", "needle": value})
        for needle, why in banned_css:
            # This file is generated from tokens.json for the React stack.
            # Hand-written .wpcy-* elsewhere in the PHP kit remains invalid.
            if needle == ".wpcy-" and f.name == WPCY_TOKENS_CSS:
                continue
            if needle == ".eco":
                if ".eco" in text or "eco-" in text:
                    findings.append({"file": str(f), "kind": "CSS", "why": why})
                continue
            if needle in text:
                findings.append({"file": str(f), "kind": "CSS", "why": why, "needle": needle})

    handle_ok = False
    class_guard = 0
    class_decl = 0
    for f in php_files:
        text = f.read_text(errors="replace")
        if "wenpai-admin-ui" in text and "STYLE_HANDLE" in text:
            handle_ok = True
        if "class Wenpai_Admin_" in text:
            class_decl += 1
            if "class_exists" in text:
                class_guard += 1
        if "function wenpai_admin_ui_register" in text and "function_exists" not in text:
            findings.append({"file": str(f), "kind": "PHP", "why": "register without function_exists"})

    if class_decl and class_guard < class_decl:
        findings.append(
            {
                "file": str(php_dir),
                "kind": "PHP",
                "why": f"class_exists missing ({class_guard}/{class_decl})",
            }
        )
    if php_files and not handle_ok:
        loader = php_dir / "class-wenpai-admin-loader.php"
        if loader.exists() and "wenpai-admin-ui" not in loader.read_text(errors="replace"):
            findings.append({"file": str(loader), "kind": "PHP", "why": "handle not wenpai-admin-ui"})

    shell = php_dir / "class-wenpai-admin-shell.php"
    if shell.is_file():
        shell_text = shell.read_text(errors="replace")
        if "wenpai-main" not in shell_text:
            findings.append({"file": str(shell), "kind": "PHP", "why": "shell missing wenpai-main"})
        if 'class="foot"' not in shell_text and "class='foot'" not in shell_text:
            findings.append({"file": str(shell), "kind": "PHP", "why": "shell missing footer.foot"})
        if "wenpai-foot" in shell_text:
            findings.append({"file": str(shell), "kind": "PHP", "why": "shell uses wenpai-foot not foot"})
        if "wenpai-mode" in shell_text:
            findings.append({"file": str(shell), "kind": "PHP", "why": "shell uses wenpai-mode not mode"})

    js_file = root / "js" / "wenpai-admin.js"
    if js_file.is_file():
        js_text = js_file.read_text(errors="replace")
        if "form.action" in js_text:
            findings.append({"file": str(js_file), "kind": "JS", "why": "js reads form.action"})

    wp_css = css_dir / "wenpai-admin-wp.css"
    if wp_css.is_file():
        wp_text = wp_css.read_text(errors="replace")
        if "max-width: none" not in wp_text and "max-width:none" not in wp_text:
            findings.append({"file": str(wp_css), "kind": "CSS", "why": "overlay missing .card max-width none"})
        if not re.search(r"\.notice \+ \.notice \{\s*margin-top:\s*(12px|var\(--wenpai-space-3\))", wp_text):
            findings.append({"file": str(wp_css), "kind": "CSS", "why": "overlay missing notice sibling 12px"})
        if "overflow-wrap: anywhere" not in wp_text and "overflow-wrap:anywhere" not in wp_text:
            findings.append({"file": str(wp_css), "kind": "CSS", "why": "overlay missing native notice wrap"})
        if "#wpbody-content > .fade" not in wp_text:
            findings.append({"file": str(wp_css), "kind": "CSS", "why": "overlay missing #wpbody-content > .fade"})

    return findings, len(css_files), len(php_files)


def lint_prototypes(root: Path) -> tuple[list[dict], str | None]:
    proto = root / "prototypes"
    if not proto.is_dir():
        return [], "0 prototype html"
    htmls = sorted(p for p in proto.rglob("*.html") if p.is_file())
    if not htmls:
        return [], "0 prototype html"
    findings: list[dict] = []
    for f in htmls:
        text = f.read_text(errors="replace")
        if "wenpai-admin.css" not in text:
            continue
        i_css = text.find("wenpai-admin.css")
        i_wp = text.find("wenpai-admin-wp.css")
        i_tok = text.find(TOKENS_CSS)
        if i_tok < 0:
            findings.append({"file": str(f), "kind": "HTML", "why": "prototype missing wenpai-tokens.css"})
        elif i_tok > i_css:
            findings.append({"file": str(f), "kind": "HTML", "why": "wenpai-tokens.css must load before wenpai-admin.css"})
        if i_wp < 0:
            findings.append({"file": str(f), "kind": "HTML", "why": "prototype missing wenpai-admin-wp.css"})
        elif i_wp < i_css:
            findings.append({"file": str(f), "kind": "HTML", "why": "overlay before surface css"})
        if "wenpai-ui-page" not in text:
            findings.append({"file": str(f), "kind": "HTML", "why": "prototype body missing wenpai-ui-page"})
    return findings, None


def _under_templates(plugin_root: Path, path: Path) -> bool:
    try:
        return path.relative_to(plugin_root).parts[0] == "templates"
    except ValueError:
        return False


def iter_plugin_page_files(plugin_root: Path) -> list[Path]:
    out: list[Path] = []
    templates = plugin_root / "templates"
    if templates.is_dir():
        out.extend(sorted(templates.rglob("*.php")))
        out.extend(sorted(templates.rglob("*.html")))
    includes = plugin_root / "includes"
    if includes.is_dir():
        out.extend(sorted(includes.rglob("*.php")))
    src = plugin_root / "src"
    if src.is_dir():
        out.extend(sorted(src.rglob("*.php")))
    js_dir = plugin_root / "assets" / "js"
    if js_dir.is_dir():
        out.extend(sorted(js_dir.rglob("*.js")))
    cleaned: list[Path] = []
    for path in out:
        posix = path.as_posix()
        if "lib/wenpai-admin-ui" in posix:
            continue
        cleaned.append(path)
    return cleaned


HELPER_LITERALS = (
    'class="card-head"',
    'class="simple-row',
    'class="tile',
    'class="pill',
    'class="wenpai-gate"',
    'class="seg"',
    'class="next"',
    'class="empty"',
)


def lint_plugin_helpers(plugin_root: Path) -> list[dict]:
    """Warn when product templates hand-write kit components (0.3.x warn, 0.4 error)."""
    out: list[dict] = []
    for path in iter_plugin_page_files(plugin_root):
        rel = path.relative_to(plugin_root).as_posix().lower()
        if any(part in rel for part in TEMPLATE_SKIP_PARTS):
            continue
        text = path.read_text(errors="replace")
        rel_print = path.relative_to(plugin_root).as_posix()
        for i, line in enumerate(text.splitlines(), 1):
            for lit in HELPER_LITERALS:
                if lit in line:
                    out.append(
                        {
                            "file": f"{rel_print}:{i}",
                            "kind": "HELPER",
                            "why": "hand-written kit component; use Wenpai_Admin_UI",
                            "needle": lit,
                        }
                    )
                    break
    return out


def lint_plugin_pages(plugin_root: Path) -> tuple[list[dict], str | None]:
    files = iter_plugin_page_files(plugin_root)
    if not files:
        return [], "0 plugin templates/js"
    findings: list[dict] = []
    for path in files:
        rel = path.relative_to(plugin_root).as_posix().lower()
        text = path.read_text(errors="replace")
        kind = "JS" if path.suffix == ".js" else "PHP"
        skip_form = any(part in rel for part in TEMPLATE_SKIP_PARTS)
        in_templates = _under_templates(plugin_root, path)
        kit_page = in_templates or ("wenpai-app" in text) or ("wenpai-main" in text)
        if (not skip_form) and kit_page and "form-table" in text:
            findings.append({"file": str(path), "kind": kind, "why": "kit page uses form-table"})
        if "wpcy-app" in text:
            findings.append({"file": str(path), "kind": kind, "why": "product still uses wpcy-app"})
    return findings, None


import re as _re
PLUGIN_RULES = (
    (_re.compile(r"\badd_menu_page\s*\("), "top-level menu; use Wenpai_Admin_Menu::register"),
    (_re.compile(r"add_\w+_page\s*\([^;]*,\s*8[0-2]\s*\)"), "menu position 80-82 is reserved"),
    (_re.compile(r"admin\.php\?page=|admin_url\(\s*[\x27\"]admin\.php[\x27\"]"), "hardcoded admin.php; use Wenpai_Admin_Menu::url"),
    (_re.compile(r"class=[\x27\"][^\x27\"]*\b(wenpai-top|wenpai-brand)\b|class=[\x27\"]foot[\x27\"]"), "hand-written chrome; use Wenpai_Admin_Shell"),
)


def lint_plugin_vendor_floor(plugin_root: Path) -> list[dict]:
    """Vendored kit older than the floor breaks class loading (complete() glob fix)."""
    ver_file = plugin_root / "lib" / "wenpai-admin-ui" / "VERSION"
    if not ver_file.is_file():
        return []
    ver = ver_file.read_text(errors="replace").strip()
    def vkey(t: str) -> tuple:
        return tuple(int(x) for x in t.split(".") if x.isdigit())
    try:
        ok = vkey(ver) >= vkey(VENDOR_FLOOR)
    except ValueError:
        ok = False
    if ok:
        return []
    return [{"file": str(ver_file.relative_to(plugin_root)), "kind": "RULE", "why": f"vendored kit {ver or '(empty)'} is below floor {VENDOR_FLOOR}; complete() class loading breaks", "needle": ver}]


def lint_plugin_rules(plugin_root):
    findings = []
    used_shell = False
    scanned_php = 0
    for path in sorted(plugin_root.rglob("*.php")):
        posix = path.as_posix()
        if "lib/wenpai-admin-ui" in posix or "/vendor/" in posix or "/node_modules/" in posix:
            continue
        scanned_php += 1
        text = path.read_text(errors="replace")
        if "Wenpai_Admin_Shell::open" in text:
            used_shell = True
        for i, line in enumerate(text.splitlines(), 1):
            for rx, why in PLUGIN_RULES:
                if rx.search(line):
                    findings.append({"file": f"{path.relative_to(plugin_root)}:{i}", "kind": "RULE", "why": why})
    if scanned_php > 0 and not used_shell:
        findings.append({"file": str(plugin_root), "kind": "RULE", "why": "plugin never calls Wenpai_Admin_Shell::open"})
    return findings


CONSUMER_CSS_SKIP = ("lib/wenpai-admin-ui", "/vendor/", "/node_modules/", "/dist/", "/build/", ".min.css")
# Vendored third-party code keeps its own colors; the kit cannot retoken it.
THIRD_PARTY_CSS = ("plugin-update-checker", "/third-party/", "/puc-")
NATIVE_EDITOR_DIRS = {"editor", "gutenberg", "block-editor"}
NATIVE_EDITOR_FILES = {"editor.css", "gutenberg.css", "block-editor.css"}
VENDOR_FLOOR = "0.4.2"


def iter_product_css(plugin_root: Path) -> list[Path]:
    """The product's own admin CSS: no vendored, third-party, scratch or native editor files."""
    out: list[Path] = []
    for path in sorted(plugin_root.rglob("*.css")):
        posix = path.as_posix()
        if any(part in posix for part in CONSUMER_CSS_SKIP):
            continue
        if any(part in posix for part in THIRD_PARTY_CSS):
            continue
        relative = path.relative_to(plugin_root)
        if any(part.startswith(".tmp") for part in relative.parts[:-1]):
            continue
        if path.name in NATIVE_EDITOR_FILES or NATIVE_EDITOR_DIRS.intersection(relative.parts[:-1]):
            continue
        out.append(path)
    return out


def lint_plugin_spacing(plugin_root: Path, scale: dict[float, str]) -> list[dict]:
    """Consumer mode: on-scale hand-written spacing in the product's admin CSS.

    Same file set as lint_plugin_colors. Defaults to WARN (products carry a
    large backlog); --strict-spacing / --strict turn it into FAIL.
    """
    out: list[dict] = []
    for path in iter_product_css(plugin_root):
        rel = path.relative_to(plugin_root).as_posix()
        for line, prop, written, use in spacing_hits(path.read_text(errors="replace"), scale):
            out.append(_spacing_finding(rel, line, prop, written, use, "SPACING"))
    return out


def lint_plugin_colors(plugin_root: Path) -> list[dict]:
    """Consumer mode: raw colors in the product's own admin CSS.

    Native editor assets follow WordPress components, not kit tokens (D-21).
    Other product CSS defaults to WARN; --strict-colors turns it into FAIL.
    Hex inside a var(--wenpai-*, #hex) fallback is reported separately: it
    duplicates a token value and drifts when the token changes.
    """
    out: list[dict] = []
    for path in iter_product_css(plugin_root):
        text = path.read_text(errors="replace")
        rel = path.relative_to(plugin_root).as_posix()
        for line, prop, value in raw_colors(text):
            why = "token fallback duplicates a value" if _FALLBACK.search(value) else f"raw color in {prop}"
            out.append({"file": f"{rel}:{line}", "kind": "COLOR", "why": why, "needle": value})
    return out


TIERS_CANDIDATES = (
    Path(__file__).resolve().parents[2] / "docs" / "design" / "wenpai-admin-ui" / "tiers.yml",
    Path.home() / "Projects" / "linuxjoy" / "docs" / "design" / "wenpai-admin-ui" / "tiers.yml",
)
PAGE_FAILS = {
    "kit page uses form-table",
    "product still uses wpcy-app",
    "B plugin vendored the PHP shell",
    "D plugin loads the shell",
    "C plugin uses wenpai blue",
}
BANNED_COPY_SUBSTR = (
    "配额",
    "遥测",
    "隐私",
    "上报",
    "占位",
    "暂无数据",
    "生产可用",
    "整体上",
    "控制面",
    "SoT",
    "Task Board",
    "joy board",
    "observe-only",
    "版本化责任",
    "本版不做",
    "不负责",
)
BANNED_COPY_RE = (
    (re.compile(r"插件不(?:切|下载|请求|上传|提供|支持|负责|做|改|承担|兼容)"), "capability disclaimer"),
    (re.compile(r"不提供.{0,8}(上传|切分|下载|转换)"), "capability disclaimer"),
)
COPY_I18N_FUNCS = (
    "esc_html__",
    "esc_attr__",
    "esc_html_e",
    "esc_attr_e",
    "__",
    "_e",
    "_x",
    "_ex",
    "_n",
    "_nx",
)
COPY_STRING_RE = re.compile(
    r"(?:" + "|".join(COPY_I18N_FUNCS) + r")\(\s*'((?:[^'\\]|\\.)*)'"
)
COPY_BANNED_FILE = ".wenpai-copy-banned.txt"


def plugin_copy_bans(plugin_root: Path) -> list[str]:
    extra: list[str] = []
    path = plugin_root / COPY_BANNED_FILE
    if not path.is_file():
        return extra
    for line in path.read_text(errors="replace").splitlines():
        text = line.split("#", 1)[0].strip()
        if text:
            extra.append(text)
    return extra


def product_sources(plugin_root: Path) -> list[Path]:
    files = iter_plugin_page_files(plugin_root)
    for path in sorted(plugin_root.glob("*.php")):
        if path not in files:
            files.append(path)
    return files


def lint_copy(plugin_root: Path) -> list[dict]:
    banned = list(BANNED_COPY_SUBSTR) + plugin_copy_bans(plugin_root)
    findings: list[dict] = []
    for path in product_sources(plugin_root):
        if path.suffix != ".php":
            continue
        text = path.read_text(errors="replace")
        for match in COPY_STRING_RE.finditer(text):
            string = match.group(1)
            for needle in banned:
                if needle in string:
                    findings.append(
                        {
                            "file": str(path),
                            "kind": "COPY",
                            "why": f"banned copy: {needle}",
                            "needle": string[:80],
                        }
                    )
                    break
            else:
                for pattern, why in BANNED_COPY_RE:
                    if pattern.search(string):
                        findings.append(
                            {
                                "file": str(path),
                                "kind": "COPY",
                                "why": f"banned copy pattern: {why}",
                                "needle": string[:80],
                            }
                        )
                        break
    return findings


def load_tiers() -> dict[str, str]:
    out: dict[str, str] = {}
    tiers_path = next((p for p in TIERS_CANDIDATES if p.is_file()), None)
    if tiers_path is None:
        return out
    for line in tiers_path.read_text(errors="replace").splitlines():
        text = line.split("#", 1)[0].strip()
        if not text or ":" not in text:
            continue
        name, tier = (part.strip() for part in text.split(":", 1))
        if tier in {"A", "B", "C", "D"}:
            out[name] = tier
    return out


def color_files(plugin_root: Path) -> list[Path]:
    skip_dirs = {"node_modules", "vendor", ".git", "dist"}
    found: list[Path] = []
    for path in plugin_root.rglob("*"):
        if not path.is_file() or path.suffix.lower() not in {".css", ".scss", ".php"}:
            continue
        if skip_dirs.intersection(path.parts):
            continue
        found.append(path)
    return found


def lint_tier(plugin_root: Path, tier: str) -> list[dict]:
    findings: list[dict] = []
    if tier == "C":
        for path in color_files(plugin_root):
            text = path.read_text(errors="replace").lower()
            if "3858e9" in text:
                findings.append({"file": str(path), "kind": "CSS", "why": "C plugin uses wenpai blue"})
        return findings
    if tier == "B":
        shell = plugin_root / "lib" / "wenpai-admin-ui" / "php"
        if shell.is_dir():
            findings.append({"file": str(shell), "kind": "PHP", "why": "B plugin vendored the PHP shell"})
        for path in product_sources(plugin_root):
            text = path.read_text(errors="replace")
            if "Wenpai_Admin_Shell" in text or "wenpai_admin_ui" in text:
                findings.append({"file": str(path), "kind": "PHP" if path.suffix == ".php" else "JS", "why": "B plugin vendored the PHP shell"})
        return findings
    if tier != "D":
        return findings
    needles = ("wenpai-app", "wenpai_admin_ui", "Wenpai_Admin_Shell")
    for path in product_sources(plugin_root):
        text = path.read_text(errors="replace")
        if any(needle in text for needle in needles):
            findings.append({"file": str(path), "kind": "PHP" if path.suffix == ".php" else "JS", "why": "D plugin loads the shell"})
    return findings


def lint_prototype_dir(proto_dir: Path) -> tuple[list[dict], str | None, int]:
    """Lint a standalone prototype HTML directory (without requiring vendored kit)."""
    if not proto_dir.is_dir():
        return [], f"missing directory {proto_dir}", 0
    htmls = sorted(p for p in proto_dir.rglob("*.html") if p.is_file())
    if not htmls:
        return [], "0 prototype html", 0
    findings: list[dict] = []
    for p in htmls:
        t = p.read_text(errors="replace")
        rel = p.relative_to(proto_dir).as_posix()
        if "wenpai-admin.css" in t:
            if TOKENS_CSS not in t:
                findings.append({"file": rel, "kind": "HTML", "why": f"prototype missing {TOKENS_CSS}"})
            elif t.index(TOKENS_CSS) > t.index("wenpai-admin.css"):
                findings.append({"file": rel, "kind": "HTML", "why": f"{TOKENS_CSS} must load before wenpai-admin.css"})
            if "wenpai-admin-wp.css" not in t:
                findings.append({"file": rel, "kind": "HTML", "why": "prototype missing wenpai-admin-wp.css"})
            elif t.index("wenpai-admin-wp.css") < t.index("wenpai-admin.css"):
                findings.append({"file": rel, "kind": "HTML", "why": "overlay before surface css"})
            if "wenpai-ui-page" not in t:
                findings.append({"file": rel, "kind": "HTML", "why": "prototype body missing wenpai-ui-page"})
        for ln in inline_styles(t):
            findings.append({"file": f"{rel}:{ln}", "kind": "HTML", "rule": INLINE_RULE, "why": "inline style attribute in prototype; use a kit class"})
    return findings, None, len(htmls)


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", default="", help="kit repo root")
    ap.add_argument("--plugin-root", default="", help="plugin root; lints lib/wenpai-admin-ui plus templates")
    ap.add_argument("--prototype-dir", default="", help="standalone prototype HTML directory (no vendored kit required)")
    ap.add_argument("--tier", default="", help="A B C or D; default is tiers.yml by directory name")
    ap.add_argument("--json", action="store_true")
    ap.add_argument("--strict-colors", action="store_true", help="with --plugin-root: raw colors in product CSS FAIL instead of WARN")
    ap.add_argument("--strict-spacing", action="store_true", help="with --plugin-root: hand-written on-scale spacing in product CSS FAIL instead of WARN")
    ap.add_argument("--strict", action="store_true", help="with --plugin-root: both --strict-colors and --strict-spacing")
    args = ap.parse_args()

    if args.prototype_dir:
        pdir = Path(args.prototype_dir).resolve()
        p_findings, p_skip, n_html = lint_prototype_dir(pdir)
        if p_skip:
            return skip(p_skip)
        result = {
            "prototype_dir": str(pdir),
            "html": n_html,
            "findings": p_findings,
            "status": "FAIL" if p_findings else "PASS",
        }
        if args.json:
            print(json.dumps(result, ensure_ascii=False, indent=2))
        else:
            print(f"{result['status']} html={n_html} findings={len(p_findings)}")
            for item in p_findings:
                rule = f" [{item['rule']}]" if item.get("rule") else ""
                print(f"  {item['kind']}{rule} {item['file']}: {item['why']}")
        return 1 if p_findings else 0

    plugin_root: Path | None = None
    tier = ""
    if args.plugin_root:
        plugin_root = Path(args.plugin_root).resolve()
        tier = (args.tier or load_tiers().get(plugin_root.name, "A")).upper()
        if tier in {"B", "C", "D"}:
            findings = lint_tier(plugin_root, tier)
            result = {
                "root": str(plugin_root),
                "tier": tier,
                "findings": findings,
                "status": "FAIL" if findings else "PASS",
            }
            if args.json:
                print(json.dumps(result, ensure_ascii=False, indent=2))
            else:
                print(f"{result['status']} tier={tier} findings={len(findings)}")
                for item in findings:
                    print(f"  {item['kind']} {item['file']}: {item['why']}")
            return 1 if findings else 0
        root = plugin_root / "lib" / "wenpai-admin-ui"
        if not root.is_dir():
            print(f"FAIL missing {root}")
            return 1
    elif args.root:
        root = Path(args.root).resolve()
    else:
        print("usage: --root <kit> or --plugin-root <plugin>", file=sys.stderr)
        return 2

    css_dir = root / "css"
    php_dir = root / "php"
    if not css_dir.is_dir() and not php_dir.is_dir():
        return skip("no css/ or php/ under --root")

    findings, n_css, n_php = lint_kit(root, vendored=plugin_root is not None)
    if not n_css and not n_php:
        return skip("0 css/php files")

    scale, scale_src = spacing_scale(root)
    # Kit CSS is judged in the kit repo only. A vendored copy is a released
    # kit; its spacing debt is the kit's, not the plugin's (same as colors).
    spacing_status: str | None = None
    if plugin_root is None:
        kit_css = [f for f in sorted(css_dir.glob("*.css")) if f.name not in (TOKENS_CSS, WPCY_TOKENS_CSS)] if css_dir.is_dir() else []
        if not scale or not kit_css:
            spacing_status = "SKIP"
        else:
            spacing_findings = []
            for f in kit_css:
                rel = f.relative_to(root).as_posix()
                for line, prop, written, use in spacing_hits(f.read_text(errors="replace"), scale):
                    spacing_findings.append(_spacing_finding(rel, line, prop, written, use, "CSS"))
            findings.extend(spacing_findings)
            spacing_status = "FAIL" if spacing_findings else "PASS"

    proto_findings, proto_skip = lint_prototypes(root)
    findings.extend(proto_findings)
    proto_notes: list[str] = []
    if not proto_skip:
        inline_findings, proto_notes = lint_inline_styles(root, sorted(p for p in (root / "prototypes").rglob("*.html") if p.is_file()))
        proto_findings.extend(inline_findings)
        findings.extend(inline_findings)

    strict_spacing = args.strict or args.strict_spacing
    strict_colors = args.strict or args.strict_colors
    plugin_skip: str | None = None
    helper_findings: list[dict] = []
    spacing_product: list[dict] = []
    if plugin_root is not None:
        page_findings, plugin_skip = lint_plugin_pages(plugin_root)
        findings.extend(page_findings)
        findings.extend(lint_copy(plugin_root))
        findings.extend(lint_plugin_vendor_floor(plugin_root))
        findings.extend(lint_plugin_rules(plugin_root))
        color_findings = lint_plugin_colors(plugin_root)
        helper_findings = lint_plugin_helpers(plugin_root)
        if scale:
            spacing_product = lint_plugin_spacing(plugin_root, scale)
    else:
        color_findings = []

    if proto_skip:
        proto_status = "SKIP"
    elif proto_findings:
        proto_status = "FAIL"
    elif (root / "prototypes").is_dir():
        proto_status = "PASS"
    else:
        proto_status = "SKIP"

    if plugin_root is None:
        plugin_status = None
    elif plugin_skip:
        plugin_status = "SKIP"
    elif any(item.get("why") in PAGE_FAILS for item in findings) or any(item.get("kind") == "RULE" for item in findings):
        plugin_status = "FAIL"
    else:
        plugin_status = "PASS"

    result = {
        "root": str(root),
        "css": n_css,
        "php": n_php,
        "findings": findings,
        "status": "FAIL" if findings else "PASS",
        "prototypes": proto_status,
        "plugin_pages": plugin_status,
        "plugin_pages_skip": plugin_skip,
        "prototypes_skip": proto_skip,
        "product_colors": color_findings,
        "product_colors_level": ("FAIL" if strict_colors else "WARN") if plugin_root is not None else None,
        "spacing": spacing_status,
        "spacing_scale": scale_src or None,
        "product_spacing": spacing_product,
        "product_spacing_level": (("FAIL" if strict_spacing else "WARN") if scale else "SKIP") if plugin_root is not None else None,
        "prototype_notes": proto_notes,
    }
    if args.json:
        print(json.dumps(result, ensure_ascii=False, indent=2))
    else:
        extra = f" prototypes={proto_status}"
        if spacing_status is not None:
            extra += f" spacing={spacing_status}"
        if plugin_status is not None:
            extra += f" plugin_pages={plugin_status}"
            if plugin_skip:
                extra += f" ({plugin_skip})"
        print(f"{result['status']} css={n_css} php={n_php} findings={len(findings)}{extra}")
        for item in findings:
            rule = f" [{item['rule']}]" if item.get("rule") else ""
            print(f"  {item['kind']}{rule} {item['file']}: {item['why']}")
        for note in proto_notes:
            print(f"NOTE {INLINE_RULE} {note}")
        if color_findings:
            level = "FAIL" if strict_colors else "WARN"
            print(f"{level} product colors: {len(color_findings)} raw color declaration(s) outside kit tokens")
            for item in color_findings:
                print(f"  {level} {item['file']}: {item['why']}: {item['needle']}")
        if plugin_root is not None and not scale:
            print(f"SKIP product spacing: no spacing scale in {root} (tokens/tokens.json or css/{TOKENS_CSS})")
        if spacing_product:
            level = "FAIL" if strict_spacing else "WARN"
            print(f"{level} product spacing: {len(spacing_product)} hand-written on-scale spacing value(s) [{SPACING_RULE}]")
            for item in spacing_product:
                print(f"  {level} [{SPACING_RULE}] {item['file']}: {item['why']}")
        if helper_findings:
            print(f"WARN hand-written helpers: {len(helper_findings)} (use Wenpai_Admin_UI; error in 0.4)")
            for item in helper_findings:
                print(f"  WARN {item['file']}: {item['why']}: {item['needle']}")
    if strict_colors and color_findings:
        return 1
    if strict_spacing and spacing_product:
        return 1
    return 1 if findings else 0


if __name__ == "__main__":
    sys.exit(main())
