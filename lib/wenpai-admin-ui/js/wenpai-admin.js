/**
 * wenpai-admin-ui kit behaviour. No jQuery. Product pages may no-op
 * Instant toggles use form[data-wenpai-instant] (admin-post Action).
 * Form busy uses .is-busy + aria-busy — never .btn.is-loading (E10).
 */
(function () {
	"use strict";

	var FADE_MS = 5000;

	function inApp(el) {
		return el && el.closest && el.closest(".wenpai-app");
	}

	function reduceMotion() {
		return window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
	}


	function syncChoice(radio) {
		var name = radio.getAttribute("name");
		if (!name) {
			return;
		}
		var root = radio.form || radio.closest(".wenpai-app") || document;
		var nodes = root.querySelectorAll(".choice input[type=\"radio\"]");
		for (var i = 0; i < nodes.length; i++) {
			if (nodes[i].name !== name) {
				continue;
			}
			var lab = nodes[i].closest(".choice");
			if (lab) {
				lab.classList.toggle("is-on", nodes[i].checked);
			}
		}
	}

	function syncGate(gate) {
		var box = gate.querySelector('input[type="checkbox"]');
		if (!box) {
			return;
		}
		var host = gate.closest("form") || gate.closest(".card") || gate.parentElement;
		if (!host) {
			return;
		}
		var apply = host.querySelectorAll("[data-wenpai-need-apply]");
		for (var i = 0; i < apply.length; i++) {
			apply[i].disabled = !box.checked;
		}
	}

	function syncImportFile(card) {
		if (!card) {
			return;
		}
		var file = card.querySelector("input[name=\"wenpai_settings_file\"]");
		var gate = card.querySelector(".wenpai-gate");
		if (!file || !gate) {
			return;
		}
		var box = gate.querySelector("input[type=\"checkbox\"]");
		var hint = gate.querySelector("[data-wenpai-need-file]");
		var has = !!(file.files && file.files.length);
		if (box) {
			box.disabled = !has;
			if (!has) {
				box.checked = false;
			}
		}
		if (hint) {
			if (has) {
				hint.setAttribute("hidden", "");
			} else {
				hint.removeAttribute("hidden");
			}
		}
		syncGate(gate);
	}

	/* ---- view rules: mode + group (0.9) -------------------------------------
	 * data-wenpai-mode="simple|advanced" on an element shows it only in that
	 * mode (?mode=, default simple). data-wenpai-group="id" panels inside a
	 * [data-wenpai-groups] container show only the current ?section= (default:
	 * data-wenpai-groups-default, else the first group in DOM order).
	 * PHP helpers mode_attrs()/group_attrs() print hidden server-side; this
	 * pass marks data-wenpai-view-hidden so depends_on cannot unhide them and
	 * covers markup that ships the attrs without hidden.
	 */
	function queryParam(name) {
		var m = new RegExp("[?&]" + name + "=([^&#]*)").exec(window.location.search || "");
		return m ? decodeURIComponent(m[1].replace(/\+/g, " ")) : "";
	}

	function markView(el, hide) {
		if (hide) {
			el.setAttribute("data-wenpai-view-hidden", "");
		} else {
			el.removeAttribute("data-wenpai-view-hidden");
		}
		syncView(el);
	}

	function syncViewRules(app) {
		var scope = app || document;
		var mode = queryParam("mode") === "advanced" ? "advanced" : "simple";
		var modeEls = scope.querySelectorAll(".wenpai-app [data-wenpai-mode]");
		for (var i = 0; i < modeEls.length; i++) {
			markView(modeEls[i], modeEls[i].getAttribute("data-wenpai-mode") !== mode);
		}
		var containers = scope.querySelectorAll(".wenpai-app [data-wenpai-groups]");
		for (var c = 0; c < containers.length; c++) {
			var box = containers[c];
			var panels = box.querySelectorAll("[data-wenpai-group]");
			var byId = {};
			var first = null;
			for (var g = 0; g < panels.length; g++) {
				var gid = panels[g].getAttribute("data-wenpai-group");
				byId[gid] = panels[g];
				if (!first) {
					first = panels[g];
				}
			}
			if (!first) {
				continue;
			}
			var cur = queryParam("section");
			if (!byId[cur]) {
				var dflt = box.getAttribute("data-wenpai-groups-default") || "";
				cur = byId[dflt] ? dflt : first.getAttribute("data-wenpai-group");
			}
			for (var h = 0; h < panels.length; h++) {
				markView(panels[h], panels[h].getAttribute("data-wenpai-group") !== cur);
			}
		}
	}

	/* ---- depends_on (settings framework 0.3.0) ------------------------------
	 * One condition:  data-wenpai-depends-on="opt[field]" data-wenpai-depends-value="1"
	 *                 (value may be a JSON list = any of) [data-wenpai-depends-not]
	 *                 [data-wenpai-depends-mode="disable"]
	 * Tree:           data-wenpai-depends='{"mode":"hide|disable","relation":"AND|OR",
	 *                 "rules":[{"on":"opt[a]","value":["1"],"not":false,"reason":"…"}, {group}]}'
	 *                 (a bare JSON list = AND, 0.3.0-dev format).
	 * hide:    `hidden` + `inert`; inputs still submit.
	 * disable: controls `disabled`, the reason of the first failing condition is
	 *          written into .wenpai-field__reason; the server keeps the saved value.
	 * exclude: (0.7) hidden like hide AND controls disabled like disable — the
	 *          block submits nothing; the server keeps the saved value.
	 * An element whose trigger control is not on the page is left as PHP printed it.
	 * Without JS nothing is hidden or disabled.
	 */
	var DEP_SEL = "[data-wenpai-depends-on], [data-wenpai-depends]";

	function attrQuote(s) {
		return String(s).replace(/["\\]/g, "\\$&");
	}

	function parseList(raw) {
		if (raw && raw.charAt(0) === "[") {
			try {
				var arr = JSON.parse(raw);
				if (Array.isArray(arr)) {
					return arr.map(String);
				}
			} catch (err) {
				/* a plain value that starts with "[" */
			}
		}
		return [raw === null ? "1" : raw];
	}

	function normNode(n) {
		if (!n || typeof n !== "object") {
			return null;
		}
		if (n.on !== undefined) {
			return {
				on: String(n.on),
				value: (Array.isArray(n.value) ? n.value : [n.value]).map(String),
				not: !!n.not,
				reason: n.reason ? String(n.reason) : "",
				saved: typeof n.saved === "boolean" ? n.saved : undefined
			};
		}
		var rules = Array.isArray(n) ? n : (Array.isArray(n.rules) ? n.rules : []);
		return {
			relation: n.relation === "OR" ? "OR" : "AND",
			rules: rules.map(normNode).filter(Boolean),
			reason: n.reason ? String(n.reason) : ""
		};
	}

	function treeOf(el) {
		if (el._wenpaiTree) {
			return el._wenpaiTree;
		}
		var raw = el.getAttribute("data-wenpai-depends");
		var tree = null;
		if (raw) {
			try {
				var parsed = JSON.parse(raw);
				tree = normNode(parsed);
				tree.mode = !Array.isArray(parsed) && (parsed.mode === "disable" || parsed.mode === "exclude") ? parsed.mode : "hide";
			} catch (err) {
				tree = null;
			}
		} else {
			tree = {
				relation: "AND",
				reason: el.getAttribute("data-wenpai-depends-reason") || "",
				rules: [{
					on: el.getAttribute("data-wenpai-depends-on"),
					value: parseList(el.getAttribute("data-wenpai-depends-value")),
					not: el.hasAttribute("data-wenpai-depends-not"),
					reason: "",
					saved: el.hasAttribute("data-wenpai-depends-saved") ? el.getAttribute("data-wenpai-depends-saved") === "1" : undefined
				}],
				mode: el.getAttribute("data-wenpai-depends-mode") === "disable" || el.getAttribute("data-wenpai-depends-mode") === "exclude" ? el.getAttribute("data-wenpai-depends-mode") : "hide"
			};
		}
		el._wenpaiTree = tree;
		return tree;
	}

	function controlsFor(scope, name) {
		var q = attrQuote(name);
		return scope.querySelectorAll('[name="' + q + '"], [name="' + q + '[]"]');
	}

	/* Current values of a field. Toggle: "1" or "0". Multiselect: the checked values. */
	function valuesOf(nodes) {
		var vals = [];
		var hiddenVals = [];
		var sawChoice = false;
		for (var i = 0; i < nodes.length; i++) {
			var n = nodes[i];
			var type = (n.type || "").toLowerCase();
			if (type === "checkbox" || type === "radio") {
				sawChoice = true;
				if (n.checked) {
					vals.push(n.value);
				}
			} else if (type === "hidden") {
				hiddenVals.push(n.value);
			} else if (n.tagName === "SELECT" && n.multiple) {
				for (var o = 0; o < n.options.length; o++) {
					if (n.options[o].selected) {
						vals.push(n.options[o].value);
					}
				}
			} else {
				vals.push(n.value);
			}
		}
		if (sawChoice && !vals.length) {
			return hiddenVals;
		}
		return vals.length ? vals : hiddenVals;
	}

	/* A trigger that is itself hidden or disabled by depends_on never matches (same as PHP inactive_fields()). */
	function hiddenByDepends(node) {
		return !!(node.closest && node.closest("[data-wenpai-dep-hidden], [data-wenpai-dep-disabled]"));
	}

	/*
	 * A secret trigger with an empty box but a saved value (data-wenpai-saved)
	 * still has a value: the box is always empty on load. Not when the user
	 * ticked 「清除已保存的值」.
	 */
	function savedSecret(node) {
		if (!node.matches || !node.matches("input[data-wenpai-secret]") || node.value !== "" || !node.hasAttribute("data-wenpai-saved")) {
			return false;
		}
		var wrap = node.closest(".wenpai-field");
		var clear = wrap && wrap.querySelector(".wenpai-field__clear input[type=\"checkbox\"]");
		return !(clear && clear.checked);
	}

	/*
	 * Returns {ok: bool, reason: string}, or null when it cannot be decided here.
	 * A trigger whose control is not on the page (another tab) uses `saved`, its
	 * result against saved values, printed by PHP. Without it (a secret trigger)
	 * the condition is undecided and the state PHP printed stays.
	 */
	function evalNode(scope, n) {
		if (n.on !== undefined) {
			var nodes = controlsFor(scope, n.on);
			if (!nodes.length) {
				if (typeof n.saved === "boolean") {
					return { ok: n.saved, reason: n.saved ? "" : n.reason };
				}
				return null;
			}
			if (hiddenByDepends(nodes[0])) {
				/* A hidden or disabled trigger never satisfies a condition, `not` included (same as PHP node_met()). */
				return { ok: false, reason: n.reason };
			}
			var match;
			if (savedSecret(nodes[0])) {
				/* Saved secret: known to be non-empty, its value is not on the page. */
				if (n.value.some(function (v) { return v !== ""; })) {
					return null;
				}
				match = false;
			} else {
				var vals = valuesOf(nodes);
				match = vals.some(function (v) {
					return n.value.indexOf(v) !== -1;
				});
			}
			var ok = n.not ? !match : match;
			return { ok: ok, reason: ok ? "" : n.reason };
		}
		var firstReason = "";
		var any = false;
		var all = true;
		var open = false;
		for (var i = 0; i < n.rules.length; i++) {
			var r = evalNode(scope, n.rules[i]);
			if (r === null) {
				open = true;
				continue;
			}
			if (n.relation === "OR" && r.ok) {
				return { ok: true, reason: "" };
			}
			if (n.relation !== "OR" && !r.ok) {
				return { ok: false, reason: r.reason || n.reason };
			}
			any = any || r.ok;
			all = all && r.ok;
			if (!r.ok && !firstReason) {
				firstReason = r.reason;
			}
		}
		if (open) {
			return null;
		}
		var res = n.relation === "OR" ? any : all;
		return { ok: res, reason: res ? "" : (firstReason || n.reason) };
	}

	function usable(c) {
		return c && c.type !== "hidden" && !c.disabled && !hiddenByDepends(c) && !(c.closest && c.closest("[hidden]"));
	}

	/* Focus inside a block that gets hidden or disabled goes to the control the user changed. */
	function moveFocusOut(el, scope, tree, origin) {
		var active = document.activeElement;
		if (!active || !el.contains(active)) {
			return;
		}
		if (usable(origin)) {
			origin.focus();
			return;
		}
		var first = tree;
		while (first && first.on === undefined && first.rules && first.rules.length) {
			first = first.rules[0];
		}
		if (!first || first.on === undefined) {
			return;
		}
		var back = controlsFor(scope, first.on);
		for (var i = 0; i < back.length; i++) {
			if (usable(back[i])) {
				back[i].focus();
				return;
			}
		}
	}

	/*
	 * Effective visibility = dep-hidden OR view-hidden (0.9: data-wenpai-mode /
	 * data-wenpai-group blocks PHP or boot marked with data-wenpai-view-hidden).
	 * depends_on owns data-wenpai-dep-hidden; view rules own data-wenpai-view-hidden;
	 * hidden/inert reflect the OR so neither side can unhide the other.
	 */
	function syncView(el) {
		var hide = el.hasAttribute("data-wenpai-dep-hidden") || el.hasAttribute("data-wenpai-view-hidden");
		el.hidden = hide;
		if (hide) {
			el.setAttribute("inert", "");
		} else {
			el.removeAttribute("inert");
		}
	}

	function applyHide(el, show, scope, tree, origin) {
		if (show === !el.hasAttribute("data-wenpai-dep-hidden")) {
			return false;
		}
		if (!show) {
			moveFocusOut(el, scope, tree, origin);
			el.setAttribute("data-wenpai-dep-hidden", "");
		} else {
			el.removeAttribute("data-wenpai-dep-hidden");
		}
		syncView(el);
		return true;
	}

	function applyDisable(el, enable, reason, scope, tree, origin) {
		var note = el.querySelector(".wenpai-field__reason");
		if (note) {
			note.textContent = enable ? "" : reason;
			note.hidden = enable || !reason;
		}
		if (enable === !el.hasAttribute("data-wenpai-dep-disabled")) {
			return false;
		}
		var ctl = el.querySelectorAll("input, select, textarea, button");
		if (!enable) {
			moveFocusOut(el, scope, tree, origin);
			/* Hidden companions too (a toggle's "0"): a disabled field submits nothing; the server keeps the stored value. */
			for (var i = 0; i < ctl.length; i++) {
				if (ctl[i].disabled) {
					continue;
				}
				ctl[i].disabled = true;
				ctl[i].setAttribute("data-wenpai-dep-dis", "");
			}
			el.setAttribute("data-wenpai-dep-disabled", "");
			el.classList.add("is-disabled");
		} else {
			for (var j = 0; j < ctl.length; j++) {
				if (ctl[j].hasAttribute("data-wenpai-dep-dis")) {
					ctl[j].disabled = false;
					ctl[j].removeAttribute("data-wenpai-dep-dis");
				}
			}
			el.removeAttribute("data-wenpai-dep-disabled");
			el.classList.remove("is-disabled");
		}
		return true;
	}

	/* origin: the control the user changed; focus goes back to it. */
	function syncDepends(root, origin) {
		var scope = root || document;
		var els = scope.querySelectorAll(".wenpai-app [data-wenpai-depends-on], .wenpai-app [data-wenpai-depends]");
		/* Repeat so chains settle (a hides b, b hides c). */
		for (var pass = 0; pass < 5; pass++) {
			var changed = false;
			for (var i = 0; i < els.length; i++) {
				var el = els[i];
				var tree = treeOf(el);
				if (!tree) {
					continue;
				}
				var form = el.closest("form") || el.closest(".wenpai-app") || document;
				var res = evalNode(form, tree);
				if (res === null) {
					continue;
				}
				var did;
				if (tree.mode === "disable") {
					did = applyDisable(el, res.ok, res.reason || tree.reason, form, tree, origin);
				} else if (tree.mode === "exclude") {
					/* 0.7: hidden like hide, controls disabled like disable — the pane submits nothing. */
					var hid = applyHide(el, res.ok, form, tree, origin);
					var dis = applyDisable(el, res.ok, res.reason || tree.reason, form, tree, origin);
					did = hid || dis;
				} else {
					did = applyHide(el, res.ok, form, tree, origin);
				}
				changed = changed || did;
			}
			if (!changed) {
				break;
			}
		}
	}

	/* ---- field actions (data-wenpai-action) --------------------------------- */

	function submitPairs(scope, name) {
		var out = [];
		var nodes = controlsFor(scope, name);
		for (var i = 0; i < nodes.length; i++) {
			var n = nodes[i];
			var type = (n.type || "").toLowerCase();
			if ((type === "checkbox" || type === "radio") && !n.checked) {
				continue;
			}
			if (n.tagName === "SELECT" && n.multiple) {
				for (var o = 0; o < n.options.length; o++) {
					if (n.options[o].selected) {
						out.push([n.name, n.options[o].value]);
					}
				}
				continue;
			}
			out.push([n.name, n.value]);
		}
		return out;
	}

	function jsonAttr(el, name, fallback) {
		var raw = el.getAttribute(name);
		if (!raw) {
			return fallback;
		}
		try {
			return JSON.parse(raw);
		} catch (err) {
			return fallback;
		}
	}

	/* POST to admin-ajax. Resolves {ok, message, data, code}. Times out after 15 s. */
	function request(url, action, nonce, pairs, extra) {
		var fd = new FormData();
		fd.append("action", action);
		fd.append("_wpnonce", nonce || "");
		(pairs || []).forEach(function (p) {
			fd.append(p[0], p[1]);
		});
		Object.keys(extra || {}).forEach(function (k) {
			fd.append(k, extra[k]);
		});
		var ctrl = window.AbortController ? new window.AbortController() : null;
		var timer = window.setTimeout(function () {
			if (ctrl) {
				ctrl.abort();
			}
		}, 15000);
		return window.fetch(url || window.ajaxurl, {
			method: "POST",
			body: fd,
			credentials: "same-origin",
			signal: ctrl ? ctrl.signal : undefined
		}).then(function (r) {
			return r.json();
		}).then(function (j) {
			window.clearTimeout(timer);
			var d = (j && j.data) || {};
			return { ok: !!(j && j.success && d.ok !== false), message: d.message || "", data: d.data === undefined ? null : d.data, code: d.code ? String(d.code) : "" };
		}, function () {
			window.clearTimeout(timer);
			return { ok: false, message: "", data: null, code: "" };
		});
	}

	function actionScope(btn) {
		return btn.closest("form") || btn.closest(".wenpai-app") || document;
	}

	function syncActionReady(btn) {
		if (btn.getAttribute("aria-busy") === "true") {
			return;
		}
		var needs = jsonAttr(btn, "data-wenpai-requires", []);
		var scope = actionScope(btn);
		var missing = false;
		needs.forEach(function (name) {
			var nodes = controlsFor(scope, name);
			var have = false;
			for (var i = 0; i < nodes.length; i++) {
				if (nodes[i].type !== "hidden" && (nodes[i].value !== "" || nodes[i].hasAttribute("data-wenpai-saved"))) {
					have = true;
				}
			}
			missing = missing || !have;
		});
		btn.disabled = missing || btn.closest("[data-wenpai-dep-disabled]") !== null;
		var res = document.getElementById(btn.getAttribute("data-wenpai-result"));
		if (res && needs.length) {
			var note = btn.getAttribute("data-wenpai-requires-note") || "";
			if (missing && !res.getAttribute("data-wenpai-filled")) {
				res.className = "wenpai-field__result is-note";
				res.textContent = note;
			} else if (!missing && res.classList.contains("is-note")) {
				res.className = "wenpai-field__result";
				res.textContent = "";
			}
		}
	}

	/* The WP_Error code is never shown; it is on data-wenpai-code and in the wenpai:action event. */
	function setResult(res, ok, message, code) {
		res.setAttribute("role", ok ? "status" : "alert");
		res.className = "wenpai-field__result " + (ok ? "is-ok" : "is-bad");
		res.textContent = message;
		res.setAttribute("data-wenpai-filled", "1");
		if (code) {
			res.setAttribute("data-wenpai-code", code);
		} else {
			res.removeAttribute("data-wenpai-code");
		}
	}

	function clearResultsFor(control) {
		var name = control.name;
		if (!name) {
			return;
		}
		var scope = actionScope(control);
		var btns = scope.querySelectorAll("[data-wenpai-action]");
		for (var i = 0; i < btns.length; i++) {
			var inc = jsonAttr(btns[i], "data-wenpai-include", []);
			var base = name.replace(/\[\]$/, "");
			if (inc.indexOf(base) === -1) {
				continue;
			}
			var res = document.getElementById(btns[i].getAttribute("data-wenpai-result"));
			if (res && res.getAttribute("data-wenpai-filled")) {
				res.textContent = "";
				res.className = "wenpai-field__result";
				res.removeAttribute("data-wenpai-filled");
				res.removeAttribute("data-wenpai-code");
				res.setAttribute("role", "status");
			}
			syncActionReady(btns[i]);
		}
	}

	function runAction(btn) {
		if (btn.disabled || btn.getAttribute("aria-busy") === "true") {
			return;
		}
		var scope = actionScope(btn);
		var pairs = [];
		jsonAttr(btn, "data-wenpai-include", []).forEach(function (name) {
			pairs = pairs.concat(submitPairs(scope, name));
		});
		var res = document.getElementById(btn.getAttribute("data-wenpai-result"));
		var label = btn.innerHTML;
		btn.disabled = true;
		btn.setAttribute("aria-busy", "true");
		/* data-wenpai-busy is the in-progress copy without 「…」 (e.g. 正在测试); the kit appends it. */
		btn.textContent = (btn.getAttribute("data-wenpai-busy") || "") + "…";
		var spin = document.createElement("span");
		spin.className = "spin";
		spin.setAttribute("aria-hidden", "true");
		btn.appendChild(spin);
		return request(btn.getAttribute("data-wenpai-url"), btn.getAttribute("data-wenpai-action"), btn.getAttribute("data-wenpai-nonce"), pairs, jsonAttr(btn, "data-wenpai-data", {})).then(function (r) {
			if (res) {
				setResult(res, r.ok, r.message || btn.getAttribute(r.ok ? "data-wenpai-ok" : "data-wenpai-fail") || "", r.code);
			}
			btn.innerHTML = label;
			btn.removeAttribute("aria-busy");
			btn.disabled = false;
			syncActionReady(btn);
			var ev;
			var detail = { action: btn.getAttribute("data-wenpai-action"), ok: r.ok, message: r.message, code: r.code, data: r.data };
			if (typeof window.CustomEvent === "function") {
				ev = new window.CustomEvent("wenpai:action", { bubbles: true, detail: detail });
				btn.dispatchEvent(ev);
			}
			return r;
		});
	}

	/* ---- secret: a typed new value wins over "clear" ------------------------- */

	function syncSecretClear(input) {
		var wrap = input.closest(".wenpai-field");
		var clear = wrap && wrap.querySelector(".wenpai-field__clear input[type=\"checkbox\"]");
		if (!clear) {
			return;
		}
		var typed = input.value !== "";
		if (typed) {
			clear.checked = false;
		}
		clear.disabled = typed || input.disabled;
	}

	/* ---- secret: show / hide (P2b Field extension · Track B / E09) ------------ */

	function syncSecretReveal(btn) {
		var id = btn.getAttribute("aria-controls");
		var input = id ? document.getElementById(id) : (btn.closest(".wenpai-field__secret") || btn.parentNode).querySelector("input[data-wenpai-secret]");
		if (!input) {
			return;
		}
		var show = btn.getAttribute("data-wenpai-reveal-show") || "显示";
		var hide = btn.getAttribute("data-wenpai-reveal-hide") || "隐藏";
		var on = btn.getAttribute("aria-pressed") === "true";
		var next = !on;
		input.type = next ? "text" : "password";
		btn.setAttribute("aria-pressed", next ? "true" : "false");
		btn.setAttribute("aria-label", next ? hide : show);
		btn.textContent = next ? hide : show;
	}

	/*
	 * A password input drops line breaks, so a pasted PEM would be saved folded
	 * into one line. Refuse the paste and flag it; the server answers with the
	 * secret_multiline field error and keeps the stored value.
	 */
	function multilineFlag(input) {
		var wrap = input.closest(".wenpai-field") || input.parentNode;
		return wrap.querySelector("input[data-wenpai-multiline-flag]");
	}

	function onSecretPaste(e) {
		var input = e.target;
		if (!input.matches || !input.matches("input[data-wenpai-secret]")) {
			return;
		}
		var text = (e.clipboardData || window.clipboardData) ? (e.clipboardData || window.clipboardData).getData("text") : "";
		if (!/[\r\n]/.test(String(text).trim())) {
			return;
		}
		e.preventDefault();
		input.value = "";
		syncSecretClear(input);
		var wrap = input.closest(".wenpai-field") || input.parentNode;
		if (!multilineFlag(input)) {
			var flag = document.createElement("input");
			flag.type = "hidden";
			// Nested names (opt[a][b][c]) must flag at option root, not the leaf key.
			flag.name = input.name.replace(/\[.*$/, "[__wenpai_multiline][]");
			flag.value = input.getAttribute("data-wenpai-secret");
			flag.setAttribute("data-wenpai-multiline-flag", "");
			input.parentNode.insertBefore(flag, input.nextSibling);
		}
		var msgId = input.id + "-multiline";
		var msg = document.getElementById(msgId);
		if (!msg) {
			msg = document.createElement("p");
			msg.className = "wenpai-field__error";
			msg.id = msgId;
			msg.setAttribute("role", "alert");
			var after = wrap.querySelector(".wenpai-field__row") || input;
			after.parentNode.insertBefore(msg, after.nextSibling);
			input.setAttribute("aria-describedby", (msgId + " " + (input.getAttribute("aria-describedby") || "")).trim());
		}
		msg.textContent = input.getAttribute("data-wenpai-multiline") || "";
		input.setAttribute("aria-invalid", "true");
	}

	function clearMultiline(input) {
		var flag = multilineFlag(input);
		if (!flag || input.value === "") {
			return;
		}
		flag.parentNode.removeChild(flag);
		var msg = document.getElementById(input.id + "-multiline");
		if (msg) {
			msg.parentNode.removeChild(msg);
		}
		input.setAttribute("aria-describedby", (input.getAttribute("aria-describedby") || "").replace(input.id + "-multiline", "").trim());
		if (!input.closest(".is-invalid")) {
			input.removeAttribute("aria-invalid");
		}
	}

	document.addEventListener("paste", onSecretPaste);

	/* ---- notices ------------------------------------------------------------ */

	/*
	 * One polite live region per page, inserted empty at boot; later only its text changes
	 * (a region added at the moment of the change can be missed, e.g. VoiceOver + Safari).
	 * Save receipts and P-7 dismissals both speak through it.
	 */
	var liveTimer = null;
	function liveRegion() {
		var live = document.querySelector("[data-wenpai-live]");
		if (!live) {
			live = document.createElement("div");
			live.className = "screen-reader-text";
			live.setAttribute("role", "status");
			live.setAttribute("aria-live", "polite");
			live.setAttribute("aria-atomic", "true");
			live.setAttribute("data-wenpai-live", "");
			(document.querySelector(".wenpai-app") || document.body).appendChild(live);
		}
		return live;
	}

	/* Replace the region's text after a short delay; a newer message cancels a pending one. */
	function announceLive(text, delay) {
		var live = liveRegion();
		if (liveTimer) {
			window.clearTimeout(liveTimer);
		}
		live.textContent = "";
		liveTimer = window.setTimeout(function () {
			liveTimer = null;
			live.textContent = text;
		}, delay || 100);
	}

	/*
	 * A receipt that is already in the page at load (data-wenpai-announce) is not read by
	 * screen readers by itself: hand its text to the shared live region once, and drop the
	 * receipt's own role so the page has a single live region for it (no double reading).
	 */
	function announce(root) {
		var nodes = document.querySelectorAll("[data-wenpai-announce]");
		var said = [];
		for (var i = 0; i < nodes.length; i++) {
			if (nodes[i]._wenpaiAnnounced) {
				continue;
			}
			nodes[i]._wenpaiAnnounced = true;
			var text = (nodes[i].textContent || "").replace(/\s+/g, " ").trim();
			if (text) {
				said.push(text);
			}
			nodes[i].removeAttribute("role");
		}
		if (said.length) {
			announceLive(said.join(" "), 500);
		}
	}

	/* Fade after 5 s; hover or focus inside pauses; then collapse without animation. */
	function scheduleFade(el) {
		if (el._wenpaiFade) {
			return;
		}
		el._wenpaiFade = true;
		var timer = null;
		function start() {
			window.clearTimeout(timer);
			timer = window.setTimeout(function () {
				el.classList.add("is-gone");
				window.setTimeout(function () {
					if (el.classList.contains("is-gone")) {
						el.hidden = true;
						/* Collapse an emptied kit wrapper too (it stays behind when core moves the notice). */
						var wrap = el.parentNode;
						if (wrap && wrap.classList && wrap.classList.contains("wenpai-notice-wrap") && !wrap.querySelector(".notice:not([hidden])")) {
							wrap.hidden = true;
						}
					}
				}, 450);
			}, FADE_MS);
		}
		function pause() {
			window.clearTimeout(timer);
			el.classList.remove("is-gone");
		}
		el.addEventListener("mouseenter", pause);
		el.addEventListener("focusin", pause);
		el.addEventListener("mouseleave", function () {
			if (!el.contains(document.activeElement)) {
				start();
			}
		});
		el.addEventListener("focusout", function (e) {
			if (!el.contains(e.relatedTarget) && !el.matches(":hover")) {
				start();
			}
		});
		start();
	}

	var NOTICE_ICON = '<svg class="wenpai-ico" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22ZM12 20C16.4183 20 20 16.4183 20 12C20 7.58172 16.4183 4 12 4C7.58172 4 4 7.58172 4 12C4 16.4183 7.58172 20 12 20ZM11 7H13V9H11V7ZM11 11H13V17H11V11Z"/></svg>';

	/*
	 * Runtime notice, same markup as Wenpai_Admin_UI::notice_html(): text only (textContent),
	 * spoken through the shared live region, faded like a boot-time notice when asked.
	 * opts: level info|ok|warn|bad, text, fade, container (element or selector; default the
	 * shell main, else the core .wrap). Outside .wenpai-app the wrap carries .wenpai-native
	 * for the tokens. Returns the .notice element.
	 */
	function notice(opts) {
		opts = opts || {};
		var level = ["info", "ok", "warn", "bad"].indexOf(opts.level) >= 0 ? opts.level : "info";
		var el = document.createElement("div");
		el.className = "notice " + level;
		el.innerHTML = NOTICE_ICON;
		var span = document.createElement("span");
		span.textContent = opts.text == null ? "" : String(opts.text);
		el.appendChild(span);
		var wrap = document.createElement("div");
		wrap.className = "wenpai-notice-wrap";
		wrap.appendChild(el);
		var box = opts.container;
		if (typeof box === "string") {
			box = document.querySelector(box);
		}
		if (!box) {
			box = document.querySelector(".wenpai-app > .wenpai-main") || document.querySelector("#wpbody-content .wrap") || document.body;
		}
		if (!box.closest || (!box.closest(".wenpai-app") && !box.closest(".wenpai-native"))) {
			wrap.className = "wenpai-native wenpai-notice-wrap";
		}
		box.insertBefore(wrap, box.firstChild);
		if (span.textContent) {
			announceLive(span.textContent);
		}
		if (opts.fade) {
			el.setAttribute("data-wenpai-fade", "");
			if (!reduceMotion()) {
				scheduleFade(el);
			}
		}
		return el;
	}

	function relocateTopNotices() {
		if (!document.body || !document.body.classList.contains("wenpai-ui-page")) {
			return;
		}
		var main = document.querySelector(".wenpai-app > .wenpai-main");
		var content = document.getElementById("wpbody-content");
		if (!main || !content) {
			return;
		}
		var moved = [];
		var kids = content.children;
		for (var i = 0; i < kids.length; i++) {
			var el = kids[i];
			if (!el.classList) {
				continue;
			}
			/* Core version nag stays out of the product shell. */
			if (el.classList.contains("update-nag")) {
				continue;
			}
			if (
				el.classList.contains("notice") ||
				el.classList.contains("updated") ||
				el.classList.contains("error") ||
				el.classList.contains("fade")
			) {
				moved.push(el);
			}
		}
		for (var j = 0; j < moved.length; j++) {
			main.insertBefore(moved[j], main.firstChild);
		}
	}

	function boot(root) {
		repeaterBoot(root);

		relocateTopNotices();
		var app = root || document;
		var radios = app.querySelectorAll(".wenpai-app .choice input[type=\"radio\"]");
		for (var i = 0; i < radios.length; i++) {
			syncChoice(radios[i]);
		}
		syncViewRules(app);
		syncDepends(app);
		var btns = app.querySelectorAll(".wenpai-app [data-wenpai-action]");
		for (var b = 0; b < btns.length; b++) {
			btns[b].hidden = false;
			syncActionReady(btns[b]);
		}
		var secrets = app.querySelectorAll(".wenpai-app .wenpai-field input[type=\"password\"]");
		for (var s = 0; s < secrets.length; s++) {
			syncSecretClear(secrets[s]);
		}
		liveRegion();
		announce(app);
		var gates = app.querySelectorAll(".wenpai-app .wenpai-gate");
		for (var g = 0; g < gates.length; g++) {
			syncGate(gates[g]);
		}
		var imports = app.querySelectorAll(".wenpai-app #wenpai-tools-import");
		for (var im = 0; im < imports.length; im++) {
			syncImportFile(imports[im]);
		}
		if (reduceMotion()) {
			return;
		}
		/*
		 * The fading element is the notice itself, looked up in the whole
		 * document: on a plain .wrap page core common.js moves notices out of
		 * their container (after the h1), so a container-scoped lookup misses it.
		 */
		var fades = document.querySelectorAll(".notice[data-wenpai-fade], .wenpai-notice-wrap[data-wenpai-fade]");
		for (var w = 0; w < fades.length; w++) {
			scheduleFade(fades[w]);
		}
	}

	/* First focusable element after box in document order, outside every skipped box. */
	function focusableAfter(box, skip) {
		var all = document.querySelectorAll("a[href], button:not([disabled]), input:not([disabled]):not([type=\"hidden\"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex=\"-1\"])");
		for (var i = 0; i < all.length; i++) {
			var el = all[i];
			if (!(box.compareDocumentPosition(el) & 4)) {
				continue;
			}
			var inside = false;
			for (var k = 0; k < skip.length; k++) {
				if (skip[k].contains(el)) {
					inside = true;
				}
			}
			if (!inside && el.getClientRects().length && !el.closest("[hidden], [inert]")) {
				return el;
			}
		}
		return null;
	}

	/*
	 * P-7 「不再提示」: the link is the no-JS path (admin-post + nonce, redirects back).
	 * With JS, post it with wenpai_js=1, remove every referral of that slot and move
	 * focus to the next focusable element. Any failure falls back to the link.
	 */
	function dismissReferral(a) {
		if (a.getAttribute("aria-busy") === "true") {
			return;
		}
		a.setAttribute("aria-busy", "true");
		var href = a.href;
		var said = a.getAttribute("data-wenpai-dismissed") || "";
		var fallback = function () {
			window.location.href = href;
		};
		var fd = new FormData();
		fd.append("wenpai_js", "1");
		window.fetch(href, { method: "POST", body: fd, credentials: "same-origin" }).then(function (r) {
			return r.json();
		}).then(function (j) {
			if (!j || !j.success) {
				fallback();
				return;
			}
			var box = a.closest("[data-wenpai-referral]") || a;
			var slot = box.getAttribute("data-wenpai-referral");
			var boxes = [box];
			if (slot) {
				boxes = Array.prototype.slice.call(document.querySelectorAll("[data-wenpai-referral]")).filter(function (b) {
					return b.getAttribute("data-wenpai-referral") === slot;
				});
			}
			var next = focusableAfter(box, boxes);
			var holder = box.parentNode;
			boxes.forEach(function (b) {
				b.parentNode.removeChild(b);
			});
			if (!next && holder) {
				holder.setAttribute("tabindex", "-1");
				next = holder;
			}
			if (next) {
				next.focus();
			}
			if (said) {
				announceLive(said);
			}
		}, fallback);
	}

	document.addEventListener("click", function (e) {
		var reveal = e.target.closest && e.target.closest(".wenpai-app [data-wenpai-reveal]");
		if (reveal) {
			e.preventDefault();
			syncSecretReveal(reveal);
			return;
		}
		var dis = e.target.closest && e.target.closest(".wenpai-app a[data-wenpai-dismiss]");
		if (dis && window.fetch && window.FormData) {
			e.preventDefault();
			dismissReferral(dis);
			return;
		}
		var act = e.target.closest && e.target.closest(".wenpai-app [data-wenpai-action]");
		if (act) {
			e.preventDefault();
			runAction(act);
			return;
		}
		/* Error summary links: land focus on the control, not the wrapper. */
		var link = e.target.closest && e.target.closest(".wenpai-app .wenpai-error-links a[href^=\"#\"]");
		if (link) {
			var target = document.getElementById(link.getAttribute("href").slice(1));
			if (target) {
				var ctl = target.matches("input, select, textarea") ? target : target.querySelector("input:not([type=\"hidden\"]), select, textarea");
				if (ctl && !ctl.disabled) {
					e.preventDefault();
					target.scrollIntoView({ block: "center" });
					ctl.focus();
				}
			}
			return;
		}
	});

	function onEdit(e) {
		var t = e.target;
		if (!inApp(t) || !t.name) {
			return;
		}
		if (t.matches && t.matches("input[data-wenpai-secret]") && t.closest(".wenpai-field")) {
			syncSecretClear(t);
			clearMultiline(t);
		}
		clearResultsFor(t);
	}

	document.addEventListener("input", onEdit);

	document.addEventListener("change", function (e) {
		var t = e.target;
		if (!inApp(t)) {
			return;
		}
		onEdit(e);
		if (t.name && document.querySelector(DEP_SEL)) {
			syncDepends(document, t);
		}
		if (t.matches(".choice input[type=\"radio\"]")) {
			syncChoice(t);
		}
		if (t.matches(".wenpai-gate input[type=\"checkbox\"]")) {
			var gate = t.closest(".wenpai-gate");
			if (gate) {
				syncGate(gate);
			}
		}
		if (t.name === "wenpai_settings_file") {
			var card = t.closest("#wenpai-tools-import") || t.closest(".card");
			var g2 = card && card.querySelector(".wenpai-gate");
			var box = g2 && g2.querySelector("input[type=\"checkbox\"]");
			if (box) {
				box.checked = false;
			}
			syncImportFile(card);
		}
	});

	/* Track C / E06: instant toggle — change submits the Action form (admin-post). */
	document.addEventListener("change", function (e) {
		var t = e.target;
		if (!t || !t.matches || !t.matches(".wenpai-app form[data-wenpai-instant] .wenpai-switch__input")) {
			return;
		}
		var form = t.closest("form[data-wenpai-instant]");
		if (!form || form.getAttribute("aria-busy") === "true") {
			return;
		}
		form.classList.add("is-busy");
		form.setAttribute("aria-busy", "true");
		t.setAttribute("aria-busy", "true");
		t.disabled = true;
		if (typeof form.requestSubmit === "function") {
			form.requestSubmit();
		} else {
			form.submit();
		}
	});

	document.addEventListener("submit", function (e) {
		var form = e.target;
		if (!inApp(form) || !form.closest) {
			return;
		}
		/* Track C / E10: settings / Action forms — .is-busy + aria-busy, never .btn.is-loading. */
		if (form.matches("form[data-wenpai-instant], form.wenpai-field__action-form") ||
			(form.getAttribute("action") && form.getAttribute("action").indexOf("options.php") !== -1)) {
			if (form.getAttribute("aria-busy") === "true" && !form.matches("form[data-wenpai-instant]")) {
				e.preventDefault();
				return;
			}
			form.classList.add("is-busy");
			form.setAttribute("aria-busy", "true");
			var btn = form.querySelector("button[type=\"submit\"]");
			if (btn) {
				btn.disabled = true;
				btn.setAttribute("aria-busy", "true");
				var busy = btn.getAttribute("data-wenpai-busy");
				if (busy) {
					btn.textContent = busy.indexOf("…") >= 0 || busy.indexOf("...") >= 0 ? busy : busy + "…";
				}
			}
		}
		var exp = form.closest("#wenpai-tools-export");
		if (exp) {
			var expBtn = form.querySelector("button[type=\"submit\"]");
			if (expBtn && expBtn.getAttribute("aria-busy") === "true") {
				e.preventDefault();
				return;
			}
			if (expBtn) {
				expBtn.setAttribute("aria-busy", "true");
				window.setTimeout(function () {
					expBtn.removeAttribute("aria-busy");
				}, 2000);
			}
			return;
		}
		var rst = form.closest("#wenpai-tools-reset");
		if (!rst) {
			return;
		}
		var rstBtn = form.querySelector("button[type=\"submit\"]");
		if (!rstBtn) {
			return;
		}
		rstBtn.setAttribute("aria-busy", "true");
		var busy = rstBtn.getAttribute("data-wenpai-busy");
		if (busy) {
			rstBtn.textContent = busy;
		}
	});


	/* ---- repeater (Track D / E08) -------------------------------------------
	 * Add / remove / reorder via <template>; renumber legends and names.
	 * No-JS: server already outputs one empty row so each save can add one.
	 */
	function repeaterRenumber(root) {
		var rows = root.querySelectorAll("[data-wenpai-repeater-row]");
		var itemTpl = root.getAttribute("data-wenpai-item-tpl") || "";
		var delTpl = root.getAttribute("data-wenpai-delete-tpl") || "";
		var base = root.getAttribute("data-wenpai-repeater-name") || "";
		var max = parseInt(root.getAttribute("data-wenpai-repeater-max") || "20", 10) || 20;
		for (var i = 0; i < rows.length; i++) {
			var row = rows[i];
			var n = i + 1;
			row.setAttribute("data-wenpai-repeater-index", String(i));
			var leg = row.querySelector(".wenpai-repeater__legend");
			var title = row.querySelector(".card-title");
			var label = itemTpl ? itemTpl.replace("%d", String(n)) : ("第 " + n + " 项");
			if (leg) leg.textContent = label;
			if (title) title.textContent = label;
			var rem = row.querySelector("[data-wenpai-repeater-remove]");
			if (rem) {
				rem.textContent = delTpl ? delTpl.replace("%d", String(n)) : ("删除第 " + n + " 项");
			}
			var controls = row.querySelectorAll("input[name], select[name], textarea[name]");
			for (var c = 0; c < controls.length; c++) {
				var nm = controls[c].getAttribute("name") || "";
				if (!base || nm.indexOf(base + "[") !== 0) continue;
				controls[c].setAttribute(
					"name",
					nm.replace(new RegExp("^" + base.replace(/[.*+?^${}()|[\]\\]/g, "\\$&") + "\\[[^\\]]+\\]"), base + "[" + i + "]")
				);
			}
			var ids = row.querySelectorAll("[id]");
			for (var d = 0; d < ids.length; d++) {
				var id = ids[d].id || "";
				ids[d].id = id.replace(/-r-\d+-/, "-r-" + i + "-").replace(/-\d+-(?=[a-z0-9_]+$)/i, "-" + i + "-");
			}
			var labs = row.querySelectorAll("label[for]");
			for (var l = 0; l < labs.length; l++) {
				var f = labs[l].getAttribute("for") || "";
				labs[l].setAttribute("for", f.replace(/-r-\d+-/, "-r-" + i + "-").replace(/-\d+-(?=[a-z0-9_]+$)/i, "-" + i + "-"));
			}
			var ctrls = row.querySelectorAll("[aria-controls]");
			for (var ac = 0; ac < ctrls.length; ac++) {
				var target = ctrls[ac].getAttribute("aria-controls") || "";
				ctrls[ac].setAttribute("aria-controls", target.replace(/-r-\d+-/, "-r-" + i + "-").replace(/-\d+-(?=[a-z0-9_]+$)/i, "-" + i + "-"));
			}
		}
		var add = root.querySelector("[data-wenpai-repeater-add]");
		if (add) add.disabled = rows.length >= max;
	}

	function repeaterBoot(scope) {
		var roots = (scope || document).querySelectorAll(".wenpai-app [data-wenpai-repeater]");
		for (var i = 0; i < roots.length; i++) {
			var root = roots[i];
			if (root.getAttribute("data-wenpai-repeater-ready")) continue;
			root.setAttribute("data-wenpai-repeater-ready", "1");
			/* Cache zh templates from first row labels when present. */
			var firstLeg = root.querySelector("[data-wenpai-repeater-row] .wenpai-repeater__legend");
			var firstDel = root.querySelector("[data-wenpai-repeater-remove]");
			if (firstLeg && /%d|第\s*\d+\s*项/.test(firstLeg.textContent) === false) {
				/* keep defaults */
			}
			root.setAttribute("data-wenpai-item-tpl", "第 %d 项");
			root.setAttribute("data-wenpai-delete-tpl", "删除第 %d 项");
			if (firstDel && firstDel.textContent.indexOf("删除") === 0) {
				root.setAttribute("data-wenpai-delete-tpl", "删除第 %d 项");
			}
		}
	}

	document.addEventListener("click", function (e) {
		var add = e.target.closest && e.target.closest("[data-wenpai-repeater-add]");
		if (add) {
			var root = add.closest("[data-wenpai-repeater]");
			if (!root || !inApp(root)) return;
			e.preventDefault();
			var max = parseInt(root.getAttribute("data-wenpai-repeater-max") || "20", 10) || 20;
			var box = root.querySelector("[data-wenpai-repeater-rows]");
			var tpl = root.querySelector("template[data-wenpai-repeater-template]");
			if (!box || !tpl || box.querySelectorAll("[data-wenpai-repeater-row]").length >= max) return;
			var node = tpl.content.cloneNode(true);
			box.appendChild(node);
			repeaterRenumber(root);
			var rows = box.querySelectorAll("[data-wenpai-repeater-row]");
			var last = rows[rows.length - 1];
			var focus = last && last.querySelector("input, select, textarea, button");
			if (focus && focus.focus) focus.focus();
			return;
		}
		var rem = e.target.closest && e.target.closest("[data-wenpai-repeater-remove]");
		if (rem) {
			var root2 = rem.closest("[data-wenpai-repeater]");
			var row = rem.closest("[data-wenpai-repeater-row]");
			if (!root2 || !row || !inApp(root2)) return;
			e.preventDefault();
			var box2 = root2.querySelector("[data-wenpai-repeater-rows]");
			row.parentNode.removeChild(row);
			repeaterRenumber(root2);
			var prev = box2 && box2.querySelectorAll("[data-wenpai-repeater-row]");
			var focus2 = prev && prev.length ? prev[prev.length - 1].querySelector("input, select, textarea, button") : root2.querySelector("[data-wenpai-repeater-add]");
			if (focus2 && focus2.focus) focus2.focus();
			return;
		}
		var up = e.target.closest && e.target.closest("[data-wenpai-repeater-up]");
		if (up) {
			var root3 = up.closest("[data-wenpai-repeater]");
			var row3 = up.closest("[data-wenpai-repeater-row]");
			if (!root3 || !row3 || !inApp(root3)) return;
			e.preventDefault();
			var prevRow = row3.previousElementSibling;
			if (prevRow && prevRow.hasAttribute("data-wenpai-repeater-row")) {
				row3.parentNode.insertBefore(row3, prevRow);
				repeaterRenumber(root3);
			}
			return;
		}
		var down = e.target.closest && e.target.closest("[data-wenpai-repeater-down]");
		if (down) {
			var root4 = down.closest("[data-wenpai-repeater]");
			var row4 = down.closest("[data-wenpai-repeater-row]");
			if (!root4 || !row4 || !inApp(root4)) return;
			e.preventDefault();
			var nextRow = row4.nextElementSibling;
			if (nextRow && nextRow.hasAttribute("data-wenpai-repeater-row")) {
				row4.parentNode.insertBefore(nextRow, row4);
				repeaterRenumber(root4);
			}
		}
	});

	/* ---- sortable ------------------------------------------------------------ */

	/*
	 * Wenpai_Admin_UI::sortable(): move-up / move-down buttons swap neighbours. No drag.
	 * After a move: index numbers, the hidden order input (ids joined by commas), and the
	 * first/last disabled states are rewritten; focus stays on the moved item; the new
	 * position goes to the shared live region. Without JS the rendered order is submitted.
	 */
	function sortableItems(list) {
		var items = [];
		for (var c = list.firstElementChild; c; c = c.nextElementSibling) {
			if (c.hasAttribute("data-wenpai-sortable-item")) {
				items.push(c);
			}
		}
		return items;
	}

	function sortableSync(list) {
		var items = sortableItems(list);
		var ids = [];
		for (var i = 0; i < items.length; i++) {
			var index = items[i].querySelector(".wenpai-sortable__index");
			if (index) {
				index.textContent = String(i + 1);
			}
			var up = items[i].querySelector("[data-wenpai-sortable-up]");
			var down = items[i].querySelector("[data-wenpai-sortable-down]");
			if (up) {
				up.disabled = i === 0;
			}
			if (down) {
				down.disabled = i === items.length - 1;
			}
			ids.push(items[i].getAttribute("data-wenpai-sortable-item") || "");
		}
		var inputId = list.getAttribute("data-wenpai-sortable") || "";
		var input = inputId ? document.getElementById(inputId) : null;
		if (input && input.value !== ids.join(",")) {
			input.value = ids.join(",");
			input.dispatchEvent(new Event("change", { bubbles: true }));
		}
		return items;
	}

	document.addEventListener("click", function (e) {
		var btn = e.target.closest && e.target.closest("[data-wenpai-sortable-up], [data-wenpai-sortable-down]");
		if (!btn || btn.disabled) {
			return;
		}
		var list = btn.closest("[data-wenpai-sortable]");
		var item = btn.closest("[data-wenpai-sortable-item]");
		if (!list || !item || item.parentNode !== list || !inApp(list)) {
			return;
		}
		e.preventDefault();
		var up = btn.hasAttribute("data-wenpai-sortable-up");
		var items = sortableItems(list);
		var at = items.indexOf(item);
		var other = items[up ? at - 1 : at + 1];
		if (!other) {
			return;
		}
		if (up) {
			list.insertBefore(item, other);
		} else {
			list.insertBefore(other, item);
		}
		items = sortableSync(list);
		/* Same-direction button on the moved item; at either end it is disabled, so take the other one. */
		var same = item.querySelector(up ? "[data-wenpai-sortable-up]" : "[data-wenpai-sortable-down]");
		var back = item.querySelector(up ? "[data-wenpai-sortable-down]" : "[data-wenpai-sortable-up]");
		var focus = same && !same.disabled ? same : back;
		if (focus && focus.focus) {
			focus.focus();
		}
		var title = item.querySelector(".wenpai-sortable__title");
		var name = title ? (title.textContent || "").trim() : "";
		/* Function replacer: a title may contain "$&" and the like. */
		var said = (list.getAttribute("data-wenpai-sortable-moved") || "%1$s 已移到第 %2$d 位，共 %3$d 项")
			.replace("%2$d", String(items.indexOf(item) + 1))
			.replace("%3$d", String(items.length))
			.replace("%1$s", function () {
				return name;
			});
		announceLive(said);
	});

	window.wenpaiAdmin = {
		notice: notice,
		request: request,
		runAction: runAction,
		syncDepends: syncDepends,
		syncViewRules: syncViewRules
	};

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", function () {
			boot(document);
		});
	} else {
		boot(document);
	}
})();

