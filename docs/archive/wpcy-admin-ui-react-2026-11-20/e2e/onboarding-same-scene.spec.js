const { test, expect } = require( '@playwright/test' );
const {
	isRestRoute,
	loginAsAdmin,
	openAdminPage,
	restMethod,
	wpEval,
} = require( './helpers' );

/**
 * M-UI-2 O1 · WZ-04 same scene (AC B1–B3).
 *
 * The settings PUT goes to the real server so the DocumentWriter no-op is
 * what gets verified; only the binding flow is answered by page.route.
 * profile_confirmed_at is put back afterwards so a Studio site keeps its
 * baseline.
 */

const SETTINGS = '/wpcy/v1/settings';
const WRITE_METHODS = [ 'PUT', 'POST', 'PATCH', 'DELETE' ];

/**
 * Run PHP and return the JSON printed after the WPCY_JSON: marker (WP-CLI
 * wrappers may print banners around it).
 *
 * @param {string} php PHP that echoes 'WPCY_JSON:' . json_encode( … ).
 * @return {*} Decoded value.
 */
function wpJson( php ) {
	const out = String( wpEval( php ) );
	const match = out.match( /WPCY_JSON:(.*)$/m );
	if ( ! match ) {
		throw new Error( 'No WPCY_JSON marker in wp eval output: ' + out );
	}
	return JSON.parse( match[ 1 ] );
}

/**
 * Stored profile state and the ids of logged profile_set events.
 *
 * @return {{profile:string,stamp:(string|null),ids:string[]}} State.
 */
function profileState() {
	return wpJson(
		'$s = get_option( "wpcy_settings" );' +
			'$e = get_option( "wpcy_events", array() );' +
			'$ids = array();' +
			'foreach ( ( is_array( $e ) && isset( $e["events"] ) ? $e["events"] : array() ) as $row ) {' +
			' if ( isset( $row["type"], $row["id"] ) && "profile_set" === $row["type"] ) { $ids[] = $row["id"]; }' +
			'}' +
			'echo "WPCY_JSON:" . wp_json_encode( array(' +
			' "profile" => is_array( $s ) && ! empty( $s["profile"] ) ? $s["profile"] : "domestic",' +
			' "stamp" => is_array( $s ) && isset( $s["profile_confirmed_at"] ) ? $s["profile_confirmed_at"] : null,' +
			' "ids" => $ids,' +
			') );'
	);
}

/**
 * Put profile_confirmed_at back to what it was before the test.
 *
 * @param {string|null} stamp Original value.
 */
function restoreStamp( stamp ) {
	wpEval(
		'$r = new \\WenPai\\ChinaYes\\Config\\Repository();' +
			'$r->set( "profile_confirmed_at", ' +
			( stamp === null ? 'null' : JSON.stringify( stamp ) ) +
			' );'
	);
}

/**
 * GET /wpcy/v1/settings through the page's own apiFetch (nonce included).
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<Object>} Settings document.
 */
function readSettings( page ) {
	return page.evaluate( () =>
		window.wp.apiFetch( { path: '/wpcy/v1/settings' } )
	);
}

/**
 * Settings document without the step-1 timestamp, serialised as returned.
 *
 * @param {Object} doc Settings document.
 * @return {string} JSON.
 */
function withoutStamp( doc ) {
	const copy = { ...doc };
	delete copy.profile_confirmed_at;
	return JSON.stringify( copy );
}

/**
 * Keep binding unbound and log every settings write.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<{writes:string[]}>} Write log.
 */
async function watchWizard( page ) {
	const seen = { writes: [] };
	page.on( 'request', ( req ) => {
		const method = restMethod( req );
		if ( isRestRoute( req, SETTINGS ) && WRITE_METHODS.includes( method ) ) {
			seen.writes.push( method + ' ' + ( req.postData() || '' ) );
		}
	} );
	await page.route(
		( url ) => String( url ).includes( 'wpcy' ),
		async ( route ) => {
			const req = route.request();
			if ( isRestRoute( req, '/wpcy/v1/binding' ) ) {
				return route.fulfill( {
					status: 200,
					contentType: 'application/json',
					body: JSON.stringify( { status: 'unbound', site_hash: null } ),
				} );
			}
			return route.fallback();
		}
	);
	return seen;
}

