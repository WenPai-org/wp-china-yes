/**
 * CO-01 store contract: optimistic merge, PUT partial object, roll back to the
 * last server document on failure, never set the page notice (no Snackbar).
 */

import {
	actions,
	isLockError,
	lockedFieldNames,
	mergeSettings,
	reducer,
	saveErrorMessage,
	NETWORK_SETTINGS_PATH,
	SETTINGS_PATH,
} from './index';

const SERVER = {
	profile: 'crossborder',
	connectivity: {
		heartbeat: 'on',
		public_assets: { items: [ 'google_fonts' ], scope: 'admin' },
	},
};

function hydrated() {
	return reducer( undefined, {
		type: 'HYDRATE',
		bootstrap: { settings: SERVER },
	} );
}

describe( 'mergeSettings', () => {
	it( 'deep merges objects and replaces arrays', () => {
		expect(
			mergeSettings( SERVER, {
				connectivity: {
					public_assets: { items: [ 'emoji' ] },
				},
			} ).connectivity
		).toEqual( {
			heartbeat: 'on',
			public_assets: { items: [ 'emoji' ], scope: 'admin' },
		} );
	} );
} );

describe( 'saveErrorMessage', () => {
	it( 'schema 400 vs other', () => {
		expect( saveErrorMessage( { code: 'wpcy_invalid_schema' } ) ).toBe(
			'暂时无法保存设置，请检查填写内容后重试。'
		);
		expect( saveErrorMessage( { code: 'internal_server_error' } ) ).toBe(
			'暂时无法保存设置，请稍后重试。'
		);
		expect( saveErrorMessage( undefined ) ).toBe(
			'暂时无法保存设置，请稍后重试。'
		);
	} );
} );

/**
 * Drive the patchSettings generator through the reducer.
 *
 * @param {Object}   data    Partial body.
 * @param {Function} respond Called with the API_FETCH request; returns
 *                           { ok: settings } or { fail: error }.
 * @param {Object}   options patchSettings options.
 * @return {{state:Object,result:Object,requests:Object[],types:string[]}} Trace.
 */
function run( data, respond, options ) {
	let state = hydrated();
	const requests = [];
	const types = [];
	const gen = actions.patchSettings( data, options );
	let step = gen.next();
	while ( ! step.done ) {
		const action = step.value;
		types.push( action.type );
		if ( action.type === 'API_FETCH' ) {
			requests.push( action.request );
			const reply = respond( action.request, state );
			step = reply.fail ? gen.throw( reply.fail ) : gen.next( reply.ok );
			continue;
		}
		state = reducer( state, action );
		step = gen.next();
	}
	return { state, result: step.value, requests, types };
}

describe( 'patchSettings', () => {
	it( 'applies the change before the response arrives', () => {
		let seen;
		run( { connectivity: { heartbeat: 'off' } }, ( req, state ) => {
			seen = state.settings.connectivity.heartbeat;
			return { ok: mergeSettings( SERVER, req.data ) };
		} );
		expect( seen ).toBe( 'off' );
	} );

	it( 'rolls the control back to the server value on failure', () => {
		const trace = run( { connectivity: { heartbeat: 'off' } }, () => ( {
			fail: { code: 'rest_error', message: 'boom' },
		} ) );
		expect( trace.state.settings ).toEqual( SERVER );
		expect( trace.state.saving ).toBe( false );
		expect( trace.result.ok ).toBe( false );
		expect( trace.result.error.message ).toBe(
			'暂时无法保存设置，请稍后重试。'
		);
		expect( trace.types ).toContain( 'ROLLBACK_SETTINGS' );
		expect( trace.types ).not.toContain( 'SET_NOTICE' );
		expect( trace.state.notice ).toBeNull();
	} );

	it( 'schema failure keeps the schema sentence', () => {
		const trace = run( { connectivity: { heartbeat: 'x' } }, () => ( {
			fail: { code: 'wpcy_invalid_schema' },
		} ) );
		expect( trace.result.error.code ).toBe( 'wpcy_invalid_schema' );
		expect( trace.result.error.message ).toBe(
			'暂时无法保存设置，请检查填写内容后重试。'
		);
	} );

	it( 'sends one PUT with only the partial object', () => {
		const trace = run( { profile: 'domestic' }, () => ( {
			ok: { ...SERVER, profile: 'domestic' },
		} ) );
		expect( trace.requests ).toEqual( [
			{
				path: SETTINGS_PATH,
				method: 'PUT',
				data: { profile: 'domestic' },
			},
		] );
		expect( trace.result.ok ).toBe( true );
		expect( trace.state.serverSettings.profile ).toBe( 'domestic' );
	} );

	it( 'network view writes /network-settings', () => {
		const trace = run(
			{ allow_site_override: false },
			() => ( { ok: { allow_site_override: false } } ),
			{ path: NETWORK_SETTINGS_PATH }
		);
		expect( trace.requests[ 0 ].path ).toBe( '/wpcy/v1/network-settings' );
	} );

	it( 'after a failure the next success updates the server copy', () => {
		const failed = run( { connectivity: { heartbeat: 'off' } }, () => ( {
			fail: {},
		} ) );
		const state = reducer( failed.state, {
			type: 'SET_SETTINGS',
			settings: { ...SERVER, profile: 'mixed' },
		} );
		expect(
			reducer( state, { type: 'ROLLBACK_SETTINGS' } ).settings.profile
		).toBe( 'mixed' );
	} );
} );

