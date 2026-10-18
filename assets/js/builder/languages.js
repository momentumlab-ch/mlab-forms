/**
 * MomentumLab Forms, Builder: Sprachen (Umschalter, Sprache hinzufügen und entfernen, Zähler).
 */
( function ( root ) {
	'use strict';

	var ns = root.MLabBuilder;
	var h = ns.Dom.h;
	var Core = ns.Core;

	function nameOf( app, code ) {
		var found = app.config.languageList.filter( function ( item ) {
			return item.code === code;
		} )[ 0 ];

		return found ? found.name : code.toUpperCase();
	}

	/**
	 * Sprachen, die sich noch hinzufügen lassen. Die erste Sprache hinzuzufügen heisst, eine zweite neben die
	 * Sprache der Website zu stellen; diese ist dann die Standardsprache und steht nicht zur Wahl.
	 */
	function available( app ) {
		var taken = Core.languages( app.schema );

		if ( ! taken.length ) {
			taken = [ app.config.siteLanguage ];
		}
		return app.config.languageList.filter( function ( item ) {
			return -1 === taken.indexOf( item.code );
		} );
	}

	/**
	 * Segmentierte Auswahl der Sprachen; leer, solange das Formular einsprachig ist.
	 */
	function switcher( app ) {
		var codes = Core.languages( app.schema );
		var buttons;

		if ( codes.length < 2 ) {
			return null;
		}
		buttons = codes.map( function ( code, index ) {
			var title = nameOf( app, code ) + ( 0 === index ? ' (' + app.t( 'defaultLanguage' ) + ')' : '' );

			return h( 'button', {
				type: 'button',
				'aria-pressed': code === app.lang ? 'true' : 'false',
				'aria-label': title,
				title: title,
				lang: code,
				'data-focus': 'lang:' + code,
				onclick: function () {
					app.setLang( code );
				},
			}, code.toUpperCase() );
		} );
		return h( 'div', { class: 'mlab-b-seg', role: 'group', 'aria-label': app.t( 'language' ) }, buttons );
	}

	function addSelect( app ) {
		var choices = available( app );
		var select;

		if ( ! choices.length ) {
			return null;
		}
		select = h( 'select', {
			class: 'mlab-b-in mlab-b-lang-add',
			'aria-label': app.t( 'addLanguage' ),
			'data-focus': 'lang-add',
			onchange: function () {
				var code = select.value;

				select.value = '';
				if ( code ) {
					app.addLanguage( code );
				}
			},
		}, [ h( 'option', { value: '' }, app.t( 'addLanguage' ) + ' …' ) ].concat( choices.map( function ( item ) {
			return h( 'option', { value: item.code }, item.name + ' (' + item.code + ')' );
		} ) ) );

		return select;
	}

	/**
	 * Zähler «12 von 14 übersetzt» der gewählten Sprache oder der Hinweis auf die Standardsprache.
	 */
	function counterText( app ) {
		var status;

		if ( ! app.translating() ) {
			return Core.isMultilingual( app.schema ) ? app.t( 'defaultLanguageNote' ) : app.t( 'oneLanguageNote' );
		}
		status = Core.translationStatus( app.schema, app.lang, app.config.types, app.config.defaults );
		return app.t( 'translated' ).replace( '%1$d', status.done ).replace( '%2$d', status.total );
	}

	function removeButton( app ) {
		if ( ! app.translating() ) {
			return null;
		}
		return h( 'button', {
			type: 'button',
			class: 'mlab-b-link',
			'data-focus': 'lang-remove',
			onclick: function () {
				app.removeLanguage( app.lang );
			},
		}, app.t( 'removeLanguage' ) );
	}

	/**
	 * Zeichnet die Leiste unter den Reitern neu.
	 */
	function renderBar( app, el ) {
		ns.Dom.keepFocus( el, function () {
			ns.Dom.clear( el );
			[
				h( 'span', { class: 'mlab-b-lbl' }, app.t( 'language' ) ),
				switcher( app ),
				addSelect( app ),
				h( 'span', { class: 'mlab-b-muted mlab-b-small mlab-b-count', 'data-role': 'count' }, counterText( app ) ),
				removeButton( app ),
			].forEach( function ( child ) {
				if ( child ) {
					el.appendChild( child );
				}
			} );
		}, [ 'lang-add' ] );
	}

	/**
	 * Aktualisiert nur den Zähler (bei jeder Texteingabe).
	 */
	function updateCount( app, el ) {
		var count = el.querySelector( '[data-role="count"]' );

		if ( count ) {
			count.textContent = counterText( app );
		}
	}

	/**
	 * Umschalter für die Vorschau; leer bei einsprachigen Formularen.
	 */
	function renderSwitcher( app, host ) {
		var control = switcher( app );

		ns.Dom.keepFocus( host, function () {
			ns.Dom.clear( host );
			host.hidden = ! control;
			if ( control ) {
				host.appendChild( control );
			}
		} );
	}

	root.MLabBuilder.Languages = {
		renderBar: renderBar,
		updateCount: updateCount,
		renderSwitcher: renderSwitcher,
		nameOf: nameOf,
	};
}( window ) );
