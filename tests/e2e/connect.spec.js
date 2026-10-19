const { test, expect } = require( '@playwright/test' );
const {
	isRestRoute,
	loginAsAdmin,
	openAdminPage,
	restMethod,
} = require( './helpers' );

const SETTINGS = '/wpcy/v1/settings';

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

	test( 'CO-01 保存失败：开关回滚，卡顶红色提示与重试', async ( { page } ) => {
		await page.route(
			( url ) => String( url ).includes( 'wpcy' ),
			async ( route ) => {
				const req = route.request();
				if ( isRestRoute( req, SETTINGS ) && restMethod( req ) === 'PUT' ) {
					await route.fulfill( {
						status: 500,
						contentType: 'application/json',
						body: JSON.stringify( {
							code: 'wpcy_e2e_down',
							message: 'down',
							data: { status: 500 },
						} ),
					} );
					return;
				}
				await route.fallback();
			}
		);
		await openAdminPage( page, 'wpcy-connect' );
		const card = page.locator( '[data-card="admin"]' );
		const toggle = card.getByRole( 'switch' ).first();
		const before = await toggle.getAttribute( 'aria-checked' );
		await toggle.click();
		const alert = card.getByRole( 'alert' );
		await expect( alert ).toContainText( '暂时无法保存设置，请稍后重试。' );
		await expect( alert.getByRole( 'button', { name: '重试' } ) ).toBeVisible();
		await expect( toggle ).toHaveAttribute( 'aria-checked', before );
		await expect( page.locator( '.components-snackbar' ) ).toHaveCount( 0 );
	} );

	test( 'CO-11 切换场景先确认，取消不写入', async ( { page } ) => {
		const writes = [];
		page.on( 'request', ( req ) => {
			if ( isRestRoute( req, SETTINGS ) && restMethod( req ) === 'PUT' ) {
				writes.push( req.url() );
			}
		} );
		await openAdminPage( page, 'wpcy-connect' );
		const scene = page.locator( '[data-card="scene"]' );
		const other = scene
			.locator( 'label.choice' )
			.filter( { has: page.locator( 'input:not(:checked)' ) } )
			.first();
		await other.click();
		const dialog = page.getByRole( 'dialog' );
		await expect( dialog ).toBeVisible();
		await expect( dialog ).toContainText( '切换到「' );
		await dialog.getByRole( 'button', { name: '取消' } ).click();
		await expect( dialog ).toHaveCount( 0 );
		expect( writes ).toHaveLength( 0 );
	} );

	test( 'L-10 / L-11 lede 与向导入口', async ( { page } ) => {
		await openAdminPage( page, 'wpcy-connect' );
		await expect( page.locator( '#wpcy-admin-root' ) ).toContainText(
			'改动即时生效，单项仍可调整'
		);
		await expect(
			page.getByRole( 'link', { name: '跑一次 30 秒设置向导' } )
		).toHaveAttribute( 'href', /page=wpcy-onboarding/ );
	} );
} );
