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

	function setStatbarCell(statsEl, idx, numStr, unitStr) {
		const dds = statsEl.querySelectorAll('.wenpai-statbar__item dd');
		const dd = dds[idx];
		if (!dd) return;
		dd.classList.remove('is-none');
		dd.textContent = String(numStr) + ' ';
		if (unitStr) {
			const sm = document.createElement('small');
			sm.textContent = unitStr;
			dd.appendChild(sm);
		}
	}

	function renderTrendSvg(series) {
		const days = Array.isArray(series) && series.length ? series.slice(-7) : [];
		const pts = days.length ? days.map(function (r) {
			const total = (Number(r.wporg_rewrites) || 0) + (Number(r.public_asset_rewrites) || 0) + (Number(r.avatar_rewrites) || 0);
			return { label: String(r.date || '').slice(5) || '—', val: total };
		}) : [
			{ label: 'D-6', val: 0 }, { label: 'D-5', val: 0 }, { label: 'D-4', val: 0 },
			{ label: 'D-3', val: 0 }, { label: 'D-2', val: 0 }, { label: '昨天', val: 0 }, { label: '今天', val: 0 }
		];
		const maxVal = Math.max(10, Math.max.apply(null, pts.map(function (p) { return p.val; })));
		const x0 = 44;
		const x1 = 612;
		const yTop = 26;
		const yBot = 148;
		const step = pts.length > 1 ? (x1 - x0) / (pts.length - 1) : 0;
		const coords = pts.map(function (p, i) {
			const x = Math.round(x0 + i * step);
			const y = Math.round(yBot - (p.val / maxVal) * (yBot - yTop));
			return { x: x, y: y, label: p.label, val: p.val };
		});
		const poly = coords.map(function (c) { return c.x + ',' + c.y; }).join(' ');
		const area = 'M ' + coords[0].x + ' ' + yBot + ' L ' + coords.map(function (c) { return c.x + ' ' + c.y; }).join(' L ') + ' L ' + coords[coords.length - 1].x + ' ' + yBot + ' Z';
		let circles = '';
		let labels = '';
		coords.forEach(function (c) {
			circles += '<circle cx="' + c.x + '" cy="' + c.y + '" r="3.5" fill="var(--wenpai-accent)"/>';
			labels += '<text x="' + c.x + '" y="168" text-anchor="middle">' + c.label + '</text>';
		});
		return '<svg data-wenpai-art viewBox="0 0 640 180" width="100%" height="180" role="img" aria-label="近 7 日加速趋势">'
			+ '<defs><linearGradient id="wpcy-g" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="var(--wenpai-accent)" stop-opacity="0.18"/><stop offset="100%" stop-color="var(--wenpai-accent)" stop-opacity="0.0"/></linearGradient></defs>'
			+ '<g stroke="var(--wenpai-line-soft)" stroke-width="1">'
			+ '<line x1="44" y1="26" x2="612" y2="26"/><line x1="44" y1="87" x2="612" y2="87"/><line x1="44" y1="148" x2="612" y2="148"/>'
			+ '</g>'
			+ '<path d="' + area + '" fill="url(#wpcy-g)"/>'
			+ '<polyline fill="none" stroke="var(--wenpai-accent)" stroke-width="2" points="' + poly + '"/>'
			+ circles
			+ '<g fill="var(--wenpai-ink-3)" font-size="11">'
			+ '<text x="36" y="30" text-anchor="end">' + maxVal + '</text>'
			+ '<text x="36" y="91" text-anchor="end">' + Math.round(maxVal / 2) + '</text>'
			+ '<text x="36" y="152" text-anchor="end">0</text>'
			+ labels
			+ '</g></svg>';
	}

	function applyTargetsToOverview(targets, statsEl, meterEl) {
		if (!Array.isArray(targets) || !targets.length) return;
		const byTarget = {};
		const latencies = [];
		targets.forEach(function (t) {
			if (t && t.target) byTarget[t.target] = t;
			if (t && typeof t.latency_ms === 'number' && t.latency_ms > 0) {
				latencies.push(t.latency_ms);
			}
		});
		if (statsEl && latencies.length) {
			const avg = Math.round(latencies.reduce(function (a, b) { return a + b; }, 0) / latencies.length);
			setStatbarCell(statsEl, 1, avg, 'ms');
		}
		if (meterEl) {
			const wporg = byTarget['api.wenpai.net'] || byTarget['downloads.wenpai.net'];
			const pub = byTarget['cdnjs.admincdn.com'] || byTarget['jsd.admincdn.com'];
			const ava = byTarget['cravatar.com'] || byTarget['cn.cravatar.com'] || targets.find(function (t) { return t && /cravatar/i.test(t.target); });
			const rows = meterEl.querySelectorAll('.wenpai-meter__row');
			[wporg, pub, ava].forEach(function (item, idx) {
				const row = rows[idx];
				if (!row || !item) return;
				const valEl = row.querySelector('.wenpai-meter__value');
				const barEl = row.querySelector('.wenpai-meter__bar > i');
				const ms = item.latency_ms != null ? item.latency_ms : null;
				if (valEl) valEl.textContent = ms != null ? (ms + 'ms') : (item.result === 'down' ? '不可达' : '正常');
				if (barEl && ms != null) {
					const pct = Math.max(6, Math.min(100, Math.round((ms / 800) * 100)));
					barEl.style.inlineSize = pct + '%';
				}
			});
		}
	}

	// 1. Overview: hydrate statbar, trend chart, latency meter, and recent events.
	function initOverview() {
		const statsEl = document.querySelector('[data-wpcy-stats]');
		const trendEl = document.querySelector('[data-wpcy-trend]');
		const meterEl = document.querySelector('[data-wpcy-latency]');
		if (statsEl || trendEl) {
			api('/stats?days=7').then(function (data) {
				const totals = (data && data.totals) ? data.totals : {};
				const totalAcc = (Number(totals.wporg_rewrites) || 0) + (Number(totals.public_asset_rewrites) || 0) + (Number(totals.avatar_rewrites) || 0);
				if (statsEl) {
					setStatbarCell(statsEl, 0, totalAcc, '次');
					setStatbarCell(statsEl, 2, Number(totals.avatar_rewrites) || 0, '次');
					setStatbarCell(statsEl, 3, Number(totals.blocked_outbound) || 0, '次');
				}
				if (trendEl) {
					const plot = trendEl.querySelector('.wenpai-chart__plot') || trendEl;
					plot.innerHTML = renderTrendSvg(data && data.series);
				}
			}).catch(function () {});
		}

		if (statsEl || meterEl) {
			api('/diagnostics').then(function (data) {
				const targets = Array.isArray(data && data.targets) ? data.targets : [];
				if (targets.length) {
					applyTargetsToOverview(targets, statsEl, meterEl);
				} else {
					api('/diagnostics/run', { method: 'POST' }).then(function (fresh) {
						applyTargetsToOverview(fresh && fresh.targets, statsEl, meterEl);
					}).catch(function () {});
				}
			}).catch(function () {});
		}

		const eventsEl = document.querySelector('[data-wpcy-events]');
		if (eventsEl) {
			api('/events?per_page=5').then(function (data) {
				const list = Array.isArray(data && data.events) ? data.events : [];
				if (!list.length) {
					eventsEl.innerHTML = '<p class="meta">最近还没有新的加速或巡检事件。</p>';
					return;
				}
				const ul = document.createElement('ul');
				ul.className = 'wenpai-log';
				list.slice(0, 5).forEach(function (ev) {
					const li = document.createElement('li');
					const when = ev.at || ev.when || '';
					const label = ev.type || ev.summary || '事件';
					li.textContent = (when ? when + ' · ' : '') + label;
					ul.appendChild(li);
				});
				eventsEl.innerHTML = '';
				eventsEl.appendChild(ul);
			}).catch(function () {
				eventsEl.innerHTML = '<p class="meta">最近还没有新的加速或巡检事件。</p>';
			});
		}
	}

	// 2. Settings: instant toggles + unified form save.
	function initSettings() {
		const form = document.getElementById('wpcy-settings-form');
		if (!form) return;
		const isNetwork = form.getAttribute('data-wpcy-network') === '1';

		form.querySelectorAll('input[name="profile"]').forEach(function (radio) {
			radio.addEventListener('change', function () {
				form.querySelectorAll('.choice').forEach(function (lbl) {
					lbl.classList.toggle('is-on', !!lbl.querySelector('input:checked'));
				});
			});
		});

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

	function renderServerTargets(srvList, targets) {
		if (!srvList || !Array.isArray(targets) || !targets.length) return;
		const map = {};
		targets.forEach(function (t) { if (t && t.target) map[t.target] = t; });
		const picks = [
			map['api.wenpai.net'],
			map['downloads.wenpai.net'],
			map['cravatar.com'] || map['cn.cravatar.com'] || targets.find(function (t) { return t && /cravatar/i.test(t.target); }),
			map['cdnjs.admincdn.com'] || map['jsd.admincdn.com']
		];
		picks.forEach(function (item, idx) {
			if (!item) return;
			const ms = item.latency_ms != null ? (item.latency_ms + 'ms') : '—';
			const state = item.result === 'ok' ? 'ok' : (item.result === 'fallback' ? 'warn' : 'bad');
			const label = item.result === 'ok' ? '正常' : (item.result === 'fallback' ? '已回退' : '不可达');
			updateStatusListRow(srvList, idx, ms, state, label);
		});
	}

	async function runBrowserProbes(browserList) {
		if (!browserList) return;
		const probes = [
			{ idx: 0, url: 'https://cn.cravatar.com/avatar/00000000000000000000000000000000?d=mp&s=16' },
			{ idx: 1, url: 'https://cdnjs.admincdn.com/ajax/libs/jquery/3.7.1/jquery.min.js' },
			{ idx: 2, url: 'https://cn.windfonts.com/' }
		];
		for (let i = 0; i < probes.length; i++) {
			const p = probes[i];
			const t0 = performance.now();
			try {
				await fetch(p.url, { mode: 'no-cors', cache: 'no-store' });
				const ms = Math.max(1, Math.round(performance.now() - t0));
				const state = ms > 1000 ? 'warn' : 'ok';
				const label = ms > 1000 ? '较慢' : '正常';
				updateStatusListRow(browserList, p.idx, ms + 'ms', state, label);
			} catch (e) {
				updateStatusListRow(browserList, p.idx, '超时', 'bad', '不可达');
			}
		}
	}

	// 3. Diagnose & Services actions.
	function initDiagnoseAndServices() {
		const btn = document.querySelector('[data-wpcy-action="diagnose"]');
		const srvList = document.querySelector('[data-wpcy-diag-server]');
		const browserList = document.querySelector('[data-wpcy-diag-browser]');

		function triggerRun(showNotice) {
			if (btn) {
				btn.classList.add('is-busy');
				btn.setAttribute('aria-busy', 'true');
			}
			runBrowserProbes(browserList);
			api('/diagnostics/run', { method: 'POST' })
				.then(function (data) {
					renderServerTargets(srvList, data && data.targets);
					if (showNotice) notify('ok', '服务器与浏览器双向连通性检测已完成。');
				})
				.catch(function (err) {
					if (showNotice) notify('bad', err.message);
				})
				.finally(function () {
					if (btn) {
						btn.classList.remove('is-busy');
						btn.removeAttribute('aria-busy');
					}
				});
		}

		if (srvList) {
			api('/diagnostics').then(function (data) {
				const targets = Array.isArray(data && data.targets) ? data.targets : [];
				if (targets.length) {
					renderServerTargets(srvList, targets);
					runBrowserProbes(browserList);
				} else {
					triggerRun(false);
				}
			}).catch(function () {});
		}

		if (btn) {
			btn.addEventListener('click', function () {
				triggerRun(true);
			});
		}

		document.querySelectorAll('[data-wpcy-action="recovery-on"], [data-wpcy-action="recovery-off"]').forEach(function (el) {
			el.addEventListener('click', function () {
				const enable = el.getAttribute('data-wpcy-action') === 'recovery-on';
				if (enable && el.hasAttribute('data-wenpai-confirm') && el.dataset.wenpaiConfirmed !== '1') {
					return;
				}
				api('/recovery', { method: 'POST', body: { action: enable ? 'disable_rewrites' : 'exit' } })
					.then(function () {
						notify('ok', enable ? '已进入安全恢复模式。' : '已恢复正常运行。');
						window.location.reload();
					})
					.catch(function (err) { notify('bad', err.message); });
			});
		});

		document.querySelectorAll('[data-wpcy-action="bind"]').forEach(function (el) {
			el.addEventListener('click', function () {
				el.classList.add('is-busy');
				api('/binding/start', { method: 'POST' })
					.then(function (data) {
						if (data && data.authorize_url) {
							window.location.href = data.authorize_url;
						} else {
							notify('ok', '站点绑定请求已发起。');
						}
					})
					.catch(function (err) { notify('bad', err.message); })
					.finally(function () { el.classList.remove('is-busy'); });
			});
		});
	}

	// 4. Wizard: save chosen profile with defaults and navigate to overview.
	function initWizard() {
		const form = document.getElementById('wpcy-wizard-form');
		if (!form) return;
		form.querySelectorAll('input[name="profile"]').forEach(function (radio) {
			radio.addEventListener('change', function () {
				form.querySelectorAll('.choice').forEach(function (lbl) {
					lbl.classList.toggle('is-on', !!lbl.querySelector('input:checked'));
				});
			});
		});
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
		initDiagnoseAndServices();
		initWizard();
	});
})();
