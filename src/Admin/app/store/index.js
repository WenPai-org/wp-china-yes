/**
 * Admin data store `wpcy/admin`.
 *
 */

import { createReduxStore, register } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

export const STORE_NAME = 'wpcy/admin';

export const SETTINGS_PATH = '/wpcy/v1/settings';
export const NETWORK_SETTINGS_PATH = '/wpcy/v1/network-settings';

const DEFAULT_DIAGNOSTICS = { targets: [] };

const DEFAULT_STATE = {
	settings: {},
	serverSettings: {},
	unsaved: {},
	settingsError: false,
	capabilities: {},
	diagnostics: DEFAULT_DIAGNOSTICS,
	diagnosticsError: false,
	diagnosticsLoaded: false,
	diagnosticsMs: null,
	stats: null,
	statsError: false,
	statsLoaded: false,
	events: null,
	eventsError: false,
	eventsLoaded: false,
	binding: null,
	bindingError: false,
	bindingLoaded: false,
	migration: null,
	migrationError: false,
	migrationLoaded: false,
	clientProbe: null,
	clientProbeError: false,
	clientProbeLoaded: false,
	links: {},
	providers: {},
	siteContext: {},
	pluginVersion: '',
	draft: null,
	saving: false,
	running: false,
	notice: null,
	exitedRecovery: false,
};

/**
 * Connect form slice from a settings document.
 *
 * @param {Object} settings Settings document.
 * @return {Object} Draft fields for the connect form.
 */
export function connectDraftFromSettings( settings ) {
	const connectivity = settings?.connectivity || {};
	const modules = settings?.modules || {};
	const publicAssets = connectivity.public_assets;
	let items = [];
	if ( Array.isArray( publicAssets ) ) {
		items = publicAssets.slice();
	} else if ( Array.isArray( publicAssets?.items ) ) {
		items = publicAssets.items.slice();
	}
	return {
		wordpress_org: connectivity.wordpress_org || 'auto',
		public_assets: items,
		avatar: connectivity.avatar || 'cravatar_cn',
		windfonts: Boolean( modules.windfonts ),
	};
}

/**
 * Plain-object check for merge.
 *
 * @param {*} value Value.
 * @return {boolean} Whether value is a non-array object.
 */
function isPlainObject( value ) {
	return (
		value !== null && typeof value === 'object' && ! Array.isArray( value )
	);
}

/**
 * Deep-merge a partial PUT body onto a settings document (arrays replace).
 *
 * @param {Object} base  Current document.
 * @param {Object} patch Partial body.
 * @return {Object} Merged copy.
 */
export function mergeSettings( base, patch ) {
	const out = { ...( isPlainObject( base ) ? base : {} ) };
	Object.keys( patch || {} ).forEach( ( key ) => {
		const next = patch[ key ];
		out[ key ] =
			isPlainObject( next ) && isPlainObject( out[ key ] )
				? mergeSettings( out[ key ], next )
				: next;
	} );
	return out;
}

/**
 * Error codes for a refused subsite write (server contract, PR #157).
 */
export const LOCK_CODES = [
	'wpcy_settings_locked',
	'wpcy_settings_network_scope',
];

/**
 * Network refusal of a subsite write, handled the same way: 409
 * wpcy_settings_locked (locked field changed while allow_site_override is
 * closed) and 403 wpcy_settings_network_scope (network-scope field changed).
 * Other codes mentioning "lock" are treated alike, defensively.
 *
 * @param {Object} error api-fetch error.
 * @return {boolean} Whether the write was refused by the network lock.
 */
export function isLockError( error ) {
	const code = String( error?.code || '' );
	return LOCK_CODES.includes( code ) || /lock/i.test( code );
}

/**
 * Field names the server refused. The contract sends root names in
 * data.fields (e.g. "connectivity"); other keys are read defensively.
 *
 * @param {Object} error api-fetch error.
 * @return {string[]|null} Names (dot paths or leaf names), or null when the
 *                         error carries no list (treat the whole request as
 *                         refused).
 */
export function lockedFieldNames( error ) {
	const data = error?.data || {};
	for ( const key of [
		'fields',
		'locked_fields',
		'rejected_fields',
		'locked',
		'rejected',
		'params',
	] ) {
		const value = data[ key ];
		if ( Array.isArray( value ) && value.length ) {
			return value.map( String );
		}
		if ( value && typeof value === 'object' && ! Array.isArray( value ) ) {
			const names = Object.keys( value );
			if ( names.length ) {
				return names;
			}
		}
	}
	return null;
}

