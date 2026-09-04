const { test, expect } = require( '@playwright/test' );
const { loginAsAdmin, openAdminPage } = require( './helpers' );

test.describe( 'connect', () => {
	test.beforeEach( async ( { page } ) => {
		await loginAsAdmin( page );
	} );

	test( 'E3: 头像改为 off 保存后刷新仍为 off', async ( { page } ) => {
		await openAdminPage( page, 'wpcy-connect' );
		await expect(
			page.getByRole( 'heading', { name: '连接优化', level: 1 } )
		).toBeVisible();

		await expect( page.getByText( 'WeAvatar' ) ).toBeVisible();

		const avatarGroup = () =>
			page
				.locator( '.components-radio-control' )
				.filter( { hasText: '头像' } );
		const off = () => avatarGroup().getByRole( 'radio', { name: '关闭' } );

		if ( await off().isChecked() ) {
			await avatarGroup()
				.getByRole( 'radio', { name: 'WeAvatar' } )
				.click();
			await page.getByRole( 'button', { name: '保存' } ).click();
			await expect( page.getByTestId( 'snackbar' ) ).toContainText(
				'已保存'
			);
			await page.reload( { waitUntil: 'domcontentloaded' } );
			await page
				.locator( '#wpcy-admin-root' )
				.getByRole( 'heading', { name: '连接优化', level: 1 } )
				.waitFor( { state: 'visible' } );
		}

		await off().click();
		await expect( page.getByRole( 'button', { name: '保存' } ) ).toBeEnabled();
		await page.getByRole( 'button', { name: '保存' } ).click();
		await expect( page.getByTestId( 'snackbar' ) ).toContainText( '已保存' );

		await page.reload( { waitUntil: 'domcontentloaded' } );
		await page
			.locator( '#wpcy-admin-root' )
			.getByRole( 'heading', { name: '连接优化', level: 1 } )
			.waitFor( { state: 'visible' } );
		await expect( off() ).toBeChecked();
	} );

	test( 'E10: Tab 遍历到保存按钮且焦点可见', async ( { page } ) => {
		await openAdminPage( page, 'wpcy-connect' );
		const save = page.getByRole( 'button', { name: '保存' } );
		await expect( save ).toBeVisible();

		const avatarGroup = page
			.locator( '.components-radio-control' )
			.filter( { hasText: '头像' } );
		const weavatar = avatarGroup.getByRole( 'radio', { name: 'WeAvatar' } );
		if ( ! ( await weavatar.isChecked() ) ) {
			await weavatar.click();
		}
		await expect( save ).toBeEnabled();

		const start = page
			.locator( '#wpcy-admin-root' )
			.getByRole( 'radio' )
			.first();
		await start.focus();
		let focusedSave = false;
		for ( let i = 0; i < 80; i++ ) {
			await page.keyboard.press( 'Tab' );
			focusedSave = await save.evaluate(
				( el ) => el === document.activeElement
			);
			if ( focusedSave ) {
				break;
			}
		}
		expect( focusedSave ).toBe( true );

		const outline = await save.evaluate( ( el ) => {
			const style = window.getComputedStyle( el );
			return {
				outlineStyle: style.outlineStyle,
				outlineWidth: style.outlineWidth,
				boxShadow: style.boxShadow,
			};
		} );
		const hasRing =
			( outline.outlineStyle &&
				outline.outlineStyle !== 'none' &&
				outline.outlineWidth !== '0px' ) ||
			( outline.boxShadow && outline.boxShadow !== 'none' );
		expect( hasRing ).toBe( true );
	} );
} );
