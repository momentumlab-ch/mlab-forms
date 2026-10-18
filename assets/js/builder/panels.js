/**
 * MomentumLab Forms, Builder: Reiter «Einstellungen» und «Vorschau».
 */
( function ( root ) {
	'use strict';

	var ns = root.MLabBuilder;
	var h = ns.Dom.h;
	var Core = ns.Core;

	/* ---------- Einstellungen ---------- */

	function settingInput( app, key, labelText, opts ) {
		var id = ns.Dom.uid();
		var current = ( app.schema.settings || {} )[ key ];
		var control = h( opts.multiline ? 'textarea' : 'input', {
			id: id,
			class: 'mlab-b-in',
			type: opts.multiline ? null : ( opts.type || 'text' ),
			rows: opts.multiline ? 3 : null,
			placeholder: opts.placeholder,
			value: current || '',
			autocomplete: 'off',
			oninput: function () {
				app.schema.settings = app.schema.settings || {};
				Core.setValue( app.schema.settings, key, control.value.trim() );
				if ( ! Object.keys( app.schema.settings ).length ) {
					delete app.schema.settings;
				}
				app.change( 'text' );
			},
		} );

		return h( 'div', { class: 'mlab-b-li' }, [
			h( 'label', { for: id }, labelText ),
			control,
			opts.hint ? h( 'div', { class: 'mlab-b-muted mlab-b-small' }, opts.hint ) : null,
		] );
	}

	/**
	 * Meldung zu einer Adressliste, leer wenn sie in Ordnung ist.
	 */
	function recipientsProblem( app, text ) {
		var parsed = Core.parseAddresses( text );

		if ( parsed.invalid.length ) {
			return app.t( 'recipientsInvalid' ).replace( '%s', parsed.invalid.join( ', ' ) );
		}
		return parsed.tooMany ? app.t( 'recipientsTooMany' ).replace( '%s', '10' ) : '';
	}

	/**
	 * Abschnitt «Empfänger»: eigene Adresse(n) statt des Standard-Empfängers, mit Prüfung beim Tippen.
	 */
	function recipientsSection( app ) {
		var id = ns.Dom.uid();
		var errorId = id + '-err';
		var current = ( app.schema.settings || {} ).recipients;
		var error = h( 'div', { class: 'mlab-b-err', id: errorId, role: 'alert', hidden: true } );
		var control = h( 'textarea', {
			id: id,
			class: 'mlab-b-in',
			rows: 2,
			value: 'string' === typeof current ? current : '',
			placeholder: app.t( 'recipientsExample' ),
			autocomplete: 'off',
			spellcheck: false,
			'aria-describedby': errorId,
			oninput: function () {
				Core.setRecipients( app.schema, control.value );
				paint();
				app.change( 'text' );
			},
		} );

		function paint() {
			var message = recipientsProblem( app, control.value );

			error.textContent = message;
			error.hidden = ! message;
			control.setAttribute( 'aria-invalid', message ? 'true' : 'false' );
		}

		paint();

		return [
			h( 'h2', { class: 'mlab-b-group' }, app.t( 'recipientsGroup' ) ),
			h( 'div', { class: 'mlab-b-inset' }, h( 'div', { class: 'mlab-b-li' }, [
				h( 'label', { for: id }, app.t( 'recipientsLabel' ) ),
				control,
				error,
				h( 'div', { class: 'mlab-b-muted mlab-b-small' }, app.t( 'recipientsEmptyHint' ).replace( '%s', app.config.defaults.recipients || '' ) ),
				Core.hasVisitorMail( app.schema ) ? h( 'div', { class: 'mlab-b-muted mlab-b-small' }, app.t( 'recipientsGuestNote' ) ) : null,
			] ) ),
		];
	}

	/**
	 * Übersetzung von Button-Text oder Erfolgsmeldung; darunter steht der Standardtext.
	 */
	function settingTranslation( app, slot, labelText, multiline ) {
		var id = ns.Dom.uid();
		var current = Core.getText( app.schema.settings || {}, app.lang, slot.key );
		var warn = h( 'span', { class: 'mlab-b-tag mlab-b-tag-warn', hidden: '' !== current.trim() }, app.t( 'notTranslated' ) );
		var control = h( multiline ? 'textarea' : 'input', {
			id: id,
			class: 'mlab-b-in',
			rows: multiline ? 3 : null,
			value: current,
			lang: app.lang,
			autocomplete: 'off',
			oninput: function () {
				app.schema.settings = app.schema.settings || {};
				Core.setText( app.schema.settings, app.lang, slot.key, control.value );
				if ( ! Object.keys( app.schema.settings ).length ) {
					delete app.schema.settings;
				}
				warn.hidden = '' !== control.value.trim();
				app.change( 'text' );
			},
		} );

		return h( 'div', { class: 'mlab-b-li' }, [
			h( 'label', { for: id }, [ labelText, ' ', warn ] ),
			control,
			h( 'div', { class: 'mlab-b-muted mlab-b-small mlab-b-source' }, app.t( 'sourceText' ).replace( '%s', slot.source ) ),
		] );
	}

	function renderSettingTranslations( app, el ) {
		var slots = Core.settingSlots( app.schema, app.config.defaults );

		ns.Dom.clear( el );
		el.appendChild( h( 'div', { class: 'mlab-b-set-wrap' }, [
			h( 'h2', { class: 'mlab-b-group' }, app.t( 'afterSubmit' ) ),
			h( 'div', { class: 'mlab-b-inset' }, [
				settingTranslation( app, slots[ 0 ], app.t( 'submitLabel' ), false ),
				settingTranslation( app, slots[ 1 ], app.t( 'successMessage' ), true ),
			] ),
			h( 'p', { class: 'mlab-b-muted mlab-b-small' }, app.t( 'translatingNote' ).replace( '%s', ns.Languages.nameOf( app, app.lang ) ) ),
			h( 'p', { class: 'mlab-b-muted mlab-b-small' }, app.t( 'mailTranslateNote' ) ),
		] ) );
	}

	function renderSettings( app, el ) {
		var defaults = app.config.defaults;

		if ( app.translating() ) {
			renderSettingTranslations( app, el );
			return;
		}
		ns.Dom.clear( el );
		el.appendChild( h( 'div', { class: 'mlab-b-set-wrap' }, [
			h( 'h2', { class: 'mlab-b-group' }, app.t( 'afterSubmit' ) ),
			h( 'div', { class: 'mlab-b-inset' }, [
				settingInput( app, 'submit_label', app.t( 'submitLabel' ), { placeholder: defaults.submitLabel } ),
				settingInput( app, 'success_message', app.t( 'successMessage' ), { multiline: true, placeholder: defaults.successMessage } ),
				settingInput( app, 'redirect_url', app.t( 'thanksPage' ), { type: 'url', placeholder: 'https://', hint: app.t( 'thanksPageHint' ) } ),
			] ),
		].concat( recipientsSection( app ), [
			h( 'h2', { class: 'mlab-b-group' }, app.t( 'retention' ) ),
			h( 'div', { class: 'mlab-b-inset' }, h( 'div', { class: 'mlab-b-li mlab-b-muted' }, app.t( 'retentionHint' ) ) ),
		] ) ) );
	}

	/* ---------- Vorschau ---------- */

	function createPreview( app ) {
		var state = { phone: true, timer: null, request: 0 };
		var frame = h( 'iframe', { class: 'mlab-b-pv-frame', title: app.t( 'previewTitle' ), sandbox: 'allow-same-origin' } );
		var status = h( 'div', { class: 'mlab-b-pv-status', role: 'status' } );
		var wrap = h( 'div', { class: 'mlab-b-pv-wrap is-phone' }, [ frame ] );
		var buttons = {};
		var el = h( 'div', { class: 'mlab-b-pv' } );
		var languageHost = h( 'div', { class: 'mlab-b-pv-lang', hidden: true } );

		function sizeButton( phone, text ) {
			buttons[ phone ] = h( 'button', {
				type: 'button',
				onclick: function () {
					state.phone = phone;
					paintButtons();
				},
			}, text );
			return buttons[ phone ];
		}

		function paintButtons() {
			wrap.classList.toggle( 'is-phone', state.phone );
			buttons[ true ].setAttribute( 'aria-pressed', state.phone ? 'true' : 'false' );
			buttons[ false ].setAttribute( 'aria-pressed', state.phone ? 'false' : 'true' );
			fit();
		}

		function fit() {
			var doc = frame.contentDocument;
			if ( doc && doc.documentElement ) {
				frame.style.height = Math.max( 240, doc.documentElement.scrollHeight ) + 'px';
			}
		}

		/**
		 * Verständliche Hinweise des Builders gehen vor den Meldungen des Servers.
		 */
		function messages( errors ) {
			var own = Core.problems( app.schema, app.config.types, app.config.texts );

			if ( ! own.length ) {
				return errors.map( function ( error ) {
					return Core.friendlyError( error, app.schema, app.config.texts, app.config.types );
				} );
			}
			return own.map( function ( problem ) {
				var field = app.schema.fields.filter( function ( item ) {
					return item.id === problem.id;
				} )[ 0 ];
				var type = field ? app.config.types[ field.type ] : null;
				var name = field ? field.label || ( type && type.label ) || field.id : '';

				return name ? app.t( 'fieldPrefix' ).replace( '%s', name ) + ' ' + problem.message : problem.message;
			} );
		}

		function showErrors( errors ) {
			ns.Dom.clear( status );
			frame.hidden = true;
			status.className = 'mlab-b-pv-status is-error';
			status.appendChild( h( 'strong', null, app.t( 'previewInvalid' ) ) );
			status.appendChild( h( 'ul', null, messages( errors ).map( function ( text ) {
				return h( 'li', null, text );
			} ) ) );
		}

		function show( document ) {
			status.className = 'mlab-b-pv-status';
			status.textContent = '';
			frame.hidden = false;
			frame.srcdoc = document;
		}

		function load() {
			var body = new window.URLSearchParams( {
				action: app.config.preview.action,
				nonce: app.config.preview.nonce,
				schema: JSON.stringify( app.schema ),
				lang: app.lang,
			} );
			var ticket = ++state.request;

			status.className = 'mlab-b-pv-status';
			status.textContent = app.t( 'previewLoading' );
			window.fetch( app.config.preview.url, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( data ) {
					var answer = data && data.data ? data.data : {};
					if ( ticket !== state.request ) {
						return;
					}
					if ( data && data.success && answer.ok ) {
						show( answer.document );
					} else if ( answer.errors ) {
						showErrors( answer.errors );
					} else {
						throw new Error( 'preview' );
					}
				} )
				.catch( function () {
					status.className = 'mlab-b-pv-status is-error';
					status.textContent = app.t( 'previewFailed' );
				} );
		}

		frame.addEventListener( 'load', fit );
		el.appendChild( h( 'div', { class: 'mlab-b-pv-bar' }, [
			h( 'div', { class: 'mlab-b-seg', role: 'group', 'aria-label': app.t( 'previewSize' ) }, [
				sizeButton( true, app.t( 'phone' ) ),
				sizeButton( false, app.t( 'computer' ) ),
			] ),
			languageHost,
		] ) );
		app.switchers = app.switchers.filter( function ( host ) {
			return host.isConnected;
		} );
		app.switchers.push( languageHost );
		ns.Languages.renderSwitcher( app, languageHost );
		el.appendChild( h( 'p', { class: 'mlab-b-muted mlab-b-small mlab-b-center' }, app.t( 'previewNote' ) ) );
		el.appendChild( status );
		el.appendChild( wrap );
		paintButtons();

		return {
			element: el,
			refresh: function () {
				clearTimeout( state.timer );
				state.timer = setTimeout( load, 250 );
			},
		};
	}

	root.MLabBuilder.Panels = { renderSettings: renderSettings, createPreview: createPreview };
}( window ) );
