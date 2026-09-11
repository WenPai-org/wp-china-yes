/**
 * OV-18 connectivity stack status from /diagnostics groups + settings + binding.
 *
 * Same diagnostics payload as OV-13. Never contradict the route list.
 *
 */

import { __, sprintf } from '@wordpress/i18n';
import { relTime } from './relTime';
import { profileLabel as sceneLabel } from './profiles';

const RANK = { down: 3, fallback: 2, ok: 1 };

/**
 * Default route groups (rest-api §/diagnostics). Used when bootstrap.providers
 * is present; group membership is still this table.
 */
export const ROUTE_GROUPS = [
	{
		id: 'wordpress_org',
		name: __( 'WordPress.org 镜像', 'wp-china-yes' ),
		desc: __( '更新检查与安装包', 'wp-china-yes' ),
		hosts: [ 'api.wenpai.net', 'downloads.wenpai.net' ],
		providerKey: 'wordpress_org',
		fallbackProvider: 'WenPai.org',
	},
	{
		id: 'public_assets',
		name: __( '公共库源', 'wp-china-yes' ),
		desc: __( 'Google Fonts、Ajax、jsDelivr、Emoji', 'wp-china-yes' ),
		hosts: [
			'googlefonts.admincdn.com',
			'googleajax.admincdn.com',
			'jsd.admincdn.com',
		],
		providerKey: 'public_assets',
		fallbackProvider: 'adminCDN',
	},
	{
		id: 'cdnjs',
		name: __( 'CDNJS 源', 'wp-china-yes' ),
		desc: __( '备用公共库', 'wp-china-yes' ),
		hosts: [ 'cdnjs.admincdn.com' ],
		providerKey: 'cdnjs',
		fallbackProvider: 'adminCDN',
	},
	{
		id: 'cravatar',
		name: __( '头像', 'wp-china-yes' ),
		desc: __( '评论头像', 'wp-china-yes' ),
		hosts: [ 'cn.cravatar.com', 'en.cravatar.com' ],
		providerKey: 'cravatar',
		fallbackProvider: 'Cravatar',
	},
];

/**
 * Provider brand from bootstrap.providers, else the fallback original.
 *
 * @param {Object} providers
 * @param {string} key
 * @param {string} fallback
 * @return {string} Value.
 */
export function providerName( providers, key, fallback ) {
	if ( providers && providers[ key ] ) {
		return providers[ key ];
	}
	return fallback;
}

/**
 * Worst result among matching targets.
 *
 * @param {Array}    targets
 * @param {string[]} hosts
 * @return {{result: string|null, latency_ms: number|null, checked_at: string|null, target: string|null}} Aggregate.
 */
export function groupAggregate( targets, hosts ) {
	const rows = ( targets || [] ).filter( ( row ) =>
		hosts.includes( row.target )
	);
	if ( ! rows.length ) {
		return {
			result: null,
			latency_ms: null,
			checked_at: null,
			target: null,
		};
	}
	let worst = rows[ 0 ];
	rows.forEach( ( row ) => {
		if ( ( RANK[ row.result ] || 0 ) > ( RANK[ worst.result ] || 0 ) ) {
			worst = row;
		}
	} );
	let latency = null;
	rows.forEach( ( row ) => {
		if ( row.latency_ms === null || row.latency_ms === undefined ) {
			return;
		}
		if ( latency === null || row.latency_ms > latency ) {
			latency = row.latency_ms;
		}
	} );
	let checked = rows[ 0 ].checked_at;
	rows.forEach( ( row ) => {
		if ( ! checked ) {
			checked = row.checked_at;
			return;
		}
		if (
			row.checked_at &&
			Date.parse( row.checked_at ) < Date.parse( checked )
		) {
			checked = row.checked_at;
		}
	} );
	return {
		result: worst.result,
		latency_ms: latency,
		checked_at: checked,
		target: worst.target,
	};
}

/**
 * @param {Object} settings
 * @return {boolean} Value.
 */
export function wordpressOrgEnabled( settings ) {
	return ( settings?.connectivity?.wordpress_org || 'auto' ) !== 'off';
}

