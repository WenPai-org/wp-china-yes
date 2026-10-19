/**
 * admin-ui-spec §8: async empty states go into a persistent role="status".
 */

// act() is test-only; React ships with @wordpress/element.
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';
import { createRoot } from '@wordpress/element';
import { AppsSection } from './Services';
import Empty from '../ui/Empty';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );
jest.mock( '@wordpress/components', () => ( { Modal: () => null } ) );
jest.mock( '@wordpress/data', () => ( { useSelect: () => ( {} ) } ) );
jest.mock( '../components/PageShell', () => () => null );
jest.mock( '../store', () => ( { STORE_NAME: 'wpcy/test' } ) );

global.IS_REACT_ACT_ENVIRONMENT = true;

const SV10 = '小工具目录暂时不可用。';

let host;
let root;

function render( ui ) {
	act( () => root.render( ui ) );
}

beforeEach( () => {
	host = document.createElement( 'div' );
	document.body.appendChild( host );
	root = createRoot( host );
} );

afterEach( () => {
	act( () => root.unmount() );
	host.remove();
} );

function section( props ) {
	return (
		<AppsSection
			bound
			appsUnavailable={ false }
			apps={ [] }
			onOpen={ () => {} }
			{ ...props }
		/>
	);
}

describe( 'AppsSection SV-10 live region', () => {
	test( 'status container exists and is empty before the failure', () => {
		render( section() );
		const status = host.querySelector( '[role="status"]' );
		expect( status ).not.toBeNull();
		expect( status.textContent ).toBe( '' );
		expect( host.textContent ).not.toContain( SV10 );
	} );

	test( 'after the failure the same container carries the SV-10 copy once', () => {
		render( section() );
		const before = host.querySelector( '[role="status"]' );
		render( section( { appsUnavailable: true } ) );
		const statuses = host.querySelectorAll( '[role="status"]' );
		expect( statuses ).toHaveLength( 1 );
		expect( statuses[ 0 ] ).toBe( before );
		expect( statuses[ 0 ].textContent ).toContain( SV10 );
		expect( host.textContent.split( SV10 ) ).toHaveLength( 2 );
	} );

	test( 'recovery clears the container without remounting it', () => {
		render( section( { appsUnavailable: true } ) );
		const before = host.querySelector( '[role="status"]' );
		render( section() );
		const status = host.querySelector( '[role="status"]' );
		expect( status ).toBe( before );
		expect( status.textContent ).toBe( '' );
	} );

	test( 'SV-01 unbound empty state carries no role', () => {
		render( section( { bound: false, appsUnavailable: true } ) );
		const empty = host.querySelector( '[data-testid="wpcy-apps-empty"]' );
		expect( empty ).not.toBeNull();
		expect( empty.closest( '[role]' ) ).toBeNull();
		expect( host.querySelector( '[role="status"]' ).textContent ).toBe(
			''
		);
		expect( host.textContent ).not.toContain( SV10 );
	} );
} );

describe( 'Empty role prop', () => {
	test( 'no role by default', () => {
		render( <Empty why="x" /> );
		expect( host.querySelector( '.empty' ).hasAttribute( 'role' ) ).toBe(
			false
		);
	} );

	test( 'role is forwarded when set', () => {
		render( <Empty why="x" role="status" /> );
		expect( host.querySelector( '.empty' ).getAttribute( 'role' ) ).toBe(
			'status'
		);
	} );
} );
