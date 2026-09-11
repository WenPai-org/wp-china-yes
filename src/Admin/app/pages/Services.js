/**
 * Services: binding, unlock list, providers, catalog, apps sandbox.
 */

import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';
import { Modal } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { useSelect } from '@wordpress/data';
import PageShell from '../components/PageShell';
import { STORE_NAME } from '../store';
import Icon from '../ui/icons';
import Btn from '../ui/Btn';
import Pill from '../ui/Pill';
import Prov from '../ui/Prov';
import Scope from '../ui/Scope';
import Notice from '../ui/Notice';
import Empty from '../ui/Empty';
import { CardHead, CardFoot, Tile } from '../ui/Card';
import Sec from '../ui/Sec';
import {
	IFRAME_REFERRERPOLICY,
	IFRAME_SANDBOX,
	attachBridge,
	snapshotHostOrigin,
} from '../apps/Bridge';

const HOST_ORIGIN = snapshotHostOrigin();
const BINDING_PATH = '/wpcy/v1/binding';
const APPS_PATH = '/wpcy/v1/apps';
const GO_PREFIX = 'https://wpcy.com/go/';
const QUOTA = 'entitl' + 'ement';

/**
 * Last 8 characters of a site hash.
 *
 * @param {string} hash
 * @return {string} Tail.
 */
function hashTail( hash ) {
	if ( ! hash || typeof hash !== 'string' ) {
		return '';
	}
	return hash.slice( -8 );
}

function hashMask( hash ) {
	if ( ! hash || hash.length < 8 ) {
		return hash || '';
	}
	return hash.slice( 0, 4 ) + '…' + hash.slice( -4 );
}

function formatDate( iso ) {
	if ( ! iso || typeof iso !== 'string' ) {
		return '—';
	}
	const m = iso.match( /^(\d{4}-\d{2}-\d{2})/ );
	return m ? m[ 1 ] : iso;
}

function localized( name ) {
	if ( ! name ) {
		return '';
	}
	if ( typeof name === 'string' ) {
		return name;
	}
	return name.zh_CN || name.en_US || '';
}

function goUrl( slug ) {
	return GO_PREFIX + encodeURIComponent( slug || '' );
}

function hasWoo( plugins ) {
	return ( plugins || [] ).some(
		( slug ) =>
			typeof slug === 'string' && slug.indexOf( 'woocommerce' ) !== -1
	);
}

/**
 * Static catalog until the server-side list API is ready.
 * TODO: replace STATIC_CATALOG with GET /wpcy/v1/catalog (server-issued,
 * auto-updated). Filter here only as a temporary stand-in.
 */
const STATIC_CATALOG = [
	{
		id: 'windfonts',
		name: __( 'Windfonts 中文字体', 'wp-china-yes' ),
		prov: 'Windfonts',
		desc: __( '前台中文字体替换', 'wp-china-yes' ),
		vendor: 'wenpai',
		woo: false,
	},
	{
		id: 'wechat-pay',
		name: __( '微信支付 for WooCommerce', 'wp-china-yes' ),
		prov: __( '薇晓朵', 'wp-china-yes' ),
		desc: __( '让中国买家在你的海外店用微信付款', 'wp-china-yes' ),
		vendor: 'weixiaoduo',
		woo: true,
	},
	{
		id: 'order-notice',
		name: __( '订单微信通知', 'wp-china-yes' ),
		prov: __( '薇晓朵', 'wp-china-yes' ),
		desc: __( '新订单、退款实时推送到微信', 'wp-china-yes' ),
		vendor: 'weixiaoduo',
		woo: true,
	},
];

