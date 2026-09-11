/**
 * Settings: scene cards, simple / advanced, instant save.
 */

/* eslint-disable jsx-a11y/label-has-associated-control -- prototype choice/opt labels wrap radio + nested copy */

import { __, sprintf } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { Modal } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import PageShell from '../components/PageShell';
import { STORE_NAME } from '../store';
import { adminPageUrl, PAGES } from '../routing';
import Icon from '../ui/icons';
import Btn from '../ui/Btn';
import Pill from '../ui/Pill';
import Prov from '../ui/Prov';
import Scope from '../ui/Scope';
import Toggle from '../ui/Toggle';
import Notice from '../ui/Notice';
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

export default function Connect() {
	const { settings, saving, notice, capabilities } = useSelect(
		( select ) => {
			const store = select( STORE_NAME );
			return {
				settings: store.getSettings() || {},
				saving: store.isSaving(),
				notice: store.getNotice(),
				capabilities: store.getCapabilities() || {},
			};
		},
		[]
	);
	const { patchSettings, clearNotice } = useDispatch( STORE_NAME );
	const [ view, setView ] = useState( 'simple' );
	const [ suggest, setSuggest ] = useState( null );
	const [ pendingProfile, setPendingProfile ] = useState( null );
	const [ savedFlash, setSavedFlash ] = useState( '' );

	useEffect( () => {
		let cancelled = false;
		apiFetch( { path: '/wp/v2/users/me?context=edit' } )
			.then( ( me ) => {
				if ( cancelled ) {
					return;
				}
				if ( me?.meta?.[ META_KEY ] === 'advanced' ) {
					setView( 'advanced' );
				}
			} )
			.catch( () => {} );
		apiFetch( { path: '/wpcy/v1/profile/suggest' } )
			.then( ( body ) => {
				if ( ! cancelled ) {
					setSuggest( body );
				}
			} )
			.catch( () => {} );
		return () => {
			cancelled = true;
		};
	}, [] );

	const setViewPersist = ( next ) => {
		setView( next );
		apiFetch( {
			path: '/wp/v2/users/me',
			method: 'POST',
			data: { meta: { [ META_KEY ]: next } },
		} ).catch( () => {} );
	};

	const flash = ( key ) => {
		setSavedFlash( key );
		window.setTimeout( () => setSavedFlash( '' ), 2000 );
	};

	const patch = ( data, key ) => {
		clearNotice();
		return patchSettings( data ).then( ( action ) => {
			if ( ! action?.notice ) {
				flash( key );
			}
			return action;
		} );
	};

	const profile = settings.profile || 'domestic';
	const networkLocked = Boolean(
		capabilities.network_locked || settings.network_locked
	);
	const customs = customizedKeys( settings );
	const suggestion = suggest?.suggestion;
	const cards = sceneCards();

	const lede =
		view === 'advanced'
			? sprintf(
					/* translators: %s: scene name */
					__(
						'已按「%s」配好 · 高级模式显示每项的源与作用域',
						'wp-china-yes'
					),
					profileLabel( profile )
			  )
			: sprintf(
					/* translators: %s: scene name */
					__(
						'已按「%s」配好 · 改动即时生效，单项仍可调整',
						'wp-china-yes'
					),
					profileLabel( profile )
			  );

	const mode = (
		<div className="mode">
			<Icon name="sliders" size={ 16 } />
			{ __( '显示', 'wp-china-yes' ) }{ ' ' }
			<span className="seg">
				<button
					type="button"
					className={ view === 'simple' ? 'on' : '' }
					onClick={ () => setViewPersist( 'simple' ) }
				>
					{ __( '简单', 'wp-china-yes' ) }
				</button>
				<button
					type="button"
					className={ view === 'advanced' ? 'on' : '' }
					onClick={ () => setViewPersist( 'advanced' ) }
				>
					{ __( '高级', 'wp-china-yes' ) }
				</button>
			</span>
		</div>
	);

	return (
		<PageShell
			title={ __( '设置', 'wp-china-yes' ) }
			lede={
				networkLocked
					? __( '已由网络设定 · 本站不能单独修改', 'wp-china-yes' )
					: lede
			}
			actions={ networkLocked ? null : mode }
		>
			{ notice?.status === 'error' ? (
				<Notice tone="warn">{ notice.message }</Notice>
			) : null }
			{ networkLocked ? (
				<Notice tone="info">
					{ __(
						'这些设置由网络管理员统一设定。需要本站单独调整时，请联系网络管理员在网络后台开放「允许子站覆盖」。',
						'wp-china-yes'
					) }
				</Notice>
			) : null }
			<div className="wpcy-stack">
				<SceneCard
					profile={ profile }
					cards={ cards }
					suggestion={ suggestion }
					customs={ customs }
					disabled={ networkLocked }
					onPick={ ( id ) => {
						if ( id !== profile ) {
							setPendingProfile( id );
						}
					} }
				/>
				{ view === 'simple' ? (
					<>
						<SimpleAccel
							settings={ settings }
							disabled={ networkLocked }
							patch={ patch }
							savedFlash={ savedFlash }
						/>
						<SimpleAdmin
							settings={ settings }
							disabled={ networkLocked }
							patch={ patch }
						/>
					</>
				) : (
					<>
						<AdvancedConn
							settings={ settings }
							disabled={ networkLocked }
							patch={ patch }
							savedFlash={ savedFlash }
							customs={ customs }
						/>
						<SimpleAdmin
							settings={ settings }
							disabled={ networkLocked }
							patch={ patch }
							advanced
						/>
					</>
				) }
			</div>
			{ pendingProfile ? (
				<Modal
					title={ sprintf(
						/* translators: %s: scene */
						__( '切换到「%s」？', 'wp-china-yes' ),
						profileLabel( pendingProfile )
					) }
					onRequestClose={ () => setPendingProfile( null ) }
				>
					<p>
						{ __(
							'切换场景会把下面各项重置为该场景的默认组合。',
							'wp-china-yes'
						) }
					</p>
					<p className="wpcy-card-actions">
						<Btn
							variant="primary"
							disabled={ saving }
							onClick={ () => {
								const next = pendingProfile;
								setPendingProfile( null );
								patch( { profile: next }, 'profile' );
							} }
						>
							{ __( '切换', 'wp-china-yes' ) }
						</Btn>
						<Btn
							variant="secondary"
							onClick={ () => setPendingProfile( null ) }
						>
							{ __( '取消', 'wp-china-yes' ) }
						</Btn>
					</p>
				</Modal>
			) : null }
		</PageShell>
	);
}

