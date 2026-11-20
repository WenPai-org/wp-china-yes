import { createElement, isValidElement } from 'react';

/**
 * True when a single element already carries the `.mode` class (avoid double-wrap).
 *
 * @param {import('react').ReactNode} node
 * @return {boolean}
 */
function hasModeClass( node ) {
	if ( ! isValidElement( node ) ) {
		return false;
	}
	const cls = node.props && node.props.className;
	if ( typeof cls !== 'string' ) {
		return false;
	}
	return cls.split( /\s+/ ).includes( 'mode' );
}

/**
 * Page chrome dual-stack with kit Wenpai_Admin_Shell.
 * Root `.wpcy-app`; chrome `.wpcy-top|wrap|head|tabs|main|page-head`; footer `.foot`.
 *
 * Main column order (default): pageHead → notices (snackbar) → children.
 * Pass `noticesBeforeHead` to restore notices→pageHead for rare callers.
 *
 * @param {object} props
 * @param {string} [props.name] product display name
 * @param {import('react').ReactNode} [props.mark] brand mark (svg/node)
 * @param {string} [props.homeHref='#']
 * @param {Array<{id:string,label:import('react').ReactNode,href:string,icon?:import('react').ReactNode}>} [props.tabs]
 * @param {string} [props.current] active tab id
 * @param {import('react').ReactNode} [props.headRight] help/feedback actions
 * @param {import('react').ReactNode} [props.headExtra]
 * @param {string} [props.mainClassName]
 * @param {import('react').ReactNode} [props.pageTitle]
 * @param {import('react').ReactNode} [props.pageLede]
 * @param {import('react').ReactNode} [props.pageAside] page-head actions; if already `.mode`, not re-wrapped
 * @param {import('react').ReactNode} [props.notices] snackbar / notices (in `.wpcy-snackbar-slot`)
 * @param {boolean} [props.noticesBeforeHead=false] when true, notices render above pageHead
 * @param {import('react').ReactNode} [props.version] footer left text (used when footerLeft omitted)
 * @param {import('react').ReactNode} [props.footerLeft] footer left slot (supersedes version when set)
 * @param {Array<{href:string,label:import('react').ReactNode}>} [props.footerLinks]
 * @param {import('react').ReactNode} [props.children]
 * @param {string} [props.className] extra on root
 * @param {string} [props.product] data-wpcy-product (mirrors data-wenpai-product)
 * @param {boolean} [props.wizard] SHELL-12: use `.wizard` layout (no wrap/tabs typical)
 */
export function PageShell( {
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
	noticesBeforeHead = false,
	version = '',
	footerLeft = null,
	footerLinks = [],
	children,
	className = '',
	product = '',
	wizard = false,
	...rest
} ) {
	const rootClass = [ 'wpcy-app', className ].filter( Boolean ).join( ' ' );
	const mainClass = [
		wizard ? 'wizard' : 'wpcy-main',
		wizard ? null : 'wpcy-wrap',
		mainClassName,
	]
		.filter( Boolean )
		.join( ' ' );

	const tabNodes =
		tabs && tabs.length
			? createElement(
					'nav',
					{ className: 'wpcy-tabs', 'aria-label': name || undefined },
					tabs.map( ( tab ) =>
						createElement(
							'a',
							{
								key: tab.id,
								className: [
									'wpcy-tab',
									tab.id === current && 'is-active',
								]
									.filter( Boolean )
									.join( ' ' ),
								href: tab.href || '#',
							},
							tab.icon || null,
							tab.label
						)
					)
			  )
			: null;

	const asideNode = pageAside
		? hasModeClass( pageAside )
			? pageAside
			: createElement( 'div', { className: 'mode' }, pageAside )
		: null;

	const pageHead =
		pageTitle || pageLede || asideNode
			? createElement(
					'div',
					{ className: 'wpcy-page-head' },
					createElement(
						'div',
						null,
						pageTitle
							? createElement(
									'h1',
									{ className: 'wpcy-h1' },
									pageTitle
							  )
							: null,
						pageLede
							? createElement(
									'p',
									{ className: 'wpcy-lede' },
									pageLede
							  )
							: null
					),
					asideNode
			  )
			: null;

	const noticesNode = notices
		? createElement(
				'div',
				{ className: 'wpcy-snackbar-slot' },
				notices
		  )
		: null;

	const footLinks =
		footerLinks && footerLinks.length
			? createElement(
					'span',
					{ className: 'r' },
					footerLinks.map( ( link, i ) =>
						createElement(
							'a',
							{
								key: link.href || `fl-${ i }`,
								href: link.href,
								target: '_blank',
								rel: 'noopener noreferrer',
							},
							link.label
						)
					)
			  )
			: null;

	const leftSlot =
		footerLeft != null
			? footerLeft
			: version
			? createElement( 'span', null, version )
			: null;

	const mainKids = noticesBeforeHead
		? [ noticesNode, pageHead, children ]
		: [ pageHead, noticesNode, children ];

	return createElement(
		'div',
		{
			...rest,
			className: rootClass,
			...( product ? { 'data-wpcy-product': product } : {} ),
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
						createElement( 'span', { className: 'wpcy-mark' }, mark ),
						createElement( 'span', { className: 'wpcy-name' }, name )
					),
					tabNodes,
					headExtra,
					createElement(
						'div',
						{ className: 'wpcy-head-right' },
						headRight
					)
				)
			)
		),
		createElement(
			'main',
			{ className: mainClass },
			...mainKids,
			createElement(
				'footer',
				{ className: 'foot' },
				leftSlot,
				footLinks
			)
		)
	);
}

export default PageShell;