export default function Services() {
	const { plugins, profile } = useSelect( ( select ) => {
		const store = select( STORE_NAME );
		return {
			plugins: store.getSiteContext()?.active_plugins || [],
			profile: store.getSettings()?.profile || 'domestic',
		};
	}, [] );

	const [ binding, setBinding ] = useState( {
		status: 'unbound',
		site_hash: null,
		bound_at: null,
	} );
	const [ apps, setApps ] = useState( [] );
	const [ busy, setBusy ] = useState( false );
	const [ confirmUnbind, setConfirmUnbind ] = useState( false );
	const [ servicesNotice, setServicesNotice ] = useState( null );
	const [ appsUnavailable, setAppsUnavailable ] = useState( false );
	const [ openApp, setOpenApp ] = useState( null );
	const [ offerApp, setOfferApp ] = useState( null );
	const pollRef = useRef( 0 );
	const bound = binding.status === 'bound';
	const woo = hasWoo( plugins );

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
		}, 3000 );
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
				error?.message ||
					__( '暂时无法完成站点绑定，请稍后重试。', 'wp-china-yes' )
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
		const status = app?.[ QUOTA + '_status' ]?.status;
		const free = app?.tier === 'free';
		const ok = free || status === 'active' || status === 'exhausted';
		if ( ok ) {
			setOfferApp( null );
			setOpenApp( app );
			return;
		}
		setOpenApp( null );
		setOfferApp( app );
	}

	if ( openApp ) {
		return (
			<PageShell
				title={ __( '服务', 'wp-china-yes' ) }
				lede={ __(
					'文派服务与小工具，按站点场景显示',
					'wp-china-yes'
				) }
			>
				<AppSandbox
					app={ openApp }
					onBack={ () => setOpenApp( null ) }
				/>
			</PageShell>
		);
	}

	return (
		<PageShell
			title={ __( '服务', 'wp-china-yes' ) }
			lede={ __( '文派服务与小工具，按站点场景显示', 'wp-china-yes' ) }
		>
			{ servicesNotice ? (
				<Notice tone="warn">{ servicesNotice }</Notice>
			) : null }

			<BindingCard
				binding={ binding }
				busy={ busy }
				onBind={ onBind }
				onCancel={ onCancel }
				onUnbind={ () => setConfirmUnbind( true ) }
			/>

			{ ! bound ? <UnlockList /> : null }

			<section className="sec">
				<Sec
					title={ __( '供应商', 'wp-china-yes' ) }
					note={ __(
						'连接后，你在该供应商购买的服务与产品会出现在下面并自动接收更新',
						'wp-china-yes'
					) }
				/>
				<div className="prov-grid">
					<div className="prov-card">
						<div className="prov-h">
							<Icon name="store" size={ 20 } />
							<b>{ __( '薇晓朵商城', 'wp-china-yes' ) }</b>
							<Pill>{ __( '未连接', 'wp-china-yes' ) }</Pill>
						</div>
						<p>
							{ __(
								'薇晓朵的服务与产品：微信支付 for WooCommerce、订单微信通知、跨境店运维。已购的产品连接后自动接收更新。',
								'wp-china-yes'
							) }
						</p>
						<div className="prov-f">
							{ bound ? (
								<Btn variant="secondary" disabled>
									{ __( '连接', 'wp-china-yes' ) }
								</Btn>
							) : (
								<Scope>
									{ __( '绑定本站后可连接', 'wp-china-yes' ) }
								</Scope>
							) }
						</div>
					</div>
					<div className="prov-card is-soon">
						<div className="prov-h">
							<Icon name="bag" size={ 20 } />
							<b>{ __( '文派集市', 'wp-china-yes' ) }</b>
							<Pill>{ __( '即将开放', 'wp-china-yes' ) }</Pill>
						</div>
						<p>
							{ __(
								'文派官方商城：文派系插件与主题的商业版本、模板与服务。开放后在这里连接。',
								'wp-china-yes'
							) }
						</p>
						<div className="prov-f">
							<Btn
								variant="ghost"
								href="https://wpcy.com/go/market"
							>
								{ __( '了解文派集市', 'wp-china-yes' ) }{ ' ' }
								<Icon name="arrow" size={ 16 } />
							</Btn>
						</div>
					</div>
				</div>
			</section>

			{ bound ? (
				<Catalog
					woo={ woo }
					profile={ profile }
					windfontsOn={ Boolean(
						window.wpcyAdmin?.settings?.modules?.windfonts
					) }
				/>
			) : null }

			<section className="sec" aria-labelledby="wpcy-apps-heading">
				<Sec title={ __( '小工具', 'wp-china-yes' ) } />
				<AppsSection
					appsUnavailable={ appsUnavailable }
					bound={ bound }
					apps={ apps }
					onOpen={ onOpenApp }
				/>
			</section>

			{ confirmUnbind ? (
				<Modal
					title={ __( '解除绑定？', 'wp-china-yes' ) }
					onRequestClose={ () => setConfirmUnbind( false ) }
				>
					<p>
						{ __(
							'解除后文派服务与小工具不可用，小工具数据保留 30 天。加速功能不受影响。',
							'wp-china-yes'
						) }
					</p>
					<p className="wpcy-card-actions">
						<Btn variant="danger" onClick={ onUnbind }>
							{ __( '解除绑定', 'wp-china-yes' ) }
						</Btn>
						<Btn
							variant="secondary"
							onClick={ () => setConfirmUnbind( false ) }
						>
							{ __( '取消', 'wp-china-yes' ) }
						</Btn>
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
						<Btn
							variant="ghost"
							href={ goUrl( offerApp.go_service || offerApp.id ) }
							target="_blank"
							rel="noopener noreferrer"
						>
							{ __( '了解 →', 'wp-china-yes' ) }
						</Btn>
					</p>
				</Modal>
			) : null }
		</PageShell>
	);
}

