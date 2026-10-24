import { createElement } from 'react';

const LEVELS = new Set(['info', 'ok', 'warn', 'bad']);

/** Default info icon (18×18), matches kit notice icon footprint. */
function InfoIcon() {
	return createElement(
		'svg',
		{
			xmlns: 'http://www.w3.org/2000/svg',
			viewBox: '0 0 24 24',
			width: 18,
			height: 18,
			fill: 'none',
			stroke: 'currentColor',
			strokeWidth: 2,
			'aria-hidden': 'true',
			focusable: 'false',
		},
		createElement('circle', { cx: 12, cy: 12, r: 9 }),
		createElement('line', { x1: 12, y1: 10, x2: 12, y2: 16 }),
		createElement('circle', { cx: 12, cy: 7, r: 0.75, fill: 'currentColor', stroke: 'none' })
	);
}

/**
 * In-app notice — `.notice` + level; optional wrap `.wpcy-notice-wrap`.
 * `act` adds `.act` (for `.notice.warn.act`).
 * `tone` is an alias for `level` (leaf LoadError compat).
 * `action` is a trailing control (e.g. retry `Btn`); CSS pins `.notice .btn`.
 */
export function Notice({
	level,
	tone,
	text,
	children,
	fade = false,
	wrap = true,
	act = false,
	icon,
	action,
	className = '',
	...rest
}) {
	const raw = level ?? tone ?? 'info';
	const lv = LEVELS.has(raw) ? raw : 'info';
	const classes = ['notice', lv, act && 'act', className].filter(Boolean).join(' ');

	let role;
	if (lv === 'bad') role = 'alert';
	else if (lv === 'ok' || lv === 'warn') role = 'status';

	const inner = createElement(
		'div',
		{
			...rest,
			className: classes,
			role,
			...(fade ? { 'data-wenpai-fade': '' } : {}),
		},
		icon === null ? null : icon ?? createElement(InfoIcon),
		createElement('span', null, children ?? text ?? null),
		action ?? null
	);

	if (!wrap) return inner;
	return createElement('div', { className: 'wpcy-notice-wrap' }, inner);
}

export default Notice;
