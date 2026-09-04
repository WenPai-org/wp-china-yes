/**
 * Overview: recovery banner, line cards, attention, announcements slot.
 */

import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardFooter,
	CardHeader,
	Notice,
	Spinner,
} from '@wordpress/components';
import PageShell from '../components/PageShell';
import StatusDot from '../components/StatusDot';
import { STORE_NAME } from '../store';
import { adminPageUrl, PAGES } from '../routing';

const PUBLIC_HOSTS = [
	'cdnjs.admincdn.com',
	'jsd.admincdn.com',
	'googleajax.admincdn.com',
	'googlefonts.admincdn.com',
];

/**
 * Human-readable minutes since a UTC ISO timestamp.
 *
 * @param {string} iso UTC ISO 8601 timestamp.
 * @return {string} Relative check time, or empty.
 */
function minutesAgo( iso ) {
	if ( ! iso ) {
		return '';
	}
	const then = Date.parse( iso );
	if ( Number.isNaN( then ) ) {
		return iso;
	}
	const minutes = Math.max( 0, Math.round( ( Date.now() - then ) / 60000 ) );
	return (
		__( '最近检查', 'wp-china-yes' ) +
		' ' +
		minutes +
		' ' +
		__( '分钟前', 'wp-china-yes' )
	);
}

/**
 * @param {string} result
 */
function resultStatus( result ) {
	if ( result === 'ok' ) {
		return {
			tone: 'success',
			label: __( '国内镜像正常', 'wp-china-yes' ),
		};
	}
	if ( result === 'fallback' ) {
		return {
			tone: 'warning',
			label: __( '已回原始上游', 'wp-china-yes' ),
		};
	}
	if ( result === 'down' ) {
		return { tone: 'danger', label: __( '不可用', 'wp-china-yes' ) };
	}
	return null;
}

/**
 * @param {Array} targets
 */
function wordpressOrgSummary( targets ) {
	const rows = ( targets || [] ).filter(
		( row ) =>
			row.target === 'api.wenpai.net' ||
			row.target === 'downloads.wenpai.net'
	);
	if ( ! rows.length ) {
		return null;
	}
	let worst = 'ok';
	if ( rows.find( ( row ) => row.result === 'down' ) ) {
		worst = 'down';
	} else if ( rows.find( ( row ) => row.result === 'fallback' ) ) {
		worst = 'fallback';
	}
	const latest = rows.reduce( ( acc, row ) => {
		if (
			! acc ||
			Date.parse( row.checked_at ) > Date.parse( acc.checked_at )
		) {
			return row;
		}
		return acc;
	}, null );
	return { status: resultStatus( worst ), latest };
}

/**
 * @param {Array} targets
 */
function publicAssetsSummary( targets ) {
	const rows = ( targets || [] ).filter( ( row ) =>
		PUBLIC_HOSTS.includes( row.target )
	);
	if ( ! rows.length ) {
		return null;
	}
	const accelerated = rows.filter( ( row ) => row.result === 'ok' );
	const fallback = rows.filter( ( row ) => row.result !== 'ok' );
	return {
		accelerated: accelerated.length,
		total: rows.length,
		fallbackNames: fallback.map( ( row ) => row.target ),
		partial: fallback.length > 0,
	};
}

/**
 * Attention rows from diagnostics. Empty array means the section is omitted.
 *
 * @param {Array} targets Probe rows.
 * @return {Array<{id: string, text: string, href: string, action: string}>} Up to five items.
 */
function attentionItems( targets ) {
	const items = [];
	const org = wordpressOrgSummary( targets );
	if ( org?.status?.tone === 'danger' ) {
		items.push( {
			id: 'org-down',
			text: __(
				'WordPress.org 源不可用，更新与安装包已暂停。',
				'wp-china-yes'
			),
			href: adminPageUrl( PAGES.diagnose ),
			action: __( '详情', 'wp-china-yes' ),
		} );
	}
	return items.slice( 0, 5 );
}

/**
 * Source label. wptea = 文派茶馆, one = 薇晓朵.
 *
 * @param {string} source Source id.
 * @return {string} Label.
 */
function sourceLabel( source ) {
	if ( source === 'one' ) {
		return __( '薇晓朵', 'wp-china-yes' );
	}
	return __( '文派茶馆', 'wp-china-yes' );
}

/**
 * Date part of a UTC ISO timestamp.
 *
 * @param {string} iso Timestamp.
 * @return {string} YYYY-MM-DD or the original string.
 */
