/**
 * Overview: six states per admin-ui-spec §4.1.
 *
 */

import { __, sprintf } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import PageShell from '../components/PageShell';
import { STORE_NAME } from '../store';
import { adminPageUrl, PAGES } from '../routing';
import Icon from '../ui/icons';
import Btn from '../ui/Btn';
import Hero, { HeroAnchor } from '../ui/Hero';
import Notice from '../ui/Notice';
import Next from '../ui/Next';
import Sec from '../ui/Sec';
import Card, { CardHead } from '../ui/Card';
import StatArea from '../ui/StatArea';
import Eco from '../ui/Eco';
import Empty from '../ui/Empty';
import Skeleton from '../ui/Skeleton';
import LoadError from '../ui/LoadError';
import SvcMatrix from '../ui/SvcMatrix';
import EventsSimple from '../ui/EventsSimple';
import {
	daysSince,
	eventTime,
	formatBytes,
	formatInt,
	relTime,
	sumSeries,
} from '../ui/relTime';
import { buildRouteRows, buildSvcRows, profileLabel } from '../ui/svcStatus';

const SKELETON_MS = 8000;

/**
 * Split 14-day series into previous 7 / last 7.
 *
 * @param {Array} series
 * @return {{prev: Array, last: Array}} Split series.
 */
function split14( series ) {
	const list = Array.isArray( series ) ? series : [];
	if ( list.length <= 7 ) {
		return { prev: [], last: list };
	}
	return {
		prev: list.slice( 0, list.length - 7 ),
		last: list.slice( list.length - 7 ),
	};
}

/**
 * Growth percent, or null when not shown.
 *
 * @param {number} last
 * @param {number} prev
 * @return {string|null} Value.
 */
function deltaPct( last, prev ) {
	if ( ! prev ) {
		return null;
	}
	if ( last <= prev ) {
		return null;
	}
	return Math.round( ( ( last - prev ) / prev ) * 100 ) + '%';
}

/**
 * @param {Object} settings
 * @return {boolean} Value.
 */
function isCross( settings ) {
	const p = settings?.profile;
	return p === 'crossborder' || p === 'mixed' || p === 'inbound';
}

/**
 * True when /stats 200 and every total is 0.
 *
 * @param {Object|null} stats
 * @return {boolean} Value.
 */
function statsAllZero( stats ) {
	if ( ! stats ) {
		return false;
	}
	const totals = stats.totals;
	if ( totals && typeof totals === 'object' ) {
		const values = Object.values( totals );
		if ( ! values.length ) {
			return true;
		}
		return values.every( ( value ) => ! Number( value ) );
	}
	const series = stats.series || {};
	const parts = Object.values( series );
	if ( ! parts.length ) {
		return true;
	}
	return parts.every( ( rows ) => sumSeries( rows ) === 0 );
}

export default function Overview() {
	const slice = useSelect( ( select ) => {
		const store = select( STORE_NAME );
		return {
			settings: store.getSettings(),
			recovery: store.isRecoveryMode(),
			targets: store.getDiagnostics()?.targets || [],
			diagnosticsError: store.getDiagnosticsError(),
			diagnosticsLoaded: store.isDiagnosticsLoaded(),
			diagnosticsMs: store.getDiagnosticsMs(),
			stats: store.getStats(),
			statsError: store.getStatsError(),
			statsLoaded: store.isStatsLoaded(),
			events: store.getEvents(),
			eventsError: store.getEventsError(),
			eventsLoaded: store.isEventsLoaded(),
			binding: store.getBinding(),
			bindingError: store.getBindingError(),
			bindingLoaded: store.isBindingLoaded(),
			migration: store.getMigration(),
			migrationError: store.getMigrationError(),
			migrationLoaded: store.isMigrationLoaded(),
			providers: store.getProviders(),
			links: store.getLinks(),
			running: store.isRunning(),
		};
	}, [] );
	const {
		fetchDiagnostics,
		fetchStats,
		fetchEvents,
		fetchBinding,
		fetchMigration,
		runDiagnostics,
		exitRecovery,
	} = useDispatch( STORE_NAME );

	const [ timedOut, setTimedOut ] = useState( false );

	useEffect( () => {
		fetchDiagnostics();
		fetchStats();
		fetchEvents();
		fetchBinding();
		fetchMigration();
		const t = window.setTimeout( () => setTimedOut( true ), SKELETON_MS );
		return () => window.clearTimeout( t );
		// eslint-disable-next-line react-hooks/exhaustive-deps -- one-shot on mount
	}, [] );

	const loadedCore =
		slice.diagnosticsLoaded &&
		slice.statsLoaded &&
		slice.eventsLoaded &&
		slice.bindingLoaded &&
		slice.migrationLoaded;
	const showSkeleton = ! loadedCore && ! timedOut;

	return (
		<PageShell>
			{ showSkeleton ? (
				<Skeleton />
			) : (
				<OverviewBody
					slice={ slice }
					timedOut={ timedOut }
					runDiagnostics={ runDiagnostics }
					exitRecovery={ exitRecovery }
					fetchDiagnostics={ fetchDiagnostics }
					fetchStats={ fetchStats }
					fetchEvents={ fetchEvents }
					fetchBinding={ fetchBinding }
					fetchMigration={ fetchMigration }
				/>
			) }
		</PageShell>
	);
}

