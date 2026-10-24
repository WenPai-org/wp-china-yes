import { createElement } from 'react';

function cx(...parts) {
	return parts.filter(Boolean).join(' ');
}

/**
 * Choice card grid — `.choice-cards` / `.cols-2` (CTRL-04).
 * Default three columns; `cols={2}` → `.cols-2`. Whole card is the hit target.
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
 * One choice card — `<label class="choice">` with optional radio + title/desc.
 * Re-clicking an already-selected card should be ignored by the consumer.
 *
 * @param {object} props
 * @param {boolean} [props.selected]
 * @param {boolean} [props.disabled]
 * @param {import('react').ReactNode} [props.title]
 * @param {import('react').ReactNode} [props.description]
 * @param {import('react').ReactNode} [props.recommended] — `.rec` badge content (omit if unused)
 * @param {string} [props.htmlFor]
 * @param {string} [props.className]
 * @param {import('react').ReactNode} [props.children] — e.g. the <input type="radio">
 */
export function Choice({
	selected = false,
	disabled = false,
	title,
	description,
	recommended = null,
	htmlFor,
	className = '',
	children,
	...rest
}) {
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
				disabled && 'is-disabled',
				className
			),
		},
		children,
		body
	);
}

Choice.Cards = ChoiceCards;
export default Choice;