function publishedDate( iso ) {
	if ( ! iso ) {
		return '';
	}
	return iso.slice( 0, 10 );
}

/**
 * Announcements list. Empty cache: render nothing, no error.
 *
 * @param {Object}   props
 * @param {Array}    props.items
 * @param {Function} props.onDismiss
 */
function AnnouncementsList( { items, onDismiss } ) {
	if ( ! items.length ) {
		return null;
	}
	return (
		<section
			className="wpcy-section"
			aria-labelledby="wpcy-announcements-heading"
		>
			<h2 id="wpcy-announcements-heading">
				{ __( '公告', 'wp-china-yes' ) }
			</h2>
			<ul className="wpcy-announcements">
				{ items.map( ( item ) => (
					<li key={ item.id }>
						<a
							className="wpcy-ann-title"
							href={ item.url }
							target="_blank"
							rel="noopener noreferrer"
						>
							{ item.title }
						</a>
						<span className="wpcy-ann-source">
							{ sourceLabel( item.source ) }
						</span>
						<time
							className="wpcy-ann-date"
							dateTime={ item.published_at }
						>
							{ publishedDate( item.published_at ) }
						</time>
						<Button
							className="wpcy-ann-dismiss"
							variant="tertiary"
							label={ __( '关闭', 'wp-china-yes' ) }
							onClick={ () => onDismiss( item.id ) }
						>
							×
						</Button>
					</li>
				) ) }
			</ul>
		</section>
	);
}

/**
 * WordPress.org card status block.
 *
 * @param {Object}  props
 * @param {boolean} props.empty
 * @param {Object}  props.org
 */
function OrgCardBody( { empty, org } ) {
	if ( empty ) {
		return (
			<p className="wpcy-empty">{ __( '尚未检查', 'wp-china-yes' ) }</p>
		);
	}
	if ( ! org?.status ) {
		return null;
	}
	let latency = '';
	if (
		org.latest &&
		org.latest.latency_ms !== null &&
		org.latest.latency_ms !== undefined
	) {
		latency =
			' · ' +
			__( '延迟', 'wp-china-yes' ) +
			' ' +
			org.latest.latency_ms +
			' ms';
	}
	return (
		<>
			<p>
				<StatusDot
					tone={ org.status.tone }
					label={ org.status.label }
				/>
			</p>
			{ org.latest ? (
				<p className="wpcy-card-meta">
					{ minutesAgo( org.latest.checked_at ) }
					{ latency }
				</p>
			) : null }
		</>
	);
}

/**
 * Public assets card status block.
 *
 * @param {Object}  props
 * @param {boolean} props.empty
 * @param {Object}  props.assets
 */
function AssetsCardBody( { empty, assets } ) {
	if ( empty || ! assets ) {
		return (
			<p className="wpcy-empty">{ __( '尚未检查', 'wp-china-yes' ) }</p>
		);
	}
	const label = assets.partial
		? __( '部分回退', 'wp-china-yes' )
		: assets.accelerated + ' ' + __( '项已加速', 'wp-china-yes' );
	return <p>{ label }</p>;
}