/* 0.5.3 confirm dialog: delegates [data-wenpai-confirm] on any admin screen. */
(function () {
	"use strict";

	var dialog = null;

	function ensureDialog() {
		if (dialog) {
			return dialog;
		}
		dialog = document.createElement("dialog");
		// Lives on <body>, outside .wenpai-app: .wenpai-native is what carries the tokens.
		dialog.className = "wenpai-native wenpai-dialog";
		dialog.innerHTML =
			'<h3></h3><p></p>' +
			'<div class="wenpai-gate" hidden><label><input type="checkbox"> <span></span></label></div>' +
			'<div class="card-foot"><a class="btn btn-ghost btn-sm" target="_blank" rel="noopener noreferrer" data-wenpai-dialog-more hidden></a><span>' +
			'<button type="button" class="btn btn-ghost" data-wenpai-dialog-cancel></button>' +
			'<button type="button" class="btn btn-secondary" data-wenpai-dialog-ok></button>' +
			"</span></div>";
		document.body.appendChild(dialog);
		dialog.addEventListener("click", function (event) {
			if (event.target === dialog) {
				dialog.close();
			}
		});
		return dialog;
	}

	function openConfirm(trigger) {
		var box = ensureDialog();
		var danger = trigger.hasAttribute("data-wenpai-confirm-danger");
		var primary = trigger.hasAttribute("data-wenpai-confirm-primary");
		var gate = trigger.getAttribute("data-wenpai-confirm-gate") || "";
		var moreLabel = trigger.getAttribute("data-wenpai-confirm-more-label") || "";
		var moreUrl = trigger.getAttribute("data-wenpai-confirm-more-url") || "";
		var hasMore = !!(moreLabel && /^https?:\/\//i.test(moreUrl));
		box.className = "wenpai-native wenpai-dialog" + (danger ? " is-danger" : "");
		box.querySelector("h3").textContent = trigger.getAttribute("data-wenpai-confirm-title") || (danger ? "请确认" : "确认");
		box.querySelector("p").textContent = trigger.getAttribute("data-wenpai-confirm") || "";
		var gateWrap = box.querySelector(".wenpai-gate");
		var gateInput = gateWrap.querySelector("input");
		gateWrap.hidden = gate === "";
		gateWrap.querySelector("span").textContent = gate;
		gateInput.checked = false;
		var foot = box.querySelector(".card-foot");
		var more = box.querySelector("[data-wenpai-dialog-more]");
		if (foot && more) {
			foot.className = hasMore ? "card-foot start" : "card-foot";
			more.hidden = !hasMore;
			more.textContent = hasMore ? moreLabel : "";
			if (hasMore) {
				more.setAttribute("href", moreUrl);
				more.onclick = function () {
					box.close();
				};
			} else {
				more.removeAttribute("href");
				more.onclick = null;
			}
		}
		var cancel = box.querySelector("[data-wenpai-dialog-cancel]");
		var ok = box.querySelector("[data-wenpai-dialog-ok]");
		cancel.textContent = trigger.getAttribute("data-wenpai-confirm-cancel") || "取消";
		ok.textContent = trigger.getAttribute("data-wenpai-confirm-ok") || (danger ? "确认执行" : "确认");
		ok.className = "btn " + (danger ? "btn-danger" : (primary ? "btn-primary" : "btn-secondary"));
		ok.disabled = gate !== "";
		gateInput.onchange = function () {
			ok.disabled = gate !== "" && !gateInput.checked;
		};
		cancel.onclick = function () {
			box.close();
		};
		ok.onclick = function () {
			box.close();
			trigger.setAttribute("data-wenpai-confirm-bypass", "1");
			trigger.click();
			trigger.removeAttribute("data-wenpai-confirm-bypass");
		};
		if (typeof box.showModal === "function") {
			box.showModal();
		} else {
			box.setAttribute("open", "open");
		}
		cancel.focus();
	}

	document.addEventListener(
		"click",
		function (event) {
			var el = event.target && event.target.closest ? event.target.closest("[data-wenpai-confirm]") : null;
			if (!el || el.disabled || el.hasAttribute("data-wenpai-confirm-bypass")) {
				return;
			}
			event.preventDefault();
			event.stopPropagation();
			openConfirm(el);
		},
		true
	);
})();

/* 0.5.6 list table bulk selection: row tint + live count (shell pages). */
(function () {
	"use strict";

	function refresh(root) {
		var boxes = root.querySelectorAll('.wp-list-table input[type="checkbox"][name="post[]"], .wp-list-table tbody input[type="checkbox"]');
		var selected = 0;
		boxes.forEach(function (box) {
			var row = box.closest("tr");
			if (!row) {
				return;
			}
			row.classList.toggle("is-selected", box.checked);
			if (box.checked) {
				selected += 1;
			}
		});
		var count = root.querySelector("[data-wenpai-bulk-count]");
		if (count) {
			count.textContent = count.getAttribute("data-wenpai-bulk-count-zero") || "";
			if (selected > 0) {
				count.textContent = (count.getAttribute("data-wenpai-bulk-count-one") || "已选 %d 项").replace("%d", String(selected));
			}
		}
	}

	document.addEventListener("change", function (event) {
		var el = event.target;
		if (!el || !el.closest || !el.closest(".list-table")) {
			return;
		}
		if (el.type !== "checkbox") {
			return;
		}
		refresh(el.closest(".list-table"));
	});
})();

/* 0.6 fields: color hex echo + media picker (wp.media when enqueued). */
(function () {
	"use strict";

	document.addEventListener("input", function (event) {
		var el = event.target;
		if (!el || el.type !== "color") {
			return;
		}
		var row = el.closest(".wenpai-color-row");
		var hex = row && row.querySelector("[data-wenpai-color-hex]");
		if (hex) {
			hex.textContent = String(el.value || "").toUpperCase();
		}
	});

	function setThumb(thumb, url) {
		if (!thumb) {
			return;
		}
		if (url) {
			thumb.innerHTML = "";
			var img = document.createElement("img");
			img.src = url;
			img.alt = "";
			thumb.appendChild(img);
		} else {
			thumb.textContent = thumb.getAttribute("data-wenpai-media-empty") || "";
		}
	}

	document.addEventListener("click", function (event) {
		var btn = event.target && event.target.closest ? event.target.closest("[data-wenpai-media-select], [data-wenpai-media-clear]") : null;
		if (!btn) {
			return;
		}
		event.preventDefault();
		var row = btn.closest(".wenpai-media-row");
		if (!row) {
			return;
		}
		var hidden = row.querySelector("[data-wenpai-media-id]") || row.querySelector("input[type=hidden]");
		var urlBox = row.querySelector("[data-wenpai-media-url]");
		var thumb = row.querySelector("[data-wenpai-media-thumb]");
		if (btn.hasAttribute("data-wenpai-media-clear")) {
			if (hidden) {
				hidden.value = "";
				hidden.dispatchEvent(new Event("change", { bubbles: true }));
			}
			if (urlBox) {
				urlBox.value = "";
			}
			setThumb(thumb, "");
			return;
		}
		if (!window.wp || !window.wp.media) {
			return;
		}
		var frame = window.wp.media({ library: { type: "image" }, multiple: false });
		frame.on("select", function () {
			var att = frame.state().get("selection").first();
			if (!att) {
				return;
			}
			var data = typeof att.toJSON === "function" ? att.toJSON() : att;
			var url = (data.sizes && data.sizes.thumbnail && data.sizes.thumbnail.url) || data.url || "";
			if (hidden) {
				hidden.value = String(data.id || "");
				hidden.dispatchEvent(new Event("change", { bubbles: true }));
			}
			if (urlBox) {
				urlBox.value = url;
			}
			setThumb(thumb, url);
		});
		frame.open();
	});
})();

/* 0.7 list_bar: a filter change submits the GET form (noscript button covers no-JS). */
document.addEventListener("change", function (event) {
	var t = event.target;
	if (!t || !t.closest || t.tagName !== "SELECT" || !t.closest("form.wenpai-list-bar")) {
		return;
	}
	var form = t.closest("form.wenpai-list-bar");
	if (typeof form.requestSubmit === "function") {
		form.requestSubmit();
	} else {
		form.submit();
	}
});

/* 0.7 checkgroup / catalog_picker: count, select all / clear, catalog filter. */
(function () {
	"use strict";

	function cgRefresh(box) {
		var count = box.querySelector("[data-wenpai-count-template]");
		if (!count) {
			return;
		}
		var total = parseInt(box.getAttribute("data-wenpai-count-total") || "0", 10) || 0;
		var boxes = box.querySelectorAll('input[type="checkbox"]');
		var sel = 0;
		for (var i = 0; i < boxes.length; i++) {
			if (boxes[i].checked) {
				sel++;
			}
		}
		var tpl = count.getAttribute("data-wenpai-count-template") || "";
		count.textContent = tpl.replace("%1$d", String(sel)).replace("%2$d", String(total));
	}

	document.addEventListener("change", function (event) {
		var t = event.target;
		if (!t || !t.closest || t.type !== "checkbox") {
			return;
		}
		var box = t.closest("[data-wenpai-checkgroup]");
		if (box) {
			cgRefresh(box);
		}
	});

	document.addEventListener("click", function (event) {
		var btn = event.target && event.target.closest ? event.target.closest("[data-wenpai-cg-all], [data-wenpai-cg-none]") : null;
		if (!btn) {
			return;
		}
		var box = btn.closest("[data-wenpai-checkgroup]");
		if (!box) {
			return;
		}
		event.preventDefault();
		var on = btn.hasAttribute("data-wenpai-cg-all");
		var boxes = box.querySelectorAll('input[type="checkbox"]');
		var first = null;
		for (var i = 0; i < boxes.length; i++) {
			if (boxes[i].disabled) {
				continue;
			}
			boxes[i].checked = on;
			if (!first) {
				first = boxes[i];
			}
		}
		cgRefresh(box);
		/* Let depends_on and other listeners see the change. */
		if (first && typeof window.Event === "function") {
			first.dispatchEvent(new window.Event("change", { bubbles: true }));
		}
	});

	document.addEventListener("input", function (event) {
		var inp = event.target;
		if (!inp || !inp.matches || !inp.matches("[data-wenpai-catalog-filter]")) {
			return;
		}
		var cat = inp.closest("[data-wenpai-catalog]");
		if (!cat) {
			return;
		}
		var q = String(inp.value || "").trim().toLowerCase();
		var items = cat.querySelectorAll(".wenpai-checkgroup__list li");
		for (var i = 0; i < items.length; i++) {
			var li = items[i];
			li.hidden = q !== "" && String(li.textContent || "").toLowerCase().indexOf(q) === -1;
		}
	});
})();

/* 0.7 suggest: ArrowUp/Down moves .is-active inside .wenpai-suggest, Enter activates, Esc closes. */
(function () {
	"use strict";

	function itemsOf(out) {
		var all = out.querySelectorAll(".wenpai-suggest__list li");
		var list = [];
		for (var i = 0; i < all.length; i++) {
			if (!all[i].hidden) {
				list.push(all[i]);
			}
		}
		return list;
	}

	document.addEventListener("keydown", function (event) {
		var inp = event.target;
		if (!inp || !inp.matches || !inp.matches(".wenpai-suggest input")) {
			return;
		}
		var wrap = inp.closest(".wenpai-suggest");
		var out = wrap && wrap.querySelector(".wenpai-suggest__out");
		if (!out || out.hidden) {
			return;
		}
		var items = itemsOf(out);
		if (!items.length) {
			return;
		}
		var idx = -1;
		for (var i = 0; i < items.length; i++) {
			if (items[i].classList.contains("is-active")) {
				idx = i;
			}
		}
		if (event.key === "ArrowDown" || event.key === "ArrowUp") {
			event.preventDefault();
			var next = event.key === "ArrowDown"
				? (idx + 1) % items.length
				: (idx <= 0 ? items.length - 1 : idx - 1);
			for (var j = 0; j < items.length; j++) {
				items[j].classList.toggle("is-active", j === next);
			}
			return;
		}
		if (event.key === "Enter" && idx >= 0) {
			var btn = items[idx].querySelector("button");
			if (btn) {
				event.preventDefault();
				btn.click();
			}
			return;
		}
		if (event.key === "Escape") {
			out.hidden = true;
		}
	});

	/* A click anywhere on a suggestion row activates its button. */
	document.addEventListener("click", function (event) {
		var li = event.target && event.target.closest ? event.target.closest(".wenpai-suggest__list li") : null;
		if (!li) {
			return;
		}
		var btn = li.querySelector("button");
		if (btn && event.target !== btn) {
			event.preventDefault();
			btn.click();
		}
	});
})();

/* 0.8 fields + UI helpers: copy / entity / file / tags / range / icon.
 * Vanilla, delegated. entity/file ajax endpoints are per-field data attrs
 * (data-ajax = admin-ajax.php, data-nonce, data-kind, data-object, data-name,
 * data-multiple, data-field-key) rendered by the settings class; helpers work
 * outside .wenpai-app. */
(function () {
	"use strict";

	function closest(el, sel) {
		return el && el.closest ? el.closest(sel) : null;
	}

	/* First non-empty data attribute among candidates (kit attrs have drifted
	 * between data-ajax/data-nonce and data-wenpai-* spellings). */
	function dataAttr(el, names) {
		for (var i = 0; i < names.length; i++) {
			var v = el.getAttribute(names[i]);
			if (v !== null && v !== "") {
				return v;
			}
		}
		return "";
	}

	/* ---- copy_button ------------------------------------------------------ */

	function legacyCopy(text) {
		var ta = document.createElement("textarea");
		ta.value = text;
		ta.setAttribute("readonly", "");
		ta.style.position = "fixed";
		ta.style.opacity = "0";
		document.body.appendChild(ta);
		ta.select();
		var ok = false;
		try {
			ok = document.execCommand("copy");
		} catch (err) {
			ok = false;
		}
		document.body.removeChild(ta);
		return ok;
	}

	function writeClipboard(text) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			return navigator.clipboard.writeText(text).then(
				function () { return true; },
				function () { return legacyCopy(text); }
			);
		}
		return Promise.resolve(legacyCopy(text));
	}

	function copyDone(btn) {
		var label = btn.getAttribute("data-done-label") || "已复制";
		var html = btn.innerHTML;
		btn.classList.add("is-done");
		btn.textContent = label;
		window.setTimeout(function () {
			btn.classList.remove("is-done");
			btn.innerHTML = html;
		}, 1600);
	}

	function copyTargetText(btn) {
		var sel = btn.getAttribute("data-wenpai-copy-target") || "";
		var node = null;
		if (sel.indexOf("self:") === 0) {
			var scope = closest(btn, ".wenpai-code");
			node = scope ? scope.querySelector(sel.slice(5)) : null;
		} else if (sel) {
			node = document.querySelector(sel);
		}
		if (!node) {
			return "";
		}
		var tag = node.tagName ? node.tagName.toLowerCase() : "";
		return tag === "input" || tag === "textarea" || tag === "select" ? node.value : node.textContent;
	}

	document.addEventListener("click", function (e) {
		var btn = e.target && e.target.closest ? e.target.closest("[data-wenpai-copy-text], [data-wenpai-copy-target]") : null;
		if (!btn || btn.disabled || btn.getAttribute("aria-busy") === "true") {
			return;
		}
		e.preventDefault();
		var text = btn.getAttribute("data-wenpai-copy-text");
		if (text === null) {
			text = copyTargetText(btn);
		}
		if (text === null || text === undefined) {
			return;
		}
		writeClipboard(String(text)).then(function (ok) {
			if (ok) {
				copyDone(btn);
			}
		});
	});

	/* ---- entity picker -----------------------------------------------------
	 * .wenpai-entity[data-ajax][data-nonce][data-kind][data-object]
	 * > .wenpai-entity__picked (+ .wenpai-entity__clear)
	 *   input.wenpai-entity__input[type=search]
	 *   input[type=hidden]   (the submitted id)
	 *   .wenpai-entity__out[hidden] > ul.wenpai-entity__list > li > button.wenpai-entity__item
	 */

	function entityQuery(root) {
		var inp = root.querySelector(".wenpai-entity__input");
		var q = inp ? inp.value : "";
		var base = dataAttr(root, ["data-ajax", "data-wenpai-ajax", "data-ajax-url"]) || window.ajaxurl || "";
		if (!base || !window.fetch) {
			return;
		}
		var url = base + (base.indexOf("?") === -1 ? "?" : "&")
			+ "action=wenpai_entity_lookup"
			+ "&kind=" + encodeURIComponent(dataAttr(root, ["data-kind", "data-wenpai-kind"]) || "post")
			+ "&object=" + encodeURIComponent(dataAttr(root, ["data-object", "data-wenpai-object"]))
			+ "&q=" + encodeURIComponent(q)
			+ "&_ajax_nonce=" + encodeURIComponent(dataAttr(root, ["data-nonce", "data-wenpai-entity-nonce", "data-wenpai-nonce"]));
		var seq = (root._wenpaiEntitySeq || 0) + 1;
		root._wenpaiEntitySeq = seq;
		window.fetch(url, { credentials: "same-origin" }).then(function (r) {
			return r.json();
		}).then(function (j) {
			if (seq !== root._wenpaiEntitySeq) {
				return;
			}
			var items = j && j.success && j.data && Array.isArray(j.data.items) ? j.data.items : [];
			renderEntity(root, items);
		}, function () {
			/* network failure: leave the dropdown as it is */
		});
	}

	function scheduleEntity(root) {
		if (root._wenpaiEntityTimer) {
			window.clearTimeout(root._wenpaiEntityTimer);
		}
		root._wenpaiEntityTimer = window.setTimeout(function () {
			root._wenpaiEntityTimer = null;
			entityQuery(root);
		}, 250);
	}

	function renderEntity(root, items) {
		var out = root.querySelector(".wenpai-entity__out");
		var list = out && out.querySelector(".wenpai-entity__list");
		if (!out || !list) {
			return;
		}
		list.textContent = "";
		if (!items.length) {
			var empty = document.createElement("li");
			empty.className = "wenpai-entity__empty";
			empty.textContent = root.getAttribute("data-empty") || "没有匹配的结果";
			list.appendChild(empty);
		} else {
			for (var i = 0; i < items.length; i++) {
				var it = items[i] || {};
				var li = document.createElement("li");
				var btn = document.createElement("button");
				btn.type = "button";
				btn.className = "wenpai-entity__item";
				btn._wenpaiEntity = { id: it.id, label: it.label, meta: it.meta };
				var lab = document.createElement("span");
				lab.className = "wenpai-entity__label";
				lab.textContent = it.label === undefined || it.label === null ? "" : String(it.label);
				btn.appendChild(lab);
				if (it.meta) {
					var meta = document.createElement("span");
					meta.className = "wenpai-entity__meta";
					meta.textContent = String(it.meta);
					btn.appendChild(meta);
				}
				li.appendChild(btn);
				list.appendChild(li);
			}
		}
		out.hidden = false;
		root._wenpaiEntityItems = items;
	}

	function entityItems(root) {
		var out = root.querySelector(".wenpai-entity__out");
		if (!out || out.hidden) {
			return [];
		}
		var btns = out.querySelectorAll(".wenpai-entity__item");
		var list = [];
		for (var i = 0; i < btns.length; i++) {
			list.push(btns[i]);
		}
		return list;
	}

	function setEntityPicked(root, label, meta) {
		var picked = root.querySelector(".wenpai-entity__picked");
		if (!picked) {
			return;
		}
		var clear = picked.querySelector(".wenpai-entity__clear");
		picked.textContent = "";
		var lab = document.createElement("span");
		lab.className = "wenpai-entity__picked-label";
		lab.textContent = label;
		picked.appendChild(lab);
		if (meta) {
			var m = document.createElement("span");
			m.className = "wenpai-entity__meta";
			m.textContent = meta;
			picked.appendChild(m);
		}
		if (clear) {
			picked.appendChild(clear);
		}
		picked.hidden = false;
	}

	function selectEntity(root, item) {
		var hidden = root.querySelector("input[type=\"hidden\"]");
		if (hidden) {
			hidden.value = item.id === undefined || item.id === null ? "" : String(item.id);
			if (typeof window.Event === "function") {
				hidden.dispatchEvent(new window.Event("change", { bubbles: true }));
			}
		}
		setEntityPicked(root, item.label, item.meta);
		var inp = root.querySelector(".wenpai-entity__input");
		if (inp) {
			inp.value = "";
		}
		var out = root.querySelector(".wenpai-entity__out");
		if (out) {
			out.hidden = true;
		}
	}

	function clearEntity(root) {
		var hidden = root.querySelector("input[type=\"hidden\"]");
		if (hidden) {
			hidden.value = "";
			if (typeof window.Event === "function") {
				hidden.dispatchEvent(new window.Event("change", { bubbles: true }));
			}
		}
		var picked = root.querySelector(".wenpai-entity__picked");
		if (picked) {
			picked.hidden = true;
		}
		var inp = root.querySelector(".wenpai-entity__input");
		if (inp) {
			inp.focus();
		}
		var out = root.querySelector(".wenpai-entity__out");
		if (out) {
			out.hidden = true;
		}
	}

	document.addEventListener("input", function (e) {
		var inp = e.target;
		if (!inp || !inp.matches || !inp.matches(".wenpai-entity__input")) {
			return;
		}
		var root = closest(inp, ".wenpai-entity");
		if (root) {
			scheduleEntity(root);
		}
	});

	document.addEventListener("keydown", function (e) {
		var inp = e.target;
		if (!inp || !inp.matches || !inp.matches(".wenpai-entity__input")) {
			return;
		}
		var root = closest(inp, ".wenpai-entity");
		if (!root) {
			return;
		}
		var out = root.querySelector(".wenpai-entity__out");
		if (e.key === "Escape") {
			if (out) {
				out.hidden = true;
			}
			return;
		}
		if (!out || out.hidden) {
			return;
		}
		var items = entityItems(root);
		if (!items.length) {
			return;
		}
		var idx = -1;
		for (var i = 0; i < items.length; i++) {
			if (items[i].classList.contains("is-active")) {
				idx = i;
			}
		}
		if (e.key === "ArrowDown" || e.key === "ArrowUp") {
			e.preventDefault();
			var next = e.key === "ArrowDown"
				? (idx + 1) % items.length
				: (idx <= 0 ? items.length - 1 : idx - 1);
			for (var j = 0; j < items.length; j++) {
				items[j].classList.toggle("is-active", j === next);
			}
			return;
		}
		if (e.key === "Enter" && idx >= 0) {
			var it = items[idx]._wenpaiEntity;
			if (it) {
				e.preventDefault();
				selectEntity(root, it);
			}
		}
	});

	document.addEventListener("click", function (e) {
		var itemBtn = e.target && e.target.closest ? e.target.closest(".wenpai-entity__item") : null;
		if (itemBtn) {
			var iroot = closest(itemBtn, ".wenpai-entity");
			if (iroot && itemBtn._wenpaiEntity) {
				e.preventDefault();
				selectEntity(iroot, itemBtn._wenpaiEntity);
			}
			return;
		}
		var clr = e.target && e.target.closest ? e.target.closest(".wenpai-entity__clear") : null;
		if (clr) {
			var croot = closest(clr, ".wenpai-entity");
			if (croot) {
				e.preventDefault();
				clearEntity(croot);
			}
			return;
		}
		/* Outside click closes every open entity dropdown. */
		var eouts = document.querySelectorAll(".wenpai-entity__out:not([hidden])");
		for (var ei = 0; ei < eouts.length; ei++) {
			var ehost = closest(eouts[ei], ".wenpai-entity");
			if (ehost && !ehost.contains(e.target)) {
				eouts[ei].hidden = true;
			}
		}
	});

	/* ---- file upload --------------------------------------------------------
	 * .wenpai-file[data-ajax][data-nonce][data-name][data-multiple][data-field-key]
	 * data-multiple "1" = append, "0"/absent = single (replace all).
	 * data-field-key is the {option}__{field} registry key for the upload
	 * endpoint; data-name is the input name used for hidden inputs.
	 * .wenpai-file__drop > input.wenpai-file__input[type=file][hidden]
	 * .wenpai-file__list > .wenpai-file__item (.wenpai-file__name/__size/__state
	 * + button.wenpai-file__remove + hidden inputs name="{field}[{i}][key]")
	 */

	function fmtSize(bytes) {
		var n = parseInt(bytes, 10) || 0;
		if (n < 1024) {
			return n + " B";
		}
		if (n < 1024 * 1024) {
			return (n / 1024).toFixed(1) + " KB";
		}
		return (n / (1024 * 1024)).toFixed(1) + " MB";
	}

	function fileItemNode(root, file, item) {
		var div = document.createElement("span");
		div.className = "wenpai-file__item";
		var name = document.createElement("span");
		name.className = "wenpai-file__name";
		name.textContent = item && item.name ? item.name : (file ? file.name : "");
		var size = document.createElement("span");
		size.className = "wenpai-file__size";
		size.textContent = item ? fmtSize(item.size) : "";
		var state = document.createElement("span");
		state.className = "wenpai-file__state";
		var btn = document.createElement("button");
		btn.type = "button";
		btn.className = "wenpai-file__remove";
		btn.textContent = root.getAttribute("data-remove") || "移除";
		div.appendChild(name);
		div.appendChild(size);
		div.appendChild(state);
		div.appendChild(btn);
		if (item) {
			var keys = ["id", "url", "name", "size", "type"];
			for (var i = 0; i < keys.length; i++) {
				var h = document.createElement("input");
				h.type = "hidden";
				h.setAttribute("data-key", keys[i]);
				h.value = item[keys[i]] === undefined || item[keys[i]] === null ? "" : String(item[keys[i]]);
				div.appendChild(h);
			}
		}
		return div;
	}

	/* Rewrite every item's hidden-input indices so removal never leaves gaps. */
	function fileRenumber(root) {
		var list = root.querySelector(".wenpai-file__list");
		if (!list) {
			return;
		}
		var items = list.querySelectorAll(".wenpai-file__item:not(.is-uploading)");
		for (var i = 0; i < items.length; i++) {
			var hiddens = items[i].querySelectorAll("input[type=\"hidden\"]");
			for (var j = 0; j < hiddens.length; j++) {
				var m = hiddens[j].name.match(/^(.*)\[(\d+)\]\[([^\]]+)\]$/);
				if (m) {
					hiddens[j].name = m[1] + "[" + i + "][" + m[3] + "]";
				}
			}
		}
	}

	function fileNameFor(root, index, key) {
		return fileFieldName(root) + "[" + index + "][" + key + "]";
	}

	function fileFieldName(root) {
		return dataAttr(root, ["data-name", "data-wenpai-name", "data-field-name"]);
	}

	/* The upload endpoint wants the {option}__{field id} registry key. PHP may
	 * emit it as data-field-key / data-ajax-field; otherwise derive it from the
	 * input name "option[…][id]" → "option__id". */
	function fileFieldKey(root) {
		var k = dataAttr(root, ["data-field-key", "data-ajax-field", "data-wenpai-field-key", "data-field"]);
		if (k !== "") {
			return k;
		}
		var nm = fileFieldName(root);
		var m = nm.match(/^([^\[]+)((?:\[[^\]]*\])+?)$/);
		if (!m) {
			return nm;
		}
		var segs = m[2].match(/\[([^\]]*)\]/g) || [];
		var last = segs.length ? segs[segs.length - 1].replace(/^\[|\]$/g, "") : "";
		return m[1] + "__" + last;
	}

	function fileUpload(root, file) {
		var list = root.querySelector(".wenpai-file__list");
		var base = dataAttr(root, ["data-ajax", "data-wenpai-ajax", "data-ajax-url"]) || window.ajaxurl || "";
		if (!list || !base || !window.fetch || !window.FormData) {
			return;
		}
		/* PHP contract: data-multiple "1" = append, "0" = single file (a new
		 * upload replaces the whole list). data-max is a legacy fallback. */
		var dm = root.getAttribute("data-multiple");
		var mv = root.getAttribute("data-max");
		var multi;
		if (dm !== null) {
			multi = dm === "1" || dm === "true";
		} else if (mv !== null) {
			multi = mv === "0";
		} else {
			multi = false;
		}
		if (!multi) {
			var olds = list.querySelectorAll(".wenpai-file__item");
			for (var k = 0; k < olds.length; k++) {
				olds[k].parentNode.removeChild(olds[k]);
			}
		}
		var node = fileItemNode(root, file, null);
		node.classList.add("is-uploading");
		var state = node.querySelector(".wenpai-file__state");
		if (state) {
			state.textContent = root.getAttribute("data-uploading") || "上传中…";
		}
		list.appendChild(node);
		var fd = new FormData();
		fd.append("action", "wenpai_file_upload");
		fd.append("_ajax_nonce", dataAttr(root, ["data-nonce", "data-wenpai-nonce", "data-wenpai-file-nonce"]));
		fd.append("field", fileFieldKey(root));
		fd.append("file", file);
		window.fetch(base, { method: "POST", body: fd, credentials: "same-origin" }).then(function (r) {
			return r.json();
		}).then(function (j) {
			node.classList.remove("is-uploading");
			var item = j && j.success && j.data && j.data.item ? j.data.item : null;
			if (!item) {
				node.classList.add("is-bad");
				if (state) {
					state.textContent = root.getAttribute("data-failed") || "上传失败";
				}
				return;
			}
			/* Fill the placeholder with the server item. */
			node.querySelector(".wenpai-file__name").textContent = item.name || "";
			node.querySelector(".wenpai-file__size").textContent = fmtSize(item.size);
			if (state) {
				state.textContent = "";
			}
			var keys = ["id", "url", "name", "size", "type"];
			var hiddens = node.querySelectorAll("input[type=\"hidden\"]");
			if (!hiddens.length) {
				for (var i = 0; i < keys.length; i++) {
					var h = document.createElement("input");
					h.type = "hidden";
					h.setAttribute("data-key", keys[i]);
					node.appendChild(h);
				}
				hiddens = node.querySelectorAll("input[type=\"hidden\"]");
			}
			/* Position among completed items; fileRenumber() normalizes every index. */
			var done = list.querySelectorAll(".wenpai-file__item:not(.is-uploading)");
			var idx = -1;
			for (var d = 0; d < done.length; d++) {
				if (done[d] === node) {
					idx = d;
				}
			}
			if (idx < 0) {
				idx = done.length;
			}
			for (var x = 0; x < hiddens.length && x < keys.length; x++) {
				hiddens[x].name = fileNameFor(root, idx, keys[x]);
				hiddens[x].value = item[keys[x]] === undefined || item[keys[x]] === null ? "" : String(item[keys[x]]);
			}
			fileRenumber(root);
		}, function () {
			node.classList.remove("is-uploading");
			node.classList.add("is-bad");
			if (state) {
				state.textContent = root.getAttribute("data-failed") || "上传失败";
			}
		});
	}

	function fileUploadAll(root, files) {
		if (!files || !files.length) {
			return;
		}
		for (var i = 0; i < files.length; i++) {
			fileUpload(root, files[i]);
		}
	}

	/* Local mode (data-wenpai-file-local): the file never leaves the browser.
	 * The kit only mirrors the selection into .wenpai-file__list (name + size,
	 * removable) and fires "wenpai:file-select" on the root; the product reads
	 * input.files or event.detail.files for its own flow (import preview,
	 * client-side parse). The real <input type=file> keeps its value so
	 * form posts and .files reads keep working. */
	function fileIsLocal(root) {
		return !!(root && (root.hasAttribute("data-wenpai-file-local") || root.getAttribute("data-local") === "1" || root.getAttribute("data-mode") === "local"));
	}

	function fileLocalAdd(root, files) {
		if (!files || !files.length) {
			return;
		}
		var list = root.querySelector(".wenpai-file__list");
		if (!list) {
			return;
		}
		var dm = root.getAttribute("data-multiple");
		var multi = dm === "1" || dm === "true";
		if (!multi) {
			var olds = list.querySelectorAll(".wenpai-file__item");
			for (var k = 0; k < olds.length; k++) {
				olds[k].parentNode.removeChild(olds[k]);
			}
		}
		var picked = [];
		for (var i = 0; i < files.length; i++) {
			var node = fileItemNode(root, files[i], null);
			node.querySelector(".wenpai-file__size").textContent = fmtSize(files[i].size);
			var st = node.querySelector(".wenpai-file__state");
			if (st) {
				st.textContent = "";
			}
			list.appendChild(node);
			picked.push(files[i]);
		}
		try {
			root.dispatchEvent(new CustomEvent("wenpai:file-select", { bubbles: true, detail: { files: picked } }));
		} catch (err) {
			/* CustomEvent unsupported: product falls back to input.files. */
		}
	}

	document.addEventListener("click", function (e) {
		var drop = e.target && e.target.closest ? e.target.closest(".wenpai-file__drop") : null;
		if (!drop) {
			return;
		}
		if (e.target && e.target.matches && e.target.matches(".wenpai-file__input")) {
			return;
		}
		var root = closest(drop, ".wenpai-file");
		var inp = drop.querySelector(".wenpai-file__input") || (root && root.querySelector(".wenpai-file__input"));
		if (root && inp && !inp.disabled) {
			e.preventDefault();
			inp.click();
		}
	});

	document.addEventListener("keydown", function (e) {
		var drop = e.target && e.target.closest ? e.target.closest(".wenpai-file__drop") : null;
		if (!drop || (e.key !== "Enter" && e.key !== " ")) {
			return;
		}
		var root = closest(drop, ".wenpai-file");
		var inp = root && root.querySelector(".wenpai-file__input");
		if (root && inp && !inp.disabled && e.target === drop) {
			e.preventDefault();
			inp.click();
		}
	});

	/* Capture phase: products may consume input.files and reset .value in their
	 * own change handlers (e.g. import-then-clear); the kit must see the FileList
	 * before that happens. */
	document.addEventListener("change", function (e) {
		var inp = e.target;
		if (!inp || !inp.matches || !inp.matches(".wenpai-file__input") || inp.disabled) {
			return;
		}
		var root = closest(inp, ".wenpai-file");
		if (root) {
			if (fileIsLocal(root)) {
				fileLocalAdd(root, inp.files);
			} else {
				fileUploadAll(root, inp.files);
				inp.value = "";
			}
		}
	}, true);

	document.addEventListener("dragover", function (e) {
		var drop = e.target && e.target.closest ? e.target.closest(".wenpai-file__drop") : null;
		if (drop) {
			e.preventDefault();
			var dinp = drop.querySelector(".wenpai-file__input");
			if (!dinp || !dinp.disabled) {
				drop.classList.add("is-drag");
			}
		}
	});
	document.addEventListener("dragleave", function (e) {
		var drop = e.target && e.target.closest ? e.target.closest(".wenpai-file__drop") : null;
		if (drop && !drop.contains(e.relatedTarget)) {
			drop.classList.remove("is-drag");
		}
	});
	document.addEventListener("drop", function (e) {
		var drop = e.target && e.target.closest ? e.target.closest(".wenpai-file__drop") : null;
		if (!drop) {
			return;
		}
		e.preventDefault();
		drop.classList.remove("is-drag");
		var root = closest(drop, ".wenpai-file");
		var dropInp = drop.querySelector(".wenpai-file__input");
		if (root && e.dataTransfer && dropInp && !dropInp.disabled) {
			if (fileIsLocal(root)) {
				/* Keep input.files in sync so form posts and .files reads work
				 * exactly like a picker selection. */
				try {
					var dt = new DataTransfer();
					for (var fi = 0; fi < e.dataTransfer.files.length; fi++) {
						dt.items.add(e.dataTransfer.files[fi]);
					}
					dropInp.files = dt.files;
				} catch (err) {
					/* Older engines: CustomEvent detail.files still carries them. */
				}
				fileLocalAdd(root, e.dataTransfer.files);
			} else {
				fileUploadAll(root, e.dataTransfer.files);
			}
		}
	});

	document.addEventListener("click", function (e) {
		var rem = e.target && e.target.closest ? e.target.closest(".wenpai-file__remove") : null;
		if (!rem) {
			return;
		}
		var item = closest(rem, ".wenpai-file__item");
		var root = closest(rem, ".wenpai-file");
		if (item && root) {
			/* The item's index before removal; hidden inputs may live inside
			 * the item or as siblings in the list — drop both shapes. */
			var list = root.querySelector(".wenpai-file__list");
			var siblings = list ? list.querySelectorAll(".wenpai-file__item") : [];
			var oldIdx = -1;
			for (var i = 0; i < siblings.length; i++) {
				if (siblings[i] === item) {
					oldIdx = i;
				}
			}
			var base = fileFieldName(root);
			if (base !== "" && oldIdx >= 0) {
				var pat = new RegExp("^" + base.replace(/[.*+?^${}()|[\]\\]/g, "\\$&") + "\\[" + oldIdx + "\\]\\[");
				var hs = root.querySelectorAll("input[type=\"hidden\"]");
				for (var h = 0; h < hs.length; h++) {
					if (pat.test(hs[h].name)) {
						hs[h].parentNode.removeChild(hs[h]);
					}
				}
			}
			if (item.parentNode) {
				item.parentNode.removeChild(item);
			}
			fileRenumber(root);
		}
	});

	/* ---- tags ---------------------------------------------------------------
	 * .wenpai-tags[data-wenpai-tags] > .wenpai-tags__chips > .wenpai-tags__chip
	 * (text + button.wenpai-tags__chip-x) + input.wenpai-tags__input
	 * Hidden inputs name="{field}[]" live inside .wenpai-tags; 0 tags = one
	 * sentinel empty value (same convention as multicheck).
	 */

	function tagsHiddenName(root) {
		var h = root.querySelector("input[type=\"hidden\"]");
		if (h && h.name) {
			return h.name;
		}
		return dataAttr(root, ["data-name", "data-wenpai-name", "data-field"]);
	}

	function tagsState(root) {
		if (!root._wenpaiTags) {
			var vals = [];
			var hs = root.querySelectorAll("input[type=\"hidden\"]");
			for (var i = 0; i < hs.length; i++) {
				if (hs[i].value !== "") {
					vals.push(hs[i].value);
				}
			}
			root._wenpaiTags = vals;
		}
		return root._wenpaiTags;
	}

	function rebuildTags(root) {
		var chips = root.querySelector(".wenpai-tags__chips");
		var input = root.querySelector(".wenpai-tags__input");
		var name = tagsHiddenName(root);
		if (!chips || !input) {
			return;
		}
		var olds = chips.querySelectorAll(".wenpai-tags__chip");
		for (var i = 0; i < olds.length; i++) {
			olds[i].parentNode.removeChild(olds[i]);
		}
		/* Rebuild hidden inputs (they may sit outside .wenpai-tags__chips). */
		var hs = root.querySelectorAll("input[type=\"hidden\"]");
		for (var j = 0; j < hs.length; j++) {
			hs[j].parentNode.removeChild(hs[j]);
		}
		var tags = tagsState(root);
		for (var k = 0; k < tags.length; k++) {
			var chip = document.createElement("span");
			chip.className = "wenpai-tags__chip";
			chip.appendChild(document.createTextNode(tags[k]));
			var x = document.createElement("button");
			x.type = "button";
			x.className = "wenpai-tags__chip-x";
			x.setAttribute("aria-label", "移除");
			x.textContent = "×";
			chip.appendChild(x);
			/* The text input may sit outside .wenpai-tags__chips (PHP layout);
			 * appending keeps chip order = tag order either way. */
			chips.appendChild(chip);
		}
		if (name) {
			var values = tags.length ? tags : [""];
			for (var v = 0; v < values.length; v++) {
				var h = document.createElement("input");
				h.type = "hidden";
				h.name = name;
				h.value = values[v];
				root.appendChild(h);
			}
		}
	}

	function addTag(root, raw) {
		var tag = String(raw || "").trim();
		if (!tag) {
			return;
		}
		var tags = tagsState(root);
		if (tags.indexOf(tag) !== -1) {
			return;
		}
		tags.push(tag);
		rebuildTags(root);
	}

	document.addEventListener("keydown", function (e) {
		var inp = e.target;
		if (!inp || !inp.matches || !inp.matches(".wenpai-tags__input")) {
			return;
		}
		var root = closest(inp, ".wenpai-tags");
		if (!root || e.isComposing || e.keyCode === 229) {
			return;
		}
		if (e.key === "Enter" || e.key === "," || e.key === "、" || e.key === "，" || e.key === ";") {
			var v = inp.value;
			if (String(v).trim() !== "") {
				e.preventDefault();
				addTag(root, v);
				inp.value = "";
			}
			return;
		}
		if (e.key === "Backspace" && inp.value === "") {
			var tags = tagsState(root);
			if (tags.length) {
				e.preventDefault();
				tags.pop();
				rebuildTags(root);
				var focus = root.querySelector(".wenpai-tags__input");
				if (focus) {
					focus.focus();
				}
			}
		}
	});

	document.addEventListener("click", function (e) {
		var x = e.target && e.target.closest ? e.target.closest(".wenpai-tags__chip-x") : null;
		if (!x) {
			return;
		}
		var chip = closest(x, ".wenpai-tags__chip");
		var root = closest(x, ".wenpai-tags");
		var chips = root && root.querySelector(".wenpai-tags__chips");
		if (!chip || !chips || !root) {
			return;
		}
		var all = chips.querySelectorAll(".wenpai-tags__chip");
		var idx = -1;
		for (var i = 0; i < all.length; i++) {
			if (all[i] === chip) {
				idx = i;
			}
		}
		if (idx >= 0) {
			var tags = tagsState(root);
			if (idx < tags.length) {
				tags.splice(idx, 1);
				rebuildTags(root);
			}
		}
	});

	/* ---- range ----------------------------------------------------------------
	 * .wenpai-range > input[type=range] + output.wenpai-range__out
	 * Unit suffix: data-unit / data-wenpai-unit, else keep the output's own
	 * non-numeric suffix (PHP may have rendered "40%").
	 */

	function rangeSync(root) {
		var inp = root.querySelector("input[type=\"range\"]");
		var out = root.querySelector(".wenpai-range__out");
		if (!inp || !out) {
			return;
		}
		var unit = root.getAttribute("data-unit") || root.getAttribute("data-wenpai-unit") || "";
		if (!unit) {
			/* PHP may have rendered the suffix already ("40%"): keep whatever
			 * non-numeric tail the output currently shows. */
			unit = (out.textContent || "").replace(/^[\-+]?[\d.,]+\s*/, "");
		}
		out.textContent = inp.value + unit;
	}

	document.addEventListener("input", function (e) {
		var inp = e.target;
		if (!inp || !inp.matches || !inp.matches(".wenpai-range input[type=\"range\"]")) {
			return;
		}
		var root = closest(inp, ".wenpai-range");
		if (root) {
			rangeSync(root);
		}
	});

	function rangeInit() {
		var roots = document.querySelectorAll(".wenpai-range");
		for (var i = 0; i < roots.length; i++) {
			var out = roots[i].querySelector(".wenpai-range__out");
			if (out && out.textContent.trim() === "") {
				rangeSync(roots[i]);
			}
		}
	}
	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", rangeInit);
	} else {
		rangeInit();
	}

	/* ---- icon picker -----------------------------------------------------------
	 * .wenpai-icon > input[type=text] (the stored name, not .wenpai-icon__filter)
	 *   + .wenpai-icon__preview + button.wenpai-icon__pick
	 *   + .wenpai-icon__picker[hidden] > input.wenpai-icon__filter
	 *     + .wenpai-icon__grid > button.wenpai-icon__opt[data-name] (svg + name)
	 */

	function iconNameInput(root) {
		return root.querySelector("input[type=\"text\"]:not(.wenpai-icon__filter)");
	}

	function iconOpts(root) {
		if (!root._wenpaiIconOpts) {
			var grid = root.querySelector(".wenpai-icon__grid");
			root._wenpaiIconOpts = grid ? Array.prototype.slice.call(grid.querySelectorAll(".wenpai-icon__opt")) : [];
		}
		return root._wenpaiIconOpts;
	}

	function iconPreview(root, name) {
		var prev = root.querySelector(".wenpai-icon__preview");
		var opt = null;
		var opts = iconOpts(root);
		for (var i = 0; i < opts.length; i++) {
			var on = opts[i].getAttribute("data-name") === name;
			opts[i].classList.toggle("is-current", on);
			if (on) {
				opt = opts[i];
			}
		}
		if (prev) {
			prev.innerHTML = "";
			prev.classList.toggle("is-empty", !opt);
			if (opt) {
				var svg = opt.querySelector("svg");
				if (svg) {
					prev.appendChild(svg.cloneNode(true));
				}
			}
		}
	}

	function iconPicker(root) {
		return root.querySelector(".wenpai-icon__picker");
	}

	document.addEventListener("click", function (e) {
		var pick = e.target && e.target.closest ? e.target.closest(".wenpai-icon__pick") : null;
		if (pick) {
			var proot = closest(pick, ".wenpai-icon");
			var p = proot && iconPicker(proot);
			if (p) {
				e.preventDefault();
				p.hidden = !p.hidden;
				var f = p.querySelector(".wenpai-icon__filter");
				if (!p.hidden && f && f.focus) {
					f.focus();
				}
			}
			return;
		}
		var opt = e.target && e.target.closest ? e.target.closest(".wenpai-icon__opt") : null;
		if (opt) {
			var oroot = closest(opt, ".wenpai-icon");
			var picker = oroot && iconPicker(oroot);
			var inp = oroot && iconNameInput(oroot);
			if (oroot && inp) {
				e.preventDefault();
				var name = opt.getAttribute("data-name") || "";
				inp.value = name;
				iconPreview(oroot, name);
				if (picker) {
					picker.hidden = true;
				}
				if (typeof window.Event === "function") {
					inp.dispatchEvent(new window.Event("input", { bubbles: true }));
					inp.dispatchEvent(new window.Event("change", { bubbles: true }));
				}
			}
			return;
		}
		/* Outside click closes every open icon picker. */
		var open = document.querySelectorAll(".wenpai-icon__picker:not([hidden])");
		for (var i = 0; i < open.length; i++) {
			var host = closest(open[i], ".wenpai-icon");
			if (host && !host.contains(e.target)) {
				open[i].hidden = true;
			}
		}
	});

	document.addEventListener("input", function (e) {
		var inp = e.target;
		/* PHP renders the filter as type=search and the name input as
		 * type=text; accept any input inside .wenpai-icon and branch. */
		if (!inp || !inp.matches || !inp.matches(".wenpai-icon input")) {
			return;
		}
		if (inp.matches(".wenpai-icon__filter")) {
			var froot = closest(inp, ".wenpai-icon");
			if (!froot) {
				return;
			}
			var q = String(inp.value || "").trim().toLowerCase();
			var opts = iconOpts(froot);
			for (var i = 0; i < opts.length; i++) {
				opts[i].hidden = q !== "" && String(opts[i].getAttribute("data-name") || "").toLowerCase().indexOf(q) === -1;
			}
			return;
		}
		var root = closest(inp, ".wenpai-icon");
		if (root) {
			iconPreview(root, inp.value);
		}
	});

	document.addEventListener("keydown", function (e) {
		if (e.key !== "Escape") {
			return;
		}
		var inp = e.target;
		if (!inp || !inp.matches || !inp.matches(".wenpai-icon input")) {
			return;
		}
		var root = closest(inp, ".wenpai-icon");
		var p = root && iconPicker(root);
		if (p && !p.hidden) {
			p.hidden = true;
		}
	});
})();

