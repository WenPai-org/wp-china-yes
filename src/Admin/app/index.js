/**
 * Admin React app entry.
 */

import { createRoot, lazy, Suspense } from '@wordpress/element';
import { dispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { Spinner } from '@wordpress/components';
import { STORE_NAME } from './store';
import Commands from './commands';
import Overview from './pages/Overview';
import { getPageSlug, PAGES } from './routing';
import './style.css';

const Connect = lazy( () => import( './pages/Connect' ) );
const Services = lazy( () => import( './pages/Services' ) );
const Diagnose = lazy( () => import( './pages/Diagnose' ) );

/**
 * Configure api-fetch with the PHP bootstrap nonce and REST root.
 *
 * @param {Object} bootstrap Inline payload from AdminModule.
 */
function configureApiFetch( bootstrap ) {
	if ( bootstrap.nonce ) {
		apiFetch.use( apiFetch.createNonceMiddleware( bootstrap.nonce ) );
	}
	if ( bootstrap.restRoot ) {
		apiFetch.use( apiFetch.createRootURLMiddleware( bootstrap.restRoot ) );
	}
}

/**
 * Page for the current ?page= slug.
 *
 * @param {Object} props
 * @param {string} props.slug Menu slug.
 */
function PageForSlug( { slug } ) {
	if ( slug === PAGES.connect ) {
		return <Connect />;
	}
	if ( slug === PAGES.services ) {
		return <Services />;
	}
	if ( slug === PAGES.diagnose ) {
		return <Diagnose />;
	}
	return <Overview />;
}

/**
 * Root: command registration plus the current page.
 */
function App() {
	const slug = getPageSlug();
	return (
		<>
			<Commands />
			<Suspense fallback={ <Spinner /> }>
				<PageForSlug slug={ slug } />
			</Suspense>
		</>
	);
}

const rootEl = document.getElementById( 'wpcy-admin-root' );
if ( rootEl ) {
	const bootstrap = window.wpcyAdmin || {};
	configureApiFetch( bootstrap );
	dispatch( STORE_NAME ).hydrate( bootstrap );
	if ( bootstrap.capabilities?.manage_options ) {
		dispatch( STORE_NAME ).fetchDiagnostics();
	}
	createRoot( rootEl ).render( <App /> );
}
