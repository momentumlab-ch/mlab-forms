/* MomentumLab Forms: «Verbindung testen» auf der Einstellungsseite. */
( function () {
	'use strict';

	var config = window.mlabFormsAdmin || {};

	function show( result, ok, text ) {
		result.textContent = text;
		result.style.color = ok ? '#1a7f37' : '#b32d2e';
	}

	function test( button ) {
		var result = button.parentNode.querySelector( '.mlab-test-result' );
		var body = new window.URLSearchParams( {
			action: config.action,
			nonce: config.nonce,
			service: button.getAttribute( 'data-mlab-test' )
		} );

		button.disabled = true;
		show( result, true, config.working );
		window.fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( data ) {
				var answer = data && data.data ? data.data : {};
				show( result, !! answer.ok, answer.message || config.failed );
			} )
			.catch( function () {
				show( result, false, config.failed );
			} )
			.then( function () {
				button.disabled = false;
			} );
	}

	document.querySelectorAll( '[data-mlab-test]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			test( button );
		} );
	} );
}() );