/* 0.9.2 field generate button: fill a text/secret control with a random
   value entirely client-side (no ajax roundtrip for keys/secrets). */
(function () {
	"use strict";

	function randBytes(n) {
		var buf = new Uint8Array(n);
		if (window.crypto && window.crypto.getRandomValues) {
			window.crypto.getRandomValues(buf);
		} else {
			for (var i = 0; i < n; i++) {
				buf[i] = Math.floor(Math.random() * 256);
			}
		}
		return buf;
	}

	function generateValue(length, charset) {
		var bytes = randBytes(length);
		var out = "";
		if (charset === "hex") {
			var hex = "0123456789abcdef";
			for (var i = 0; i < length; i++) {
				out += hex[bytes[i] & 15] + hex[(bytes[i] >> 4) & 15];
				if (out.length >= length) {
					break;
				}
			}
			return out.slice(0, length);
		}
		var alpha = charset === "alnum" ? "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789" : "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_";
		for (var j = 0; j < length; j++) {
			out += alpha[bytes[j] % alpha.length];
		}
		return out;
	}

	document.addEventListener("click", function (e) {
		var btn = e.target && e.target.closest ? e.target.closest("[data-wenpai-generate-for]") : null;
		if (!btn || btn.disabled) {
			return;
		}
		var id = btn.getAttribute("data-wenpai-generate-for") || "";
		var inp = id ? document.getElementById(id) : null;
		if (!inp) {
			return;
		}
		var length = parseInt(btn.getAttribute("data-wenpai-generate-length") || "32", 10) || 32;
		var charset = btn.getAttribute("data-wenpai-generate-charset") || "hex";
		inp.value = generateValue(length, charset);
		inp.dispatchEvent(new Event("input", { bubbles: true }));
		inp.dispatchEvent(new Event("change", { bubbles: true }));
	});
})();

