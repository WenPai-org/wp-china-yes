/**
 * SH-06 LoadError announcement (admin-ui-spec §8): speak() once per error.
 */

// @wordpress/a11y ships with WordPress core (wp-a11y) and is externalized by wp-scripts.
// eslint-disable-next-line import/no-extraneous-dependencies
import { speak } from '@wordpress/a11y';
import { useState } from '@wordpress/element';
import LoadError from './LoadError';
import { cleanup, click, render } from '../test-utils/render';

jest.mock( '@wordpress/a11y', () => ( { speak: jest.fn() } ) );
jest.mock( '@wordpress/components', () =>
	require( '../test-utils/components-mock' )
);

const TEXT = '暂时无法读取设置，请刷新页面重试。';

function Harness() {
	const [ errors, setErrors ] = useState( 0 );
	const [ , setTick ] = useState( 0 );
	return (
		<>
			<button id="fail" onClick={ () => setErrors( errors + 1 ) }>
				fail
			</button>
			<button id="tick" onClick={ () => setTick( ( t ) => t + 1 ) }>
				tick
			</button>
			{ Array.from( { length: errors } ).map( ( _, i ) => (
				<LoadError key={ i } what="设置" onRetry={ () => undefined } />
			) ) }
		</>
	);
}

afterEach( async () => {
	await cleanup();
	speak.mockClear();
} );

describe( 'LoadError announcement', () => {
	it( 'speaks nothing before an error, once when it appears', async () => {
		const { container } = await render( <Harness /> );
		expect( speak ).not.toHaveBeenCalled();
		await click( container.querySelector( '#fail' ) );
		expect( speak ).toHaveBeenCalledTimes( 1 );
		expect( speak ).toHaveBeenCalledWith( TEXT, 'polite' );
		// Re-render does not repeat it.
		await click( container.querySelector( '#tick' ) );
		expect( speak ).toHaveBeenCalledTimes( 1 );
		// A second error is announced once more.
		await click( container.querySelector( '#fail' ) );
		expect( speak ).toHaveBeenCalledTimes( 2 );
	} );
} );
