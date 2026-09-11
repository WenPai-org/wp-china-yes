/**
 * First-run wizard: 5 steps, step 3 is the scene pack.
 */

/* eslint-disable jsx-a11y/label-has-associated-control -- prototype choice cards wrap radio + nested copy */

import { __, sprintf } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import PageShell from '../components/PageShell';
import { STORE_NAME } from '../store';
import { adminPageUrl, PAGES } from '../routing';
import Icon from '../ui/icons';
import Btn from '../ui/Btn';
import { Tile } from '../ui/Card';
import Notice from '../ui/Notice';
import { profileLabel, sceneCards } from '../ui/profiles';

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

function packTitle( profile ) {
	if ( profile === 'crossborder' ) {
		return __( '跨境店常用的，还可以加', 'wp-china-yes' );
	}
	if ( profile === 'inbound' ) {
		return __( '面向中国买家的，还可以加', 'wp-china-yes' );
	}
	return __( '可能还需要', 'wp-china-yes' );
}

function doneRows( profile ) {
	if ( profile === 'domestic' ) {
		return [
			{
				t: __( '更新与安装包走国内镜像', 'wp-china-yes' ),
				d: __( '不可用时自动回 WordPress.org', 'wp-china-yes' ),
			},
			{
				t: __( '前端公共库走国内可达源', 'wp-china-yes' ),
				d: __(
					'Google Fonts、Ajax、jsDelivr、Emoji 在前台和后台都接通',
					'wp-china-yes'
				),
			},
			{
				t: __( '头像走 Cravatar 中国线路', 'wp-china-yes' ),
				d: __( '前台后台都不再空头像', 'wp-china-yes' ),
			},
			{
				t: __( '后台保持 WordPress 默认', 'wp-china-yes' ),
				d: __( '心跳与仪表盘内容不改动', 'wp-china-yes' ),
			},
		];
	}
	if ( profile === 'inbound' ) {
		return [
			{
				t: __( 'WordPress.org 直连', 'wp-china-yes' ),
				d: __(
					'你的服务器在海外，直连更快；国内镜像已关闭',
					'wp-china-yes'
				),
			},
			{
				t: __( '前台公共库走国内可达源', 'wp-china-yes' ),
				d: __(
					'Google Fonts、Ajax、jsDelivr 只在前台接通；后台不动',
					'wp-china-yes'
				),
			},
			{
				t: __( '前台头像走 Cravatar', 'wp-china-yes' ),
				d: __( '后台保留 Gravatar', 'wp-china-yes' ),
			},
			{
				t: __( '后台保持 WordPress 默认', 'wp-china-yes' ),
				d: __( '心跳与仪表盘内容不改动', 'wp-china-yes' ),
			},
		];
	}
	return [
		{
			t: __( 'WordPress.org 直连', 'wp-china-yes' ),
			d: __(
				'你的服务器在海外，直连更快；国内镜像已关闭',
				'wp-china-yes'
			),
		},
		{
			t: __( '后台公共库走国内可达源', 'wp-china-yes' ),
			d: __(
				'Google Fonts、Ajax、jsDelivr 只在后台接通；海外访客看到的前台不变',
				'wp-china-yes'
			),
		},
		{
			t: __( '后台头像走 Cravatar', 'wp-china-yes' ),
			d: __( '前台保留 Gravatar', 'wp-china-yes' ),
		},
		{
			t: __( '后台心跳已节流，仪表盘外部内容已屏蔽', 'wp-china-yes' ),
			d: __( '减少你在国内操作海外后台的等待', 'wp-china-yes' ),
		},
	];
}

function packRows( profile ) {
	if ( profile === 'crossborder' || profile === 'inbound' ) {
		return [
			{
				icon: 'card',
				t: __( '微信支付 for WooCommerce', 'wp-china-yes' ),
				d: __(
					'让中国买家在你的海外店用微信付款 · 薇晓朵商城',
					'wp-china-yes'
				),
			},
			{
				icon: 'bell',
				t: __( '订单微信通知', 'wp-china-yes' ),
				d: __(
					'新订单、退款实时推送到微信 · 薇晓朵商城',
					'wp-china-yes'
				),
			},
			{
				icon: 'font',
				t: __( '中文字体（Windfonts）', 'wp-china-yes' ),
				d: __(
					'给中国买家看到的页面更好的中文字体 · 绑定后可用',
					'wp-china-yes'
				),
			},
		];
	}
	return [
		{
			icon: 'font',
			t: __( '中文字体（Windfonts）', 'wp-china-yes' ),
			d: __( '让站点用上更好看的中文字体 · 绑定后可用', 'wp-china-yes' ),
		},
		{
			icon: 'grid',
			t: __( '小工具', 'wp-china-yes' ),
			d: __( '连接测速、字体预览，在沙箱中运行', 'wp-china-yes' ),
		},
	];
}

