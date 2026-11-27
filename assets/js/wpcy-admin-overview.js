/**
 * WPCY 4.0 Overview screen progressive enhancement: stat hub, trend chart,
 * latency metric tiles, events/news tag feed, and quick tools.
 */
(function () {
	'use strict';

	const kit = window.wpcyAdminKit;
	if (!kit) return;

	const api = kit.api;
	const notify = kit.notify;
	const showToast = kit.showToast;
	const setStatbarCell = kit.setStatbarCell;
	const formatShortTime = kit.formatShortTime;
	const formatShortDate = kit.formatShortDate;
	const copyTextToClipboard = kit.copyTextToClipboard;

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
								showToast(okMsg, 'ok');
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
					showToast(toastMsg, 'ok');
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
					api('/tools/catalog/refresh', { method: 'POST' }).catch(function () {});
					triggerOverviewProbe('更新、目录与镜像检测缓存已刷新');
				} else if (kind === 'reset-fallback') {
					triggerOverviewProbe('回退锁定已重置并完成重测');
				} else if (kind === 'scan-outbound') {
					measureOverviewBrowser(tilesEl).then(function () {
						showToast('静态源与访客侧可达性检测完成', 'ok');
					});
				} else if (kind === 'copy-report') {
					const summary = [
						'Plugin: WenPai Leaf 4.0',
						'Stats: ' + (trendSumEl ? trendSumEl.textContent : ''),
						'Status: ' + (checkedAtEl ? checkedAtEl.textContent : '')
					].join('\n');
					copyTextToClipboard(summary).then(function () {
						showToast('脱敏诊断摘要已复制', 'ok');
					}).catch(function () {
						showToast('复制失败，请检查浏览器剪贴板权限', 'bad');
					});
				} else if (kind === 'clear-transients') {
					btn.disabled = true;
					api('/tools/clear-transients', { method: 'POST' }).then(function (res) {
						const deleted = res && typeof res.deleted === 'number' ? res.deleted : 0;
						showToast(
							deleted > 0
								? ('已清理 ' + deleted + ' 条过期瞬态缓存并刷新对象缓存')
								: '未发现过期瞬态缓存，已刷新对象缓存',
							'ok'
						);
					}).catch(function (err) {
						showToast((err && err.message) || '清理瞬态缓存失败，请稍后重试', 'bad');
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
						showToast(
							active
								? '已开启前台维护模式（管理员后台不受影响）'
								: '已关闭维护模式，前台恢复正常访问',
							'ok'
						);
					}).catch(function (err) {
						showToast((err && err.message) || '切换维护模式失败，请稍后重试', 'bad');
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
							showToast(
								'已复制环境快照（WP ' + (res.wp_version || '-') + ' / PHP ' + (res.php_version || '-') + ' / ' + (res.active_plugins || 0) + ' 款插件）',
								'ok'
							);
						});
					}).catch(function (err) {
						showToast((err && err.message) || '复制环境快照失败，请稍后重试', 'bad');
					}).finally(function () {
						btn.disabled = false;
					});
				} else if (kind === 'reserved-slot') {
					showToast('文派轻便工具箱预留位，后续版本按需接入', 'ok');
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

	kit.registerInit(initOverview);
})();
