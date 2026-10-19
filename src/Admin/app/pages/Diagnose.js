/**
 * Diagnose: server checks, browser probe, records, recovery.
 */

import { __, sprintf } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import PageShell from '../components/PageShell';
import { STORE_NAME } from '../store';
import { adminPageUrl, PAGES } from '../routing';
import Icon from '../ui/icons';
import Btn from '../ui/Btn';
import Pill from '../ui/Pill';
import Notice from '../ui/Notice';
import Sec from '../ui/Sec';
import Card, { CardHead } from '../ui/Card';
import Empty from '../ui/Empty';
import EventsSimple from '../ui/EventsSimple';
import { eventTime, formatInt, relTime } from '../ui/relTime';
import { formatMs, groupAggregate, ROUTE_GROUPS } from '../ui/svcStatus';

const PAIRS = [
	{
		origin: 'fonts.googleapis.com',
		originHost: 'fonts.googleapis.com',
		target: 'googlefonts.admincdn.com',
		targetHost: 'googlefonts.admincdn.com',
		provider: 'adminCDN',
	},
	{
		origin: 'gravatar.com',
		originHost: 'secure.gravatar.com',
		target: 'cn.cravatar.com',
		targetHost: 'cn.cravatar.com',
		provider: 'Cravatar',
	},
];

function probeActionLabel( probing, probe ) {
	if ( probing ) {
		return __( '测速中', 'wp-china-yes' );
	}
	if ( probe?.checked_at ) {
		return __( '重新测速', 'wp-china-yes' );
	}
	return __( '开始测速', 'wp-china-yes' );
}

function pillFor( result, latency ) {
	if ( result === 'ok' ) {
		if ( latency > 1000 ) {
			return { tone: 'warn', word: __( '偏慢', 'wp-china-yes' ) };
		}
		return { tone: 'ok', word: __( '正常', 'wp-china-yes' ) };
	}
	if ( result === 'fallback' ) {
		return { tone: 'warn', word: __( '已回原始上游', 'wp-china-yes' ) };
	}
	if ( result === 'down' ) {
		return { tone: 'bad', word: __( '不可达', 'wp-china-yes' ) };
	}
	return { tone: '', word: __( '未检查', 'wp-china-yes' ) };
}

