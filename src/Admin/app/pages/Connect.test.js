/**
 * Settings page states: CO-01 save failure, CO-11 switch modal, suggestion
 * null branch, CO-06 subsite read-only and network-admin view.
 */

import apiFetch from '@wordpress/api-fetch';
import { dispatch } from '@wordpress/data';
import { STORE_NAME } from '../store';
import Connect from './Connect';
import {
	buttonsByText,
	cleanup,
	click,
	flush,
	inAct,
	render,
} from '../test-utils/render';
import { sceneDefaults } from '../ui/profiles';

jest.mock( '@wordpress/components', () =>
	require( '../test-utils/components-mock' )
);

jest.mock( '@wordpress/api-fetch', () => {
	const fn = jest.fn();
	fn.use = jest.fn();
	fn.createNonceMiddleware = jest.fn();
	fn.createRootURLMiddleware = jest.fn();
	return { __esModule: true, default: fn };
} );

function doc( scene = 'crossborder', extra = {} ) {
	const def = sceneDefaults( scene );
	return {
		profile: scene,
		profile_confirmed_at: '2026-10-19T00:00:00Z',
		connectivity: {
			wordpress_org: def.wordpress_org,
			public_assets: def.public_assets,
			avatar: def.avatar,
			heartbeat: def.heartbeat,
			dashboard_feeds: def.dashboard_feeds,
			admin_locale_follow: true,
			...( extra.connectivity || {} ),
		},
		admin: { hide_promo: true },
		modules: { windfonts: false },
		...( extra.root || {} ),
	};
}

let routes;
let calls;

function mockApi() {
	calls = [];
	apiFetch.mockImplementation( ( req ) => {
		calls.push( req );
		const key = ( req.method || 'GET' ) + ' ' + req.path;
		const handler = routes[ key ] || routes[ req.path ];
		if ( ! handler ) {
			return Promise.resolve( {} );
		}
		return handler( req );
	} );
}

function puts() {
	return calls.filter( ( c ) => c.method === 'PUT' );
}

afterEach( async () => {
	await cleanup();
} );

async function mount( settings, capabilities = {} ) {
	await inAct( () =>
		dispatch( STORE_NAME ).hydrate( {
			settings,
			capabilities: { manage_options: true, ...capabilities },
		} )
	);
	return render( <Connect /> );
}

beforeEach( () => {
	routes = {
		'/wp/v2/users/me?context=edit': () =>
			Promise.resolve( { meta: { wpcy_settings_view: 'simple' } } ),
		'/wpcy/v1/profile/suggest': () =>
			Promise.resolve( { suggestion: null } ),
		'/wpcy/v1/binding': () =>
			Promise.resolve( { status: 'unbound', site_hash: null } ),
	};
	mockApi();
	document.body.innerHTML = '';
} );

describe( 'CO-01 instant save', () => {
	it( 'failure rolls the switch back and shows the alert at the top of that card', async () => {
		routes[ 'PUT /wpcy/v1/settings' ] = () =>
			Promise.reject( { code: 'internal_server_error' } );
		const { container } = await mount( doc( 'crossborder' ) );
		const card = container.querySelector( '[data-card="admin"]' );
		const heart = card.querySelectorAll( '[role="switch"]' )[ 1 ];
		expect( heart.getAttribute( 'aria-checked' ) ).toBe( 'true' );

		await click( heart );

		expect( puts() ).toEqual( [
			{
				path: '/wpcy/v1/settings',
				method: 'PUT',
				data: { connectivity: { heartbeat: 'off' } },
			},
		] );
		const alert = card.querySelector( '[role="alert"]' );
		expect( alert.textContent ).toContain(
			'暂时无法保存设置，请稍后重试。'
		);
		expect(
			card.querySelector( '.card-alert' ).nextElementSibling
		).not.toBeNull();
		expect(
			card
				.querySelectorAll( '[role="switch"]' )[ 1 ]
				.getAttribute( 'aria-checked' )
		).toBe( 'true' );
		expect(
			container.querySelector( '[data-card="accel"] [role="alert"]' )
		).toBeNull();
		expect( document.querySelector( '.components-snackbar' ) ).toBeNull();
	} );

	it( 'schema 400 uses the schema sentence; 重试 resends the same partial object', async () => {
		let n = 0;
		routes[ 'PUT /wpcy/v1/settings' ] = ( req ) => {
			n++;
			if ( n === 1 ) {
				return Promise.reject( { code: 'wpcy_invalid_schema' } );
			}
			return Promise.resolve(
				doc( 'crossborder', { connectivity: req.data.connectivity } )
			);
		};
		const { container } = await mount( doc( 'crossborder' ) );
		const card = container.querySelector( '[data-card="accel"]' );
		await click( card.querySelectorAll( '[role="switch"]' )[ 0 ] );
		expect( card.querySelector( '[role="alert"]' ).textContent ).toContain(
			'暂时无法保存设置，请检查填写内容后重试。'
		);

		await click( buttonsByText( card, '重试' )[ 0 ] );

		expect( puts() ).toHaveLength( 2 );
		expect( puts()[ 1 ].data ).toEqual( puts()[ 0 ].data );
		expect( card.querySelector( '[role="alert"]' ) ).toBeNull();
		expect( card.textContent ).toContain( '已保存 ✓' );
	} );
} );

