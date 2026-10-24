/**
 * Thin adapter: package Card family; Tile still accepts product string `icon`.
 * Card defaults `as="article"` to match prior product markup.
 */

import { createElement } from '@wordpress/element';
import {
	Card as UiCard,
	CardHead,
	CardFoot,
	Tile as UiTile,
} from '@wenpai/wpcy-admin-ui';
import Icon from './icons';

/**
 * @param {Object}                    props
 * @param {string}                    [props.tone]
 * @param {string}                    [props.icon]      String name → product Icon.
 * @param {import('react').ReactNode} [props.children]
 * @param {string}                    [props.className]
 */
export function Tile( { tone = '', icon, children, className = '', ...rest } ) {
	const content = icon
		? createElement( Icon, { name: icon, size: 20 } )
		: children;
	return createElement( UiTile, { ...rest, tone, className }, content );
}

/**
 * @param {Object}                    props
 * @param {boolean}                   [props.tight]
 * @param {string}                    [props.className]
 * @param {string}                    [props.as='article']
 * @param {import('react').ReactNode} props.children
 */
export default function Card( {
	tight = false,
	className = '',
	as = 'article',
	children,
	...rest
} ) {
	return createElement( UiCard, { ...rest, tight, className, as }, children );
}

export { Card, CardHead, CardFoot };
