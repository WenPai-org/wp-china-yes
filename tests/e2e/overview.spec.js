const { test, expect } = require( '@playwright/test' );
const { loginAsAdmin, openAdminPage, wpEval } = require( './helpers' );

test.describe( 'overview', () => {
	test.beforeEach( async ( { page } ) => {
		await loginAsAdmin( page );
	} );

	test( 'E1: 概览标题与三卡区域', async ( { page } ) => {
		await openAdminPage( page, 'wpcy' );
		await expect(
			page.locator( '#wpcy-admin-root' ).getByRole( 'heading', {
				name: '概览',
				level: 1,
			} )
		).toBeVisible();
		await expect( page.locator( '.wpcy-card-row' ) ).toBeVisible();
		await expect( page.getByText( 'WordPress.org 源' ) ).toBeVisible();
		await expect( page.getByText( '公共库与头像' ) ).toBeVisible();
		await expect(
			page.locator( '.wpcy-card-row' ).getByText( '文派服务', { exact: true } )
		).toBeVisible();
	} );

	test( 'E2: 概览点立即检查不报 JS 错', async ( { page } ) => {
		const pageErrors = [];
		page.on( 'pageerror', ( error ) => {
			pageErrors.push( String( error ) );
		} );

		wpEval(
			'delete_transient("wpcy_diagnostics_results");'
		);

		await openAdminPage( page, 'wpcy' );
		await expect( page.getByText( '尚未检查' ).first() ).toBeVisible();

		await page.getByRole( 'button', { name: '立即检查' } ).click();
		await expect( page.getByText( '尚未检查' ) ).toHaveCount( 0, {
			timeout: 90000,
		} );
		await expect( page.locator( '.wpcy-card-row' ) ).toBeVisible();
		expect( pageErrors, pageErrors.join( '\n' ) ).toEqual( [] );
	} );

	test( 'recovery banner when recovery_mode is on', async ( { page } ) => {
		wpEval(
			'$r=new \\WenPai\\ChinaYes\\Config\\Repository(); $r->set("recovery_mode", true);'
		);
		try {
			await openAdminPage( page, 'wpcy' );
			await expect(
				page.getByText( '恢复模式已开启：全部 URL 改写与模块已停用。' )
			).toBeVisible();
			await expect(
				page.getByRole( 'button', { name: '退出恢复模式' } )
			).toBeVisible();
		} finally {
			wpEval(
				'$r=new \\WenPai\\ChinaYes\\Config\\Repository(); $r->set("recovery_mode", false);'
			);
		}
	} );
} );
