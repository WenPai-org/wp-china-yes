/**
 * First-run wizard: 5 steps (WZ-01 – WZ-05). Step 3 is the scene pack.
 */

/* eslint-disable jsx-a11y/label-has-associated-control -- prototype choice cards wrap radio + nested copy */

import { __, _n, sprintf } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useRef, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import PageShell from '../components/PageShell';
import { STORE_NAME } from '../store';
import { adminPageUrl, PAGES } from '../routing';
import { CardHead, Notice, Pill, Tile } from '@wenpai/wpcy-admin-ui';
import Btn from '../ui/Btn';
import Icon from '../ui/icons';
import { profileLabel, sceneCards } from '../ui/profiles';
import {
	doneRows,
	hasWoo,
	maskHash,
	packRows,
	packTitle,
	resourceSummary,
} from '../ui/wizard';

export const BINDING_POLL_MS = 3000;

const STEPS = [
	__( '站点场景', 'wp-china-yes' ),
	__( '已为你做的', 'wp-china-yes' ),
	__( '可能还需要', 'wp-china-yes' ),
	__( '绑定本站', 'wp-china-yes' ),
	__( '完成', 'wp-china-yes' ),
];

function stepClass( i, step ) {
	if ( i === step ) {
		return 'st cur';
	}
	if ( i < step ) {
		return 'st done';
	}
	return 'st';
}

function BadAlert( { children } ) {
	return (
		<Notice
			tone="bad"
			icon={ <Icon name="warn" size={ 18 } /> }
			wrap={ false }
			role="alert"
			className="wizard-alert"
		>
			{ children }
		</Notice>
	);
}

