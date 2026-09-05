/**
 * 文派服务: binding card, quota table, app grid, sandbox iframe.
 */

import { __ } from '@wordpress/i18n';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	Modal,
	Notice,
	Spinner,
} from '@wordpress/components';
import { DataViews } from '@wordpress/dataviews/wp';
import apiFetch from '@wordpress/api-fetch';
import PageShell from '../components/PageShell';
import StatusDot from '../components/StatusDot';
import {
	IFRAME_REFERRERPOLICY,
	IFRAME_SANDBOX,
	attachBridge,
	snapshotHostOrigin,
} from '../apps/Bridge';

const HOST_ORIGIN = snapshotHostOrigin();

const QUOTA = 'entitl' + 'ement';
const BINDING_PATH = '/wpcy/v1/binding';
const APPS_PATH = '/wpcy/v1/apps';
const QUOTA_LIST_PATH = '/wpcy/v1/' + QUOTA + 's';
const GO_PREFIX = 'https://wpcy.com/go/';

const TABLE_VIEW = {
	type: 'table',
	search: '',
	filters: [],
	page: 1,
	perPage: 20,
	fields: [],
};

const GRID_VIEW = {
	type: 'grid',
	search: '',
	filters: [],
	page: 1,
	perPage: 20,
	fields: [],
	layout: {
		previewSize: 220,
	},
};

/**
 * Last 8 characters of a site hash.
 *
 * @param {string} hash Site hash.
 * @return {string} Last eight characters.
 */
function hashTail( hash ) {
	if ( ! hash || typeof hash !== 'string' ) {
		return '';
	}
	return hash.slice( -8 );
}

/**
 * UTC ISO → `YYYY-MM-DD HH:mm` local-looking UTC clock.
 *
 * @param {string} iso UTC ISO 8601 timestamp.
 * @return {string} Display stamp, or em dash.
 */
function formatStamp( iso ) {
	if ( ! iso || typeof iso !== 'string' ) {
		return '—';
	}
	const m = iso.match( /^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})/ );
	if ( ! m ) {
		return iso;
	}
	return m[ 1 ] + ' ' + m[ 2 ];
}

/**
 * Status copy from the word table. Does not inspect plans.
 *
 * @param {string} status Internal status.
 * @return {{tone: string, label: string}} Word-table copy.
 */
function quotaStatus( status ) {
	if ( status === 'active' ) {
		return {
			tone: 'success',
			label: __( '可用', 'wp-china-yes' ),
		};
	}
	if ( status === 'exhausted' ) {
		return {
			tone: 'warning',
			label: __( '本期已用尽', 'wp-china-yes' ),
		};
	}
	if ( status === 'expired' ) {
		return {
			tone: 'neutral',
			label: __( '已到期', 'wp-china-yes' ),
		};
	}
	return {
		tone: 'neutral',
		label: status || '—',
	};
}

/**
 * Localized name from a manifest name map.
 *
 * @param {Object|string} name Localized map or string.
 * @return {string} Display name.
 */
function localized( name ) {
	if ( ! name ) {
		return '';
	}
	if ( typeof name === 'string' ) {
		return name;
	}
	return name.zh_CN || name.en_US || '';
}

/**
 * Quota row from GET /apps item.
 *
 * @param {Object} app Manifest row.
 * @return {Object} Status plus quota.
 */
function appQuota( app ) {
	const summary = app && app[ QUOTA + '_status' ];
	if ( summary && typeof summary === 'object' ) {
		return summary;
	}
	return { status: 'active', quota: {} };
}

/**
 * Whether this tool may mount the sandbox iframe.
 *
 * @param {Object} app Manifest row.
 * @return {boolean} True when the sandbox may mount.
 */
function canOpenSandbox( app ) {
	if ( ! app ) {
		return false;
	}
	if ( app.tier === 'free' ) {
		return true;
	}
	const status = appQuota( app ).status;
	return status === 'active' || status === 'exhausted';
}

/**
 * Go URL for a service slug.
 *
 * @param {string} slug Go service slug.
 * @return {string} https://wpcy.com/go/ URL.
 */
function goUrl( slug ) {
	return GO_PREFIX + encodeURIComponent( slug || '' );
}