describe( 'network lock refusal', () => {
	const DATA = {
		connectivity: { heartbeat: 'off', dashboard_feeds: false },
	};

	it( 'detects lock errors defensively', () => {
		expect( isLockError( { code: 'wpcy_settings_locked' } ) ).toBe( true );
		expect( isLockError( { code: 'wpcy_settings_network_scope' } ) ).toBe(
			true
		);
		expect( isLockError( { code: 'rest_forbidden' } ) ).toBe( false );
		expect(
			lockedFieldNames( {
				data: { locked_fields: [ 'connectivity.heartbeat' ] },
			} )
		).toEqual( [ 'connectivity.heartbeat' ] );
		expect(
			lockedFieldNames( { data: { params: { heartbeat: 'locked' } } } )
		).toEqual( [ 'heartbeat' ] );
		expect( lockedFieldNames( { data: {} } ) ).toBeNull();
	} );

	it( '409 wpcy_settings_locked: refused roots revert, other roots stay as unsaved input', () => {
		const data = {
			connectivity: { heartbeat: 'off' },
			modules: { windfonts: true },
		};
		const { state, result } = run( data, () => ( {
			fail: {
				code: 'wpcy_settings_locked',
				data: { status: 409, fields: [ 'modules' ] },
			},
		} ) );
		expect( result.ok ).toBe( false );
		expect( result.error.locked ).toBe( true );
		expect( result.error.lockedPaths ).toEqual( [ 'modules.windfonts' ] );
		expect( state.settings.modules ).toBeUndefined();
		expect( state.settings.connectivity.heartbeat ).toBe( 'off' );
		expect( state.unsaved ).toEqual( {
			connectivity: { heartbeat: 'off' },
		} );
		expect( state.serverSettings ).toEqual( SERVER );

		const ok = reducer( state, {
			type: 'SET_SETTINGS',
			settings: mergeSettings( SERVER, state.unsaved ),
		} );
		expect( ok.unsaved ).toEqual( {} );
	} );

	it( '403 wpcy_settings_network_scope is handled like a lock', () => {
		const { state, result } = run( DATA, () => ( {
			fail: {
				code: 'wpcy_settings_network_scope',
				data: { status: 403, fields: [ 'connectivity' ] },
			},
		} ) );
		expect( result.error.locked ).toBe( true );
		expect( result.error.lockedPaths ).toEqual( [
			'connectivity.heartbeat',
			'connectivity.dashboard_feeds',
		] );
		expect( state.settings ).toEqual( SERVER );
		expect( state.unsaved ).toEqual( {} );
	} );

	it( 'without a field list the whole request is refused', () => {
		const { state, result } = run( DATA, () => ( {
			fail: { code: 'wpcy_settings_locked', data: { status: 409 } },
		} ) );
		expect( result.error.lockedPaths ).toEqual( [
			'connectivity.heartbeat',
			'connectivity.dashboard_feeds',
		] );
		expect( state.settings ).toEqual( SERVER );
		expect( state.unsaved ).toEqual( {} );
	} );
} );