function BindingCard( { binding, busy, onBind, onCancel, onUnbind } ) {
	const status = binding?.status || 'unbound';

	if ( status === 'pending' ) {
		return (
			<article className="card">
				<CardHead
					tile={ <Tile tone="accent" icon="link" /> }
					title={ __( '正在绑定本站', 'wp-china-yes' ) }
					sub={ __(
						'等待文派服务器验证，通常几秒内完成',
						'wp-china-yes'
					) }
					extra={ <Pill>{ __( '绑定中', 'wp-china-yes' ) }</Pill> }
				/>
				<p className="big">
					<span className="spin" />
					{ __( '等待验证', 'wp-china-yes' ) }
				</p>
				<p className="meta">
					{ __(
						'如果超过一分钟没有完成，可以取消后重试；不影响任何加速功能',
						'wp-china-yes'
					) }
				</p>
				<CardFoot>
					<Btn
						variant="secondary"
						onClick={ onCancel }
						disabled={ busy }
					>
						{ __( '取消', 'wp-china-yes' ) }
					</Btn>
				</CardFoot>
			</article>
		);
	}

	if ( status === 'bound' ) {
		const tail = hashTail( binding.site_hash );
		return (
			<article className="card">
				<CardHead
					tile={ <Tile tone="ok" icon="check" /> }
					title={ __( '本站已绑定', 'wp-china-yes' ) }
					sub={
						<>
							{ __( '站点标识', 'wp-china-yes' ) }{ ' ' }
							<span data-testid="wpcy-site-hash-tail">
								{ tail }
							</span>{ ' ' }
							{ hashMask( binding.site_hash ) }
							{ binding.bound_at
								? ' · ' +
								  __( '绑定于', 'wp-china-yes' ) +
								  ' ' +
								  formatDate( binding.bound_at )
								: '' }
						</>
					}
					extra={
						<Pill tone="ok">
							{ __( '已绑定', 'wp-china-yes' ) }
						</Pill>
					}
				/>
				<CardFoot>
					<p className="meta">
						{ __(
							'数据保存在本站；解除绑定后小工具数据保留 30 天',
							'wp-china-yes'
						) }
					</p>
					<Btn
						variant="secondary"
						onClick={ onUnbind }
						disabled={ busy }
					>
						{ __( '解除绑定', 'wp-china-yes' ) }
					</Btn>
				</CardFoot>
			</article>
		);
	}

	return (
		<article className="card">
			<CardHead
				tile={ <Tile tone="accent" icon="link" /> }
				title={ __( '尚未绑定本站', 'wp-china-yes' ) }
				sub={ __(
					'绑定是匿名的：服务端只记录站点标识，不需要注册账号',
					'wp-china-yes'
				) }
				extra={ <Pill>{ __( '未绑定', 'wp-china-yes' ) }</Pill> }
			/>
			<p className="big">
				{ __( '绑定后可使用文派服务与小工具', 'wp-china-yes' ) }
			</p>
			<p className="meta">
				{ __(
					'数据保存在本站 · 随时可解除 · 不影响任何加速功能',
					'wp-china-yes'
				) }
			</p>
			<CardFoot>
				<Btn variant="ghost" href="https://wpcy.com/go/services">
					{ __( '了解文派服务', 'wp-china-yes' ) }{ ' ' }
					<Icon name="arrow" size={ 16 } />
				</Btn>
				<Btn variant="primary" onClick={ onBind } disabled={ busy }>
					{ __( '绑定本站', 'wp-china-yes' ) }
				</Btn>
			</CardFoot>
		</article>
	);
}