function OverviewBody( {
	slice,
	timedOut,
	runDiagnostics,
	exitRecovery,
	fetchDiagnostics,
	fetchStats,
	fetchEvents,
	fetchBinding,
	fetchMigration,
} ) {
	const settings = slice.settings || {};
	const profile = settings.profile || 'domestic';
	const domestic = profile === 'domestic';
	const mixed = profile === 'mixed';
	const inbound = profile === 'inbound';
	const cross = isCross( settings );
	const recovery = slice.recovery;
	const diagnosticsError =
		slice.diagnosticsError || ( timedOut && ! slice.diagnosticsLoaded );
	const statsError = slice.statsError || ( timedOut && ! slice.statsLoaded );
	const eventsError =
		slice.eventsError || ( timedOut && ! slice.eventsLoaded );
	const bindingError =
		slice.bindingError || ( timedOut && ! slice.bindingLoaded );
	const migrationError =
		slice.migrationError || ( timedOut && ! slice.migrationLoaded );
	const days = daysSince( slice.stats?.installed_at );
	const freshInstall = days < 7;
	const emptyStats =
		slice.statsLoaded &&
		! statsError &&
		Boolean( slice.stats?.installed_at ) &&
		days < 7 &&
		statsAllZero( slice.stats );
	const bound = slice.binding?.status === 'bound';

	const svcRows = buildSvcRows( {
		targets: slice.targets,
		settings,
		binding: slice.binding,
		providers: slice.providers,
		recovery,
	} );
	const routeRows = buildRouteRows( {
		targets: slice.targets,
		settings,
		providers: slice.providers,
	} );

	const degradedGroup = routeRows.find(
		( row ) => row.result === 'fallback' || row.result === 'down'
	);
	const hasDown = routeRows.some( ( row ) => row.result === 'down' );

	const needConfirm =
		slice.migration &&
		slice.migration.status !== 'none' &&
		! settings.profile_confirmed_at;

	const showBindNext =
		! needConfirm && ! domestic && ! bound && ! bindingError;

	const hasNext = Boolean( needConfirm || showBindNext );
	const degraded = Boolean( degradedGroup ) && ! recovery;

	const eyebrow = [
		profileLabel( profile ),
		days === 0 || ! slice.stats?.installed_at
			? __( '刚安装', 'wp-china-yes' )
			: sprintf(
					/* translators: %d: days */
					__( '已运行 %d 天', 'wp-china-yes' ),
					days
			  ),
	].join( ' · ' );

	const copy = heroCopy( {
		recovery,
		degraded,
		degradedGroup,
		hasDown,
		freshInstall,
		domestic,
		mixed,
		inbound,
	} );

	const heroActions = heroButtons( {
		recovery,
		degraded,
		hasNext,
		cross,
		inbound,
	} );

	const anchor = heroAnchor( {
		recovery,
		svcRows,
		degraded,
		inbound,
		cross,
		checkedAt: routeRows[ 0 ]?.checked_at || svcRows[ 0 ]?.checked_at,
	} );

	return (
		<>
			{ recovery ? (
				<Notice
					tone="warn"
					act
					icon="shield"
					action={
						<Btn
							variant="secondary"
							onClick={ () => exitRecovery() }
						>
							{ __( '退出恢复模式', 'wp-china-yes' ) }
						</Btn>
					}
				>
					<b>{ __( '恢复模式已开启：', 'wp-china-yes' ) }</b>
					{ __(
						'全部 URL 改写与模块已停用。站点现在的行为和未安装本插件时一样。',
						'wp-china-yes'
					) }
				</Notice>
			) : null }

			<Hero
				eyebrow={ eyebrow }
				title={ copy.title }
				lede={ copy.lede }
				actions={ heroActions }
				anchor={
					diagnosticsError ? (
						<LoadError
							what={ __( '线路状态', 'wp-china-yes' ) }
							onRetry={ () => fetchDiagnostics() }
						/>
					) : (
						anchor
					)
				}
			/>

			{ needConfirm ? (
				<Next
					icon="map"
					action={
						<Btn
							variant="primary"
							href={ adminPageUrl( PAGES.connect ) }
						>
							{ __( '确认你的站点场景', 'wp-china-yes' ) }
						</Btn>
					}
				>
					<strong>
						{ __( '确认你的站点场景。', 'wp-china-yes' ) }
					</strong>{ ' ' }
					{ __(
						'从 3.8 升级的站点先按「国内站」运行；如果服务器或访客在海外，选对场景后加速只作用于该作用的地方。',
						'wp-china-yes'
					) }
				</Next>
			) : null }

			{ showBindNext ? (
				<Next
					icon="link"
					action={
						<Btn
							variant="primary"
							href={ adminPageUrl( PAGES.services ) }
						>
							{ __( '绑定本站', 'wp-china-yes' ) }
						</Btn>
					}
				>
					<strong>
						{ __( '下一步：绑定本站。', 'wp-china-yes' ) }
					</strong>{ ' ' }
					{ __(
						'绑定后可使用文派服务与小工具，数据保存在本站，随时可解除。',
						'wp-china-yes'
					) }
				</Next>
			) : null }

			{ bindingError ? (
				<LoadError
					what={ __( '绑定状态', 'wp-china-yes' ) }
					onRetry={ () => fetchBinding() }
				/>
			) : null }

			{ migrationError ? (
				<LoadError
					what={ __( '迁移记录', 'wp-china-yes' ) }
					onRetry={ () => fetchMigration() }
				/>
			) : null }

			{ degraded && ! hasNext ? (
				<Notice
					tone="warn"
					act
					action={
						<Btn
							variant="secondary"
							onClick={ () => runDiagnostics() }
							disabled={ slice.running }
						>
							{ __( '立即重试', 'wp-china-yes' ) }
						</Btn>
					}
				>
					{ sprintf(
						/* translators: %s: group name */
						__(
							'%s连续不可达超过 1 小时会提醒你；现在不需要做任何事，也可先看服务商状态。',
							'wp-china-yes'
						),
						degradedGroup?.name || __( '镜像', 'wp-china-yes' )
					) }
				</Notice>
			) : null }

			<section className="sec">
				<Sec
					caps
					title={ __( '过去 7 天为你处理', 'wp-china-yes' ) }
					note={ __( '数字来自本站计数', 'wp-china-yes' ) }
				/>
				{ statsError ? (
					<LoadError
						what={ __( '统计', 'wp-china-yes' ) }
						onRetry={ () => fetchStats() }
					/>
				) : (
					<StatsGrid
						stats={ slice.stats }
						domestic={ domestic }
						inbound={ inbound }
						freshInstall={ emptyStats }
					/>
				) }
			</section>

			<section className="sec">
				<div className="grid2-w">
					<SvcCard
						rows={ diagnosticsError ? [] : svcRows }
						error={ diagnosticsError }
						onRetry={ () => fetchDiagnostics() }
						degraded={ degraded }
						recovery={ recovery }
					/>
					<EventsCard
						events={ slice.events }
						error={ eventsError }
						onRetry={ () => fetchEvents() }
					/>
				</div>
			</section>

			<Eco
				crossborder={ profile === 'crossborder' || mixed }
				inbound={ inbound }
				brands={ slice.links?.brands || {} }
				links={ slice.links || {} }
			/>
		</>
	);
}