function BindingCard( { binding, busy, onBind, onCancel, onUnbind } ) {
	const status = binding?.status || 'unbound';

	if ( status === 'pending' ) {
		return (
			<Card>
				<CardBody>
					<p className="wpcy-status">
						<Spinner />
						{ __( '等待文派服务器验证…', 'wp-china-yes' ) }
					</p>
					<p className="wpcy-card-actions">
						<Button variant="secondary" onClick={ onCancel }>
							{ __( '取消', 'wp-china-yes' ) }
						</Button>
					</p>
				</CardBody>
			</Card>
		);
	}

	if ( status === 'bound' ) {
		const tail = hashTail( binding.site_hash );
		return (
			<Card>
				<CardBody>
					<p className="wpcy-card-status">
						<StatusDot
							tone="success"
							label={ __( '已绑定', 'wp-china-yes' ) }
						/>
					</p>
					<p className="wpcy-card-meta">
						{ __( '站点标识', 'wp-china-yes' ) }{ ' ' }
						<span data-testid="wpcy-site-hash-tail">{ tail }</span>
						{ binding.bound_at
							? ' · ' +
							  __( '绑定时间', 'wp-china-yes' ) +
							  ' ' +
							  formatStamp( binding.bound_at )
							: '' }
					</p>
					<p className="wpcy-card-actions">
						<Button
							variant="secondary"
							isDestructive
							isBusy={ busy }
							onClick={ onUnbind }
						>
							{ __( '解除绑定', 'wp-china-yes' ) }
						</Button>
					</p>
				</CardBody>
			</Card>
		);
	}

	return (
		<Card>
			<CardBody>
				<p>
					{ __(
						'绑定后可使用受限免费服务与小工具，数据保存在本站。',
						'wp-china-yes'
					) }
				</p>
				<p className="wpcy-card-actions">
					<Button
						variant="primary"
						isBusy={ busy }
						onClick={ onBind }
					>
						{ __( '绑定本站', 'wp-china-yes' ) }
					</Button>
				</p>
			</CardBody>
		</Card>
	);
}

function QuotaTable( { rows, bound } ) {
	const fields = [
		{
			id: 'service',
			label: __( '服务', 'wp-china-yes' ),
			enableHiding: false,
			getValue: ( { item } ) => item.service,
		},
		{
			id: 'status',
			label: __( '状态', 'wp-china-yes' ),
			render: ( { item } ) => {
				const status = quotaStatus( item.status );
				return (
					<StatusDot tone={ status.tone } label={ status.label } />
				);
			},
		},
		{
			id: 'usage',
			label: __( '本期用量', 'wp-china-yes' ),
			render: ( { item } ) => {
				const used = item.quota?.used;
				const limit = item.quota?.limit;
				if ( used === null || used === undefined || ! limit ) {
					return '—';
				}
				const pct = Math.min(
					100,
					Math.round( ( Number( used ) / Number( limit ) ) * 100 )
				);
				let barClass = 'wpcy-quota-bar';
				if ( item.status === 'exhausted' ) {
					barClass += ' is-exhausted';
				} else if ( item.status === 'expired' ) {
					barClass += ' is-expired';
				}
				return (
					<div className="wpcy-quota">
						<span className={ barClass } aria-hidden="true">
							<span style={ { width: pct + '%' } } />
						</span>
						<span className="wpcy-quota-label">
							{ used }/{ limit }
						</span>
					</div>
				);
			},
		},
		{
			id: 'resets_at',
			label: __( '重置时间', 'wp-china-yes' ),
			render: ( { item } ) =>
				item.quota?.resets_at
					? formatStamp( item.quota.resets_at )
					: '—',
		},
		{
			id: 'action',
			label: __( '动作', 'wp-china-yes' ),
			render: ( { item } ) => {
				if ( item.status === 'active' ) {
					return '';
				}
				return (
					<Button
						variant="secondary"
						size="small"
						href={ goUrl( item.service ) }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( '获取', 'wp-china-yes' ) }
					</Button>
				);
			},
		},
	];
	const [ view, setView ] = useState( {
		...TABLE_VIEW,
		fields: fields.map( ( field ) => field.id ),
	} );

	if ( ! bound ) {
		return (
			<div className="wpcy-empty-state" data-testid="wpcy-quota-empty">
				{ __( '绑定后显示', 'wp-china-yes' ) }
			</div>
		);
	}

	return (
		<DataViews
			data={ rows }
			fields={ fields }
			view={ view }
			onChangeView={ setView }
			defaultLayouts={ { table: {} } }
			paginationInfo={ {
				totalItems: rows.length,
				totalPages: 1,
			} }
			search={ false }
			empty={
				<p className="wpcy-help">
					{ __( '暂无权益记录。', 'wp-china-yes' ) }
				</p>
			}
		/>
	);
}

