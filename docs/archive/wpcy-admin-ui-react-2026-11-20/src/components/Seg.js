import { createElement } from 'react';

function cx(...parts) {
	return parts.filter(Boolean).join(' ');
}

/**
 * Segmented control shell — `.seg` (CTRL-02).
 * Items are nav links or pressed buttons; do not invent a second pill SPA.
 *
 * @param {object} props
 * @param {string} [props.className]
 * @param {import('react').ReactNode} props.children
 */
export function Seg({ className = '', children, ...rest }) {
	return createElement(
		'span',
		{ ...rest, className: cx('seg', className) },
		children
	);
}

/**
 * One segment — renders <a> when `href` is set, else <button type="button">.
 *
 * @param {object} props
 * @param {boolean} [props.active]
 * @param {string} [props.href]
 * @param {boolean} [props.disabled]
 * @param {string} [props.className]
 * @param {import('react').ReactNode} props.children
 */
export function SegItem({
	active = false,
	href,
	disabled = false,
	className = '',
	children,
	...rest
}) {
	const classes = cx(active && 'on', className);
	if (href != null && href !== '') {
		if (disabled) {
			return createElement(
				'a',
				{
					...rest,
					className: classes,
					role: 'link',
					'aria-disabled': 'true',
					tabIndex: -1,
				},
				children
			);
		}
		const linkProps = { ...rest, className: classes, href };
		// Nav links: active + href → aria-current=page (kit mode_switch / subnav parity).
		if (active && linkProps['aria-current'] == null) {
			linkProps['aria-current'] = 'page';
		}
		return createElement('a', linkProps, children);
	}
	return createElement(
		'button',
		{
			...rest,
			type: 'button',
			className: classes,
			'aria-pressed': active ? 'true' : 'false',
			disabled: disabled || undefined,
		},
		children
	);
}

Seg.Item = SegItem;
export default Seg;
