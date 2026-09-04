const { test, expect } = require( '@playwright/test' );
const { loginAsAdmin, wpEval } = require( './helpers' );

test.describe( 'recovery', () => {
	test( 'E7: 无 JS 关闭全部 URL 改写后绿 Notice', async ( {
		browser,
		browserName,
	} ) => {
		test.skip(
			browserName !== 'chromium',
			'javaScriptEnabled is asserted on chromium'
		);

		wpEval(
			'$r=new \\WenPai\\ChinaYes\\Config\\Repository(); $r->set("recovery_mode", false);'
		);

		const context = await browser.newContext( {
			javaScriptEnabled: false,
		} );
		const page = await context.newPage();
		try {
			await loginAsAdmin( page );
			await page.goto( '/wp-admin/admin.php?page=wpcy-recovery' );
			await expect(
				page.getByRole( 'heading', { name: '文派叶子 · 恢复模式' } )
			).toBeVisible();
			await page
				.getByRole( 'button', { name: '关闭全部 URL 改写' } )
				.click();
			await expect(
				page.locator( '.notice-success' ).getByText( '恢复模式已开启' )
			).toBeVisible();
		} finally {
			await context.close();
			wpEval(
				'$r=new \\WenPai\\ChinaYes\\Config\\Repository(); $r->set("recovery_mode", false);'
			);
		}
	} );
} );
