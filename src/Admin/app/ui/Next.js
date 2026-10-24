/**
 * Thin adapter: package Next wants ReactNode `icon`; product may pass string name.
 */

import { createElement } from '@wordpress/element';
import { Next as UiNext } from '@wenpai/wpcy-admin-ui';
import Icon from './icons';

/**
 * @param {Object}                           props
 * @param {string|import('react').ReactNode} [props.icon]
 * @param {import('react').ReactNode}        [props.action]
 * @param {string}                           [props.className]
 * @param {import('react').ReactNode}        props.children
 */
export default function Next( {
	icon = 'link',
	action = null,
	className = '',
	children,
} ) {
	const iconNode =
		typeof icon === 'string'
			? createElement( Icon, { name: icon, size: 20 } )
			: icon;
	return createElement(
		UiNext,
		{ icon: iconNode, action, className },
		children
	);
}

export { Next };
