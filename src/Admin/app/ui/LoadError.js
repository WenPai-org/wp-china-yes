/**
 * Per-region read failure. SH-06.
 *
 * The visible notice is not a live region; its text is announced through the
 * persistent @wordpress/a11y live region via speak() (admin-ui-spec §8).
 *
 * Failures that arrive within ANNOUNCE_WINDOW_MS of the first one form one
 * load round and are announced together: one region speaks its own sentence,
 * two or more speak a single combined sentence (a screen reader only reads the
 * last of several quick announcements). A retry that fails again mounts a new
 * notice and starts a new round.
 */

import { __, _n, sprintf } from '@wordpress/i18n';
import Notice from './Notice';
import Btn from './Btn';
// @wordpress/a11y ships with WordPress core (wp-a11y) and is externalized by wp-scripts.
// eslint-disable-next-line import/no-extraneous-dependencies
import { speak } from '@wordpress/a11y';
import { useEffect } from '@wordpress/element';

/** Collection window for one load round, from the first failure. */
export const ANNOUNCE_WINDOW_MS = 300;

let pending = [];
let timer = null;

function flush() {
	timer = null;
	const round = pending;
	pending = [];
	if ( round.length === 0 ) {
		return;
	}
	if ( round.length === 1 ) {
		speak( round[ 0 ].text, 'polite' );
		return;
	}
	speak(
		sprintf(
			/* translators: %d: number of regions that failed to load */
			_n(
				'有 %d 项内容暂时无法读取，请刷新页面重试。',
				'有 %d 项内容暂时无法读取，请刷新页面重试。',
				round.length,
				'wp-china-yes'
			),
			round.length
		),
		'polite'
	);
}

/**
 * Add one failed region to the current round.
 *
 * @param {{text:string}} entry Region entry.
 */
function enqueue( entry ) {
	pending.push( entry );
	if ( timer === null ) {
		timer = setTimeout( flush, ANNOUNCE_WINDOW_MS );
	}
}

/**
 * Drop a region that recovered (or unmounted) before the round was announced.
 *
 * @param {{text:string}} entry Region entry.
 */
function dequeue( entry ) {
	pending = pending.filter( ( item ) => item !== entry );
}

/**
 * @param {Object}   props
 * @param {string}   props.what    Region name from the glossary.
 * @param {Function} props.onRetry
 */
export default function LoadError( { what, onRetry } ) {
	const text = sprintf(
		/* translators: %s: region name */
		__( '暂时无法读取%s，请刷新页面重试。', 'wp-china-yes' ),
		what
	);
	useEffect( () => {
		const entry = { text };
		enqueue( entry );
		return () => dequeue( entry );
	}, [ text ] );
	return (
		<Notice
			tone="warn"
			act
			action={
				<Btn variant="secondary" onClick={ onRetry }>
					{ __( '重试', 'wp-china-yes' ) }
				</Btn>
			}
		>
			{ text }
		</Notice>
	);
}