function heroCopy( {
	recovery,
	degraded,
	degradedGroup,
	hasDown,
	freshInstall,
	domestic,
	mixed,
	inbound,
} ) {
	if ( recovery ) {
		return {
			title: (
				<>
					{ __( '恢复模式已开启，', 'wp-china-yes' ) }
					<br />
					{ __( '叶子现在什么都不做。', 'wp-china-yes' ) }
				</>
			),
			lede: __(
				'全部 URL 改写与模块已停用，站点回到未安装本插件时的行为。问题排除后退出恢复模式即可恢复之前的设置。',
				'wp-china-yes'
			),
		};
	}
	if ( degraded ) {
		const name =
			degradedGroup?.provider ||
			degradedGroup?.name ||
			__( '镜像', 'wp-china-yes' );
		const what = degradedGroup?.desc || __( '该项', 'wp-china-yes' );
		return {
			title: (
				<>
					{ name } { __( '镜像暂时不可达，', 'wp-china-yes' ) }
					<br />
					{ __( '已自动回原始上游。', 'wp-china-yes' ) }
				</>
			),
			lede: hasDown
				? sprintf(
						/* translators: %s: what the group does */
						__( '%s已暂停接通，恢复后自动继续。', 'wp-china-yes' ),
						what
				  )
				: sprintf(
						/* translators: %s: what the group does */
						__(
							'%s暂时直连原始来源，会慢一些但不会失败；其余线路正常。恢复后自动切回，不需要你操作。',
							'wp-china-yes'
						),
						what
				  ),
		};
	}
	if ( freshInstall ) {
		return {
			title: (
				<>
					{ __( '已经开始为你接通，', 'wp-china-yes' ) }
					<br />
					{ __( '数字稍后就有。', 'wp-china-yes' ) }
				</>
			),
			lede: __(
				'更新、公共库、头像已经接通各自的国内线路。第一次自动线路检查已完成，计数从现在开始。',
				'wp-china-yes'
			),
		};
	}
	if ( inbound ) {
		return {
			title: (
				<>
					{ __( '让中国买家打开你的店，', 'wp-china-yes' ) }
					<br />
					{ __( '快人一步。', 'wp-china-yes' ) }
				</>
			),
			lede: __(
				'只对访客看到的前台做接通——Google Fonts、头像这些大陆访客打不开或很慢的资源，改走国内可达的节点；你的后台和海外访客一个字节不动。',
				'wp-china-yes'
			),
		};
	}
	if ( mixed ) {
		return {
			title: (
				<>
					{ __( '访客在哪都不等，', 'wp-china-yes' ) }
					<br />
					{ __( '你在后台也不等。', 'wp-china-yes' ) }
				</>
			),
			lede: __(
				'前台资源与头像走国内可达源，后台资源走国内可达源；更新直连 WordPress.org。',
				'wp-china-yes'
			),
		};
	}
	if ( ! domestic ) {
		return {
			title: (
				<>
					{ __( '人在国内、站在海外，', 'wp-china-yes' ) }
					<br />
					{ __( '后台不该卡。', 'wp-china-yes' ) }
				</>
			),
			lede: __(
				'只对你在后台看到的资源做接通：打不开的字体和头像改走国内可达的节点；海外访客看到的前台一个字节不动；更新直连 WordPress.org。',
				'wp-china-yes'
			),
		};
	}
	return {
		title: (
			<>
				{ __( '国内访问 WordPress 的事，', 'wp-china-yes' ) }
				<br />
				{ __( '已经替你办好了。', 'wp-china-yes' ) }
			</>
		),
		lede: __(
			'更新与安装包、常用前端资源、头像这些原本要等海外线路的事，已经改走国内可达的线路。',
			'wp-china-yes'
		),
	};
}

