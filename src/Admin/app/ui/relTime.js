/**
 * Relative time and number formatting (admin-ui-spec §5).
 *
 */

import { __, sprintf } from '@wordpress/i18n';

/**
 * Relative time: <60s 刚刚, <60min N 分钟前, <24h N 小时前, else N 天前.
 *
 * @param {string} iso   UTC ISO 8601.
 * @param {number} [now] Epoch ms.
 * @return {string} Value.
 */
export function relTime( iso, now = Date.now() ) {
	if ( ! iso ) {
		return '';
	}
	const then = Date.parse( iso );
	if ( Number.isNaN( then ) ) {
		return '';
	}
	const sec = Math.max( 0, Math.round( ( now - then ) / 1000 ) );
	if ( sec < 60 ) {
		return __( '刚刚', 'wp-china-yes' );
	}
	const min = Math.floor( sec / 60 );
	if ( min < 60 ) {
		return sprintf(
			/* translators: %d: minutes */
			__( '%d 分钟前', 'wp-china-yes' ),
			min
		);
	}
	const hour = Math.floor( min / 60 );
	if ( hour < 24 ) {
		return sprintf(
			/* translators: %d: hours */
			__( '%d 小时前', 'wp-china-yes' ),
			hour
		);
	}
	const day = Math.floor( hour / 24 );
	return sprintf(
		/* translators: %d: days */
		__( '%d 天前', 'wp-china-yes' ),
		day
	);
}

/**
 * Timeline absolute-ish label: 今天 HH:MM / 昨天 HH:MM / M 月 D 日.
 *
 * @param {string} iso   UTC ISO 8601.
 * @param {number} [now] Epoch ms.
 * @return {string} Value.
 */
export function eventTime( iso, now = Date.now() ) {
	if ( ! iso ) {
		return '';
	}
	const then = Date.parse( iso );
	if ( Number.isNaN( then ) ) {
		return '';
	}
	const d = new Date( then );
	const n = new Date( now );
	const pad = ( v ) => String( v ).padStart( 2, '0' );
	const sameDay = ( a, b ) =>
		a.getFullYear() === b.getFullYear() &&
		a.getMonth() === b.getMonth() &&
		a.getDate() === b.getDate();
	const yest = new Date( n );
	yest.setDate( n.getDate() - 1 );
	const hm = pad( d.getHours() ) + ':' + pad( d.getMinutes() );
	if ( sameDay( d, n ) ) {
		return sprintf(
			/* translators: %s: HH:MM */
			__( '今天 %s', 'wp-china-yes' ),
			hm
		);
	}
	if ( sameDay( d, yest ) ) {
		return sprintf(
			/* translators: %s: HH:MM */
			__( '昨天 %s', 'wp-china-yes' ),
			hm
		);
	}
	return sprintf(
		/* translators: 1: month 2: day */
		__( '%1$d 月 %2$d 日', 'wp-china-yes' ),
		d.getMonth() + 1,
		d.getDate()
	);
}

/**
 * Integer with thousands separators.
 *
 * @param {number} n
 * @return {string} Value.
 */
export function formatInt( n ) {
	return Math.round( Number( n ) || 0 ).toLocaleString( 'en-US' );
}

/**
 * mirror_bytes_saved: < 1 GB → N MB (int), else N.N GB.
 *
 * @param {number} bytes
 * @return {string} Value.
 */
export function formatBytes( bytes ) {
	const n = Number( bytes ) || 0;
	const gb = 1073741824;
	if ( n < gb ) {
		return Math.round( n / 1048576 ) + ' MB';
	}
	return ( n / gb ).toFixed( 1 ) + ' GB';
}

/**
 * Whole days since ISO, floor, min 0.
 *
 * @param {string} iso
 * @param {number} [now]
 * @return {number} Value.
 */
export function daysSince( iso, now = Date.now() ) {
	if ( ! iso ) {
		return 0;
	}
	const then = Date.parse( iso );
	if ( Number.isNaN( then ) ) {
		return 0;
	}
	return Math.max( 0, Math.floor( ( now - then ) / 86400000 ) );
}

/**
 * Sum of series values.
 *
 * @param {Array<{value: number}>} series
 * @return {number} Value.
 */
export function sumSeries( series ) {
	if ( ! Array.isArray( series ) ) {
		return 0;
	}
	return series.reduce(
		( acc, row ) => acc + ( Number( row?.value ) || 0 ),
		0
	);
}
