import { createElement, forwardRef } from 'react';

const VARIANTS = new Set(['primary', 'secondary', 'ghost', 'danger']);

/**
 * Dual-stack button — shared classes `.btn` / `.btn-*` under `.wpcy-app`.
 * No `:active` contract color (Q1). No `.is-loading` in P2a (Q2).
 *
 * @param {object} props
 * @param {'primary'|'secondary'|'ghost'|'danger'} [props.variant='secondary']
 * @param {string} [props.href] — render <a> when set
 * @param {boolean} [props.disabled]
 * @param {boolean} [props.needApply] — data-wenpai-need-apply (kit attr name kept)
 * @param {string} [props.busyLabel] — data-wenpai-busy
 * @param {string} [props.className]
 * @param {import('react').ReactNode} [props.children]
 */
export const Btn = forwardRef(function Btn(props, ref) {
	const {
		variant = 'secondary',
		href,
		target,
		type = 'button',
		disabled = false,
		needApply = false,
		busyLabel,
		className = '',
		children,
		label,
		...rest
	} = props;

	const v = VARIANTS.has(variant) ? variant : 'secondary';
	const classes = ['btn', `btn-${v}`, className].filter(Boolean).join(' ');
	const content = children ?? label ?? null;

	const dataAttrs = {};
	if (needApply) dataAttrs['data-wenpai-need-apply'] = '';
	if (busyLabel) dataAttrs['data-wenpai-busy'] = busyLabel;

	if (href != null && href !== '') {
		if (disabled) {
			return createElement(
				'a',
				{
					...rest,
					...dataAttrs,
					ref,
					className: classes,
					role: 'link',
					'aria-disabled': 'true',
					tabIndex: -1,
				},
				content
			);
		}
		const aProps = {
			...rest,
			...dataAttrs,
			ref,
			className: classes,
			href,
		};
		if (target) {
			aProps.target = target;
			if (target === '_blank') {
				aProps.rel = 'noopener noreferrer';
			}
		}
		return createElement('a', aProps, content);
	}

	return createElement(
		'button',
		{
			...rest,
			...dataAttrs,
			ref,
			type: type === 'submit' ? 'submit' : 'button',
			className: classes,
			disabled: disabled || undefined,
		},
		content
	);
});

Btn.displayName = 'Btn';
export default Btn;
