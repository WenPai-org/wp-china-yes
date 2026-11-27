/**
 * WPCY 4.0 shared admin core utilities (REST client, notices, formatters, DOM helpers).
 */
(function () {
	'use strict';

	const cfg = window.wpcyAdmin || {};
	const restRoot = (cfg.restRoot || '').replace(/\/+$/, '');
	const nonce = cfg.nonce || '';
	const initializers = [];

	function makePill(tone, label, withDot) {
		const sp = document.createElement('span');
		sp.className = ['pill', tone || 'mute'].join(' ');
		if (withDot) {
			const dot = document.createElement('span');
			dot.className = 'dot';
			dot.setAttribute('aria-hidden', 'true');
			sp.appendChild(dot);
		}
		sp.appendChild(document.createTextNode(label));
		return sp;
	}

	function notify(level, text) {
		if (window.wenpaiAdmin && typeof window.wenpaiAdmin.notice === 'function') {
			window.wenpaiAdmin.notice({ level: level, text: text, fade: true });
			return;
		}
		if (level === 'bad') {
			console.error('[wpcy]', text);
		}
	}

	function toast(level, text) {
		if (!text) return;
		if (typeof window.wenpaiToast === 'function') {
			window.wenpaiToast(text, level === 'bad' || level === 'warn' ? 'bad' : 'ok');
			return;
		}
		notify(level, text);
	}

	function showToast(msg, tone) {
		if (typeof window.wenpaiToast === 'function') {
			window.wenpaiToast(msg, tone || 'ok');
		} else {
			notify(tone || 'ok', msg);
		}
	}

	function stashFlashToast(level, text) {
		try {
			window.sessionStorage.setItem('wpcy_flash_toast', JSON.stringify({ level: level, text: text }));
		} catch (e) {}
	}

	function consumeFlashToast() {
		try {
			const raw = window.sessionStorage.getItem('wpcy_flash_toast');
			if (!raw) return;
			window.sessionStorage.removeItem('wpcy_flash_toast');
			const item = JSON.parse(raw);
			if (item && item.text) {
				setTimeout(function () {
					toast(item.level || 'ok', String(item.text));
				}, 120);
			}
		} catch (e) {}
	}

	async function api(path, options) {
		const opts = Object.assign({ method: 'GET', headers: {} }, options || {});
		opts.headers['X-WP-Nonce'] = nonce;
		if (opts.body && typeof opts.body !== 'string') {
			opts.headers['Content-Type'] = 'application/json';
			opts.body = JSON.stringify(opts.body);
		}
		const res = await fetch(restRoot + '/wpcy/v1' + path, opts);
		const data = await res.json().catch(function () { return {}; });
		if (!res.ok) {
			const msg = (data && data.message) ? data.message : '暂时无法完成请求，请稍后重试。';
			throw new Error(msg);
		}
		return data;
	}

	function settingsEndpoint(isNetwork) {
		return isNetwork ? '/network-settings' : '/settings';
	}

	function setStatbarCell(statsEl, idx, numStr, unitStr) {
		const dds = statsEl.querySelectorAll('.wenpai-statbar__item dd');
		const dd = dds[idx];
		if (!dd) return;
		dd.classList.remove('is-none');
		dd.textContent = unitStr ? (String(numStr) + ' ') : String(numStr);
		if (unitStr) {
			const sm = document.createElement('small');
			sm.textContent = unitStr;
			dd.appendChild(sm);
		}
	}

	function formatShortTime(raw) {
		if (!raw) return '';
		const d = new Date(raw);
		if (isNaN(d.getTime())) return '';
		const pad = function (n) { return n < 10 ? '0' + n : String(n); };
		return pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
	}

	function formatShortDate(raw) {
		if (!raw) return '';
		const d = new Date(raw);
		if (isNaN(d.getTime())) return '';
		const pad = function (n) { return n < 10 ? '0' + n : String(n); };
		return pad(d.getMonth() + 1) + '-' + pad(d.getDate());
	}

	function updateStatusListRow(dl, idx, valueText, state, stateLabel) {
		const rows = dl ? dl.querySelectorAll('.wenpai-status-list__row') : [];
		const row = rows[idx];
		if (!row) return;
		const dd = row.querySelector('dd');
		if (!dd) return;
		dd.textContent = valueText + ' ';
		if (state) {
			const sp = document.createElement('span');
			sp.className = 'wenpai-status-list__state is-' + state;
			sp.textContent = stateLabel || state;
			dd.appendChild(sp);
		}
	}

	function copyTextToClipboard(text) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			return navigator.clipboard.writeText(text);
		}
		return new Promise(function (resolve, reject) {
			try {
				const ta = document.createElement('textarea');
				ta.value = text;
				ta.setAttribute('readonly', 'readonly');
				ta.className = 'screen-reader-text';
				document.body.appendChild(ta);
				ta.select();
				const ok = document.execCommand('copy');
				document.body.removeChild(ta);
				if (ok) resolve();
				else reject(new Error('copy failed'));
			} catch (err) {
				reject(err);
			}
		});
	}

	window.wpcyAdminKit = {
		cfg: cfg,
		makePill: makePill,
		notify: notify,
		toast: toast,
		showToast: showToast,
		stashFlashToast: stashFlashToast,
		consumeFlashToast: consumeFlashToast,
		api: api,
		settingsEndpoint: settingsEndpoint,
		setStatbarCell: setStatbarCell,
		formatShortTime: formatShortTime,
		formatShortDate: formatShortDate,
		updateStatusListRow: updateStatusListRow,
		copyTextToClipboard: copyTextToClipboard,
		registerInit: function (fn) {
			if (typeof fn === 'function') {
				initializers.push(fn);
			}
		},
		boot: function () {
			consumeFlashToast();
			initializers.forEach(function (fn) {
				fn();
			});
		}
	};
})();
