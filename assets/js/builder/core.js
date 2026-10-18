/**
 * MomentumLab Forms, Builder: reine Logik ohne DOM (Feld-IDs, Felder anlegen, Optionen, Hinweise).
 */
( function ( root ) {
	'use strict';

	var MAX_ID = 64;
	var UMLAUTS = { 'ä': 'ae', 'ö': 'oe', 'ü': 'ue', 'ß': 'ss' };

	function clone( value ) {
		return JSON.parse( JSON.stringify( value ) );
	}

	/**
	 * Macht aus einem Text eine ID aus a-z, 0-9 und _ (höchstens 64 Zeichen).
	 */
	function slugify( text ) {
		var slug = String( text ).toLowerCase()
			.replace( /[äöüß]/g, function ( c ) {
				return UMLAUTS[ c ];
			} )
			.normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' )
			.replace( /[^a-z0-9]+/g, '_' )
			.replace( /^_+|_+$/g, '' )
			.slice( 0, MAX_ID );

		return slug.replace( /_+$/, '' );
	}

	function idSet( fields ) {
		var set = {};
		( fields || [] ).forEach( function ( field ) {
			set[ field.id ] = true;
		} );
		return set;
	}

	/**
	 * Hängt bei Bedarf _2, _3 … an, bis die ID im Formular noch nicht vorkommt.
	 */
	function uniqueId( base, taken ) {
		var id = base || 'feld';
		var counter = 2;
		var suffix;

		while ( taken[ id ] ) {
			suffix = '_' + counter++;
			id = ( base || 'feld' ).slice( 0, MAX_ID - suffix.length ) + suffix;
		}
		return id;
	}

	/**
	 * Neue ID aus Wunsch oder Beschriftung. Bestehende Felder behalten ihre ID.
	 */
	function deriveId( label, fields, hint ) {
		return uniqueId( slugify( hint || label ), idSet( fields ) );
	}

	/**
	 * Neues Feld aus einem Baustein. «id» steht zuerst im Objekt.
	 */
	function fromPreset( preset, fields ) {
		var field = clone( preset.field );
		var id = deriveId( field.label, fields, preset.id_hint );

		return Object.assign( { id: id }, field, { id: id } ); // Die ID des Systems gewinnt immer.
	}

	/**
	 * Neues eigenes Feld eines frei gewählten Typs.
	 */
	function fromType( type, fields, texts ) {
		var field = { type: type.key, label: type.label };

		if ( 'choice' === type.kind ) {
			field.options = [ texts.option1, texts.option2 ];
		}
		if ( 'checkbox' === type.kind && 'consent' === type.key ) {
			field.required = true;
		}
		if ( 'html' === type.key ) {
			field.content = '';
		}
		return Object.assign( { id: deriveId( field.label, fields ) }, field );
	}

	/**
	 * Kopie eines Felds mit neuer ID. Die Beschriftung bekommt den Zusatz nur, wenn es eine gibt;
	 * Parameter der Vorauswahl zeigen auf die neue ID.
	 */
	function duplicate( field, fields, suffix ) {
		var copy = clone( field );

		if ( copy.label ) {
			copy.label += suffix;
		}
		copy.id = deriveId( copy.label || field.id + suffix, fields );
		if ( copy.prefill ) {
			copy.prefill.param = copy.id;
			delete copy.prefill.data_attribute; // Dasselbe Attribut dürfte nicht zwei Felder setzen.
		}
		return copy;
	}

	function move( list, index, delta ) {
		var target = index + delta;
		var item;

		if ( target < 0 || target >= list.length ) {
			return false;
		}
		item = list.splice( index, 1 )[ 0 ];
		list.splice( target, 0, item );
		return true;
	}

	/**
	 * Setzt einen Wert; leere Werte entfernen den Schlüssel.
	 */
	function setValue( obj, key, value ) {
		if ( undefined === value || null === value || '' === value || false === value ) {
			delete obj[ key ];
		} else {
			obj[ key ] = value;
		}
	}

	/* ---------- Optionen ---------- */

	function optionText( option ) {
		if ( 'string' === typeof option ) {
			return option;
		}
		return 'string' === typeof option.label ? option.label : option.value || '';
	}

	function optionIsDefault( option ) {
		return 'object' === typeof option && true === option.default;
	}

	function setOptionText( options, index, text ) {
		var option = options[ index ];

		if ( 'string' === typeof option ) {
			options[ index ] = text;
		} else if ( 'string' === typeof option.label ) {
			option.label = text;
		} else {
			option.value = text;
		}
	}

	/**
	 * Schaltet die Standardzeile um: höchstens eine Zeile trägt «default». Zeilen aus
	 * options_source.prepend zählen mit und verlieren ihre Markierung.
	 */
	function toggleDefault( options, index, prepend ) {
		var wasDefault = optionIsDefault( options[ index ] );

		options.forEach( function ( option, i ) {
			options[ i ] = withDefault( option, i === index && ! wasDefault );
		} );
		( prepend || [] ).forEach( function ( option, i ) {
			prepend[ i ] = withDefault( option, false );
		} );
	}

	function allOptions( field ) {
		var source = field.options_source && Array.isArray( field.options_source.prepend ) ? field.options_source.prepend : [];

		return ( Array.isArray( field.options ) ? field.options : [] ).concat( source );
	}

	/**
	 * Hinweise zu den Einträgen: leer, doppelt oder mehrere Standardzeilen.
	 */
	function optionProblems( field, texts ) {
		var list = allOptions( field );
		var seen = {};
		var empty = false;
		var duplicate = false;

		( field.options || [] ).forEach( function ( option ) {
			var value = 'string' === typeof option ? option : option.value;
			var text = optionText( option ).trim();

			empty = empty || '' === text;
			duplicate = duplicate || ( '' !== text && seen[ text ] ) || ( '' !== value && seen[ 'v:' + value ] );
			seen[ text ] = true;
			seen[ 'v:' + value ] = true;
		} );
		return [
			empty ? texts.optionEmpty : '',
			duplicate ? texts.optionDuplicate : '',
			list.filter( optionIsDefault ).length > 1 ? texts.multipleDefaults : '',
		].filter( Boolean );
	}

	function withDefault( option, on ) {
		var out = 'string' === typeof option ? { value: option } : option;

		if ( on ) {
			out.default = true;
		} else {
			delete out.default;
		}
		return 1 === Object.keys( out ).length && 'string' === typeof out.value ? out.value : out;
	}

	/* ---------- Eigenschaften je Feldtyp ---------- */

	/**
	 * Welche Eigenschaften der Typ kennt. Was ein Typ nicht kennt, zeigt der Builder nicht.
	 */
	function props( field, type ) {
		var kind = type ? type.kind : 'other';
		var input = ! type || type.input;
		var isHidden = 'hidden' === kind;
		var isChoice = 'choice' === kind;

		return {
			label: ! isHidden,
			labelMultiline: 'checkbox' === kind,
			placeholder: 'text' === kind || 'textarea' === kind || 'number' === kind || ( 'select' === field.type && false !== field.empty_first ),
			help: input && ! isHidden,
			defaultValue: 'text' === kind || 'textarea' === kind || 'number' === kind || isHidden,
			required: input && ! isHidden,
			width: ! isHidden,
			format: 'text' === kind && 'text' === field.type,
			options: isChoice,
			emptyFirst: isChoice && 'select' === field.type,
			prefill: isChoice && ( 'select' === field.type || 'radio' === field.type ),
			range: 'number' === kind,
			futureOnly: 'date' === kind,
			link: 'checkbox' === kind,
			content: 'content' === kind && 'html' === field.type,
		};
	}

	/**
	 * Schalter «Mit Datenschutzerklärung verlinken»: der alte Link kommt beim Einschalten zurück.
	 */
	function toggleLink( memory, field, on ) {
		if ( on ) {
			setValue( field, 'link', memory[ field.id + ':link' ] || '{privacy_url}' );
		} else {
			memory[ field.id + ':link' ] = field.link;
			delete field.link;
		}
	}

	/**
	 * Schalter «Nur Daten ab heute»: ein eigenes Mindestdatum kommt beim Ausschalten zurück.
	 */
	function toggleFuture( memory, field, on ) {
		var slot = field.id + ':min';

		if ( on ) {
			if ( field.min && 'today' !== field.min ) {
				memory[ slot ] = field.min;
			}
			field.min = 'today';
		} else {
			setValue( field, 'min', memory[ slot ] );
			delete memory[ slot ];
		}
	}

	/**
	 * Kommt {field:id} in einer Benachrichtigung vor?
	 */
	function usedInNotifications( schema, id ) {
		var tag = '{field:' + id + '}';
		var list = schema.settings && Array.isArray( schema.settings.notifications ) ? schema.settings.notifications : [];

		return list.some( function ( notification ) {
			return [ 'to', 'reply_to', 'subject', 'body' ].some( function ( key ) {
				return 'string' === typeof notification[ key ] && -1 !== notification[ key ].indexOf( tag );
			} );
		} );
	}

	function prefillEnabled( field ) {
		return !! field.prefill && false !== field.prefill.enabled;
	}

	function setPrefill( field, on ) {
		if ( on ) {
			field.prefill = Object.assign( { param: field.id }, field.prefill || {}, { enabled: true } );
			if ( ! field.prefill.param ) {
				field.prefill.param = field.id;
			}
		} else if ( field.prefill ) {
			field.prefill.enabled = false;
		}
	}

	/* ---------- Hinweise ---------- */

	/**
	 * Probleme, die das Speichern verhindern würden, in verständlicher Sprache.
	 *
	 * @return {Array<{id: string, message: string}>} id leer: betrifft das ganze Formular.
	 */
	function problems( schema, types, texts ) {
		var list = [];
		var fields = schema.fields || [];

		if ( ! fields.length ) {
			list.push( { id: '', message: texts.noFields } );
		}
		fields.forEach( function ( field ) {
			var type = types[ field.type ];
			var p = props( field, type );
			if ( p.label && type && type.input && ! String( field.label || '' ).trim() ) {
				list.push( { id: field.id, message: texts.labelMissing } );
			}
			if ( p.options && ! ( field.options && field.options.length ) && ! field.options_source ) {
				list.push( { id: field.id, message: texts.optionsMissing } );
			}
			if ( p.options && field.options ) {
				optionProblems( field, texts ).forEach( function ( message ) {
					list.push( { id: field.id, message: message } );
				} );
			}
		} );
		return list;
	}

	/**
	 * Fehler des Servers (Pfad wie fields.2.label) mit dem Namen des Felds davor.
	 * Ohne Beschriftung nennt die Meldung den Namen des Feldtyps.
	 */
	function friendlyError( error, schema, texts, types ) {
		var match = /^fields\.(\d+)/.exec( error.path || '' );
		var field = match && schema && schema.fields ? schema.fields[ Number( match[ 1 ] ) ] : null;
		var type = field && types ? types[ field.type ] : null;
		var name = field && ( field.label || ( type && type.label ) || field.id );

		return name ? texts.fieldPrefix.replace( '%s', name ) + ' ' + error.message : error.message;
	}

	var api = {
		clone: clone,
		slugify: slugify,
		uniqueId: uniqueId,
		deriveId: deriveId,
		fromPreset: fromPreset,
		fromType: fromType,
		duplicate: duplicate,
		move: move,
		setValue: setValue,
		optionText: optionText,
		optionIsDefault: optionIsDefault,
		setOptionText: setOptionText,
		toggleDefault: toggleDefault,
		props: props,
		prefillEnabled: prefillEnabled,
		toggleLink: toggleLink,
		toggleFuture: toggleFuture,
		usedInNotifications: usedInNotifications,
		optionProblems: optionProblems,
		setPrefill: setPrefill,
		problems: problems,
		friendlyError: friendlyError,
	};

	if ( 'undefined' !== typeof module && module.exports ) {
		module.exports = api;
	} else {
		root.MLabBuilder = root.MLabBuilder || {};
		root.MLabBuilder.Core = api;
	}
}( this ) );
