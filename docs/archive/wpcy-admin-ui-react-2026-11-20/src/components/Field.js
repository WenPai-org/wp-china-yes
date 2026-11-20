import { createElement, useState } from 'react';

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
export function Hint({ bad = false, ok = false, className = '', children, ...rest }) {
	return createElement(
		'div',
		{ ...rest, className: cx('hint', bad && 'is-bad', ok && 'is-ok', className) },
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


/**
 * Secret control — password + show/hide (P2b Field extension · Track B / E09).
 * Mirrors kit wenpai-field__secret / wenpai-field__reveal. Never put a
 * saved plaintext into value from the caller; empty leave / clear stay server-side.
 *
 * Nested option names (endpoints[provider][api_key]) are the caller's name
 * string; this component only handles the reveal UX.
 *
 * @param {object} props
 * @param {string} [props.id]
 * @param {string} [props.name]
 * @param {string} [props.value] — usually ''
 * @param {boolean} [props.saved] — marks data-wenpai-saved when a value exists server-side
 * @param {string} [props.showLabel='显示']
 * @param {string} [props.hideLabel='隐藏']
 * @param {string} [props.className]
 */
export function SecretInput({
	id,
	name,
	value = '',
	saved = false,
	showLabel = '显示',
	hideLabel = '隐藏',
	className = '',
	disabled = false,
	onChange,
	...rest
}) {
	const [revealed, setRevealed] = useState(false);
	const inputId = id || undefined;
	const data = { 'data-wenpai-secret': name || id || '' };
	if (saved) data['data-wenpai-saved'] = '';
	return createElement(
		'span',
		{ className: cx('wpcy-field__secret', className) },
		createElement('input', {
			...rest,
			...data,
			id: inputId,
			name,
			type: revealed ? 'text' : 'password',
			value,
			disabled,
			onChange,
			autoComplete: 'new-password',
			spellCheck: false,
		}),
		createElement(
			'button',
			{
				type: 'button',
				className: 'btn btn-ghost wpcy-field__reveal',
				'data-wenpai-reveal': '',
				'aria-pressed': revealed ? 'true' : 'false',
				'aria-controls': inputId,
				'aria-label': revealed ? hideLabel : showLabel,
				disabled,
				onClick: () => setRevealed((v) => !v),
			},
			revealed ? hideLabel : showLabel
		)
	);
}

/**
 * Email control — type=email (Track C′ parity with kit Settings email field).
 * Sanitize / is_email live in the PHP kit; this is UX parity only.
 */
export function EmailInput({ className = '', ...rest }) {
	return createElement('input', {
		...rest,
		type: 'email',
		autoComplete: rest.autoComplete ?? 'email',
		className: cx(className),
	});
}

/**
 * URL control — type=url (Track C′ parity with kit Settings url field).
 * esc_url_raw http/https live in the PHP kit; this is UX parity only.
 */
export function UrlInput({ className = '', ...rest }) {
	return createElement('input', {
		...rest,
		type: 'url',
		autoComplete: rest.autoComplete ?? 'url',
		className: cx(className),
	});
}

/**
 * Field-side action / busy row — `.field-ctl-row` + Btn busy (Track C / E10).
 * Caller wires onClick / form post; kit owns admin-post Action envelope.
 */
export function FieldAction({ className = '', children, ...rest }) {
	return createElement(
		'div',
		{ ...rest, className: cx('field-ctl-row', className) },
		children
	);
}

Field.Hint = Hint;
Field.Opts = Opts;
Field.Opt = Opt;
Field.Secret = SecretInput;
Field.Email = EmailInput;
Field.Url = UrlInput;
Field.Action = FieldAction;
export default Field;