/**
 * Leaf dot paths of a partial settings body.
 *
 * @param {Object} data   Partial body.
 * @param {string} prefix Path prefix.
 * @return {string[]} Paths.
 */
export function leafPaths( data, prefix = '' ) {
	const out = [];
	Object.keys( data || {} ).forEach( ( key ) => {
		const path = prefix ? prefix + '.' + key : key;
		const value = data[ key ];
		if ( value && typeof value === 'object' && ! Array.isArray( value ) ) {
			out.push( ...leafPaths( value, path ) );
		} else {
			out.push( path );
		}
	} );
	return out;
}

/**
 * Whether a leaf path is covered by one refused name (exact path, a parent
 * section, or the same leaf name).
 *
 * @param {string}   path  Leaf path.
 * @param {string[]} names Refused names.
 * @return {boolean} Covered.
 */
function refused( path, names ) {
	return names.some(
		( name ) =>
			path === name ||
			path.startsWith( name + '.' ) ||
			path.endsWith( '.' + name )
	);
}

/**
 * Body without the refused leaves.
 *
 * @param {Object}   data   Partial body.
 * @param {string[]} names  Refused names.
 * @param {string}   prefix Path prefix.
 * @return {Object} Remaining partial body.
 */
function withoutRefused( data, names, prefix = '' ) {
	const out = {};
	Object.keys( data || {} ).forEach( ( key ) => {
		const path = prefix ? prefix + '.' + key : key;
		const value = data[ key ];
		if ( value && typeof value === 'object' && ! Array.isArray( value ) ) {
			const rest = withoutRefused( value, names, path );
			if ( Object.keys( rest ).length ) {
				out[ key ] = rest;
			}
		} else if ( ! refused( path, names ) ) {
			out[ key ] = value;
		}
	} );
	return out;
}

/**
 * CO-01 save failure copy: schema 400 vs everything else.
 *
 * @param {Object} error api-fetch error.
 * @return {string} Glossary sentence.
 */
export function saveErrorMessage( error ) {
	if ( error?.code === 'wpcy_invalid_schema' ) {
		return __( '暂时无法保存设置，请检查填写内容后重试。', 'wp-china-yes' );
	}
	return __( '暂时无法保存设置，请稍后重试。', 'wp-china-yes' );
}

function failFlag( type ) {
	return { type, error: true };
}

