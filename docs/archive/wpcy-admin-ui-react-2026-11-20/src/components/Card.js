import { createElement } from 'react';

function cx(...parts) {
	return parts.filter(Boolean).join(' ');
}

/**
 * Icon tile — `.tile` / `.tile.ok|warn|bad|accent` (CARD-03).
 * Pass children (svg/node); no hard-bound icon set.
 */
export function Tile({ tone = '', className = '', children, ...rest }) {
	return createElement(
		'div',
		{ ...rest, className: cx('tile', tone, className) },
		children
	);
}

/** Card head: title / sub / trailing / leading tile (CARD-02). */
export function CardHead({
	title,
	sub,
	icon,
	tile,
	trailing,
	extra,
	headingLevel = 2,
	className = '',
	children,
}) {
	const Heading = `h${Math.min(6, Math.max(1, Number(headingLevel) || 2))}`;
	const lead = tile || icon || null;
	const trail = trailing || extra || null;
	const hasText = title || sub || lead || trail || children;
	if (!hasText) return null;
	return createElement(
		'div',
		{ className: cx('card-head', className) },
		lead,
		createElement(
			'div',
			null,
			title ? createElement(Heading, { className: 'card-title' }, title) : null,
			sub ? createElement('p', { className: 'card-sub' }, sub) : null,
			children
		),
		trail
	);
}

/** Card footer — actions right-aligned; `start` → space-between (CARD-02). */
export function CardFoot({ meta, start = false, className = '', children }) {
	return createElement(
		'div',
		{ className: cx('card-foot', start && 'start', className) },
		meta != null && meta !== ''
			? createElement('span', { className: 'meta' }, meta)
			: null,
		children
	);
}

/**
 * Card container — `.card` / `.card.tight` (CARD-01).
 * Prefer children over HTML strings (no kses).
 */
export function Card({
	tight = false,
	as = 'section',
	className = '',
	children,
	...rest
}) {
	return createElement(
		as,
		{ ...rest, className: cx('card', tight && 'tight', className) },
		children
	);
}

Card.Head = CardHead;
Card.Foot = CardFoot;
Card.Tile = Tile;

export default Card;
