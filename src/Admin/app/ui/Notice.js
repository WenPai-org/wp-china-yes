/**
 * Thin adapter: package Notice; default wrap=false (no .wpcy-notice-wrap)
 * to match prior product layout. `tone` is already a package alias for `level`.
 */

import { createElement } from '@wordpress/element';
import { Notice as UiNotice } from '@wenpai/wpcy-admin-ui';

/**
 * @param {Object}                    props
 * @param {boolean}                   [props.wrap=false]
 * @param {import('react').ReactNode} [props.children]
 */
export default function Notice( { wrap = false, ...rest } ) {
	return createElement( UiNotice, { ...rest, wrap } );
}

export { Notice };
