/**
 * MomentumLab Forms, Builder: Palette (Bausteine) und Formular-Ansicht (Felder in der Mitte).
 */
( function ( root ) {
	'use strict';

	var ns = root.MLabBuilder;
	var h = ns.Dom.h;
	var Core = ns.Core;

	/* ---------- Palette ---------- */

	function renderPalette( app, el ) {
		var items = app.config.presets.map( function ( preset ) {
			return h(
				'button',
				{
					type: 'button',
					class: 'mlab-b-pal-item',
					'data-preset': preset.key,
					onclick: function () {
						app.addField( Core.fromPreset( preset, app.schema.fields ) );
					},
				},
				[ h( 'b', null, preset.name ), h( 'span', null, preset.hint ) ]
			);
		} );

		items.push( h(
			'button',
			{ type: 'button', class: 'mlab-b-pal-item mlab-b-pal-custom', 'data-preset': 'custom', onclick: function () {
				openCustomDialog( app );
			} },
			[ h( 'b', null, app.t( 'customField' ) ), h( 'span', null, app.t( 'customFieldHint' ) ) ]
		) );

		ns.Dom.clear( el );
		el.appendChild( h( 'div', { class: 'mlab-b-pal', role: 'group', 'aria-label': app.t( 'palette' ) }, items ) );
	}

	/**
	 * «Eigenes Feld»: Dialog mit den Feldtypen des Systems.
	 */
	function openCustomDialog( app ) {
		var dialog = h( 'dialog', { class: 'mlab-b-dialog', 'aria-labelledby': 'mlab-b-dialog-title' } );
		var close = function () {
			dialog.close();
			dialog.remove();
		};
		var buttons = app.config.typeList.map( function ( type ) {
			return h(
				'button',
				{
					type: 'button',
					class: 'mlab-b-type',
					'data-type': type.key,
					onclick: function () {
						close();
						app.addField( Core.fromType( type, app.schema.fields, { option1: app.t( 'option1' ), option2: app.t( 'option2' ) } ) );
					},
				},
				[ h( 'b', null, type.label ), h( 'span', null, app.typeHint( type ) ) ]
			);
		} );

		dialog.appendChild( h( 'h2', { id: 'mlab-b-dialog-title' }, app.t( 'customField' ) ) );
		dialog.appendChild( h( 'p', { class: 'mlab-b-muted' }, app.t( 'customFieldIntro' ) ) );
		dialog.appendChild( h( 'div', { class: 'mlab-b-types' }, buttons ) );
		dialog.appendChild( h( 'button', { type: 'button', class: 'mlab-b-btn', onclick: close }, app.t( 'cancel' ) ) );
		dialog.addEventListener( 'cancel', function () {
			dialog.remove();
		} );
		app.root.appendChild( dialog );
		dialog.showModal();
	}

	/* ---------- Formular-Ansicht ---------- */

	function fakeControl( app, field, type ) {
		var kind = type ? type.kind : 'other';
		var text = '';

		if ( 'checkbox' === kind || 'content' === kind || 'hidden' === kind ) {
			return null;
		}
		if ( 'choice' === kind ) {
			text = 'select' === field.type ? selectText( app, field ) : ( field.options || [] ).map( function ( option ) {
				return ( 'radio' === field.type ? '○ ' : '☐ ' ) + Core.optionText( option );
			} ).join( '   ' );
		} else {
			text = field.placeholder || '';
		}
		return h( 'div', { class: 'mlab-b-fake' + ( 'textarea' === kind ? ' is-tall' : '' ) + ( 'select' === field.type ? ' is-select' : '' ) }, text );
	}

	function selectText( app, field ) {
		var options = field.options || [];
		var chosen = options.filter( Core.optionIsDefault )[ 0 ];

		if ( chosen ) {
			return Core.optionText( chosen );
		}
		if ( false === field.empty_first ) {
			return options.length ? Core.optionText( options[ 0 ] ) : '';
		}
		return field.placeholder || app.t( 'pleaseChoose' );
	}

	function rowTitle( field, type ) {
		var label = field.label || ( type ? type.label : field.type );

		return [
			'checkbox' === ( type && type.kind ) ? '☐ ' + label : label,
			field.required ? h( 'span', { class: 'mlab-b-req', 'aria-hidden': 'true' }, ' *' ) : null,
		];
	}

	function actionButton( app, field, action, index, count ) {
		var disabled = ( 'up' === action && 0 === index ) || ( 'down' === action && index === count - 1 );
		var labels = { up: 'moveUp', down: 'moveDown', copy: 'duplicate', trash: 'remove' };

		return h( 'button', {
			type: 'button',
			class: 'mlab-b-icon-btn',
			'data-focus': action + ':' + field.id,
			'aria-label': app.t( labels[ action ] ) + ': ' + ( field.label || field.id ),
			title: app.t( labels[ action ] ),
			disabled: disabled,
			onclick: function () {
				app.rowAction( action, field.id );
			},
		}, ns.Dom.icon( action ) );
	}

	function renderRow( app, field, index, count, hints ) {
		var type = app.config.types[ field.type ];
		var selected = field.id === app.selectedId;
		var hint = hints[ field.id ];
		var cls = 'mlab-b-row mlab-b-w-' + ( field.width || 'full' ) + ( selected ? ' is-selected' : '' ) + ( 'content' === ( type && type.kind ) ? ' is-content' : '' );

		return h( 'li', { class: cls, 'data-id': field.id }, [
			h( 'button', {
				type: 'button',
				class: 'mlab-b-row-main',
				'data-focus': 'select:' + field.id,
				'aria-current': selected ? 'true' : null,
				'aria-label': app.t( 'field' ) + ' ' + ( field.label || field.id ) + ( hint ? '. ' + hint : '' ),
				onclick: function () {
					app.select( field.id, true );
				},
			}, [
				h( 'span', { class: 'mlab-b-row-top' }, [
					h( 'span', { class: 'mlab-b-row-label' }, rowTitle( field, type ) ),
					h( 'span', { class: 'mlab-b-tag' }, type ? type.label : field.type ),
				] ),
				fakeControl( app, field, type ),
				hint ? h( 'span', { class: 'mlab-b-row-warn' }, hint ) : null,
			] ),
			h( 'span', { class: 'mlab-b-row-btns' }, [ 'up', 'down', 'copy', 'trash' ].map( function ( action ) {
				return actionButton( app, field, action, index, count );
			} ) ),
		] );
	}

	function renderCanvas( app, el ) {
		var fields = app.schema.fields || [];
		var hints = {};

		Core.problems( app.schema, app.config.types, app.config.texts ).forEach( function ( problem ) {
			hints[ problem.id ] = problem.message;
		} );

		ns.Dom.keepFocus( el, function () {
			ns.Dom.clear( el );
			if ( ! fields.length ) {
				el.appendChild( h( 'p', { class: 'mlab-b-empty' }, app.t( 'emptyForm' ) ) );
				return;
			}
			el.appendChild( h( 'ul', { class: 'mlab-b-canvas' }, fields.map( function ( field, index ) {
				return renderRow( app, field, index, fields.length, hints );
			} ) ) );
		}, function ( key ) {
			var id = key.slice( key.indexOf( ':' ) + 1 );
			return [ 'up:' + id, 'down:' + id, 'select:' + id ];
		} );
	}

	root.MLabBuilder.Fields = { renderPalette: renderPalette, renderCanvas: renderCanvas };
}( window ) );