describe( 'CO-01 status line (admin-ui-spec §8)', () => {
	it( 'role=status is mounted from first paint; success only swaps its text', async () => {
		routes[ 'PUT /wpcy/v1/settings' ] = ( req ) =>
			Promise.resolve(
				doc( 'crossborder', { connectivity: req.data.connectivity } )
			);
		const { container } = await mount( doc( 'crossborder' ) );
		const card = container.querySelector( '[data-card="admin"]' );
		const lines = card.querySelectorAll( '.saved[role="status"]' );
		expect( lines.length ).toBeGreaterThan( 0 );
		lines.forEach( ( el ) => expect( el.textContent ).toBe( '' ) );
		const heart = card.querySelectorAll( '[role="switch"]' )[ 1 ];
		const line = heart.closest( '.row, .field, li, div' ).parentElement;
		const before = Array.from(
			card.querySelectorAll( '.saved[role="status"]' )
		);

		await click( heart );

		const after = Array.from(
			card.querySelectorAll( '.saved[role="status"]' )
		);
		expect( after ).toEqual( before );
		expect(
			after.filter( ( el ) => el.textContent === '已保存 ✓' )
		).toHaveLength( 1 );
		expect( line ).toBeTruthy();
	} );

	it( 'non-2xx: no 已保存 ✓', async () => {
		routes[ 'PUT /wpcy/v1/settings' ] = () =>
			Promise.reject( { code: 'rest_forbidden', data: { status: 403 } } );
		const { container } = await mount( doc( 'crossborder' ) );
		const card = container.querySelector( '[data-card="admin"]' );
		await click( card.querySelectorAll( '[role="switch"]' )[ 1 ] );
		expect( container.textContent ).not.toContain( '已保存 ✓' );
		expect( card.querySelector( '[role="alert"]' ) ).toBeTruthy();
	} );

	it.each( [
		[ 'wpcy_settings_locked', 409 ],
		[ 'wpcy_settings_network_scope', 403 ],
	] )(
		'%s (%d): reverts, no alert or 重试, lock sentence only on the changed item',
		async ( code, status ) => {
			routes[ 'PUT /wpcy/v1/settings' ] = () =>
				Promise.reject( {
					code,
					message: 'refused',
					data: { status, fields: [ 'connectivity' ] },
				} );
			const { container } = await mount( doc( 'crossborder' ) );
			const card = container.querySelector( '[data-card="admin"]' );
			const heart = card.querySelectorAll( '[role="switch"]' )[ 1 ];

			await click( heart );

			expect( heart.getAttribute( 'aria-checked' ) ).toBe( 'true' );
			expect( card.querySelector( '[role="alert"]' ) ).toBeNull();
			expect( buttonsByText( card, '重试' ) ).toHaveLength( 0 );
			expect( container.textContent ).not.toContain( '已保存 ✓' );
			const lines = Array.from(
				card.querySelectorAll( '.saved[role="status"]' )
			).filter( ( el ) => el.textContent !== '' );
			expect( lines ).toHaveLength( 1 );
			expect( lines[ 0 ].textContent ).toBe(
				'网络管理员已锁定此项，修改未保存。'
			);
		}
	);
} );

