/**
 * Empty state. Prototype .empty — never a bare empty table.
 *
 */

import { Tile } from './Card';

/**
 * @param {Object} props
 * @param {string} [props.icon]
 * @param {string} props.why
 * @param {string} [props.when]
 */
export default function Empty( { icon = 'grid', why, when } ) {
	return (
		<div className="empty">
			<Tile icon={ icon } />
			<p>{ why }</p>
			{ when ? <p className="meta">{ when }</p> : null }
		</div>
	);
}
