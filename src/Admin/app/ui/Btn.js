/**
 * Thin adapter: package Btn; strip product `isBusy` → disabled + aria-busy.
 * Call sites still pass WordPress-components-style `isBusy`; package Btn
 * would forward it to the DOM and React warns (Connect/Onboarding jest).
 */

import { createElement } from '@wordpress/element';
import { Btn as UiBtn } from '@wenpai/wpcy-admin-ui';

/**
 * @param {Object}                    props
 * @param {boolean}                   [props.isBusy]
 * @param {boolean}                   [props.disabled]
 * @param {import('react').ReactNode} [props.children]
 */
export default function Btn( {
	isBusy = false,
	disabled = false,
	children,
	...rest
} ) {
	return createElement(
		UiBtn,
		{
			...rest,
			disabled: Boolean( disabled || isBusy ),
			'aria-busy': isBusy ? 'true' : undefined,
		},
		children
	);
}

export { Btn };
