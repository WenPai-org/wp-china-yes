/**
 * Commercial update providers hydration & Overview quota sync (zero dependencies).
 */
(function () {
	'use strict';

	const cfg = window.wpcyAdmin || {};
	const restRoot = String(cfg.restRoot || '').replace(/\/+$/, '');
	const nonce = String(cfg.nonce || '');
	const MALL_ID = 'weixiaoduo-mall';
	const UNBOUND_HINT = '请先点击本页右上角「绑定本站」关联站点通行证，再连接商业更新源。';

	function feedback(level, text) {
		if (!text) return;
		if (typeof window.wenpaiToast === 'function') {
			const tone = level === 'bad' || level === 'warn' ? 'bad' : (level === 'ok' ? 'ok' : '');
			window.wenpaiToast(text, tone);
			return;
		}
		if (window.wenpaiAdmin && typeof window.wenpaiAdmin.notice === 'function') {
			window.wenpaiAdmin.notice({ level: level, text: text, fade: true });
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

	function setBusy(btn, busy, lockedAfter) {
		if (!btn) return;
		if (busy) {
			btn.classList.add('is-busy');
			btn.setAttribute('aria-busy', 'true');
			btn.disabled = true;
		} else {
			btn.classList.remove('is-busy');
			btn.removeAttribute('aria-busy');
			btn.disabled = !!lockedAfter;
		}
	}

	function initProvidersCard() {
		const card = document.querySelector('[data-wpcy-providers-card]');
		if (!card) return;

		const isLocked = card.getAttribute('data-wpcy-locked') === '1';
		const toggleBtn = card.querySelector('[data-wpcy-prov-toggle="' + MALL_ID + '"]');
		const rowEl = card.querySelector('[data-wpcy-prov-row="' + MALL_ID + '"]') || (toggleBtn && toggleBtn.closest('.simple-row'));
		const pillEl = rowEl ? rowEl.querySelector('.pill') : null;
		const tileEl = rowEl ? rowEl.querySelector('.tile') : null;
		const panelEl = card.querySelector('[data-wpcy-prov-panel="' + MALL_ID + '"]');
		const statusEl = card.querySelector('[data-wpcy-prov-status="' + MALL_ID + '"]');
		const formEl = card.querySelector('[data-wpcy-prov-form="' + MALL_ID + '"]');
		const emailInput = card.querySelector('[data-wpcy-prov-email="' + MALL_ID + '"]');
		const keyInput = card.querySelector('[data-wpcy-prov-key="' + MALL_ID + '"]');
		const connectBtn = card.querySelector('[data-wpcy-prov-connect="' + MALL_ID + '"]');
		const connectedBar = card.querySelector('[data-wpcy-prov-connected="' + MALL_ID + '"]');
		const testBtn = card.querySelector('[data-wpcy-prov-test="' + MALL_ID + '"]');
		const productsBtn = card.querySelector('[data-wpcy-prov-products-btn="' + MALL_ID + '"]');
		const disconnectBtn = card.querySelector('[data-wpcy-prov-disconnect="' + MALL_ID + '"]');
		const productsSlot = card.querySelector('[data-wpcy-prov-products="' + MALL_ID + '"]');

		let bindingStatus = 'unbound';

		function syncToggleLabel() {
			if (!toggleBtn || !panelEl) return;
			toggleBtn.setAttribute('aria-expanded', panelEl.hidden ? 'false' : 'true');
		}

		function buildSummary(item) {
			const parts = [];
			if (item && item.email_masked) {
				parts.push(String(item.email_masked));
			}
			if (item && typeof item.product_count === 'number') {
				parts.push(item.product_count + ' 款产品');
			}
			return parts.join(' · ');
		}

		function applyProviderItem(item) {
			const conn = (item && item.connection) ? String(item.connection) : 'disconnected';
			const summary = buildSummary(item);

			if (pillEl) {
				if (conn === 'connected') {
					pillEl.className = 'pill ok';
					pillEl.textContent = '已连接';
				} else if (conn === 'invalid') {
					pillEl.className = 'pill warn';
					pillEl.textContent = '密钥失效';
				} else {
					pillEl.className = 'pill mute';
					pillEl.textContent = '未连接';
				}
			}

			if (tileEl) {
				tileEl.classList.toggle('ok', conn === 'connected');
				tileEl.classList.toggle('warn', conn === 'invalid');
			}

			if (formEl) {
				formEl.hidden = conn === 'connected';
			}
			if (connectedBar) {
				connectedBar.hidden = !(conn === 'connected' || (conn === 'invalid' && item && item.email_masked));
			}
			if (conn === 'disconnected' && productsSlot) {
				productsSlot.hidden = true;
				productsSlot.innerHTML = '';
			}

			const connectDisabled = isLocked || bindingStatus !== 'bound';
			if (connectBtn) {
				connectBtn.disabled = connectDisabled;
			}

			if (statusEl) {
				if (bindingStatus !== 'bound') {
					statusEl.textContent = summary ? (summary + ' · ' + UNBOUND_HINT) : UNBOUND_HINT;
				} else if (conn === 'connected') {
					statusEl.textContent = summary || '已连接薇晓朵商城更新源。';
				} else if (conn === 'invalid') {
					statusEl.textContent = summary
						? (summary + ' · 授权密钥已失效，请重新验证或检测连接。')
						: '授权密钥验证未通过，请核对购买邮箱与授权密钥后重试。';
				} else {
					statusEl.textContent = '输入薇晓朵商城购买邮箱与订单授权密钥，密钥加密保存于本站。';
				}
			}
		}

		function renderProducts(rows) {
			if (!productsSlot) return;
			const ul = document.createElement('ul');
			ul.className = 'wenpai-tag-feed';

			if (!Array.isArray(rows) || !rows.length) {
				const li = document.createElement('li');
				const tag = document.createElement('span');
				tag.className = 'wenpai-micro-tag';
				tag.textContent = '清单';
				const textWrap = document.createElement('span');
				textWrap.className = 'wenpai-tag-feed__text';
				const sp = document.createElement('span');
				sp.textContent = '当前账号下暂无可同步的已购扩展记录。';
				textWrap.appendChild(sp);
				const side = document.createElement('span');
				side.className = 'wenpai-tag-feed__side';
				li.appendChild(tag);
				li.appendChild(textWrap);
				li.appendChild(side);
				ul.appendChild(li);
			} else {
				rows.forEach(function (p) {
					if (!p || typeof p !== 'object') return;
					const li = document.createElement('li');
					const tag = document.createElement('span');
					const managed = !!p.update_managed;
					const installed = p.installed === true;
					tag.className = 'wenpai-micro-tag' + ((managed || installed) ? ' is-ok' : '');
					tag.textContent = managed ? '更新托管' : (installed ? '已安装' : '已购');

					const textWrap = document.createElement('span');
					textWrap.className = 'wenpai-tag-feed__text';
					const nameEl = document.createElement('span');
					nameEl.textContent = p.title || p.name || p.slug || ('#' + (p.product_id || ''));
					textWrap.appendChild(nameEl);

					const noteParts = [];
					if (p.slug) {
						noteParts.push(String(p.slug));
					}
					if (p.version || p.new_version) {
						noteParts.push('v' + String(p.version || p.new_version));
					}
					if (noteParts.length) {
						const sm = document.createElement('small');
						sm.textContent = noteParts.join(' · ');
						textWrap.appendChild(sm);
					}

					const side = document.createElement('span');
					side.className = 'wenpai-tag-feed__side';
					const verLabel = p.version || p.new_version
						? ('v' + String(p.version || p.new_version))
						: (p.product_id ? ('#' + String(p.product_id)) : '');
					if (verLabel) {
						const verSpan = document.createElement('span');
						verSpan.textContent = verLabel;
						side.appendChild(verSpan);
					}

					li.appendChild(tag);
					li.appendChild(textWrap);
					li.appendChild(side);
					ul.appendChild(li);
				});
			}

			productsSlot.innerHTML = '';
			productsSlot.appendChild(ul);
			productsSlot.hidden = false;
		}

		if (toggleBtn && panelEl) {
			toggleBtn.addEventListener('click', function () {
				if (isLocked) return;
				panelEl.hidden = !panelEl.hidden;
				syncToggleLabel();
			});
		}

		if (connectBtn) {
			connectBtn.addEventListener('click', function () {
				if (isLocked || bindingStatus !== 'bound') {
					feedback('warn', UNBOUND_HINT);
					return;
				}
				const email = emailInput ? emailInput.value.trim() : '';
				const licenseKey = keyInput ? keyInput.value.trim() : '';
				if (!email || !licenseKey) {
					feedback('warn', '请填写完整的购买邮箱与授权密钥。');
					return;
				}
				setBusy(connectBtn, true);
				api('/providers/' + MALL_ID + '/connect', {
					method: 'POST',
					body: { email: email, license_key: licenseKey }
				}).then(function (item) {
					applyProviderItem(item);
					if (item && item.connection === 'connected') {
						if (keyInput) keyInput.value = '';
						feedback('ok', '已连接薇晓朵商城更新源。');
					} else {
						feedback('bad', '授权密钥验证未通过，请核对后重试。');
					}
				}).catch(function (err) {
					feedback('bad', err.message || '暂时无法连接薇晓朵商城，请稍后重试。');
				}).finally(function () {
					setBusy(connectBtn, false, isLocked || bindingStatus !== 'bound');
				});
			});
		}

		if (testBtn) {
			testBtn.addEventListener('click', function () {
				if (isLocked) return;
				setBusy(testBtn, true);
				api('/providers/' + MALL_ID + '/test', { method: 'POST' })
					.then(function (item) {
						applyProviderItem(item);
						if (item && item.connection === 'connected') {
							feedback('ok', '连接检测正常，已同步最新授权状态。');
						} else {
							feedback('warn', '授权密钥状态异常，请重新验证连接。');
						}
					})
					.catch(function (err) {
						feedback('bad', err.message || '暂时无法完成连接检测，请稍后重试。');
					})
					.finally(function () {
						setBusy(testBtn, false, isLocked);
					});
			});
		}

		if (productsBtn) {
			productsBtn.addEventListener('click', function () {
				setBusy(productsBtn, true);
				api('/providers/' + MALL_ID + '/products')
					.then(function (data) {
						const list = Array.isArray(data && data.products) ? data.products : [];
						renderProducts(list);
					})
					.catch(function (err) {
						feedback('bad', err.message || '暂时无法读取已购产品清单，请稍后重试。');
					})
					.finally(function () {
						setBusy(productsBtn, false, false);
					});
			});
		}

		if (disconnectBtn) {
			disconnectBtn.addEventListener('click', function () {
				if (isLocked) return;
				setBusy(disconnectBtn, true);
				api('/providers/' + MALL_ID, { method: 'DELETE' })
					.then(function (item) {
						applyProviderItem(item);
						feedback('ok', '已断开薇晓朵商城连接。');
					})
					.catch(function (err) {
						feedback('bad', err.message || '暂时无法断开连接，请稍后重试。');
					})
					.finally(function () {
						setBusy(disconnectBtn, false, isLocked);
					});
			});
		}

		let lastMallItem = null;

		function refreshProviders() {
			api('/providers').then(function (data) {
				bindingStatus = (data && typeof data.binding_status === 'string') ? data.binding_status : 'unbound';
				const list = Array.isArray(data && data.providers) ? data.providers : [];
				lastMallItem = list.find(function (p) { return p && p.id === MALL_ID; }) || null;
				applyProviderItem(lastMallItem);
			}).catch(function () {});
		}

		window.addEventListener('wpcy:binding-changed', function (ev) {
			if (ev && ev.detail && typeof ev.detail.status === 'string') {
				bindingStatus = ev.detail.status;
				applyProviderItem(lastMallItem);
			}
			refreshProviders();
		});

		refreshProviders();
	}

	function matchQuotaNode(nodes, item) {
		const rawName = String(item.name || item.title || '').trim();
		const rawKey = String(item.service || item.id || '').toLowerCase();
		const aliases = {
			admincdn: 'adminCDN',
			wpstatic: 'adminCDN',
			static: 'adminCDN',
			windfonts: '文风',
			wpfonts: '文风',
			fonts: '文风',
			wpmind: '心思',
			mind: '心思',
			ai: '心思',
			motucloud: '墨图',
			wpphoto: '墨图',
			wpsss: '智储',
			storage: '智储',
			cravatar: 'Cravatar',
			wporg: 'WP.org'
		};
		const needle = aliases[rawKey] || '';

		for (let i = 0; i < nodes.length; i++) {
			const nameEl = nodes[i].querySelector('.wenpai-quota-item__name');
			const label = nameEl ? String(nameEl.textContent || '').trim() : '';
			if (!label) continue;
			if (rawName && (label === rawName || label.indexOf(rawName) !== -1 || rawName.indexOf(label) !== -1)) {
				return nodes[i];
			}
			if (needle && label.indexOf(needle) !== -1) {
				return nodes[i];
			}
		}
		return null;
	}

	function initOverviewQuotaSync() {
		const screen = document.querySelector('[data-wpcy-screen="overview"]');
		if (!screen) return;
		const quotasEl = screen.querySelector('[data-wpcy-quotas]');
		if (!quotasEl) return;

		api('/catalog').then(function (data) {
			const items = Array.isArray(data && data.items) ? data.items : [];
			if (!items.length) return;

			const quotaNodes = Array.prototype.slice.call(quotasEl.querySelectorAll('.wenpai-quota-item'));
			if (!quotaNodes.length) return;

			items.forEach(function (item) {
				if (!item || typeof item !== 'object') return;
				const q = (item.quota && typeof item.quota === 'object') ? item.quota : item;
				const hasPair = typeof q.used === 'number' && typeof q.limit === 'number' && q.limit > 0;
				const hasPct = typeof q.percent === 'number' || typeof item.percent === 'number';
				if (!hasPair && !hasPct) return;

				const li = matchQuotaNode(quotaNodes, item);
				if (!li) return;

				const numEl = li.querySelector('.wenpai-quota-item__num');
				const pct = hasPair
					? Math.max(0, Math.min(100, Math.round((q.used / q.limit) * 100)))
					: Math.max(0, Math.min(100, Math.round(typeof q.percent === 'number' ? q.percent : item.percent)));

				if (numEl) {
					if (typeof item.usage === 'string' && item.usage) {
						numEl.textContent = item.usage;
					} else if (hasPair) {
						numEl.textContent = q.used + ' / ' + q.limit + ' (' + pct + '%)';
					} else {
						numEl.textContent = '已用 ' + pct + '%';
					}
				}

				li.classList.toggle('is-warn', pct >= 85 && pct < 100);
				li.classList.toggle('is-full', pct >= 100);
			});
		}).catch(function () {});
	}

	function boot() {
		initProvidersCard();
		initOverviewQuotaSync();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