export const actions = {
	hydrate( bootstrap ) {
		return { type: 'HYDRATE', bootstrap: bootstrap || {} };
	},
	setDraft( draft ) {
		return { type: 'SET_DRAFT', draft };
	},
	setNotice( notice ) {
		return { type: 'SET_NOTICE', notice };
	},
	clearNotice() {
		return { type: 'SET_NOTICE', notice: null };
	},
	*fetchSettings() {
		try {
			const settings = yield {
				type: 'API_FETCH',
				request: { path: '/wpcy/v1/settings' },
			};
			return { type: 'SET_SETTINGS', settings };
		} catch ( error ) {
			return {
				type: 'SET_NOTICE',
				notice: {
					status: 'error',
					message:
						error?.message ||
						__( '无法读取设置。', 'wp-china-yes' ),
				},
			};
		}
	},
	*fetchDiagnostics() {
		const started =
			typeof performance !== 'undefined' ? performance.now() : 0;
		try {
			const diagnostics = yield {
				type: 'API_FETCH',
				request: { path: '/wpcy/v1/diagnostics' },
			};
			const ms =
				typeof performance !== 'undefined'
					? Math.round( performance.now() - started )
					: null;
			return { type: 'SET_DIAGNOSTICS', diagnostics, latencyMs: ms };
		} catch ( error ) {
			void error;
			return failFlag( 'SET_DIAGNOSTICS_ERROR' );
		}
	},
	*fetchStats() {
		try {
			const stats = yield {
				type: 'API_FETCH',
				request: { path: '/wpcy/v1/stats?days=14' },
			};
			return { type: 'SET_STATS', stats };
		} catch ( error ) {
			void error;
			return failFlag( 'SET_STATS_ERROR' );
		}
	},
	*fetchEvents() {
		try {
			const payload = yield {
				type: 'API_FETCH',
				request: { path: '/wpcy/v1/events?per_page=6' },
			};
			return { type: 'SET_EVENTS', events: payload };
		} catch ( error ) {
			void error;
			return failFlag( 'SET_EVENTS_ERROR' );
		}
	},
	*fetchBinding() {
		try {
			const binding = yield {
				type: 'API_FETCH',
				request: { path: '/wpcy/v1/binding' },
			};
			return { type: 'SET_BINDING', binding };
		} catch ( error ) {
			void error;
			return failFlag( 'SET_BINDING_ERROR' );
		}
	},
	*fetchMigration() {
		try {
			const migration = yield {
				type: 'API_FETCH',
				request: { path: '/wpcy/v1/migration/report' },
			};
			return { type: 'SET_MIGRATION', migration };
		} catch ( error ) {
			void error;
			return failFlag( 'SET_MIGRATION_ERROR' );
		}
	},
	*fetchClientProbe() {
		try {
			const clientProbe = yield {
				type: 'API_FETCH',
				request: { path: '/wpcy/v1/diagnostics/client-probe' },
			};
			return { type: 'SET_CLIENT_PROBE', clientProbe };
		} catch ( error ) {
			void error;
			return failFlag( 'SET_CLIENT_PROBE_ERROR' );
		}
	},
	/**
	 * CO-01 instant save: optimistic merge, PUT the partial object, roll back
	 * to the last server document on failure. Never sets the page notice
	 * (no Snackbar); the caller shows the card-level alert.
	 *
	 * @param {Object} data           Partial settings body.
	 * @param {Object} [options]      Options.
	 * @param {string} [options.path] REST path (site or network settings).
	 */
	*patchSettings( data, options = {} ) {
		const path = options.path || SETTINGS_PATH;
		yield { type: 'SET_SAVING', saving: true };
		yield { type: 'PATCH_SETTINGS', data };
		try {
			const settings = yield {
				type: 'API_FETCH',
				request: {
					path,
					method: 'PUT',
					data,
				},
			};
			yield { type: 'SET_SETTINGS', settings };
			yield { type: 'SET_SAVING', saving: false };
			return { type: 'SAVE_RESULT', ok: true, error: null };
		} catch ( error ) {
			yield { type: 'ROLLBACK_SETTINGS' };
			const locked = isLockError( error );
			let lockedPaths = [];
			if ( locked ) {
				// The server refuses the whole request when any field is
				// locked: refused fields go back to the stored value, the
				// rest stay on screen as unsaved input for the next save.
				const names = lockedFieldNames( error );
				const paths = leafPaths( data );
				lockedPaths = names
					? paths.filter( ( leaf ) => refused( leaf, names ) )
					: paths;
				const keep = names ? withoutRefused( data, names ) : {};
				if ( Object.keys( keep ).length ) {
					yield { type: 'KEEP_UNSAVED', data: keep };
				}
			}
			yield { type: 'SET_SAVING', saving: false };
			return {
				type: 'SAVE_RESULT',
				ok: false,
				error: {
					code: error?.code || '',
					locked,
					lockedPaths,
					message: saveErrorMessage( error ),
				},
			};
		}
	},
	*fetchNetworkSettings() {
		try {
			const settings = yield {
				type: 'API_FETCH',
				request: { path: NETWORK_SETTINGS_PATH },
			};
			return { type: 'SET_SETTINGS', settings };
		} catch ( error ) {
			void error;
			return failFlag( 'SET_SETTINGS_ERROR' );
		}
	},
	*saveSettings( draft ) {
		yield { type: 'SET_SAVING', saving: true };
		try {
			const settings = yield {
				type: 'API_FETCH',
				request: {
					path: '/wpcy/v1/settings',
					method: 'PUT',
					data: {
						connectivity: {
							wordpress_org: draft.wordpress_org,
							public_assets: draft.public_assets,
							avatar: draft.avatar,
						},
						modules: {
							windfonts: draft.windfonts,
						},
					},
				},
			};
			yield { type: 'SET_SETTINGS', settings };
			yield { type: 'SET_SAVING', saving: false };
			return {
				type: 'SET_NOTICE',
				notice: {
					status: 'success',
					message: __( '已保存', 'wp-china-yes' ),
				},
			};
		} catch ( error ) {
			yield { type: 'SET_SAVING', saving: false };
			return {
				type: 'SET_NOTICE',
				notice: {
					status: 'error',
					message:
						error?.message || __( '保存失败。', 'wp-china-yes' ),
				},
			};
		}
	},
	*runDiagnostics() {
		yield { type: 'SET_RUNNING', running: true };
		try {
			const diagnostics = yield {
				type: 'API_FETCH',
				request: {
					path: '/wpcy/v1/diagnostics/run',
					method: 'POST',
				},
			};
			yield { type: 'SET_DIAGNOSTICS', diagnostics };
			yield { type: 'SET_RUNNING', running: false };
			return { type: 'SET_NOTICE', notice: null };
		} catch ( error ) {
			yield { type: 'SET_RUNNING', running: false };
			return {
				type: 'SET_NOTICE',
				notice: {
					status: 'error',
					message:
						error?.message ||
						__( '检查未能完成。', 'wp-china-yes' ),
				},
			};
		}
	},
	*exitRecovery() {
		try {
			const settings = yield {
				type: 'API_FETCH',
				request: {
					path: '/wpcy/v1/recovery',
					method: 'POST',
					data: { action: 'exit' },
				},
			};
			yield { type: 'SET_SETTINGS', settings };
			yield { type: 'SET_EXITED_RECOVERY', value: true };
			try {
				const diagnostics = yield {
					type: 'API_FETCH',
					request: { path: '/wpcy/v1/diagnostics' },
				};
				return { type: 'SET_DIAGNOSTICS', diagnostics };
			} catch ( inner ) {
				void inner;
				return failFlag( 'SET_DIAGNOSTICS_ERROR' );
			}
		} catch ( error ) {
			return {
				type: 'SET_NOTICE',
				notice: {
					status: 'error',
					message:
						error?.message ||
						__( '无法退出恢复模式。', 'wp-china-yes' ),
				},
			};
		}
	},
};

