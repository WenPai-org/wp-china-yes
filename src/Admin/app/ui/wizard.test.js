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
	presetDiff,
	resourceSummary,
} from './wizard';
import { sceneDefaults } from './profiles';
import BASE38 from '../test-utils/settings-3.8-defaults.json';
import { applyScene } from '../test-utils/scene';

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

describe( 'presetDiff / resourceSummary (WZ-04)', () => {
	// Measured from the 3.8 baseline: 跨境 9, 内贸 7, 混合 8; 国内站 is the
	// baseline's own scene (no-op, 0). The spec's 8 / 9 / 7 / 10 are reference
	// values for an all-off document; see the "all-off" case below.
	it.each( [
		[ 'crossborder', 9 ],
		[ 'inbound', 7 ],
		[ 'mixed', 8 ],
		[ 'domestic', 0 ],
	] )( '3.8 defaults → %s changes %d fields', ( scene, count ) => {
		const after = applyScene( BASE38, scene );
		const summary = resourceSummary( BASE38, after );
		expect( summary.count ).toBe( count );
		expect( summary.keys ).toEqual( presetDiff( BASE38, after ) );
		expect( summary.same ).toBe( scene === 'domestic' );
	} );

	it( '3.8 defaults → 跨境 lists the changed keys', () => {
		expect(
			presetDiff( BASE38, applyScene( BASE38, 'crossborder' ) )
		).toEqual( [
			'wordpress_org',
			'public_assets.google_fonts',
			'public_assets.google_ajax',
			'public_assets.cdnjs',
			'public_assets.jsdelivr',
			'public_assets.emoji',
			'heartbeat',
			'dashboard_feeds',
			'icon_photos',
		] );
	} );

	it.each( [
		[ 'domestic', 8 ],
		[ 'crossborder', 9 ],
		[ 'inbound', 7 ],
		[ 'mixed', 10 ],
	] )( 'all-off document → %s changes %d fields', ( scene, count ) => {
		const off = {
			profile: 'none',
			connectivity: {
				wordpress_org: 'off',
				public_assets: { items: [], scope: 'off' },
				avatar: 'off',
				heartbeat: 'off',
				dashboard_feeds: 'allow',
				icon_photos: { enabled: 'off' },
			},
		};
		expect( resourceSummary( off, applyScene( off, scene ) ).count ).toBe(
			count
		);
	} );

	it( 'same scene: before equals after → 0, no keys', () => {
		const doc = applyScene( BASE38, 'mixed' );
		expect( presetDiff( doc, doc ) ).toEqual( [] );
		expect( resourceSummary( doc, doc ) ).toEqual( {
			count: 0,
			keys: [],
			same: true,
			admin: true,
		} );
	} );

	it( 'non-default baseline counts only the fields that really change', () => {
		// Hand-tuned: crossborder-like already, but emoji dropped and the
		// heartbeat left at the WordPress default.
		const before = {
			profile: 'domestic',
			connectivity: {
				wordpress_org: 'off',
				public_assets: {
					items: [
						'google_fonts',
						'google_ajax',
						'cdnjs',
						'jsdelivr',
					],
					scope: 'admin',
				},
				avatar: 'cravatar_cn',
				heartbeat: 'off',
				dashboard_feeds: 'block',
				icon_photos: { enabled: 'admin' },
			},
		};
		expect(
			presetDiff( before, applyScene( before, 'crossborder' ) )
		).toEqual( [ 'public_assets.emoji', 'heartbeat' ] );
	} );

	it( 'scope change counts each affected item', () => {
		const before = applyScene( BASE38, 'domestic' );
		const both = {
			...before,
			connectivity: {
				...before.connectivity,
				public_assets: {
					items: [ 'google_fonts', 'cdnjs' ],
					scope: 'both',
				},
			},
		};
		const admin = {
			...both,
			connectivity: {
				...both.connectivity,
				public_assets: {
					items: [ 'google_fonts', 'cdnjs' ],
					scope: 'admin',
				},
			},
		};
		expect( presetDiff( both, admin ) ).toEqual( [
			'public_assets.google_fonts',
			'public_assets.cdnjs',
		] );
	} );

	it( 'missing values equal explicit defaults', () => {
		const explicit = {
			connectivity: {
				wordpress_org: 'auto',
				public_assets: {
					items: [
						'google_fonts',
						'google_ajax',
						'cdnjs',
						'jsdelivr',
						'emoji',
					],
					scope: 'both',
				},
				avatar: 'off',
				heartbeat: 'off',
				dashboard_feeds: 'allow',
				icon_photos: { enabled: 'off' },
			},
		};
		expect( presetDiff( {}, explicit ) ).toEqual( [] );
		expect( presetDiff( { connectivity: {} }, explicit ) ).toEqual( [] );
		expect(
			presetDiff( {}, { connectivity: { avatar: { admin: 'off' } } } )
		).toEqual( [] );
		expect(
			presetDiff(
				{},
				{ connectivity: { avatar: { admin: 'cravatar_cn' } } }
			)
		).toEqual( [ 'avatar' ] );
	} );

	it( 'Windfonts and other modules are not preset fields', () => {
		expect(
			presetDiff(
				{ modules: { windfonts: false } },
				{ modules: { windfonts: true } }
			)
		).toEqual( [] );
	} );

	it( 'presetChanges still lists enabled items of one document', () => {
		expect( presetChanges( applyScene( BASE38, 'inbound' ) ) ).toEqual( [
			'public_assets.google_fonts',
			'public_assets.google_ajax',
			'public_assets.cdnjs',
			'public_assets.jsdelivr',
			'public_assets.emoji',
			'avatar',
			'icon_photos',
		] );
	} );
} );

describe( 'maskHash', () => {
	it( 'maskHash shows first 4 … last 4', () => {
		expect( maskHash( 'a3f9000000000000c21e' ) ).toBe( 'a3f9…c21e' );
		expect( maskHash( null ) ).toBe( '' );
	} );
} );
