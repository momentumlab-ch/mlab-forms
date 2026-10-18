/**
 * MomentumLab Forms, Builder: Eigenschaften des gewählten Felds (rechts, am Handy als Blatt von unten).
 */
( function ( root ) {
	'use strict';

	var ns = root.MLabBuilder;
	var h = ns.Dom.h;
	var Core = ns.Core;

	/* ---------- Bausteine der Oberfläche ---------- */

	function row( labelText, control, id, hint ) {
		return h( 'div', { class: 'mlab-b-field' }, [
			h( 'label', { for: id }, labelText ),
			control,
			hint ? h( 'div', { class: 'mlab-b-muted mlab-b-small' }, hint ) : null,
		] );
	}

	/**
	 * Eingabe, die direkt in das Feld schreibt. Leere Eingaben entfernen die Eigenschaft.
	 */
	function textInput( app, field, key, labelText, opts ) {
		var id = ns.Dom.uid();
		var tag = opts && opts.multiline ? 'textarea' : 'input';
		var control = h( tag, {
			id: id,
			class: 'mlab-b-in',
			type: 'textarea' === tag ? null : ( opts && opts.type ) || 'text',
			rows: 'textarea' === tag ? ( opts && opts.rows ) || 3 : null,
			value: undefined === field[ key ] ? '' : field[ key ],
			'data-focus': 'prop:' + key,
			autocomplete: 'off',
			oninput: function () {
				var value = opts && opts.number ? ( '' === control.value ? '' : Number( control.value ) ) : control.value;
				Core.setValue( field, key, value );
				if ( opts && opts.onInput ) {
					opts.onInput( control );
				}
				app.change( 'text' );
			},
		} );

		return row( labelText, control, id, opts && opts.hint );
	}

	function toggle( app, title, hint, checked, onChange, focusKey ) {
		var titleId = ns.Dom.uid();
		var hintId = ns.Dom.uid();
		var input = h( 'input', {
			type: 'checkbox',
			role: 'switch',
			checked: checked,
			'aria-labelledby': titleId,
			'aria-describedby': hint ? hintId : null,
			'data-focus': focusKey,
			onchange: function () {
				onChange( input.checked );
				app.change( 'structure' );
			},
		} );

		return h( 'div', { class: 'mlab-b-field mlab-b-toggle' }, [
			h( 'div', { class: 'mlab-b-toggle-text' }, [
				h( 'div', { class: 'mlab-b-lbl', id: titleId }, title ),
				hint ? h( 'div', { class: 'mlab-b-muted mlab-b-small', id: hintId }, hint ) : null,
			] ),
			h( 'span', { class: 'mlab-b-sw' }, [ input, h( 'i', { 'aria-hidden': 'true' } ) ] ),
		] );
	}

	function segmented( app, field, key, labelText, choices ) {
		var labelId = ns.Dom.uid();
		var buttons = choices.map( function ( choice ) {
			var on = ( field[ key ] || choices[ 0 ].value ) === choice.value;
			return h( 'button', {
				type: 'button',
				'aria-pressed': on ? 'true' : 'false',
				'data-focus': key + ':' + choice.value,
				onclick: function () {
					Core.setValue( field, key, 'full' === choice.value ? '' : choice.value );
					app.change( 'structure' );
				},
			}, choice.label );
		} );

		return h( 'div', { class: 'mlab-b-field' }, [
			h( 'span', { class: 'mlab-b-lbl', id: labelId }, labelText ),
			h( 'div', { class: 'mlab-b-seg', role: 'group', 'aria-labelledby': labelId }, buttons ),
		] );
	}

	/* ---------- Auswahllisten ---------- */

	function optionLine( app, field, index, count ) {
		var options = field.options;
		var isDefault = Core.optionIsDefault( options[ index ] );
		var input = h( 'input', {
			class: 'mlab-b-in',
			value: Core.optionText( options[ index ] ),
			'aria-label': app.t( 'entry' ) + ' ' + ( index + 1 ),
			'data-focus': 'opt-in:' + index,
			autocomplete: 'off',
			oninput: function () {
				Core.setOptionText( options, index, input.value );
				app.change( 'text' );
			},
		} );
		var act = function ( name, labelKey, disabled, fn ) {
			return h( 'button', {
				type: 'button',
				class: 'mlab-b-icon-btn',
				'data-focus': name + ':' + index,
				'aria-label': app.t( labelKey ) + ' (' + ( index + 1 ) + ')',
				title: app.t( labelKey ),
				disabled: disabled,
				onclick: function () {
					fn();
					app.change( 'structure' );
				},
			}, ns.Dom.icon( name ) );
		};

		return h( 'li', { class: 'mlab-b-opt' }, [
			h( 'button', {
				type: 'button',
				class: 'mlab-b-icon-btn mlab-b-star',
				'aria-pressed': isDefault ? 'true' : 'false',
				'aria-label': app.t( 'makeDefault' ) + ' (' + ( index + 1 ) + ')',
				title: app.t( 'makeDefault' ),
				'data-focus': 'opt-def:' + index,
				onclick: function () {
					Core.toggleDefault( options, index );
					app.change( 'structure' );
				},
			}, ns.Dom.icon( 'star' ) ),
			input,
			act( 'up', 'moveUp', 0 === index, function () {
				Core.move( options, index, -1 );
			} ),
			act( 'down', 'moveDown', index === count - 1, function () {
				Core.move( options, index, 1 );
			} ),
			act( 'trash', 'removeEntry', false, function () {
				options.splice( index, 1 );
			} ),
		] );
	}

	function optionsEditor( app, field, container ) {
		var count;

		if ( ! Array.isArray( field.options ) ) {
			field.options = [];
		}
		count = field.options.length;
		container.appendChild( h( 'div', { class: 'mlab-b-hr' } ) );
		container.appendChild( h( 'div', { class: 'mlab-b-lbl' }, app.t( 'entries' ) ) );
		container.appendChild( h( 'ul', { class: 'mlab-b-opts' }, field.options.map( function ( option, index ) {
			return optionLine( app, field, index, count );
		} ) ) );
		container.appendChild( h( 'button', {
			type: 'button',
			class: 'mlab-b-btn',
			'data-focus': 'opt-add',
			onclick: function () {
				field.options.push( app.t( 'newEntry' ) );
				app.change( 'structure' );
				var added = container.querySelector( '[data-focus="opt-in:' + ( field.options.length - 1 ) + '"]' );
				if ( added ) {
					added.focus();
					added.select();
				}
			},
		}, app.t( 'addEntry' ) ) );
		container.appendChild( h( 'p', { class: 'mlab-b-muted mlab-b-small' }, app.t( 'defaultHint' ) ) );
	}

	function choiceSwitches( app, field, p, container ) {
		if ( p.emptyFirst ) {
			container.appendChild( toggle( app, app.t( 'emptyFirst' ), app.t( 'emptyFirstHint' ), false !== field.empty_first, function ( on ) {
				field.empty_first = on;
				if ( on ) {
					delete field.empty_first;
				}
			}, 'prop:empty_first' ) );
		}
		if ( p.prefill ) {
			var param = ( field.prefill && field.prefill.param ) || field.id;
			container.appendChild( toggle( app, app.t( 'prefill' ), app.t( 'prefillHint' ).replace( '%s', param ), Core.prefillEnabled( field ), function ( on ) {
				Core.setPrefill( field, on );
			}, 'prop:prefill' ) );
			container.appendChild( h( 'p', { class: 'mlab-b-muted mlab-b-small' }, app.t( 'prefillDev' ) ) );
		}
	}

	/* ---------- Prüfhinweis ---------- */

	function checkText( app, field, type ) {
		var kind = type ? type.kind : '';

		if ( 'email' === field.type ) {
			return app.t( 'checkEmail' );
		}
		if ( 'tel' === field.type || 'tel' === field.format ) {
			return app.t( 'checkTel' );
		}
		if ( 'postcode_ch' === field.format ) {
			return app.t( 'checkPostcode' );
		}
		return { date: app.t( 'checkDate' ), number: app.t( 'checkNumber' ) }[ kind ] || '';
	}

	/* ---------- Zusammenbau ---------- */

	function labelInput( app, field, p, type ) {
		var message = h( 'div', { class: 'mlab-b-err', role: 'alert', hidden: true }, app.t( 'labelMissing' ) );
		var heading = 'content' === ( type && type.kind );
		var labelText = heading ? app.t( 'titleText' ) : app.t( 'label' );
		var needed = !! ( type && type.input );
		var wrap = textInput( app, field, 'label', labelText, {
			multiline: p.labelMultiline,
			onInput: function ( control ) {
				var bad = needed && '' === control.value.trim();
				control.setAttribute( 'aria-invalid', bad ? 'true' : 'false' );
				message.hidden = ! bad;
			},
		} );

		wrap.appendChild( message );
		return wrap;
	}

	function basics( app, field, p, type, box ) {
		var info = checkText( app, field, type );

		if ( p.label ) {
			box.appendChild( labelInput( app, field, p, type ) );
		}
		if ( p.placeholder ) {
			box.appendChild( textInput( app, field, 'placeholder', 'select' === field.type ? app.t( 'emptyLabel' ) : app.t( 'placeholder' ) ) );
		}
		if ( p.help ) {
			box.appendChild( textInput( app, field, 'help', app.t( 'help' ) ) );
		}
		if ( p.content ) {
			box.appendChild( textInput( app, field, 'content', app.t( 'htmlContent' ), { multiline: true, rows: 5 } ) );
		}
		if ( p.defaultValue ) {
			box.appendChild( textInput( app, field, 'default', app.t( 'defaultValue' ), { number: 'number' === type.kind, type: 'number' === type.kind ? 'number' : 'text' } ) );
		}
		if ( info ) {
			box.appendChild( h( 'div', { class: 'mlab-b-info' }, app.t( 'autoChecked' ) + ' ' + info ) );
		}
	}

	function behaviour( app, field, p, type, box ) {
		if ( p.format ) {
			box.appendChild( formatSelect( app, field ) );
		}
		if ( p.range ) {
			box.appendChild( textInput( app, field, 'min', app.t( 'min' ), { number: true, type: 'number' } ) );
			box.appendChild( textInput( app, field, 'max', app.t( 'max' ), { number: true, type: 'number' } ) );
		}
		if ( p.futureOnly ) {
			box.appendChild( toggle( app, app.t( 'futureOnly' ), app.t( 'futureOnlyHint' ), 'today' === field.min, function ( on ) {
				Core.setValue( field, 'min', on ? 'today' : '' );
			}, 'prop:min' ) );
		}
		if ( p.required ) {
			box.appendChild( toggle( app, app.t( 'required' ), app.t( 'requiredHint' ), !! field.required, function ( on ) {
				Core.setValue( field, 'required', on );
			}, 'prop:required' ) );
		}
		if ( p.link && 'consent' === field.type ) {
			box.appendChild( toggle( app, app.t( 'linkPrivacy' ), app.t( 'linkPrivacyHint' ), !! field.link, function ( on ) {
				Core.setValue( field, 'link', on ? '{privacy_url}' : '' );
			}, 'prop:link' ) );
		}
		if ( p.width ) {
			box.appendChild( segmented( app, field, 'width', app.t( 'width' ), [
				{ value: 'full', label: app.t( 'widthFull' ) },
				{ value: 'half', label: app.t( 'widthHalf' ) },
				{ value: 'third', label: app.t( 'widthThird' ) },
			] ) );
		}
		if ( p.options ) {
			optionsEditor( app, field, box );
			choiceSwitches( app, field, p, box );
		}
	}

	function formatSelect( app, field ) {
		var id = ns.Dom.uid();
		var select = h( 'select', {
			id: id,
			class: 'mlab-b-in',
			'data-focus': 'prop:format',
			onchange: function () {
				Core.setValue( field, 'format', select.value );
				app.change( 'structure' );
			},
		}, [
			h( 'option', { value: '' }, app.t( 'formatNone' ) ),
			h( 'option', { value: 'postcode_ch' }, app.t( 'formatPostcode' ) ),
		] );

		select.value = field.format || '';
		return row( app.t( 'format' ), select, id );
	}

	function render( app, el ) {
		var field = app.field();

		ns.Dom.keepFocus( el, function () {
			var box = h( 'div', { class: 'mlab-b-insp-body' } );
			var type = field ? app.config.types[ field.type ] : null;

			ns.Dom.clear( el );
			el.appendChild( h( 'button', {
				type: 'button',
				class: 'mlab-b-btn mlab-b-sheet-close',
				'data-focus': 'sheet-close',
				onclick: function () {
					app.closeSheet();
				},
			}, app.t( 'done' ) ) );
			el.appendChild( h( 'h2', { id: 'mlab-b-insp-title', tabindex: '-1' }, app.t( 'properties' ) ) );

			if ( ! field ) {
				el.appendChild( h( 'p', { class: 'mlab-b-muted' }, app.t( 'selectField' ) ) );
				return;
			}
			var p = Core.props( field, type );
			basics( app, field, p, type, box );
			behaviour( app, field, p, type, box );
			el.appendChild( box );
		} );
	}

	root.MLabBuilder.Inspector = { render: render };
}( window ) );