const controls = {
	API_FETCH( { request } ) {
		return apiFetch( request );
	},
};

export function reducer( state = DEFAULT_STATE, action ) {
	switch ( action.type ) {
		case 'HYDRATE': {
			const settings = action.bootstrap.settings || {};
			return {
				...state,
				settings,
				serverSettings: settings,
				capabilities: action.bootstrap.capabilities || {},
				links: action.bootstrap.links || {},
				providers: action.bootstrap.providers || {},
				siteContext: action.bootstrap.siteContext || {},
				pluginVersion: action.bootstrap.pluginVersion || '',
				draft: connectDraftFromSettings( settings ),
			};
		}
		case 'SET_SETTINGS':
			return {
				...state,
				settings: action.settings || {},
				serverSettings: action.settings || {},
				unsaved: {},
				settingsError: false,
				draft: connectDraftFromSettings( action.settings || {} ),
			};
		case 'SET_SETTINGS_ERROR':
			return { ...state, settingsError: true };
		case 'PATCH_SETTINGS':
			return {
				...state,
				settings: mergeSettings( state.settings, action.data ),
			};
		case 'ROLLBACK_SETTINGS':
			return {
				...state,
				settings: mergeSettings(
					state.serverSettings || {},
					state.unsaved || {}
				),
			};
		case 'KEEP_UNSAVED': {
			const unsaved = mergeSettings( state.unsaved || {}, action.data );
			return {
				...state,
				unsaved,
				settings: mergeSettings( state.serverSettings || {}, unsaved ),
			};
		}
		case 'SET_DRAFT':
			return {
				...state,
				draft: { ...state.draft, ...action.draft },
			};
		case 'SET_DIAGNOSTICS':
			return {
				...state,
				diagnostics: action.diagnostics || DEFAULT_DIAGNOSTICS,
				diagnosticsError: false,
				diagnosticsLoaded: true,
				diagnosticsMs:
					action.latencyMs === undefined
						? state.diagnosticsMs
						: action.latencyMs,
			};
		case 'SET_DIAGNOSTICS_ERROR':
			return {
				...state,
				diagnosticsError: true,
				diagnosticsLoaded: true,
			};
		case 'SET_STATS':
			return {
				...state,
				stats: action.stats,
				statsError: false,
				statsLoaded: true,
			};
		case 'SET_STATS_ERROR':
			return { ...state, statsError: true, statsLoaded: true };
		case 'SET_EVENTS':
			return {
				...state,
				events: action.events,
				eventsError: false,
				eventsLoaded: true,
			};
		case 'SET_EVENTS_ERROR':
			return { ...state, eventsError: true, eventsLoaded: true };
		case 'SET_BINDING':
			return {
				...state,
				binding: action.binding,
				bindingError: false,
				bindingLoaded: true,
			};
		case 'SET_BINDING_ERROR':
			return { ...state, bindingError: true, bindingLoaded: true };
		case 'SET_MIGRATION':
			return {
				...state,
				migration: action.migration,
				migrationError: false,
				migrationLoaded: true,
			};
		case 'SET_MIGRATION_ERROR':
			return { ...state, migrationError: true, migrationLoaded: true };
		case 'SET_CLIENT_PROBE':
			return {
				...state,
				clientProbe: action.clientProbe,
				clientProbeError: false,
				clientProbeLoaded: true,
			};
		case 'SET_CLIENT_PROBE_ERROR':
			return {
				...state,
				clientProbeError: true,
				clientProbeLoaded: true,
			};
		case 'SET_SAVING':
			return { ...state, saving: Boolean( action.saving ) };
		case 'SET_RUNNING':
			return { ...state, running: Boolean( action.running ) };
		case 'SET_NOTICE':
			return { ...state, notice: action.notice };
		case 'SET_EXITED_RECOVERY':
			return { ...state, exitedRecovery: Boolean( action.value ) };
		default:
			return state;
	}
}