describe( 'CO-11 scene cards and switch modal', () => {
	it( 'renders four cards in a 2×2 grid and the wizard entry', async () => {
		const { container } = await mount( doc() );
		const grid = container.querySelector(
			'[data-card="scene"] .choice-cards'
		);
		expect( grid.classList.contains( 'cols-2' ) ).toBe( true );
		expect( grid.querySelectorAll( '.choice' ) ).toHaveLength( 4 );
		expect( container.textContent ).toContain(
			'拿不准？跑一次 30 秒设置向导'
		);
		expect( container.textContent ).toContain(
			'已按「跨境 · 外贸站」配好 · 改动即时生效，单项仍可调整'
		);
	} );

	it( 'suggestion null: no suggestion sentence, no 应用建议', async () => {
		const { container } = await mount( doc() );
		expect( container.textContent ).toContain(
			'切换场景会把下面各项重置为该场景的默认组合。'
		);
		expect( container.textContent ).not.toContain( '我们建议' );
		expect( buttonsByText( container, '应用建议' ) ).toHaveLength( 0 );
	} );

	it( 'suggestion present: spec sentence and 应用建议', async () => {
		routes[ '/wpcy/v1/profile/suggest' ] = () =>
			Promise.resolve( { suggestion: 'domestic' } );
		const { container } = await mount( doc() );
		expect( container.textContent ).toContain(
			'根据服务器位置和你的浏览器，我们建议：国内站。'
		);
		expect( buttonsByText( container, '应用建议' ) ).toHaveLength( 1 );
	} );

	it( 'cancel and close send nothing; confirm sends one PUT {profile}; modal shows customs', async () => {
		routes[ 'PUT /wpcy/v1/settings' ] = () =>
			Promise.resolve( doc( 'domestic' ) );
		const { container } = await mount(
			doc( 'crossborder', { connectivity: { avatar: 'off' } } )
		);
		const radio = container.querySelector( '#wpcy-profile-domestic' );

		await click( radio );
		let modal = document.querySelector( '.components-modal__frame' );
		expect( modal.textContent ).toContain( '切换到「国内站」？' );
		expect( modal.textContent ).toContain(
			'改过的设置在切换场景后会恢复默认（当前 1 项：头像）。'
		);
		await click( buttonsByText( modal, '取消' )[ 0 ] );
		expect(
			document.querySelector( '.components-modal__frame' )
		).toBeNull();

		await click( radio );
		modal = document.querySelector( '.components-modal__frame' );
		const close = modal.querySelector( 'button[aria-label="关闭"]' );
		expect( close ).not.toBeNull();
		await click( close );
		expect( puts() ).toHaveLength( 0 );

		await click( radio );
		modal = document.querySelector( '.components-modal__frame' );
		await click( buttonsByText( modal, '切换' ).pop() );
		await flush();
		expect( puts() ).toEqual( [
			{
				path: '/wpcy/v1/settings',
				method: 'PUT',
				data: { profile: 'domestic' },
			},
		] );
		expect( container.textContent ).toContain( '已按「国内站」配好' );
	} );
} );