/**
 * @param {Object} settings
 * @return {boolean} Value.
 */
export function publicAssetsEnabled( settings ) {
	const scope = settings?.connectivity?.public_assets?.scope;
	if ( scope === 'off' ) {
		return false;
	}
	const items = settings?.connectivity?.public_assets?.items;
	if ( Array.isArray( items ) && items.length === 0 ) {
		return false;
	}
	return true;
}

/**
 * @param {Object} settings
 * @return {boolean} Value.
 */
export function avatarEnabled( settings ) {
	const avatar = settings?.connectivity?.avatar;
	if ( ! avatar ) {
		return true;
	}
	if ( typeof avatar === 'string' ) {
		return avatar !== 'off';
	}
	return avatar.admin !== 'off' || avatar.frontend !== 'off';
}

/**
 * Admin-only public assets / avatar (crossborder / mixed).
 *
 * @param {Object} settings
 * @return {boolean} Value.
 */
export function isAdminOnly( settings ) {
	const profile = settings?.profile;
	if ( profile === 'crossborder' || profile === 'mixed' ) {
		return true;
	}
	return settings?.connectivity?.public_assets?.scope === 'admin';
}

/**
 * Frontend-only public assets / avatar (inbound).
 *
 * @param {Object} settings
 * @return {boolean} Value.
 */
export function isFrontendOnly( settings ) {
	if ( settings?.profile === 'inbound' ) {
		return true;
	}
	return settings?.connectivity?.public_assets?.scope === 'frontend';
}

/**
 * Profile display name.
 *
 * @param {string} profile
 * @return {string} Value.
 */
export function profileLabel( profile ) {
	return sceneLabel( profile );
}

/**
 * Build five core-service rows.
 *
 * @param {Object}  args
 * @param {Array}   args.targets
 * @param {Object}  args.settings
 * @param {Object}  [args.binding]
 * @param {Object}  [args.providers]
 * @param {boolean} args.recovery
 * @return {Array} Value.
 */
export function buildSvcRows( {
	targets,
	settings,
	binding,
	providers,
	recovery,
} ) {
	const profile = settings?.profile || 'domestic';
	const domestic = profile === 'domestic';
	const bound = binding?.status === 'bound';
	const orgOn = wordpressOrgEnabled( settings );
	const assetsOn = publicAssetsEnabled( settings );
	const avatarOn = avatarEnabled( settings );
	const org = groupAggregate( targets, ROUTE_GROUPS[ 0 ].hosts );
	const pub = groupAggregate( targets, ROUTE_GROUPS[ 1 ].hosts );
	const ava = groupAggregate( targets, ROUTE_GROUPS[ 3 ].hosts );
	const wenpai = providerName( providers, 'wordpress_org', 'WenPai.org' );
	const admincdn = providerName( providers, 'public_assets', 'adminCDN' );
	const cravatar = providerName( providers, 'cravatar', 'Cravatar' );

	const rows = [];

	// WordPress.org
	rows.push(
		orgRow( {
			recovery,
			enabled: orgOn,
			domestic,
			agg: org,
			provider: wenpai,
		} )
	);
	rows.push(
		assetsRow( {
			recovery,
			enabled: assetsOn,
			domestic,
			adminOnly: isAdminOnly( settings ),
			frontendOnly: isFrontendOnly( settings ),
			agg: pub,
			provider: admincdn,
		} )
	);
	rows.push(
		avatarRow( {
			recovery,
			enabled: avatarOn,
			domestic,
			agg: ava,
			provider: cravatar,
		} )
	);
	rows.push( motuRow( { recovery } ) );
	rows.push( fontRow( { recovery, bound, settings } ) );

	return rows;
}

