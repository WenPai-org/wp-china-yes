import { createElement } from 'react';
import { Tile } from './Card.js';

/**
 * Next-step bar — `.next` (CARD-05). At most one per page.
 *
 * @param {object} props
 * @param {import('react').ReactNode} [props.icon]
 * @param {import('react').ReactNode} [props.action]
 * @param {import('react').ReactNode} props.children
 * @param {string} [props.className]
 */
export function Next({ icon = null, action = null, className = '', children }) {
	return createElement(
		'div',
		{ className: ['next', className].filter(Boolean).join(' ') },
		icon ? createElement(Tile, null, icon) : null,
		createElement('div', null, children),
		action
	);
}

export default Next;
