/**
 * WZ-02 rows follow sceneDefaults(); WZ-05 WooCommerce gating; WZ-04 count.
 */

import {
	doneRows,
	hasWoo,
	maskHash,
	packRows,
	packTitle,
	presetChanges,
	resourceSummary,
} from './wizard';
import { sceneDefaults } from './profiles';

const SCENES = [ 'domestic', 'crossborder', 'inbound', 'mixed' ];

describe( 'doneRows', () => {
	it.each( SCENES )( '%s has four rows built from the matrix', ( scene ) => {
		const def = sceneDefaults( scene );
		const rows = doneRows( scene );
		expect( rows ).toHaveLength( 4 );
		expect( rows.map( ( r ) => r.key ) ).toEqual( [
			'wordpress_org',
			'public_assets.scope',
			'avatar',
			'heartbeat+dashboard_feeds',
		] );
		expect( rows[ 0 ].value ).toBe( def.wordpress_org );
		expect( rows[ 1 ].value ).toBe( def.public_assets.scope );
		expect( rows[ 2 ].value ).toBe( def.avatar );
		expect( rows[ 3 ].value ).toBe(
			def.heartbeat + '+' + def.dashboard_feeds
		);
		rows.forEach( ( r ) => {
			expect( r.t ).toBeTruthy();
			expect( r.d ).toBeTruthy();
		} );
	} );

	it( 'domestic copy (§5.2 WZ-02 国内)', () => {
		expect( doneRows( 'domestic' ).map( ( r ) => r.t ) ).toEqual( [
			'更新与安装包走国内镜像',
			'前端公共库走国内可达源',
			'头像走 Cravatar 中国线路',
			'后台保持 WordPress 默认',
		] );
	} );

	it( 'crossborder copy (§5.2 WZ-02 跨境)', () => {
		expect( doneRows( 'crossborder' ).map( ( r ) => r.t ) ).toEqual( [
			'WordPress.org 直连',
			'后台公共库走国内可达源',
			'头像走 Cravatar 中国线路',
			'后台心跳已节流，仪表盘外部内容已屏蔽',
		] );
	} );

	it( 'inbound copy (L-2)', () => {
		const rows = doneRows( 'inbound' );
		expect( rows.map( ( r ) => r.t ) ).toEqual( [
			'WordPress.org 直连',
			'前台公共库走国内可达源',
			'头像走 Cravatar 中国线路',
			'后台保持 WordPress 默认',
		] );
		expect( rows[ 1 ].d ).toBe(
			'Google Fonts、Ajax、jsDelivr 只在前台接通；后台不动'
		);
	} );

	it( 'mixed first row is the mirror, not 直连 (L-1 / L-2)', () => {
		const rows = doneRows( 'mixed' );
		expect( rows[ 0 ].t ).toBe( '更新与安装包走国内镜像' );
		expect( rows[ 0 ].d ).toBe( '不可用时自动回 WordPress.org' );
		expect( rows[ 1 ].t ).toBe( '后台公共库走国内可达源' );
		expect( rows[ 3 ].t ).toBe( '后台心跳已节流，仪表盘外部内容已屏蔽' );
	} );
} );

