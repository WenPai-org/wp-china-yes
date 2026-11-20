import { createElement } from 'react';

/**
 * Scope chip — `.scope` (CARD-10). Optional link.
 */
export function Scope({ href, className = '', children, ...rest }) {
	const cls = ['scope', className].filter(Boolean).join(' ');
	if (href) {
		return createElement('a', { ...rest, className: cls, href }, children);
	}
	return createElement('span', { ...rest, className: cls }, children);
}

export default Scope;
