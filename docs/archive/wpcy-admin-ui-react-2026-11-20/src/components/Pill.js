import { createElement } from 'react';

const STATES = new Set(['', 'ok', 'warn', 'bad', 'mute']);

/**
 * Status pill — `.pill` + optional `.ok|.warn|.bad|.mute`.
 * Not a control; wrap in Btn/link if clickable.
 */
export function Pill({ state = '', text, children, className = '', ...rest }) {
	const s = STATES.has(state) ? state : '';
	const classes = ['pill', s, className].filter(Boolean).join(' ');
	return createElement(
		'span',
		{ ...rest, className: classes },
		children ?? text ?? null
	);
}

export default Pill;
