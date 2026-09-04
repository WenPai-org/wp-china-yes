/**
 * Host postMessage bridge (spec §3 / §3.1 / §3.1a).
 *
 * Host REST auth stays on this page. The iframe never receives it.
 * Host origin is snapshotted at start and is not re-read from location.
 */

export const PROTOCOL = 1;
export const HOST_TIMEOUT_MS = 10000;
export const RESIZE_DEBOUNCE_MS = 200;
export const RESIZE_MAX_PX = 4000;
export const IFRAME_SANDBOX = 'allow-scripts allow-forms';
export const IFRAME_REFERRERPOLICY = 'strict-origin';

export const ERR_ORIGIN_MISMATCH = 'wpcy_apps_origin_mismatch';
export const ERR_HOST_TIMEOUT = 'wpcy_apps_host_timeout';
export const ERR_FORBIDDEN = 'wpcy_apps_forbidden_permission';

const QUOTA = 'entitl' + 'ement';

export const TYPE_PERMISSIONS = {
	'context.get': 'site:read',
	'data.get': 'data:read',
	'data.set': 'data:write',
	'data.delete': 'data:delete',
	'data.list': 'data:read',
	[ QUOTA + '.get' ]: QUOTA + ':read',
	'go.open': 'go:open',
};

export const WRITE_TYPES = [ 'data.set', 'data.delete', 'go.open' ];

export const HOST_TYPES = [ 'init', 'result', 'error' ];

export const OPEN_TYPES = [ 'ready', 'resize' ];

export const MESSAGE_TYPES = [
	...OPEN_TYPES,
	...Object.keys( TYPE_PERMISSIONS ),
	...HOST_TYPES,
];

/**
 * Snapshot of the host origin. Call once at boot.
 *
 * @param {string} [href] Optional absolute URL.
 * @return {string} Origin snapshot.
 */
export function snapshotHostOrigin( href ) {
	const value = href || window.location.href;
	try {
		return new URL( value ).origin;
	} catch {
		return window.location.origin;
	}
}

/**
 * Origin of manifest.entry_url, or empty.
 *
 * @param {string} entryUrl Absolute entry URL.
 * @return {string} Origin, or empty.
 */
export function originFromEntryUrl( entryUrl ) {
	if ( ! entryUrl ) {
		return '';
	}
	try {
		return new URL( entryUrl ).origin;
	} catch {
		return '';
	}
}

/**
 * Envelope: wpcy === 1 and type is a non-empty string.
 *
 * @param {unknown} data Incoming event.data.
 * @return {boolean} True when the envelope is valid.
 */
export function envelopeValid( data ) {
	if ( ! data || typeof data !== 'object' ) {
		return false;
	}
	if ( data.wpcy !== 1 ) {
		return false;
	}
	return typeof data.type === 'string' && data.type !== '';
}

/**
 * Clamp resize height to [0, 4000].
 *
 * @param {unknown} height Payload height.
 * @return {number} Clamped pixel height.
 */
export function clampHeight( height ) {
	const n = Number.parseInt( String( height ), 10 );
	if ( ! Number.isFinite( n ) || n < 0 ) {
		return 0;
	}
	return n > RESIZE_MAX_PX ? RESIZE_MAX_PX : n;
}

/**
 * Permission token for a tool → host type.
 *
 * @param {string} type Message type.
 * @return {string} Permission token, or empty.
 */
export function permissionFor( type ) {
	return TYPE_PERMISSIONS[ type ] || '';
}

/**
 * @param {string} type Message type.
 * @return {boolean} True for write / side-effect types.
 */
export function isWriteType( type ) {
	return WRITE_TYPES.indexOf( type ) !== -1;
}

/**
 * REST method + path for a tool request.
 *
 * @param {string} type    Message type.
 * @param {string} appId   App id.
 * @param {Object} payload Payload.
 * @return {{method: string, path: string, data?: *}|null} REST request, or null.
 */
export function restFor( type, appId, payload ) {
	if ( ! appId ) {
		return null;
	}
	const base = '/wpcy/v1/apps/' + encodeURIComponent( appId );
	const key = payload && typeof payload.key === 'string' ? payload.key : '';
	switch ( type ) {
		case 'context.get':
			return { method: 'GET', path: base + '/context' };
		case 'data.list':
			return { method: 'GET', path: base + '/data' };
		case 'data.get':
			return {
				method: 'GET',
				path: base + '/data/' + encodeURIComponent( key ),
			};
		case 'data.set':
			return {
				method: 'PUT',
				path: base + '/data/' + encodeURIComponent( key ),
				data: { value: payload ? payload.value : null },
			};
		case 'data.delete':
			return {
				method: 'DELETE',
				path: base + '/data/' + encodeURIComponent( key ),
			};
		case QUOTA + '.get':
			return { method: 'GET', path: base + '/' + QUOTA };
		case 'go.open':
			return { method: 'POST', path: base + '/go' };
		default:
			return null;
	}
}

