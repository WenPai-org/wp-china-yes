import { createElement } from 'react';

/**
 * In-card section title — `.section-title` / `.section-desc` (CARD-12).
 */
export function SectionTitle({
	icon = null,
	trailing = null,
	desc = null,
	as = 'h3',
	className = '',
	children,
}) {
	return createElement(
		'div',
		null,
		createElement(
			as,
			{ className: ['section-title', className].filter(Boolean).join(' ') },
			icon,
			children,
			trailing
		),
		desc != null ? createElement('p', { className: 'section-desc' }, desc) : null
	);
}

export default SectionTitle;
