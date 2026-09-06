/**
 * Stat card with area() SVG from parts_v5.area().
 *
 */

import { useId } from '@wordpress/element';
import Icon from './icons';

/**
 * Area path from a list of numbers. Same algorithm as parts_v5.area().
 *
 * @param {number[]} points
 * @param {number}   [w=300]
 * @param {number}   [h=44]
 * @return {{ d: string, fill: string }} Path data.
 */
export function areaPath( points, w = 300, h = 44 ) {
	const n = points.length;
	const mx = Math.max( ...points, 0 ) || 1;
	const xs = points.map( ( _, i ) =>
		n === 1 ? 0 : ( i * w ) / ( n - 1 )
	);
	const ys = points.map( ( p ) => h - 6 - ( p / mx ) * ( h - 12 ) );
	const d =
		'M' +
		xs
			.map( ( x, i ) => `${ x.toFixed( 1 ) },${ ys[ i ].toFixed( 1 ) }` )
			.join( ' L' );
	const fill = d + ` L${ w },${ h } L0,${ h } Z`;
	return { d, fill };
}

/**
 * @param {Object}   props
 * @param {number[]} props.points
 * @param {string}   [props.color]
 */
export function Area( { points, color = '#3858e9' } ) {
	const gid = 'g' + useId().replace( /:/g, '' );
	if ( ! points || points.length < 2 ) {
		return null;
	}
	const { d, fill } = areaPath( points );
	return (
		<svg
			className="area"
			viewBox="0 0 300 44"
			preserveAspectRatio="none"
			aria-hidden="true"
		>
			<defs>
				<linearGradient id={ gid } x1="0" y1="0" x2="0" y2="1">
					<stop offset="0" stopColor={ color } stopOpacity=".18" />
					<stop offset="1" stopColor={ color } stopOpacity="0" />
				</linearGradient>
			</defs>
			<path d={ fill } fill={ `url(#${ gid })` } />
			<path
				d={ d }
				fill="none"
				stroke={ color }
				strokeWidth="1.75"
				strokeLinejoin="round"
			/>
		</svg>
	);
}

/**
 * @param {Object}   props
 * @param {string}   props.icon
 * @param {string}   props.label
 * @param {string}   props.value
 * @param {string}   [props.unit]
 * @param {string}   [props.delta]
 * @param {string}   props.desc
 * @param {number[]} [props.points]
 * @param {boolean}  [props.empty]
 */
export default function StatArea( {
	icon,
	label,
	value,
	unit,
	delta,
	desc,
	points,
	empty = false,
} ) {
	return (
		<article className="card stat-a">
			<div className="k">
				<Icon name={ icon } size={ 18 } />
				{ label }
			</div>
			{ empty ? (
				<div className="v none">{ value }</div>
			) : (
				<div className="v">
					{ value }
					{ unit ? <small>{ unit }</small> : null }
					{ delta ? (
						<span className="delta">
							<Icon name="up" size={ 12 } />
							{ delta }
						</span>
					) : null }
				</div>
			) }
			<div className="d">{ desc }</div>
			{ ! empty && points && points.length ? (
				<Area points={ points } />
			) : null }
		</article>
	);
}