function heroButtons( { recovery, degraded, hasNext, cross, inbound } ) {
	if ( recovery ) {
		return (
			<>
				<Btn
					variant="secondary"
					href={ adminPageUrl( PAGES.diagnose ) }
				>
					<Icon name="pulse" size={ 16 } />
					{ __( '查看诊断', 'wp-china-yes' ) }
				</Btn>
				<Btn variant="secondary" href={ adminPageUrl( PAGES.connect ) }>
					{ __( '查看设置', 'wp-china-yes' ) }
				</Btn>
			</>
		);
	}
	const secondary = degraded || hasNext;
	if ( cross && ! inbound ) {
		return (
			<>
				<Btn
					variant={ secondary ? 'secondary' : 'primary' }
					href={ adminPageUrl( PAGES.diagnose, '#probe' ) }
				>
					<Icon name="gauge" size={ 16 } />
					{ __( '从我的浏览器测速', 'wp-china-yes' ) }
				</Btn>
				<Btn variant="secondary" href={ adminPageUrl( PAGES.connect ) }>
					{ __( '调整设置', 'wp-china-yes' ) }
				</Btn>
			</>
		);
	}
	if ( secondary ) {
		return (
			<>
				<Btn
					variant="secondary"
					href={ adminPageUrl( PAGES.diagnose ) }
				>
					<Icon name="pulse" size={ 16 } />
					{ __( '查看线路详情', 'wp-china-yes' ) }
				</Btn>
				<Btn variant="secondary" href={ adminPageUrl( PAGES.connect ) }>
					{ __( '调整设置', 'wp-china-yes' ) }
				</Btn>
			</>
		);
	}
	return (
		<>
			<Btn variant="primary" href={ adminPageUrl( PAGES.diagnose ) }>
				<Icon name="pulse" size={ 16 } />
				{ __( '运行诊断', 'wp-china-yes' ) }
			</Btn>
			<Btn variant="secondary" href={ adminPageUrl( PAGES.connect ) }>
				{ __( '调整设置', 'wp-china-yes' ) }
			</Btn>
		</>
	);
}

