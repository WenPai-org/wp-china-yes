import { createElement } from 'react';

function cx(...parts) {
	return parts.filter(Boolean).join(' ');
}

/**
 * Switch with visible label — `.toggle` / `.on` / `.off-dis` (CTRL-03).
 * Requires a text `label` (contract: no unlabeled switches).
 *
 * @param {object} props
 * @param {boolean} props.checked
 * @param {import('react').ReactNode} props.label
 * @param {(next: boolean) => void} [props.onChange]
 * @param {boolean} [props.disabled]
 * @param {string} [props.className]
 */
export function Toggle({
	checked = false,
	label,
	onChange,
	disabled = false,
	className = '',
	...rest
}) {
	return createElement(
		'button',
		{
			...rest,
			type: 'button',
			className: cx('toggle', checked && 'on', disabled && 'off-dis', className),
			role: 'switch',
			'aria-checked': checked ? 'true' : 'false',
			disabled: disabled || undefined,
			onClick: (e) => {
				if (rest.onClick) rest.onClick(e);
				if (e.defaultPrevented) return;
				if (!disabled && typeof onChange === 'function') {
					onChange(!checked);
				}
			},
		},
		createElement('i', { 'aria-hidden': 'true' }),
		label
	);
}

export default Toggle;
