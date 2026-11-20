import { createElement } from 'react';

/**
 * Section heading — `.sec-h` / `.sec-r` (SHELL-10).
 * Wrap in `.sec` yourself for section spacing when needed.
 */
export function Sec({ title, note, action, caps = false, className = '', children }) {
	const cls = ['sec-h', caps && 'caps', className].filter(Boolean).join(' ');
	return createElement(
		'div',
		{ className: cls },
		createElement('h2', null, title),
		note || action
			? createElement(
					'div',
					{ className: 'sec-r' },
					note ? createElement('p', null, note) : null,
					action || null
				)
			: null,
		children
	);
}

export default Sec;