/* 0.9.2 dirty guard: forms opt in with data-wenpai-dirty-guard; the browser's
   native beforeunload prompt fires only after a real input/change. */
(function () {
	"use strict";

	function markDirty(e) {
		var t = e.target;
		var f = t && t.closest ? t.closest("form[data-wenpai-dirty-guard]") : null;
		if (f) {
			f.setAttribute("data-wenpai-dirty", "1");
		}
	}
	document.addEventListener("input", markDirty);
	document.addEventListener("change", markDirty);
	document.addEventListener("submit", function (e) {
		var f = e.target;
		if (f && f.matches && f.matches("form[data-wenpai-dirty-guard]")) {
			f.removeAttribute("data-wenpai-dirty");
		}
	});
	window.addEventListener("beforeunload", function (e) {
		if (document.querySelector("form[data-wenpai-dirty-guard][data-wenpai-dirty]")) {
			e.preventDefault();
			e.returnValue = "";
		}
	});
})();

/* 0.12.0 sticky savebar, ID-based conditional blocks ([data-wenpai-when]), and
   instant-toggle toast feedback (window.wenpaiToast). */
(function () {
	"use strict";

	function formForSavebar(bar) {
		var fid = bar.getAttribute("data-wenpai-savebar-for");
		if (fid) {
			return document.getElementById(fid);
		}
		return bar.closest ? bar.closest("form") : null;
	}

	function snapshotForm(form) {
		var snap = [];
		var els = form.elements || [];
		for (var i = 0; i < els.length; i++) {
			var el = els[i];
			if (!el.name || el.disabled || el.type === "submit" || el.type === "reset" || el.type === "button") {
				continue;
			}
			if (el.type === "checkbox" || el.type === "radio") {
				snap.push(el.name + ":" + el.value + "=" + (el.checked ? "1" : "0"));
			} else {
				snap.push(el.name + "=" + el.value);
			}
		}
		return snap.join("&");
	}

	function syncSavebar(bar) {
		var form = formForSavebar(bar);
		if (!form) {
			return;
		}
		if (typeof form._wenpaiInitialSnap !== "string") {
			form._wenpaiInitialSnap = snapshotForm(form);
		}
		bar.hidden = snapshotForm(form) === form._wenpaiInitialSnap;
	}

	function syncAllSavebars() {
		var bars = document.querySelectorAll("[data-wenpai-savebar]");
		for (var i = 0; i < bars.length; i++) {
			syncSavebar(bars[i]);
		}
	}

	function syncWhenBlocks() {
		var blocks = document.querySelectorAll(".wenpai-app [data-wenpai-when]");
		for (var i = 0; i < blocks.length; i++) {
			var block = blocks[i];
			var sel = block.getAttribute("data-wenpai-when") || "";
			if (!sel) {
				continue;
			}
			var ctl = sel.charAt(0) === "#" ? document.getElementById(sel.slice(1)) : document.querySelector(sel);
			if (!ctl) {
				continue;
			}
			var expected = block.getAttribute("data-wenpai-when-value");
			var matched = false;
			if (ctl.type === "checkbox" || ctl.type === "radio") {
				matched = expected !== null ? (ctl.checked && String(ctl.value) === expected) : !!ctl.checked;
			} else {
				matched = expected !== null ? String(ctl.value) === expected : Boolean(ctl.value);
			}
			if (block.hasAttribute("data-wenpai-when-not")) {
				matched = !matched;
			}
			block.hidden = !matched;
			if (!matched) {
				block.setAttribute("inert", "");
			} else {
				block.removeAttribute("inert");
			}
		}
	}

	document.addEventListener("input", function () {
		syncAllSavebars();
		syncWhenBlocks();
	});
	document.addEventListener("change", function () {
		syncAllSavebars();
		syncWhenBlocks();
	});
	document.addEventListener("reset", function (e) {
		var f = e.target;
		setTimeout(function () {
			if (f && f.querySelectorAll) {
				var cbs = f.querySelectorAll("input, select, textarea");
				for (var i = 0; i < cbs.length; i++) {
					cbs[i].dispatchEvent(new Event("change", { bubbles: true }));
				}
			}
			syncAllSavebars();
			syncWhenBlocks();
		}, 0);
	});
	document.addEventListener("wenpai:saved", function (e) {
		var f = e.target;
		if (f && f.elements) {
			f._wenpaiInitialSnap = snapshotForm(f);
		}
		syncAllSavebars();
	});

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", function () {
			syncAllSavebars();
			syncWhenBlocks();
		});
	} else {
		syncAllSavebars();
		syncWhenBlocks();
	}

	var toastTimer = null;
	window.wenpaiToast = function (message, tone) {
		var app = document.querySelector(".wenpai-app");
		if (!app || !message) {
			return;
		}
		var node = app.querySelector(".wenpai-toast");
		if (!node) {
			node = document.createElement("div");
			node.setAttribute("role", "status");
			node.setAttribute("aria-live", "polite");
			app.appendChild(node);
		}
		node.className = "wenpai-toast" + (tone === "ok" || tone === "bad" ? " is-" + tone : "");
		node.textContent = String(message);
		node.hidden = false;
		if (toastTimer) {
			clearTimeout(toastTimer);
		}
		toastTimer = setTimeout(function () {
			if (node) {
				node.hidden = true;
			}
		}, 2600);
	};
})();