describe( 'packRows / packTitle (WZ-05, L-3)', () => {
	it( 'crossborder without WooCommerce only lists Windfonts', () => {
		expect( packRows( 'crossborder', false ).map( ( r ) => r.id ) ).toEqual(
			[ 'windfonts' ]
		);
	} );

	it( 'crossborder and inbound with WooCommerce list the two Woo rows', () => {
		[ 'crossborder', 'inbound' ].forEach( ( scene ) => {
			expect( packRows( scene, true ).map( ( r ) => r.t ) ).toEqual( [
				'微信支付 for WooCommerce',
				'订单微信通知',
				'中文字体（Windfonts）',
			] );
		} );
	} );

	it( 'domestic and mixed list Windfonts and 小工具 regardless of Woo', () => {
		[ 'domestic', 'mixed' ].forEach( ( scene ) => {
			[ true, false ].forEach( ( woo ) => {
				expect( packRows( scene, woo ).map( ( r ) => r.t ) ).toEqual( [
					'中文字体（Windfonts）',
					'小工具',
				] );
			} );
		} );
	} );

	it( 'titles per scene', () => {
		expect( packTitle( 'crossborder' ) ).toBe( '跨境店常用的，还可以加' );
		expect( packTitle( 'inbound' ) ).toBe( '面向中国买家的，还可以加' );
		expect( packTitle( 'domestic' ) ).toBe( '可能还需要' );
		expect( packTitle( 'mixed' ) ).toBe( '可能还需要' );
	} );

	it( 'hasWoo matches the woocommerce slug', () => {
		expect( hasWoo( [ 'woocommerce/woocommerce.php' ] ) ).toBe( true );
		expect( hasWoo( [ 'akismet/akismet.php' ] ) ).toBe( false );
		expect( hasWoo( undefined ) ).toBe( false );
	} );
} );

describe( 'resourceSummary (WZ-04)', () => {
	// Each scene preset as PUT /settings stores it (Config\Profile matrix).
	const presetDoc = ( scene ) => {
		const def = sceneDefaults( scene );
		return {
			profile: scene,
			connectivity: {
				wordpress_org: def.wordpress_org,
				public_assets: def.public_assets,
				avatar: def.avatar,
				heartbeat: def.heartbeat,
				dashboard_feeds: def.dashboard_feeds,
				icon_photos: def.icon_photos,
			},
		};
	};

	it.each( [
		// mirror + 5 assets + avatar + icons
		[ 'domestic', 8 ],
		// 5 assets + avatar + heartbeat + feeds + icons (WordPress.org direct)
		[ 'crossborder', 9 ],
		// 5 assets + avatar + icons (WordPress.org direct, WP default admin)
		[ 'inbound', 7 ],
		// mirror + 5 assets + avatar + heartbeat + feeds + icons
		[ 'mixed', 10 ],
	] )( '%s preset changes %d items', ( scene, count ) => {
		expect( resourceSummary( presetDoc( scene ) ).count ).toBe( count );
	} );

	it( 'the four scenes do not share one count', () => {
		const counts = SCENES.map(
			( scene ) => resourceSummary( presetDoc( scene ) ).count
		);
		expect( new Set( counts ).size ).toBe( SCENES.length );
	} );

	it( 'lists the changed keys', () => {
		expect( presetChanges( presetDoc( 'inbound' ) ) ).toEqual( [
			'public_assets.google_fonts',
			'public_assets.google_ajax',
			'public_assets.cdnjs',
			'public_assets.jsdelivr',
			'public_assets.emoji',
			'avatar',
			'icon_photos',
		] );
	} );

	it( 'follows the saved document; Windfonts is not a preset change', () => {
		expect(
			resourceSummary( {
				profile: 'mixed',
				connectivity: {
					wordpress_org: 'off',
					public_assets: {
						items: [ 'google_fonts', 'jsdelivr' ],
						scope: 'admin',
					},
					avatar: 'cravatar_cn',
				},
				modules: { windfonts: true },
			} )
		).toEqual( { count: 3, admin: true } );
	} );

	it( 'stock WordPress counts zero', () => {
		expect(
			resourceSummary( {
				connectivity: {
					wordpress_org: 'off',
					public_assets: { items: [ 'emoji' ], scope: 'off' },
					avatar: 'off',
					heartbeat: 'off',
					dashboard_feeds: 'allow',
					icon_photos: { enabled: 'off' },
				},
			} )
		).toEqual( { count: 0, admin: false } );
	} );

	it( 'maskHash shows first 4 … last 4', () => {
		expect( maskHash( 'a3f9000000000000c21e' ) ).toBe( 'a3f9…c21e' );
		expect( maskHash( null ) ).toBe( '' );
	} );
} );