function AppCard( { item, onOpen } ) {
	const name = localized( item.name );
	const description = localized( item.description );
	const summary = appQuota( item );
	let badge = null;
	if ( item.offline ) {
		badge = {
			className: 'wpcy-app-badge is-offline',
			label: __( '离线', 'wp-china-yes' ),
		};
	} else if ( item.tier !== 'free' && summary.status === 'expired' ) {
		badge = {
			className: 'wpcy-app-badge is-expired',
			label: __( '已到期', 'wp-china-yes' ),
		};
	} else if ( item.tier !== 'free' && summary.status !== 'active' ) {
		badge = {
			className: 'wpcy-app-badge is-get',
			label: __( '获取', 'wp-china-yes' ),
		};
	}

	return (
		<article className="wpcy-app-card">
			{ badge ? (
				<span className={ badge.className }>{ badge.label }</span>
			) : null }
			{ item.icon ? (
				<img
					className="wpcy-app-icon"
					src={ item.icon }
					alt=""
					width={ 20 }
					height={ 20 }
				/>
			) : (
				<div className="wpcy-app-icon" aria-hidden="true" />
			) }
			<h3>{ name }</h3>
			<p>{ description }</p>
			<p className="wpcy-card-actions">
				<Button variant="secondary" onClick={ () => onOpen( item ) }>
					{ name }
				</Button>
			</p>
		</article>
	);
}

function AppsGrid( { apps, bound, onOpen } ) {
	const fields = [
		{
			id: 'name',
			label: __( '名称', 'wp-china-yes' ),
			enableHiding: false,
			getValue: ( { item } ) => localized( item.name ),
			render: ( { item } ) => <AppCard item={ item } onOpen={ onOpen } />,
		},
	];
	const [ view, setView ] = useState( {
		...GRID_VIEW,
		fields: [ 'name' ],
	} );

	if ( ! bound ) {
		return (
			<div className="wpcy-empty-state" data-testid="wpcy-apps-empty">
				{ __( '绑定后显示', 'wp-china-yes' ) }
			</div>
		);
	}

	return (
		<DataViews
			data={ apps }
			fields={ fields }
			view={ view }
			onChangeView={ setView }
			defaultLayouts={ { grid: {} } }
			paginationInfo={ {
				totalItems: apps.length,
				totalPages: 1,
			} }
			search={ false }
			empty={
				<p className="wpcy-help">
					{ __( '暂无小工具。', 'wp-china-yes' ) }
				</p>
			}
		/>
	);
}

function AppSandbox( { app, onBack } ) {
	const iframeRef = useRef( null );
	const [ height, setHeight ] = useState( 360 );
	const hostOrigin = HOST_ORIGIN;

	useEffect( () => {
		const node = iframeRef.current;
		if ( ! node ) {
			return undefined;
		}
		const bootstrap =
			typeof window !== 'undefined' && window.wpcyAdmin
				? window.wpcyAdmin
				: {};
		const bridge = attachBridge( {
			iframe: node,
			manifest: app,
			hostOrigin,
			locale: document.documentElement.lang || 'zh_CN',
			pluginVersion: bootstrap.pluginVersion || '',
			context: bootstrap.siteContext || {},
			restFetch: ( request ) => apiFetch( request ),
			onResize: setHeight,
		} );
		return () => {
			bridge.destroy();
		};
	}, [ app, hostOrigin ] );

	const name = localized( app.name );

	return (
		<div className="wpcy-app-open">
			<div className="wpcy-app-return">
				<Button
					variant="tertiary"
					onClick={ onBack }
					aria-label={ __( '返回小工具列表', 'wp-china-yes' ) }
				>
					←
				</Button>
				<span>
					{ name }
					{ app.version ? ' · ' + app.version : '' }
				</span>
			</div>
			<iframe
				ref={ iframeRef }
				className="wpcy-app-frame"
				title={ name }
				src={ app.entry_url }
				sandbox={ IFRAME_SANDBOX }
				referrerPolicy={ IFRAME_REFERRERPOLICY }
				height={ height }
				data-testid="wpcy-app-iframe"
				style={ { height: height + 'px' } }
			/>
		</div>
	);
}

