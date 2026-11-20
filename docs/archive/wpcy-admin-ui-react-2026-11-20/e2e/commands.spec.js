const { test, expect } = require( '@playwright/test' );
const { loginAsAdmin, openAdminPage } = require( './helpers' );

test.describe( 'commands', () => {
	test( 'E8: 命令面板打开连接优化', async ( { page } ) => {
		await loginAsAdmin( page );
		await openAdminPage( page, 'wpcy' );

		const registered = await page.evaluate( () => {
			const commands =
				window.wp?.data?.select( 'core/commands' )?.getCommands?.() ||
				[];
			return commands
				.filter( ( command ) => command && command.label )
				.map( ( command ) => command.label );
		} );
		expect( registered ).toContain( '打开连接优化' );

		const bar = page.locator( '#wp-admin-bar-command-palette a' );
		await expect( bar ).toBeVisible();
		await bar.click();

		const search = page.getByPlaceholder( /Search commands|搜索命令/i );
		if ( await search.isVisible().catch( () => false ) ) {
			await search.fill( '打开连接优化' );
			await page.getByText( '打开连接优化', { exact: true } ).click();
		} else {
			await page.evaluate( () => {
				const commands =
					window.wp?.data?.select( 'core/commands' )?.getCommands?.() ||
					[];
				const match = commands.find(
					( command ) => command.label === '打开连接优化'
				);
				if ( match && typeof match.callback === 'function' ) {
					match.callback( { close() {} } );
				}
			} );
		}

		await expect( page ).toHaveURL( /page=wpcy-connect/ );
	} );
} );
