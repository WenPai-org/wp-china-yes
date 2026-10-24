/**
 * Wizard copy builders (WZ-02 / WZ-04 / WZ-05). Pure functions so jest can
 * check them against sceneDefaults() without rendering.
 */

import { __ } from '@wordpress/i18n';
import { sceneDefaults } from './profiles';

/**
 * WZ-02 「已为你做的」: four rows generated from the scene default matrix.
 *
 * Each row carries the matrix key and value it was built from, so a test can
 * prove the copy follows sceneDefaults() instead of a hand-kept table.
 *
 * @param {string} profile Scene id.
 * @return {Array<{key:string,value:*,t:string,d:string}>} Rows.
 */
export function doneRows( profile ) {
	const def = sceneDefaults( profile );
	const rows = [];

	if ( def.wordpress_org === 'auto' ) {
		rows.push( {
			key: 'wordpress_org',
			value: 'auto',
			t: __( '更新与安装包走国内镜像', 'wp-china-yes' ),
			d: __( '不可用时自动回 WordPress.org', 'wp-china-yes' ),
		} );
	} else {
		rows.push( {
			key: 'wordpress_org',
			value: def.wordpress_org,
			t: __( 'WordPress.org 直连', 'wp-china-yes' ),
			d: __(
				'你的服务器在海外，直连更快；国内镜像已关闭',
				'wp-china-yes'
			),
		} );
	}

	const scope = def.public_assets.scope;
	if ( scope === 'admin' ) {
		rows.push( {
			key: 'public_assets.scope',
			value: scope,
			t: __( '后台公共库走国内可达源', 'wp-china-yes' ),
			d: __(
				'Google Fonts、Ajax、jsDelivr 只在后台接通；海外访客看到的前台不变',
				'wp-china-yes'
			),
		} );
	} else if ( scope === 'frontend' ) {
		rows.push( {
			key: 'public_assets.scope',
			value: scope,
			t: __( '前台公共库走国内可达源', 'wp-china-yes' ),
			d: __(
				'Google Fonts、Ajax、jsDelivr 只在前台接通；后台不动',
				'wp-china-yes'
			),
		} );
	} else {
		rows.push( {
			key: 'public_assets.scope',
			value: scope,
			t: __( '前端公共库走国内可达源', 'wp-china-yes' ),
			d: __(
				'Google Fonts、Ajax、jsDelivr、Emoji 在前台和后台都接通',
				'wp-china-yes'
			),
		} );
	}

	rows.push( {
		key: 'avatar',
		value: def.avatar,
		t: __( '头像走 Cravatar 中国线路', 'wp-china-yes' ),
		d:
			profile === 'domestic'
				? __( '前台后台都不再空头像', 'wp-china-yes' )
				: __( '全站不再空头像', 'wp-china-yes' ),
	} );

	if ( def.heartbeat === 'on' && def.dashboard_feeds === 'block' ) {
		rows.push( {
			key: 'heartbeat+dashboard_feeds',
			value: 'on+block',
			t: __( '后台心跳已节流，仪表盘外部内容已屏蔽', 'wp-china-yes' ),
			d: __( '减少你在国内操作海外后台的等待', 'wp-china-yes' ),
		} );
	} else {
		rows.push( {
			key: 'heartbeat+dashboard_feeds',
			value: def.heartbeat + '+' + def.dashboard_feeds,
			t: __( '后台保持 WordPress 默认', 'wp-china-yes' ),
			d: __( '心跳与仪表盘内容不改动', 'wp-china-yes' ),
		} );
	}

	return rows;
}

/**
 * Whether WooCommerce is among active plugins (same test as the services page).
 *
 * @param {string[]} plugins Active plugin slugs.
 * @return {boolean} Value.
 */
export function hasWoo( plugins ) {
	return ( plugins || [] ).some(
		( slug ) =>
			typeof slug === 'string' && slug.indexOf( 'woocommerce' ) !== -1
	);
}

