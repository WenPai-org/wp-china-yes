import { createElement } from 'react';

/**
 * Simple status row — `.simple-row` (CARD-11).
 *
 * @param {object} props
 * @param {import('react').ReactNode} [props.tile]
 * @param {import('react').ReactNode} [props.title]
 * @param {import('react').ReactNode} [props.desc]
 * @param {import('react').ReactNode} [props.right]
 * @param {string} [props.className]
 */
export function SimpleRow({
	tile = null,
	title,
	desc,
	right = null,
	className = '',
	children,
	...rest
}) {
	return createElement(
		'div',
		{ ...rest, className: ['simple-row', className].filter(Boolean).join(' ') },
		tile,
		createElement(
			'div',
			null,
			title != null ? createElement('div', { className: 't' }, title) : null,
			desc != null ? createElement('div', { className: 'd' }, desc) : null,
			children
		),
		right ? createElement('div', { className: 'r' }, right) : null
	);
}

export default SimpleRow;
