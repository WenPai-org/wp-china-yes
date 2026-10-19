const { test, expect } = require( '@playwright/test' );
const { loginAsAdmin, openAdminPage } = require( './helpers' );

test.describe( 'connect', () => {
	test.beforeEach( async ( { page } ) => {
		await loginAsAdmin( page );
	} );

	test( '四张场景卡与后台语言开关', async ( { page } ) => {
		await openAdminPage( page, 'wpcy-connect' );
		await expect(
			page.getByRole( 'heading', { name: '设置', level: 1 } )
		).toBeVisible();
		await expect( page.getByText( '国内站', { exact: true } ) ).toBeVisible();
		await expect( page.getByText( '跨境 · 外贸站' ) ).toBeVisible();
		await expect( page.getByText( '内贸 · 进中国站' ) ).toBeVisible();
		await expect( page.getByText( '混合站' ) ).toBeVisible();
		await expect( page.getByText( '后台语言跟随管理员' ) ).toBeVisible();
		await expect( page.getByText( 'WeAvatar' ) ).toHaveCount( 0 );
		await expect( page.getByRole( 'button', { name: '保存' } ) ).toHaveCount(
			0
		);
	} );

	test( '高级模式单一头像字段', async ( { page } ) => {
		await openAdminPage( page, 'wpcy-connect' );
		await page.getByRole( 'button', { name: '高级' } ).click();
		await expect( page.getByText( '头像', { exact: true } ).first() ).toBeVisible();
		await expect( page.getByText( '前台头像' ) ).toHaveCount( 0 );
		await expect( page.getByText( '后台头像' ) ).toHaveCount( 0 );
		await expect( page.getByText( '后台加速' ) ).toHaveCount( 0 );
		await expect( page.getByText( '连通性' ) ).toBeVisible();
	} );
} );
