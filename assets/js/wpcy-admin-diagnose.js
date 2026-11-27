/**
 * WPCY 4.0 Diagnose, Services, Site Blocklist, URL Tester, and Outbound Overview interactions.
 */
(function () {
	'use strict';

	const kit = window.wpcyAdminKit;
	if (!kit) return;

	const api = kit.api;
	const makePill = kit.makePill;
	const notify = kit.notify;
	const showToast = kit.showToast;
	const updateStatusListRow = kit.updateStatusListRow;

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
				if (enable && el.hasAttribute('data-wenpai-confirm') && !el.hasAttribute('data-wenpai-confirm-bypass')) {
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
				if (snap && (snap.status === 'bound' || snap.state === 'bound')) {
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
							const passportPill = document.querySelector('[data-wpcy-licenses-passport] .pill');
							if (passportPill) {
								passportPill.className = 'pill mute';
								passportPill.textContent = '未绑定';
							}
							notify('ok', '已解除当前站点绑定。');
							window.dispatchEvent(new CustomEvent('wpcy:binding-changed', { detail: { status: 'revoked' } }));
						})
						.catch(function (err) { notify('bad', err.message); })
						.finally(function () { el.classList.remove('is-busy'); });
					return;
				}
				api('/binding/start', { method: 'POST' })
					.then(function (data) {
						if (data && data.authorize_url) {
							window.location.href = data.authorize_url;
						} else if (data && data.status === 'bound') {
							el.dataset.wpcyBound = '1';
							el.textContent = '已绑定 · 解除绑定';
							el.classList.remove('btn-primary');
							el.classList.add('btn-secondary');
							const passportPill = document.querySelector('[data-wpcy-licenses-passport] .pill');
							if (passportPill) {
								passportPill.className = 'pill ok';
								passportPill.textContent = '已绑定';
							}
							notify('ok', '站点通行证绑定成功。');
							window.dispatchEvent(new CustomEvent('wpcy:binding-changed', { detail: { status: 'bound' } }));
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

		const auditCard = document.querySelector('[data-wpcy-audit-card]');
		if (auditCard) {
			const scoreEl = auditCard.querySelector('[data-wpcy-audit-score]');
			const fixBtn = auditCard.querySelector('[data-wpcy-audit-fix]');
			const refreshBtn = auditCard.querySelector('[data-wpcy-audit-refresh]');
			const summaryFoot = auditCard.querySelector('[data-wpcy-audit-summary]');

			function renderAuditSnapshot(audit) {
				if (!audit || !Array.isArray(audit.items)) return;
				const score = Number(audit.score) || 0;
				const tone = audit.status || 'ok';
				const fixCount = Number(audit.fixable_count) || 0;
				if (scoreEl) {
					scoreEl.className = 'pill ' + tone;
					scoreEl.textContent = score + ' 分 · ' + (tone === 'ok' ? '已就绪' : (fixCount > 0 ? '可一键优化' : '需留意'));
				}
				if (fixBtn) {
					fixBtn.disabled = fixCount === 0;
					fixBtn.textContent = fixCount > 0 ? ('一键修复（' + fixCount + ' 项）') : '已全部优化';
					fixBtn.classList.toggle('btn-primary', fixCount > 0);
					fixBtn.classList.toggle('btn-secondary', fixCount === 0);
				}
				audit.items.forEach(function (it) {
					const row = auditCard.querySelector('[data-wpcy-audit-item="' + it.id + '"]');
					if (!row) return;
					const pill = row.querySelector('.pill');
					if (pill) {
						pill.className = 'pill ' + (it.status || 'mute');
						pill.textContent = it.pill || '';
					}
					const desc = row.querySelector('.d');
					if (desc && it.detail) {
						desc.textContent = it.detail;
					}
				});
				if (summaryFoot) {
					const metaEl = summaryFoot.querySelector('.meta') || summaryFoot.firstElementChild;
					if (metaEl) {
						metaEl.textContent = fixCount > 0
							? ('发现 ' + fixCount + ' 项可自动优化的国内化配置，一键修复不影响第三方插件。')
							: '国内化核心配置已就绪，修复动作始终可逆。';
					}
				}
			}

			if (fixBtn) {
				fixBtn.addEventListener('click', function () {
					fixBtn.classList.add('is-busy');
					fixBtn.disabled = true;
					api('/tools/audit/fix', { method: 'POST' })
						.then(function (res) {
							if (res && res.audit) {
								renderAuditSnapshot(res.audit);
							}
							const before = res && typeof res.before_score === 'number' ? res.before_score : 0;
							const after = res && typeof res.after_score === 'number' ? res.after_score : before;
							const cnt = res && typeof res.fixed_count === 'number' ? res.fixed_count : 0;
							showToast(
								cnt > 0
									? ('已一键修复 ' + cnt + ' 项配置，国内化体检评分 ' + before + ' → ' + after + ' 分')
									: '当前配置已就绪，无需额外修复',
								'ok'
							);
						})
						.catch(function (err) {
							showToast((err && err.message) || '一键修复未完成，请稍后重试', 'bad');
							fixBtn.disabled = false;
						})
						.finally(function () {
							fixBtn.classList.remove('is-busy');
						});
				});
			}

			if (refreshBtn) {
				refreshBtn.addEventListener('click', function () {
					refreshBtn.classList.add('is-busy');
					refreshBtn.disabled = true;
					api('/tools/audit')
						.then(function (res) {
							renderAuditSnapshot(res);
							showToast('国内化体检结果已刷新', 'ok');
						})
						.catch(function (err) {
							showToast((err && err.message) || '刷新体检结果失败，请稍后重试', 'bad');
						})
						.finally(function () {
							refreshBtn.classList.remove('is-busy');
							refreshBtn.disabled = false;
						});
				});
			}
		}

		document.querySelectorAll('[data-wpcy-bundle-install]').forEach(function (bundleBtn) {
			bundleBtn.addEventListener('click', function () {
				const raw = String(bundleBtn.getAttribute('data-wpcy-bundle-install') || '');
				const slugs = raw.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
				if (!slugs.length) return;
				const origLabel = bundleBtn.textContent;
				bundleBtn.disabled = true;
				bundleBtn.classList.add('is-busy');

				let installed = 0;
				let failed = 0;
				let chain = Promise.resolve();
				slugs.forEach(function (slug, idx) {
					chain = chain.then(function () {
						bundleBtn.textContent = '正在安装 ' + (idx + 1) + '/' + slugs.length + '…';
						return api('/suite/install', { method: 'POST', body: { slug: slug } })
							.then(function () { installed += 1; })
							.catch(function () { failed += 1; });
					});
				});
				chain.finally(function () {
					bundleBtn.classList.remove('is-busy');
					if (failed === 0 && installed > 0) {
						bundleBtn.textContent = '已全部启用';
						const row = bundleBtn.closest('[data-wpcy-bundle-row]');
						const pill = row && row.querySelector('.pill');
						if (pill) {
							pill.className = 'pill ok';
							pill.textContent = '已全部启用';
						}
						if (typeof window.wenpaiToast === 'function') {
							window.wenpaiToast('组合装机包 ' + installed + ' 款组件已安装并启用，正在刷新…', 'ok');
						}
						window.setTimeout(function () { window.location.reload(); }, 900);
					} else {
						bundleBtn.disabled = false;
						bundleBtn.textContent = origLabel;
						const msg = installed > 0
							? ('已安装 ' + installed + ' 款，' + failed + ' 款失败，请稍后重试')
							: '组合安装未完成，请检查服务器写入权限或稍后重试';
						showToast(msg, 'bad');
					}
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
					if (el && sw && sw.checked) {
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
						showToast('已保存', 'ok');
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

	kit.registerInit(initDiagnoseAndServices);
	kit.registerInit(initSiteBlocklist);
	kit.registerInit(initUrlTester);
	kit.registerInit(initOutboundOverview);
})();
