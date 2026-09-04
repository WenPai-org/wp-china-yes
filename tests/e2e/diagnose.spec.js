const { test, expect } = require( '@playwright/test' );
const { loginAsAdmin, openAdminPage } = require( './helpers' );

test.describe( 'diagnose', () => {
	test.beforeEach( async ( { page } ) => {
		await loginAsAdmin( page );
	} );

	test( 'E5: 立即检查后 DataViews 至少一行目标', async ( { page } ) => {
		await openAdminPage( page, 'wpcy-diagnose' );
		await page.getByRole( 'button', { name: '立即检查' } ).click();
		await expect( page.getByText( 'api.wenpai.net' ) ).toBeVisible( {
			timeout: 90000,
		} );
		expect(
			await page.locator( '.dataviews-view-table tbody tr' ).count()
		).toBeGreaterThan( 0 );
	} );

	test( 'E6: 进入恢复模式到达 wpcy-recovery', async ( { page } ) => {
		await openAdminPage( page, 'wpcy-diagnose' );
		await page.getByRole( 'tab', { name: '数据与恢复' } ).click();
		await page.getByRole( 'link', { name: '进入恢复模式' } ).click();
		await expect( page ).toHaveURL( /page=wpcy-recovery/ );
		await expect(
			page.getByRole( 'heading', { name: '文派叶子 · 恢复模式' } )
		).toBeVisible();
	} );

	test( 'hash tab switch does not change ?page=', async ( { page } ) => {
		await openAdminPage( page, 'wpcy-diagnose' );
		const before = new URL( page.url() );
		expect( before.searchParams.get( 'page' ) ).toBe( 'wpcy-diagnose' );

		await page.getByRole( 'tab', { name: '被隐藏的通知' } ).click();
		await expect( page ).toHaveURL( /#\/tab=notices/ );
		const after = new URL( page.url() );
		expect( after.searchParams.get( 'page' ) ).toBe( 'wpcy-diagnose' );
		expect( after.pathname ).toBe( before.pathname );
	} );
} );
