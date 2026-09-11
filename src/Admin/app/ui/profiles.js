/**
 * Site-scene catalog (ADR-004 2026-09-11: four scenes).
 */

import { __ } from '@wordpress/i18n';

/**
 * Four scene cards. Copy from admin-ui-spec v2.2 / prototype e/v7.
 *
 * @return {Array<{id:string,title:string,desc:string}>} Cards.
 */
export function sceneCards() {
	return [
		{
			id: 'domestic',
			title: __( '国内站', 'wp-china-yes' ),
			desc: __( '网站和买家都在国内', 'wp-china-yes' ),
		},
		{
			id: 'crossborder',
			title: __( '跨境 · 外贸站', 'wp-china-yes' ),
			desc: __(
				'店开在海外，卖给海外买家，你在国内管理',
				'wp-china-yes'
			),
		},
		{
			id: 'inbound',
			title: __( '内贸 · 进中国站', 'wp-china-yes' ),
			desc: __( '店开在海外，主要做中国买家的生意', 'wp-china-yes' ),
		},
		{
			id: 'mixed',
			title: __( '混合站', 'wp-china-yes' ),
			desc: __( '国内外的买家都有', 'wp-china-yes' ),
		},
	];
}

/**
 * Scene display name.
 *
 * @param {string} profile
 * @return {string} Value.
 */
export function profileLabel( profile ) {
	const hit = sceneCards().find( ( card ) => card.id === profile );
	return hit ? hit.title : __( '国内站', 'wp-china-yes' );
}

/**
 * Whether this scene is overseas-admin density (next-step bind, eco extra).
 *
 * @param {string} profile
 * @return {boolean} Value.
 */
export function isOverseasAdmin( profile ) {
	return (
		profile === 'crossborder' ||
		profile === 'mixed' ||
		profile === 'inbound'
	);
}

/**
 * Scene default connectivity snapshot. Mirrors Config\Profile matrix.
 *
 * @param {string} profile
 * @return {Object} Defaults.
 */
export function sceneDefaults( profile ) {
	const five = [
		'google_fonts',
		'google_ajax',
		'cdnjs',
		'jsdelivr',
		'emoji',
	];
	const matrix = {
		domestic: {
			wordpress_org: 'auto',
			public_assets: { items: five, scope: 'both' },
			avatar: 'cravatar_cn',
			heartbeat: 'off',
			dashboard_feeds: 'allow',
			windfonts: false,
		},
		crossborder: {
			wordpress_org: 'off',
			public_assets: { items: five, scope: 'admin' },
			avatar: 'cravatar_cn',
			heartbeat: 'on',
			dashboard_feeds: 'block',
			windfonts: false,
		},
		inbound: {
			wordpress_org: 'off',
			public_assets: { items: five, scope: 'frontend' },
			avatar: 'cravatar_cn',
			heartbeat: 'off',
			dashboard_feeds: 'allow',
			windfonts: false,
		},
		mixed: {
			wordpress_org: 'auto',
			public_assets: { items: five, scope: 'admin' },
			avatar: 'cravatar_cn',
			heartbeat: 'on',
			dashboard_feeds: 'block',
			windfonts: false,
		},
	};
	return matrix[ profile ] || matrix.domestic;
}

/**
 * Human names for customized keys, used by scene-switch warning.
 *
 * @param {string} key
 * @return {string} Value.
 */
export function customKeyLabel( key ) {
	const map = {
		wordpress_org: __( 'WordPress.org 源', 'wp-china-yes' ),
		public_assets: __( '公共库接通', 'wp-china-yes' ),
		avatar: __( '头像', 'wp-china-yes' ),
		heartbeat: __( '减少后台心跳', 'wp-china-yes' ),
		dashboard_feeds: __( '不加载仪表盘的外部内容', 'wp-china-yes' ),
		windfonts: __( '中文字体', 'wp-china-yes' ),
	};
	return map[ key ] || key;
}

/**
 * Keys that differ from the scene default.
 *
 * @param {Object} settings
 * @return {string[]} Keys.
 */
export function customizedKeys( settings ) {
	const profile = settings?.profile || 'domestic';
	const def = sceneDefaults( profile );
	const conn = settings?.connectivity || {};
	const keys = [];
	if ( ( conn.wordpress_org || 'auto' ) !== def.wordpress_org ) {
		keys.push( 'wordpress_org' );
	}
	const scope = conn.public_assets?.scope || 'both';
	if ( scope !== def.public_assets.scope ) {
		keys.push( 'public_assets' );
	}
	const avatar = conn.avatar || 'cravatar_cn';
	const avatarValue = typeof avatar === 'string' ? avatar : avatar.admin;
	if ( ( avatarValue || 'cravatar_cn' ) !== def.avatar ) {
		keys.push( 'avatar' );
	}
	if ( ( conn.heartbeat || 'off' ) !== def.heartbeat ) {
		keys.push( 'heartbeat' );
	}
	if ( ( conn.dashboard_feeds || 'allow' ) !== def.dashboard_feeds ) {
		keys.push( 'dashboard_feeds' );
	}
	if ( Boolean( settings?.modules?.windfonts ) !== def.windfonts ) {
		keys.push( 'windfonts' );
	}
	return keys;
}

/**
 * Scope chip copy.
 *
 * @param {string} scope
 * @return {string} Value.
 */
export function scopeWord( scope ) {
	if ( scope === 'admin' ) {
		return __( '只在后台', 'wp-china-yes' );
	}
	if ( scope === 'frontend' ) {
		return __( '只在前台', 'wp-china-yes' );
	}
	if ( scope === 'both' ) {
		return __( '后台与前台', 'wp-china-yes' );
	}
	return '';
}
