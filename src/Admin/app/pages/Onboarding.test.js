/**
 * Wizard states: WZ-01 suggestion null / present / save failure, WZ-03
 * binding poll (pending → bound, failed), WZ-04 dynamic count, WZ-05 Woo rows.
 */

import apiFetch from '@wordpress/api-fetch';
import { dispatch } from '@wordpress/data';
import { STORE_NAME } from '../store';
import { sceneDefaults } from '../ui/profiles';
import Onboarding, { BINDING_POLL_MS } from './Onboarding';
import {
	act,
	buttonsByText,
	cleanup,
	click,
	flush,
	inAct,
	render,
} from '../test-utils/render';

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

let routes;
let calls;

function settingsFor( scene, items, scope ) {
	return {
		profile: scene,
		connectivity: {
			wordpress_org: scene === 'domestic' ? 'auto' : 'off',
			public_assets: { items, scope },
			avatar: 'cravatar_cn',
		},
	};
}

beforeEach( () => {
	calls = [];
	routes = {
		'/wpcy/v1/profile/suggest': () =>
			Promise.resolve( { suggestion: null } ),
		'/wpcy/v1/binding': () =>
			Promise.resolve( { status: 'unbound', site_hash: null } ),
		'PUT /wpcy/v1/settings': ( req ) =>
			Promise.resolve(
				settingsFor(
					req.data.profile,
					[
						'google_fonts',
						'google_ajax',
						'cdnjs',
						'jsdelivr',
						'emoji',
					],
					'both'
				)
			),
	};
	apiFetch.mockImplementation( ( req ) => {
		calls.push( req );
		const key = ( req.method || 'GET' ) + ' ' + req.path;
		const handler = routes[ key ] || routes[ req.path ];
		return handler ? handler( req ) : Promise.resolve( {} );
	} );
	document.body.innerHTML = '';
} );

afterEach( async () => {
	await cleanup();
	jest.useRealTimers();
} );

async function mount( plugins = [] ) {
	await inAct( () =>
		dispatch( STORE_NAME ).hydrate( {
			settings: { profile: 'domestic', profile_confirmed_at: null },
			siteContext: { active_plugins: plugins },
		} )
	);
	return render( <Onboarding /> );
}

function primary( root ) {
	return root.querySelectorAll( '.wizard .actions .btn-primary' );
}

async function goToBindStep( container, scene = 'crossborder' ) {
	await click( container.querySelector( '#ob-p-' + scene ) );
	await click( buttonsByText( container, '继续' )[ 0 ] );
	await click( buttonsByText( container, '继续' )[ 0 ] );
	await click( buttonsByText( container, '继续' )[ 0 ] );
}

