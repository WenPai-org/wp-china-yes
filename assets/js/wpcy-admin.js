/**
 * WPCY 4.0 lightweight progressive enhancement (Route A, zero dependencies).
 * Communicates with existing REST `/wpcy/v1/*` endpoints and kit `wenpaiAdmin.notice()`.
 */
(function () {
	'use strict';

	const cfg = window.wpcyAdmin || {};
	const restRoot = (cfg.restRoot || '').replace(/\/+$/, '');
	const nonce = cfg.nonce || '';

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

	function setStatbarDesc(statsEl, idx, descText) {
		const items = statsEl.querySelectorAll('.wenpai-statbar__item');
		const item = items[idx];
		if (!item) return;
		let p = item.querySelector('p');
		if (!p) {
			p = document.createElement('p');
			item.appendChild(p);
		}
		p.textContent = descText;
	}

	function formatShortTime(raw) {
		if (!raw) return '';
		const d = new Date(raw);
		if (isNaN(d.getTime())) return '';
		const pad = function (n) { return n < 10 ? '0' + n : String(n); };
		return pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
	}

	function applyOverviewDiagnostics(targets, statsEl, ovSrvEl, checkedAtEl) {
		if (!Array.isArray(targets) || !targets.length) return;
		const byTarget = {};
		targets.forEach(function (t) {
			if (t && t.target) byTarget[t.target] = t;
		});
		const wporg = byTarget['api.wenpai.net'] || byTarget['downloads.wenpai.net'];
		const pub = byTarget['cdnjs.admincdn.com'] || byTarget['jsd.admincdn.com'];
		const ava = byTarget['cravatar.com'] || byTarget['cn.cravatar.com'] || targets.find(function (t) { return t && /cravatar/i.test(t.target); });

		if (ovSrvEl) {
			[wporg, pub, ava].forEach(function (item, idx) {
				if (!item) return;
				const ms = item.latency_ms != null ? (item.latency_ms + 'ms') : '—';
				const state = item.result === 'ok' ? 'ok' : (item.result === 'fallback' ? 'warn' : 'bad');
				const label = item.result === 'ok' ? '已连接' : (item.result === 'fallback' ? '已回退' : '未连接');
				updateStatusListRow(ovSrvEl, idx, ms, state, label);
			});
		}

		if (checkedAtEl) {
			const stamp = formatShortTime((wporg && wporg.checked_at) || (targets[0] && targets[0].checked_at));
			if (stamp) {
				checkedAtEl.textContent = '检测于 ' + stamp;
			}
		}

		if (statsEl && wporg) {
			const firstDd = statsEl.querySelector('.wenpai-statbar__item dd');
			const cur = firstDd ? firstDd.textContent.trim() : '';
			if (wporg.result === 'fallback' && cur === '已开启') {
				setStatbarCell(statsEl, 0, '已回退', '');
			} else if (wporg.result === 'ok' && cur === '已回退') {
				setStatbarCell(statsEl, 0, '已开启', '');
			}
		}
	}

	// 1. Overview: hydrate core statbar, dual server/browser probe, news feed, and recent events.
	function initOverview() {
		const statsEl = document.querySelector('[data-wpcy-stats]');
		const ovSrvEl = document.querySelector('[data-wpcy-ov-server]');
		const ovBrowserEl = document.querySelector('[data-wpcy-ov-browser]');
		const checkedAtEl = document.querySelector('[data-wpcy-checked-at]');
		const browserCheckedEl = document.querySelector('[data-wpcy-browser-checked]');
		const probeBtn = document.querySelector('[data-wpcy-overview-probe]');
		const eventsEl = document.querySelector('[data-wpcy-events]');
		const alertsEl = document.querySelector('[data-wpcy-alerts]');
		const newsEl = document.querySelector('[data-wpcy-news-list]');
		const migEl = document.querySelector('[data-wpcy-migration-banner]');

		if (statsEl) {
			api('/stats?days=7').then(function (data) {
				const totals = (data && data.totals) ? data.totals : {};
				const totalAcc = (Number(totals.wporg_rewrites) || 0) + (Number(totals.public_asset_rewrites) || 0) + (Number(totals.avatar_rewrites) || 0);
				const avatarCount = Number(totals.avatar_rewrites) || 0;
				const blockedCount = Number(totals.blocked_outbound) || 0;
				setStatbarCell(statsEl, 3, totalAcc, '次');
				setStatbarDesc(statsEl, 3, '头像 ' + avatarCount + ' 次 · 拦截慢外链 ' + blockedCount + ' 次');
			}).catch(function () {});
		}

		const eventLabels = {
			first_check: '完成首次连通性检测',
			route_recovered: '上游加速节点恢复正常',
			mirror_fallback: '镜像波动自动回退至官方源',
			route_fallback: '上游线路自动切换备用源',
			route_down: '上游节点不可达（已自动回退官方源）',
			update_check: '完成系统与插件更新检查',
			migrated: '已完成旧版配置平滑导入',
			profile_set: '更新站点场景设置',
			settings_saved: '保存加速设置',
			diagnostics_run: '执行双端连通性检测',
			recovery_entered: '进入安全恢复模式',
			recovery_exited: '恢复正常加速运行',
			fallback: '镜像波动自动回退至官方源',
			route_up: '上游加速节点恢复正常',
			binding_started: '发起站点绑定请求',
			binding_revoked: '解除站点绑定'
		};

		function formatEventTime(raw) {
			if (!raw) return '';
			const d = new Date(raw);
			if (isNaN(d.getTime())) return String(raw);
			const pad = function (n) { return n < 10 ? '0' + n : String(n); };
			return pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
		}

		const emptyEventsHtml = '<p class="meta">尚未产生线路切换或巡检记录，执行连通检测或发生自动回退时会在此记录。</p>';

		function loadEvents() {
			if (!eventsEl) return;
			api('/events?per_page=5').then(function (data) {
				const list = Array.isArray(data && data.events) ? data.events : [];
				if (!list.length) {
					eventsEl.innerHTML = emptyEventsHtml;
					return;
				}
				const ul = document.createElement('ul');
				ul.className = 'wpcy-event-list';
				list.slice(0, 5).forEach(function (ev) {
					const li = document.createElement('li');
					li.className = 'wpcy-event-item';
					const left = document.createElement('span');
					const rawType = ev.type || '';
					left.textContent = ev.title || eventLabels[rawType] || ev.summary || rawType || '运行状态记录';
					const right = document.createElement('span');
					right.className = 'wpcy-event-item__time';
					right.textContent = formatEventTime(ev.at || ev.when || '');
					li.appendChild(left);
					li.appendChild(right);
					ul.appendChild(li);
				});
				eventsEl.innerHTML = '';
				eventsEl.appendChild(ul);
			}).catch(function () {
				eventsEl.innerHTML = emptyEventsHtml;
			});
		}

		loadEvents();

		function probeBrowserWithStamp() {
			if (!ovBrowserEl) return Promise.resolve();
			return runBrowserProbes(ovBrowserEl).then(function () {
				if (browserCheckedEl) {
					browserCheckedEl.textContent = '实时直测 · 已完成';
				}
			});
		}

		if (ovBrowserEl) {
			probeBrowserWithStamp();
		}

		if (ovSrvEl) {
			api('/diagnostics').then(function (data) {
				const targets = Array.isArray(data && data.targets) ? data.targets : [];
				if (targets.length) {
					applyOverviewDiagnostics(targets, statsEl, ovSrvEl, checkedAtEl);
				} else {
					api('/diagnostics/run', { method: 'POST' }).then(function (fresh) {
						applyOverviewDiagnostics(fresh && fresh.targets, statsEl, ovSrvEl, checkedAtEl);
						loadEvents();
					}).catch(function () {});
				}
			}).catch(function () {});
		}

		if (probeBtn) {
			probeBtn.addEventListener('click', function () {
				probeBtn.classList.add('is-busy');
				probeBtn.setAttribute('aria-busy', 'true');
				probeBtn.disabled = true;
				if (checkedAtEl) checkedAtEl.textContent = '检测中…';
				if (browserCheckedEl) browserCheckedEl.textContent = '测速中…';
				if (ovBrowserEl) {
					for (let i = 0; i < 3; i++) {
						updateStatusListRow(ovBrowserEl, i, '测速中…', '', '');
					}
				}
				Promise.all([
					api('/diagnostics/run', { method: 'POST' }),
					probeBrowserWithStamp()
				]).then(function (res) {
					const fresh = res[0];
					applyOverviewDiagnostics(fresh && fresh.targets, statsEl, ovSrvEl, checkedAtEl);
					if (checkedAtEl) checkedAtEl.textContent = '刚刚检测';
					loadEvents();
					if (typeof window.wenpaiToast === 'function') {
						window.wenpaiToast('双端连通检测已完成', 'ok');
					} else {
						notify('ok', '双端连通检测已完成。');
					}
				}).catch(function (err) {
					notify('bad', err.message || '连通性检测未完成，请稍后重试。');
				}).finally(function () {
					probeBtn.classList.remove('is-busy');
					probeBtn.removeAttribute('aria-busy');
					probeBtn.disabled = false;
				});
			});
		}

		if (newsEl && newsEl.getAttribute('data-wpcy-news-enabled') === '1') {
			const emptyNewsHtml = '<p class="meta">当前没有未读社区动态与维护通知，每日定时同步后将在此展示。</p>';
			api('/announcements').then(function (data) {
				const items = Array.isArray(data && data.items) ? data.items : [];
				if (!items.length) {
					newsEl.innerHTML = emptyNewsHtml;
					return;
				}
				const ul = document.createElement('ul');
				ul.className = 'wpcy-event-list';
				items.slice(0, 5).forEach(function (item) {
					const body = item.summary || item.body || item.message || '';
					const labelText = (item.title ? item.title + (body ? '：' : '') : '') + body;

					if (alertsEl && item.tone === 'warn') {
						alertsEl.hidden = false;
						const banner = document.createElement('div');
						banner.className = 'notice warn';
						const sp = document.createElement('span');
						sp.textContent = labelText;
						if (item.url && /^https:\/\//i.test(item.url)) {
							sp.appendChild(document.createTextNode(' '));
							const al = document.createElement('a');
							al.href = item.url;
							al.target = '_blank';
							al.rel = 'noopener noreferrer';
							al.textContent = '查看详情';
							sp.appendChild(al);
						}
						banner.appendChild(sp);
						alertsEl.appendChild(banner);
					}

					const li = document.createElement('li');
					li.className = 'wpcy-event-item';
					let titleNode;
					if (item.url && /^https:\/\//i.test(item.url)) {
						titleNode = document.createElement('a');
						titleNode.href = item.url;
						titleNode.target = '_blank';
						titleNode.rel = 'noopener noreferrer';
						titleNode.textContent = item.title || labelText || '社区动态';
					} else {
						titleNode = document.createElement('span');
						titleNode.textContent = labelText || '社区动态';
					}

					const btn = document.createElement('button');
					btn.type = 'button';
					btn.className = 'btn btn-ghost btn-sm';
					btn.textContent = '已读';
					btn.addEventListener('click', function () {
						api('/announcements/' + encodeURIComponent(item.id) + '/dismiss', { method: 'POST' })
							.finally(function () {
								li.remove();
								if (!ul.children.length) {
									newsEl.innerHTML = emptyNewsHtml;
								}
							});
					});
					li.appendChild(titleNode);
					li.appendChild(btn);
					ul.appendChild(li);
				});
				newsEl.innerHTML = '';
				newsEl.appendChild(ul);
			}).catch(function () {
				newsEl.innerHTML = emptyNewsHtml;
			});
		}

		if (migEl) {
			api('/migration/report').then(function (rep) {
				if (!rep || rep.status === 'none') return;
				migEl.hidden = false;
				const div = document.createElement('div');
				div.className = 'notice info';
				const ver = rep.source_version ? ' ' + rep.source_version : '';
				const migratedCount = Array.isArray(rep.migrated) ? rep.migrated.length : 0;
				const span = document.createElement('span');
				span.textContent = '已从旧版文派叶子' + ver + ' 平滑导入 ' + migratedCount + ' 项兼容设置，原有配置已自动留存备份。';
				div.appendChild(span);
				migEl.appendChild(div);
			}).catch(function () {});
		}
	}

	// 2. Settings & Feature Forms: instant toggles + unified form save.
	function initSettings() {
		const form = document.getElementById('wpcy-settings-form');
		const isNetwork = !!(form && form.getAttribute('data-wpcy-network') === '1');

		if (form) {
			form.querySelectorAll('input[name="profile"]').forEach(function (radio) {
				radio.addEventListener('change', function () {
					form.querySelectorAll('.choice').forEach(function (lbl) {
						lbl.classList.toggle('is-on', !!lbl.querySelector('input:checked'));
					});
				});
			});
		}

		document.querySelectorAll('input[data-wpcy-instant="1"]').forEach(function (input) {
			input.addEventListener('change', function () {
				const checked = input.checked;
				const labelEl = input.parentElement && input.parentElement.querySelector('.wenpai-switch__label');
				if (labelEl) labelEl.textContent = checked ? '已开启' : '未开启';

				const patch = {};
				if (input.name === 'public_assets_enabled') {
					patch.connectivity = { public_assets: { scope: checked ? 'both' : 'off' } };
				} else if (input.name === 'avatar_enabled') {
					patch.connectivity = { avatar: checked ? 'cravatar_cn' : 'off' };
				} else if (input.name === 'icon_photos_enabled') {
					patch.connectivity = { icon_photos: { enabled: checked ? 'on' : 'off' } };
				} else if (input.name === 'admin_locale_follow') {
					patch.connectivity = { admin_locale_follow: checked };
					const card = input.closest('[data-wpcy-core-card]');
					const iconEl = card && card.querySelector('.tile');
					if (iconEl) iconEl.classList.toggle('ok', checked);
				} else if (input.name === 'admin_news_enabled') {
					patch.admin = { news_enabled: checked };
					const card = input.closest('[data-wpcy-core-card]');
					const iconEl = card && card.querySelector('.tile');
					if (iconEl) iconEl.classList.toggle('ok', checked);
				} else if (input.name === 'admin_group_suite_menus') {
					patch.admin = { group_suite_menus: checked };
				}
				api(settingsEndpoint(isNetwork), { method: 'PUT', body: patch })
					.then(function () {
						if (typeof window.wenpaiToast === 'function') {
							window.wenpaiToast('已保存', 'ok');
						} else {
							notify('ok', '已保存');
						}
						if (input.name === 'admin_group_suite_menus') {
							setTimeout(function () { window.location.reload(); }, 350);
						}
					})
					.catch(function (err) {
						input.checked = !checked;
						if (labelEl) labelEl.textContent = input.checked ? '已开启' : '未开启';
						notify('bad', err.message);
					});
			});
		});

		const wlToggleEl = form ? form.querySelector('[data-wpcy-wl-toggle]') : null;
		const wlFieldsEl = form ? form.querySelector('[data-wpcy-wl-fields]') : null;
		if (wlToggleEl && wlFieldsEl) {
			wlToggleEl.addEventListener('change', function () {
				const on = wlToggleEl.checked;
				wlFieldsEl.hidden = !on;
				const lbl = wlToggleEl.parentElement && wlToggleEl.parentElement.querySelector('.wenpai-switch__label');
				if (lbl) lbl.textContent = on ? '已开启' : '未开启';
			});
		}

		if (!form) return;

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			const fd = new FormData(form);
			const connPatch = {
				wordpress_org: String(fd.get('wporg_mirror') || 'auto'),
				public_assets: { scope: String(fd.get('public_assets_scope') || 'both') },
				avatar: String(fd.get('avatar_source') || 'cravatar_cn'),
				heartbeat: fd.get('heartbeat') ? 'on' : 'off',
				dashboard_feeds: fd.get('dashboard_feeds') ? 'block' : 'allow',
			};
			if (form.querySelector('select[name="icon_photos_mode"]')) {
				connPatch.icon_photos = { enabled: String(fd.get('icon_photos_mode') || 'off') };
			}
			const payload = {
				profile: String(fd.get('profile') || 'domestic'),
				apply_defaults: false,
				connectivity: connPatch,
			};
			if (form.querySelector('input[name="noise_form"]')) {
				payload.modules = { notice_control: !!fd.get('notice_control') };
				payload.admin = { hide_promo: !!fd.get('hide_promo') };
			}
			const wlToggle = form.querySelector('input[name="wl_enabled"]');
			if (wlToggle && !wlToggle.disabled) {
				const wlEnabled = wlToggle.type === 'checkbox' ? wlToggle.checked : wlToggle.value === '1';
				payload.admin = Object.assign({}, payload.admin || {}, {
					whitelabel: {
						enabled: wlEnabled,
						brand_name: String(fd.get('wl_brand_name') || '').trim(),
						help_url: String(fd.get('wl_help_url') || '').trim(),
						feedback_url: String(fd.get('wl_feedback_url') || '').trim(),
						docs_url: String(fd.get('wl_docs_url') || '').trim(),
						news_feed_url: String(fd.get('wl_news_feed_url') || '').trim(),
						logo_url: String(fd.get('wl_logo_url') || '').trim(),
						hide_logo: !!fd.get('wl_hide_logo'),
						hide_links: !!fd.get('wl_hide_links'),
					},
				});
			}
			api(settingsEndpoint(isNetwork), { method: 'PUT', body: payload })
				.then(function () {
					form.dispatchEvent(new Event('wenpai:saved', { bubbles: true }));
					notify('ok', '设置已保存。');
					if (payload.admin && payload.admin.whitelabel) {
						setTimeout(function () { window.location.reload(); }, 450);
					}
				})
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
			const label = item.result === 'ok' ? '已连接' : (item.result === 'fallback' ? '已回退' : '未连接');
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
				const label = ms > 1000 ? '响应偏慢' : '已连接';
				updateStatusListRow(browserList, p.idx, ms + 'ms', state, label);
			} catch (e) {
				updateStatusListRow(browserList, p.idx, '连接超时', 'bad', '未连接');
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
						notify('ok', enable ? '已进入安全恢复模式。' : '已恢复正常加速运行。');
						window.location.reload();
					})
					.catch(function (err) { notify('bad', err.message); });
			});
		});

		document.querySelectorAll('[data-wpcy-action="bind"]').forEach(function (el) {
			api('/binding').then(function (snap) {
				if (snap && snap.state === 'bound') {
					el.textContent = '已绑定 · 解除绑定';
					el.classList.remove('btn-primary');
					el.classList.add('btn-secondary');
					el.dataset.wpcyBound = '1';
				}
			}).catch(function () {});

			el.addEventListener('click', function () {
				el.classList.add('is-busy');
				if (el.dataset.wpcyBound === '1') {
					api('/binding', { method: 'DELETE' })
						.then(function () {
							el.dataset.wpcyBound = '0';
							el.textContent = '绑定本站';
							el.classList.remove('btn-secondary');
							el.classList.add('btn-primary');
							notify('ok', '已解除当前站点绑定。');
						})
						.catch(function (err) { notify('bad', err.message); })
						.finally(function () { el.classList.remove('is-busy'); });
					return;
				}
				api('/binding/start', { method: 'POST' })
					.then(function (data) {
						if (data && data.authorize_url) {
							window.location.href = data.authorize_url;
						} else {
							notify('ok', '已发起站点绑定请求。');
						}
					})
					.catch(function (err) { notify('bad', err.message); })
					.finally(function () { el.classList.remove('is-busy'); });
			});
		});

		document.querySelectorAll('[data-wpcy-catalog-filter]').forEach(function (box) {
			const chips = box.querySelectorAll('[data-wpcy-cat]');
			const items = box.querySelectorAll('[data-wpcy-cat-item]');
			chips.forEach(function (chip) {
				chip.addEventListener('click', function () {
					const cat = chip.getAttribute('data-wpcy-cat') || 'all';
					chips.forEach(function (c) { c.classList.toggle('is-active', c === chip); });
					items.forEach(function (it) {
						const match = cat === 'all' || it.getAttribute('data-wpcy-cat-item') === cat;
						it.hidden = !match;
					});
				});
			});
		});

		const svcScreen = document.querySelector('[data-wpcy-screen="services"]');
		if (svcScreen) {
			api('/diagnostics').then(function (data) {
				const targets = Array.isArray(data && data.targets) ? data.targets : [];
				const map = {
					'api.wenpai.net': ['wporg', 'WenPai.org'],
					'cdnjs.admincdn.com': ['admincdn', 'adminCDN'],
					'cravatar.com': ['cravatar', 'Cravatar'],
					'cn.cravatar.com': ['cravatar', 'Cravatar'],
					wporg_mirror: ['wporg', 'WenPai.org'],
					public_assets: ['admincdn', 'adminCDN'],
					cravatar: ['cravatar', 'Cravatar']
				};
				targets.forEach(function (t) {
					const m = t && map[t.target];
					if (!m || typeof t.latency_ms !== 'number' || t.latency_ms <= 0) return;
					const el = svcScreen.querySelector('[data-wpcy-core-metric-text="' + m[0] + '"]');
					const sw = svcScreen.querySelector('[data-wpcy-service-toggle="' + m[0] + '"]');
					if (el && (!sw || sw.checked)) {
						el.textContent = Math.round(t.latency_ms) + ' ms · ' + m[1];
					}
				});
			}).catch(function () {});
		}

		document.querySelectorAll('[data-wpcy-service-toggle]').forEach(function (input) {
			input.addEventListener('change', function () {
				const id = input.getAttribute('data-wpcy-service-toggle');
				const on = input.checked;
				const card = input.closest('[data-wpcy-core-card]');
				const iconEl = card && card.querySelector('.tile');
				const dotEl = card && card.querySelector('.wenpai-module-card__dot');
				const metricEl = card && card.querySelector('[data-wpcy-core-metric-text]');
				const labelEl = input.parentElement && input.parentElement.querySelector('.wenpai-switch__label');

				let patch = {};
				let nextMetric = '';
				if (id === 'wporg') {
					patch = { connectivity: { wordpress_org: on ? 'auto' : 'off' } };
					nextMetric = on ? 'WenPai.org 镜像 · 异常自动回退' : '直连 WordPress.org 官方源';
				} else if (id === 'admincdn') {
					patch = { connectivity: { public_assets: { scope: on ? 'both' : 'off' } } };
					nextMetric = on ? '后台与前台同时加速' : '未开启';
				} else if (id === 'cravatar') {
					patch = { connectivity: { avatar: on ? 'cravatar_cn' : 'off' } };
					nextMetric = on ? 'Cravatar 中国线路' : '未开启（原始 Gravatar）';
				} else if (id === 'noise') {
					patch = { connectivity: { heartbeat: on ? 'on' : 'off', dashboard_feeds: on ? 'block' : 'allow' } };
					nextMetric = on ? '心跳降频与外部源屏蔽已生效' : '保持 WordPress 默认行为';
				}

				if (labelEl) labelEl.textContent = on ? '已开启' : '未开启';
				if (iconEl) iconEl.classList.toggle('accent', on);
				if (dotEl) dotEl.classList.toggle('is-ok', on);
				if (metricEl && nextMetric) metricEl.textContent = nextMetric;

				api('/settings', { method: 'PUT', body: patch })
					.then(function () {
						if (typeof window.wenpaiToast === 'function') {
							window.wenpaiToast('已保存', 'ok');
						} else {
							notify('ok', '已保存');
						}
					})
					.catch(function (err) {
						input.checked = !on;
						if (labelEl) labelEl.textContent = input.checked ? '已开启' : '未开启';
						if (iconEl) iconEl.classList.toggle('accent', input.checked);
						notify('bad', err.message || '暂时无法保存设置，请稍后重试。');
					});
			});
		});
	}

	// 3b. Site Blocklist (`GET/PUT /site-blocklist`).
	function initSiteBlocklist() {
		const root = document.querySelector('[data-wpcy-blocklist]');
		if (!root) return;
		const chk = root.querySelector('[data-wpcy-bl-enabled]');
		const hostInput = root.querySelector('[data-wpcy-bl-host]');
		const matchSelect = root.querySelector('[data-wpcy-bl-match]');
		const noteInput = root.querySelector('[data-wpcy-bl-note]');
		const addBtn = root.querySelector('[data-wpcy-bl-add]');
		const listEl = root.querySelector('[data-wpcy-bl-list]');

		let state = { enabled: false, hosts: [] };

		function renderList() {
			if (chk) {
				chk.checked = !!state.enabled;
				const lbl = chk.parentElement && chk.parentElement.querySelector('.wenpai-switch__label');
				if (lbl) lbl.textContent = state.enabled ? '已开启' : '未开启';
			}
			if (!listEl) return;
			listEl.innerHTML = '';
			if (!state.hosts.length) {
				const li = document.createElement('li');
				li.className = 'wpcy-host-item';
				const span = document.createElement('span');
				span.className = 'wpcy-host-item__note';
				span.textContent = '尚未添加自定义拦截域名，需要屏蔽慢源时可在上方输入域名后添加。';
				li.appendChild(span);
				listEl.appendChild(li);
				return;
			}
			state.hosts.forEach(function (item, idx) {
				const li = document.createElement('li');
				li.className = 'wpcy-host-item';
				const main = document.createElement('div');
				main.className = 'wpcy-host-item__main';
				const code = document.createElement('code');
				code.textContent = item.host;
				const badge = makePill('mute', item.match === 'suffix' ? '含子域名' : '精确匹配', false);
				main.appendChild(code);
				main.appendChild(badge);
				if (item.note) {
					const note = document.createElement('span');
					note.className = 'wpcy-host-item__note';
					note.textContent = item.note;
					main.appendChild(note);
				}
				const rm = document.createElement('button');
				rm.type = 'button';
				rm.className = 'btn btn-ghost btn-sm';
				rm.textContent = '移除域名';
				rm.addEventListener('click', function () {
					const next = {
						enabled: state.enabled,
						hosts: state.hosts.filter(function (_, i) { return i !== idx; })
					};
					saveBlocklist(next, '已移除拦截域名。');
				});
				li.appendChild(main);
				li.appendChild(rm);
				listEl.appendChild(li);
			});
		}

		function saveBlocklist(next, msg) {
			api('/site-blocklist', { method: 'PUT', body: next })
				.then(function (saved) {
					state = {
						enabled: !!(saved && saved.enabled),
						hosts: Array.isArray(saved && saved.hosts) ? saved.hosts : []
					};
					renderList();
					if (msg) notify('ok', msg);
				})
				.catch(function (err) {
					renderList();
					notify('bad', err.message || '暂时无法保存拦截列表，请稍后重试。');
				});
		}

		api('/site-blocklist').then(function (data) {
			state = {
				enabled: !!(data && data.enabled),
				hosts: Array.isArray(data && data.hosts) ? data.hosts : []
			};
			renderList();
		}).catch(function () {});

		if (chk) {
			chk.addEventListener('change', function () {
				saveBlocklist({ enabled: chk.checked, hosts: state.hosts }, chk.checked ? '已开启自定义外链拦截表。' : '已关闭自定义外链拦截表。');
			});
		}

		if (addBtn) {
			addBtn.addEventListener('click', function () {
				const host = hostInput ? hostInput.value.trim().toLowerCase() : '';
				if (!host) {
					notify('warn', '请先输入需要拦截的域名。');
					return;
				}
				const entry = {
					host: host,
					match: matchSelect ? matchSelect.value : 'exact',
					note: noteInput ? noteInput.value.trim() : ''
				};
				const next = {
					enabled: state.enabled || true,
					hosts: state.hosts.concat([entry])
				};
				saveBlocklist(next, '已添加拦截域名：' + host);
				if (hostInput) hostInput.value = '';
				if (noteInput) noteInput.value = '';
			});
		}

		root.querySelectorAll('[data-wpcy-bl-preset]').forEach(function (chip) {
			chip.addEventListener('click', function () {
				if (hostInput) hostInput.value = chip.getAttribute('data-wpcy-bl-preset') || '';
				if (noteInput) noteInput.value = chip.getAttribute('data-wpcy-bl-preset-note') || '';
				if (hostInput) hostInput.focus();
			});
		});
	}

	// 3c. Outbound URL routing tester (`POST /residency/test`).
	function initUrlTester() {
		const root = document.querySelector('[data-wpcy-url-tester]');
		if (!root) return;
		const input = root.querySelector('[data-wpcy-url-input]');
		const btn = root.querySelector('[data-wpcy-url-submit]');
		const out = root.querySelector('[data-wpcy-url-output]');

		const layerLabels = {
			l0: '核心业务白名单（始终放行）',
			l1: '内置镜像与连接规则',
			l2: '自定义外链拦截表',
			noise_block: '后台慢源过滤规则',
			none: '未命中特殊规则'
		};
		const actionMap = {
			allow: { text: '直连放行', cls: 'mute' },
			reroute: { text: '走国内镜像加速', cls: 'ok' },
			record: { text: '放行并记录频次', cls: 'warn' },
			block: { text: '阻断请求', cls: 'bad' },
			ignore: { text: '直接放行', cls: 'mute' }
		};

		function runTest() {
			const url = input ? input.value.trim() : '';
			if (!url) {
				notify('warn', '请输入完整的 http:// 或 https:// 网址。');
				return;
			}
			if (btn) btn.classList.add('is-busy');
			api('/residency/test', { method: 'POST', body: { url: url } })
				.then(function (res) {
					if (!out) return;
					const layer = layerLabels[res && res.layer] || String(res && res.layer || 'none');
					const act = actionMap[res && res.action] || { text: String(res && res.action || 'allow'), cls: 'mute' };
					out.hidden = false;
					out.innerHTML = '';
					const left = document.createElement('span');
					left.textContent = (res && res.host ? res.host + ' · ' : '') + layer;
					const pill = makePill(act.cls, act.text, true);
					out.appendChild(left);
					out.appendChild(pill);
				})
				.catch(function (err) {
					notify('bad', err.message || '暂时无法完成网址检测，请确认输入了完整网址。');
				})
				.finally(function () {
					if (btn) btn.classList.remove('is-busy');
				});
		}

		if (btn) btn.addEventListener('click', runTest);
		if (input) {
			input.addEventListener('keydown', function (e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					runTest();
				}
			});
		}
		root.querySelectorAll('[data-wpcy-url-sample]').forEach(function (chip) {
			chip.addEventListener('click', function () {
				if (input) {
					input.value = chip.getAttribute('data-wpcy-url-sample') || '';
					runTest();
				}
			});
		});
	}

	// 3d. Outbound screen rules snapshot & recent log.
	function initOutboundOverview() {
		const layersEl = document.querySelector('[data-wpcy-outbound-layers]');
		if (layersEl) {
			api('/residency/protected').then(function (snap) {
				const l0Count = snap && snap.l0 && Array.isArray(snap.l0.hosts) ? snap.l0.hosts.length : 0;
				const l1Ver = snap && snap.l1 && snap.l1.version ? String(snap.l1.version) : '内置默认版';
				const noiseOn = !!(snap && snap.noise_block && snap.noise_block.enabled);
				const noiseCount = snap && snap.noise_block && Array.isArray(snap.noise_block.hosts) ? snap.noise_block.hosts.length : 0;
				updateStatusListRow(layersEl, 0, l0Count + ' 个核心业务域名', 'ok', '始终放行');
				updateStatusListRow(layersEl, 1, '规则版本 ' + l1Ver, 'ok', '已开启');
				updateStatusListRow(layersEl, 2, noiseCount + ' 个常见慢源域名', noiseOn ? 'ok' : 'mute', noiseOn ? '已开启' : '未开启');
			}).catch(function () {});
		}

		const logEl = document.querySelector('[data-wpcy-outbound-log]');
		if (logEl) {
			api('/residency/log?per_page=10').then(function (data) {
				const items = Array.isArray(data && data.items) ? data.items : [];
				if (!items.length) {
					logEl.innerHTML = '<p class="meta">近期没有触发记录的外部请求，产生外部连接时会自动汇总在此。</p>';
					return;
				}
				const ul = document.createElement('ul');
				ul.className = 'wpcy-host-list';
				items.forEach(function (row) {
					const li = document.createElement('li');
					li.className = 'wpcy-host-item';
					const main = document.createElement('span');
					main.className = 'wpcy-host-item__main';
					const code = document.createElement('code');
					code.textContent = row.host || '';
					const badge = makePill('mute', (row.count || 0) + ' 次', false);
					main.appendChild(code);
					main.appendChild(badge);
					const note = document.createElement('span');
					note.className = 'wpcy-host-item__note';
					note.textContent = (row.data_class || '') + (row.last_seen ? ' · ' + row.last_seen : '');
					li.appendChild(main);
					li.appendChild(note);
					ul.appendChild(li);
				});
				logEl.innerHTML = '';
				logEl.appendChild(ul);
			}).catch(function () {
				logEl.innerHTML = '<p class="meta">近期没有触发记录的外部请求，产生外部连接时会自动汇总在此。</p>';
			});
		}
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
		initSiteBlocklist();
		initUrlTester();
		initOutboundOverview();
		initWizard();
	});
})();