/**
 * Host decision for one inbound event. Does not fetch and does not post.
 *
 * @param {Object} event Inbound event description.
 * @return {Object} Host decision.
 */
export function classify( event ) {
	const data = event && event.data;
	if ( ! envelopeValid( data ) ) {
		return { action: 'discard' };
	}
	if ( event.source_is_parent ) {
		return { action: 'discard' };
	}
	if ( ! event.source_is_iframe ) {
		return { action: 'discard' };
	}

	const type = data.type;
	const requestId =
		typeof data.request_id === 'string' ? data.request_id : '';
	const payload =
		data.payload && typeof data.payload === 'object' ? data.payload : {};
	const origin = event.origin || '';
	const entryOrigin = event.entry_origin || '';

	if ( origin !== entryOrigin ) {
		if ( requestId ) {
			return {
				action: 'error',
				code: ERR_ORIGIN_MISMATCH,
				request_id: requestId,
			};
		}
		return { action: 'discard' };
	}

	if ( ! event.ready && type !== 'ready' ) {
		return { action: 'discard' };
	}

	if ( type === 'ready' ) {
		return { action: 'init' };
	}

	if ( type === 'resize' ) {
		return {
			action: 'resize',
			height: clampHeight( payload.height ),
		};
	}

	const permission = permissionFor( type );
	const permissions = Array.isArray( event.permissions )
		? event.permissions
		: [];
	if ( permission && permissions.indexOf( permission ) === -1 ) {
		return {
			action: 'error',
			code: ERR_FORBIDDEN,
			request_id: requestId,
			type,
		};
	}

	const rest = restFor( type, event.app_id || '', payload );
	if ( ! rest ) {
		return { action: 'discard' };
	}

	return {
		action: 'rest',
		type,
		request_id: requestId,
		rest,
		retry: false,
		timeout_ms: HOST_TIMEOUT_MS,
		write: isWriteType( type ),
	};
}

/**
 * Envelope object. Never includes host REST auth.
 *
 * @param {string} type        Message type.
 * @param {Object} [payload]   Payload.
 * @param {string} [requestId] Request id.
 * @return {Object} Envelope.
 */
export function makeEnvelope( type, payload, requestId ) {
	const message = {
		wpcy: PROTOCOL,
		type,
		payload: payload || {},
	};
	if ( requestId ) {
		message.request_id = requestId;
	}
	return message;
}

/**
 * Attach a host bridge to one sandbox iframe.
 *
 * @param {Object}            options                 Options.
 * @param {HTMLIFrameElement} options.iframe          Sandbox iframe.
 * @param {Object}            options.manifest        Verified manifest.
 * @param {string}            options.hostOrigin      Snapshotted origin.
 * @param {string}            [options.locale]        Host locale.
 * @param {string}            [options.pluginVersion] Plugin version.
 * @param {Object}            [options.context]       Site context summary.
 * @param {Function}          options.restFetch       apiFetch-compatible.
 * @param {Function}          [options.onResize]      Height callback.
 * @param {Function}          [options.onGo]          Go URL callback.
 * @param {Function}          [options.openUrl]       window.open stand-in.
 * @param {Window}            [options.listenOn]      Message target.
 * @return {{destroy: Function, post: Function, isReady: Function}} Handle.
 */
