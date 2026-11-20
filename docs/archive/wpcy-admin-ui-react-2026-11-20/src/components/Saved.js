import { createElement } from 'react';

/**
 * Inline save flash — `.saved` (CARD-09). Prefer next to the control, not a page banner.
 */
export function Saved({ className = '', children = '已保存', ...rest }) {
	return createElement(
		'span',
		{ ...rest, className: ['saved', className].filter(Boolean).join(' ') },
		children
	);
}

export default Saved;