export default function Services() {
	const [ binding, setBinding ] = useState( {
		status: 'unbound',
		site_hash: null,
		bound_at: null,
	} );
	const [ quotas, setQuotas ] = useState( [] );
	const [ apps, setApps ] = useState( [] );
	const [ busy, setBusy ] = useState( false );
	const [ confirmUnbind, setConfirmUnbind ] = useState( false );
	const [ servicesNotice, setServicesNotice ] = useState( null );
	const [ appsUnavailable, setAppsUnavailable ] = useState( false );
	const [ openApp, setOpenApp ] = useState( null );
	const [ offerApp, setOfferApp ] = useState( null );
	const pollRef = useRef( 0 );

	const bound = binding.status === 'bound';

	function stopPoll() {
		if ( pollRef.current ) {
			window.clearInterval( pollRef.current );
			pollRef.current = 0;
		}
	}

	async function refreshBinding() {
		const next = await apiFetch( { path: BINDING_PATH } );
		setBinding(
			next || { status: 'unbound', site_hash: null, bound_at: null }
		);
		return next;
	}

	async function refreshQuotas() {
		try {
			const body = await apiFetch( { path: QUOTA_LIST_PATH } );
			const listKey = QUOTA + 's';
			const list = body && body[ listKey ];
			setQuotas( Array.isArray( list ) ? list : [] );
			setServicesNotice( null );
		} catch ( error ) {
			setServicesNotice( __( '暂时无法连接文派服务', 'wp-china-yes' ) );
			void error;
		}
	}

	async function refreshApps() {
		try {
			const body = await apiFetch( { path: APPS_PATH } );
			if ( Array.isArray( body ) ) {
				setApps( body );
				setAppsUnavailable( false );
				return;
			}
			const list = body && Array.isArray( body.apps ) ? body.apps : [];
			const status =
				body && typeof body.index_status === 'string'
					? body.index_status
					: 'ok';
			setApps( list );
			setAppsUnavailable( status !== 'ok' );
		} catch ( error ) {
			setApps( [] );
			setAppsUnavailable( true );
			void error;
		}
	}

	useEffect( () => {
		refreshBinding().catch( () => {
			setBinding( {
				status: 'unbound',
				site_hash: null,
				bound_at: null,
			} );
		} );
		refreshApps();
		return stopPoll;
	}, [] );

	useEffect( () => {
		if ( ! bound ) {
			setQuotas( [] );
			return;
		}
		refreshQuotas();
	}, [ bound ] );

	useEffect( () => {
		if ( binding.status !== 'pending' ) {
			stopPoll();
			return undefined;
		}
		pollRef.current = window.setInterval( () => {
			refreshBinding()
				.then( ( next ) => {
					if ( next && next.status === 'bound' ) {
						stopPoll();
					}
				} )
				.catch( () => undefined );
		}, 2000 );
		return stopPoll;
	}, [ binding.status ] );

	async function onBind() {
		setBusy( true );
		try {
			const next = await apiFetch( {
				path: BINDING_PATH + '/start',
				method: 'POST',
			} );
			setBinding( {
				status: next?.status || 'pending',
				site_hash: next?.site_hash || null,
				bound_at: next?.bound_at || null,
			} );
		} catch ( error ) {
			setServicesNotice(
				error?.message || __( '暂时无法连接文派服务', 'wp-china-yes' )
			);
		} finally {
			setBusy( false );
		}
	}

	async function onCancel() {
		stopPoll();
		setBusy( true );
		try {
			const next = await apiFetch( {
				path: BINDING_PATH,
				method: 'DELETE',
			} );
			setBinding(
				next || {
					status: 'unbound',
					site_hash: null,
					bound_at: null,
				}
			);
		} catch ( error ) {
			setBinding( {
				status: 'unbound',
				site_hash: null,
				bound_at: null,
			} );
			void error;
		} finally {
			setBusy( false );
		}
	}

	async function onUnbind() {
		setConfirmUnbind( false );
		setBusy( true );
		try {
			const next = await apiFetch( {
				path: BINDING_PATH,
				method: 'DELETE',
			} );
			setBinding(
				next || {
					status: 'revoked',
					site_hash: null,
					bound_at: null,
				}
			);
			setOpenApp( null );
		} catch ( error ) {
			void error;
		} finally {
			setBusy( false );
		}
	}

	function onOpenApp( app ) {
		if ( canOpenSandbox( app ) ) {
			setOfferApp( null );
			setOpenApp( app );
			return;
		}
		setOpenApp( null );
		setOfferApp( app );
	}

	const quotaRows = useMemo(
		() =>
			( quotas || [] ).map( ( row, index ) => ( {
				...row,
				id: ( row.id || row.service || 'row' ) + '-' + index,
			} ) ),
		[ quotas ]
	);

	if ( openApp ) {
		return (
			<PageShell title={ __( '文派服务', 'wp-china-yes' ) }>
				<AppSandbox
					app={ openApp }
					onBack={ () => setOpenApp( null ) }
				/>
			</PageShell>
		);
	}

	return (
		<PageShell title={ __( '文派服务', 'wp-china-yes' ) }>
			{ servicesNotice ? (
				<Notice status="warning" isDismissible={ false }>
					{ servicesNotice }
				</Notice>
			) : null }

			<section
				className="wpcy-section"
				aria-labelledby="wpcy-bind-heading"
			>
				<h2 id="wpcy-bind-heading">
					{ __( '站点绑定', 'wp-china-yes' ) }
				</h2>
				<BindingCard
					binding={ binding }
					busy={ busy }
					onBind={ onBind }
					onCancel={ onCancel }
					onUnbind={ () => setConfirmUnbind( true ) }
				/>
			</section>

			<section
				className="wpcy-section"
				aria-labelledby="wpcy-quota-heading"
			>
				<h2 id="wpcy-quota-heading">
					{ __( '权益与配额', 'wp-china-yes' ) }
				</h2>
				<QuotaTable rows={ quotaRows } bound={ bound } />
			</section>

			<section
				className="wpcy-section"
				aria-labelledby="wpcy-apps-heading"
			>
				<h2 id="wpcy-apps-heading">
					{ __( '小工具', 'wp-china-yes' ) }
				</h2>
				{ appsUnavailable ? (
					<Notice status="warning" isDismissible={ false }>
						{ __( '小工具目录暂时不可用', 'wp-china-yes' ) }
					</Notice>
				) : null }
				<AppsGrid apps={ apps } bound={ bound } onOpen={ onOpenApp } />
			</section>

			{ confirmUnbind ? (
				<Modal
					title={ __( '解除绑定', 'wp-china-yes' ) }
					onRequestClose={ () => setConfirmUnbind( false ) }
				>
					<p>
						{ __(
							'解除绑定后，受限免费服务与小工具将不可用。数据仍保存在本站。',
							'wp-china-yes'
						) }
					</p>
					<p className="wpcy-card-actions">
						<Button
							variant="primary"
							isDestructive
							onClick={ onUnbind }
						>
							{ __( '解除绑定', 'wp-china-yes' ) }
						</Button>
					</p>
				</Modal>
			) : null }

			{ offerApp ? (
				<Modal
					title={ localized( offerApp.name ) }
					onRequestClose={ () => setOfferApp( null ) }
				>
					<p>{ localized( offerApp.description ) }</p>
					<p>
						<Button
							variant="primary"
							href={ goUrl( offerApp.go_service || offerApp.id ) }
							target="_blank"
							rel="noopener noreferrer"
						>
							{ __( '获取', 'wp-china-yes' ) }
						</Button>
					</p>
				</Modal>
			) : null }
		</PageShell>
	);
}