function orgRow( { recovery, enabled, domestic, agg, provider } ) {
	const name = __( '更新与安装包', 'wp-china-yes' );
	if ( recovery ) {
		return matrixRow( {
			key: 'paused',
			dot: 'paused',
			icon: 'download',
			name,
			provider,
			word: __( '未接管', 'wp-china-yes' ),
			status: 'paused',
			agg,
			tooltip: name,
		} );
	}
	if ( ! enabled ) {
		if ( ! domestic ) {
			return matrixRow( {
				key: 'direct',
				dot: 'direct',
				icon: 'download',
				name,
				provider: '',
				word: __( '直连', 'wp-china-yes' ),
				status: 'direct',
				agg,
				tooltip:
					__( '更新', 'wp-china-yes' ) +
					' · ' +
					__( '直连 WordPress.org', 'wp-china-yes' ),
			} );
		}
		return matrixRow( {
			key: 'off',
			dot: 'off',
			icon: 'download',
			name,
			provider,
			word: __( '未启用', 'wp-china-yes' ),
			status: 'off',
			action: 'enable-connect',
			tooltip: name + ' · ' + __( '未启用', 'wp-china-yes' ),
		} );
	}
	return fromAgg( {
		icon: 'download',
		name,
		agg,
		provider,
		okLine: domestic
			? __(
					'经 WenPai.org 镜像接通 · 原始源在国内常超时',
					'wp-china-yes'
			  )
			: __( '经 WenPai.org 镜像接通', 'wp-china-yes' ),
	} );
}

function assetsRow( {
	recovery,
	enabled,
	domestic,
	adminOnly,
	frontendOnly,
	agg,
	provider,
} ) {
	const name = __( '公共库加速', 'wp-china-yes' );
	let extra = '';
	if ( adminOnly ) {
		extra = __( '只在后台', 'wp-china-yes' );
	} else if ( frontendOnly ) {
		extra = __( '只在前台', 'wp-china-yes' );
	}
	if ( recovery ) {
		return matrixRow( {
			key: 'paused',
			dot: 'paused',
			icon: 'bolt',
			name,
			extra,
			provider,
			word: __( '未接管', 'wp-china-yes' ),
			status: 'paused',
			agg,
			tooltip: name,
		} );
	}
	if ( ! enabled ) {
		return matrixRow( {
			key: 'off',
			dot: 'off',
			icon: 'bolt',
			name,
			extra,
			provider,
			word: __( '未启用', 'wp-china-yes' ),
			status: 'off',
			action: 'enable-connect',
			tooltip: name + ' · ' + __( '未启用', 'wp-china-yes' ),
		} );
	}
	const okLine = domestic
		? __( '经 adminCDN 接通 · 原始源在国内不可达', 'wp-china-yes' )
		: __( '后台里经 adminCDN 接通 · 原始源在国内打不开', 'wp-china-yes' );
	return fromAgg( {
		icon: 'bolt',
		name,
		extra,
		agg,
		provider,
		okLine,
		tooltip: extra ? name + ' · ' + extra : name,
	} );
}

function avatarRow( { recovery, enabled, domestic, agg, provider } ) {
	const name = __( '头像', 'wp-china-yes' );
	if ( recovery ) {
		return matrixRow( {
			key: 'paused',
			dot: 'paused',
			icon: 'user',
			name,
			provider,
			word: __( '未接管', 'wp-china-yes' ),
			status: 'paused',
			agg,
			tooltip: name,
		} );
	}
	if ( ! enabled ) {
		return matrixRow( {
			key: 'off',
			dot: 'off',
			icon: 'user',
			name,
			provider,
			word: __( '未启用', 'wp-china-yes' ),
			status: 'off',
			action: 'enable-connect',
			tooltip: name + ' · ' + __( '未启用', 'wp-china-yes' ),
		} );
	}
	const okLine = domestic
		? __( '经 Cravatar 接通 · Gravatar 在国内空白', 'wp-china-yes' )
		: __( '经 Cravatar 接通 · Gravatar 在国内空白', 'wp-china-yes' );
	return fromAgg( {
		icon: 'user',
		name,
		agg,
		provider,
		okLine,
		tooltip: name,
	} );
}

function motuRow( { recovery } ) {
	const name = __( '图标与图片', 'wp-china-yes' );
	const provider = 'MotuCloud';
	if ( recovery ) {
		return matrixRow( {
			key: 'paused',
			dot: 'paused',
			icon: 'image',
			name,
			provider,
			word: __( '未接管', 'wp-china-yes' ),
			status: 'paused',
			tooltip: name,
		} );
	}
	return matrixRow( {
		key: 'on',
		dot: 'ok',
		icon: 'image',
		name,
		provider,
		word: __( '已接通', 'wp-china-yes' ),
		status: 'on',
		tone: 'ok',
		line: __( '经 MotuCloud 接通', 'wp-china-yes' ),
		tooltip: name,
	} );
}

