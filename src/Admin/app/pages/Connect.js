/**
 * Settings: scene cards, simple / advanced, instant save (CO-01–CO-13),
 * subsite read-only and network-admin views (CO-06).
 */

/* eslint-disable jsx-a11y/label-has-associated-control -- prototype choice/opt labels wrap radio + nested copy */

import { __, sprintf } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useRef, useState } from '@wordpress/element';
import { Modal } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import PageShell from '../components/PageShell';
import {
	leafPaths,
	mergeSettings,
	NETWORK_SETTINGS_PATH,
	STORE_NAME,
} from '../store';
import { adminPageUrl, PAGES } from '../routing';
import { LoadError, Notice, Pill, Scope, Toggle } from '@wenpai/wpcy-admin-ui';
import Icon from '../ui/icons';
import Btn from '../ui/Btn';
import { Skel } from '../ui/Skeleton';
import {
	customizedKeys,
	customKeyLabel,
	profileLabel,
	sceneCards,
	sceneDefaults,
	scopeWord,
} from '../ui/profiles';

const META_KEY = 'wpcy_settings_view';
const FIVE = [ 'google_fonts', 'google_ajax', 'cdnjs', 'jsdelivr', 'emoji' ];
const ASSET_LABEL = {
	google_fonts: 'Google Fonts',
	google_ajax: 'Google Ajax',
	cdnjs: 'CDNJS',
	jsdelivr: 'jsDelivr',
	emoji: 'Emoji',
};

/**
 * @param {Object} settings
 * @return {Object} Connectivity slice.
 */
function conn( settings ) {
	return settings?.connectivity || {};
}

/**
 * Single avatar value (v2.3 site-wide enum; tolerate the legacy split object).
 *
 * @param {Object} settings
 * @return {string} Avatar enum.
 */
function avatarOf( settings ) {
	const avatar = conn( settings ).avatar || 'cravatar_cn';
	return typeof avatar === 'string' ? avatar : avatar.admin || 'cravatar_cn';
}

/**
 * Visible switch label (§8: switches carry 已开启 / 未开启).
 *
 * @param {boolean} on
 * @return {string} Label.
 */
function onOff( on ) {
	return on ? __( '已开启', 'wp-china-yes' ) : __( '未开启', 'wp-china-yes' );
}