test.describe( 'onboarding WZ-04 same scene (O1 B1–B3)', () => {
	let original;

	test.beforeEach( async ( { page } ) => {
		original = profileState();
		await loginAsAdmin( page );
	} );

	test.afterEach( () => {
		if ( original ) {
			restoreStamp( original.stamp );
		}
	} );

	test( 'B1–B3 选当前场景：只有第 1 步一次写入，文档不变，0 项且第 5 步无「更新」卡，profile_set 不变', async ( {
		page,
	} ) => {
		const seen = await watchWizard( page );
		await openAdminPage( page, 'wpcy-onboarding' );
		const before = await readSettings( page );
		const scene = before.profile || original.profile;

		// Step 1: choose the scene the site already has.
		await page.locator( `label[for="ob-p-${ scene }"]` ).click();
		await expect( page.locator( `#ob-p-${ scene }` ) ).toBeChecked();
		await page.getByRole( 'button', { name: '继续' } ).click();
		await expect(
			page.getByRole( 'heading', { name: /已按「.+」配置好/ } )
		).toBeVisible();

		// B1: the step-1 PUT {profile} is the only settings write …
		expect( seen.writes ).toHaveLength( 1 );
		expect( seen.writes[ 0 ] ).toMatch( /^(PUT|POST) / );
		expect( JSON.parse( seen.writes[ 0 ].replace( /^\S+ /, '' ) ) ).toEqual( {
			profile: scene,
		} );

		await page.getByRole( 'button', { name: '继续' } ).click();
		await page.getByRole( 'button', { name: '继续' } ).click();
		await expect(
			page.getByRole( 'heading', { name: '要绑定本站吗？' } )
		).toBeVisible();
		await page.getByRole( 'button', { name: '暂不，稍后再说' } ).click();
		await expect(
			page.getByRole( 'heading', { name: '一切就绪' } )
		).toBeVisible();

		// B2 / D-06: 已调整 — 0 项 — 当前已是「{场景}」，没有改动。（同场景单行说明）
		const count = page.locator( '[data-testid="wpcy-wz-count"]' );
		await expect( count ).toHaveText( '0 项' );
		const same = page.locator( '[data-testid="wpcy-wz-same"]' );
		const sameText = await same.innerText();
		const label = sameText.match( /当前已是「(.+)」，没有改动。/ );
		expect( label ).not.toBeNull();
		await expect( same ).toHaveText(
			`当前已是「${ label[ 1 ] }」，没有改动。`
		);
		// No 「更新」 summary card; two-card grid fills content (no empty third col).
		const step5 = page.locator( '.wizard-done .wpcy-grid-2' );
		await expect( step5 ).toHaveCount( 1 );
		await expect( step5 ).not.toContainText( '更新' );
		await expect( step5.locator( 'article' ) ).toHaveCount( 2 );

		// … and leaving the wizard does not write either.
		await page.getByRole( 'link', { name: '进入概览' } ).click();
		await page.waitForURL( /page=wpcy(&|$)/ );
		expect( seen.writes ).toHaveLength( 1 );

		// B1: server answered unchanged — document identical apart from the
		// step-1 timestamp.
		const after = await readSettings( page );
		expect( withoutStamp( after ) ).toBe( withoutStamp( before ) );

		// B3: profile_confirmed_at written at step 1; profile_set only on the
		// first confirmation (profile unchanged).
		const state = profileState();
		expect( typeof state.stamp ).toBe( 'string' );
		expect( state.stamp ).not.toBe( '' );
		expect( after.profile_confirmed_at ).toBe( state.stamp );
		const added = state.ids.filter( ( id ) => ! original.ids.includes( id ) );
		const firstConfirm =
			typeof original.stamp !== 'string' || original.stamp === '';
		expect( added ).toHaveLength( firstConfirm ? 1 : 0 );
		expect( state.profile ).toBe( original.profile );
	} );
} );