function fontRow( { recovery, bound, settings } ) {
	const name = __( '中文字体', 'wp-china-yes' );
	const provider = 'Windfonts';
	if ( recovery ) {
		return matrixRow( {
			key: 'paused',
			dot: 'paused',
			icon: 'font',
			name,
			provider,
			word: __( '未启用', 'wp-china-yes' ),
			status: 'paused',
			tooltip: name,
		} );
	}
	if ( ! bound ) {
		return matrixRow( {
			key: 'off',
			dot: 'off',
			icon: 'font',
			name,
			provider,
			word: __( '未启用', 'wp-china-yes' ),
			status: 'off',
			action: 'enable-services',
			line: __( 'Windfonts 提供 · 绑定本站后可用', 'wp-china-yes' ),
			tooltip: name + ' · ' + __( '未启用', 'wp-china-yes' ),
		} );
	}
	if ( settings?.modules?.windfonts ) {
		return matrixRow( {
			key: 'on',
			dot: 'ok',
			icon: 'font',
			name,
			provider,
			word: __( '已接通', 'wp-china-yes' ),
			status: 'on',
			tone: 'ok',
			line: __( '经 Windfonts 接通', 'wp-china-yes' ),
			tooltip: name,
		} );
	}
	return matrixRow( {
		key: 'off',
		dot: 'off',
		icon: 'font',
		name,
		provider,
		word: __( '未启用', 'wp-china-yes' ),
		status: 'off',
		action: 'enable-connect',
		line: __( '未启用', 'wp-china-yes' ),
		tooltip: name + ' · ' + __( '未启用', 'wp-china-yes' ),
	} );
}

/**
 * Minutes since a UTC timestamp, at least 1.
 *
 * @param {string} iso   UTC ISO 8601.
 * @param {number} [now] Epoch ms.
 * @return {string} Value.
 */
export function unreachableMinutes( iso, now = Date.now() ) {
	if ( ! iso ) {
		return '';
	}
	const then = Date.parse( iso );
	if ( Number.isNaN( then ) ) {
		return '';
	}
	const min = Math.max( 1, Math.floor( ( now - then ) / 60000 ) );
	return sprintf(
		/* translators: %d: minutes */
		__( '%d 分钟', 'wp-china-yes' ),
		min
	);
}

/**
 * Shared matrix row shape for the core-services card and hero dots.
 *
 * @param {Object} args
 * @return {Object} Row.
 */
function matrixRow( args ) {
	const agg = args.agg || {};
	const meta = metaFromAgg( agg );
	return {
		key: args.key,
		icon: args.icon,
		name: args.name,
		extra: args.extra || '',
		provider: args.provider || '',
		dot: args.dot || '',
		word: args.word,
		status: args.status,
		tone: args.tone || '',
		line: args.line || '',
		desc: args.desc || '',
		action: args.action || '',
		meta: args.action ? '' : args.meta || meta,
		tooltip: args.tooltip || args.name,
		groupResult: args.groupResult,
		latency_ms: agg.latency_ms,
		checked_at: agg.checked_at,
	};
}

function metaFromAgg( agg ) {
	if ( ! agg || ( agg.latency_ms === null && ! agg.checked_at ) ) {
		return '';
	}
	const parts = [];
	if ( agg.latency_ms !== null && agg.latency_ms !== undefined ) {
		parts.push( formatMs( agg.latency_ms ) );
	}
	if ( agg.checked_at ) {
		parts.push( relTime( agg.checked_at ) );
	}
	return parts.join( ' · ' );
}

function uncheckedRow( { icon, name, extra, provider, tooltip } ) {
	return matrixRow( {
		key: 'unchecked',
		dot: 'off',
		icon,
		name,
		extra,
		provider,
		word: __( '未检查', 'wp-china-yes' ),
		status: 'unchecked',
		line: __( '未检查', 'wp-china-yes' ),
		tooltip: tooltip || name,
		groupResult: null,
	} );
}

