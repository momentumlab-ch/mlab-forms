/* MomentumLab Forms: progressive Vorschau, Versandvorschau und Speicherschutz. */
( function () {
	'use strict';
	var root = document.querySelector( '.mlab-workspace' );
	if ( ! root ) { return; }
	var config = window.mlabFormsWorkspace || {};
	var dialog = root.querySelector( '.mlab-drawer' );
	var trigger;
	if ( dialog && typeof dialog.showModal === 'function' ) {
		root.addEventListener( 'click', function ( event ) {
			var link = event.target.closest( '[data-mlab-preview]' );
			if ( ! link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey ) { return; }
			var template = document.getElementById( link.dataset.mlabPreview );
			if ( ! template ) { return; }
			event.preventDefault();
			trigger = link;
			dialog.querySelector( '[data-mlab-preview-content]' ).replaceChildren( template.content.cloneNode( true ) );
			dialog.showModal();
		} );
		dialog.querySelector( '[data-mlab-close]' ).addEventListener( 'click', function () { dialog.close(); } );
		dialog.addEventListener( 'close', function () { if ( trigger ) { trigger.focus(); } } );
		dialog.addEventListener( 'click', function ( event ) { if ( event.target === dialog && event.clientX < dialog.getBoundingClientRect().left ) { dialog.close(); } } );
	}
	root.querySelectorAll( 'form[id^="mlab-resend-"]' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function () {
			root.querySelectorAll( '[form="' + form.id + '"]' ).forEach( function ( button ) { button.disabled = true; } );
		} );
	} );
	var settings = root.querySelector( '[data-mlab-settings]' );
	if ( ! settings ) { return; }
	var dirty = false;
	var initial = new URLSearchParams( new FormData( settings ) ).toString();
	var mailButton = settings.querySelector( '[data-mlab-mail-test]' );
	function field( key ) { return settings.querySelector( '#mlab_forms_' + key ); }
	function value( key ) { var input = field( key ); return input ? input.value.trim() : ''; }
	function text( selector, content ) { var element = settings.querySelector( selector ); if ( element ) { element.textContent = content; } }
	function update() {
		dirty = new URLSearchParams( new FormData( settings ) ).toString() !== initial;
		text( '[data-mlab-save-state]', dirty ? config.unsaved : config.saved );
		settings.querySelector( '.mlab-savebar' ).classList.toggle( 'is-dirty', dirty );
		if ( mailButton ) {
			mailButton.disabled = dirty;
			var to = settings.querySelector( '[data-mlab-mail-to]' );
			text( '[data-mlab-mail-to]', value( 'default_recipient' ) || to.dataset.fallback );
			text( '[data-mlab-mail-from]', [ value( 'email_from_name' ), value( 'email_from_address' ) ].filter( Boolean ).join( ' · ' ) || config.wordpress );
			text( '[data-mlab-mail-reply]', value( 'email_reply_to_mode' ) === 'visitor' ? config.visitor : value( 'email_reply_to' ) || '–' );
			var fixed = field( 'email_reply_to' );
			fixed.closest( '.mlab-setting-field' ).hidden = value( 'email_reply_to_mode' ) !== 'fixed';
		}
	}
	settings.addEventListener( 'input', update );
	settings.addEventListener( 'change', update );
	settings.addEventListener( 'submit', function () { dirty = false; } );
	window.addEventListener( 'beforeunload', function ( event ) { if ( dirty ) { event.preventDefault(); event.returnValue = ''; } } );
	update();
	if ( mailButton ) {
		mailButton.addEventListener( 'click', function () {
			mailButton.disabled = true;
			text( '[data-mlab-mail-result]', config.working );
			fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: new URLSearchParams( { action: config.mailAction, nonce: config.mailNonce } ) } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( response ) { var answer = response.data || {}; text( '[data-mlab-mail-result]', answer.message || config.failed ); } )
				.catch( function () { text( '[data-mlab-mail-result]', config.failed ); } )
				.finally( function () { mailButton.disabled = dirty; } );
		} );
	}
}() );
