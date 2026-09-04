/**
 * Page slug from ?page= and in-page views from hash only.
 */

export const PAGES = {
	overview: 'wpcy',
	connect: 'wpcy-connect',
	services: 'wpcy-services',
	diagnose: 'wpcy-diagnose',
	recovery: 'wpcy-recovery',
};

/**
 * Current admin.php?page= slug. Does not read iframe parent location.
 *
 * @return {string} Menu slug.
 */
export function getPageSlug() {
	const params = new URLSearchParams( window.location.search );
	return params.get( 'page' ) || PAGES.overview;
}

/**
 * Admin URL for a menu slug. Same-window navigation; no History API.
 *
 * @param {string} slug   Menu page slug.
 * @param {string} [hash] Optional hash, including leading #.
 * @return {string} Relative admin.php URL.
 */
export function adminPageUrl( slug, hash ) {
	const path = 'admin.php?page=' + encodeURIComponent( slug );
	if ( ! hash ) {
		return path;
	}
	return path + ( hash.charAt( 0 ) === '#' ? hash : '#' + hash );
}

/**
 * Parse `#/tab=connect` style hashes. Unknown keys ignored.
 *
 * @param {string} [hash] location.hash
 * @return {Record<string, string>} Parsed keys.
 */
export function parseHash( hash ) {
	const raw = ( hash || window.location.hash || '' ).replace( /^#\/?/, '' );
	if ( ! raw ) {
		return {};
	}
	const out = {};
	new URLSearchParams( raw ).forEach( ( value, key ) => {
		out[ key ] = value;
	} );
	return out;
}

/**
 * Write in-page view state to the hash. Does not change ?page=.
 *
 * @param {Record<string, string>} next Hash keys.
 */
export function writeHash( next ) {
	const params = new URLSearchParams();
	Object.keys( next ).forEach( ( key ) => {
		if ( next[ key ] ) {
			params.set( key, next[ key ] );
		}
	} );
	const encoded = params.toString();
	window.location.hash = encoded ? '#/' + encoded : '';
}

/**
 * Navigate to another wp-admin page in this window.
 *
 * @param {string} slug   Menu slug.
 * @param {string} [hash] Optional hash.
 */
export function goToPage( slug, hash ) {
	window.location.assign( adminPageUrl( slug, hash ) );
}
