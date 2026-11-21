/**
 * WPCY 4.0 browser probe persistence & non-intrusive scene suggestion (zero dependencies).
 *
 * 1. Persists real client-side latency samples to `POST /wpcy/v1/diagnostics/client-probe`
 *    so WP-CLI `wp wpcy status`, Site Health, and SSR tiles stay hydrated.
 * 2. Queries `GET /wpcy/v1/profile/suggest` on Wizard and Settings to append a non-intrusive
 *    recommendation badge without altering user radio selection.
 */
(function () {
	'use strict';

	const PROBE_TARGETS = [
		{
			target: 'cn.cravatar.com',
			url: 'https://cn.cravatar.com/avatar/00000000000000000000000000000000?d=mp&s=16'
		},
		{
			target: 'googlefonts.admincdn.com',
			url: 'https://googlefonts.admincdn.com/css?family=Roboto'
		}
	];

	const VALID_PROFILES = ['domestic', 'crossborder', 'mixed'];
	let probeInFlight = false;

	function getConfig() {
		const cfg = window.wpcyAdmin || {};
		return {
			restRoot: String(cfg.restRoot || '').replace(/\/+$/, ''),
			nonce: String(cfg.nonce || '')
		};
	}

	async function restRequest(path, options) {
		const cfg = getConfig();
		const opts = Object.assign({ method: 'GET', headers: {} }, options || {});
		opts.headers['X-WP-Nonce'] = cfg.nonce;
		if (opts.body && typeof opts.body !== 'string') {
			opts.headers['Content-Type'] = 'application/json';
			opts.body = JSON.stringify(opts.body);
		}
		const res = await fetch(cfg.restRoot + '/wpcy/v1' + path, opts);
		const data = await res.json().catch(function () { return {}; });
		if (!res.ok) {
			throw new Error((data && data.message) ? data.message : 'Request failed');
		}
		return data;
	}

	function nowMs() {
		if (typeof performance !== 'undefined' && typeof performance.now === 'function') {
			return performance.now();
		}
		return Date.now();
	}

	async function measureOne(entry) {
		const t0 = nowMs();
		try {
			await fetch(entry.url, { mode: 'no-cors', cache: 'no-store' });
			const elapsed = Math.max(1, Math.round(nowMs() - t0));
			return {
				url: entry.url,
				target: entry.target,
				result: 'ok',
				latency_ms: elapsed
			};
		} catch (e) {
			return {
				url: entry.url,
				target: entry.target,
				result: 'down',
				latency_ms: null
			};
		}
	}

	async function runAndPersistBrowserProbes() {
		if (probeInFlight) {
			return;
		}
		probeInFlight = true;
		try {
			const probes = [];
			for (let i = 0; i < PROBE_TARGETS.length; i++) {
				probes.push(await measureOne(PROBE_TARGETS[i]));
			}
			await restRequest('/diagnostics/client-probe', {
				method: 'POST',
				body: { probes: probes }
			});
		} catch (e) {
			// Silent background persistence; UI error toasts belong to interactive handlers.
		} finally {
			probeInFlight = false;
		}
	}

	function initBrowserProbePersistence() {
		const hasOverviewTiles = !!document.querySelector('[data-wpcy-latency]');
		const hasDiagnoseList = !!document.querySelector('[data-wpcy-diag-browser]');
		if (hasOverviewTiles || hasDiagnoseList) {
			runAndPersistBrowserProbes();
		}

		document.querySelectorAll('[data-wpcy-overview-probe], [data-wpcy-action="diagnose"]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				runAndPersistBrowserProbes();
			});
		});
	}

	function resolveTimezone() {
		try {
			if (typeof Intl !== 'undefined' && Intl.DateTimeFormat) {
				return String(Intl.DateTimeFormat().resolvedOptions().timeZone || '');
			}
		} catch (e) {
			// Ignore unsupported Intl environments.
		}
		return '';
	}

	function appendSuggestedPill(form, profile) {
		if (VALID_PROFILES.indexOf(profile) === -1) {
			return;
		}
		const radio = form.querySelector('input[name="profile"][value="' + profile + '"]');
		if (!radio || radio.type !== 'radio') {
			return;
		}
		const label = radio.closest('label');
		const titleEl = label ? label.querySelector('.t') : null;
		if (!titleEl || titleEl.querySelector('[data-wpcy-suggested-pill]')) {
			return;
		}
		const pill = document.createElement('span');
		pill.className = 'pill accent';
		pill.setAttribute('data-wpcy-suggested-pill', '1');
		pill.textContent = '智能推荐';
		titleEl.appendChild(document.createTextNode(' '));
		titleEl.appendChild(pill);
	}

	function initSceneSuggestion() {
		const forms = Array.prototype.slice.call(
			document.querySelectorAll('#wpcy-wizard-form, #wpcy-settings-form')
		).filter(function (form) {
			return !!form.querySelector('input[type="radio"][name="profile"]');
		});
		if (!forms.length) {
			return;
		}

		const locale = (typeof navigator !== 'undefined' && navigator.language) ? String(navigator.language) : '';
		const timezone = resolveTimezone();
		const query = '?locale=' + encodeURIComponent(locale) + '&timezone=' + encodeURIComponent(timezone);

		restRequest('/profile/suggest' + query)
			.then(function (data) {
				const suggested = (data && typeof data.suggested_profile === 'string' && data.suggested_profile)
					? data.suggested_profile
					: ((data && typeof data.suggestion === 'string' && data.suggestion) ? data.suggestion : '');
				if (!suggested) {
					return;
				}
				forms.forEach(function (form) {
					appendSuggestedPill(form, suggested);
				});
			})
			.catch(function () {});
	}

	function boot() {
		initBrowserProbePersistence();
		initSceneSuggestion();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