export default function Onboarding() {
	const settings = useSelect(
		( select ) => select( STORE_NAME ).getSettings() || {},
		[]
	);
	const { patchSettings } = useDispatch( STORE_NAME );
	const [ step, setStep ] = useState( 0 );
	const [ profile, setProfile ] = useState( settings.profile || '' );
	const [ suggest, setSuggest ] = useState( null );
	const [ bound, setBound ] = useState( false );
	const [ pending, setPending ] = useState( false );

	useEffect( () => {
		apiFetch( { path: '/wpcy/v1/profile/suggest' } )
			.then( ( body ) => {
				setSuggest( body );
				if ( body?.suggestion && ! profile ) {
					setProfile( body.suggestion );
				}
			} )
			.catch( () => {} );
		apiFetch( { path: '/wpcy/v1/binding' } )
			.then( ( body ) => {
				if ( body?.status === 'bound' ) {
					setBound( true );
				}
			} )
			.catch( () => {} );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const cards = sceneCards();
	const current = profile || 'domestic';

	return (
		<PageShell showTabs={ false }>
			<main className="wizard">
				<div className="steps">
					{ STEPS.map( ( label, i ) => (
						<span
							key={ label }
							style={ {
								display: 'contents',
							} }
						>
							{ i > 0 ? <div className="bar" /> : null }
							<div className={ stepClass( i, step ) }>
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
						{ suggest?.suggestion ? (
							<Notice tone="info">
								{ sprintf(
									/* translators: %s: scene */
									__(
										'侦测到服务器在海外、你在国内管理——建议选「%s」。',
										'wp-china-yes'
									),
									profileLabel( suggest.suggestion )
								) }
							</Notice>
						) : null }
						<div className="actions">
							<Btn
								variant="primary"
								disabled={ ! profile }
								onClick={ () => {
									patchSettings( { profile } );
									setStep( 1 );
								} }
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
									<div key={ row.t }>
										<Tile tone="ok" icon="check" />
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
								{ packRows( current ).map( ( row ) => (
									<div key={ row.t }>
										<Tile tone="accent" icon={ row.icon } />
										<div>
											<div className="t">{ row.t }</div>
											<div className="d">{ row.d }</div>
										</div>
										<Btn
											variant="ghost"
											className="r"
											href={ adminPageUrl(
												PAGES.services
											) }
										>
											{ __( '了解', 'wp-china-yes' ) }{ ' ' }
											<Icon name="arrow" size={ 16 } />
										</Btn>
									</div>
								) ) }
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
						<h1>{ __( '要绑定本站吗？', 'wp-china-yes' ) }</h1>
						<p className="lede">
							{ __(
								'绑定后可使用文派服务与小工具。绑定是匿名的，不需要注册账号；数据保存在本站，随时可解除。',
								'wp-china-yes'
							) }
						</p>
						<article className="card">
							<div className="rows">
								<div>
									<Tile tone="accent" icon="grid" />
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
									<Tile tone="accent" icon="link" />
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
						<div className="actions">
							<Btn
								variant="primary"
								disabled={ pending }
								onClick={ async () => {
									setPending( true );
									try {
										await apiFetch( {
											path: '/wpcy/v1/binding/start',
											method: 'POST',
										} );
										setBound( true );
									} catch ( err ) {
										void err;
									} finally {
										setPending( false );
										setStep( 4 );
									}
								} }
							>
								{ __( '绑定本站', 'wp-china-yes' ) }
							</Btn>
							<Btn variant="ghost" onClick={ () => setStep( 4 ) }>
								{ __( '暂不，稍后再说', 'wp-china-yes' ) }
							</Btn>
						</div>
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
						<div className="wpcy-grid-3">
							<article className="card stat-a">
								<div className="k">
									<Icon name="globe" size={ 16 } />
									{ __( '更新', 'wp-china-yes' ) }
								</div>
								<div className="v">
									{ current === 'domestic'
										? __( '国内镜像', 'wp-china-yes' )
										: __( '直连', 'wp-china-yes' ) }
								</div>
								<div className="d">WordPress.org</div>
							</article>
							<article className="card stat-a">
								<div className="k">
									<Icon name="bolt" size={ 16 } />
									{ current === 'domestic'
										? __( '前端资源', 'wp-china-yes' )
										: __( '后台资源', 'wp-china-yes' ) }
								</div>
								<div className="v">
									4 { __( '项', 'wp-china-yes' ) }
								</div>
								<div className="d">
									{ __( '已走国内可达源', 'wp-china-yes' ) }
								</div>
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
										? __( '站点标识', 'wp-china-yes' )
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
								onClick={ () =>
									patchSettings( {
										profile: current,
									} )
								}
							>
								{ __( '进入概览', 'wp-china-yes' ) }
							</Btn>
						</div>
					</>
				) : null }
			</main>
		</PageShell>
	);
}