/* 0.13.0 overview hub primitives: stat_hub drawer, zero-jump view_tabs/view_panes,
   and ad_slot carousel + single-column dismiss reflow. */
(function () {
	"use strict";

	function setDrawerState(drawerId, open, opts) {
		if (!drawerId) {
			return;
		}
		var drawer = document.getElementById(drawerId);
		var hub = document.querySelector('[data-wenpai-stat-hub="' + drawerId + '"]');
		var bar = document.querySelector('[data-wenpai-drawer-open="' + drawerId + '"]');
		if (opts && typeof opts.hasIssue === "boolean" && bar) {
			bar.setAttribute("data-wenpai-has-issue", opts.hasIssue ? "1" : "0");
		}
		if (opts && typeof opts.alertText === "string" && bar) {
			var txt = bar.querySelector("[data-wenpai-alert-text]");
			if (txt) {
				txt.textContent = opts.alertText;
			}
		}
		var isOpen = Boolean(open);
		if (drawer) {
			drawer.classList.toggle("is-open", isOpen);
			drawer.hidden = !isOpen;
		}
		var hasIssue = bar && bar.getAttribute("data-wenpai-has-issue") === "1";
		var showBar = Boolean(hasIssue && !isOpen);
		if (bar) {
			bar.classList.toggle("is-visible", showBar);
		}
		if (hub) {
			hub.classList.toggle("has-drawer-visible", isOpen || showBar);
		}
		var toggles = document.querySelectorAll('[data-wenpai-drawer-toggle="' + drawerId + '"]');
		for (var i = 0; i < toggles.length; i++) {
			var btn = toggles[i];
			btn.classList.toggle("is-open", isOpen);
			btn.setAttribute("aria-expanded", isOpen ? "true" : "false");
			var span = btn.querySelector("[data-wenpai-toggle-text]");
			var closedLbl = btn.getAttribute("data-wenpai-label-closed") || "通道详情";
			var openLbl = btn.getAttribute("data-wenpai-label-open") || "收起通道";
			if (span) {
				span.textContent = isOpen ? openLbl : closedLbl;
			}
		}
	}

	window.wenpaiSyncStatHub = function (drawerId, opts) {
		var drawer = drawerId ? document.getElementById(drawerId) : null;
		var curOpen = Boolean(drawer && drawer.classList.contains("is-open"));
		var nextOpen = opts && typeof opts.open === "boolean" ? opts.open : curOpen;
		setDrawerState(drawerId, nextOpen, opts || {});
	};

	function activateViewTab(btn) {
		var tablist = btn.closest ? btn.closest("[data-wenpai-tabs]") : null;
		if (!tablist) {
			return;
		}
		var group = tablist.getAttribute("data-wenpai-tabs");
		var target = btn.getAttribute("data-wenpai-tab");
		if (!group || !target) {
			return;
		}
		var buttons = tablist.querySelectorAll("[data-wenpai-tab]");
		for (var i = 0; i < buttons.length; i++) {
			var active = buttons[i] === btn;
			buttons[i].classList.toggle("is-active", active);
			buttons[i].setAttribute("aria-selected", active ? "true" : "false");
		}
		var containers = document.querySelectorAll('[data-wenpai-panes="' + group + '"]');
		for (var c = 0; c < containers.length; c++) {
			var panes = containers[c].querySelectorAll("[data-wenpai-pane]");
			for (var p = 0; p < panes.length; p++) {
				var match = panes[p].getAttribute("data-wenpai-pane") === target;
				panes[p].classList.toggle("is-inactive", !match);
				if (match) {
					panes[p].removeAttribute("inert");
				} else {
					panes[p].setAttribute("inert", "");
				}
			}
		}
		var card = btn.closest ? btn.closest(".card") : null;
		if (card) {
			var nextTitle = btn.getAttribute("data-wenpai-card-title");
			if (nextTitle) {
				var h = card.querySelector(":scope > .card-head .card-title");
				if (h) {
					h.textContent = nextTitle;
				}
			}
			var foot = card.querySelector(":scope > .card-foot");
			if (foot) {
				if (btn.hasAttribute("data-wenpai-foot-meta")) {
					var m = foot.querySelector(".meta");
					if (m) {
						m.textContent = btn.getAttribute("data-wenpai-foot-meta") || "";
					}
				}
				if (btn.hasAttribute("data-wenpai-foot-label")) {
					var a = foot.querySelector("a.btn");
					if (a) {
						a.textContent = btn.getAttribute("data-wenpai-foot-label") || "";
						if (btn.hasAttribute("data-wenpai-foot-href")) {
							a.setAttribute("href", btn.getAttribute("data-wenpai-foot-href") || "#");
						}
					}
				}
			}
		}
		try {
			tablist.dispatchEvent(new CustomEvent("wenpai:tab-change", { bubbles: true, detail: { group: group, tab: target } }));
		} catch (err) {
			/* Ignore older runtimes without CustomEvent constructor. */
		}
	}

	function stepAdSlide(btn) {
		var slot = btn.closest ? btn.closest("[data-wenpai-ad-slot]") : null;
		if (!slot) {
			return;
		}
		var slides = slot.querySelectorAll("[data-wenpai-ad-slide]");
		if (!slides.length) {
			return;
		}
		var cur = 0;
		for (var i = 0; i < slides.length; i++) {
			if (!slides[i].classList.contains("is-inactive")) {
				cur = i;
				break;
			}
		}
		var delta = parseInt(btn.getAttribute("data-wenpai-ad-step") || "1", 10) || 1;
		var next = (cur + delta + slides.length) % slides.length;
		for (var s = 0; s < slides.length; s++) {
			var on = s === next;
			slides[s].classList.toggle("is-inactive", !on);
			if (on) {
				slides[s].removeAttribute("inert");
			} else {
				slides[s].setAttribute("inert", "");
			}
		}
		var idxEl = slot.querySelector("[data-wenpai-ad-index]");
		if (idxEl) {
			idxEl.textContent = String(next + 1) + " / " + String(slides.length);
		}
		var activeSlide = slides[next];
		var footMeta = slot.querySelector("[data-wenpai-ad-foot-meta]");
		if (footMeta && activeSlide.hasAttribute("data-wenpai-foot-meta")) {
			footMeta.textContent = activeSlide.getAttribute("data-wenpai-foot-meta") || "";
		}
		var footCta = slot.querySelector("[data-wenpai-ad-foot-cta]");
		if (footCta && activeSlide.hasAttribute("data-wenpai-cta-label")) {
			footCta.textContent = activeSlide.getAttribute("data-wenpai-cta-label") || "";
			if (activeSlide.hasAttribute("data-wenpai-cta-url")) {
				footCta.setAttribute("href", activeSlide.getAttribute("data-wenpai-cta-url") || "#");
			}
		}
	}

	document.addEventListener("click", function (e) {
		var target = e.target;
		if (!target || !target.closest) {
			return;
		}
		var toggleBtn = target.closest("[data-wenpai-drawer-toggle]");
		if (toggleBtn) {
			var tid = toggleBtn.getAttribute("data-wenpai-drawer-toggle");
			var d = tid ? document.getElementById(tid) : null;
			setDrawerState(tid, !(d && d.classList.contains("is-open")));
			return;
		}
		var openBtn = target.closest("[data-wenpai-drawer-open]");
		if (openBtn) {
			setDrawerState(openBtn.getAttribute("data-wenpai-drawer-open"), true);
			return;
		}
		var closeBtn = target.closest("[data-wenpai-drawer-close]");
		if (closeBtn) {
			setDrawerState(closeBtn.getAttribute("data-wenpai-drawer-close"), false);
			return;
		}
		var viewTab = target.closest("[data-wenpai-tab]");
		if (viewTab) {
			activateViewTab(viewTab);
			return;
		}
		var adStep = target.closest("[data-wenpai-ad-step]");
		if (adStep) {
			stepAdSlide(adStep);
			return;
		}
		var adDismiss = target.closest("[data-wenpai-ad-dismiss]");
		if (adDismiss) {
			var slot = adDismiss.closest("[data-wenpai-ad-slot]");
			if (slot) {
				slot.hidden = true;
				var split = slot.closest(".wenpai-split--reverse");
				if (split) {
					split.classList.add("is-single");
				}
				try {
					slot.dispatchEvent(new CustomEvent("wenpai:ad-dismiss", { bubbles: true }));
				} catch (err) {
					/* Ignore older runtimes without CustomEvent constructor. */
				}
			}
		}
	});
})();