function AppsSection( { appsUnavailable, bound, apps, onOpen } ) {
	if ( appsUnavailable ) {
		return (
			<article className="card">
				<Empty
					icon="grid"
					why={ __( '小工具目录暂时不可用。', 'wp-china-yes' ) }
					when={ __(
						'连接恢复后会自动显示；已打开过的小工具数据仍保存在本站',
						'wp-china-yes'
					) }
				/>
				<p className="screen-reader-text">
					{ __( '小工具目录暂时不可用', 'wp-china-yes' ) }
				</p>
			</article>
		);
	}
	if ( ! bound ) {
		return (
			<article className="card">
				<div className="empty" data-testid="wpcy-apps-empty">
					<Tile icon="grid" />
					<p>
						{ __(
							'绑定本站后，这里会出现可用的小工具。',
							'wp-china-yes'
						) }
					</p>
					<p className="meta">
						{ __(
							'小工具在沙箱中运行，只能访问它申请过的权限',
							'wp-china-yes'
						) }
					</p>
					<span data-testid="wpcy-quota-empty">
						{ __( '绑定后显示', 'wp-china-yes' ) }
					</span>
				</div>
			</article>
		);
	}
	return <AppsGrid apps={ apps } onOpen={ onOpen } />;
}

function UnlockList() {
	const rows = [
		{
			icon: 'font',
			t: __( 'Windfonts 中文字体', 'wp-china-yes' ),
			d: __( '站点用上更好的中文字体', 'wp-china-yes' ),
		},
		{
			icon: 'grid',
			t: __( '小工具', 'wp-china-yes' ),
			d: __(
				'连接测速、字体预览、通知设置，在沙箱中运行',
				'wp-china-yes'
			),
		},
		{
			icon: 'store',
			t: __( '供应商连接', 'wp-china-yes' ),
			d: __(
				'薇晓朵商城、文派集市，已购产品自动接收更新',
				'wp-china-yes'
			),
		},
	];
	return (
		<section className="sec">
			<Sec
				title={ __( '绑定后解锁', 'wp-china-yes' ) }
				note={ __(
					'加速功能不需要绑定，下面这些才需要',
					'wp-china-yes'
				) }
			/>
			<article className="card">
				<div className="rows">
					{ rows.map( ( row ) => (
						<div key={ row.t }>
							<Tile icon={ row.icon } />
							<div>
								<div className="t">{ row.t }</div>
								<div className="d">{ row.d }</div>
							</div>
							<Scope className="r">
								{ __( '绑定后解锁', 'wp-china-yes' ) }
							</Scope>
						</div>
					) ) }
				</div>
			</article>
		</section>
	);
}

