/**
 * Admin data store `wpcy/admin`.
 */

import { createReduxStore, register } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

export const STORE_NAME = 'wpcy/admin';

const DEFAULT_DIAGNOSTICS = { targets: [] };

const DEFAULT_STATE = {
	settings: {},
	capabilities: {},
	diagnostics: DEFAULT_DIAGNOSTICS,
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
	return {
		wordpress_org: connectivity.wordpress_org || 'auto',
		public_assets: Array.isArray( connectivity.public_assets )
			? connectivity.public_assets.slice()
			: [],
		avatar: connectivity.avatar || 'cravatar_cn',
		windfonts: Boolean( modules.windfonts ),
	};
}

const actions = {
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
		try {
			const diagnostics = yield {
				type: 'API_FETCH',
				request: { path: '/wpcy/v1/diagnostics' },
			};
			return { type: 'SET_DIAGNOSTICS', diagnostics };
		} catch ( error ) {
			return {
				type: 'SET_NOTICE',
				notice: {
					status: 'error',
					message:
						error?.message ||
						__( '无法读取诊断结果。', 'wp-china-yes' ),
				},
			};
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
			return {
				type: 'SET_NOTICE',
				notice: {
					status: 'warning',
					message: __(
						'改写与模块仍处于关闭，前往连接优化开启。',
						'wp-china-yes'
					),
				},
			};
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

function reducer( state = DEFAULT_STATE, action ) {
	switch ( action.type ) {
		case 'HYDRATE': {
			const settings = action.bootstrap.settings || {};
			return {
				...state,
				settings,
				capabilities: action.bootstrap.capabilities || {},
				draft: connectDraftFromSettings( settings ),
			};
		}
		case 'SET_SETTINGS':
			return {
				...state,
				settings: action.settings || {},
				draft: connectDraftFromSettings( action.settings || {} ),
			};
		case 'SET_DRAFT':
			return {
				...state,
				draft: { ...state.draft, ...action.draft },
			};
		case 'SET_DIAGNOSTICS':
			return {
				...state,
				diagnostics: action.diagnostics || DEFAULT_DIAGNOSTICS,
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
	getCapabilities( state ) {
		return state.capabilities;
	},
	getDiagnostics( state ) {
		return state.diagnostics;
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
