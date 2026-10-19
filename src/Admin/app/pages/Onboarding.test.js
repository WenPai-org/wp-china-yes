/**
 * Wizard states: WZ-01 suggestion null / present / save failure, WZ-03
 * binding poll (pending → bound, failed), WZ-04 dynamic count, WZ-05 Woo rows.
 */

import apiFetch from '@wordpress/api-fetch';
import { dispatch } from '@wordpress/data';
import { STORE_NAME } from '../store';
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

describe( 'WZ-04', () => {
	it( 'resource count comes from the saved settings, not a fixed 4', async () => {
		routes[ 'PUT /wpcy/v1/settings' ] = ( req ) =>
			Promise.resolve(
				req.data.profile === 'domestic'
					? settingsFor(
							'domestic',
							[
								'google_fonts',
								'google_ajax',
								'cdnjs',
								'jsdelivr',
								'emoji',
							],
							'both'
					  )
					: settingsFor(
							'crossborder',
							[ 'google_fonts', 'google_ajax' ],
							'admin'
					  )
			);
		const counts = {};
		for ( const scene of [ 'domestic', 'crossborder' ] ) {
			const { container, unmount } = await mount();
			await goToBindStep( container, scene );
			await click( buttonsByText( container, '暂不，稍后再说' )[ 0 ] );
			counts[ scene ] = container.querySelector(
				'[data-testid="wpcy-wz-count"]'
			).textContent;
			expect( container.textContent ).toContain( '稍后可在服务页绑定' );
			await unmount();
		}
		expect( counts ).toEqual( { domestic: '6 项', crossborder: '3 项' } );
	} );
} );