function heroAnchor( {
	recovery,
	svcRows,
	degraded,
	inbound,
	cross,
	checkedAt,
} ) {
	const dots = ( svcRows || [] ).map( ( row ) => ( {
		dot: row.dot || '',
		tooltip: row.tooltip || row.name,
	} ) );
	if ( recovery ) {
		return (
			<HeroAnchor
				count={ __( '暂停', 'wp-china-yes' ) }
				label={ __( '全部服务未接管', 'wp-china-yes' ) }
				sub={ __( '站点行为与未安装本插件时相同', 'wp-china-yes' ) }
				check={ __( '设置已保留，随时可退出', 'wp-china-yes' ) }
				moreHref={ adminPageUrl( PAGES.diagnose ) }
				tone="mute"
				dots={ dots }
			/>
		);
	}
	const on = svcRows.filter( ( r ) => r.status === 'on' ).length;
	const total = 5;
	let sub = '';
	const off = svcRows.find( ( r ) => r.status === 'off' );
	const fallback = svcRows.find(
		( r ) => r.status === 'fallback' || r.status === 'down'
	);
	if ( fallback ) {
		sub =
			fallback.name +
			' ' +
			__( '已回退', 'wp-china-yes' ) +
			' · ' +
			__( '功能不中断', 'wp-china-yes' );
	} else if ( inbound ) {
		sub = sprintf(
			/* translators: %d: count */
			__( '已接通的 %d 项只在前台生效', 'wp-china-yes' ),
			on
		);
	} else if ( cross ) {
		sub =
			__( '更新直连', 'wp-china-yes' ) +
			' · ' +
			sprintf(
				/* translators: %d: count */
				__( '已接通的 %d 项只在后台生效', 'wp-china-yes' ),
				on
			);
	} else if ( off ) {
		sub = off.name + __( '未启用', 'wp-china-yes' );
	}
	const check = checkedAt
		? __( '最近检查', 'wp-china-yes' ) + ' ' + relTime( checkedAt )
		: __( '最近检查', 'wp-china-yes' );
	return (
		<HeroAnchor
			count={ on + '/' + total }
			label={ __( '核心服务已接通', 'wp-china-yes' ) }
			sub={ sub }
			check={ check }
			moreHref={ adminPageUrl( PAGES.diagnose ) }
			tone={ degraded ? 'warn' : 'ok' }
			dots={ dots }
		/>
	);
}

