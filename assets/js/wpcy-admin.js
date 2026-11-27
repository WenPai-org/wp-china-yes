/**
 * WPCY 4.0 lightweight progressive enhancement entrypoint (Route A, zero dependencies).
 * Loads after wpcy-admin-{core,overview,settings,diagnose,wizard}.js and boots all registered modules.
 */
(function () {
	'use strict';

	function boot() {
		if (window.wpcyAdminKit && typeof window.wpcyAdminKit.boot === 'function') {
			window.wpcyAdminKit.boot();
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
