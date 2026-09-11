/**
 * Next-step bar. Prototype .next. At most one per page.
 *
 */

import { Tile } from './Card';

/**
 * @param {Object}                    props
 * @param {string}                    [props.icon]
 * @param {import('react').ReactNode} props.children
 * @param {import('react').ReactNode} props.action
 */
export default function Next( { icon = 'link', children, action } ) {
	return (
		<div className="next" style={ { marginTop: 16 } }>
			<Tile icon={ icon } />
			<div>{ children }</div>
			{ action }
		</div>
	);
}