export default function Onboarding() {
	const { settings, plugins } = useSelect( ( select ) => {
		const store = select( STORE_NAME );
		return {
			settings: store.getSettings() || {},
			plugins: store.getSiteContext()?.active_plugins || [],
		};
	}, [] );
	const { patchSettings } = useDispatch( STORE_NAME );
	const confirmed = Boolean( settings.profile_confirmed_at );
	const [ step, setStep ] = useState( 0 );
	// WZ-01: no pre-selection unless a suggestion arrives or the scene was
	// already confirmed earlier.
	const [ profile, setProfile ] = useState(
		confirmed ? settings.profile || '' : ''
	);
	const [ suggestion, setSuggestion ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ saveError, setSaveError ] = useState( '' );
	const [ locked, setLocked ] = useState( false );
	// WZ-04: settings document as it was before the scene was applied, so
	// the summary counts what the PUT actually changed. Taken on every step-1
	// 「继续」: after going back and picking another scene, the count is
	// relative to the previous save, not to the document the wizard opened with.
	const [ before, setBefore ] = useState( null );
	const [ binding, setBinding ] = useState( {
		status: 'unbound',
		site_hash: null,
	} );
	const [ bindBusy, setBindBusy ] = useState( false );
	const [ bindError, setBindError ] = useState( false );
	const pollRef = useRef( 0 );

	const stopPoll = () => {
		if ( pollRef.current ) {
			window.clearInterval( pollRef.current );
			pollRef.current = 0;
		}
	};

	useEffect( () => {
		let cancelled = false;
		// L-7 ①: failure = no suggestion.
		apiFetch( { path: '/wpcy/v1/profile/suggest' } )
			.then( ( body ) => {
				if ( cancelled || ! body?.suggestion ) {
					return;
				}
				setSuggestion( body.suggestion );
				setProfile( ( cur ) => cur || body.suggestion );
			} )
			.catch( () => {} );
		apiFetch( { path: '/wpcy/v1/binding' } )
			.then( ( body ) => {
				if ( ! cancelled && body?.status ) {
					setBinding( body );
				}
			} )
			.catch( () => {} );
		return () => {
			cancelled = true;
			stopPoll();
		};
	}, [] );

	// WZ-03: only on step 4 and only while pending, GET /binding every 3 s
	// until bound or failed. A pending left over from an earlier visit must
	// not move the wizard from another step.
	useEffect( () => {
		if ( binding.status !== 'pending' || step !== 3 ) {
			stopPoll();
			return undefined;
		}
		pollRef.current = window.setInterval( () => {
			apiFetch( { path: '/wpcy/v1/binding' } )
				.then( ( next ) => {
					if ( ! next?.status || next.status === 'pending' ) {
						return;
					}
					stopPoll();
					if ( next.status === 'bound' ) {
						setBinding( next );
						setStep( 4 );
						return;
					}
					setBinding( next );
					setBindError( true );
				} )
				.catch( () => undefined );
		}, BINDING_POLL_MS );
		return stopPoll;
	}, [ binding.status, step ] );

	const cards = sceneCards();
	const current = profile || settings.profile || 'domestic';
	const bound = binding.status === 'bound';
	const pending = binding.status === 'pending';

	const onContinue = () => {
		setSaving( true );
		setSaveError( '' );
		setLocked( false );
		setBefore( settings );
		Promise.resolve( patchSettings( { profile } ) ).then( ( result ) => {
			setSaving( false );
			if ( result?.ok ) {
				setStep( 1 );
				return;
			}
			if ( result?.error?.locked ) {
				// 409 wpcy_settings_locked / 403 wpcy_settings_network_scope:
				// nothing was saved (the server records no profile_set), so
				// stay here and point back to the settings page.
				setLocked( true );
				return;
			}
			setSaveError(
				result?.error?.message ||
					__( '暂时无法保存设置，请稍后重试。', 'wp-china-yes' )
			);
		} );
	};

	const onBind = () => {
		setBindBusy( true );
		setBindError( false );
		apiFetch( { path: '/wpcy/v1/binding/start', method: 'POST' } )
			.then( ( next ) => {
				if ( next?.status === 'bound' ) {
					setBinding( next );
					setStep( 4 );
					return;
				}
				setBinding( {
					status: 'pending',
					site_hash: next?.site_hash || null,
				} );
			} )
			.catch( () => {
				setBindError( true );
			} )
			.finally( () => setBindBusy( false ) );
	};

	const onCancelBind = () => {
		stopPoll();
		setBindBusy( true );
		apiFetch( { path: '/wpcy/v1/binding', method: 'DELETE' } )
			.catch( () => undefined )
			.finally( () => {
				setBinding( { status: 'unbound', site_hash: null } );
				setBindBusy( false );
			} );
	};

	const summary = resourceSummary( before, settings );

	return (
		<PageShell showTabs={ false }>
			<main className="wizard">
				<div className="steps">
					{ STEPS.map( ( label, i ) => (
						<span key={ label } className="wpcy-step-wrap">
							{ i > 0 ? <div className="bar" /> : null }
							<div
								className={ stepClass( i, step ) }
								aria-current={ i === step ? 'step' : undefined }
							>
								<span className="n">
									{ i < step ? '✓' : i + 1 }
								</span>
								{ label }
							</div>
						</span>
					) ) }
				</div>
				{ step === 0 ? (
					<>
						<h1>{ __( '你的站点在哪里？', 'wp-china-yes' ) }</h1>
						<p className="lede">
							{ __(
								'我们据此决定哪些加速只作用于后台、哪些作用于访客。以后都可以在设置里改。',
								'wp-china-yes'
							) }
						</p>
						<div className="choice-cards cols-2">
							{ cards.map( ( card ) => (
								<label
									key={ card.id }
									htmlFor={ 'ob-p-' + card.id }
									className={
										'choice' +
										( profile === card.id ? ' is-on' : '' )
									}
								>
									<input
										id={ 'ob-p-' + card.id }
										type="radio"
										name="ob-p"
										checked={ profile === card.id }
										onChange={ () => setProfile( card.id ) }
									/>
									<span>
										<div className="t">{ card.title }</div>
										<div className="d">{ card.desc }</div>
									</span>
								</label>
							) ) }
						</div>
						{ suggestion ? (
							<Notice
								tone="info"
								wrap={ false }
								className="wizard-alert"
							>
								{ sprintf(
									/* translators: %s: scene */
									__(
										'根据服务器位置和你的浏览器，我们建议：%s。',
										'wp-china-yes'
									),
									profileLabel( suggestion )
								) }
							</Notice>
						) : null }
						{ saveError ? (
							<BadAlert>{ saveError }</BadAlert>
						) : null }
						{ /* Always mounted; only the text changes. */ }
						<p className="wizard-lock" role="status">
							{ locked
								? __(
										'网络管理员已锁定本站设置，向导里的选择没有保存。',
										'wp-china-yes'
								  )
								: '' }
						</p>
						{ locked ? (
							<div className="actions">
								<Btn
									variant="secondary"
									href={ adminPageUrl( PAGES.connect ) }
								>
									{ __( '返回设置', 'wp-china-yes' ) }
								</Btn>
							</div>
						) : null }
						<div className="actions">
							<Btn
								variant="primary"
								disabled={ ! profile || saving || locked }
								isBusy={ saving }
								onClick={ onContinue }
							>
								{ __( '继续', 'wp-china-yes' ) }
							</Btn>
							<Btn
								variant="ghost"
								href={ adminPageUrl( PAGES.overview ) }
							>
								{ __( '跳过，稍后设置', 'wp-china-yes' ) }
							</Btn>
						</div>
					</>
				) : null }
				{ step === 1 ? (
					<>
						<h1>
							{ sprintf(
								/* translators: %s: scene */
								__( '已按「%s」配置好', 'wp-china-yes' ),
								profileLabel( current )
							) }
						</h1>
						<p className="lede">
							{ __(
								'这些都是默认组合，以后可以在设置里逐项改。',
								'wp-china-yes'
							) }
						</p>
						<article className="card">
							<div className="rows">
								{ doneRows( current ).map( ( row ) => (
									<div key={ row.key }>
										<Tile tone="ok">
											<Icon name="check" size={ 20 } />
										</Tile>
										<div>
											<div className="t">{ row.t }</div>
											<div className="d">{ row.d }</div>
										</div>
									</div>
								) ) }
							</div>
						</article>
						<div className="actions">
							<Btn
								variant="primary"
								onClick={ () => setStep( 2 ) }
							>
								{ __( '继续', 'wp-china-yes' ) }
							</Btn>
							<Btn variant="ghost" onClick={ () => setStep( 0 ) }>
								{ __( '返回', 'wp-china-yes' ) }
							</Btn>
						</div>
					</>
				) : null }
				{ step === 2 ? (
					<>
						<h1>{ packTitle( current ) }</h1>
						<p className="lede">
							{ __(
								'这些通过供应商提供，按需了解；不装也不影响已接通的功能。',
								'wp-china-yes'
							) }
						</p>
						<article className="card">
							<div className="rows">
								{ packRows( current, hasWoo( plugins ) ).map(
									( row ) => (
										<div key={ row.id }>
											<Tile tone="accent">
												<Icon
													name={ row.icon }
													size={ 20 }
												/>
											</Tile>
											<div>
												<div className="t">
													{ row.t }
												</div>
												<div className="d">
													{ row.d }
												</div>
											</div>
											<Btn
												variant="ghost"
												className="r"
												href={ adminPageUrl(
													PAGES.services
												) }
												target="_blank"
												rel="noopener noreferrer"
											>
												{ __( '了解', 'wp-china-yes' ) }{ ' ' }
												<Icon
													name="arrow"
													size={ 16 }
												/>
											</Btn>
										</div>
									)
								) }
							</div>
						</article>
						<div className="actions">
							<Btn
								variant="primary"
								onClick={ () => setStep( 3 ) }
							>
								{ __( '继续', 'wp-china-yes' ) }
							</Btn>
							<Btn variant="ghost" onClick={ () => setStep( 3 ) }>
								{ __( '跳过，以后再说', 'wp-china-yes' ) }
							</Btn>
						</div>
					</>
				) : null }
				{ step === 3 ? (
					<>
						{ /* One live region for step 4 (admin-ui-spec §8):
						     mounted with the step, only its text changes. */ }
						<p className="screen-reader-text" role="status">
							{ bindStatusText( binding.status ) }
						</p>
						{ bound ? (
							<BoundState
								siteHash={ binding.site_hash }
								onContinue={ () => setStep( 4 ) }
							/>
						) : (
							<>
								<h1>
									{ __( '要绑定本站吗？', 'wp-china-yes' ) }
								</h1>
								<p className="lede">
									{ __(
										'绑定后可使用文派服务与小工具。绑定是匿名的，不需要注册账号；数据保存在本站，随时可解除。',
										'wp-china-yes'
									) }
								</p>
								{ pending ? (
									<BindingWait
										busy={ bindBusy }
										onCancel={ onCancelBind }
									/>
								) : (
									<>
										<BindIntro />
										{ bindError ? (
											<BadAlert>
												{ __(
													'暂时无法完成站点绑定，请稍后重试。',
													'wp-china-yes'
												) }
											</BadAlert>
										) : null }
										<div className="actions">
											<Btn
												variant="primary"
												disabled={ bindBusy }
												isBusy={ bindBusy }
												onClick={ onBind }
											>
												{ __(
													'绑定本站',
													'wp-china-yes'
												) }
											</Btn>
											<Btn
												variant="ghost"
												onClick={ () => setStep( 4 ) }
											>
												{ __(
													'暂不，稍后再说',
													'wp-china-yes'
												) }
											</Btn>
										</div>
									</>
								) }
							</>
						) }
					</>
				) : null }
				{ step === 4 ? (
					<>
						<h1>{ __( '一切就绪', 'wp-china-yes' ) }</h1>
						<p className="lede">
							{ __(
								'文派叶子已在后台运行。你随时可以回到概览查看它为你处理了什么。',
								'wp-china-yes'
							) }
						</p>
						<div className="wizard-done">
							<div
								className={
									summary.same ? 'wpcy-grid-2' : 'wpcy-grid-3'
								}
							>
								{ /* WZ-04 same scene: nothing changed, so no 「更新」 card. */ }
								{ summary.same ? null : (
									<article className="card stat-a">
										<div className="k">
											<Icon name="globe" size={ 16 } />
											{ __( '更新', 'wp-china-yes' ) }
										</div>
										<div className="v">
											{ ( settings.connectivity
												?.wordpress_org || 'auto' ) ===
											'off'
												? __( '直连', 'wp-china-yes' )
												: __(
														'国内镜像',
														'wp-china-yes'
												  ) }
										</div>
										<div className="d">WordPress.org</div>
									</article>
								) }
								<article className="card stat-a">
									<div className="k">
										<Icon name="bolt" size={ 16 } />
										{ __( '已调整', 'wp-china-yes' ) }
									</div>
									<div
										className="v"
										data-testid="wpcy-wz-count"
									>
										{ sprintf(
											/* translators: %d: number of settings the scene preset changed */
											_n(
												'%d 项',
												'%d 项',
												summary.count,
												'wp-china-yes'
											),
											summary.count
										) }
									</div>
									{ summary.same ? (
										<div
											className="d d-same"
											data-testid="wpcy-wz-same"
										>
											{ sprintf(
												/* translators: %s: scene */
												__(
													'当前已是「%s」，没有改动。',
													'wp-china-yes'
												),
												profileLabel( current )
											) }
										</div>
									) : (
										<div className="d">
											{ sprintf(
												/* translators: %s: scene */
												__(
													'按「%s」预设',
													'wp-china-yes'
												),
												profileLabel( current )
											) }
										</div>
									) }
								</article>
								<article className="card stat-a">
									<div className="k">
										<Icon name="link" size={ 16 } />
										{ __( '服务', 'wp-china-yes' ) }
									</div>
									<div className="v">
										{ bound
											? __( '已绑定', 'wp-china-yes' )
											: __( '未绑定', 'wp-china-yes' ) }
									</div>
									<div className="d">
										{ bound
											? sprintf(
													/* translators: %s: masked site hash */
													__(
														'站点标识 %s',
														'wp-china-yes'
													),
													maskHash(
														binding.site_hash
													)
											  )
											: __(
													'稍后可在服务页绑定',
													'wp-china-yes'
											  ) }
									</div>
								</article>
							</div>
							<div className="actions">
								<Btn
									variant="primary"
									href={ adminPageUrl( PAGES.overview ) }
								>
									{ __( '进入概览', 'wp-china-yes' ) }
								</Btn>
							</div>
						</div>
					</>
				) : null }
			</main>
		</PageShell>
	);
}