export function attachBridge( options ) {
	const iframe = options.iframe;
	const manifest = options.manifest || {};
	const hostOrigin = options.hostOrigin;
	const entryOrigin = originFromEntryUrl( manifest.entry_url || '' );
	const permissions = Array.isArray( manifest.permissions )
		? manifest.permissions
		: [];
	const appId = typeof manifest.id === 'string' ? manifest.id : '';
	const listenOn = options.listenOn || window;
	const restFetch = options.restFetch;
	const parentWindow = listenOn.parent;

	let ready = false;
	let destroyed = false;
	let resizeTimer = 0;
	let lastHeight = 0;

	/**
	 * Post to the iframe only. Never to parent.
	 *
	 * @param {Object} message
	 */
	function post( message ) {
		if ( destroyed || ! iframe.contentWindow ) {
			return;
		}
		iframe.contentWindow.postMessage( message, entryOrigin || hostOrigin );
	}

	/**
	 * @param {string} requestId
	 * @param {string} code
	 * @param {string} [text]
	 */
	function postError( requestId, code, text ) {
		post(
			makeEnvelope(
				'error',
				{
					code,
					message: text || code,
				},
				requestId
			)
		);
	}

	/**
	 * @param {string} requestId
	 * @param {*}      payload
	 */
	function postResult( requestId, payload ) {
		post( makeEnvelope( 'result', payload || {}, requestId ) );
	}

	function sendInit() {
		const hasSiteRead = permissions.indexOf( 'site:read' ) !== -1;
		post(
			makeEnvelope( 'init', {
				app_id: appId,
				locale: options.locale || 'zh_CN',
				plugin_version: options.pluginVersion || '',
				host_origin: hostOrigin,
				context: hasSiteRead && options.context ? options.context : {},
			} )
		);
	}

	/**
	 * @param {number} height
	 */
	function scheduleResize( height ) {
		lastHeight = height;
		if ( resizeTimer ) {
			listenOn.clearTimeout( resizeTimer );
		}
		resizeTimer = listenOn.setTimeout( () => {
			resizeTimer = 0;
			if ( typeof options.onResize === 'function' ) {
				options.onResize( lastHeight );
			}
		}, RESIZE_DEBOUNCE_MS );
	}

	/**
	 * @param {Object}  rest
	 * @param {string}  requestId
	 * @param {boolean} write
	 */
	function forwardRest( rest, requestId, write ) {
		if ( typeof restFetch !== 'function' ) {
			postError( requestId, ERR_HOST_TIMEOUT );
			return;
		}

		let timedOut = false;
		const timer = listenOn.setTimeout( () => {
			timedOut = true;
			postError( requestId, ERR_HOST_TIMEOUT );
		}, HOST_TIMEOUT_MS );

		const request = {
			path: rest.path,
			method: rest.method,
		};
		if ( rest.data !== undefined && rest.method !== 'GET' ) {
			request.data = rest.data;
		}

		Promise.resolve( restFetch( request ) )
			.then( ( body ) => {
				listenOn.clearTimeout( timer );
				if ( timedOut || destroyed ) {
					return;
				}
				if ( write && rest.method === 'POST' && body && body.url ) {
					const open =
						options.openUrl ||
						( ( url ) => {
							listenOn.open(
								url,
								'_blank',
								'noopener,noreferrer'
							);
						} );
					open( body.url );
					if ( typeof options.onGo === 'function' ) {
						options.onGo( body.url );
					}
				}
				postResult( requestId, body );
			} )
			.catch( ( error ) => {
				listenOn.clearTimeout( timer );
				if ( timedOut || destroyed ) {
					return;
				}
				const code = ( error && error.code ) || 'wpcy_apps_unknown_app';
				const text = ( error && error.message ) || code;
				postError( requestId, code, text );
			} );
	}

	/**
	 * @param {MessageEvent} event
	 */
	function onMessage( event ) {
		if ( destroyed ) {
			return;
		}
		if ( parentWindow && event.source === parentWindow ) {
			return;
		}

		const decision = classify( {
			data: event.data,
			origin: event.origin,
			entry_origin: entryOrigin,
			source_is_iframe: event.source === iframe.contentWindow,
			source_is_parent: parentWindow
				? event.source === parentWindow
				: false,
			ready,
			permissions,
			app_id: appId,
		} );

		if ( decision.action === 'discard' ) {
			return;
		}
		if ( decision.action === 'error' ) {
			postError( decision.request_id, decision.code );
			return;
		}
		if ( decision.action === 'init' ) {
			ready = true;
			sendInit();
			return;
		}
		if ( decision.action === 'resize' ) {
			scheduleResize( decision.height );
			return;
		}
		if ( decision.action === 'rest' ) {
			forwardRest( decision.rest, decision.request_id, decision.write );
		}
	}

	listenOn.addEventListener( 'message', onMessage );

	return {
		post,
		isReady() {
			return ready;
		},
		destroy() {
			destroyed = true;
			listenOn.removeEventListener( 'message', onMessage );
			if ( resizeTimer ) {
				listenOn.clearTimeout( resizeTimer );
			}
		},
	};
}
