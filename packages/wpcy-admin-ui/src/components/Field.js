import { createElement } from 'react';

function cx(...parts) {
	return parts.filter(Boolean).join(' ');
}

/**
 * Settings field row — `.field` / `.field-label` / `.field-help` / `.field-ctl` (CTRL-05).
 * Label column + control column; icon is a node (no hard-bound icon set).
 *
 * @param {object} props
 * @param {import('react').ReactNode} [props.label]
 * @param {import('react').ReactNode} [props.title] — alias of label
 * @param {import('react').ReactNode} [props.help]
 * @param {import('react').ReactNode} [props.icon]
 * @param {string} [props.className]
 * @param {import('react').ReactNode} props.children — goes in `.field-ctl`
 */
export function Field({
	label,
	title,
	help,
	icon = null,
	className = '',
	children,
	...rest
}) {
	const heading = label ?? title ?? null;
	return createElement(
		'div',
		{ ...rest, className: cx('field', className) },
		createElement(
			'div',
			null,
			heading || icon
				? createElement(
						'div',
						{ className: 'field-label' },
						icon,
						heading
				  )
				: null,
			help != null && help !== ''
				? createElement('div', { className: 'field-help' }, help)
				: null
		),
		createElement('div', { className: 'field-ctl' }, children)
	);
}

/**
 * Hint under a control — `.hint` / `.hint.is-bad` (CTRL-05).
 */
export function Hint({ bad = false, className = '', children, ...rest }) {
	return createElement(
		'div',
		{ ...rest, className: cx('hint', bad && 'is-bad', className) },
		children
	);
}

/**
 * Radio/check option stack — `.opts` / `.opt` (CTRL-06 companion).
 */
export function Opts({ className = '', children, ...rest }) {
	return createElement('div', { ...rest, className: cx('opts', className) }, children);
}

export function Opt({
	title: t,
	description,
	className = '',
	children,
	...rest
}) {
	return createElement(
		'label',
		{ ...rest, className: cx('opt', className) },
		children,
		createElement(
			'span',
			null,
			t != null ? createElement('div', { className: 't' }, t) : null,
			description != null
				? createElement('div', { className: 'd' }, description)
				: null
		)
	);
}

Field.Hint = Hint;
Field.Opts = Opts;
Field.Opt = Opt;
export default Field;
