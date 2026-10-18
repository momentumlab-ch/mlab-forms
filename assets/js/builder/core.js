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

	function optionValue( option ) {
		return 'string' === typeof option ? option : String( option.value || '' );
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

	/* ---------- Sprachen und Übersetzungen ---------- */

	var LANG_PATTERN = /^[a-z]{2,3}$/;
	var FIELD_TEXT_KEYS = [ 'label', 'placeholder', 'help', 'content' ];
	var SETTING_TEXT_KEYS = [ 'submit_label', 'success_message' ];

	/**
	 * Gültige, einmalige Sprachcodes des Schemas; die erste ist die Standardsprache.
	 */
	function languages( schema ) {
		var list = schema && Array.isArray( schema.languages ) ? schema.languages : [];

		return list.filter( function ( code, index ) {
			return 'string' === typeof code && LANG_PATTERN.test( code ) && index === list.indexOf( code );
		} );
	}

	function isMultilingual( schema ) {
		return languages( schema ).length > 1;
	}

	function defaultLanguage( schema ) {
		return isMultilingual( schema ) ? languages( schema )[ 0 ] : '';
	}

	/**
	 * Alles, was «i18n» trägt: Einstellungen, Benachrichtigungen und Felder.
	 */
	function holders( schema ) {
		var settings = schema.settings && 'object' === typeof schema.settings ? schema.settings : null;
		var list = settings ? [ settings ] : [];

		( settings && Array.isArray( settings.notifications ) ? settings.notifications : [] ).forEach( function ( notification ) {
			if ( notification && 'object' === typeof notification ) {
				list.push( notification );
			}
		} );
		return list.concat( ( schema.fields || [] ).filter( Boolean ) );
	}

	/**
	 * Fügt eine Sprache hinzu. Hat das Formular noch keine, wird defaultCode die Standardsprache.
	 *
	 * @return {boolean} false bei ungültigem oder vorhandenem Code.
	 */
	function addLanguage( schema, code, defaultCode ) {
		var list = languages( schema );

		if ( ! LANG_PATTERN.test( code ) || -1 !== list.indexOf( code ) ) {
			return false;
		}
		if ( ! list.length ) {
			if ( ! LANG_PATTERN.test( defaultCode ) || defaultCode === code ) {
				return false;
			}
			list = [ defaultCode ];
		}
		schema.languages = list.concat( code );
		return true;
	}

	/**
	 * Alle Texte einer Sprache (ohne leere).
	 */
	function countTranslations( schema, code ) {
		var count = 0;

		holders( schema ).forEach( function ( holder ) {
			var bundle = holder.i18n && holder.i18n[ code ] ? holder.i18n[ code ] : {};

			Object.keys( bundle ).forEach( function ( key ) {
				if ( 'options' === key && bundle.options && 'object' === typeof bundle.options ) {
					count += Object.keys( bundle.options ).filter( function ( value ) {
						return '' !== String( bundle.options[ value ] ).trim();
					} ).length;
				} else if ( 'string' === typeof bundle[ key ] && '' !== bundle[ key ].trim() ) {
					count += 1;
				}
			} );
		} );
		return count;
	}

	function dropEmptyI18n( holder ) {
		if ( holder.i18n && ! Object.keys( holder.i18n ).length ) {
			delete holder.i18n;
		}
	}

	/**
	 * Entfernt eine Sprache samt Übersetzungen. Mit nur einer Sprache wird das Formular wieder einsprachig.
	 * Die Standardsprache lässt sich nicht entfernen.
	 *
	 * @return {boolean} false bei Standardsprache oder unbekanntem Code.
	 */
	function removeLanguage( schema, code ) {
		var list = languages( schema );

		if ( list.indexOf( code ) < 1 ) {
			return false;
		}
		holders( schema ).forEach( function ( holder ) {
			if ( holder.i18n ) {
				delete holder.i18n[ code ];
				dropEmptyI18n( holder );
			}
		} );
		list.splice( list.indexOf( code ), 1 );
		if ( list.length > 1 ) {
			schema.languages = list;
		} else {
			delete schema.languages;
		}
		return true;
	}

	/**
	 * Übersetzung eines Texts oder leer.
	 */
	function getText( holder, lang, key ) {
		var bundle = holder.i18n && holder.i18n[ lang ] ? holder.i18n[ lang ] : null;

		return bundle && 'string' === typeof bundle[ key ] ? bundle[ key ] : '';
	}

	/**
	 * Setzt eine Übersetzung; ein leerer Text löscht sie und räumt leere Hüllen auf.
	 */
	function setText( holder, lang, key, text ) {
		var empty = '' === String( text ).trim();
		var bundle;

		if ( empty ) {
			if ( holder.i18n && holder.i18n[ lang ] ) {
				delete holder.i18n[ lang ][ key ];
				if ( ! Object.keys( holder.i18n[ lang ] ).length ) {
					delete holder.i18n[ lang ];
				}
				dropEmptyI18n( holder );
			}
			return;
		}
		holder.i18n = holder.i18n || {};
		bundle = holder.i18n[ lang ] = holder.i18n[ lang ] || {};
		bundle[ key ] = text;
	}

	function getOptionLabel( field, lang, value ) {
		var labels = field.i18n && field.i18n[ lang ] ? field.i18n[ lang ].options : null;

		return labels && 'string' === typeof labels[ value ] ? labels[ value ] : '';
	}

	/**
	 * Setzt die Beschriftung einer Option in einer Sprache (der Wert der Option bleibt); leer löscht sie.
	 */
	function setOptionLabel( field, lang, value, text ) {
		var bundle;

		if ( '' === String( text ).trim() ) {
			if ( field.i18n && field.i18n[ lang ] && field.i18n[ lang ].options ) {
				delete field.i18n[ lang ].options[ value ];
				if ( ! Object.keys( field.i18n[ lang ].options ).length ) {
					setText( field, lang, 'options', '' );
				}
			}
			return;
		}
		field.i18n = field.i18n || {};
		bundle = field.i18n[ lang ] = field.i18n[ lang ] || {};
		bundle.options = bundle.options || {};
		bundle.options[ value ] = text;
	}

	function forEachOptionLabels( field, fn ) {
		Object.keys( field.i18n || {} ).forEach( function ( lang ) {
			if ( field.i18n[ lang ] && field.i18n[ lang ].options ) {
				fn( field.i18n[ lang ].options, lang );
			}
		} );
	}

	/**
	 * Ändert den Text einer Option in der Standardsprache. Die Übersetzungen bleiben unberührt;
	 * sie wandern erst mit moveOptionLabels(), wenn die Eingabe abgeschlossen ist.
	 *
	 * @return {string} Der Wert der Option vor der Änderung.
	 */
	function renameOption( field, index, text ) {
		var before = optionValue( field.options[ index ] );

		setOptionText( field.options, index, text );
		return before;
	}

	/**
	 * Lässt die Übersetzungen dem neuen Wert einer Option folgen, wenn der Text zugleich der Wert ist.
	 * Nichts wird verschoben oder überschrieben, wenn der neue Wert schon zu einer anderen Option gehört
	 * oder schon als Übersetzungsschlüssel vorkommt.
	 *
	 * @return {boolean} true, wenn Übersetzungen verschoben wurden.
	 */
	function moveOptionLabels( field, before, index ) {
		var after = optionValue( field.options[ index ] );
		var owned = Object.prototype.hasOwnProperty;
		var taken = field.options.some( function ( option, i ) {
			return i !== index && optionValue( option ) === after;
		} );
		var clash = false;
		var moved = false;

		if ( before === after || taken ) {
			return false;
		}
		forEachOptionLabels( field, function ( labels ) {
			clash = clash || owned.call( labels, after );
		} );
		if ( clash ) {
			return false;
		}
		forEachOptionLabels( field, function ( labels ) {
			if ( owned.call( labels, before ) ) {
				labels[ after ] = labels[ before ];
				delete labels[ before ];
				moved = true;
			}
		} );
		return moved;
	}

	/**
	 * Entfernt einen Eintrag samt seinen Übersetzungen (ausser ein anderer Eintrag hat denselben Wert).
	 */
	function removeOption( field, index ) {
		var value = optionValue( field.options[ index ] );

		field.options.splice( index, 1 );
		if ( field.options.some( function ( option ) {
			return optionValue( option ) === value;
		} ) ) {
			return;
		}
		Object.keys( field.i18n || {} ).forEach( function ( lang ) {
			setOptionLabel( field, lang, value, '' );
		} );
	}

	/**
	 * Zu übersetzende Texte eines Felds: Beschriftung, Platzhalter, Hilfetext, Inhalt und Einträge,
	 * jeweils nur wenn der Standardtext nicht leer ist.
	 *
	 * @return {Array<{key: string, source: string, value?: string}>}
	 */
	function fieldSlots( field, type ) {
		var p = props( field, type );
		var flags = { label: p.label, placeholder: p.placeholder, help: p.help, content: p.content };
		var slots = [];

		FIELD_TEXT_KEYS.forEach( function ( key ) {
			var source = String( field[ key ] || '' );
			if ( flags[ key ] && '' !== source.trim() ) {
				slots.push( { key: key, source: source } );
			}
		} );
		if ( p.options ) {
			allOptions( field ).forEach( function ( option ) {
				var source = optionText( option );
				if ( '' !== source.trim() ) {
					slots.push( { key: 'options', value: optionValue( option ), source: source } );
				}
			} );
		}
		return slots;
	}

	function settingSlots( schema, defaults ) {
		var settings = schema.settings || {};
		var fallback = { submit_label: defaults.submitLabel, success_message: defaults.successMessage };

		return SETTING_TEXT_KEYS.map( function ( key ) {
			return { key: key, source: String( settings[ key ] || fallback[ key ] || '' ) };
		} );
	}

	function slotTranslated( holder, lang, slot ) {
		var value = 'options' === slot.key ? getOptionLabel( holder, lang, slot.value ) : getText( holder, lang, slot.key );

		return '' !== value.trim();
	}

	/**
	 * Wie viele Texte in der Sprache übersetzt sind.
	 *
	 * @return {{done: number, total: number}}
	 */
	function translationStatus( schema, lang, types, defaults ) {
		var status = { done: 0, total: 0 };
		var count = function ( holder, slots ) {
			slots.forEach( function ( slot ) {
				status.total += 1;
				status.done += slotTranslated( holder, lang, slot ) ? 1 : 0;
			} );
		};

		count( schema.settings || {}, settingSlots( schema, defaults ) );
		( schema.fields || [] ).forEach( function ( field ) {
			count( field, fieldSlots( field, types[ field.type ] ) );
		} );
		return status;
	}

	/**
	 * Anzahl der Texte eines Felds, die in der Sprache noch fehlen.
	 */
	function missingTranslations( field, type, lang ) {
		return fieldSlots( field, type ).filter( function ( slot ) {
			return ! slotTranslated( field, lang, slot );
		} ).length;
	}

	/**
	 * Kopie des Felds mit den Texten der Sprache, für die Anzeige in der Formular-Ansicht.
	 * Nicht übersetzte Texte bleiben Standardtexte.
	 */
	function localizedField( field, lang ) {
		var view = Object.assign( {}, field );

		FIELD_TEXT_KEYS.forEach( function ( key ) {
			if ( '' !== getText( field, lang, key ).trim() ) {
				view[ key ] = getText( field, lang, key );
			}
		} );
		if ( Array.isArray( field.options ) ) {
			view.options = field.options.map( function ( option ) {
				var label = getOptionLabel( field, lang, optionValue( option ) );

				if ( '' === label.trim() ) {
					return option;
				}
				return Object.assign( {}, 'string' === typeof option ? { value: option } : option, { label: label } );
			} );
		}
		return view;
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
		optionValue: optionValue,
		optionIsDefault: optionIsDefault,
		setOptionText: setOptionText,
		renameOption: renameOption,
		moveOptionLabels: moveOptionLabels,
		removeOption: removeOption,
		languages: languages,
		isMultilingual: isMultilingual,
		defaultLanguage: defaultLanguage,
		addLanguage: addLanguage,
		removeLanguage: removeLanguage,
		countTranslations: countTranslations,
		getText: getText,
		setText: setText,
		getOptionLabel: getOptionLabel,
		setOptionLabel: setOptionLabel,
		fieldSlots: fieldSlots,
		settingSlots: settingSlots,
		slotTranslated: slotTranslated,
		translationStatus: translationStatus,
		missingTranslations: missingTranslations,
		localizedField: localizedField,
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