/**
 * @param {Object}  props
 * @param {Object}  props.stats
 * @param {boolean} props.domestic
 * @param {boolean} props.inbound
 * @param {boolean} props.freshInstall
 */
function StatsGrid( { stats, domestic, inbound, freshInstall } ) {
	const series = stats?.series || {};
	let cards;
	if ( inbound ) {
		cards = [
			{
				icon: 'bolt',
				label: __( '前台公共库请求', 'wp-china-yes' ),
				key: 'assets_rewrites_frontend',
				when: __( '有访客打开页面后开始计数', 'wp-china-yes' ),
				desc: () => __( '只在前台 · 大陆访客首屏更快', 'wp-china-yes' ),
			},
			{
				icon: 'user',
				label: __( '头像请求', 'wp-china-yes' ),
				key: 'avatar_rewrites_frontend',
				when: __( '有评论或用户头像加载后开始计数', 'wp-china-yes' ),
				desc: () =>
					__( '走 Cravatar · 中国买家不再空头像', 'wp-china-yes' ),
			},
			{
				icon: 'download',
				label: __( '更新检查', 'wp-china-yes' ),
				key: 'mirror_downloads',
				when: __( '下次更新检查后开始计数', 'wp-china-yes' ),
				desc: () =>
					__( '服务器在海外 · 直连 WordPress.org', 'wp-china-yes' ),
			},
		];
	} else if ( domestic ) {
		cards = [
			{
				icon: 'download',
				label: __( '更新与安装包', 'wp-china-yes' ),
				key: 'mirror_downloads',
				when: __( '下次更新检查后开始计数', 'wp-china-yes' ),
				desc: ( bytes ) =>
					sprintf(
						/* translators: %s: saved size */
						__( '经国内镜像完成 · 节省下载约 %s', 'wp-china-yes' ),
						formatBytes( bytes )
					),
				bytesKey: 'mirror_bytes_saved',
			},
			{
				icon: 'bolt',
				label: __( '公共库请求', 'wp-china-yes' ),
				key: 'assets_rewrites_frontend',
				when: __( '有访客打开页面后开始计数', 'wp-china-yes' ),
				desc: () =>
					__( '改走到国内可达源 · 访客首屏更快', 'wp-china-yes' ),
			},
			{
				icon: 'user',
				label: __( '头像请求', 'wp-china-yes' ),
				keys: [ 'avatar_rewrites_frontend', 'avatar_rewrites_admin' ],
				when: __( '有评论或用户头像加载后开始计数', 'wp-china-yes' ),
				desc: () =>
					__( '走国内节点 · 评论区不再空头像', 'wp-china-yes' ),
			},
		];
	} else {
		cards = [
			{
				icon: 'bolt',
				label: __( '后台公共库请求', 'wp-china-yes' ),
				key: 'assets_rewrites_admin',
				when: __( '你下次打开后台页面后开始计数', 'wp-china-yes' ),
				desc: () => __( '只在后台生效 · 后台加载更快', 'wp-china-yes' ),
			},
			{
				icon: 'clock',
				label: __( '后台心跳请求', 'wp-china-yes' ),
				key: 'heartbeat_saved',
				when: __( '打开编辑器或仪表盘后开始计数', 'wp-china-yes' ),
				desc: () =>
					__(
						'已节省 · 仪表盘关闭、编辑器 60 秒一次',
						'wp-china-yes'
					),
			},
			{
				icon: 'block',
				label: __( '出站请求', 'wp-china-yes' ),
				keys: [ 'dashboard_feeds_blocked', 'outbound_blocked' ],
				when: __( '有仪表盘外部请求被拦下后开始计数', 'wp-china-yes' ),
				desc: () =>
					__(
						'已屏蔽 · WordPress 新闻、活动等仪表盘外部内容',
						'wp-china-yes'
					),
			},
		];
	}

	return (
		<div className="wpcy-grid-3">
			{ cards.map( ( card ) => {
				const keys = card.keys || [ card.key ];
				const lastParts = keys.map(
					( k ) => split14( series[ k ] ).last
				);
				const prevParts = keys.map(
					( k ) => split14( series[ k ] ).prev
				);
				const lastSum = lastParts.reduce(
					( acc, part ) => acc + sumSeries( part ),
					0
				);
				const prevSum = prevParts.reduce(
					( acc, part ) => acc + sumSeries( part ),
					0
				);
				const points = ( lastParts[ 0 ] || [] ).map( ( row, i ) =>
					keys.reduce(
						( acc, k ) =>
							acc +
							( Number(
								split14( series[ k ] ).last[ i ]?.value
							) || 0 ),
						0
					)
				);
				const empty = Boolean( freshInstall );
				const bytes = card.bytesKey
					? sumSeries( split14( series[ card.bytesKey ] ).last )
					: 0;
				return (
					<StatArea
						key={ card.label }
						icon={ card.icon }
						label={ card.label }
						value={
							empty
								? __( '还没有数据', 'wp-china-yes' )
								: formatInt( lastSum )
						}
						unit={ empty ? '' : __( '次', 'wp-china-yes' ) }
						delta={ empty ? null : deltaPct( lastSum, prevSum ) }
						desc={ empty ? card.when : card.desc( bytes ) }
						points={ empty ? [] : points }
						empty={ empty }
					/>
				);
			} ) }
		</div>
	);
}