export default function Diagnose() {
	const slice = useSelect( ( select ) => {
		const store = select( STORE_NAME );
		return {
			targets: store.getDiagnostics()?.targets || [],
			running: store.isRunning(),
			events: store.getEvents(),
			migration: store.getMigration(),
			clientProbe: store.getClientProbe(),
			diagnosticsMs: store.getDiagnosticsMs(),
			pluginVersion: store.getPluginVersion(),
		};
	}, [] );
	const {
		runDiagnostics,
		fetchDiagnostics,
		fetchEvents,
		fetchMigration,
		fetchClientProbe,
	} = useDispatch( STORE_NAME );
	const [ logs, setLogs ] = useState( [] );
	const [ elementHide, setElementHide ] = useState( null );
	const [ probing, setProbing ] = useState( false );
	const [ probeError, setProbeError ] = useState( false );
	useEffect( () => {
		fetchDiagnostics();
		fetchEvents();
		fetchMigration();
		fetchClientProbe();
		apiFetch( { path: '/wpcy/v1/residency/log?per_page=5' } )
			.then( ( body ) => {
				setLogs( Array.isArray( body?.items ) ? body.items : [] );
			} )
			.catch( () => setLogs( [] ) );
		apiFetch( { path: '/wpcy/v1/element-hide' } )
			.then( ( body ) => {
				setElementHide(
					body && typeof body === 'object' ? body : null
				);
			} )
			.catch( () => setElementHide( null ) );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	async function runProbe() {
		setProbing( true );
		setProbeError( false );
		try {
			const probes = [];
			const started = performance.now();
			await apiFetch( { path: '/wpcy/v1/diagnostics' } );
			const ttfb = Math.round( performance.now() - started );
			const hosts = PAIRS.flatMap( ( p ) => [
				p.originHost,
				p.targetHost,
			] );
			for ( const host of hosts ) {
				const t0 = performance.now();
				try {
					await fetch( 'https://' + host + '/', {
						mode: 'no-cors',
						cache: 'no-store',
					} );
					probes.push( {
						target: host,
						result: 'ok',
						latency_ms: Math.round( performance.now() - t0 ),
					} );
				} catch ( err ) {
					void err;
					probes.push( {
						target: host,
						result: 'down',
						latency_ms: null,
					} );
				}
			}
			await apiFetch( {
				path: '/wpcy/v1/diagnostics/client-probe',
				method: 'POST',
				data: { ttfb_ms: ttfb, probes },
			} );
			fetchClientProbe();
		} catch ( err ) {
			void err;
			setProbeError( true );
		} finally {
			setProbing( false );
		}
	}

	useEffect( () => {
		if ( window.location.hash.indexOf( 'probe' ) !== -1 ) {
			runProbe();
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const exportReport = () => {
		const blob = new Blob(
			[
				JSON.stringify(
					{
						diagnostics: { targets: slice.targets },
						clientProbe: slice.clientProbe,
						migration: slice.migration,
						version: slice.pluginVersion,
					},
					null,
					2
				),
			],
			{ type: 'application/json' }
		);
		const url = URL.createObjectURL( blob );
		const a = document.createElement( 'a' );
		a.href = url;
		a.download = 'wpcy-diagnose.json';
		a.click();
		URL.revokeObjectURL( url );
	};

	const lastCheck = slice.targets[ 0 ]?.checked_at;
	const groups = ROUTE_GROUPS.map( ( group ) => {
		const agg = groupAggregate( slice.targets, group.hosts );
		return { group, agg };
	} ).filter( ( row ) => row.agg.result );

	return (
		<PageShell
			title={ __( '诊断', 'wp-china-yes' ) }
			lede={ __(
				'线路检查、浏览器测速、迁移记录与恢复',
				'wp-china-yes'
			) }
			actions={
				<Btn variant="secondary" onClick={ exportReport }>
					<Icon name="download" size={ 16 } />
					{ __( '导出诊断报告', 'wp-china-yes' ) }
				</Btn>
			}
		>
			<section className="sec">
				<Sec
					title={ __( '从你的服务器到各源', 'wp-china-yes' ) }
					note={
						lastCheck
							? sprintf(
									/* translators: %s: relative time */
									__(
										'每 10 分钟自动检查 · 上次 %s',
										'wp-china-yes'
									),
									relTime( lastCheck )
							  )
							: __( '每 10 分钟自动检查', 'wp-china-yes' )
					}
					action={
						<Btn
							variant="ghost"
							disabled={ slice.running }
							onClick={ () => runDiagnostics() }
						>
							{ slice.running
								? __( '检查中', 'wp-china-yes' )
								: __( '立即检查', 'wp-china-yes' ) }
						</Btn>
					}
				/>
				<article className="card tight">
					<table className="tbl">
						<thead>
							<tr>
								<th>{ __( '源', 'wp-china-yes' ) }</th>
								<th>{ __( '状态', 'wp-china-yes' ) }</th>
								<th>{ __( '延迟', 'wp-china-yes' ) }</th>
								<th>{ __( '最近检查', 'wp-china-yes' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ groups.length
								? groups.map( ( { group, agg } ) => {
										const pill = pillFor(
											agg.result,
											agg.latency_ms
										);
										return (
											<tr key={ group.id }>
												<td>
													<div className="t">
														{ group.name }
													</div>
													<div className="d">
														{
															group.fallbackProvider
														}{ ' ' }
														· { group.desc }
													</div>
													<span className="screen-reader-text">
														{ agg.target }
													</span>
												</td>
												<td>
													<Pill tone={ pill.tone }>
														{ pill.word }
													</Pill>
												</td>
												<td className="num">
													{ formatMs(
														agg.latency_ms
													) }
												</td>
												<td className="num">
													{ relTime(
														agg.checked_at
													) }
												</td>
											</tr>
										);
								  } )
								: ( slice.targets || [] ).map( ( row ) => {
										const pill = pillFor(
											row.result,
											row.latency_ms
										);
										return (
											<tr key={ row.target }>
												<td>
													<div className="t">
														{ row.target }
													</div>
												</td>
												<td>
													<Pill tone={ pill.tone }>
														{ pill.word }
													</Pill>
												</td>
												<td className="num">
													{ formatMs(
														row.latency_ms
													) }
												</td>
												<td className="num">
													{ relTime(
														row.checked_at
													) }
												</td>
											</tr>
										);
								  } ) }
						</tbody>
					</table>
				</article>
				<div className="prov-status">
					{ __(
						'线路异常时，先看服务商状态再排查自己：',
						'wp-china-yes'
					) }
					<a
						href="https://status.wpcy.com"
						target="_blank"
						rel="noopener noreferrer"
					>
						status.wpcy.com
					</a>
					{ ' · ' }
					<a
						href="https://status.wpcy.net"
						target="_blank"
						rel="noopener noreferrer"
					>
						status.wpcy.net
					</a>
				</div>
			</section>

			<section className="sec" id="probe">
				<Sec
					title={ __( '从你的浏览器到各源', 'wp-china-yes' ) }
					note={ __(
						'用你现在的网络测，区分是插件层还是网络层的问题',
						'wp-china-yes'
					) }
					action={
						<Btn
							variant="ghost"
							disabled={ probing }
							onClick={ runProbe }
						>
							{ probeActionLabel( probing, slice.clientProbe ) }
						</Btn>
					}
				/>
				<ProbeTable
					probe={ slice.clientProbe }
					serverMs={ slice.diagnosticsMs }
					error={ probeError }
				/>
			</section>

			<section className="sec">
				<Sec title={ __( '记录', 'wp-china-yes' ) } />
				<div className="wpcy-grid-2">
					<MigrationCard migration={ slice.migration } />
					<LogCard logs={ logs } elementHide={ elementHide } />
				</div>
			</section>

			<section className="sec">
				<Sec title={ __( '最近动态', 'wp-china-yes' ) } />
				<Card tight>
					<EventsBlock events={ slice.events } />
				</Card>
			</section>

			<section className="sec">
				<Sec title={ __( '数据与恢复', 'wp-china-yes' ) } />
				<article className="card">
					<div className="rows recover">
						<div>
							<div>
								<div className="t">
									{ __( '进入恢复模式', 'wp-china-yes' ) }
								</div>
								<div className="d">
									{ __(
										'一键停用所有资源接通与模块；恢复页不依赖 JavaScript，后台样式错乱时也能打开',
										'wp-china-yes'
									) }
								</div>
							</div>
							<Btn
								variant="secondary"
								className="r"
								href={ adminPageUrl( PAGES.recovery ) }
							>
								{ __( '进入恢复模式', 'wp-china-yes' ) }
							</Btn>
						</div>
						<div>
							<div>
								<div className="t">
									{ __( '小工具数据', 'wp-china-yes' ) }
								</div>
								<div className="d">
									{ __(
										'按小工具导出或删除它保存在本站的数据',
										'wp-china-yes'
									) }
								</div>
							</div>
							<Btn variant="ghost" className="r" disabled>
								{ __( '管理', 'wp-china-yes' ) }
							</Btn>
						</div>
					</div>
				</article>
			</section>
		</PageShell>
	);
}

function ProbeTable( { probe, serverMs, error } ) {
	if ( error ) {
		return (
			<article className="card tight">
				<Notice tone="warn">
					{ __(
						'暂时无法从浏览器测速，请稍后重试。',
						'wp-china-yes'
					) }
				</Notice>
			</article>
		);
	}
	const never = ! probe || ! probe.checked_at;
	if ( never ) {
		return (
			<article className="card tight">
				<Empty
					icon="gauge"
					why={ __( '尚未从浏览器测速', 'wp-china-yes' ) }
				/>
			</article>
		);
	}
	const probes = probe.probes || [];
	const ago = relTime( probe.checked_at );
	const ttfb = probe.ttfb_ms || serverMs;
	const find = ( host ) => probes.find( ( p ) => p.target === host );

	const originDown = PAIRS.some(
		( p ) => find( p.originHost )?.result === 'down'
	);
	const targetOk = PAIRS.some(
		( p ) => find( p.targetHost )?.result === 'ok'
	);
	let insight = '';
	if ( ttfb > 1000 ) {
		insight = sprintf(
			/* translators: %s: ms */
			__(
				'后台慢主要来自你到服务器的往返（%s），插件层能接通的资源已接通；换线路或就近节点才能进一步改善。',
				'wp-china-yes'
			),
			formatMs( ttfb )
		);
	} else if ( originDown && targetOk ) {
		insight = __(
			'原始源在你的网络不可达，后台已接通国内可达源，这就是叶子在做的事。',
			'wp-china-yes'
		);
	}

	return (
		<article className="card tight">
			<table className="tbl">
				<thead>
					<tr>
						<th>{ __( '源', 'wp-china-yes' ) }</th>
						<th>{ __( '状态', 'wp-china-yes' ) }</th>
						<th>{ __( '延迟', 'wp-china-yes' ) }</th>
						<th>{ __( '最近检查', 'wp-china-yes' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ ttfb ? (
						<tr>
							<td>
								<div className="t">
									{ __( '你的服务器', 'wp-china-yes' ) }
								</div>
								<div className="d">
									{ __( '站点后台 TTFB', 'wp-china-yes' ) }
								</div>
							</td>
							<td>
								<Pill tone={ ttfb > 1000 ? 'warn' : 'ok' }>
									{ ttfb > 1000
										? __( '偏慢', 'wp-china-yes' )
										: __( '正常', 'wp-china-yes' ) }
								</Pill>
							</td>
							<td className="num">{ formatMs( ttfb ) }</td>
							<td className="num">{ ago }</td>
						</tr>
					) : null }
					{ PAIRS.map( ( pair ) => {
						const origin = find( pair.originHost );
						const target = find( pair.targetHost );
						return [
							<tr key={ pair.origin }>
								<td>
									<div className="t">{ pair.origin }</div>
									<div className="d">
										{ __( '原始源', 'wp-china-yes' ) }
									</div>
								</td>
								<td>
									<Pill
										tone={
											origin?.result === 'ok'
												? 'ok'
												: 'bad'
										}
									>
										{ origin?.result === 'ok'
											? __( '正常', 'wp-china-yes' )
											: __( '超时', 'wp-china-yes' ) }
									</Pill>
								</td>
								<td className="num">
									{ formatMs( origin?.latency_ms ) }
								</td>
								<td className="num">{ ago }</td>
							</tr>,
							<tr key={ pair.target }>
								<td>
									<div className="t">
										<span
											className="swaped"
											title={
												__( '原始源', 'wp-china-yes' ) +
												' ' +
												pair.origin
											}
										>
											<Icon name="swap" size={ 14 } />
										</span>
										{ pair.target }
									</div>
									<div className="d">
										{ pair.provider } ·{ ' ' }
										{ __( '接通后', 'wp-china-yes' ) }
									</div>
								</td>
								<td>
									<Pill
										tone={
											target?.result === 'ok'
												? 'ok'
												: 'bad'
										}
									>
										{ target?.result === 'ok'
											? __( '正常', 'wp-china-yes' )
											: __( '超时', 'wp-china-yes' ) }
									</Pill>
								</td>
								<td className="num">
									{ formatMs( target?.latency_ms ) }
								</td>
								<td className="num">{ ago }</td>
							</tr>,
						];
					} ) }
				</tbody>
			</table>
			{ insight ? <Notice tone="info">{ insight }</Notice> : null }
		</article>
	);
}

function MigrationCard( { migration } ) {
	if ( ! migration || migration.status === 'none' ) {
		return (
			<Card tight>
				<CardHead
					title={ __( '迁移记录', 'wp-china-yes' ) }
					sub={ __( '从 3.x 升级时的设置迁移', 'wp-china-yes' ) }
				/>
				<p className="meta">
					{ __(
						'这个站点是直接安装 4.0 的，没有迁移记录。',
						'wp-china-yes'
					) }
				</p>
			</Card>
		);
	}
	const kept = Array.isArray( migration.kept )
		? migration.kept.length
		: migration.kept || 0;
	const ignored = Array.isArray( migration.ignored )
		? migration.ignored.length
		: migration.ignored || 0;
	return (
		<Card tight>
			<CardHead
				title={ __( '迁移记录', 'wp-china-yes' ) }
				sub={ __( '从 3.x 升级时的设置迁移', 'wp-china-yes' ) }
			/>
			<div className="rows">
				<div>
					<div>
						<div className="t">
							{ ( migration.migrated_at || '' ).slice( 0, 10 ) }{ ' ' }
							{ sprintf(
								/* translators: 1: from 2: to */
								__( '从 %1$s 升级到 %2$s', 'wp-china-yes' ),
								migration.source_version || '3.x',
								migration.version || '4.0.0'
							) }
						</div>
						<div className="d">
							{ sprintf(
								/* translators: 1: kept 2: ignored */
								__(
									'%1$d 项已迁移 · %2$d 项已不再需要',
									'wp-china-yes'
								),
								kept,
								ignored
							) }
						</div>
					</div>
					<Pill tone="ok" className="r">
						{ __( '成功', 'wp-china-yes' ) }
					</Pill>
				</div>
			</div>
		</Card>
	);
}

function LogCard( { logs, elementHide } ) {
	const hits = Number( elementHide?.hits ) || 0;
	const version = Number( elementHide?.version ) || 0;
	return (
		<Card tight>
			<CardHead
				title={ __( '出站主机记录', 'wp-china-yes' ) }
				sub={ __(
					'插件按主机表处理过的出站请求（不含内容）',
					'wp-china-yes'
				) }
			/>
			<p className="meta">
				{ sprintf(
					/* translators: 1: monthly hide count 2: ruleset version */
					__(
						'本月隐藏推广 %1$d 次 · 规则集版本 %2$d',
						'wp-china-yes'
					),
					hits,
					version
				) }
			</p>
			{ logs.length ? (
				<table className="tbl">
					<thead>
						<tr>
							<th>{ __( '主机', 'wp-china-yes' ) }</th>
							<th>{ __( '类别', 'wp-china-yes' ) }</th>
							<th>{ __( '次数', 'wp-china-yes' ) }</th>
							<th>{ __( '最近', 'wp-china-yes' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ logs.map( ( row ) => (
							<tr key={ row.host }>
								<td className="t">{ row.host }</td>
								<td className="d">{ row.data_class }</td>
								<td className="num">
									{ formatInt( row.count ) }
								</td>
								<td className="num">
									{ relTime( row.last_seen ) }
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) : (
				<p className="meta">
					{ __(
						'还没有记录。有出站请求被主机表处理后会出现在这里。',
						'wp-china-yes'
					) }
				</p>
			) }
		</Card>
	);
}

function EventsBlock( { events } ) {
	let list = [];
	if ( Array.isArray( events?.events ) ) {
		list = events.events;
	} else if ( Array.isArray( events ) ) {
		list = events;
	}
	const items = list.slice( 0, 20 ).map( ( ev ) => ( {
		id: ev.id,
		tone: ev.tone,
		time: eventTime( ev.at ),
		text: ev.title,
	} ) );
	if ( ! items.length ) {
		return (
			<Empty
				icon="clock"
				why={ __(
					'还没有动态。线路切换、更新检查等会记录在这里。',
					'wp-china-yes'
				) }
			/>
		);
	}
	return <EventsSimple items={ items } />;
}
