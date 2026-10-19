/**
 * CO-01 store contract: optimistic merge, PUT partial object, roll back to the
 * last server document on failure, never set the page notice (no Snackbar).
 */

import {
	actions,
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
