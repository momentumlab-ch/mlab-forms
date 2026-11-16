'use strict';
const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { readFileSync } = require( 'node:fs' );
const { resolve } = require( 'node:path' );
const { JSDOM } = require( 'jsdom' );
const script = readFileSync( resolve( __dirname, '../../assets/js/workspace.js' ), 'utf8' );
const css = readFileSync( resolve( __dirname, '../../assets/css/workspace.css' ), 'utf8' );
function fixture() {
	const dom = new JSDOM( `<!doctype html><style>
		input[type=checkbox]:checked{background:#2271b1;border-color:#2271b1}
		${ css }
		</style><div data-mlab-external-notices><hr class="wp-header-end"></div>
		<div class="mlab-workspace"><header><h1>Forms</h1><div id="foreign" class="notice">Other plugin</div></header>
		<div id="own" class="notice inline" data-mlab-notice>Saved</div>
		<form data-mlab-settings><label for="toggle">Aktiv</label><input id="toggle" name="enabled" type="checkbox" value="1">
		<div class="mlab-savebar"><span data-mlab-save-state></span></div></form></div>`,
		{ runScripts: 'outside-only', url: 'https://example.org/wp-admin/edit.php' } );
	dom.window.mlabFormsWorkspace = { saved: 'Saved', unsaved: 'Unsaved' };
	dom.window.eval( script );
	return dom;
}
test( 'foreign notices move above workspace while own notices remain inside', () => {
	const dom = fixture();
	const doc = dom.window.document;
	assert.equal( doc.querySelector( '#foreign' ).parentElement, doc.querySelector( '[data-mlab-external-notices]' ) );
	assert.equal( doc.querySelector( '.mlab-workspace' ).contains( doc.querySelector( '#own' ) ), true );
	dom.window.close();
} );
test( 'late inline foreign notices move intact, including dismiss handlers', async () => {
	const dom = fixture();
	const doc = dom.window.document;
	const notice = doc.createElement( 'div' );
	notice.className = 'notice inline';
	const button = doc.createElement( 'button' );
	button.addEventListener( 'click', () => notice.remove() );
	notice.appendChild( button );
	doc.querySelector( 'header' ).appendChild( notice );
	await new Promise( resolve => dom.window.setTimeout( resolve, 0 ) );
	assert.equal( notice.parentElement, doc.querySelector( '[data-mlab-external-notices]' ) );
	button.click();
	assert.equal( notice.isConnected, false );
	dom.window.close();
} );
test( 'checkbox and associated label toggle, serialize and update save state', () => {
	const dom = fixture();
	const doc = dom.window.document;
	const checkbox = doc.querySelector( '#toggle' );
	checkbox.click();
	assert.equal( checkbox.checked, true );
	assert.equal( new dom.window.FormData( checkbox.form ).get( 'enabled' ), '1' );
	assert.equal( doc.querySelector( '[data-mlab-save-state]' ).textContent, 'Unsaved' );
	assert.equal( dom.window.getComputedStyle( checkbox ).backgroundColor, 'rgb(34, 113, 177)' );
	doc.querySelector( 'label' ).click();
	assert.equal( checkbox.checked, false );
	assert.equal( new dom.window.FormData( checkbox.form ).has( 'enabled' ), false );
	assert.equal( doc.querySelector( '[data-mlab-save-state]' ).textContent, 'Saved' );
	dom.window.close();
} );
