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

	function toast(level, text) {
		if (!text) return;
		if (typeof window.wenpaiToast === 'function') {
			window.wenpaiToast(text, level === 'bad' || level === 'warn' ? 'bad' : 'ok');
			return;
		}
		notify(level, text);
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

	function renderTrendSvg(series) {
		const days = Array.isArray(series) && series.length ? series.slice(-7) : [];
		const pts = days.length ? days.map(function (r) {
			const total = (Number(r.wporg_rewrites) || 0) + (Number(r.public_asset_rewrites) || 0) + (Number(r.avatar_rewrites) || 0);
			return { label: String(r.date || '').slice(5) || '—', val: total };
		}) : [
			{ label: '6天前', val: 0 }, { label: '5天前', val: 0 }, { label: '4天前', val: 0 },
			{ label: '3天前', val: 0 }, { label: '前天', val: 0 }, { label: '昨天', val: 0 }, { label: '今天', val: 0 }
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
			+ '<g fill="var(--wenpai-ink-3)" font-size="12">'
			+ '<text x="36" y="30" text-anchor="end">' + maxVal + '</text>'
			+ '<text x="36" y="91" text-anchor="end">' + Math.round(maxVal / 2) + '</text>'
			+ '<text x="36" y="152" text-anchor="end">0</text>'
			+ labels
			+ '</g></svg>';
	}

	function setMetricTile(tilesEl, key, ms, state, subText) {
		if (!tilesEl) return;
		const tile = tilesEl.querySelector('[data-wpcy-tile="' + key + '"]');
		const valEl = tilesEl.querySelector('[data-wpcy-tile-val="' + key + '"]');
		const subEl = tilesEl.querySelector('[data-wpcy-tile-sub="' + key + '"] span');
		if (tile) {
			tile.classList.toggle('is-warn', state === 'warn');
			tile.classList.toggle('is-bad', state === 'bad');
		}
		if (valEl) {
			valEl.innerHTML = '';
			if (ms != null) {
				valEl.appendChild(document.createTextNode(String(ms)));
				const sm = document.createElement('small');
				sm.textContent = 'ms';
				valEl.appendChild(sm);
			} else {
				valEl.textContent = state === 'bad' ? '超时' : '—';
			}
		}
		if (subEl && subText) {
			subEl.textContent = subText;
		}
	}

	function setChannelCell(key, isIssue, okLabel) {
		const cell = document.querySelector('[data-wpcy-channel="' + key + '"]');
		const val = document.querySelector('[data-wpcy-channel-val="' + key + '"]');
		if (val && val.getAttribute('data-wpcy-takeover') === '1') {
			if (cell) {
				cell.classList.remove('is-warn');
			}
			return;
		}
		if (cell) {
			cell.classList.toggle('is-warn', !!isIssue);
		}
		if (val && okLabel) {
			val.textContent = isIssue ? '探测波动 · 已自动回退' : okLabel;
		}
	}

	function applyTargetsToOverview(targets, statsEl, tilesEl, checkedAtEl) {
		if (!Array.isArray(targets) || !targets.length) return;
		const byTarget = {};
		const latencies = [];
		let issueCount = 0;
		targets.forEach(function (t) {
			if (t && t.target) byTarget[t.target] = t;
			if (t && typeof t.latency_ms === 'number' && t.latency_ms > 0) {
				latencies.push(t.latency_ms);
			}
			if (t && (t.result === 'fallback' || t.result === 'down')) {
				issueCount += 1;
			}
		});
		if (statsEl && latencies.length) {
			const avg = Math.round(latencies.reduce(function (a, b) { return a + b; }, 0) / latencies.length);
			setStatbarCell(statsEl, 1, avg, 'ms');
		}
		const wporg = byTarget['api.wenpai.net'] || byTarget['downloads.wenpai.net'];
		const pub = byTarget['cdnjs.admincdn.com'] || byTarget['jsd.admincdn.com'];
		const ava = byTarget['cravatar.com'] || byTarget['cn.cravatar.com'] || targets.find(function (t) { return t && /cravatar/i.test(t.target); });
		if (tilesEl) {
			const defs = [
				{ key: 'wporg', item: wporg, okSub: '国内镜像', chOk: 'WenPai.org 国内源（免费）' },
				{ key: 'cravatar', item: ava, okSub: '中国线路', chOk: '中国大陆节点（免费）' },
				{ key: 'admincdn', item: pub, okSub: '前后台加速', chOk: 'adminCDN 前后台加速' }
			];
			defs.forEach(function (d) {
				if (!d.item) return;
				const ms = d.item.latency_ms != null ? d.item.latency_ms : null;
				const isWarn = d.item.result === 'fallback' || d.item.result === 'down';
				setMetricTile(tilesEl, d.key, ms, isWarn ? 'warn' : '', isWarn ? '已自动回退' : d.okSub);
				setChannelCell(d.key, isWarn, d.chOk);
			});
		}
		if (typeof window.wenpaiSyncStatHub === 'function') {
			window.wenpaiSyncStatHub('wpcy-channel-drawer', {
				hasIssue: issueCount > 0,
				alertText: issueCount + ' 项通道发生自动回退，系统正定期自动复检'
			});
		}
		const ledeEl = document.querySelector('[data-wpcy-overview-lede]');
		if (ledeEl && ledeEl.getAttribute('data-wpcy-recovery') !== '1') {
			const prof = ledeEl.getAttribute('data-wpcy-profile-label') || '国内站';
			const actCnt = parseInt(ledeEl.getAttribute('data-wpcy-active-count') || '4', 10) || 0;
			if (issueCount > 0) {
				ledeEl.textContent = '当前场景：' + prof + ' · ' + issueCount + ' 项通道自动回退';
			} else if (actCnt > 0) {
				ledeEl.textContent = '当前场景：' + prof + ' · ' + actCnt + ' 项核心通道正常';
			} else {
				ledeEl.textContent = '当前场景：' + prof + ' · 当前使用默认直连';
			}
		}
		if (checkedAtEl) {
			const stamp = formatShortTime((wporg && wporg.checked_at) || (targets[0] && targets[0].checked_at));
			if (stamp) {
				checkedAtEl.textContent = stamp + ' 更新 · 自动回退';
			}
		}
	}

	async function measureOverviewBrowser(tilesEl) {
		if (!tilesEl) return;
		const urls = [
			'https://cn.cravatar.com/avatar/00000000000000000000000000000000?d=mp&s=16',
			'https://cdnjs.admincdn.com/ajax/libs/jquery/3.7.1/jquery.min.js'
		];
		const samples = [];
		for (let i = 0; i < urls.length; i++) {
			const t0 = performance.now();
			try {
				await fetch(urls[i], { mode: 'no-cors', cache: 'no-store' });
				samples.push(Math.max(1, Math.round(performance.now() - t0)));
			} catch (e) {}
		}
		if (samples.length) {
			const avg = Math.round(samples.reduce(function (a, b) { return a + b; }, 0) / samples.length);
			setMetricTile(tilesEl, 'browser', avg, '', '访客侧可达');
		} else {
			setMetricTile(tilesEl, 'browser', null, 'warn', '连接波动');
		}
	}

	// 1. Overview: hydrate stat_hub, trend chart, metric_tiles, tag_feed (events + news), ad_slot, and quick tools.
	function initOverview() {
		const statsEl = document.querySelector('[data-wpcy-stats]');
		const trendEl = document.querySelector('[data-wpcy-trend]');
		const trendSumEl = document.querySelector('[data-wpcy-trend-summary]');
		const trendLegendEl = document.querySelector('[data-wpcy-trend-legend]');
		const tilesEl = document.querySelector('[data-wpcy-latency]');
		const quotasEl = document.querySelector('[data-wpcy-quotas]');
		const checkedAtEl = document.querySelector('[data-wpcy-checked-at]');
		const probeBtn = document.querySelector('[data-wpcy-overview-probe]');
		const eventsEl = document.querySelector('[data-wpcy-events]');
		const newsEl = document.querySelector('[data-wpcy-news]');
		const feedCardEl = document.querySelector('[data-wpcy-feed-card]');
		const newsManageEl = document.querySelector('[data-wpcy-news-manage]');
		const migEl = document.querySelector('[data-wpcy-migration-banner]');

		let cachedEvents = [];
		let cachedNotices = [];
		let ssrSugNodes = eventsEl ? Array.prototype.slice.call(eventsEl.querySelectorAll('li[data-wpcy-sug-row]')) : [];

		if (statsEl || trendEl) {
			api('/stats?days=7').then(function (data) {
				const totals = (data && data.totals) ? data.totals : {};
				const wporgCnt = Number(totals.wporg_rewrites) || 0;
				const pubCnt = Number(totals.public_asset_rewrites) || 0;
				const avaCnt = Number(totals.avatar_rewrites) || 0;
				const totalAcc = wporgCnt + pubCnt + avaCnt;
				if (statsEl) {
					setStatbarCell(statsEl, 0, totalAcc, '次');
					setStatbarCell(statsEl, 2, avaCnt, '次');
					setStatbarCell(statsEl, 3, Number(totals.blocked_outbound) || 0, '次');
				}
				if (trendSumEl) {
					trendSumEl.textContent = '累计 ' + totalAcc + ' 次请求';
				}
				if (trendLegendEl) {
					trendLegendEl.textContent = totalAcc > 0
						? ('静态库 ' + pubCnt + ' 次 · Cravatar 头像 ' + avaCnt + ' 次 · 更新镜像 ' + wporgCnt + ' 次')
						: '等待首批加速请求 · 产生访问后按日汇总';
				}
				if (quotasEl && pubCnt > 0) {
					const firstNum = quotasEl.querySelector('.wenpai-quota-item__num');
					if (firstNum && /基础免费额度/.test(firstNum.textContent || '')) {
						firstNum.textContent = '近 7 日 ' + pubCnt + ' 次加速';
					}
				}
				if (trendEl) {
					const plot = trendEl.querySelector('.wenpai-chart__plot') || trendEl;
					plot.innerHTML = renderTrendSvg(data && data.series);
				}
			}).catch(function () {});
		}

		const eventMeta = {
			first_check: { tag: '巡检', tone: '', text: '完成首次连通性检测' },
			route_recovered: { tag: '恢复', tone: '', text: '上游加速节点恢复正常' },
			mirror_fallback: { tag: '回退', tone: 'warn', text: '镜像波动自动回退至官方源' },
			route_fallback: { tag: '回退', tone: 'warn', text: '上游线路自动切换备用源' },
			route_down: { tag: '回退', tone: 'warn', text: '上游节点不可达，已回退官方源' },
			update_check: { tag: '巡检', tone: '', text: '完成系统与插件更新检查' },
			migrated: { tag: '迁移', tone: 'accent', text: '已完成旧版配置平滑导入' },
			profile_set: { tag: '配置', tone: '', text: '更新站点场景设置' },
			settings_saved: { tag: '配置', tone: '', text: '保存加速设置' },
			diagnostics_run: { tag: '巡检', tone: '', text: '执行双端连通性检测' },
			recovery_entered: { tag: '恢复', tone: 'warn', text: '进入安全恢复模式' },
			recovery_exited: { tag: '恢复', tone: '', text: '恢复正常加速运行' },
			fallback: { tag: '回退', tone: 'warn', text: '镜像波动自动回退至官方源' },
			route_up: { tag: '恢复', tone: '', text: '上游加速节点恢复正常' },
			binding_started: { tag: '站点', tone: 'accent', text: '发起站点通行证绑定请求' },
			binding_revoked: { tag: '站点', tone: '', text: '解除当前站点绑定' }
		};

		function buildTagFeedRow(tag, tone, title, url, note, when, onDismiss) {
			const li = document.createElement('li');
			const badge = document.createElement('span');
			badge.className = 'wenpai-micro-tag' + (tone ? ' is-' + tone : '');
			badge.textContent = tag;

			const textWrap = document.createElement('span');
			textWrap.className = 'wenpai-tag-feed__text';
			if (url && /^https:\/\//i.test(url)) {
				const a = document.createElement('a');
				a.href = url;
				a.target = '_blank';
				a.rel = 'noopener noreferrer';
				a.textContent = title;
				textWrap.appendChild(a);
			} else {
				const sp = document.createElement('span');
				sp.textContent = title;
				textWrap.appendChild(sp);
			}
			if (note) {
				const sm = document.createElement('small');
				sm.textContent = note;
				textWrap.appendChild(sm);
			}

			const side = document.createElement('span');
			side.className = 'wenpai-tag-feed__side';
			if (when) {
				const tm = document.createElement('span');
				tm.textContent = when;
				side.appendChild(tm);
			}
			if (typeof onDismiss === 'function') {
				const btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'btn btn-ghost btn-sm';
				btn.textContent = '已读';
				btn.addEventListener('click', function () { onDismiss(li); });
				side.appendChild(btn);
			}

			li.appendChild(badge);
			li.appendChild(textWrap);
			li.appendChild(side);
			return li;
		}

		ssrSugNodes.forEach(function (li) {
			const sugId = li.getAttribute('data-wpcy-sug-row') || '';
			const fixBtn = li.querySelector('[data-wpcy-fix-sug]');
			if (fixBtn) {
				fixBtn.addEventListener('click', function () {
					const fixKind = fixBtn.getAttribute('data-wpcy-fix-sug');
					let patch = null;
					let okMsg = '已开启';
					if (fixKind === 'dashboard_feeds') {
						patch = { connectivity: { dashboard_feeds: 'block' } };
						okMsg = '已开启仪表盘高速动态小工具替换';
					} else if (fixKind === 'wporg_mirror') {
						patch = { connectivity: { wordpress_org: 'auto' } };
						okMsg = '已开启国内更新镜像加速';
					} else if (fixKind === 'public_assets') {
						patch = { connectivity: { public_assets: { scope: 'both' } } };
						okMsg = '已开启公共静态库国内加速';
					}
					if (patch) {
						fixBtn.disabled = true;
						api('/settings', { method: 'PUT', body: patch })
							.then(function () {
								ssrSugNodes = ssrSugNodes.filter(function (n) { return n !== li; });
								renderEventsAndNews();
								toast('ok', okMsg);
							})
							.catch(function (err) {
								fixBtn.disabled = false;
								notify('bad', err.message);
							});
					}
				});
			}
			const disBtn = li.querySelector('[data-wpcy-dismiss-sug]');
			if (disBtn && sugId) {
				disBtn.addEventListener('click', function () {
					api('/announcements/' + encodeURIComponent(sugId) + '/dismiss', { method: 'POST' })
						.finally(function () {
							ssrSugNodes = ssrSugNodes.filter(function (n) { return n !== li; });
							renderEventsAndNews();
						});
				});
			}
		});

		function wrapScrollableFeed(ul) {
			const wrap = document.createElement('div');
			wrap.className = 'wenpai-tag-feed-scroll';
			wrap.appendChild(ul);
			return wrap;
		}

		function renderEventsAndNews() {
			if (eventsEl) {
				const ul = document.createElement('ul');
				ul.className = 'wenpai-tag-feed';
				ssrSugNodes.forEach(function (li) {
					ul.appendChild(li);
				});
				const pinned = cachedNotices.find(function (n) {
					return n && (n.pinned || n.tone === 'warn' || n.category === 'security');
				});
				if (pinned) {
					ul.appendChild(buildTagFeedRow(
						'通知',
						pinned.tone === 'warn' ? 'warn' : 'accent',
						pinned.title || pinned.message || '重要维护通知',
						pinned.url || '',
						'',
						formatShortDate(pinned.published_at || pinned.date || ''),
						function () {
							api('/announcements/' + encodeURIComponent(pinned.id) + '/dismiss?per_page=15', { method: 'POST' })
								.finally(function () {
									cachedNotices = cachedNotices.filter(function (n) { return n.id !== pinned.id; });
									renderEventsAndNews();
								});
						}
					));
				}
				let lastDedupeKey = '';
				cachedEvents.slice(0, 20).forEach(function (ev) {
					const rawType = ev.type || '';
					const meta = eventMeta[rawType] || { tag: '记录', tone: '', text: '运行状态记录' };
					let rowTitle = String(ev.title || meta.text || ev.summary || rawType)
						.replace(/([\u4e00-\u9fa5])\s+([\u4e00-\u9fa5])/g, '$1$2')
						.trim();
					const detail = String(ev.detail || '').trim();
					if ((rawType === 'first_check' || /首次线路检查/.test(rowTitle)) && detail && detail.length <= 16) {
						rowTitle = rowTitle.replace(/^首次线路检查完成$/, '线路巡检完成') + ' · ' + detail;
					}
					const dedupeKey = rawType + '|' + rowTitle;
					if (dedupeKey === lastDedupeKey) {
						return;
					}
					lastDedupeKey = dedupeKey;
					ul.appendChild(buildTagFeedRow(
						meta.tag,
						meta.tone,
						rowTitle,
						'',
						'',
						formatShortTime(ev.at || ev.when || ''),
						null
					));
				});
				if (!ul.children.length) {
					ul.appendChild(buildTagFeedRow('就绪', '', '各加速通道正按当前站点策略平稳运行', '', '', '', null));
				}
				eventsEl.innerHTML = '';
				eventsEl.appendChild(wrapScrollableFeed(ul));
			}

			if (newsEl) {
				const ul = document.createElement('ul');
				ul.className = 'wenpai-tag-feed';
				if (!cachedNotices.length) {
					ul.appendChild(buildTagFeedRow('茶馆', 'accent', '当前没有未读社区动态与维护通知', 'https://wptea.com/', '', '', null));
				} else {
					cachedNotices.slice(0, 15).forEach(function (item) {
						const src = item.source || '';
						const tag = item.category === 'release'
							? '版本'
							: (item.tone === 'warn'
								? '公告'
								: (src === 'wptea' ? '茶馆' : (src === 'one' ? '官方' : '动态')));
						const tone = item.tone === 'warn' ? 'warn' : ((tag === '版本' || tag === '茶馆') ? 'accent' : '');
						ul.appendChild(buildTagFeedRow(
							tag,
							tone,
							item.title || item.message || '社区更新动态',
							item.url || '',
							'',
							formatShortDate(item.published_at || item.date || ''),
							item.id ? function () {
								api('/announcements/' + encodeURIComponent(item.id) + '/dismiss?per_page=15', { method: 'POST' })
									.finally(function () {
										cachedNotices = cachedNotices.filter(function (n) { return n.id !== item.id; });
										renderEventsAndNews();
									});
							} : null
						));
					});
				}
				newsEl.innerHTML = '';
				newsEl.appendChild(wrapScrollableFeed(ul));
			}
		}

		function loadEvents() {
			if (!eventsEl) return;
			api('/events?per_page=20').then(function (data) {
				cachedEvents = Array.isArray(data && data.events) ? data.events : [];
				renderEventsAndNews();
			}).catch(function () {
				renderEventsAndNews();
			});
		}

		loadEvents();

		if (eventsEl || quotasEl) {
			api('/binding').then(function (snap) {
				if (snap && (snap.status === 'bound' || snap.state === 'bound')) {
					const prevLen = ssrSugNodes.length;
					ssrSugNodes = ssrSugNodes.filter(function (li) { return li.getAttribute('data-wpcy-sug-row') !== 'sug.passport'; });
					const quotaTab = document.querySelector('[data-wenpai-tabs="wpcy-side"] [data-wenpai-tab="quota"]');
					if (quotaTab) {
						quotaTab.setAttribute('data-wenpai-foot-meta', '已关联文派云通行证 · 额度自动同步');
					}
					if (ssrSugNodes.length !== prevLen) {
						renderEventsAndNews();
					}
				}
			}).catch(function () {});
		}

		if (feedCardEl && feedCardEl.getAttribute('data-wpcy-news-enabled') !== '0') {
			api('/announcements?per_page=15').then(function (data) {
				cachedNotices = Array.isArray(data && data.items) ? data.items : [];
				renderEventsAndNews();
			}).catch(function () {
				renderEventsAndNews();
			});
		}

		document.addEventListener('wenpai:tab-change', function (e) {
			if (newsManageEl && e.detail && e.detail.group === 'wpcy-feed') {
				newsManageEl.hidden = e.detail.tab !== 'news';
			}
		});

		function triggerOverviewProbe(toastMsg) {
			if (probeBtn) {
				probeBtn.classList.add('is-busy');
				probeBtn.setAttribute('aria-busy', 'true');
				probeBtn.disabled = true;
			}
			if (checkedAtEl) checkedAtEl.textContent = '测速中…';
			setMetricTile(tilesEl, 'browser', null, '', '测速中…');
			return Promise.all([
				api('/diagnostics/run', { method: 'POST' }),
				measureOverviewBrowser(tilesEl)
			]).then(function (res) {
				const fresh = res[0];
				applyTargetsToOverview(fresh && fresh.targets, statsEl, tilesEl, checkedAtEl);
				if (checkedAtEl) checkedAtEl.textContent = '刚刚更新 · 自动回退';
				loadEvents();
				if (toastMsg) {
					if (typeof window.wenpaiToast === 'function') {
						window.wenpaiToast(toastMsg, 'ok');
					} else {
						notify('ok', toastMsg);
					}
				}
			}).catch(function (err) {
				notify('bad', err.message || '节点测速未完成，请稍后重试。');
			}).finally(function () {
				if (probeBtn) {
					probeBtn.classList.remove('is-busy');
					probeBtn.removeAttribute('aria-busy');
					probeBtn.disabled = false;
				}
			});
		}

		if (tilesEl) {
			measureOverviewBrowser(tilesEl);
		}

		if (statsEl || tilesEl) {
			api('/diagnostics').then(function (data) {
				const targets = Array.isArray(data && data.targets) ? data.targets : [];
				if (targets.length) {
					applyTargetsToOverview(targets, statsEl, tilesEl, checkedAtEl);
				} else {
					api('/diagnostics/run', { method: 'POST' }).then(function (fresh) {
						applyTargetsToOverview(fresh && fresh.targets, statsEl, tilesEl, checkedAtEl);
						loadEvents();
					}).catch(function () {});
				}
			}).catch(function () {});
		}

		if (probeBtn) {
			probeBtn.addEventListener('click', function () {
				triggerOverviewProbe('节点测速已更新');
			});
		}

		function toast(msg, tone) {
			if (typeof window.wenpaiToast === 'function') {
				window.wenpaiToast(msg, tone || 'ok');
			} else {
				notify(tone || 'ok', msg);
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

		function syncMaintenanceTile(btn, enabled) {
			btn.setAttribute('data-wpcy-maint-state', enabled ? '1' : '0');
			const labelEl = btn.querySelector('.wenpai-icon-box__label');
			if (labelEl) {
				labelEl.textContent = enabled ? '维护中' : '维护模式';
			}
			btn.setAttribute(
				'title',
				enabled
					? '前台维护模式已开启，点击关闭并恢复正常访问'
					: '临时向前台访客展示维护提示，管理员后台不受影响'
			);
			const existingDot = btn.querySelector('.wenpai-icon-box__dot');
			if (enabled) {
				if (!existingDot) {
					const dot = document.createElement('span');
					dot.className = 'wenpai-icon-box__dot is-warn';
					dot.setAttribute('aria-hidden', 'true');
					btn.insertBefore(dot, btn.firstChild);
				}
				btn.removeAttribute('data-wenpai-confirm');
				btn.removeAttribute('data-wenpai-confirm-title');
				btn.removeAttribute('data-wenpai-confirm-ok');
				btn.removeAttribute('data-wenpai-confirm-primary');
			} else {
				if (existingDot) {
					existingDot.remove();
				}
				btn.setAttribute('data-wenpai-confirm', '开启后，未登录的前台访客将看到 HTTP 503 维护提示；管理员登录、后台、REST API 与计划任务不受影响。确定现在开启？');
				btn.setAttribute('data-wenpai-confirm-title', '开启前台维护模式');
				btn.setAttribute('data-wenpai-confirm-ok', '立即开启');
				btn.setAttribute('data-wenpai-confirm-primary', '1');
			}
		}

		document.querySelectorAll('[data-wpcy-quick]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				const kind = btn.getAttribute('data-wpcy-quick');
				if (kind === 'flush-updates') {
					triggerOverviewProbe('更新与镜像检测缓存已刷新');
				} else if (kind === 'reset-fallback') {
					triggerOverviewProbe('回退锁定已重置并完成重测');
				} else if (kind === 'scan-outbound') {
					measureOverviewBrowser(tilesEl).then(function () {
						toast('静态源与访客侧可达性检测完成', 'ok');
					});
				} else if (kind === 'copy-report') {
					const summary = [
						'Plugin: WenPai Leaf 4.0',
						'Stats: ' + (trendSumEl ? trendSumEl.textContent : ''),
						'Status: ' + (checkedAtEl ? checkedAtEl.textContent : '')
					].join('\n');
					copyTextToClipboard(summary).then(function () {
						toast('脱敏诊断摘要已复制', 'ok');
					}).catch(function () {
						toast('复制失败，请检查浏览器剪贴板权限', 'bad');
					});
				} else if (kind === 'clear-transients') {
					btn.disabled = true;
					api('/tools/clear-transients', { method: 'POST' }).then(function (res) {
						const deleted = res && typeof res.deleted === 'number' ? res.deleted : 0;
						toast(
							deleted > 0
								? ('已清理 ' + deleted + ' 条过期瞬态缓存并刷新对象缓存')
								: '未发现过期瞬态缓存，已刷新对象缓存',
							'ok'
						);
					}).catch(function (err) {
						toast((err && err.message) || '清理瞬态缓存失败，请稍后重试', 'bad');
					}).finally(function () {
						btn.disabled = false;
					});
				} else if (kind === 'toggle-maintenance') {
					if (btn.hasAttribute('data-wenpai-confirm') && !btn.hasAttribute('data-wenpai-confirm-bypass')) {
						return;
					}
					const nextOn = btn.getAttribute('data-wpcy-maint-state') !== '1';
					btn.disabled = true;
					api('/tools/maintenance', { method: 'POST', body: { enabled: nextOn } }).then(function (res) {
						const active = !!(res && res.enabled);
						syncMaintenanceTile(btn, active);
						toast(
							active
								? '已开启前台维护模式（管理员后台不受影响）'
								: '已关闭维护模式，前台恢复正常访问',
							'ok'
						);
					}).catch(function (err) {
						toast((err && err.message) || '切换维护模式失败，请稍后重试', 'bad');
					}).finally(function () {
						btn.disabled = false;
					});
				} else if (kind === 'site-snapshot') {
					btn.disabled = true;
					api('/tools/snapshot').then(function (res) {
						const text = (res && res.text) ? String(res.text) : '';
						if (!text) {
							throw new Error('环境快照生成为空');
						}
						return copyTextToClipboard(text).then(function () {
							toast(
								'已复制环境快照（WP ' + (res.wp_version || '-') + ' / PHP ' + (res.php_version || '-') + ' / ' + (res.active_plugins || 0) + ' 款插件）',
								'ok'
							);
						});
					}).catch(function (err) {
						toast((err && err.message) || '复制环境快照失败，请稍后重试', 'bad');
					}).finally(function () {
						btn.disabled = false;
					});
				} else if (kind === 'reserved-slot') {
					toast('文派轻便工具箱预留位，后续版本按需接入', 'ok');
				}
			});
		});

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
				if (input.name === 'wporg_enabled') {
					patch.connectivity = { wordpress_org: checked ? 'auto' : 'off' };
					const sel = form && form.querySelector('select[name="wporg_mirror"]');
					if (sel) sel.value = checked ? 'auto' : 'off';
				} else if (input.name === 'public_assets_enabled') {
					patch.connectivity = { public_assets: { scope: checked ? 'both' : 'off' } };
					const sel = form && form.querySelector('select[name="public_assets_scope"]');
					if (sel) sel.value = checked ? 'both' : 'off';
				} else if (input.name === 'avatar_enabled') {
					patch.connectivity = { avatar: checked ? 'cravatar_cn' : 'off' };
					const sel = form && form.querySelector('select[name="avatar_source"]');
					if (sel) sel.value = checked ? 'cravatar_cn' : 'off';
				} else if (input.name === 'icon_photos_enabled') {
					patch.connectivity = { icon_photos: { enabled: checked ? 'on' : 'off' } };
					const sel = form && form.querySelector('select[name="icon_photos_mode"]');
					if (sel) sel.value = checked ? 'on' : 'off';
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

		const wlPromoHiddenEl = form ? form.querySelector('input[name="wl_promo_hidden"]') : null;
		const wlPromoFieldsEl = form ? form.querySelector('[data-wpcy-wl-promo-fields]') : null;
		if (wlPromoHiddenEl && wlPromoFieldsEl) {
			wlPromoHiddenEl.addEventListener('change', function () {
				wlPromoFieldsEl.hidden = wlPromoHiddenEl.checked;
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
						promo_hidden: !!fd.get('wl_promo_hidden'),
						promo_badge: String(fd.get('wl_promo_badge') || '').trim(),
						promo_title: String(fd.get('wl_promo_title') || '').trim(),
						promo_desc: String(fd.get('wl_promo_desc') || '').trim(),
						promo_cta_label: String(fd.get('wl_promo_cta_label') || '').trim(),
						promo_cta_url: String(fd.get('wl_promo_cta_url') || '').trim(),
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

	// 4. Wizard: 3-step scene -> conflict check -> starter companion bundle.
	function initWizard() {
		const form = document.getElementById('wpcy-wizard-form');
		if (!form) return;
		const wizShell = form.closest('[data-wpcy-wizard-shell]') || document;
		const stepPanes = Array.prototype.slice.call(form.querySelectorAll('[data-wpcy-wiz-step]'));
		const navNodes = Array.prototype.slice.call(wizShell.querySelectorAll('nav.steps .st'));
		const starterInputs = Array.prototype.slice.call(form.querySelectorAll('[data-wpcy-starter-slug]'));

		starterInputs.forEach(function (inp) {
			inp.addEventListener('change', function () {
				inp.setAttribute('data-wpcy-user-touched', '1');
			});
		});

		function syncStarterByProfile(profileKey) {
			starterInputs.forEach(function (inp) {
				if (inp.getAttribute('data-wpcy-user-touched') === '1') {
					return;
				}
				const isInstalled = inp.getAttribute('data-wpcy-installed') === '1';
				const isRec = inp.getAttribute('data-wpcy-rec-' + profileKey) === '1';
				inp.checked = isInstalled || isRec;
				const row = inp.closest('[data-wpcy-starter-row]');
				const pill = row ? row.querySelector('.pill') : null;
				if (pill && !isInstalled) {
					pill.className = 'pill ' + (isRec ? 'ok' : 'mute');
					pill.textContent = isRec ? '场景推荐' : '按需选装';
				}
			});
		}

		function showWizardStep(idx) {
			stepPanes.forEach(function (pane) {
				const pIdx = parseInt(pane.getAttribute('data-wpcy-wiz-step') || '0', 10) || 0;
				pane.hidden = pIdx !== idx;
			});
			navNodes.forEach(function (st, sIdx) {
				st.classList.remove('done', 'cur');
				st.removeAttribute('aria-current');
				const numEl = st.querySelector('.n');
				if (sIdx < idx) {
					st.classList.add('done');
					if (numEl) {
						numEl.innerHTML = '<span aria-hidden="true">✓</span><span class="screen-reader-text">已完成</span>';
					}
				} else if (sIdx === idx) {
					st.classList.add('cur');
					st.setAttribute('aria-current', 'step');
					if (numEl) {
						numEl.textContent = String(sIdx + 1);
					}
				} else if (numEl) {
					numEl.textContent = String(sIdx + 1);
				}
			});
			window.scrollTo({ top: 0, behavior: 'smooth' });
		}

		form.querySelectorAll('[data-wpcy-wiz-goto]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				const target = parseInt(btn.getAttribute('data-wpcy-wiz-goto') || '0', 10) || 0;
				showWizardStep(target);
			});
		});

		form.querySelectorAll('input[name="profile"]').forEach(function (radio) {
			radio.addEventListener('change', function () {
				form.querySelectorAll('.choice').forEach(function (lbl) {
					lbl.classList.toggle('is-on', !!lbl.querySelector('input:checked'));
				});
				if (radio.checked) {
					syncStarterByProfile(radio.value);
				}
			});
		});

		function finishWizard(includeSuite) {
			const fd = new FormData(form);
			const profile = String(fd.get('profile') || 'domestic');
			const homeUrl = form.getAttribute('data-wpcy-home') || '?page=wpcy';
			const submitBtn = form.querySelector('[data-wpcy-wiz-submit]');
			const skipBtn = form.querySelector('[data-wpcy-wiz-skip-suite]');
			if (submitBtn) submitBtn.disabled = true;
			if (skipBtn) skipBtn.disabled = true;

			const selectedSlugs = includeSuite
				? starterInputs
					.filter(function (inp) { return inp.checked && !inp.disabled; })
					.map(function (inp) { return inp.getAttribute('data-wpcy-starter-slug') || ''; })
					.filter(Boolean)
				: [];

			if (submitBtn && selectedSlugs.length) {
				submitBtn.textContent = '正在配置组件…';
			}

			api('/settings', { method: 'PUT', body: { profile: profile, apply_defaults: true } })
				.then(function () {
					if (!selectedSlugs.length) {
						return null;
					}
					return api('/suite/provision', { method: 'POST', body: { slugs: selectedSlugs } })
						.catch(function (err) {
							return { error: err && err.message ? err.message : '组件安装超时' };
						});
				})
				.then(function (provRes) {
					if (provRes && Array.isArray(provRes.results) && provRes.results.length) {
						let okCount = 0;
						let manualCount = 0;
						provRes.results.forEach(function (item) {
							if (item && (item.status === 'activated' || item.status === 'active')) {
								okCount += 1;
							} else {
								manualCount += 1;
							}
						});
						if (okCount > 0 && manualCount === 0) {
							stashFlashToast('ok', '场景配置已生效，已启用 ' + okCount + ' 款文派组件');
						} else if (okCount > 0 && manualCount > 0) {
							stashFlashToast('warn', '已启用 ' + okCount + ' 款组件；另有 ' + manualCount + ' 款请前往服务目录查看');
						} else if (manualCount > 0) {
							stashFlashToast('warn', '场景配置已保存，所选组件暂需前往服务目录手动安装');
						}
					} else if (provRes && provRes.error) {
						stashFlashToast('warn', '场景配置已保存，组件自动安装未完成：' + provRes.error);
					} else {
						stashFlashToast('ok', '站点加速场景配置已生效');
					}
					window.location.href = homeUrl;
				})
				.catch(function (err) {
					if (submitBtn) {
						submitBtn.disabled = false;
						submitBtn.textContent = '应用方案并配置组件';
					}
					if (skipBtn) skipBtn.disabled = false;
					notify('bad', err.message);
				});
		}

		const skipSuiteBtn = form.querySelector('[data-wpcy-wiz-skip-suite]');
		if (skipSuiteBtn) {
			skipSuiteBtn.addEventListener('click', function () {
				finishWizard(false);
			});
		}

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			finishWizard(true);
		});
	}

	// 5. Companion 1-click install / activate buttons (`[data-wpcy-provision-slug]`).
	function initCompanionProvisioning() {
		document.querySelectorAll('[data-wpcy-provision-slug]').forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				if (btn.hasAttribute('data-wenpai-confirm') && !btn.hasAttribute('data-wenpai-confirm-bypass')) {
					return;
				}
				e.preventDefault();
				if (btn.disabled || btn.classList.contains('is-busy')) {
					return;
				}
				const slug = btn.getAttribute('data-wpcy-provision-slug') || '';
				const name = btn.getAttribute('data-wpcy-provision-name') || slug;
				const isInstalled = btn.getAttribute('data-wpcy-suite-state') === 'installed';
				if (!slug) return;

				btn.disabled = true;
				btn.classList.add('is-busy');
				btn.setAttribute('aria-busy', 'true');
				toast('ok', (isInstalled ? '正在启用 ' : '正在安装并启用 ') + name + '…');

				api('/suite/provision', { method: 'POST', body: { slugs: [slug] } })
					.then(function (res) {
						const item = (res && Array.isArray(res.results) && res.results[0]) ? res.results[0] : null;
						if (item && (item.status === 'activated' || item.status === 'active')) {
							stashFlashToast('ok', '已启用 ' + name);
							if (item.settings_url) {
								window.location.href = item.settings_url;
							} else {
								window.location.reload();
							}
							return;
						}
						btn.disabled = false;
						btn.classList.remove('is-busy');
						btn.removeAttribute('aria-busy');
						toast('warn', '未能在线拉取安装包，请前往组件官网下载后上传安装。');
					})
					.catch(function (err) {
						btn.disabled = false;
						btn.classList.remove('is-busy');
						btn.removeAttribute('aria-busy');
						toast('bad', (err && err.message) ? err.message : '操作未完成，请稍后重试。');
					});
			});
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		consumeFlashToast();
		initOverview();
		initSettings();
		initDiagnoseAndServices();
		initSiteBlocklist();
		initUrlTester();
		initOutboundOverview();
		initWizard();
		initCompanionProvisioning();
	});
})();