/**
 * WZ-05 h1 by scene.
 *
 * @param {string} profile Scene id.
 * @return {string} Title.
 */
export function packTitle( profile ) {
	if ( profile === 'crossborder' ) {
		return __( '跨境店常用的，还可以加', 'wp-china-yes' );
	}
	if ( profile === 'inbound' ) {
		return __( '面向中国买家的，还可以加', 'wp-china-yes' );
	}
	return __( '可能还需要', 'wp-china-yes' );
}

/**
 * WZ-05 scene pack rows (L-3). WooCommerce rows only when WooCommerce is active.
 *
 * @param {string}  profile Scene id.
 * @param {boolean} woo     WooCommerce detected.
 * @return {Array<{id:string,icon:string,t:string,d:string}>} Rows.
 */
export function packRows( profile, woo ) {
	if ( profile === 'crossborder' || profile === 'inbound' ) {
		const font = {
			id: 'windfonts',
			icon: 'font',
			t: __( '中文字体（Windfonts）', 'wp-china-yes' ),
			d: __(
				'给中国买家看到的页面更好的中文字体 · 绑定后可用',
				'wp-china-yes'
			),
		};
		if ( ! woo ) {
			return [ font ];
		}
		return [
			{
				id: 'wechat-pay',
				icon: 'card',
				t: __( '微信支付 for WooCommerce', 'wp-china-yes' ),
				d: __(
					'让中国买家在你的海外店用微信付款 · 薇晓朵商城',
					'wp-china-yes'
				),
			},
			{
				id: 'order-notice',
				icon: 'bell',
				t: __( '订单微信通知', 'wp-china-yes' ),
				d: __(
					'新订单、退款实时推送到微信 · 薇晓朵商城',
					'wp-china-yes'
				),
			},
			font,
		];
	}
	return [
		{
			id: 'windfonts',
			icon: 'font',
			t: __( '中文字体（Windfonts）', 'wp-china-yes' ),
			d: __( '让站点用上更好看的中文字体 · 绑定后可用', 'wp-china-yes' ),
		},
		{
			id: 'apps',
			icon: 'grid',
			t: __( '小工具', 'wp-china-yes' ),
			d: __( '连接测速、字体预览，在沙箱中运行', 'wp-china-yes' ),
		},
	];
}

/**
 * WZ-04: the settings a scene preset changes from stock WordPress, read from
 * the saved settings document (so the count follows the real preset, not a
 * fixed number).
 *
 * One entry each for: WordPress.org mirror (not 'off'), every public-asset
 * item while the scope is not 'off', avatar (not 'off'), heartbeat throttle
 * ('on'), dashboard feeds blocked ('block') and icon / photo source
 * (not 'off'). Windfonts is bound-gated, not a preset change.
 *
 * @param {Object} settings Settings document returned by PUT /settings.
 * @return {string[]} Changed item keys.
 */
export function presetChanges( settings ) {
	const conn = settings?.connectivity || {};
	const changed = [];
	if ( ( conn.wordpress_org || 'auto' ) !== 'off' ) {
		changed.push( 'wordpress_org' );
	}
	const scope = conn.public_assets?.scope || 'both';
	const items = Array.isArray( conn.public_assets?.items )
		? conn.public_assets.items
		: [];
	if ( scope !== 'off' ) {
		items.forEach( ( item ) => changed.push( 'public_assets.' + item ) );
	}
	const avatar = conn.avatar || 'off';
	const avatarValue =
		typeof avatar === 'string' ? avatar : avatar.admin || 'off';
	if ( avatarValue !== 'off' ) {
		changed.push( 'avatar' );
	}
	if ( conn.heartbeat === 'on' ) {
		changed.push( 'heartbeat' );
	}
	if ( conn.dashboard_feeds === 'block' ) {
		changed.push( 'dashboard_feeds' );
	}
	const icons = conn.icon_photos?.enabled || 'off';
	if ( icons !== 'off' ) {
		changed.push( 'icon_photos' );
	}
	return changed;
}

