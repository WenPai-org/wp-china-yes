/**
 * SH-06 LoadError announcement (admin-ui-spec §8): one region speaks its own
 * sentence once; two or more failing in one load round speak one combined
 * sentence once. Every region still shows its notice and 重试.
 */

// @wordpress/a11y ships with WordPress core (wp-a11y) and is externalized by wp-scripts.
// eslint-disable-next-line import/no-extraneous-dependencies
import { speak } from '@wordpress/a11y';
import { useState } from '@wordpress/element';
import LoadError, { ANNOUNCE_WINDOW_MS } from './LoadError';
import { act, cleanup, click, render } from '../test-utils/render';

jest.mock( '@wordpress/a11y', () => ( { speak: jest.fn() } ) );
jest.mock( '@wordpress/components', () =>
	require( '../test-utils/components-mock' )
);

const TEXT = '暂时无法读取设置，请刷新页面重试。';
const REGIONS = [ '设置', '诊断', '统计' ];

function Harness( { initial = 0 } ) {
	const [ errors, setErrors ] = useState( initial );
	const [ , setTick ] = useState( 0 );
	return (
		<>
			<button id="fail" onClick={ () => setErrors( errors + 1 ) }>
				fail
			</button>
			<button id="ok" onClick={ () => setErrors( 0 ) }>
				ok
			</button>
			<button id="tick" onClick={ () => setTick( ( t ) => t + 1 ) }>
				tick
			</button>
			{ Array.from( { length: errors } ).map( ( _, i ) => (
				<LoadError
					key={ i }
					what={ REGIONS[ i % REGIONS.length ] }
					onRetry={ () => undefined }
				/>
			) ) }
		</>
	);
}

async function elapse() {
	await act( async () => {
		jest.advanceTimersByTime( ANNOUNCE_WINDOW_MS );
	} );
}

beforeEach( () => {
	jest.useFakeTimers();
} );

afterEach( async () => {
	await cleanup();
	await elapse();
	jest.useRealTimers();
	speak.mockClear();
} );

describe( 'LoadError announcement', () => {
	it( 'one failure speaks its own sentence once', async () => {
		const { container } = await render( <Harness /> );
		expect( speak ).not.toHaveBeenCalled();
		await click( container.querySelector( '#fail' ) );
		await elapse();
		expect( speak ).toHaveBeenCalledTimes( 1 );
		expect( speak ).toHaveBeenCalledWith( TEXT, 'polite' );
		// Re-render does not repeat it.
		await click( container.querySelector( '#tick' ) );
		await elapse();
		expect( speak ).toHaveBeenCalledTimes( 1 );
	} );

	it.each( [ 2, 3 ] )(
		'%d failures in one round speak one combined sentence',
		async ( n ) => {
			const { container } = await render( <Harness /> );
			for ( let i = 0; i < n; i++ ) {
				await click( container.querySelector( '#fail' ) );
			}
			await elapse();
			expect( speak ).toHaveBeenCalledTimes( 1 );
			expect( speak ).toHaveBeenCalledWith(
				'有 ' + n + ' 项内容暂时无法读取，请刷新页面重试。',
				'polite'
			);
			// Each region keeps its own notice and 重试.
			const notices = container.querySelectorAll( '.notice' );
			expect( notices ).toHaveLength( n );
			REGIONS.slice( 0, n ).forEach( ( what, i ) => {
				expect( notices[ i ].textContent ).toContain(
					'暂时无法读取' + what + '，请刷新页面重试。'
				);
				expect( notices[ i ].textContent ).toContain( '重试' );
			} );
		}
	);

	it( 'regions mounted together in one commit are one round', async () => {
		await render( <Harness initial={ 2 } /> );
		await elapse();
		expect( speak ).toHaveBeenCalledTimes( 1 );
		expect( speak ).toHaveBeenCalledWith(
			'有 2 项内容暂时无法读取，请刷新页面重试。',
			'polite'
		);
	} );

	it( 'a retry that fails again is a new round, old sentence not repeated', async () => {
		const { container } = await render( <Harness /> );
		await click( container.querySelector( '#fail' ) );
		await click( container.querySelector( '#fail' ) );
		await elapse();
		expect( speak ).toHaveBeenCalledTimes( 1 );
		await click( container.querySelector( '#ok' ) );
		await click( container.querySelector( '#fail' ) );
		await elapse();
		expect( speak ).toHaveBeenCalledTimes( 2 );
		expect( speak ).toHaveBeenLastCalledWith( TEXT, 'polite' );
	} );

	it( 'a region that recovers inside the window is not counted', async () => {
		const { container } = await render( <Harness /> );
		await click( container.querySelector( '#fail' ) );
		await click( container.querySelector( '#ok' ) );
		await elapse();
		expect( speak ).not.toHaveBeenCalled();
	} );
} );
