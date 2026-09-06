/**
 * OV-18 connectivity stack status from /diagnostics groups + settings + binding.
 *
 * Same diagnostics payload as OV-13. Never contradict the route list.
 *
 */

import { __, sprintf } from '@wordpress/i18n';
import { relTime } from './relTime';

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
	const admin = avatar.admin;
	const frontend = avatar.frontend;
	return admin !== 'off' || frontend !== 'off';
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
 * Profile display name.
 *
 * @param {string} profile
 * @return {string} Value.
 */
export function profileLabel( profile ) {
	if ( profile === 'crossborder' ) {
		return __( '跨境 · 外贸站', 'wp-china-yes' );
	}
	if ( profile === 'mixed' ) {
		return __( '混合站', 'wp-china-yes' );
	}
	return __( '国内站', 'wp-china-yes' );
}

/**
 * Build four OV-18 rows.
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
			agg: pub,
			provider: admincdn,
		} )
	);
	rows.push(
		avatarRow( {
			recovery,
			enabled: avatarOn,
			domestic,
			adminOnly: isAdminOnly( settings ),
			agg: ava,
			provider: cravatar,
		} )
	);
	rows.push( fontRow( { recovery, bound, settings } ) );

	return rows;
}

function orgRow( { recovery, enabled, domestic, agg, provider } ) {
	const name = __( 'WordPress 更新与安装包', 'wp-china-yes' );
	if ( recovery ) {
		return {
			key: 'paused',
			icon: 'download',
			name,
			line: __( '未接管 · 直连 WordPress.org', 'wp-china-yes' ),
			status: 'paused',
			word: __( '已停用', 'wp-china-yes' ),
			tone: '',
		};
	}
	if ( ! enabled ) {
		if ( ! domestic ) {
			return {
				key: 'direct',
				icon: 'download',
				name,
				line: __(
					'直连 WordPress.org · 服务器在海外不需镜像',
					'wp-china-yes'
				),
				status: 'direct',
				word: __( '直连', 'wp-china-yes' ),
				tone: '',
			};
		}
		return {
			key: 'off',
			icon: 'download',
			name,
			line: __( '未启用', 'wp-china-yes' ),
			status: 'off',
			word: __( '未启用', 'wp-china-yes' ),
			tone: '',
			action: 'enable-connect',
		};
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
	agg,
	provider,
} ) {
	const name = adminOnly
		? __( '后台公共库', 'wp-china-yes' )
		: __( '公共库', 'wp-china-yes' );
	const extra = adminOnly ? __( '只在后台', 'wp-china-yes' ) : '';
	if ( recovery ) {
		return {
			key: 'paused',
			icon: 'bolt',
			name,
			extra,
			line: __( '未接管 · 直连原始源', 'wp-china-yes' ),
			status: 'paused',
			word: __( '已停用', 'wp-china-yes' ),
			tone: '',
		};
	}
	if ( ! enabled ) {
		return {
			key: 'off',
			icon: 'bolt',
			name,
			extra,
			line: __( '未启用', 'wp-china-yes' ),
			status: 'off',
			word: __( '未启用', 'wp-china-yes' ),
			tone: '',
			action: 'enable-connect',
		};
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
	} );
}

function avatarRow( {
	recovery,
	enabled,
	domestic,
	adminOnly,
	agg,
	provider,
} ) {
	const name = adminOnly
		? __( '后台头像', 'wp-china-yes' )
		: __( '头像', 'wp-china-yes' );
	const extra = adminOnly ? __( '只在后台', 'wp-china-yes' ) : '';
	if ( recovery ) {
		return {
			key: 'paused',
			icon: 'user',
			name,
			extra,
			line: __( '未接管 · 直连 Gravatar', 'wp-china-yes' ),
			status: 'paused',
			word: __( '已停用', 'wp-china-yes' ),
			tone: '',
		};
	}
	if ( ! enabled ) {
		return {
			key: 'off',
			icon: 'user',
			name,
			extra,
			line: __( '未启用', 'wp-china-yes' ),
			status: 'off',
			word: __( '未启用', 'wp-china-yes' ),
			tone: '',
			action: 'enable-connect',
		};
	}
	const okLine = domestic
		? __( '经 Cravatar 接通 · Gravatar 在国内空白', 'wp-china-yes' )
		: __( '后台里经 Cravatar 接通 · Gravatar 在国内空白', 'wp-china-yes' );
	return fromAgg( {
		icon: 'user',
		name,
		extra,
		agg,
		provider,
		okLine,
	} );
}

function fontRow( { recovery, bound, settings } ) {
	const name = __( '中文字体', 'wp-china-yes' );
	if ( recovery ) {
		return {
			key: 'paused',
			icon: 'font',
			name,
			line: __( '未启用', 'wp-china-yes' ),
			status: 'paused',
			word: __( '已停用', 'wp-china-yes' ),
			tone: '',
		};
	}
	if ( ! bound ) {
		return {
			key: 'off',
			icon: 'font',
			name,
			line: __( 'Windfonts 提供 · 绑定本站后可用', 'wp-china-yes' ),
			status: 'off',
			word: __( '未启用', 'wp-china-yes' ),
			tone: '',
			action: 'enable-services',
		};
	}
	if ( settings?.modules?.windfonts ) {
		return {
			key: 'on',
			icon: 'font',
			name,
			line: __( '经 Windfonts 接通', 'wp-china-yes' ),
			status: 'on',
			word: __( '已接通', 'wp-china-yes' ),
			tone: 'ok',
		};
	}
	return {
		key: 'off',
		icon: 'font',
		name,
		line: __( '未启用', 'wp-china-yes' ),
		status: 'off',
		word: __( '未启用', 'wp-china-yes' ),
		tone: '',
		action: 'enable-connect',
	};
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

function uncheckedRow( { icon, name, extra } ) {
	return {
		key: 'unchecked',
		icon,
		name,
		extra,
		line: __( '未检查', 'wp-china-yes' ),
		status: 'unchecked',
		word: __( '未检查', 'wp-china-yes' ),
		tone: '',
		groupResult: null,
	};
}

export function fromAgg( { icon, name, extra, agg, provider, okLine } ) {
	if ( ! agg || ! agg.result ) {
		return uncheckedRow( { icon, name, extra } );
	}
	if ( agg.result === 'fallback' ) {
		const when = unreachableMinutes( agg.checked_at );
		return {
			key: 'fallback',
			icon,
			name,
			extra,
			line:
				provider +
				' ' +
				__( '不可达', 'wp-china-yes' ) +
				( when ? ' ' + when : '' ) +
				' · ' +
				__( '已回原始上游', 'wp-china-yes' ),
			status: 'fallback',
			word: __( '已回退', 'wp-china-yes' ),
			tone: 'warn',
			groupResult: 'fallback',
		};
	}
	if ( agg.result === 'down' ) {
		return {
			key: 'down',
			icon,
			name,
			extra,
			line: provider + ' ' + __( '不可达', 'wp-china-yes' ),
			status: 'down',
			word: __( '不可达', 'wp-china-yes' ),
			tone: 'bad',
			groupResult: 'down',
		};
	}
	return {
		key: 'on',
		icon,
		name,
		extra,
		line: okLine,
		status: 'on',
		word: __( '已接通', 'wp-china-yes' ),
		tone: 'ok',
		groupResult: agg.result,
	};
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
	return Number( ms ).toLocaleString( 'en-US' ) + ' ms';
}
