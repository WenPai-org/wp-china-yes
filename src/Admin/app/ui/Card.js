/**
 * Card, CardHead, CardFoot, Tile — prototype E surfaces.
 *
 */

import Icon from './icons';

/**
 * @param {Object}                    props
 * @param {string}                    [props.tone]
 * @param {string}                    [props.icon]
 * @param {import('react').ReactNode} props.children
 * @param {string}                    [props.className]
 */
export function Tile( { tone = '', icon, children, className = '' } ) {
	const cls = [ 'tile', tone, className ].filter( Boolean ).join( ' ' );
	return (
		<div className={ cls }>
			{ icon ? <Icon name={ icon } size={ 20 } /> : children }
		</div>
	);
}

/**
 * @param {Object}                    props
 * @param {import('react').ReactNode} [props.tile]
 * @param {import('react').ReactNode} [props.title]
 * @param {import('react').ReactNode} [props.sub]
 * @param {import('react').ReactNode} [props.extra]
 * @param {import('react').ReactNode} props.children
 */
export function CardHead( { tile, title, sub, extra, children } ) {
	return (
		<div className="card-head">
			{ tile }
			{ title || sub ? (
				<div>
					{ title ? <h2 className="card-title">{ title }</h2> : null }
					{ sub ? <p className="card-sub">{ sub }</p> : null }
				</div>
			) : null }
			{ extra }
			{ children }
		</div>
	);
}

/**
 * @param {Object}                    props
 * @param {string}                    [props.className]
 * @param {import('react').ReactNode} props.children
 */
export function CardFoot( { className = '', children } ) {
	return (
		<div
			className={ [ 'card-foot', className ]
				.filter( Boolean )
				.join( ' ' ) }
		>
			{ children }
		</div>
	);
}

/**
 * @param {Object}                    props
 * @param {boolean}                   [props.tight]
 * @param {string}                    [props.className]
 * @param {string}                    [props.as]
 * @param {import('react').ReactNode} props.children
 */
export default function Card( {
	tight = false,
	className = '',
	as = 'article',
	children,
} ) {
	const Tag = as;
	const cls = [ 'card', tight ? 'tight' : '', className ]
		.filter( Boolean )
		.join( ' ' );
	return <Tag className={ cls }>{ children }</Tag>;
}