describe( 'CO-13 simple rows', () => {
	it( 'row titles from the spec, no third-party provider chips', async () => {
		const { container } = await mount( doc() );
		const accel = container.querySelector( '[data-card="accel"]' );
		const titles = Array.from(
			accel.querySelectorAll( '.simple-row .t' )
		).map( ( n ) => n.textContent );
		expect( titles ).toEqual( [
			'WordPress 更新与安装',
			'公共库接通',
			'头像接通',
			'中文字体（Windfonts）',
		] );
		expect( accel.querySelector( '.prov' ) ).toBeNull();
		expect( accel.textContent ).not.toMatch(
			/adminCDN|Cravatar|WenPai\.org/
		);
		expect( accel.textContent ).toContain( '绑定本站后可用' );
		const admin = Array.from(
			container.querySelectorAll( '[data-card="admin"] .simple-row .t' )
		).map( ( n ) => n.textContent );
		expect( admin ).toEqual( [
			'隐藏后台的推广与广告',
			'减少后台心跳',
			'不加载仪表盘的外部内容',
			'后台语言跟随管理员',
		] );
	} );

	it( 'bound site can switch Windfonts on', async () => {
		routes[ '/wpcy/v1/binding' ] = () =>
			Promise.resolve( { status: 'bound', site_hash: 'abcd1234ef' } );
		routes[ 'PUT /wpcy/v1/settings' ] = () =>
			Promise.resolve( {
				...doc(),
				modules: { windfonts: true },
			} );
		const { container } = await mount( doc() );
		const accel = container.querySelector( '[data-card="accel"]' );
		expect( accel.textContent ).not.toContain( '绑定本站后可用' );
		const font = accel.querySelectorAll( '[role="switch"]' )[ 2 ];
		expect( font.disabled ).toBe( false );
		await click( font );
		expect( puts()[ 0 ].data ).toEqual( { modules: { windfonts: true } } );
	} );

	it( 'binding read failure shows SH-06 and keeps the switch disabled (L-7 ③)', async () => {
		routes[ '/wpcy/v1/binding' ] = () => Promise.reject( { code: 'x' } );
		const { container } = await mount( doc() );
		const accel = container.querySelector( '[data-card="accel"]' );
		expect( accel.textContent ).toContain(
			'暂时无法读取绑定状态，请刷新页面重试。'
		);
		expect( accel.textContent ).not.toContain( '绑定本站后可用' );
		expect(
			accel.querySelectorAll( '[role="switch"]' )[ 2 ].disabled
		).toBe( true );
	} );
} );

describe( 'CO-06', () => {
	it( 'subsite read-only: lede, info notice, pills, everything disabled, no wizard', async () => {
		const { container } = await mount( doc(), { network_locked: true } );
		expect( container.textContent ).toContain(
			'已由网络设定 · 本站不能单独修改'
		);
		expect( container.textContent ).toContain(
			'需要本站单独调整时，请联系网络管理员在网络后台开放「允许子站覆盖」。'
		);
		expect(
			container.querySelectorAll( '.wpcy-locked-pill' )
		).toHaveLength( 3 );
		expect( container.querySelector( '.mode' ) ).toBeNull();
		Array.from(
			container.querySelectorAll( '[role="switch"], input' )
		).forEach( ( el ) => expect( el.disabled ).toBe( true ) );
		expect( container.textContent ).not.toContain( '设置向导' );
		expect(
			calls.some( ( c ) => c.path === '/wpcy/v1/profile/suggest' )
		).toBe( false );
	} );

	it( 'network admin: override card, network lede, network-settings writes', async () => {
		routes[ '/wpcy/v1/network-settings' ] = () =>
			Promise.resolve( {
				...doc(),
				allow_site_override: true,
			} );
		routes[ 'PUT /wpcy/v1/network-settings' ] = ( req ) =>
			Promise.resolve( { ...doc(), ...req.data } );
		const { container } = await mount( doc(), {
			network_admin: true,
			manage_network_options: true,
		} );
		expect( container.textContent ).toContain( '网络策略，子站可覆盖' );
		const card = container.querySelector( '[data-card="override"]' );
		expect( card.textContent ).toContain( '子站覆盖' );
		expect( card.textContent ).toContain( '下面是全网络的默认设置。' );
		expect( card.textContent ).toContain( '允许子站覆盖' );
		expect( container.querySelector( '.mode' ) ).not.toBeNull();
		expect( container.textContent ).not.toContain( '设置向导' );
		expect( container.textContent ).toContain( '各子站绑定后可用' );
		expect( container.querySelector( '.wpcy-tabs' ) ).toBeNull();

		await click( card.querySelector( '[role="switch"]' ) );
		expect( puts() ).toEqual( [
			{
				path: '/wpcy/v1/network-settings',
				method: 'PUT',
				data: { allow_site_override: false },
			},
		] );
		expect(
			card
				.querySelector( '[role="switch"]' )
				.getAttribute( 'aria-checked' )
		).toBe( 'false' );
	} );
} );
