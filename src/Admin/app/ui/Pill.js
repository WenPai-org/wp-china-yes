/**
 * Thin adapter: package Pill uses `state`; product call sites still pass `tone`.
 */

import { createElement } from '@wordpress/element';
import { Pill as UiPill } from '@wenpai/wpcy-admin-ui';

/**
 * @param {Object}                    props
 * @param {string}                    [props.tone]      ok | warn | bad | mute | ''
 * @param {string}                    [props.state]     Alias preferred by package.
 * @param {string}                    [props.className]
 * @param {import('react').ReactNode} [props.children]
 * @param {string}                    [props.text]
 */
export default function Pill( {
	tone,
	state,
	className = '',
	children,
	text,
	...rest
} ) {
	return createElement(
		UiPill,
		{
			...rest,
			state: state ?? tone ?? '',
			className,
			text,
		},
		children
	);
}

export { Pill };
