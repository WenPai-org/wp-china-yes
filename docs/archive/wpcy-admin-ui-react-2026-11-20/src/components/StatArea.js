import { createElement } from 'react';
import { Area } from './Area.js';
import { Spin } from './Spin.js';

function cx(...parts) {
	return parts.filter(Boolean).join(' ');
}

/**
 * Stat card with optional Area sparkline — `.card.stat-a` (P2-charts).
 * Empty: `.v.none`, no SVG. Loading: compose `.is-busy` + Spin (no `.btn.is-loading`).
 *
 * @param {object}          props
 * @param {string}          props.label
 * @param {string}          props.value
 * @param {string}          [props.unit]
 * @param {string}          [props.delta]
 * @param {string}          props.desc
 * @param {number[]}        [props.points]
 * @param {boolean}         [props.empty=false]
 * @param {boolean}         [props.busy=false]
 * @param {import('react').ReactNode} [props.icon]
 * @param {string}          [props.className]
 */
export function StatArea({
	label,
	value,
	unit,
	delta,
	desc,
	points,
	empty = false,
	busy = false,
	icon = null,
	className = '',
	...rest
}) {
	const showArea = !empty && Array.isArray(points) && points.length >= 2;
	return createElement(
		'article',
		{
			...rest,
			className: cx('card', 'stat-a', busy && 'is-busy', className),
			'aria-busy': busy ? 'true' : undefined,
		},
		createElement(
			'div',
			{ className: 'k' },
			icon,
			label
		),
		empty
			? createElement('div', { className: 'v none' }, value)
			: createElement(
					'div',
					{ className: 'v' },
					value,
					unit ? createElement('small', null, unit) : null,
					delta
						? createElement('span', { className: 'delta' }, delta)
						: null
				),
		createElement('div', { className: 'd' }, desc),
		busy
			? createElement(
					'div',
					{ className: 'stat-a-busy' },
					createElement(Spin),
					'正在…'
				)
			: null,
		showArea ? createElement(Area, { points }) : null
	);
}

export default StatArea;
