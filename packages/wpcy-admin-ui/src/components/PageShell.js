import { createElement } from 'react';

/**
 * Page chrome dual-stack with kit Wenpai_Admin_Shell.
 * Root `.wpcy-app`; chrome `.wpcy-top|wrap|head|tabs|main|page-head`; footer `.foot`.
 *
 * @param {object} props
 * @param {string} [props.name] product display name
 * @param {import('react').ReactNode} [props.mark] brand mark (svg/node)
 * @param {string} [props.homeHref='#']
 * @param {Array<{id:string,label:string,href:string,icon?:import('react').ReactNode}>} [props.tabs]
 * @param {string} [props.current] active tab id
 * @param {import('react').ReactNode} [props.headRight] help/feedback actions
 * @param {import('react').ReactNode} [props.headExtra]
 * @param {string} [props.mainClassName]
 * @param {string} [props.pageTitle]
 * @param {string} [props.pageLede]
 * @param {import('react').ReactNode} [props.pageAside]
 * @param {import('react').ReactNode} [props.notices] top-of-main notices
 * @param {string} [props.version]
 * @param {Array<{href:string,label:string}>} [props.footerLinks]
 * @param {import('react').ReactNode} [props.children]
 * @param {string} [props.className] extra on root
 * @param {string} [props.product] data-wpcy-product (mirrors data-wenpai-product)
 * @param {boolean} [props.wizard] SHELL-12: use `.wizard` layout (no wrap/tabs typical)
 */
export function PageShell({
	name = '',
	mark = null,
	homeHref = '#',
	tabs = [],
	current = '',
	headRight = null,
	headExtra = null,
	mainClassName = '',
	pageTitle,
	pageLede,
	pageAside = null,
	notices = null,
	version = '',
	footerLinks = [],
	children,
	className = '',
	product = '',
	wizard = false,
	...rest
}) {
	const rootClass = ['wpcy-app', className].filter(Boolean).join(' ');
	const mainClass = [
		wizard ? 'wizard' : 'wpcy-main',
		wizard ? null : 'wpcy-wrap',
		mainClassName,
	].filter(Boolean).join(' ');

	const tabNodes =
		tabs && tabs.length
			? createElement(
					'nav',
					{ className: 'wpcy-tabs', 'aria-label': name || undefined },
					tabs.map((tab) =>
						createElement(
							'a',
							{
								key: tab.id,
								className: ['wpcy-tab', tab.id === current && 'is-active']
									.filter(Boolean)
									.join(' '),
								href: tab.href || '#',
							},
							tab.icon || null,
							tab.label
						)
					)
				)
			: null;

	const pageHead =
		pageTitle || pageLede || pageAside
			? createElement(
					'div',
					{ className: 'wpcy-page-head' },
					createElement(
						'div',
						null,
						pageTitle
							? createElement('h1', { className: 'wpcy-h1' }, pageTitle)
							: null,
						pageLede
							? createElement('p', { className: 'wpcy-lede' }, pageLede)
							: null
					),
					pageAside
						? createElement('div', { className: 'mode' }, pageAside)
						: null
				)
			: null;

	const footLinks =
		footerLinks && footerLinks.length
			? createElement(
					'span',
					{ className: 'r' },
					footerLinks.map((link) =>
						createElement(
							'a',
							{
								key: link.href + link.label,
								href: link.href,
								target: '_blank',
								rel: 'noopener noreferrer',
							},
							link.label
						)
					)
				)
			: null;

	return createElement(
		'div',
		{
			...rest,
			className: rootClass,
			...(product ? { 'data-wpcy-product': product } : {}),
		},
		createElement(
			'div',
			{ className: 'wpcy-top' },
			createElement(
				'div',
				{ className: 'wpcy-wrap' },
				createElement(
					'div',
					{ className: 'wpcy-head' },
					createElement(
						'a',
						{ className: 'wpcy-brand', href: homeHref },
						createElement('span', { className: 'wpcy-mark' }, mark),
						createElement('span', { className: 'wpcy-name' }, name)
					),
					tabNodes,
					headExtra,
					createElement('div', { className: 'wpcy-head-right' }, headRight)
				)
			)
		),
		createElement(
			'main',
			{ className: mainClass },
			notices
				? createElement('div', { className: 'wpcy-snackbar-slot' }, notices)
				: null,
			pageHead,
			children,
			createElement(
				'footer',
				{ className: 'foot' },
				version ? createElement('span', null, version) : null,
				footLinks
			)
		)
	);
}

export default PageShell;