function SceneCard( {
	profile,
	cards,
	suggestion,
	customs,
	disabled,
	onPick,
} ) {
	const suggestLabel = suggestion ? profileLabel( suggestion ) : '';
	return (
		<section className="card">
			<h2 className="section-title">
				<Icon name="map" size={ 18 } />
				{ __( '站点场景', 'wp-china-yes' ) }
			</h2>
			<p className="section-desc">
				{ __(
					'选对你的生意方向，加速和推荐都会跟着它走；每一项之后仍可单独调整。',
					'wp-china-yes'
				) }
			</p>
			<div className="choice-cards cols-2">
				{ cards.map( ( card ) => (
					<label
						key={ card.id }
						htmlFor={ 'wpcy-profile-' + card.id }
						className={
							'choice' + ( profile === card.id ? ' is-on' : '' )
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
								{ suggestion === card.id ? (
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
			<div className="scene-assert">
				{ suggestion ? (
					<Notice
						tone="info"
						action={
							profile === suggestion ? (
								<Btn variant="secondary" disabled>
									{ __( '已是推荐场景', 'wp-china-yes' ) }
								</Btn>
							) : (
								<Btn
									variant="secondary"
									onClick={ () => onPick( suggestion ) }
								>
									{ __( '应用建议', 'wp-china-yes' ) }
								</Btn>
							)
						}
					>
						{ sprintf(
							/* translators: %s: scene */
							__(
								'侦测到服务器在海外、管理员在国内，已为你选好「%s」。',
								'wp-china-yes'
							),
							suggestLabel
						) }
					</Notice>
				) : null }
				{ customs.length ? (
					<Notice tone="warn">
						{ sprintf(
							/* translators: 1: count 2: names */
							__(
								'改过的设置在切换场景后会恢复默认（当前 %1$d 项：%2$s）。',
								'wp-china-yes'
							),
							customs.length,
							customs.map( customKeyLabel ).join( '、' )
						) }
					</Notice>
				) : null }
			</div>
			<p
				className="hint"
				style={ {
					marginTop: 12,
					color: 'var(--wpcy-ink-3)',
					fontSize: 12,
				} }
			>
				{ __( '拿不准？', 'wp-china-yes' ) }
				<a href={ adminPageUrl( PAGES.onboarding ) }>
					{ __( '跑一次 30 秒设置向导', 'wp-china-yes' ) }
				</a>
			</p>
		</section>
	);
}

function SimpleAccel( { settings, disabled, patch, savedFlash } ) {
	const c = conn( settings );
	const orgOn = ( c.wordpress_org || 'auto' ) !== 'off';
	const assetsOn = ( c.public_assets?.scope || 'both' ) !== 'off';
	const avatar = c.avatar || 'cravatar_cn';
	const avatarValue =
		typeof avatar === 'string' ? avatar : avatar.admin || 'cravatar_cn';
	const avatarOn = avatarValue !== 'off';
	const assetScope = c.public_assets?.scope || 'both';
	const def = sceneDefaults( settings.profile || 'domestic' );

	return (
		<section className="card">
			<h2 className="section-title">
				<Icon name="bolt" size={ 18 } />
				{ __( '加速与优化', 'wp-china-yes' ) }
			</h2>
			<p className="section-desc">
				{ __(
					'按当前场景已配好，通常不需要改。想看具体走哪个源、只在后台还是前台生效，切到右上角的「高级」。',
					'wp-china-yes'
				) }
			</p>
			<div className="simple-row">
				<div className={ 'tile' + ( orgOn ? ' ok' : '' ) }>
					<Icon name="download" size={ 18 } />
				</div>
				<div>
					<div className="t">
						{ __( 'WordPress 更新与安装', 'wp-china-yes' ) }
						<Prov>WenPai.org</Prov>
					</div>
					<div className="d">
						{ __(
							'按场景自动选择：国内站走国内镜像，跨境站直连 WordPress.org',
							'wp-china-yes'
						) }
					</div>
				</div>
				<div className="r">
					<Pill tone={ orgOn ? 'ok' : '' }>
						{ orgOn
							? __( '国内镜像', 'wp-china-yes' )
							: __( '直连 WordPress.org', 'wp-china-yes' ) }
					</Pill>
				</div>
			</div>
			<div className="simple-row">
				<div className={ 'tile' + ( assetsOn ? ' ok' : '' ) }>
					<Icon name="bolt" size={ 18 } />
				</div>
				<div>
					<div className="t">
						{ __( '公共库接通', 'wp-china-yes' ) }
						<Prov>adminCDN</Prov>
					</div>
					<div className="d">
						{ __(
							'Google Fonts、Ajax、jsDelivr 等改到国内节点',
							'wp-china-yes'
						) }
					</div>
				</div>
				<div className="r">
					{ assetsOn ? (
						<Scope>{ scopeWord( assetScope ) }</Scope>
					) : null }
					<Toggle
						checked={ assetsOn }
						disabled={ disabled }
						label={
							assetsOn
								? __( '已开启', 'wp-china-yes' )
								: __( '未开启', 'wp-china-yes' )
						}
						onChange={ ( on ) =>
							patch(
								{
									connectivity: {
										public_assets: {
											items: FIVE,
											scope: on
												? def.public_assets.scope
												: 'off',
										},
									},
								},
								'assets'
							)
						}
					/>
				</div>
			</div>
			<div className="simple-row">
				<div className={ 'tile' + ( avatarOn ? ' ok' : '' ) }>
					<Icon name="user" size={ 18 } />
				</div>
				<div>
					<div className="t">
						{ __( '头像接通', 'wp-china-yes' ) }
						<Prov>Cravatar</Prov>
					</div>
					<div className="d">
						{ __(
							'Gravatar 在国内打不开，头像改走中国线路',
							'wp-china-yes'
						) }
					</div>
				</div>
				<div className="r">
					<Toggle
						checked={ avatarOn }
						disabled={ disabled }
						label={
							avatarOn
								? __( '已开启', 'wp-china-yes' )
								: __( '未开启', 'wp-china-yes' )
						}
						onChange={ ( on ) =>
							patch(
								{
									connectivity: {
										avatar: on ? def.avatar : 'off',
									},
								},
								'avatar'
							)
						}
					/>
				</div>
			</div>
			<div className="simple-row">
				<div className="tile">
					<Icon name="font" size={ 18 } />
				</div>
				<div>
					<div className="t">
						{ __( '中文字体', 'wp-china-yes' ) }
						<Prov>Windfonts</Prov>
					</div>
					<div className="d">
						{ __( '让站点用上更好看的中文字体', 'wp-china-yes' ) }
					</div>
				</div>
				<div className="r">
					<Scope href={ adminPageUrl( PAGES.services ) }>
						{ __( '绑定本站后可用', 'wp-china-yes' ) }
					</Scope>
					<Toggle
						checked={ false }
						disabled
						label={ __( '未开启', 'wp-china-yes' ) }
					/>
				</div>
			</div>
			{ savedFlash ? (
				<span className="saved">
					{ __( '已保存 ✓', 'wp-china-yes' ) }
				</span>
			) : null }
		</section>
	);
}

function SimpleAdmin( { settings, disabled, patch, advanced } ) {
	const c = conn( settings );
	const heart = ( c.heartbeat || 'off' ) === 'on';
	const feeds = ( c.dashboard_feeds || 'allow' ) === 'block';
	const locale =
		c.admin_locale_follow === undefined
			? true
			: Boolean( c.admin_locale_follow );
	const Row = advanced ? FieldRow : SimpleRowWrap;
	return (
		<section className="card">
			<h2 className="section-title">
				<Icon name="monitor" size={ 18 } />
				{ __( '后台体验', 'wp-china-yes' ) }
			</h2>
			<p className="section-desc">
				{ __(
					'为人在中国大陆、操作海外后台的管理员减少等待。',
					'wp-china-yes'
				) }
			</p>
			<Row
				icon="clock"
				title={ __( '减少后台心跳', 'wp-china-yes' ) }
				help={ __(
					'仪表盘不再轮询，编辑器 60 秒一次',
					'wp-china-yes'
				) }
			>
				<Toggle
					checked={ heart }
					disabled={ disabled }
					label={
						heart
							? __( '已开启', 'wp-china-yes' )
							: __( '未开启', 'wp-china-yes' )
					}
					onChange={ ( on ) =>
						patch( {
							connectivity: { heartbeat: on ? 'on' : 'off' },
						} )
					}
				/>
			</Row>
			<Row
				icon="block"
				title={ __( '不加载仪表盘的外部内容', 'wp-china-yes' ) }
				help={ __( 'WordPress 新闻、活动等出站请求', 'wp-china-yes' ) }
			>
				<Toggle
					checked={ feeds }
					disabled={ disabled }
					label={
						feeds
							? __( '已开启', 'wp-china-yes' )
							: __( '未开启', 'wp-china-yes' )
					}
					onChange={ ( on ) =>
						patch( {
							connectivity: {
								dashboard_feeds: on ? 'block' : 'allow',
							},
						} )
					}
				/>
			</Row>
			<Row
				icon="globe"
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
					label={
						locale
							? __( '已开启', 'wp-china-yes' )
							: __( '未开启', 'wp-china-yes' )
					}
					onChange={ ( on ) =>
						patch( {
							connectivity: { admin_locale_follow: on },
						} )
					}
				/>
			</Row>
		</section>
	);
}

function SimpleRowWrap( { icon, title, help, children } ) {
	return (
		<div className="simple-row">
			<div className="tile ok">
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

function AdvancedConn( { settings, disabled, patch, savedFlash, customs } ) {
	const c = conn( settings );
	const org = c.wordpress_org || 'auto';
	const scope = c.public_assets?.scope || 'both';
	const items = Array.isArray( c.public_assets?.items )
		? c.public_assets.items
		: FIVE;
	const avatar = c.avatar || 'cravatar_cn';
	const avatarValue =
		typeof avatar === 'string' ? avatar : avatar.admin || 'cravatar_cn';
	const def = sceneDefaults( settings.profile || 'domestic' );

	const restoreAvatar = () =>
		patch( {
			connectivity: {
				avatar: def.avatar,
			},
		} );

	return (
		<section className="card">
			<h2 className="section-title">
				<Icon name="globe" size={ 18 } />
				{ __( '连通性', 'wp-china-yes' ) }
			</h2>
			<p className="section-desc">
				{ __(
					'每一项都可以选择只作用于后台、只作用于前台，或两者。',
					'wp-china-yes'
				) }
			</p>
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
								patch( {
									connectivity: { wordpress_org: 'auto' },
								} )
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
								patch( {
									connectivity: { wordpress_org: 'off' },
								} )
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
				{ savedFlash === 'org' ? (
					<span className="saved">
						{ __( '已保存 ✓', 'wp-china-yes' ) }
					</span>
				) : null }
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
							disabled={ disabled }
							onClick={ () =>
								patch( {
									connectivity: {
										public_assets: {
											items,
											scope: value,
										},
									},
								} )
							}
						>
							{ label }
						</button>
					) ) }
				</div>
				<div className="chk" style={ { marginTop: 12 } }>
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
									patch( {
										connectivity: {
											public_assets: {
												items: next,
												scope,
											},
										},
									} );
								} }
							/>
							{ ASSET_LABEL[ item ] }
						</label>
					) ) }
				</div>
			</FieldRow>
			<AvatarField
				value={ avatarValue }
				disabled={ disabled }
				custom={ customs.includes( 'avatar' ) }
				onRestore={ restoreAvatar }
				defaultWord={ __( 'Cravatar 中国线路', 'wp-china-yes' ) }
				onChange={ ( value ) =>
					patch( {
						connectivity: {
							avatar: value,
						},
					} )
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
				<Toggle
					checked={ false }
					disabled
					label={ __( '启用 Windfonts', 'wp-china-yes' ) }
				/>
				<div className="hint">
					{ __( '绑定本站后可用', 'wp-china-yes' ) }
				</div>
			</FieldRow>
		</section>
	);
}

function AvatarField( {
	value,
	disabled,
	onChange,
	custom,
	onRestore,
	defaultWord,
} ) {
	const title = __( '头像', 'wp-china-yes' );
	const help = __( '评论与后台的头像源 · 由 Cravatar 提供', 'wp-china-yes' );
	const name = 'avatar';
	return (
		<FieldRow icon="user" title={ title } help={ help }>
			<div className="opts">
				<label className="opt" htmlFor={ name + '-cn' }>
					<input
						id={ name + '-cn' }
						type="radio"
						name={ name }
						checked={ value === 'cravatar_cn' }
						disabled={ disabled }
						onChange={ () => onChange( 'cravatar_cn' ) }
					/>
					<span>
						<span className="t">
							{ __( 'Cravatar 中国线路', 'wp-china-yes' ) }
						</span>
						<div className="d">
							{ __( 'cravatar.cn · 国内节点', 'wp-china-yes' ) }
						</div>
					</span>
				</label>
				<label className="opt" htmlFor={ name + '-global' }>
					<input
						id={ name + '-global' }
						type="radio"
						name={ name }
						checked={ value === 'cravatar_global' }
						disabled={ disabled }
						onChange={ () => onChange( 'cravatar_global' ) }
					/>
					<span>
						<span className="t">
							{ __( 'Cravatar 国际线路', 'wp-china-yes' ) }
						</span>
						<div className="d">
							{ __( 'cravatar.com · 全球节点', 'wp-china-yes' ) }
						</div>
					</span>
				</label>
				<label className="opt" htmlFor={ name + '-off' }>
					<input
						id={ name + '-off' }
						type="radio"
						name={ name }
						checked={ value === 'off' }
						disabled={ disabled }
						onChange={ () => onChange( 'off' ) }
					/>
					<span>
						<span className="t">
							{ __( '关闭', 'wp-china-yes' ) }
						</span>
						<div className="d">
							{ __( '保留 Gravatar', 'wp-china-yes' ) }
						</div>
					</span>
				</label>
			</div>
			{ custom ? (
				<div className="customized">
					{ __( '已自定义', 'wp-china-yes' ) }
					{ ' · ' }
					<button type="button" onClick={ onRestore }>
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