describe( 'WZ-01', () => {
	it( 'suggestion null: nothing preselected, no hint, 继续 disabled', async () => {
		const { container } = await mount();
		expect( container.querySelector( 'h1' ).textContent ).toBe(
			'你的站点在哪里？'
		);
		expect( container.querySelectorAll( '.choice' ) ).toHaveLength( 4 );
		expect( container.querySelectorAll( 'input:checked' ) ).toHaveLength(
			0
		);
		expect( container.textContent ).not.toContain( '我们建议' );
		expect( primary( container ) ).toHaveLength( 1 );
		expect( primary( container )[ 0 ].disabled ).toBe( true );
		expect( container.querySelectorAll( '.steps .st' ) ).toHaveLength( 5 );
	} );

	it( 'suggestion present: preselects and shows the spec sentence', async () => {
		routes[ '/wpcy/v1/profile/suggest' ] = () =>
			Promise.resolve( { suggestion: 'crossborder' } );
		const { container } = await mount();
		expect( container.querySelector( '#ob-p-crossborder' ).checked ).toBe(
			true
		);
		expect( container.textContent ).toContain(
			'根据服务器位置和你的浏览器，我们建议：跨境 · 外贸站。'
		);
		expect( primary( container )[ 0 ].disabled ).toBe( false );
	} );

	it.each( [
		[ 'wpcy_settings_locked', 409 ],
		[ 'wpcy_settings_network_scope', 403 ],
	] )(
		'%s (%d): locked status line + 返回设置, no 已按…配置好',
		async ( code, status ) => {
			routes[ 'PUT /wpcy/v1/settings' ] = () =>
				Promise.reject( {
					code,
					message: 'refused',
					data: { status, fields: [ 'profile' ] },
				} );
			const { container } = await mount();
			const line = container.querySelector( '.wizard-lock' );
			expect( line.getAttribute( 'role' ) ).toBe( 'status' );
			expect( line.textContent ).toBe( '' );
			await click( container.querySelector( '#ob-p-mixed' ) );
			await click( buttonsByText( container, '继续' )[ 0 ] );
			// Same node, only its text changed.
			expect( container.querySelector( '.wizard-lock' ) ).toBe( line );
			expect( line.textContent ).toBe(
				'网络管理员已锁定本站设置，向导里的选择没有保存。'
			);
			expect( container.querySelector( '[role="alert"]' ) ).toBeNull();
			expect( buttonsByText( container, '重试' ) ).toHaveLength( 0 );
			const back = [ ...container.querySelectorAll( 'a' ) ].find(
				( a ) => a.textContent.trim() === '返回设置'
			);
			expect( back ).toBeTruthy();
			expect( back.getAttribute( 'href' ) ).toContain(
				'page=wpcy-connect'
			);
			expect( container.textContent ).not.toContain( '配置好' );
			expect( container.querySelector( 'h1' ).textContent ).toBe(
				'你的站点在哪里？'
			);
			// One refused PUT, nothing else written (no profile_set, no
			// retry, no event call from the client).
			const writes = calls.filter(
				( req ) => req.method && req.method !== 'GET'
			);
			expect( writes ).toHaveLength( 1 );
			expect( writes[ 0 ].path ).toBe( '/wpcy/v1/settings' );
		}
	);

	it( 'save failure: red alert under the cards, stays on step 1', async () => {
		routes[ 'PUT /wpcy/v1/settings' ] = () =>
			Promise.reject( { code: 'rest_error' } );
		const { container } = await mount();
		await click( container.querySelector( '#ob-p-mixed' ) );
		await click( buttonsByText( container, '继续' )[ 0 ] );
		const alert = container.querySelector( '[role="alert"]' );
		expect( alert.textContent ).toContain(
			'暂时无法保存设置，请稍后重试。'
		);
		expect( container.querySelector( 'h1' ).textContent ).toBe(
			'你的站点在哪里？'
		);
	} );

	it( 'continue sends PUT {profile} once and shows WZ-02 rows', async () => {
		const { container } = await mount();
		await click( container.querySelector( '#ob-p-mixed' ) );
		await click( buttonsByText( container, '继续' )[ 0 ] );
		const puts = calls.filter( ( c ) => c.method === 'PUT' );
		expect( puts ).toEqual( [
			{
				path: '/wpcy/v1/settings',
				method: 'PUT',
				data: { profile: 'mixed' },
			},
		] );
		expect( container.querySelector( 'h1' ).textContent ).toBe(
			'已按「混合站」配置好'
		);
		expect( container.textContent ).toContain( '更新与安装包走国内镜像' );
	} );
} );

describe( 'WZ-05', () => {
	it( 'crossborder without WooCommerce hides the two Woo rows', async () => {
		const { container } = await mount();
		await click( container.querySelector( '#ob-p-crossborder' ) );
		await click( buttonsByText( container, '继续' )[ 0 ] );
		await click( buttonsByText( container, '继续' )[ 0 ] );
		expect( container.querySelector( 'h1' ).textContent ).toBe(
			'跨境店常用的，还可以加'
		);
		expect( container.textContent ).not.toContain(
			'微信支付 for WooCommerce'
		);
		expect( container.textContent ).toContain( '中文字体（Windfonts）' );
	} );

	it( 'crossborder with WooCommerce shows them', async () => {
		const { container } = await mount( [ 'woocommerce/woocommerce.php' ] );
		await click( container.querySelector( '#ob-p-crossborder' ) );
		await click( buttonsByText( container, '继续' )[ 0 ] );
		await click( buttonsByText( container, '继续' )[ 0 ] );
		expect( container.textContent ).toContain( '微信支付 for WooCommerce' );
		expect( container.textContent ).toContain( '订单微信通知' );
	} );
} );