function BindIntro() {
	return (
		<article className="card">
			<div className="rows">
				<div>
					<Tile tone="accent">
						<Icon name="grid" size={ 20 } />
					</Tile>
					<div>
						<div className="t">
							{ __( '小工具', 'wp-china-yes' ) }
						</div>
						<div className="d">
							{ __(
								'连接测速、字体预览、通知设置等，在沙箱中运行',
								'wp-china-yes'
							) }
						</div>
					</div>
				</div>
				<div>
					<Tile tone="accent">
						<Icon name="link" size={ 20 } />
					</Tile>
					<div>
						<div className="t">
							{ __( '文派服务', 'wp-china-yes' ) }
						</div>
						<div className="d">
							{ __(
								'Windfonts 中文字体、面向跨境店的支付与通知服务',
								'wp-china-yes'
							) }
						</div>
					</div>
				</div>
			</div>
		</article>
	);
}

/**
 * Screen-reader text for the step 4 status region.
 *
 * @param {string} status Binding status.
 * @return {string} Text.
 */
function bindStatusText( status ) {
	if ( status === 'pending' ) {
		return __( '正在绑定本站', 'wp-china-yes' );
	}
	if ( status === 'bound' ) {
		return __( '已绑定', 'wp-china-yes' );
	}
	return '';
}