function SvcCard( { rows, error, onRetry, degraded, recovery } ) {
	let sub = __( '每 10 分钟自动检查一次', 'wp-china-yes' );
	if ( degraded ) {
		sub += ' · ' + __( '不可达时每 1 分钟重试', 'wp-china-yes' );
	}
	if ( recovery ) {
		sub += ' · ' + __( '只检查不接管', 'wp-china-yes' );
	}
	return (
		<Card tight>
			<CardHead
				title={ __( '核心服务', 'wp-china-yes' ) }
				sub={ sub }
				extra={
					<a className="more" href={ adminPageUrl( PAGES.diagnose ) }>
						{ __( '查看全部', 'wp-china-yes' ) }
					</a>
				}
			/>
			{ error ? (
				<LoadError
					what={ __( '线路状态', 'wp-china-yes' ) }
					onRetry={ onRetry }
				/>
			) : (
				<SvcMatrix rows={ rows } />
			) }
		</Card>
	);
}

function EventsCard( { events, error, onRetry } ) {
	if ( error ) {
		return (
			<Card tight>
				<CardHead
					title={ __( '最近动态', 'wp-china-yes' ) }
					sub={ __( '线路切换与自动处理', 'wp-china-yes' ) }
				/>
				<LoadError
					what={ __( '动态', 'wp-china-yes' ) }
					onRetry={ onRetry }
				/>
			</Card>
		);
	}
	let list = [];
	if ( Array.isArray( events?.events ) ) {
		list = events.events;
	} else if ( Array.isArray( events ) ) {
		list = events;
	}
	const items = list.slice( 0, 6 ).map( ( ev ) => ( {
		id: ev.id,
		tone: ev.tone,
		time: eventTime( ev.at ),
		text: ev.detail ? ev.title + '，' + ev.detail : ev.title,
	} ) );
	return (
		<Card tight>
			<CardHead
				title={ __( '最近动态', 'wp-china-yes' ) }
				sub={ __( '线路切换与自动处理', 'wp-china-yes' ) }
				extra={
					<a className="more" href={ adminPageUrl( PAGES.diagnose ) }>
						{ __( '查看全部', 'wp-china-yes' ) }
					</a>
				}
			/>
			{ items.length ? (
				<EventsSimple items={ items } />
			) : (
				<Empty
					icon="clock"
					why={ __(
						'还没有动态。线路切换、更新检查等会记录在这里。',
						'wp-china-yes'
					) }
				/>
			) }
		</Card>
	);
}
