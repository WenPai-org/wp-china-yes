import { createElement } from 'react';
import { Tile } from './Card.js';

/**
 * Empty state — `.empty` (CARD-08). Never a bare empty table.
 * Pass `icon` as a React node (svg); do not hard-bind remix icons.
 *
 * @param {object} props
 * @param {import('react').ReactNode} [props.icon]
 * @param {import('react').ReactNode} props.why
 * @param {import('react').ReactNode} [props.when]
 * @param {string} [props.role]
 * @param {string} [props.className]
 */
export function Empty({ icon = null, why, when, role, className = '', children }) {
	return createElement(
		'div',
		{ className: ['empty', className].filter(Boolean).join(' '), role: role || undefined },
		icon ? createElement(Tile, null, icon) : null,
		why ? createElement('p', null, why) : null,
		when ? createElement('p', { className: 'meta' }, when) : null,
		children
	);
}

export default Empty;
