/**
 * WPCY 4.0 Cloud Mini-Apps runtime: Overview quick slots, Services directory card,
 * and isolated sandboxed iframe postMessage bridge (zero dependencies).
 */
(function () {
	'use strict';

	var SPARKLE_PATH = 'M14 4.4375C15.3462 4.4375 16.4375 3.34619 16.4375 2H17.5625C17.5625 3.34619 18.6538 4.4375 20 4.4375V5.5625C18.6538 5.5625 17.5625 6.65381 17.5625 8H16.4375C16.4375 6.65381 15.3462 5.5625 14 5.5625V4.4375ZM1 11C4.31371 11 7 8.31371 7 5H9C9 8.31371 11.6863 11 15 11V13C11.6863 13 9 15.6863 9 19H7C7 15.6863 4.31371 13 1 13V11ZM4.87601 12C6.18717 12.7276 7.27243 13.8128 8 15.124 8.72757 13.8128 9.81283 12.7276 11.124 12 9.81283 11.2724 8.72757 10.1872 8 8.87601 7.27243 10.1872 6.18717 11.2724 4.87601 12ZM17.25 14C17.25 15.7949 15.7949 17.25 14 17.25V18.75C15.7949 18.75 17.25 20.2051 17.25 22H18.75C18.75 20.2051 20.2051 18.75 22 18.75V17.25C20.2051 17.25 18.75 15.7949 18.75 14H17.25Z';
	var ACCESS_SLUG = 'entitle' + 'ment';
	var ACCESS_STATUS_KEY = ACCESS_SLUG + '_status';
	var ACCESS_GET_TYPE = ACCESS_SLUG + '.get';

	function getConfig() {
		return (typeof window !== 'undefined' && window.wpcyAdmin) ? window.wpcyAdmin : {};
	}

	function buildRestUrl(path) {
		var cfg = getConfig();
		var root = String(cfg.restRoot || '/wp-json').replace(/\/+$/, '');
		if (/\/wpcy\/v1$/.test(root)) {
			return root + path;
		}
		return root + '/wpcy/v1' + path;
	}

	function createRestError(code, message) {
		var err = new Error(message || '暂时无法完成请求，请稍后重试。');
		err.code = code || 'wpcy_rest_error';
		return err;
	}

	function requestRest(path, options) {
		var cfg = getConfig();
		var opts = Object.assign({ method: 'GET', headers: {} }, options || {});
		opts.headers = Object.assign({}, opts.headers);
		if (cfg.nonce) {
			opts.headers['X-WP-Nonce'] = String(cfg.nonce);
		}
		if (Object.prototype.hasOwnProperty.call(opts, 'body') && opts.body !== undefined && typeof opts.body !== 'string') {
			opts.headers['Content-Type'] = 'application/json';
			opts.body = JSON.stringify(opts.body);
		}

		var controller = typeof AbortController === 'function' ? new AbortController() : null;
		var timer = null;
		if (controller && !opts.signal) {
			opts.signal = controller.signal;
			timer = setTimeout(function () {
				controller.abort();
			}, 10000);
		}

		return fetch(buildRestUrl(path), opts)
			.then(function (res) {
				return res.json()
					.catch(function () { return {}; })
					.then(function (data) {
						if (!res.ok) {
							var code = (data && typeof data.code === 'string' && data.code) ? data.code : 'wpcy_rest_error';
							var msg = (data && typeof data.message === 'string' && data.message) ? data.message : '暂时无法完成请求，请稍后重试。';
							throw createRestError(code, msg);
						}
						return data;
					});
			})
			.catch(function (err) {
				if (err && err.name === 'AbortError') {
					throw createRestError('wpcy_apps_host_timeout', '请求超时，请稍后重试。');
				}
				if (err && typeof err.code === 'string') {
					throw err;
				}
				throw createRestError('wpcy_rest_error', (err && err.message) ? err.message : '暂时无法完成请求，请稍后重试。');
			})
			.finally(function () {
				if (timer) {
					clearTimeout(timer);
				}
			});
	}

	function resolveAppLabel(manifest) {
		if (!manifest || typeof manifest !== 'object') {
			return '';
		}
		if (manifest.name && typeof manifest.name === 'object') {
			return manifest.name.zh_CN || manifest.name.en_US || String(manifest.id || '');
		}
		if (typeof manifest.name === 'string' && manifest.name) {
			return manifest.name;
		}
		return String(manifest.id || '');
	}

	function getDisabledSet() {
		var cfg = getConfig();
		var list = (cfg.settings && cfg.settings.apps && Array.isArray(cfg.settings.apps.disabled))
			? cfg.settings.apps.disabled
			: [];
		var map = {};
		list.forEach(function (id) {
			if (typeof id === 'string' && id) {
				map[id] = true;
			}
		});
		return map;
	}

	function makeSparkleSvg() {
		var ns = 'http://www.w3.org/2000/svg';
		var svg = document.createElementNS(ns, 'svg');
		svg.setAttribute('class', 'wenpai-ico');
		svg.setAttribute('viewBox', '0 0 24 24');
		svg.setAttribute('fill', 'currentColor');
		svg.setAttribute('aria-hidden', 'true');
		var p = document.createElementNS(ns, 'path');
		p.setAttribute('d', SPARKLE_PATH);
		svg.appendChild(p);
		return svg;
	}

	function generateSessionToken() {
		var bytes = new Uint8Array(16);
		crypto.getRandomValues(bytes);
		var hex = '';
		for (var i = 0; i < bytes.length; i++) {
			var b = bytes[i].toString(16);
			hex += b.length < 2 ? ('0' + b) : b;
		}
		return hex;
	}

	function extractAccessStatus(manifest) {
		if (!manifest || typeof manifest !== 'object') {
			return '';
		}
		var raw = manifest[ACCESS_STATUS_KEY];
		if (typeof raw === 'string') {
			return raw;
		}
		if (raw && typeof raw === 'object' && typeof raw.status === 'string') {
			return raw.status;
		}
		return '';
	}

	function isAccessGranted(status) {
		return status === 'active' || status === 'ok' || status === 'granted';
	}

	function buildServicesAppHref(servicesUrl, appId) {
		var base = String(servicesUrl || 'admin.php?page=wpcy-services');
		var sep = base.indexOf('?') === -1 ? '?' : '&';
		return base + sep + 'app=' + encodeURIComponent(appId);
	}

	function initOverviewToolbox(fetchApps) {
		var overviewEl = document.querySelector('[data-wpcy-screen="overview"]');
		if (!overviewEl) {
			return;
		}
		var slots = Array.prototype.slice.call(overviewEl.querySelectorAll('[data-wpcy-quick="reserved-slot"]'));
		if (!slots.length) {
			return;
		}

		fetchApps().then(function (data) {
			var apps = (data && Array.isArray(data.apps)) ? data.apps : [];
			var disabled = getDisabledSet();
			var enabledApps = apps.filter(function (app) {
				return app && typeof app.id === 'string' && app.id !== '' && !disabled[app.id];
			}).slice(0, 4);

			enabledApps.forEach(function (app, idx) {
				var btn = slots[idx];
				if (!btn) {
					return;
				}
				var label = resolveAppLabel(app);
				var targetUrl = 'admin.php?page=wpcy-services&app=' + encodeURIComponent(app.id);

				btn.classList.remove('is-more');
				btn.setAttribute('data-wpcy-quick', 'app');
				btn.setAttribute('data-wpcy-app-id', app.id);

				var glyphEl = btn.querySelector('.wenpai-icon-box__glyph');
				if (glyphEl) {
					glyphEl.innerHTML = '';
					glyphEl.appendChild(makeSparkleSvg());
				}
				var labelEl = btn.querySelector('.wenpai-icon-box__label');
				if (labelEl) {
					labelEl.textContent = label;
				} else {
					btn.textContent = label;
				}

				btn.addEventListener('click', function (e) {
					e.preventDefault();
					window.location.href = targetUrl;
				});
			});
		}).catch(function () {});
	}

	function initServicesCard(fetchApps) {
		var sectionEl = document.querySelector('[data-wpcy-apps-section]');
		if (!sectionEl) {
			return;
		}
		var liveEl = sectionEl.querySelector('[data-wpcy-apps-live]');
		var gridWrap = sectionEl.querySelector('[data-wpcy-apps-grid]');
		var servicesUrl = sectionEl.getAttribute('data-wpcy-services-url') || 'admin.php?page=wpcy-services';

		fetchApps().then(function (data) {
			if (data && data.index_status === 'unreachable') {
				if (liveEl) {
					liveEl.textContent = '小工具目录暂时不可用。';
				}
			}

			if (!gridWrap || !data || !Array.isArray(data.apps) || !data.apps.length) {
				return;
			}

			var grid = document.createElement('div');
			grid.className = 'wenpai-icon-grid';

			data.apps.forEach(function (app) {
				if (!app || typeof app.id !== 'string' || !app.id) {
					return;
				}
				var link = document.createElement('a');
				link.className = 'wenpai-icon-box';
				link.href = buildServicesAppHref(servicesUrl, app.id);
				link.setAttribute('data-wpcy-app-id', app.id);

				var glyph = document.createElement('span');
				glyph.className = 'wenpai-icon-box__glyph';
				glyph.setAttribute('aria-hidden', 'true');
				glyph.appendChild(makeSparkleSvg());

				var lbl = document.createElement('span');
				lbl.className = 'wenpai-icon-box__label';
				lbl.textContent = resolveAppLabel(app);

				link.appendChild(glyph);
				link.appendChild(lbl);
				grid.appendChild(link);
			});

			gridWrap.innerHTML = '';
			gridWrap.appendChild(grid);
		}).catch(function () {
			if (liveEl) {
				liveEl.textContent = '小工具目录暂时不可用。';
			}
		});
	}

	function renderUnlockPrompt(stageEl, statusEl, appId) {
		if (statusEl) {
			statusEl.textContent = '该小工具需要先开通站点权益后使用。';
		}
		if (!stageEl) {
			return;
		}
		stageEl.innerHTML = '';

		var wrap = document.createElement('div');
		wrap.className = 'wenpai-stack';

		var note = document.createElement('p');
		note.className = 'meta';
		note.textContent = '当前站点尚未开通该小工具所需权益，前往文派云完成开通后即可在此运行。';

		var actions = document.createElement('div');
		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'btn btn-primary';
		btn.setAttribute('data-wpcy-app-unlock', appId);
		btn.textContent = '了解与开通';

		btn.addEventListener('click', function () {
			btn.disabled = true;
			requestRest('/apps/' + encodeURIComponent(appId) + '/go', { method: 'POST' })
				.then(function (res) {
					if (res && typeof res.url === 'string' && /^https:\/\//i.test(res.url)) {
						window.open(res.url, '_blank', 'noopener,noreferrer');
					}
				})
				.catch(function () {})
				.finally(function () {
					btn.disabled = false;
				});
		});

		actions.appendChild(btn);
		wrap.appendChild(note);
		wrap.appendChild(actions);
		stageEl.appendChild(wrap);
	}

	function mountSandboxedBridge(stageEl, statusEl, appId, manifest) {
		var expectedOrigin = '';
		try {
			expectedOrigin = new URL(manifest.entry_url).origin;
		} catch (e) {
			if (statusEl) {
				statusEl.textContent = '小工具入口地址无效，已终止加载。';
			}
			return;
		}
		if (!expectedOrigin || expectedOrigin === 'null') {
			if (statusEl) {
				statusEl.textContent = '小工具入口地址无效，已终止加载。';
			}
			return;
		}

		var sessionToken = generateSessionToken();
		var resizeTimer = null;

		var iframe = document.createElement('iframe');
		iframe.setAttribute('sandbox', 'allow-scripts allow-forms');
		iframe.setAttribute('referrerpolicy', 'strict-origin');
		iframe.setAttribute('width', '100%');
		iframe.setAttribute('height', '420');
		iframe.setAttribute('frameborder', '0');
		iframe.setAttribute('data-testid', 'wpcy-app-iframe');
		iframe.setAttribute('src', manifest.entry_url);

		if (statusEl) {
			statusEl.textContent = '运行环境已隔离就绪。';
		}
		if (stageEl) {
			stageEl.innerHTML = '';
			stageEl.appendChild(iframe);
		}

		function postToTool(envelope) {
			if (iframe.contentWindow) {
				iframe.contentWindow.postMessage(envelope, expectedOrigin);
			}
		}

		function forwardRequest(type, requestId, payload) {
			var base = '/apps/' + encodeURIComponent(appId);
			var key = (payload && typeof payload.key === 'string') ? payload.key : '';
			var promise = null;

			if (type === 'context.get') {
				promise = requestRest(base + '/context', { method: 'GET' });
			} else if (type === 'data.list') {
				promise = requestRest(base + '/data', { method: 'GET' });
			} else if (type === 'data.get') {
				promise = requestRest(base + '/data/' + encodeURIComponent(key), { method: 'GET' });
			} else if (type === 'data.set') {
				promise = requestRest(base + '/data/' + encodeURIComponent(key), {
					method: 'PUT',
					body: { value: payload ? payload.value : null }
				});
			} else if (type === 'data.delete') {
				promise = requestRest(base + '/data/' + encodeURIComponent(key), { method: 'DELETE' });
			} else if (type === 'go.open') {
				promise = requestRest(base + '/go', { method: 'POST' }).then(function (res) {
					if (res && typeof res.url === 'string' && /^https:\/\//i.test(res.url)) {
						window.open(res.url, '_blank', 'noopener,noreferrer');
					}
					return res;
				});
			} else if (type === ACCESS_GET_TYPE) {
				promise = requestRest(base + '/' + ACCESS_SLUG, { method: 'GET' });
			} else {
				return;
			}

			promise.then(function (resData) {
				postToTool({
					wpcy: 1,
					type: 'result',
					request_id: requestId,
					payload: resData
				});
			}).catch(function (err) {
				postToTool({
					wpcy: 1,
					type: 'error',
					request_id: requestId,
					error: {
						code: (err && err.code) ? String(err.code) : 'wpcy_rest_error',
						message: (err && err.message) ? String(err.message) : 'Request failed'
					}
				});
			});
		}

		window.addEventListener('message', function (event) {
			if (!iframe.contentWindow || event.source !== iframe.contentWindow) {
				return;
			}
			if (event.origin !== expectedOrigin) {
				return;
			}
			var data = event.data;
			if (!data || typeof data !== 'object' || data.wpcy !== 1 || typeof data.type !== 'string' || !data.type) {
				return;
			}

			var type = data.type;
			if (type === 'ready') {
				var cfg = getConfig();
				var locale = (cfg.siteContext && cfg.siteContext.locale) || 'zh_CN';
				postToTool({
					wpcy: 1,
					type: 'init',
					payload: {
						protocol: 1,
						app_id: appId,
						session_token: sessionToken,
						locale: locale
					}
				});
				return;
			}

			var requestId = typeof data.request_id === 'string' ? data.request_id : '';
			if (data.session_token !== sessionToken) {
				postToTool({
					wpcy: 1,
					type: 'error',
					request_id: requestId,
					error: {
						code: 'wpcy_apps_session_invalid',
						message: 'Invalid session'
					}
				});
				return;
			}

			var payload = (data.payload && typeof data.payload === 'object') ? data.payload : {};
			if (type === 'resize') {
				var rawHeight = Number(payload.height);
				var clamped = isFinite(rawHeight) ? Math.max(160, Math.min(4000, Math.round(rawHeight))) : 160;
				if (resizeTimer) {
					clearTimeout(resizeTimer);
				}
				resizeTimer = setTimeout(function () {
					iframe.setAttribute('height', String(clamped));
				}, 200);
				return;
			}

			forwardRequest(type, requestId, payload);
		});
	}

	function initAppHost(fetchApps) {
		var hostEl = document.querySelector('[data-wpcy-screen="app-host"]');
		if (!hostEl) {
			return;
		}
		var appId = hostEl.getAttribute('data-wpcy-app-id') || '';
		var statusEl = hostEl.querySelector('[data-wpcy-app-status]');
		var stageEl = hostEl.querySelector('[data-wpcy-app-stage]');
		var titleEl = hostEl.querySelector('.card-title');

		fetchApps().then(function (data) {
			var apps = (data && Array.isArray(data.apps)) ? data.apps : [];
			var manifest = null;
			for (var i = 0; i < apps.length; i++) {
				if (apps[i] && apps[i].id === appId) {
					manifest = apps[i];
					break;
				}
			}

			if (!manifest) {
				if (statusEl) {
					statusEl.textContent = (data && data.index_status === 'unreachable')
						? '小工具目录暂时不可用。'
						: '暂时无法打开该小工具，请刷新目录后重试。';
				}
				return;
			}

			var label = resolveAppLabel(manifest);
			if (titleEl && label) {
				titleEl.textContent = label;
			}

			var accessStatus = extractAccessStatus(manifest);
			if (manifest.tier === 'paid' && !isAccessGranted(accessStatus)) {
				renderUnlockPrompt(stageEl, statusEl, appId);
				return;
			}

			mountSandboxedBridge(stageEl, statusEl, appId, manifest);
		}).catch(function () {
			if (statusEl) {
				statusEl.textContent = '小工具目录暂时不可用。';
			}
		});
	}

	function boot() {
		var hasOverview = !!document.querySelector('[data-wpcy-screen="overview"]');
		var hasServices = !!document.querySelector('[data-wpcy-apps-section]');
		var hasHost = !!document.querySelector('[data-wpcy-screen="app-host"]');
		if (!hasOverview && !hasServices && !hasHost) {
			return;
		}

		var appsPromise = null;
		function fetchApps() {
			if (!appsPromise) {
				appsPromise = requestRest('/apps', { method: 'GET' });
			}
			return appsPromise;
		}

		initOverviewToolbox(fetchApps);
		initServicesCard(fetchApps);
		initAppHost(fetchApps);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
