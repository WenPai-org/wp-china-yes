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
 * @param {string} [props.role] Optional ARIA role for the wrapper; unset by
 *                              default. role="status" on a node that mounts
 *                              together with its text is not announced
 *                              reliably (admin-ui-spec §8): render async
 *                              states inside a persistent live region instead.
 */
export default function Empty( { icon = 'grid', why, when, role } ) {
	return (
		<div className="empty" role={ role || undefined }>
			<Tile icon={ icon } />
			<p>{ why }</p>
			{ when ? <p className="meta">{ when }</p> : null }
		</div>
	);
}
