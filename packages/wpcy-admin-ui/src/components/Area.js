import { createElement, useId } from 'react';

/**
 * Area path from a list of numbers (leaf StatArea / parts_v5.area algorithm).
 * viewBox contract: 300×44.
 *
 * Public helper export (also re-exported from package root). Q4 surface API
 * remains StatArea + Area; areaPath is path math only, not a surface component.
 *
 * @param {number[]} points
 * @param {number}   [w=300]
 * @param {number}   [h=44]
 * @return {{ d: string, fill: string }}
 */
export function areaPath(points, w = 300, h = 44) {
	const n = points.length;
	const mx = Math.max(...points, 0) || 1;
	const xs = points.map((_, i) => (n === 1 ? 0 : (i * w) / (n - 1)));
	const ys = points.map((p) => h - 6 - (p / mx) * (h - 12));
	const d =
		'M' +
		xs.map((x, i) => `${x.toFixed(1)},${ys[i].toFixed(1)}`).join(' L');
	const fill = d + ` L${w},${h} L0,${h} Z`;
	return { d, fill };
}

/**
 * Area sparkline — `.area` SVG (P2-charts).
 * Renders null when points length &lt; 2.
 *
 * @param {object}   props
 * @param {number[]} props.points
 * @param {string}   [props.color='var(--wpcy-accent)']
 * @param {string}   [props.className]
 */
export function Area({
	points,
	color = 'var(--wpcy-accent)',
	className = '',
	...rest
}) {
	const rawId = useId().replace(/:/g, '');
	const gid = `wpcy-area-${rawId}`;
	if (!points || points.length < 2) {
		return null;
	}
	const { d, fill } = areaPath(points);
	return createElement(
		'svg',
		{
			...rest,
			className: ['area', className].filter(Boolean).join(' '),
			viewBox: '0 0 300 44',
			preserveAspectRatio: 'none',
			'aria-hidden': 'true',
		},
		createElement(
			'defs',
			null,
			createElement(
				'linearGradient',
				{ id: gid, x1: '0', y1: '0', x2: '0', y2: '1' },
				createElement('stop', {
					offset: '0',
					stopColor: color,
					stopOpacity: '.18',
				}),
				createElement('stop', {
					offset: '1',
					stopColor: color,
					stopOpacity: '0',
				})
			)
		),
		createElement('path', { d: fill, fill: `url(#${gid})` }),
		createElement('path', {
			d,
			fill: 'none',
			stroke: color,
			strokeWidth: '1.75',
			strokeLinejoin: 'round',
		})
	);
}

export default Area;