/**
 * Step 4 when the site is already bound: show the bound state and let the
 * admin continue instead of asking 「要绑定本站吗？」 again.
 *
 * @param {Object}      props
 * @param {string|null} props.siteHash
 * @param {Function}    props.onContinue
 */
function BoundState( { siteHash, onContinue } ) {
	return (
		<>
			<h1>{ __( '本站已绑定', 'wp-china-yes' ) }</h1>
			<article className="card">
				<CardHead
					tile={
						<Tile tone="ok">
							<Icon name="link" size={ 20 } />
						</Tile>
					}
					title={ __( '本站已绑定', 'wp-china-yes' ) }
					sub={
						siteHash
							? sprintf(
									/* translators: %s: masked site hash */
									__( '站点标识 %s', 'wp-china-yes' ),
									maskHash( siteHash )
							  )
							: null
					}
					extra={
						<Pill state="ok">
							{ __( '已绑定', 'wp-china-yes' ) }
						</Pill>
					}
				/>
			</article>
			<div className="actions">
				<Btn variant="primary" onClick={ onContinue }>
					{ __( '继续', 'wp-china-yes' ) }
				</Btn>
			</div>
		</>
	);
}

/**
 * WZ-03 waiting state: SV-02 copy, one secondary 「取消」.
 *
 * @param {Object}   props
 * @param {boolean}  props.busy
 * @param {Function} props.onCancel
 */
function BindingWait( { busy, onCancel } ) {
	return (
		<>
			<article className="card">
				<CardHead
					tile={
						<Tile tone="accent">
							<Icon name="link" size={ 20 } />
						</Tile>
					}
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
			</article>
			<div className="actions">
				<Btn variant="secondary" disabled={ busy } onClick={ onCancel }>
					{ __( '取消', 'wp-china-yes' ) }
				</Btn>
			</div>
		</>
	);
}
