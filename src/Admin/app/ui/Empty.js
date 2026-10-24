/**
 * Thin adapter: package Empty wants ReactNode `icon`; product may pass string name.
 */

import { createElement } from '@wordpress/element';
import { Empty as UiEmpty } from '@wenpai/wpcy-admin-ui';
import Icon from './icons';

/**
 * @param {Object}                           props
 * @param {string|import('react').ReactNode} [props.icon='grid']
 * @param {import('react').ReactNode}        props.why
 * @param {import('react').ReactNode}        [props.when]
 * @param {string}                           [props.role]
 * @param {string}                           [props.className]
 * @param {import('react').ReactNode}        [props.children]
 */
export default function Empty( {
	icon = 'grid',
	why,
	when,
	role,
	className = '',
	children,
} ) {
	const iconNode =
		typeof icon === 'string'
			? createElement( Icon, { name: icon, size: 20 } )
			: icon;
	return createElement(
		UiEmpty,
		{ icon: iconNode, why, when, role, className },
		children
	);
}

export { Empty };
