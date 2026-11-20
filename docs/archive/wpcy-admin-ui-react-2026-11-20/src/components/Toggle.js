import { createElement } from 'react';

function cx(...parts) {
	return parts.filter(Boolean).join(' ');
}

/**
 * Switch with visible label — `.toggle` / `.on` / `.off-dis` / `.is-locked` (CTRL-03).
 * Track uses `--wpcy-track-off` / `--wpcy-track-locked` (P2b Q2). Requires text `label`.
 *
 * @param {object} props
 * @param {boolean} props.checked
 * @param {import('react').ReactNode} props.label
 * @param {(next: boolean) => void} [props.onChange]
 * @param {boolean} [props.disabled]
 * @param {boolean} [props.locked] — capability gate (`.is-locked`), distinct from disabled
 * @param {string} [props.className]
 */
export function Toggle({
	checked = false,
	label,
	onChange,
	disabled = false,
	locked = false,
	className = '',
	...rest
}) {
	const inert = disabled || locked;
	return createElement(
		'button',
		{
			...rest,
			type: 'button',
			className: cx(
				'toggle',
				checked && 'on',
				disabled && 'off-dis',
				locked && 'is-locked',
				className
			),
			role: 'switch',
			'aria-checked': checked ? 'true' : 'false',
			'aria-disabled': locked && !disabled ? 'true' : undefined,
			disabled: disabled || undefined,
			onClick: (e) => {
				if (rest.onClick) rest.onClick(e);
				if (e.defaultPrevented) return;
				if (!inert && typeof onChange === 'function') {
					onChange(!checked);
				}
			},
		},
		createElement('i', { 'aria-hidden': 'true' }),
		label
	);
}

export default Toggle;