/**
 * Public-asset items a missing `public_assets.items` stands for
 * (Config\Schema::PUBLIC_ASSETS).
 */
const DEFAULT_ITEMS = [
	'google_fonts',
	'google_ajax',
	'cdnjs',
	'jsdelivr',
	'emoji',
];

/**
 * Normalized per-field values of a settings document, keyed like
 * presetChanges(). Missing values read as their defaults, so a field that only
 * goes from absent to its explicit default is not a change.
 *
 * public_assets.<item> is the scope the item is live under ('off' when the
 * item is not listed or the scope is 'off'), so a scope change counts once per
 * affected item. Avatar compares the admin value of a split object.
 *
 * @param {Object}   settings Settings document.
 * @param {string[]} items    Item keys to read.
 * @return {Object<string,string>} Field key → normalized value.
 */
function presetFields( settings, items ) {
	const conn = settings?.connectivity || {};
	const out = { wordpress_org: conn.wordpress_org || 'auto' };
	const scope = conn.public_assets?.scope || 'both';
	const listed = Array.isArray( conn.public_assets?.items )
		? conn.public_assets.items
		: DEFAULT_ITEMS;
	items.forEach( ( item ) => {
		out[ 'public_assets.' + item ] =
			scope !== 'off' && listed.indexOf( item ) !== -1 ? scope : 'off';
	} );
	const avatar = conn.avatar || 'off';
	out.avatar = typeof avatar === 'string' ? avatar : avatar.admin || 'off';
	out.heartbeat = conn.heartbeat || 'off';
	out.dashboard_feeds = conn.dashboard_feeds || 'allow';
	out.icon_photos = conn.icon_photos?.enabled || 'off';
	return out;
}

/**
 * Item keys named by any of the documents (the defaults when one omits them).
 *
 * @param {Object[]} docs Settings documents.
 * @return {string[]} Items, first-seen order.
 */
function itemUnion( docs ) {
	const seen = [];
	docs.forEach( ( doc ) => {
		const items = doc?.connectivity?.public_assets?.items;
		( Array.isArray( items ) ? items : DEFAULT_ITEMS ).forEach(
			( item ) => {
				if ( seen.indexOf( item ) === -1 ) {
					seen.push( item );
				}
			}
		);
	} );
	return seen;
}

/**
 * WZ-04: the fields that actually changed between the settings document
 * before the scene was applied and the one the server returned after it.
 * Same field grouping as presetChanges(); each key counts at most once.
 *
 * @param {Object} before Settings document before PUT /settings {profile}.
 * @param {Object} after  Settings document returned by the PUT.
 * @return {string[]} Changed field keys.
 */
export function presetDiff( before, after ) {
	const items = itemUnion( [ before, after ] );
	const from = presetFields( before, items );
	const to = presetFields( after, items );
	return Object.keys( to ).filter( ( key ) => from[ key ] !== to[ key ] );
}

/**
 * WZ-04 summary card: how many fields the chosen scene preset changed.
 *
 * @param {Object} before Settings document before the scene was applied.
 * @param {Object} after  Settings document returned by PUT /settings.
 * @return {{count:number,keys:string[],same:boolean,admin:boolean}} Count,
 *         changed keys, whether the scene was already current, and whether
 *         public assets are admin-only.
 */
export function resourceSummary( before, after ) {
	const base = before || after;
	const scope = after?.connectivity?.public_assets?.scope || 'both';
	const keys = presetDiff( base, after );
	const from = base?.profile || 'domestic';
	const to = after?.profile || 'domestic';
	return {
		count: keys.length,
		keys,
		same: from === to && keys.length === 0,
		admin: scope === 'admin',
	};
}

/**
 * 站点标识 short form: first 4 … last 4.
 *
 * @param {string|null} hash Site hash.
 * @return {string} Masked hash.
 */
export function maskHash( hash ) {
	if ( typeof hash !== 'string' || hash.length < 10 ) {
		return hash || '';
	}
	return hash.slice( 0, 4 ) + '…' + hash.slice( -4 );
}