describe( 'WZ-03 binding', () => {
	it( 'pending → poll every 3 s → bound → step 5 with the site id', async () => {
		jest.useFakeTimers();
		let polls = 0;
		routes[ 'POST /wpcy/v1/binding/start' ] = () =>
			Promise.resolve( { status: 'pending', challenge_id: 'c1' } );
		const { container } = await mount();
		await goToBindStep( container );
		routes[ '/wpcy/v1/binding' ] = () => {
			polls++;
			return Promise.resolve(
				polls < 2
					? { status: 'pending' }
					: { status: 'bound', site_hash: 'a3f9000000000000c21e' }
			);
		};
		await click( buttonsByText( container, '绑定本站' ).pop() );

		expect( container.textContent ).toContain( '正在绑定本站' );
		expect( container.textContent ).toContain(
			'等待文派服务器验证，通常几秒内完成'
		);
		expect( container.textContent ).toContain( '等待验证' );
		expect( container.textContent ).toContain(
			'如果超过一分钟没有完成，可以取消后重试；不影响任何加速功能'
		);
		const actionBtns = container.querySelectorAll(
			'.wizard .actions .btn'
		);
		expect( actionBtns ).toHaveLength( 1 );
		expect( actionBtns[ 0 ].textContent ).toBe( '取消' );
		expect( primary( container ) ).toHaveLength( 0 );

		await act( async () => {
			jest.advanceTimersByTime( BINDING_POLL_MS );
		} );
		await flush();
		expect( container.textContent ).toContain( '正在绑定本站' );
		await act( async () => {
			jest.advanceTimersByTime( BINDING_POLL_MS );
		} );
		await flush();

		expect( polls ).toBe( 2 );
		expect( container.querySelector( 'h1' ).textContent ).toBe(
			'一切就绪'
		);
		expect( container.textContent ).toContain( '已绑定' );
		expect( container.textContent ).toContain( '站点标识 a3f9…c21e' );
	} );

	it( 'failed → back to the default step with the red alert', async () => {
		jest.useFakeTimers();
		routes[ 'POST /wpcy/v1/binding/start' ] = () =>
			Promise.resolve( { status: 'pending' } );
		const { container } = await mount();
		await goToBindStep( container );
		routes[ '/wpcy/v1/binding' ] = () =>
			Promise.resolve( { status: 'failed' } );
		await click( buttonsByText( container, '绑定本站' ).pop() );
		await act( async () => {
			jest.advanceTimersByTime( BINDING_POLL_MS );
		} );
		await flush();
		expect(
			container.querySelector( '[role="alert"]' ).textContent
		).toContain( '暂时无法完成站点绑定，请稍后重试。' );
		expect( buttonsByText( container, '绑定本站' ).length ).toBeGreaterThan(
			0
		);
		expect( container.querySelector( 'h1' ).textContent ).toBe(
			'要绑定本站吗？'
		);
	} );

	it( 'cancel sends DELETE /binding and returns to the default state', async () => {
		routes[ 'POST /wpcy/v1/binding/start' ] = () =>
			Promise.resolve( { status: 'pending' } );
		routes[ 'DELETE /wpcy/v1/binding' ] = () =>
			Promise.resolve( { status: 'revoked' } );
		const { container } = await mount();
		await goToBindStep( container );
		await click( buttonsByText( container, '绑定本站' ).pop() );
		await click( buttonsByText( container, '取消' )[ 0 ] );
		expect(
			calls.some(
				( c ) => c.method === 'DELETE' && c.path === '/wpcy/v1/binding'
			)
		).toBe( true );
		expect( container.textContent ).not.toContain( '正在绑定本站' );
	} );
} );