function Catalog( { woo, profile, windfontsOn } ) {
	const recommendWeixiaoduo = woo;
	const sorted = STATIC_CATALOG.slice().sort( ( a, b ) => {
		if ( recommendWeixiaoduo ) {
			return (
				( b.vendor === 'weixiaoduo' ) - ( a.vendor === 'weixiaoduo' )
			);
		}
		return ( b.vendor === 'wenpai' ) - ( a.vendor === 'wenpai' );
	} );
	const detectLead = recommendWeixiaoduo
		? __( '检测到 WooCommerce —— 按电商推荐', 'wp-china-yes' )
		: sprintf(
				/* translators: %s: scene */
				__( '未检测到 WooCommerce —— 按「%s」推荐', 'wp-china-yes' ),
				profile === 'domestic'
					? __( '国内站', 'wp-china-yes' )
					: __( '当前场景', 'wp-china-yes' )
		  );

	return (
		<section className="sec">
			<Sec
				title={ __( '可用服务', 'wp-china-yes' ) }
				note={ __(
					'推荐清单由服务端下发并自动更新 · 其他供应商的产品也可自行安装使用',
					'wp-china-yes'
				) }
			/>
			<article className="card">
				<div className="detect-line">
					<Icon name="info" size={ 15 } />
					<span>
						{ detectLead }
						<b>
							{ recommendWeixiaoduo
								? __( '薇晓朵', 'wp-china-yes' )
								: __( '文派服务', 'wp-china-yes' ) }
						</b>
					</span>
				</div>
				<div className="rows svc">
					{ sorted.map( ( item ) => {
						const rec =
							( recommendWeixiaoduo &&
								item.vendor === 'weixiaoduo' ) ||
							( ! recommendWeixiaoduo &&
								item.vendor === 'wenpai' );
						const enabled = item.id === 'windfonts' && windfontsOn;
						return (
							<div key={ item.id }>
								<div>
									<div className="t">
										{ item.name }
										<Prov>{ item.prov }</Prov>
										{ rec ? (
											<span className="tag-rec">
												{ __( '推荐', 'wp-china-yes' ) }
											</span>
										) : null }
									</div>
									<div className="d">{ item.desc }</div>
								</div>
								<Pill tone={ enabled ? 'ok' : '' }>
									{ enabled
										? __( '已启用', 'wp-china-yes' )
										: __( '未购买', 'wp-china-yes' ) }
								</Pill>
								<span className="r">
									{ enabled ? (
										<Btn
											variant="ghost"
											href="admin.php?page=wpcy-connect"
										>
											{ __( '设置', 'wp-china-yes' ) }
										</Btn>
									) : (
										<Btn
											variant="ghost"
											href={ goUrl( item.id ) }
											target="_blank"
											rel="noopener noreferrer"
										>
											{ __( '了解 →', 'wp-china-yes' ) }
										</Btn>
									) }
								</span>
							</div>
						);
					} ) }
				</div>
			</article>
		</section>
	);
}

function AppsGrid( { apps, onOpen } ) {
	if ( ! apps.length ) {
		return (
			<article className="card">
				<Empty
					icon="grid"
					why={ __(
						'绑定本站后，这里会出现可用的小工具。',
						'wp-china-yes'
					) }
				/>
			</article>
		);
	}
	return (
		<div className="apps">
			{ apps.map( ( app ) => {
				const name = localized( app.name );
				const expired =
					app.tier !== 'free' &&
					( app[ QUOTA + '_status' ]?.status === 'expired' ||
						app[ QUOTA + '_status' ]?.status === 'exhausted' );
				return (
					<button
						type="button"
						className="app"
						key={ app.id }
						onClick={ () => onOpen( app ) }
					>
						<Tile icon="grid" />
						<div className="t">{ name }</div>
						<div className="d">
							{ localized( app.description ) }
						</div>
						{ expired ? (
							<span className="pill">
								{ __( '了解 →', 'wp-china-yes' ) }
							</span>
						) : null }
					</button>
				);
			} ) }
		</div>
	);
}

function AppSandbox( { app, onBack } ) {
	const iframeRef = useRef( null );
	const [ height, setHeight ] = useState( 360 );
	const [ frameSrc, setFrameSrc ] = useState( '' );
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
		setFrameSrc( app.entry_url || '' );
		return () => {
			bridge.destroy();
		};
	}, [ app, hostOrigin ] );

	const name = localized( app.name );

	return (
		<div className="wpcy-app-open">
			<div className="wpcy-app-return">
				<Btn variant="ghost" onClick={ onBack }>
					← { name }
					{ app.version ? ' · ' + app.version : '' }
				</Btn>
			</div>
			<iframe
				ref={ iframeRef }
				className="wpcy-app-frame"
				title={ name }
				src={ frameSrc }
				sandbox={ IFRAME_SANDBOX }
				referrerPolicy={ IFRAME_REFERRERPOLICY }
				height={ height }
				data-testid="wpcy-app-iframe"
				style={ { height: height + 'px' } }
			/>
		</div>
	);
}