export default function Overview() {
	const { recovery, exited, notice, targets, hasDiagnostics, running } =
		useSelect( ( select ) => {
			const store = select( STORE_NAME );
			return {
				recovery: store.isRecoveryMode(),
				exited: store.hasExitedRecovery(),
				notice: store.getNotice(),
				targets: store.getDiagnostics()?.targets || [],
				hasDiagnostics: store.hasDiagnostics(),
				running: store.isRunning(),
			};
		}, [] );
	const { exitRecovery, runDiagnostics } = useDispatch( STORE_NAME );
	const [ announcements, setAnnouncements ] = useState( [] );

	useEffect( () => {
		let cancelled = false;
		apiFetch( { path: '/wpcy/v1/announcements' } )
			.then( ( payload ) => {
				if ( cancelled ) {
					return;
				}
				const items = Array.isArray( payload?.items )
					? payload.items.slice( 0, 5 )
					: [];
				setAnnouncements( items );
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setAnnouncements( [] );
				}
			} );
		return () => {
			cancelled = true;
		};
	}, [] );

	const dismissAnnouncement = ( id ) => {
		apiFetch( {
			path:
				'/wpcy/v1/announcements/' +
				encodeURIComponent( id ) +
				'/dismiss',
			method: 'POST',
		} )
			.then( ( payload ) => {
				const items = Array.isArray( payload?.items )
					? payload.items.slice( 0, 5 )
					: [];
				setAnnouncements( items );
			} )
			.catch( () => {
				setAnnouncements( ( current ) => current );
			} );
	};

	const org = wordpressOrgSummary( targets );
	const assets = publicAssetsSummary( targets );
	const attention = attentionItems( targets );

	const empty = ! hasDiagnostics;
	const actions = empty ? (
		<Button
			variant="primary"
			isBusy={ running }
			onClick={ () => runDiagnostics() }
		>
			{ running ? <Spinner /> : null }
			{ __( '立即检查', 'wp-china-yes' ) }
		</Button>
	) : null;

	return (
		<PageShell title={ __( '概览', 'wp-china-yes' ) } actions={ actions }>
			{ recovery ? (
				<Notice status="warning" isDismissible={ false }>
					<p>
						{ __(
							'恢复模式已开启：全部 URL 改写与模块已停用。',
							'wp-china-yes'
						) }
					</p>
					<Button
						variant="secondary"
						onClick={ () => exitRecovery() }
					>
						{ __( '退出恢复模式', 'wp-china-yes' ) }
					</Button>
				</Notice>
			) : null }

			{ ! recovery && exited && notice ? (
				<Notice status="warning" isDismissible={ false }>
					{ notice.message }
				</Notice>
			) : null }

			<section
				className="wpcy-section"
				aria-labelledby="wpcy-line-status-heading"
			>
				<h2 id="wpcy-line-status-heading" className="wpcy-sr-only">
					{ __( '线路状态', 'wp-china-yes' ) }
				</h2>
				<div className="wpcy-card-row">
					<Card>
						<CardHeader>
							{ __( 'WordPress.org 源', 'wp-china-yes' ) }
						</CardHeader>
						<CardBody>
							<p className="wpcy-card-kicker">
								{ __( '更新与安装包', 'wp-china-yes' ) }
							</p>
							<OrgCardBody empty={ empty } org={ org } />
						</CardBody>
						<CardFooter>
							<Button
								variant="secondary"
								href={ adminPageUrl( PAGES.diagnose ) }
							>
								{ __( '详情', 'wp-china-yes' ) }
							</Button>
						</CardFooter>
					</Card>

					<Card>
						<CardHeader>
							{ __( '公共库与头像', 'wp-china-yes' ) }
						</CardHeader>
						<CardBody>
							<p className="wpcy-card-kicker">
								{ __( '前端资源', 'wp-china-yes' ) }
							</p>
							<AssetsCardBody empty={ empty } assets={ assets } />
							{ ! empty && assets?.partial ? (
								<p className="wpcy-card-meta">
									{ assets.fallbackNames.join( ', ' ) }
								</p>
							) : null }
						</CardBody>
						<CardFooter>
							<Button
								variant="secondary"
								href={ adminPageUrl( PAGES.connect ) }
							>
								{ __( '设置', 'wp-china-yes' ) }
							</Button>
						</CardFooter>
					</Card>

					<Card>
						<CardHeader>
							{ __( '文派服务', 'wp-china-yes' ) }
						</CardHeader>
						<CardBody>
							<p className="wpcy-card-kicker">
								{ __( '站点绑定', 'wp-china-yes' ) }
							</p>
							<p>
								<StatusDot
									tone="neutral"
									label={ __( '未绑定', 'wp-china-yes' ) }
								/>
							</p>
							<p className="wpcy-card-meta">
								{ __(
									'绑定后可使用受限免费服务与小工具。',
									'wp-china-yes'
								) }
							</p>
						</CardBody>
						<CardFooter>
							<Button
								variant="secondary"
								href={ adminPageUrl( PAGES.services ) }
							>
								{ __( '前往', 'wp-china-yes' ) }
							</Button>
						</CardFooter>
					</Card>
				</div>
			</section>

			{ attention.length ? (
				<section
					className="wpcy-section"
					aria-labelledby="wpcy-attention-heading"
				>
					<h2 id="wpcy-attention-heading">
						{ __( '需要处理', 'wp-china-yes' ) }
					</h2>
					<ul className="wpcy-attention-list">
						{ attention.map( ( item ) => (
							<li key={ item.id }>
								<span>{ item.text }</span>
								<Button variant="secondary" href={ item.href }>
									{ item.action }
								</Button>
							</li>
						) ) }
					</ul>
				</section>
			) : null }

			<AnnouncementsList
				items={ announcements }
				onDismiss={ dismissAnnouncement }
			/>
		</PageShell>
	);
}