describe( 'WZ-03 step 4 states', () => {
	it( 'one live region on step 4; only its text changes', async () => {
		routes[ 'POST /wpcy/v1/binding/start' ] = () =>
			Promise.resolve( { status: 'pending' } );
		const { container } = await mount();
		await goToBindStep( container );
		const regions = () =>
			container.querySelectorAll(
				'.wizard [role="status"], .wizard [aria-live]'
			);
		expect( regions() ).toHaveLength( 1 );
		const region = regions()[ 0 ];
		expect( region.textContent ).toBe( '' );

		await click( buttonsByText( container, '绑定本站' ).pop() );

		expect( regions() ).toHaveLength( 1 );
		expect( regions()[ 0 ] ).toBe( region );
		expect( region.textContent ).toBe( '正在绑定本站' );
	} );

	it( 'already bound: bound state and 继续, not the bind question', async () => {
		routes[ '/wpcy/v1/binding' ] = () =>
			Promise.resolve( {
				status: 'bound',
				site_hash: 'a3f9000000000000c21e',
			} );
		const { container } = await mount();
		await goToBindStep( container );
		expect( container.querySelector( 'h1' ).textContent ).toBe(
			'本站已绑定'
		);
		expect( container.textContent ).not.toContain( '要绑定本站吗？' );
		expect( container.textContent ).toContain( '站点标识 a3f9…c21e' );
		expect(
			buttonsByText( container, '绑定本站' ).filter(
				( b ) => b.tagName === 'BUTTON'
			)
		).toHaveLength( 0 );
		await click( buttonsByText( container, '继续' )[ 0 ] );
		expect( container.querySelector( 'h1' ).textContent ).toBe(
			'一切就绪'
		);
	} );

	it( 'a leftover pending does not poll or jump from step 1', async () => {
		jest.useFakeTimers();
		let reads = 0;
		routes[ '/wpcy/v1/binding' ] = () => {
			reads++;
			return Promise.resolve(
				reads < 2
					? { status: 'pending' }
					: { status: 'bound', site_hash: 'a3f9000000000000c21e' }
			);
		};
		const { container } = await mount();
		await act( async () => {
			jest.advanceTimersByTime( BINDING_POLL_MS * 3 );
		} );
		await flush();
		expect( reads ).toBe( 1 );
		expect( container.querySelector( 'h1' ).textContent ).toBe(
			'你的站点在哪里？'
		);
	} );
} );

describe( 'WZ-04', () => {
	it( 'count follows what each scene preset changed', async () => {
		routes[ 'PUT /wpcy/v1/settings' ] = ( req ) => {
			const def = sceneDefaults( req.data.profile );
			return Promise.resolve( {
				profile: req.data.profile,
				connectivity: {
					wordpress_org: def.wordpress_org,
					public_assets: def.public_assets,
					avatar: def.avatar,
					heartbeat: def.heartbeat,
					dashboard_feeds: def.dashboard_feeds,
					icon_photos: def.icon_photos,
				},
				modules: { windfonts: def.windfonts },
			} );
		};
		const counts = {};
		for ( const scene of [
			'domestic',
			'crossborder',
			'inbound',
			'mixed',
		] ) {
			const { container, unmount } = await mount();
			await goToBindStep( container, scene );
			await click( buttonsByText( container, '暂不，稍后再说' )[ 0 ] );
			counts[ scene ] = container.querySelector(
				'[data-testid="wpcy-wz-count"]'
			).textContent;
			expect( container.textContent ).toContain( '稍后可在服务页绑定' );
			await unmount();
		}
		expect( counts ).toEqual( {
			domestic: '8 项',
			crossborder: '9 项',
			inbound: '7 项',
			mixed: '10 项',
		} );
	} );
} );
