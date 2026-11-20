import { createElement } from 'react';
import { Seg, SegItem } from './Seg.js';

function cx(...parts) {
	return parts.filter(Boolean).join(' ');
}

/**
 * In-tab secondary navigation — mirrors kit `Wenpai_Admin_UI::subnav()` (D-24 / Track A).
 * Visual: `nav.wpcy-subnav` > `.seg` chain. Pass `active` only; SegItem sets `.on` + `aria-current="page"`.
 * Not a third component family: composes existing Seg / SegItem. No Chart.js.
 *
 * Shell top tabs stay ≤4; conditional modules (e.g. 7) use this under the active tab.
 * Real hrefs with `?section=` (caller builds via router / Menu::url equivalent). No hash SPA.
 *
 * @param {object} props
 * @param {Array<{id:string,label:import('react').ReactNode,href:string}>} props.items
 * @param {string} [props.current] active section id
 * @param {string} [props.label='二级导航'] nav aria-label; empty string omits
 * @param {string} [props.className]
 * @param {import('react').ReactNode} [props.children] optional extra inside nav (rare)
 */
export function Subnav({
	items = [],
	current = '',
	label = '二级导航',
	className = '',
	children = null,
	...rest
}) {
	const navProps = {
		...rest,
		className: cx('wpcy-subnav', className),
	};
	if (label != null && label !== '') {
		navProps['aria-label'] = label;
	}

	const links = (items || []).map((item) => {
		if (!item || item.href == null || item.href === '') {
			return null;
		}
		const active = Boolean(item.current) || (current !== '' && item.id === current);
		const linkProps = {
			key: item.id || item.href,
			href: item.href,
			active,
			className: item.className,
		};
		// aria-current=page: SegItem auto-applies when active+href (single path; avoid dual-write).
		return createElement(SegItem, linkProps, item.label);
	});

	return createElement(
		'nav',
		navProps,
		createElement(Seg, null, links),
		children
	);
}

export default Subnav;
