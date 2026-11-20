/**
 * wpcy 4.0 lightweight progressive enhancement (Route A, zero dependencies).
 * Talks directly to existing REST /wpcy/v1/* endpoints and uses kit wenpaiAdmin.notice().
 */
(function () {
	'use strict';

	const cfg = window.wpcyAdmin || {};
	const restRoot = (cfg.restRoot || '').replace(/\/+$/, '');
	const nonce = cfg.nonce || '';

	function notify(level, text) {
		if (window.wenpaiAdmin && typeof window.wenpaiAdmin.notice === 'function') {
			window.wenpaiAdmin.notice({ level: level, text: text, fade: true });
			return;
		}
		if (level === 'bad') {
			console.error('[wpcy]', text);
		}
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
			const msg = (data && data.message) ? data.message : '请求失败，请稍后重试。';
			throw new Error(msg);
		}
		return data;
	}

	function settingsEndpoint(isNetwork) {
		return isNetwork ? '/network-settings' : '/settings';
	}

	// 1. Overview: hydrate statbar, recent events, and latest diagnostics.
	function initOverview() {
		const statsEl = document.querySelector('[data-wpcy-stats]');
		if (statsEl) {
			api('/stats').then(function (data) {
				const vals = statsEl.querySelectorAll('.wenpai-statbar__value');
				const d = data || {};
				const nums = [
					d.total_7d != null ? String(d.total_7d) : '0',
					d.avg_latency_ms != null ? String(d.avg_latency_ms) : '—',
					d.avatar_7d != null ? String(d.avatar_7d) : '0',
					d.blocked_7d != null ? String(d.blocked_7d) : '0',
				];
				vals.forEach(function (el, idx) {
					if (nums[idx] !== undefined && el.firstChild) {
						el.firstChild.nodeValue = nums[idx] + ' ';
					}
				});
			}).catch(function () {});
		}

		const eventsEl = document.querySelector('[data-wpcy-events]');
		if (eventsEl) {
			api('/events?limit=5').then(function (data) {
				const list = Array.isArray(data && data.items) ? data.items : (Array.isArray(data) ? data : []);
				if (!list.length) {
					eventsEl.innerHTML = '<p class="meta">最近还没有新的加速或巡检事件。</p>';
					return;
				}
				const ul = document.createElement('ul');
				ul.className = 'wenpai-log';
				list.slice(0, 5).forEach(function (ev) {
					const li = document.createElement('li');
					li.textContent = (ev.when || ev.time || '') + ' · ' + (ev.summary || ev.message || ev.type || '');
					ul.appendChild(li);
				});
				eventsEl.innerHTML = '';
				eventsEl.appendChild(ul);
			}).catch(function () {
				eventsEl.innerHTML = '<p class="meta">暂时无法读取最近记录。</p>';
			});
		}
	}

	// 2. Settings: instant toggles + unified form save.
	function initSettings() {
		const form = document.getElementById('wpcy-settings-form');
		if (!form) return;
		const isNetwork = form.getAttribute('data-wpcy-network') === '1';

		form.querySelectorAll('input[data-wpcy-instant="1"]').forEach(function (input) {
			input.addEventListener('change', function () {
				const checked = input.checked;
				const labelEl = input.parentElement && input.parentElement.querySelector('.wenpai-switch__label');
				if (labelEl) labelEl.textContent = checked ? '已开启' : '未开启';

				const patch = {};
				if (input.name === 'public_assets_enabled') {
					patch.public_assets = { scope: checked ? 'both' : 'off' };
				} else if (input.name === 'avatar_enabled') {
					patch.connectivity = { avatar: checked ? 'cravatar_cn' : 'off' };
				}
				api(settingsEndpoint(isNetwork), { method: 'PUT', body: patch })
					.then(function () { notify('ok', '设置已保存。'); })
					.catch(function (err) {
						input.checked = !checked;
						if (labelEl) labelEl.textContent = input.checked ? '已开启' : '未开启';
						notify('bad', err.message);
					});
			});
		});

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			const fd = new FormData(form);
			const payload = {
				profile: String(fd.get('profile') || 'domestic'),
				apply_defaults: false,
				public_assets: { scope: String(fd.get('public_assets_scope') || 'both') },
				connectivity: {
					wporg_mirror: String(fd.get('wporg_mirror') || 'auto'),
					avatar: String(fd.get('avatar_source') || 'cravatar_cn'),
					heartbeat: fd.get('heartbeat') ? 'on' : 'off',
					dashboard_feeds: fd.get('dashboard_feeds') ? 'block' : 'allow',
				},
			};
			api(settingsEndpoint(isNetwork), { method: 'PUT', body: payload })
				.then(function () { notify('ok', '全部设置已保存。'); })
				.catch(function (err) { notify('bad', err.message); });
		});
	}

	// 3. Diagnose: run live server diagnostics + recovery toggle.
	function initDiagnose() {
		const btn = document.querySelector('[data-wpcy-action="diagnose"]');
		const srvList = document.querySelector('[data-wpcy-diag-server]');
		if (btn && srvList) {
			btn.addEventListener('click', function () {
				btn.classList.add('is-busy');
				btn.setAttribute('aria-busy', 'true');
				api('/diagnostics', { method: 'POST' })
					.then(function (data) {
						const items = Array.isArray(data && data.checks) ? data.checks : [];
						if (items.length) {
							srvList.innerHTML = '';
							items.forEach(function (c) {
								const li = document.createElement('li');
								const k = document.createElement('span');
								k.className = 'k';
								k.textContent = c.label || c.id || '';
								const v = document.createElement('span');
								v.className = 'v';
								v.textContent = (c.latency_ms != null ? c.latency_ms + 'ms · ' : '') + (c.ok ? '正常' : '异常');
								li.appendChild(k);
								li.appendChild(v);
								srvList.appendChild(li);
							});
						}
						notify('ok', '连通性检测已完成。');
					})
					.catch(function (err) { notify('bad', err.message); })
					.finally(function () {
						btn.classList.remove('is-busy');
						btn.removeAttribute('aria-busy');
					});
			});
		}

		document.querySelectorAll('[data-wpcy-action="recovery-on"], [data-wpcy-action="recovery-off"]').forEach(function (el) {
			el.addEventListener('click', function () {
				const enable = el.getAttribute('data-wpcy-action') === 'recovery-on';
				api('/recovery', { method: 'POST', body: { enabled: enable } })
					.then(function () {
						notify('ok', enable ? '已进入安全恢复模式。' : '已恢复正常运行。');
						window.location.reload();
					})
					.catch(function (err) { notify('bad', err.message); });
			});
		});
	}

	// 4. Wizard: save chosen profile with defaults and navigate to overview.
	function initWizard() {
		const form = document.getElementById('wpcy-wizard-form');
		if (!form) return;
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			const fd = new FormData(form);
			const profile = String(fd.get('profile') || 'domestic');
			api('/settings', { method: 'PUT', body: { profile: profile, apply_defaults: true } })
				.then(function () {
					window.location.href = form.getAttribute('data-wpcy-home') || '?page=wpcy';
				})
				.catch(function (err) { notify('bad', err.message); });
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		initOverview();
		initSettings();
		initDiagnose();
		initWizard();
	});
})();
