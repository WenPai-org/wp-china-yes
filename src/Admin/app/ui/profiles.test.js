/**
 * sceneDefaults() mirrors Config\Profile::matrix() through one shared fixture
 * (tests/fixtures/profile-matrix.json, also asserted by phpunit), and the
 * 「已自定义」 detection follows it.
 */

import fixture from '../../../../tests/fixtures/profile-matrix.json';
import {
	customizedKeys,
	customKeyLabel,
	sceneCards,
	sceneDefaults,
} from './profiles';

describe( 'sceneDefaults', () => {
	it( 'has the same four scenes as the PHP matrix fixture', () => {
		expect( sceneCards().map( ( c ) => c.id ) ).toEqual(
			Object.keys( fixture )
		);
	} );

	it.each( Object.keys( fixture ) )(
		'%s matches Profile::matrix() cell for cell',
		( scene ) => {
			expect( sceneDefaults( scene ) ).toEqual( fixture[ scene ] );
		}
	);

	it( 'falls back to domestic for an unknown scene', () => {
		expect( sceneDefaults( 'nope' ) ).toEqual( fixture.domestic );
	} );
} );

function docFor( scene, extra = {} ) {
	const def = sceneDefaults( scene );
	return {
		profile: scene,
		connectivity: {
			wordpress_org: def.wordpress_org,
			public_assets: def.public_assets,
			avatar: def.avatar,
			heartbeat: def.heartbeat,
			dashboard_feeds: def.dashboard_feeds,
			...( extra.connectivity || {} ),
		},
		modules: { windfonts: def.windfonts, ...( extra.modules || {} ) },
	};
}

describe( 'customizedKeys', () => {
	it.each( Object.keys( fixture ) )(
		'%s at scene defaults has nothing customized',
		( scene ) => {
			expect( customizedKeys( docFor( scene ) ) ).toEqual( [] );
		}
	);

	it( 'flags avatar and heartbeat when they leave the crossborder default', () => {
		const doc = docFor( 'crossborder', {
			connectivity: { avatar: 'off', heartbeat: 'off' },
		} );
		expect( customizedKeys( doc ) ).toEqual( [ 'avatar', 'heartbeat' ] );
		expect( customizedKeys( doc ).map( customKeyLabel ) ).toEqual( [
			'头像',
			'减少后台心跳',
		] );
	} );

	it( 'flags wordpress_org, scope and windfonts on a domestic site', () => {
		const doc = docFor( 'domestic', {
			connectivity: {
				wordpress_org: 'off',
				public_assets: { items: [], scope: 'admin' },
			},
			modules: { windfonts: true },
		} );
		expect( customizedKeys( doc ) ).toEqual( [
			'wordpress_org',
			'public_assets',
			'windfonts',
		] );
	} );

	it( 'reads the legacy split avatar object', () => {
		const doc = docFor( 'mixed', {
			connectivity: { avatar: { admin: 'cravatar_global' } },
		} );
		expect( customizedKeys( doc ) ).toEqual( [ 'avatar' ] );
	} );
} );