export default function Connect() {
	const {
		settings,
		saving,
		capabilities,
		binding,
		bindingError,
		loadError,
		unsaved,
	} = useSelect( ( select ) => {
		const store = select( STORE_NAME );
		return {
			settings: store.getSettings() || {},
			saving: store.isSaving(),
			capabilities: store.getCapabilities() || {},
			binding: store.getBinding(),
			bindingError: store.getBindingError(),
			loadError: store.getSettingsError(),
			unsaved: store.getUnsaved(),
		};
	}, [] );
	const { patchSettings, fetchBinding, fetchNetworkSettings } =
		useDispatch( STORE_NAME );
	const network = Boolean( capabilities.network_admin );
	const networkLocked = ! network && Boolean( capabilities.network_locked );

	const [ view, setView ] = useState( 'simple' );
	const [ suggest, setSuggest ] = useState( null );
	const [ pendingProfile, setPendingProfile ] = useState( null );
	const [ switching, setSwitching ] = useState( false );
	const [ savedFlash, setSavedFlash ] = useState( {} );
	const [ cardError, setCardError ] = useState( null );
	const [ networkLoaded, setNetworkLoaded ] = useState( ! network );
	const flashTimer = useRef( 0 );

	useEffect( () => {
		let cancelled = false;
		if ( ! networkLocked ) {
			// L-7 ②: read failure = simple, no message.
			apiFetch( { path: '/wp/v2/users/me?context=edit' } )
				.then( ( me ) => {
					if (
						! cancelled &&
						me?.meta?.[ META_KEY ] === 'advanced'
					) {
						setView( 'advanced' );
					}
				} )
				.catch( () => {} );
		}
		if ( network ) {
			Promise.resolve( fetchNetworkSettings() ).finally( () => {
				if ( ! cancelled ) {
					setNetworkLoaded( true );
				}
			} );
		} else {
			fetchBinding();
		}
		if ( ! network && ! networkLocked ) {
			// L-7 ①: failure or timeout = no suggestion, no message.
			apiFetch( { path: '/wpcy/v1/profile/suggest' } )
				.then( ( body ) => {
					if ( ! cancelled ) {
						setSuggest( body );
					}
				} )
				.catch( () => {} );
		}
		return () => {
			cancelled = true;
			window.clearTimeout( flashTimer.current );
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const setViewPersist = ( next ) => {
		setView( next );
		// L-7 ②: a failed write only affects the preference; no message.
		apiFetch( {
			path: '/wp/v2/users/me',
			method: 'POST',
			data: { meta: { [ META_KEY ]: next } },
		} ).catch( () => {} );
	};

	const flash = ( key ) => {
		window.clearTimeout( flashTimer.current );
		setSavedFlash( { [ key ]: __( '已保存 ✓', 'wp-china-yes' ) } );
		flashTimer.current = window.setTimeout(
			() => setSavedFlash( {} ),
			2000
		);
	};

	// Network lock rejection: stays until the next save on this page.
	const lockedNotice = ( key ) => {
		window.clearTimeout( flashTimer.current );
		setSavedFlash( {
			[ key ]: __( '网络管理员已锁定此项，修改未保存。', 'wp-china-yes' ),
		} );
	};

	/**
	 * CO-01: PUT the partial object; on any non-2xx the store rolls the
	 * control back and this card shows the red alert with 重试. A network
	 * lock rejection is not a generic failure: the item's status line says
	 * so instead, with no alert and no 重试.
	 *
	 * @param {Object} data Partial settings body.
	 * @param {string} key  Field key for 「已保存 ✓」.
	 * @param {string} card Card id for the alert.
	 * @return {Promise<Object>} Save result.
	 */
	const patch = ( data, key, card ) => {
		setCardError( null );
		window.clearTimeout( flashTimer.current );
		setSavedFlash( {} );
		return Promise.resolve(
			patchSettings(
				// Input kept after a lock refusal goes out with the next save.
				mergeSettings( unsaved || {}, data ),
				network ? { path: NETWORK_SETTINGS_PATH } : undefined
			)
		).then( ( result ) => {
			if ( result?.ok ) {
				flash( key );
			} else if ( result?.error?.locked ) {
				// Refused field: reverted, lock hint in its status line.
				// Other fields of the request: no 已保存 ✓, kept as unsaved.
				const own = leafPaths( data );
				if (
					( result.error.lockedPaths || [] ).some( ( path ) =>
						own.includes( path )
					)
				) {
					lockedNotice( key );
				}
			} else {
				setCardError( {
					card,
					key,
					data,
					message:
						result?.error?.message ||
						__( '暂时无法保存设置，请稍后重试。', 'wp-china-yes' ),
				} );
			}
			return result;
		} );
	};

	const retry = () => {
		if ( cardError ) {
			patch( cardError.data, cardError.key, cardError.card );
		}
	};

	const profile = settings.profile || 'domestic';
	const customs = customizedKeys( settings );
	const suggestion = suggest?.suggestion || null;
	const bound = binding?.status === 'bound';

	let lede;
	if ( networkLocked ) {
		lede = __( '已由网络设定 · 本站不能单独修改', 'wp-china-yes' );
	} else if ( network ) {
		lede = __( '网络策略，子站可覆盖', 'wp-china-yes' );
	} else if ( view === 'advanced' ) {
		lede = sprintf(
			/* translators: %s: scene name */
			__(
				'已按「%s」配好 · 高级模式显示每项的源与作用域',
				'wp-china-yes'
			),
			profileLabel( profile )
		);
	} else {
		lede = sprintf(
			/* translators: %s: scene name */
			__( '已按「%s」配好 · 改动即时生效，单项仍可调整', 'wp-china-yes' ),
			profileLabel( profile )
		);
	}

	const mode = (
		<div className="mode">
			<Icon name="sliders" size={ 16 } />
			{ __( '显示', 'wp-china-yes' ) }{ ' ' }
			<span className="seg">
				<button
					type="button"
					className={ view === 'simple' ? 'on' : '' }
					aria-pressed={ view === 'simple' }
					onClick={ () => setViewPersist( 'simple' ) }
				>
					{ __( '简单', 'wp-china-yes' ) }
				</button>
				<button
					type="button"
					className={ view === 'advanced' ? 'on' : '' }
					aria-pressed={ view === 'advanced' }
					onClick={ () => setViewPersist( 'advanced' ) }
				>
					{ __( '高级', 'wp-china-yes' ) }
				</button>
			</span>
		</div>
	);

	const confirmSwitch = () => {
		const next = pendingProfile;
		setSwitching( true );
		patch( { profile: next }, 'profile', 'scene' ).finally( () => {
			setSwitching( false );
			setPendingProfile( null );
		} );
	};

	const shared = {
		settings,
		disabled: networkLocked,
		locked: networkLocked,
		patch,
		savedFlash,
		cardError,
		onRetry: retry,
	};

	let body;
	if ( network && ! networkLoaded ) {
		body = (
			<div className="wpcy-stack" aria-busy="true">
				<section className="card">
					<Skel className="skel-title" />
					<Skel />
					<Skel className="skel-block" />
				</section>
			</div>
		);
	} else if ( network && loadError ) {
		body = (
			<LoadError
				what={ __( '设置', 'wp-china-yes' ) }
				onRetry={ () => fetchNetworkSettings() }
			/>
		);
	} else {
		body = (
			<div className="wpcy-stack">
				{ networkLocked ? (
					<Notice tone="info" wrap={ false }>
						{ __(
							'这些设置由网络管理员统一设定。需要本站单独调整时，请联系网络管理员在网络后台开放「允许子站覆盖」。',
							'wp-china-yes'
						) }
					</Notice>
				) : null }
				{ network ? <OverrideCard { ...shared } /> : null }
				<SceneCard
					{ ...shared }
					profile={ profile }
					cards={ sceneCards() }
					suggestion={ suggestion }
					customs={ customs }
					network={ network }
					onPick={ ( id ) => {
						if ( id !== profile ) {
							setPendingProfile( id );
						}
					} }
				/>
				{ view === 'simple' || networkLocked ? (
					<>
						<SimpleAccel
							{ ...shared }
							network={ network }
							bound={ bound }
							bindingError={ bindingError }
							onRetryBinding={ () => fetchBinding() }
						/>
						<AdminCard { ...shared } />
					</>
				) : (
					<>
						<AdvancedConn
							{ ...shared }
							customs={ customs }
							network={ network }
							bound={ bound }
							bindingError={ bindingError }
							onRetryBinding={ () => fetchBinding() }
						/>
						<AdminCard { ...shared } advanced />
					</>
				) }
			</div>
		);
	}

	return (
		<PageShell
			title={ __( '设置', 'wp-china-yes' ) }
			lede={ lede }
			actions={ networkLocked ? null : mode }
			showTabs={ ! network }
		>
			{ body }
			{ pendingProfile ? (
				<SwitchModal
					target={ pendingProfile }
					customs={ customs }
					busy={ switching || saving }
					onConfirm={ confirmSwitch }
					onClose={ () => {
						if ( ! switching ) {
							setPendingProfile( null );
						}
					} }
				/>
			) : null }
		</PageShell>
	);
}

/**
 * Customized-items sentence (CO-11 / L-6).
 *
 * @param {string[]} customs Customized keys.
 * @return {string} Sentence.
 */
function customsSentence( customs ) {
	return sprintf(
		/* translators: 1: count 2: names */
		__(
			'改过的设置在切换场景后会恢复默认（当前 %1$d 项：%2$s）。',
			'wp-china-yes'
		),
		customs.length,
		customs.map( customKeyLabel ).join( '、' )
	);
}

function SwitchModal( { target, customs, busy, onConfirm, onClose } ) {
	return (
		<Modal
			title={ sprintf(
				/* translators: %s: scene */
				__( '切换到「%s」？', 'wp-china-yes' ),
				profileLabel( target )
			) }
			closeButtonLabel={ __( '关闭', 'wp-china-yes' ) }
			onRequestClose={ onClose }
			className="wpcy-app wpcy-switch-modal"
		>
			<p>
				{ __(
					'切换场景会把下面各项重置为该场景的默认组合。',
					'wp-china-yes'
				) }
			</p>
			{ customs.length ? (
				<Notice tone="warn" wrap={ false }>
					{ customsSentence( customs ) }
				</Notice>
			) : null }
			<div className="wpcy-modal-foot">
				<Btn variant="secondary" disabled={ busy } onClick={ onClose }>
					{ __( '取消', 'wp-china-yes' ) }
				</Btn>
				<Btn
					variant="primary"
					isBusy={ busy }
					disabled={ busy }
					onClick={ onConfirm }
				>
					{ __( '切换', 'wp-china-yes' ) }
				</Btn>
			</div>
		</Modal>
	);
}

/**
 * CO-01 card-top red alert with 重试 (L-5). role="alert".
 *
 * @param {Object}      props
 * @param {Object|null} props.cardError
 * @param {string}      props.card
 * @param {Function}    props.onRetry
 */
function CardAlert( { cardError, card, onRetry } ) {
	if ( ! cardError || cardError.card !== card ) {
		return null;
	}
	return (
		<Notice
			tone="bad"
			icon={ <Icon name="warn" size={ 18 } /> }
			wrap={ false }
			role="alert"
			className="card-alert"
			action={
				<Btn variant="secondary" onClick={ onRetry }>
					{ __( '重试', 'wp-china-yes' ) }
				</Btn>
			}
		>
			{ cardError.message }
		</Notice>
	);
}

/**
 * Status text for one field ('' when idle).
 *
 * @param {Object<string, string>} status Field key → status text.
 * @param {string}                 key    Field key.
 * @return {string} Text for that field's status line.
 */
function statusText( status, key ) {
	return ( status && status[ key ] ) || '';
}

/**
 * Per-field save status (admin-ui-spec §8): the role=status container is
 * mounted from first paint and only its text changes, so screen readers
 * announce 「已保存 ✓」 once per save.
 *
 * @param {Object} props
 * @param {string} props.text Current status text ('' when idle).
 */
function Saved( { text } ) {
	return (
		<span className="saved" role="status">
			{ text }
		</span>
	);
}

/**
 * Card title with the subsite 「已由网络设定」 pill (CO-06).
 *
 * @param {Object}                    props
 * @param {string}                    props.icon
 * @param {boolean}                   props.locked
 * @param {import('react').ReactNode} props.children
 */
function CardTitle( { icon, locked, children } ) {
	return (
		<h2 className="section-title">
			<Icon name={ icon } size={ 18 } />
			{ children }
			{ locked ? (
				<span className="wpcy-locked-pill">
					<Pill>{ __( '已由网络设定', 'wp-china-yes' ) }</Pill>
				</span>
			) : null }
		</h2>
	);
}

function OverrideCard( { settings, patch, savedFlash, cardError, onRetry } ) {
	const allow = settings.allow_site_override !== false;
	return (
		<section className="card" data-card="override">
			<CardTitle icon="layers">
				{ __( '子站覆盖', 'wp-china-yes' ) }
			</CardTitle>
			<p className="section-desc">
				{ __( '下面是全网络的默认设置。', 'wp-china-yes' ) }
			</p>
			<CardAlert
				cardError={ cardError }
				card="override"
				onRetry={ onRetry }
			/>
			<SimpleRowWrap
				icon="layers"
				ok={ allow }
				title={ __( '允许子站覆盖', 'wp-china-yes' ) }
				help={ __(
					'开启后，子站管理员可以在本站设置里单独调整；关闭后子站的单独调整不再生效，全网络统一使用这里的设置。恢复模式始终按子站生效。',
					'wp-china-yes'
				) }
			>
				<Toggle
					checked={ allow }
					label={ onOff( allow ) }
					onChange={ ( on ) =>
						patch(
							{ allow_site_override: on },
							'allow_site_override',
							'override'
						)
					}
				/>
				<Saved
					text={ statusText( savedFlash, 'allow_site_override' ) }
				/>
			</SimpleRowWrap>
		</section>
	);
}

function SceneCard( {
	profile,
	cards,
	suggestion,
	customs,
	disabled,
	locked,
	network,
	onPick,
	savedFlash,
	cardError,
	onRetry,
} ) {
	const plain = ! locked && ! network;
	return (
		<section className="card" data-card="scene">
			<CardTitle icon="map" locked={ locked }>
				{ __( '站点场景', 'wp-china-yes' ) }
			</CardTitle>
			<p className="section-desc">
				{ locked
					? __( '网络管理员为全网络选定的场景。', 'wp-china-yes' )
					: __(
							'场景决定下面各项的默认组合；每一项之后仍可单独调整。',
							'wp-china-yes'
					  ) }
			</p>
			<CardAlert
				cardError={ cardError }
				card="scene"
				onRetry={ onRetry }
			/>
			<div className="choice-cards cols-2">
				{ cards.map( ( card ) => (
					<label
						key={ card.id }
						htmlFor={ 'wpcy-profile-' + card.id }
						className={
							'choice' +
							( profile === card.id ? ' is-on' : '' ) +
							( disabled ? ' is-disabled' : '' )
						}
					>
						<input
							id={ 'wpcy-profile-' + card.id }
							type="radio"
							name="wpcy-profile"
							checked={ profile === card.id }
							disabled={ disabled }
							onChange={ () => onPick( card.id ) }
						/>
						<span>
							<div className="t">
								{ card.title }
								{ plain && suggestion === card.id ? (
									<span className="rec">
										{ __( '推荐', 'wp-china-yes' ) }
									</span>
								) : null }
							</div>
							<div className="d">{ card.desc }</div>
						</span>
					</label>
				) ) }
			</div>
			{ plain ? (
				<div className="scene-assert">
					<Notice
						wrap={ false }
						tone="info"
						action={
							suggestion ? (
								<SuggestAction
									profile={ profile }
									suggestion={ suggestion }
									onPick={ onPick }
								/>
							) : null
						}
					>
						{ __(
							'切换场景会把下面各项重置为该场景的默认组合。',
							'wp-china-yes'
						) }
						{ suggestion
							? sprintf(
									/* translators: %s: scene */
									__(
										'根据服务器位置和你的浏览器，我们建议：%s。',
										'wp-china-yes'
									),
									profileLabel( suggestion )
							  )
							: null }
					</Notice>
					{ customs.length ? (
						<Notice tone="warn" wrap={ false }>
							{ customsSentence( customs ) }
						</Notice>
					) : null }
				</div>
			) : null }
			<Saved text={ statusText( savedFlash, 'profile' ) } />
			{ plain ? (
				<p className="hint wpcy-wizard-entry">
					{ __( '拿不准？', 'wp-china-yes' ) }
					<a href={ adminPageUrl( PAGES.onboarding ) }>
						{ __( '跑一次 30 秒设置向导', 'wp-china-yes' ) }
					</a>
				</p>
			) : null }
		</section>
	);
}

function SuggestAction( { profile, suggestion, onPick } ) {
	if ( profile === suggestion ) {
		return (
			<Btn variant="secondary" disabled>
				{ __( '已是推荐场景', 'wp-china-yes' ) }
			</Btn>
		);
	}
	return (
		<Btn variant="secondary" onClick={ () => onPick( suggestion ) }>
			{ __( '应用建议', 'wp-china-yes' ) }
		</Btn>
	);
}

/**
 * CO-13 ④ / CO-05 right-hand control for 中文字体, by binding state.
 *
 * @param {Object}   props
 * @param {Object}   props.settings
 * @param {boolean}  props.network
 * @param {boolean}  props.bound
 * @param {boolean}  props.bindingError
 * @param {boolean}  props.disabled
 * @param {Function} props.patch
 * @param {Object}   props.savedFlash
 * @param {string}   props.card
 * @param {Function} props.onRetryBinding
 * @param {string}   [props.label]        Advanced switch label.
 */
function FontControl( {
	settings,
	network,
	bound,
	bindingError,
	disabled,
	patch,
	savedFlash,
	card,
	onRetryBinding,
	label,
} ) {
	const on = Boolean( settings?.modules?.windfonts );
	if ( network ) {
		return (
			<>
				<Scope>{ __( '各子站绑定后可用', 'wp-china-yes' ) }</Scope>
				<Toggle
					checked={ false }
					disabled
					label={ label || onOff( false ) }
				/>
			</>
		);
	}
	if ( bindingError ) {
		// L-7 ③: SH-06 instead of 「绑定本站后可用」, switch disabled.
		return (
			<>
				<LoadError
					what={ __( '绑定状态', 'wp-china-yes' ) }
					onRetry={ onRetryBinding }
				/>
				<Toggle
					checked={ on }
					disabled
					label={ label || onOff( on ) }
				/>
			</>
		);
	}
	if ( ! bound ) {
		return (
			<>
				<Scope href={ adminPageUrl( PAGES.services ) }>
					{ __( '绑定本站后可用', 'wp-china-yes' ) }
				</Scope>
				<Toggle
					checked={ false }
					disabled
					label={ label || onOff( false ) }
				/>
			</>
		);
	}
	return (
		<>
			<Toggle
				checked={ on }
				disabled={ disabled }
				label={ label || onOff( on ) }
				onChange={ ( next ) =>
					patch( { modules: { windfonts: next } }, 'windfonts', card )
				}
			/>
			<Saved text={ statusText( savedFlash, 'windfonts' ) } />
		</>
	);
}

function SimpleAccel( {
	settings,
	disabled,
	locked,
	network,
	patch,
	savedFlash,
	cardError,
	onRetry,
	bound,
	bindingError,
	onRetryBinding,
} ) {
	const c = conn( settings );
	const orgOn = ( c.wordpress_org || 'auto' ) !== 'off';
	const assetScope = c.public_assets?.scope || 'both';
	const assetsOn = assetScope !== 'off';
	const avatarOn = avatarOf( settings ) !== 'off';
	const def = sceneDefaults( settings.profile || 'domestic' );
	const fontOn = Boolean( settings?.modules?.windfonts );

	return (
		<section className="card" data-card="accel">
			<CardTitle icon="bolt" locked={ locked }>
				{ __( '加速与优化', 'wp-china-yes' ) }
			</CardTitle>
			<p className="section-desc">
				{ locked
					? __( '按网络选定的场景配好。', 'wp-china-yes' )
					: __(
							'按当前场景已配好，通常不需要改。想看具体走哪个源、只在后台还是前台生效，切到右上角的「高级」。',
							'wp-china-yes'
					  ) }
			</p>
			<CardAlert
				cardError={ cardError }
				card="accel"
				onRetry={ onRetry }
			/>
			<SimpleRowWrap
				icon="download"
				ok
				title={ __( 'WordPress 更新与安装', 'wp-china-yes' ) }
				help={ __(
					'按场景自动选择：跨境站直连 WordPress.org，国内站走国内镜像',
					'wp-china-yes'
				) }
			>
				<Pill state="ok">
					{ orgOn
						? __( '国内镜像', 'wp-china-yes' )
						: __( '直连 WordPress.org', 'wp-china-yes' ) }
				</Pill>
			</SimpleRowWrap>
			<SimpleRowWrap
				icon="bolt"
				ok={ assetsOn }
				title={ __( '公共库接通', 'wp-china-yes' ) }
				help={ __(
					'字体、脚本等第三方公共资源改到更快的源',
					'wp-china-yes'
				) }
			>
				{ assetsOn ? <Scope>{ scopeWord( assetScope ) }</Scope> : null }
				<Toggle
					checked={ assetsOn }
					disabled={ disabled }
					label={ onOff( assetsOn ) }
					onChange={ ( on ) =>
						patch(
							{
								connectivity: {
									public_assets: {
										items: Array.isArray(
											c.public_assets?.items
										)
											? c.public_assets.items
											: FIVE,
										scope: on
											? def.public_assets.scope
											: 'off',
									},
								},
							},
							'assets',
							'accel'
						)
					}
				/>
				<Saved text={ statusText( savedFlash, 'assets' ) } />
			</SimpleRowWrap>
			<SimpleRowWrap
				icon="user"
				ok={ avatarOn }
				title={ __( '头像接通', 'wp-china-yes' ) }
				help={ __(
					'Gravatar 的中国替代，头像不再空白',
					'wp-china-yes'
				) }
			>
				{ avatarOn ? <Scope>{ scopeWord( 'both' ) }</Scope> : null }
				<Toggle
					checked={ avatarOn }
					disabled={ disabled }
					label={ onOff( avatarOn ) }
					onChange={ ( on ) =>
						patch(
							{
								connectivity: {
									avatar: on ? def.avatar : 'off',
								},
							},
							'avatar',
							'accel'
						)
					}
				/>
				<Saved text={ statusText( savedFlash, 'avatar' ) } />
			</SimpleRowWrap>
			<SimpleRowWrap
				icon="font"
				ok={ fontOn && bound }
				title={ __( '中文字体（Windfonts）', 'wp-china-yes' ) }
				help={ __( '让站点用上更好看的中文字体', 'wp-china-yes' ) }
			>
				<FontControl
					settings={ settings }
					network={ network }
					bound={ bound }
					bindingError={ bindingError }
					disabled={ disabled }
					patch={ patch }
					savedFlash={ savedFlash }
					card="accel"
					onRetryBinding={ onRetryBinding }
				/>
			</SimpleRowWrap>
		</section>
	);
}

function AdminCard( {
	settings,
	disabled,
	locked,
	patch,
	savedFlash,
	cardError,
	onRetry,
	advanced,
} ) {
	const c = conn( settings );
	const heart = ( c.heartbeat || 'off' ) === 'on';
	const feeds = ( c.dashboard_feeds || 'allow' ) === 'block';
	const locale =
		c.admin_locale_follow === undefined
			? true
			: Boolean( c.admin_locale_follow );
	const hidePromo =
		settings?.admin?.hide_promo === undefined
			? true
			: Boolean( settings.admin.hide_promo );
	const Row = advanced ? FieldRow : SimpleRowWrap;
	const save = ( data, key ) => patch( data, key, 'admin' );
	return (
		<section className="card" data-card="admin">
			<CardTitle icon="monitor" locked={ locked }>
				{ __( '后台体验', 'wp-china-yes' ) }
			</CardTitle>
			<p className="section-desc">
				{ __(
					'为人在中国大陆、操作海外后台的管理员减少等待。',
					'wp-china-yes'
				) }
			</p>
			<CardAlert
				cardError={ cardError }
				card="admin"
				onRetry={ onRetry }
			/>
			<Row
				icon="bell"
				ok={ hidePromo }
				title={ __( '隐藏后台的推广与广告', 'wp-china-yes' ) }
				help={ __(
					'由文派服务下发规则，核心更新与安全通知不会隐藏。规则集最多沿用 72 小时。',
					'wp-china-yes'
				) }
			>
				<Toggle
					checked={ hidePromo }
					disabled={ disabled }
					label={ onOff( hidePromo ) }
					onChange={ ( on ) =>
						save( { admin: { hide_promo: on } }, 'hide_promo' )
					}
				/>
				<Saved text={ statusText( savedFlash, 'hide_promo' ) } />
			</Row>
			<Row
				icon="clock"
				ok={ heart }
				title={ __( '减少后台心跳', 'wp-china-yes' ) }
				help={ __(
					'仪表盘不再轮询，编辑器 60 秒一次',
					'wp-china-yes'
				) }
			>
				<Toggle
					checked={ heart }
					disabled={ disabled }
					label={ onOff( heart ) }
					onChange={ ( on ) =>
						save(
							{ connectivity: { heartbeat: on ? 'on' : 'off' } },
							'heartbeat'
						)
					}
				/>
				<Saved text={ statusText( savedFlash, 'heartbeat' ) } />
			</Row>
			<Row
				icon="block"
				ok={ feeds }
				title={ __( '不加载仪表盘的外部内容', 'wp-china-yes' ) }
				help={ __( 'WordPress 新闻、活动等出站请求', 'wp-china-yes' ) }
			>
				<Toggle
					checked={ feeds }
					disabled={ disabled }
					label={ onOff( feeds ) }
					onChange={ ( on ) =>
						save(
							{
								connectivity: {
									dashboard_feeds: on ? 'block' : 'allow',
								},
							},
							'dashboard_feeds'
						)
					}
				/>
				<Saved text={ statusText( savedFlash, 'dashboard_feeds' ) } />
			</Row>
			<Row
				icon="globe"
				ok={ locale }
				title={
					advanced
						? __( '后台界面语言', 'wp-china-yes' )
						: __( '后台语言跟随管理员', 'wp-china-yes' )
				}
				help={
					advanced
						? __(
								'管理员账号的语言设为中文时后台用中文；前台站点语言不变',
								'wp-china-yes'
						  )
						: __(
								'后台界面用中文，前台站点语言不变',
								'wp-china-yes'
						  )
				}
			>
				<Toggle
					checked={ locale }
					disabled={ disabled }
					label={ onOff( locale ) }
					onChange={ ( on ) =>
						save(
							{ connectivity: { admin_locale_follow: on } },
							'admin_locale_follow'
						)
					}
				/>
				<Saved
					text={ statusText( savedFlash, 'admin_locale_follow' ) }
				/>
			</Row>
		</section>
	);
}

function SimpleRowWrap( { icon, ok, title, help, children } ) {
	return (
		<div className="simple-row">
			<div className={ 'tile' + ( ok ? ' ok' : '' ) }>
				<Icon name={ icon } size={ 18 } />
			</div>
			<div>
				<div className="t">{ title }</div>
				<div className="d">{ help }</div>
			</div>
			<div className="r">{ children }</div>
		</div>
	);
}

function FieldRow( { icon, title, help, children } ) {
	return (
		<div className="field">
			<div>
				<div className="field-label">
					<Icon name={ icon } size={ 18 } />
					{ title }
				</div>
				<div className="field-help">{ help }</div>
			</div>
			<div className="field-ctl">{ children }</div>
		</div>
	);
}

function AdvancedConn( {
	settings,
	disabled,
	network,
	patch,
	savedFlash,
	customs,
	cardError,
	onRetry,
	bound,
	bindingError,
	onRetryBinding,
} ) {
	const c = conn( settings );
	const org = c.wordpress_org || 'auto';
	const scope = c.public_assets?.scope || 'both';
	const items = Array.isArray( c.public_assets?.items )
		? c.public_assets.items
		: FIVE;
	const avatarValue = avatarOf( settings );
	const def = sceneDefaults( settings.profile || 'domestic' );
	const save = ( data, key ) => patch( data, key, 'conn' );

	return (
		<section className="card" data-card="conn">
			<CardTitle icon="globe">
				{ __( '连通性', 'wp-china-yes' ) }
			</CardTitle>
			<p className="section-desc">
				{ __(
					'每一项都可以选择只作用于后台、只作用于前台，或两者。',
					'wp-china-yes'
				) }
			</p>
			<CardAlert
				cardError={ cardError }
				card="conn"
				onRetry={ onRetry }
			/>
			<FieldRow
				icon="download"
				title={ __( 'WordPress.org 源', 'wp-china-yes' ) }
				help={ __(
					'更新检查与安装包从哪里取 · 镜像由 WenPai.org 提供',
					'wp-china-yes'
				) }
			>
				<div className="opts">
					<label className="opt" htmlFor="wpcy-org-auto">
						<input
							id="wpcy-org-auto"
							type="radio"
							name="org"
							checked={ org === 'auto' }
							disabled={ disabled }
							onChange={ () =>
								save(
									{ connectivity: { wordpress_org: 'auto' } },
									'org'
								)
							}
						/>
						<span>
							<span className="t">
								{ __( '国内镜像', 'wp-china-yes' ) }
							</span>
							<div className="d">
								{ __(
									'优先国内镜像，不可用时回原始上游 · 国内站默认',
									'wp-china-yes'
								) }
							</div>
						</span>
					</label>
					<label className="opt" htmlFor="wpcy-org-off">
						<input
							id="wpcy-org-off"
							type="radio"
							name="org"
							checked={ org === 'off' }
							disabled={ disabled }
							onChange={ () =>
								save(
									{ connectivity: { wordpress_org: 'off' } },
									'org'
								)
							}
						/>
						<span>
							<span className="t">
								{ __( '直连 WordPress.org', 'wp-china-yes' ) }
							</span>
							<div className="d">
								{ __(
									'不经过镜像 · 跨境站默认',
									'wp-china-yes'
								) }
							</div>
						</span>
					</label>
				</div>
				<Saved text={ statusText( savedFlash, 'org' ) } />
			</FieldRow>
			<FieldRow
				icon="bolt"
				title={ __( '公共前端库', 'wp-china-yes' ) }
				help={ __(
					'把常用 CDN 改到 adminCDN 的国内节点',
					'wp-china-yes'
				) }
			>
				<div className="seg">
					{ [
						[ 'both', __( '后台与前台', 'wp-china-yes' ) ],
						[ 'admin', __( '仅后台', 'wp-china-yes' ) ],
						[ 'frontend', __( '仅前台', 'wp-china-yes' ) ],
						[ 'off', __( '关闭', 'wp-china-yes' ) ],
					].map( ( [ value, label ] ) => (
						<button
							key={ value }
							type="button"
							className={ scope === value ? 'on' : '' }
							aria-pressed={ scope === value }
							disabled={ disabled }
							onClick={ () =>
								save(
									{
										connectivity: {
											public_assets: {
												items,
												scope: value,
											},
										},
									},
									'assets'
								)
							}
						>
							{ label }
						</button>
					) ) }
				</div>
				<div className="chk wpcy-chk-gap">
					{ FIVE.map( ( item ) => (
						<label key={ item } htmlFor={ 'wpcy-asset-' + item }>
							<input
								id={ 'wpcy-asset-' + item }
								type="checkbox"
								checked={ items.includes( item ) }
								disabled={ disabled || scope === 'off' }
								onChange={ ( ev ) => {
									const next = ev.target.checked
										? [ ...items, item ]
										: items.filter( ( v ) => v !== item );
									save(
										{
											connectivity: {
												public_assets: {
													items: next,
													scope,
												},
											},
										},
										'assets'
									);
								} }
							/>
							{ ASSET_LABEL[ item ] }
						</label>
					) ) }
				</div>
				<Saved text={ statusText( savedFlash, 'assets' ) } />
			</FieldRow>
			<AvatarField
				value={ avatarValue }
				disabled={ disabled }
				custom={ customs.includes( 'avatar' ) }
				saved={ statusText( savedFlash, 'avatar' ) }
				onRestore={ () =>
					save( { connectivity: { avatar: def.avatar } }, 'avatar' )
				}
				defaultWord={ __( 'Cravatar 中国线路', 'wp-china-yes' ) }
				onChange={ ( value ) =>
					save( { connectivity: { avatar: value } }, 'avatar' )
				}
			/>
			<FieldRow
				icon="font"
				title={ __( '字体', 'wp-china-yes' ) }
				help={ __(
					'中文字体替换 · 由 Windfonts 提供',
					'wp-china-yes'
				) }
			>
				<FontControl
					settings={ settings }
					network={ network }
					bound={ bound }
					bindingError={ bindingError }
					disabled={ disabled }
					patch={ patch }
					savedFlash={ savedFlash }
					card="conn"
					onRetryBinding={ onRetryBinding }
					label={ __( '启用 Windfonts', 'wp-china-yes' ) }
				/>
			</FieldRow>
		</section>
	);
}

function AvatarField( {
	value,
	disabled,
	onChange,
	custom,
	saved,
	onRestore,
	defaultWord,
} ) {
	const title = __( '头像', 'wp-china-yes' );
	const help = __( '评论与后台的头像源 · 由 Cravatar 提供', 'wp-china-yes' );
	const name = 'avatar';
	const options = [
		[
			'cravatar_cn',
			'-cn',
			__( 'Cravatar 中国线路', 'wp-china-yes' ),
			__( 'cravatar.cn · 国内节点', 'wp-china-yes' ),
		],
		[
			'cravatar_global',
			'-global',
			__( 'Cravatar 国际线路', 'wp-china-yes' ),
			__( 'cravatar.com · 全球节点', 'wp-china-yes' ),
		],
		[
			'off',
			'-off',
			__( '关闭', 'wp-china-yes' ),
			__( '保留 Gravatar', 'wp-china-yes' ),
		],
	];
	return (
		<FieldRow icon="user" title={ title } help={ help }>
			<div className="opts">
				{ options.map( ( [ val, suffix, t, d ] ) => (
					<label
						key={ val }
						className="opt"
						htmlFor={ name + suffix }
					>
						<input
							id={ name + suffix }
							type="radio"
							name={ name }
							checked={ value === val }
							disabled={ disabled }
							onChange={ () => onChange( val ) }
						/>
						<span>
							<span className="t">{ t }</span>
							<div className="d">{ d }</div>
						</span>
					</label>
				) ) }
			</div>
			<Saved text={ saved } />
			{ custom ? (
				<div className="customized">
					{ __( '已自定义', 'wp-china-yes' ) }
					{ ' · ' }
					<button
						type="button"
						onClick={ onRestore }
						disabled={ disabled }
					>
						{ sprintf(
							/* translators: %s: default */
							__( '恢复场景默认（%s）', 'wp-china-yes' ),
							defaultWord
						) }
					</button>
				</div>
			) : null }
		</FieldRow>
	);
}
