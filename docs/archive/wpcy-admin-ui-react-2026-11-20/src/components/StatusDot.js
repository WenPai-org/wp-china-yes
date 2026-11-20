import { createElement } from 'react';

const TONES = new Set(['success', 'warning', 'danger', 'neutral']);

function cx(...parts) {
	return parts.filter(Boolean).join(' ');
}

/**
 * Status as a dot plus text (CTRL-16). Color is never used alone.
 *
 * @param {object} props
 * @param {'success'|'warning'|'danger'|'neutral'} props.tone
 * @param {import('react').ReactNode} props.label Visible status text.
 * @param {string} [props.className]
 */
export function StatusDot({ tone = 'neutral', label, className = '', ...rest }) {
	const t = TONES.has(tone) ? tone : 'neutral';
	return createElement(
		'span',
		{ ...rest, className: cx('wpcy-status', className) },
		createElement('span', {
			className: cx('wpcy-status-dot', `is-${t}`),
			'aria-hidden': 'true',
		}),
		label
	);
}

export default StatusDot;