const selectors = {
	getSettings( state ) {
		return state.settings;
	},
	getSettingsError( state ) {
		return state.settingsError;
	},
	getUnsaved( state ) {
		return state.unsaved || {};
	},
	getCapabilities( state ) {
		return state.capabilities;
	},
	getDiagnostics( state ) {
		return state.diagnostics;
	},
	getDiagnosticsError( state ) {
		return state.diagnosticsError;
	},
	isDiagnosticsLoaded( state ) {
		return state.diagnosticsLoaded;
	},
	getDiagnosticsMs( state ) {
		return state.diagnosticsMs;
	},
	getStats( state ) {
		return state.stats;
	},
	getStatsError( state ) {
		return state.statsError;
	},
	isStatsLoaded( state ) {
		return state.statsLoaded;
	},
	getEvents( state ) {
		return state.events;
	},
	getEventsError( state ) {
		return state.eventsError;
	},
	isEventsLoaded( state ) {
		return state.eventsLoaded;
	},
	getBinding( state ) {
		return state.binding;
	},
	getBindingError( state ) {
		return state.bindingError;
	},
	isBindingLoaded( state ) {
		return state.bindingLoaded;
	},
	getMigration( state ) {
		return state.migration;
	},
	getMigrationError( state ) {
		return state.migrationError;
	},
	isMigrationLoaded( state ) {
		return state.migrationLoaded;
	},
	getClientProbe( state ) {
		return state.clientProbe;
	},
	getClientProbeError( state ) {
		return state.clientProbeError;
	},
	isClientProbeLoaded( state ) {
		return state.clientProbeLoaded;
	},
	getLinks( state ) {
		return state.links || {};
	},
	getProviders( state ) {
		return state.providers || {};
	},
	getSiteContext( state ) {
		return state.siteContext || {};
	},
	getPluginVersion( state ) {
		return state.pluginVersion || '';
	},
	getDraft( state ) {
		return state.draft || connectDraftFromSettings( state.settings );
	},
	isDirty( state ) {
		const current = connectDraftFromSettings( state.settings );
		const draft = state.draft || current;
		return JSON.stringify( current ) !== JSON.stringify( draft );
	},
	isSaving( state ) {
		return state.saving;
	},
	isRunning( state ) {
		return state.running;
	},
	getNotice( state ) {
		return state.notice;
	},
	isRecoveryMode( state ) {
		return Boolean( state.settings?.recovery_mode );
	},
	hasExitedRecovery( state ) {
		return state.exitedRecovery;
	},
	hasDiagnostics( state ) {
		return Array.isArray( state.diagnostics?.targets )
			? state.diagnostics.targets.length > 0
			: false;
	},
};

export const store = createReduxStore( STORE_NAME, {
	reducer,
	actions,
	selectors,
	controls,
} );

register( store );
