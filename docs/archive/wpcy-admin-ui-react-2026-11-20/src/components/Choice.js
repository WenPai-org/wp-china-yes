import { createElement } from 'react';

function cx(...parts) {
	return parts.filter(Boolean).join(' ');
}

/**
 * Choice card grid — `.choice-cards` / `.cols-2` (CTRL-04).
 * Columns use kit 38rem/26rem auto-fit clamp (P2b Q1); not fixed 3-col + 960 collapse.
 *
 * @param {object} props
 * @param {2|3} [props.cols=3]
 * @param {string} [props.className]
 * @param {import('react').ReactNode} props.children
 */
export function ChoiceCards({ cols = 3, className = '', children, ...rest }) {
	const colClass = cols === 2 ? 'cols-2' : '';
	return createElement(
		'div',
		{ ...rest, className: cx('choice-cards', colClass, className) },
		children
	);
}

/**
 * One choice card — `<label class="choice">`.
 * Contract classes (P2b Q3): `.is-on` / `.is-locked` / `.is-soon`.
 * `disabled` maps to `.is-locked` (gate). Prefer explicit `locked` / `soon`.
 * Leaf `.rec` badge retained (dual-stack documented).
 *
 * @param {object} props
 * @param {boolean} [props.selected]
 * @param {boolean} [props.disabled] — → `.is-locked`
 * @param {boolean} [props.locked] — → `.is-locked`
 * @param {boolean} [props.soon] — → `.is-soon`
 * @param {import('react').ReactNode} [props.title]
 * @param {import('react').ReactNode} [props.description]
 * @param {import('react').ReactNode} [props.recommended]
 * @param {string} [props.htmlFor]
 * @param {string} [props.className]
 * @param {import('react').ReactNode} [props.children]
 */
export function Choice({
	selected = false,
	disabled = false,
	locked = false,
	soon = false,
	title,
	description,
	recommended = null,
	htmlFor,
	className = '',
	children,
	...rest
}) {
	const isLocked = locked || disabled;
	const body =
		title != null || description != null || recommended != null
			? createElement(
					'span',
					null,
					title != null || recommended != null
						? createElement(
								'div',
								{ className: 't' },
								title,
								recommended != null
									? createElement('span', { className: 'rec' }, recommended)
									: null
						  )
						: null,
					description != null
						? createElement('div', { className: 'd' }, description)
						: null
			  )
			: null;

	return createElement(
		'label',
		{
			...rest,
			htmlFor: htmlFor || undefined,
			className: cx(
				'choice',
				selected && 'is-on',
				isLocked && 'is-locked',
				soon && 'is-soon',
				className
			),
		},
		children,
		body
	);
}

Choice.Cards = ChoiceCards;
export default Choice;