export function fromAgg( {
	icon,
	name,
	extra,
	agg,
	provider,
	okLine,
	tooltip,
} ) {
	if ( ! agg || ! agg.result ) {
		return uncheckedRow( { icon, name, extra, provider, tooltip } );
	}
	if ( agg.result === 'fallback' ) {
		const when = unreachableMinutes( agg.checked_at );
		const line =
			provider +
			' ' +
			__( '不可达', 'wp-china-yes' ) +
			( when ? ' ' + when : '' ) +
			' · ' +
			__( '已回原始上游', 'wp-china-yes' );
		return matrixRow( {
			key: 'fallback',
			dot: 'warn',
			icon,
			name,
			extra,
			provider,
			word: __( '已回退', 'wp-china-yes' ),
			status: 'fallback',
			tone: 'warn',
			line,
			desc: line,
			agg,
			tooltip: name + ' · ' + __( '已回退', 'wp-china-yes' ),
			groupResult: 'fallback',
		} );
	}
	if ( agg.result === 'down' ) {
		const line = provider + ' ' + __( '不可达', 'wp-china-yes' );
		return matrixRow( {
			key: 'down',
			dot: 'bad',
			icon,
			name,
			extra,
			provider,
			word: __( '不可达', 'wp-china-yes' ),
			status: 'down',
			tone: 'bad',
			line,
			desc: line,
			agg,
			tooltip: name,
			groupResult: 'down',
		} );
	}
	return matrixRow( {
		key: 'on',
		dot: 'ok',
		icon,
		name,
		extra,
		provider,
		word: __( '已接通', 'wp-china-yes' ),
		status: 'on',
		tone: 'ok',
		line: okLine,
		agg,
		tooltip: tooltip || name,
		groupResult: agg.result,
	} );
}

/**
 * Visible route groups for OV-13, filtered by settings.
 *
 * @param {Object} args
 * @param {Array}  args.targets
 * @param {Object} args.settings
 * @param {Object} [args.providers]
 * @return {Array} Value.
 */
export function buildRouteRows( { targets, settings, providers } ) {
	const orgOn = wordpressOrgEnabled( settings );
	const assetsOn = publicAssetsEnabled( settings );
	const avatarOn = avatarEnabled( settings );
	const out = [];
	ROUTE_GROUPS.forEach( ( group ) => {
		if ( group.id === 'wordpress_org' && ! orgOn ) {
			return;
		}
		if (
			( group.id === 'public_assets' || group.id === 'cdnjs' ) &&
			! assetsOn
		) {
			return;
		}
		if ( group.id === 'cravatar' && ! avatarOn ) {
			return;
		}
		const agg = groupAggregate( targets, group.hosts );
		if ( ! agg.result ) {
			return;
		}
		let tone = 'bad';
		if ( agg.result === 'ok' ) {
			tone = 'ok';
		} else if ( agg.result === 'fallback' ) {
			tone = 'warn';
		}
		let desc = group.desc;
		if ( group.id === 'cravatar' && agg.target ) {
			desc = __( '评论头像', 'wp-china-yes' ) + ' · ' + agg.target;
		}
		out.push( {
			id: group.id,
			tone,
			name: group.name,
			provider: providerName(
				providers,
				group.providerKey,
				group.fallbackProvider
			),
			desc,
			ms:
				agg.latency_ms === null || agg.latency_ms === undefined
					? '—'
					: formatMs( agg.latency_ms ),
			ago: relTime( agg.checked_at ),
			result: agg.result,
			checked_at: agg.checked_at,
			host: agg.target,
			latency_ms: agg.latency_ms,
		} );
	} );
	return out;
}

/**
 * @param {number} ms
 * @return {string} Value.
 */
export function formatMs( ms ) {
	if ( ms === null || ms === undefined || Number.isNaN( Number( ms ) ) ) {
		return '—';
	}
	return Number( ms ).toLocaleString( 'en-US' ) + ' ms';
}
