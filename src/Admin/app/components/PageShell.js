/**
 * Layout wrapper around @wordpress/admin-ui Page.
 *
 * Isolates admin-ui 2.x layout churn from page content.
 * Does not build a fullscreen shell or read admin-bar height.
 */

import { Page } from '@wordpress/admin-ui';

/**
 * @param {Object}                    props
 * @param {import('react').ReactNode} props.title
 * @param {import('react').ReactNode} [props.actions]
 * @param {import('react').ReactNode} props.children
 */
export default function PageShell( { title, actions, children } ) {
	return (
		<Page
			title={ title }
			actions={ actions }
			hasPadding={ false }
			showSidebarToggle={ false }
		>
			<div className="wpcy-page-body">{ children }</div>
		</Page>
	);
}
