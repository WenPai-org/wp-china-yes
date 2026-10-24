import { createElement } from 'react';

function cx(...parts) {
	return parts.filter(Boolean).join(' ');
}

/**
 * Busy spinner — `.spin` (CTRL-12).
 * Decorative only (`aria-hidden`); pair with visible busy text / disabled controls.
 * Animation respects `prefers-reduced-motion` (CSS).
 *
 * @param {object} props
 * @param {string} [props.className]
 */
export function Spin({ className = '', ...rest }) {
	return createElement('span', {
		...rest,
		className: cx('spin', className),
		'aria-hidden': 'true',
	});
}

export default Spin;
