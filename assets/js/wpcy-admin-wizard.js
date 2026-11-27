/**
 * WPCY 4.0 Onboarding Wizard and Companion 1-click provisioning interactions.
 */
(function () {
	'use strict';

	const kit = window.wpcyAdminKit;
	if (!kit) return;

	const api = kit.api;
	const notify = kit.notify;
	const toast = kit.toast;
	const stashFlashToast = kit.stashFlashToast;

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

	kit.registerInit(initWizard);
	kit.registerInit(initCompanionProvisioning);
})();
