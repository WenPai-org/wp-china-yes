/**
 * Command palette: Core @wordpress/commands plus optional wp.os.
 *
 * Does not steal ⌘K. Feature-detects wp.os.registerCommand only.
 */

import { useCommands } from '@wordpress/commands';
import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import { adminPageUrl, goToPage, PAGES } from './routing';

const COMMANDS = [
	{
		name: 'wpcy/open-overview',
		label: __( '打开文派叶子概览', 'wp-china-yes' ),
		callback: ( { close } ) => {
			goToPage( PAGES.overview );
			close();
		},
		osHref: () => adminPageUrl( PAGES.overview ),
	},
	{
		name: 'wpcy/open-connect',
		label: __( '打开连接优化', 'wp-china-yes' ),
		callback: ( { close } ) => {
			goToPage( PAGES.connect );
			close();
		},
		osHref: () => adminPageUrl( PAGES.connect ),
	},
	{
		name: 'wpcy/open-services',
		label: __( '打开文派服务', 'wp-china-yes' ),
		callback: ( { close } ) => {
			goToPage( PAGES.services );
			close();
		},
		osHref: () => adminPageUrl( PAGES.services ),
	},
	{
		name: 'wpcy/run-diagnostics',
		label: __( '运行连接诊断', 'wp-china-yes' ),
		callback: ( { close } ) => {
			goToPage( PAGES.diagnose );
			close();
		},
		osHref: () => adminPageUrl( PAGES.diagnose ),
	},
	{
		name: 'wpcy/open-recovery',
		label: __( '进入恢复模式', 'wp-china-yes' ),
		callback: ( { close } ) => {
			goToPage( PAGES.recovery );
			close();
		},
		osHref: () => adminPageUrl( PAGES.recovery ),
	},
];

/**
 * Register the five commands with Core, and with OpenStation when present.
 */
export default function Commands() {
	useCommands(
		COMMANDS.map( ( command ) => ( {
			name: command.name,
			label: command.label,
			callback: command.callback,
		} ) )
	);

	useEffect( () => {
		const register = window.wp?.os?.registerCommand;
		if ( typeof register !== 'function' ) {
			return undefined;
		}

		COMMANDS.forEach( ( command ) => {
			register( {
				id: command.name,
				label: command.label,
				callback: () => {
					window.location.assign( command.osHref() );
				},
			} );
		} );

		return undefined;
	}, [] );

	return null;
}
