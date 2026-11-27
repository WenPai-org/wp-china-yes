/**
 * WPCY Admin Notification Center Drawer.
 *
 * Aligned with WordPress Core "Declared Intent" model:
 * - Operational notices (core updates, errors, Site Health, settings feedback) remain inline.
 * - Non-critical third-party notices fold into the Admin Bar drawer with full interactive fidelity.
 */
(function () {
	'use strict';

	var PROTECTED_PATTERNS = [
		'update-nag',
		'update-core',
		'core-update',
		'site-health',
		'health-check',
		'settings-error',
		'setting-error',
		'wp-core-ui',
		'security',
		'wenpai-next',
		'wpcy-'
	];

	function isProtectedNotice(el) {
		if (!el || el.nodeType !== 1) {
			return true;
		}
		if (el.hasAttribute('data-wpcy-keep') || el.closest('#wpcy-notice-drawer')) {
			return true;
		}
		if (el.closest('.wenpai-app, .wenpai-shell, .wp-editor-container, .edit-post-layout, .interface-interface-skeleton')) {
			return true;
		}
		if (el.classList.contains('inline') || el.classList.contains('hidden')) {
			return true;
		}
		// Never fold operational error alerts or settings save receipts.
		if (el.classList.contains('notice-error') || el.classList.contains('error') || el.classList.contains('update-nag')) {
			return true;
		}

		var sig = ((el.id || '') + ' ' + (el.className || '')).toLowerCase();
		for (var i = 0; i < PROTECTED_PATTERNS.length; i++) {
			if (sig.indexOf(PROTECTED_PATTERNS[i]) !== -1) {
				return true;
			}
		}

		// Preserve notices carrying explicit aria-live="assertive" / role="alert".
		var role = (el.getAttribute('role') || '').toLowerCase();
		if (role === 'alert') {
			return true;
		}

		return false;
	}

	function initNoticeCenter() {
		var drawer = document.getElementById('wpcy-notice-drawer');
		var barItem = document.getElementById('wp-admin-bar-wpcy-notice-center');
		if (!drawer || !barItem) {
			return;
		}

		// Yield if WordPress Core / WP Feature Notifications native hub is present in DOM.
		if (document.getElementById('wp-notifications-hub') || document.getElementById('wp-admin-bar-wp-notifications')) {
			return;
		}

		var listEl = drawer.querySelector('[data-wpcy-nc-list]');
		var emptyEl = drawer.querySelector('[data-wpcy-nc-empty]');
		var countEl = barItem.querySelector('[data-wpcy-nc-count]');
		var restoreBtn = drawer.querySelector('[data-wpcy-nc-restore]');
		var restored = false;
		var entries = [];

		function updateCount() {
			var visibleCount = 0;
			for (var i = 0; i < entries.length; i++) {
				var node = entries[i].node;
				if (node && node.parentNode === listEl && node.style.display !== 'none') {
					visibleCount++;
				}
			}
			if (countEl) {
				countEl.textContent = String(visibleCount);
			}
			if (visibleCount > 0) {
				barItem.classList.remove('is-empty');
				if (emptyEl) {
					emptyEl.hidden = true;
				}
			} else {
				barItem.classList.add('is-empty');
				if (emptyEl) {
					emptyEl.hidden = false;
				}
			}
		}

		function collectNotices() {
			if (restored) {
				return;
			}
			var root = document.getElementById('wpbody-content');
			if (!root) {
				return;
			}
			var candidates = root.querySelectorAll('.notice, .updated');
			for (var i = 0; i < candidates.length; i++) {
				var el = candidates[i];
				if (el.parentNode === listEl || isProtectedNotice(el)) {
					continue;
				}
				// Skip hidden promo banners already collapsed to 0 height by PromoRules CSS.
				var style = window.getComputedStyle(el);
				if (style && style.display === 'none') {
					continue;
				}
				var parent = el.parentNode;
				if (!parent) {
					continue;
				}
				var existingEntry = null;
				for (var eIdx = 0; eIdx < entries.length; eIdx++) {
					if (entries[eIdx].node === el) {
						existingEntry = entries[eIdx];
						break;
					}
				}
				// Add `.inline` while folded so WordPress core `common.js` never yanks notices back out of the drawer.
				if (!el.classList.contains('inline')) {
					el.classList.add('inline');
					el.dataset.wpcyAddedInline = '1';
				}
				if (!existingEntry) {
					var placeholder = document.createComment('wpcy-nc-slot');
					parent.insertBefore(placeholder, el);
					entries.push({ node: el, placeholder: placeholder });
				}
				el.dataset.wpcyFolded = '1';
				listEl.appendChild(el);
			}
			updateCount();
		}

		function openDrawer() {
			collectNotices();
			drawer.hidden = false;
			drawer.setAttribute('aria-hidden', 'false');
			document.body.classList.add('wpcy-nc-open');
		}

		function closeDrawer() {
			drawer.hidden = true;
			drawer.setAttribute('aria-hidden', 'true');
			document.body.classList.remove('wpcy-nc-open');
		}

		function restoreAll() {
			restored = true;
			for (var i = 0; i < entries.length; i++) {
				var entry = entries[i];
				if (entry.placeholder && entry.placeholder.parentNode && entry.node) {
					delete entry.node.dataset.wpcyFolded;
					if (entry.node.dataset.wpcyAddedInline === '1') {
						entry.node.classList.remove('inline');
						delete entry.node.dataset.wpcyAddedInline;
					}
					entry.placeholder.parentNode.insertBefore(entry.node, entry.placeholder);
					entry.placeholder.parentNode.removeChild(entry.placeholder);
				}
			}
			entries = [];
			updateCount();
			closeDrawer();
		}

		var link = barItem.querySelector('a');
		if (link) {
			link.addEventListener('click', function (e) {
				e.preventDefault();
				if (drawer.hidden) {
					openDrawer();
				} else {
					closeDrawer();
				}
			});
		}

		var closers = drawer.querySelectorAll('[data-wpcy-nc-close]');
		for (var c = 0; c < closers.length; c++) {
			closers[c].addEventListener('click', closeDrawer);
		}

		if (restoreBtn) {
			restoreBtn.addEventListener('click', restoreAll);
		}

		// Recalculate badge when a user clicks a notice's own dismiss button inside the drawer.
		listEl.addEventListener('click', function (e) {
			if (e.target && e.target.closest('.notice-dismiss')) {
				window.setTimeout(updateCount, 260);
			}
		});

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && !drawer.hidden) {
				closeDrawer();
			}
		});

		// Run after WP core `common.js` notice relocation (`window.load` / jQuery ready) plus a late sweep.
		window.setTimeout(collectNotices, 0);
		window.addEventListener('load', collectNotices);
		window.setTimeout(collectNotices, 600);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initNoticeCenter);
	} else {
		initNoticeCenter();
	}
})();
