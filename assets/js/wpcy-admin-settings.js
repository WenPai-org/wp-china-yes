/**
 * WPCY 4.0 Settings & Feature forms progressive enhancement:
 * instant toggles, White-Label toggles, and unified settings save.
 */
(function () {
	'use strict';

	const kit = window.wpcyAdminKit;
	if (!kit) return;

	const api = kit.api;
	const notify = kit.notify;
	const showToast = kit.showToast;
	const settingsEndpoint = kit.settingsEndpoint;

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
						showToast('已保存', 'ok');
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
				dashboard_feeds: fd.get('dashboard_feeds') ? 'block' : 'allow'
			};
			if (form.querySelector('select[name="icon_photos_mode"]')) {
				connPatch.icon_photos = { enabled: String(fd.get('icon_photos_mode') || 'off') };
			}
			const payload = {
				profile: String(fd.get('profile') || 'domestic'),
				apply_defaults: false,
				connectivity: connPatch
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
						promo_cta_url: String(fd.get('wl_promo_cta_url') || '').trim()
					}
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

	kit.registerInit(initSettings);
})();
