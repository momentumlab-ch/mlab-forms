/* Echte WordPress-Oberfläche mit ausschliesslich fiktiven Demodaten. */
const { chromium } = require( 'playwright' );
const { readFileSync, mkdirSync } = require( 'node:fs' );
(async () => {
	const browser = await chromium.launch( { headless: true } );
	const context = await browser.newContext( { viewport: { width: 1600, height: 1100 }, deviceScaleFactor: 1 } );
	const page = await context.newPage();
	const demo = JSON.parse( readFileSync( '/tmp/mlab-demo.json', 'utf8' ) );
	const base = 'http://127.0.0.1:8090';
	const out = 'assets/screenshots';
	mkdirSync( out, { recursive: true } );
	await page.goto( base + '/wp-login.php' );
	await page.locator( '#user_login' ).fill( 'demo' );
	await page.locator( '#user_pass' ).fill( 'local-screenshots-only' );
	await Promise.all( [ page.waitForURL( '**/wp-admin/**' ), page.locator( '#wp-submit' ).click() ] );
	async function capture( url, filename ) {
		await page.goto( base + url );
		await page.locator( '.mlab-workspace' ).waitFor();
		await page.evaluate( () => document.fonts.ready );
		await page.locator( '.mlab-workspace' ).screenshot( { path: out + '/' + filename, animations: 'disabled' } );
	}
	const entries = '/wp-admin/edit.php?post_type=mlab_form&page=mlab-forms-entries';
	await capture( entries, 'anfragen.png' );
	await page.locator( 'tbody input[name="entry[]"]' ).first().check();
	if ( ! await page.locator( 'tbody input[name="entry[]"]' ).first().isChecked() ) { throw Error( 'Checkbox-Auswahl fehlgeschlagen' ); }
	await page.locator( '[data-mlab-preview]' ).first().click();
	await page.locator( 'dialog[open]' ).waitFor();
	await page.screenshot( { fullPage: true, path: out + '/anfragen-vorschau.png', animations: 'disabled' } );
	await page.keyboard.press( 'Escape' );
	await capture( entries + '&entry=' + demo.entry, 'anfrage-detail.png' );
	await capture( '/wp-admin/edit.php?post_type=mlab_form&page=mlab-forms-settings&tab=email', 'einstellungen-email.png' );
	await page.setViewportSize( { width: 390, height: 844 } );
	await capture( entries, 'anfragen-mobil.png' );
	await browser.close();
})();
