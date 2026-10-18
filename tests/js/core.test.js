'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const Core = require( '../../assets/js/builder/core.js' );

const TEXTS = { optionEmpty: 'leer', optionDuplicate: 'doppelt', multipleDefaults: 'mehrere', noFields: 'leer', labelMissing: 'Beschriftung fehlt', optionsMissing: 'Einträge fehlen', fieldPrefix: 'Feld «%s»:', option1: 'Option 1', option2: 'Option 2' };
const TYPES = {
	text: { key: 'text', label: 'Text', kind: 'text', input: true },
	select: { key: 'select', label: 'Auswahlliste', kind: 'choice', input: true },
	heading: { key: 'heading', label: 'Zwischentitel', kind: 'content', input: false },
};

test( 'slugify: Umlaute, Sonderzeichen, Länge', () => {
	assert.equal( Core.slugify( 'Vorname' ), 'vorname' );
	assert.equal( Core.slugify( 'Grösse der Gruppe?' ), 'groesse_der_gruppe' );
	assert.equal( Core.slugify( 'E-Mail' ), 'e_mail' );
	assert.equal( Core.slugify( 'Café Crème' ), 'cafe_creme' );
	assert.equal( Core.slugify( '???' ), '' );
	assert.equal( Core.slugify( 'a'.repeat( 100 ) ).length, 64 );
	assert.match( Core.slugify( 'Ünï ✓ Test 2' ), /^[a-z0-9_]{1,64}$/ );
} );

test( 'uniqueId: Zähler und Obergrenze von 64 Zeichen', () => {
	assert.equal( Core.uniqueId( 'name', {} ), 'name' );
	assert.equal( Core.uniqueId( 'name', { name: true } ), 'name_2' );
	assert.equal( Core.uniqueId( 'name', { name: true, name_2: true } ), 'name_3' );
	assert.equal( Core.uniqueId( '', {} ), 'feld' );
	const long = 'x'.repeat( 64 );
	const id = Core.uniqueId( long, { [ long ]: true } );
	assert.equal( id.length, 64 );
	assert.ok( id.endsWith( '_2' ) );
} );

test( 'deriveId bleibt eindeutig und nutzt den Wunsch zuerst', () => {
	const fields = [ { id: 'vorname' } ];
	assert.equal( Core.deriveId( 'Vorname', fields ), 'vorname_2' );
	assert.equal( Core.deriveId( 'Irgendwas', fields, 'telefon' ), 'telefon' );
	assert.equal( Core.deriveId( '', [] ), 'feld' );
} );

test( 'Baustein ergibt Feld mit ID zuerst, ohne das Original zu ändern', () => {
	const preset = { id_hint: 'email', field: { type: 'email', label: 'E-Mail', required: true } };
	const field = Core.fromPreset( preset, [ { id: 'email' } ] );
	assert.equal( Object.keys( field )[ 0 ], 'id' );
	assert.equal( field.id, 'email_2' );
	assert.equal( field.required, true );
	field.label = 'X';
	assert.equal( preset.field.label, 'E-Mail' );
} );

test( 'Eigenes Feld: Auswahl bekommt Einträge, ID aus Typ-Namen', () => {
	const field = Core.fromType( TYPES.select, [], TEXTS );
	assert.deepEqual( field.options, [ 'Option 1', 'Option 2' ] );
	assert.equal( field.id, 'auswahlliste' );
	assert.equal( Core.fromType( TYPES.text, [ { id: 'text' } ], TEXTS ).id, 'text_2' );
} );

test( 'Duplizieren vergibt neue ID und Beschriftung', () => {
	const copy = Core.duplicate( { id: 'a', label: 'A', options: [ 'x' ] }, [ { id: 'a' } ], ' (Kopie)' );
	assert.equal( copy.label, 'A (Kopie)' );
	assert.equal( copy.id, 'a_kopie' );
} );

test( 'move verschiebt und meldet Grenzen', () => {
	const list = [ 1, 2, 3 ];
	assert.equal( Core.move( list, 0, -1 ), false );
	assert.equal( Core.move( list, 0, 1 ), true );
	assert.deepEqual( list, [ 2, 1, 3 ] );
} );

test( 'Standardzeile: genau eine, Rückumwandlung in Text', () => {
	const options = [ 'A', { value: 'B', label: 'Bee' }, { value: 'C', default: true } ];
	Core.toggleDefault( options, 1 );
	assert.deepEqual( options, [ 'A', { value: 'B', label: 'Bee', default: true }, 'C' ] );
	Core.toggleDefault( options, 1 );
	assert.deepEqual( options, [ 'A', { value: 'B', label: 'Bee' }, 'C' ] );
	Core.toggleDefault( options, 0 );
	assert.deepEqual( options, [ { value: 'A', default: true }, { value: 'B', label: 'Bee' }, 'C' ] );
	assert.equal( options.filter( Core.optionIsDefault ).length, 1 );
} );

test( 'Optionstext lesen und schreiben', () => {
	const options = [ 'A', { value: 'B', label: 'Bee' }, { value: 'C' } ];
	Core.setOptionText( options, 0, 'A2' );
	Core.setOptionText( options, 1, 'Bee2' );
	Core.setOptionText( options, 2, 'C2' );
	assert.deepEqual( options.map( Core.optionText ), [ 'A2', 'Bee2', 'C2' ] );
	assert.equal( options[ 1 ].value, 'B' );
} );

test( 'Eigenschaften: unbekannte werden nicht angeboten', () => {
	assert.equal( Core.props( { type: 'heading' }, TYPES.heading ).required, false );
	assert.equal( Core.props( { type: 'select' }, TYPES.select ).options, true );
	assert.equal( Core.props( { type: 'text' }, TYPES.text ).options, false );
	assert.equal( Core.props( { type: 'text' }, TYPES.text ).format, true );
	assert.equal( Core.props( { type: 'x' }, undefined ).label, true );
} );

test( 'Vorauswahl per Link', () => {
	const field = { id: 'angebot', type: 'select' };
	Core.setPrefill( field, true );
	assert.deepEqual( field.prefill, { param: 'angebot', enabled: true } );
	assert.equal( Core.prefillEnabled( field ), true );
	Core.setPrefill( field, false );
	assert.equal( Core.prefillEnabled( field ), false );
	assert.equal( field.prefill.param, 'angebot' );
} );

test( 'Hinweise zu Problemen und Serverfehlern', () => {
	const schema = { fields: [ { id: 'a', type: 'text', label: ' ' }, { id: 'b', type: 'select', label: 'B', options: [] } ] };
	const list = Core.problems( schema, TYPES, TEXTS );
	assert.deepEqual( list.map( ( p ) => p.id ), [ 'a', 'b' ] );
	assert.equal( Core.problems( { fields: [] }, TYPES, TEXTS )[ 0 ].message, 'leer' );
	assert.equal( Core.friendlyError( { path: 'fields.1.label', message: 'fehlt' }, schema, TEXTS ), 'Feld «B»: fehlt' );
	assert.equal( Core.friendlyError( { path: '', message: 'x' }, schema, TEXTS ), 'x' );
} );

test( 'Leere Beschriftung: Tippen ändert nur das Label, nicht den Wert', () => {
	const options = [ { value: 'a', label: 'Alpha' } ];
	Core.setOptionText( options, 0, '' );
	assert.deepEqual( options[ 0 ], { value: 'a', label: '' } );
	Core.setOptionText( options, 0, 'Neu' );
	assert.deepEqual( options[ 0 ], { value: 'a', label: 'Neu' } );
} );

test( 'Die ID des Systems gewinnt gegen eine ID im Baustein', () => {
	const field = Core.fromPreset( { id_hint: 'x', field: { id: 'boese', type: 'text', label: 'X' } }, [] );
	assert.equal( field.id, 'x' );
	assert.equal( Object.keys( field )[ 0 ], 'id' );
} );

test( 'Duplizieren: Vorauswahl zeigt auf die neue ID, Label nur wenn vorhanden', () => {
	const copy = Core.duplicate( { id: 'angebot', type: 'select', label: 'Angebot', prefill: { enabled: true, param: 'angebot', data_attribute: 'data-x' } }, [ { id: 'angebot' } ], ' (Kopie)' );
	assert.equal( copy.id, 'angebot_kopie' );
	assert.equal( copy.prefill.param, 'angebot_kopie' );
	assert.equal( copy.prefill.data_attribute, undefined );
	const bare = Core.duplicate( { id: 'h', type: 'hidden' }, [ { id: 'h' } ], ' (Kopie)' );
	assert.equal( bare.label, undefined );
	assert.notEqual( bare.id, 'h' );
} );

test( 'Standardzeile räumt auch options_source.prepend', () => {
	const field = { options: [ 'A', 'B' ], options_source: { prepend: [ { value: 'P', default: true } ] } };
	Core.toggleDefault( field.options, 1, field.options_source.prepend );
	assert.equal( Core.optionProblems( field, TEXTS ).length, 0 );
	assert.deepEqual( field.options_source.prepend, [ 'P' ] );
	assert.equal( Core.optionIsDefault( field.options[ 1 ] ), true );
} );

test( 'Hinweise: leere, doppelte Einträge und mehrere Standardzeilen', () => {
	const types = { select: TYPES.select };
	const check = ( options, extra ) => Core.problems( { fields: [ Object.assign( { id: 'a', type: 'select', label: 'A', options }, extra ) ] }, types, TEXTS ).map( ( p ) => p.message );
	assert.deepEqual( check( [ 'x', ' ' ] ), [ 'leer' ] );
	assert.deepEqual( check( [ 'x', 'x' ] ), [ 'doppelt' ] );
	assert.deepEqual( check( [ { value: 'a', label: 'X' }, { value: 'a', label: 'Y' } ] ), [ 'doppelt' ] );
	assert.deepEqual( check( [ 'x', 'y' ] ), [] );
	assert.deepEqual( check( [ { value: 'x', default: true } ], { options_source: { provider: 'post_type', prepend: [ { value: 'p', default: true } ] } } ), [ 'mehrere' ] );
} );

test( 'Schalter merken alte Werte', () => {
	const memory = {};
	const consent = { id: 'c', link: 'https://example.org/datenschutz' };
	Core.toggleLink( memory, consent, false );
	assert.equal( consent.link, undefined );
	Core.toggleLink( memory, consent, true );
	assert.equal( consent.link, 'https://example.org/datenschutz' );
	Core.toggleLink( memory, consent, false );
	delete memory[ 'c:link' ];
	Core.toggleLink( memory, consent, true );
	assert.equal( consent.link, '{privacy_url}' );

	const date = { id: 'd', min: '2026-01-01' };
	Core.toggleFuture( memory, date, true );
	assert.equal( date.min, 'today' );
	Core.toggleFuture( memory, date, false );
	assert.equal( date.min, '2026-01-01' );
	Core.toggleFuture( memory, date, false );
	assert.equal( date.min, undefined );
} );

test( 'Benachrichtigungen: Feld wird erkannt', () => {
	const schema = { settings: { notifications: [ { to: '{site_admin_email}', subject: 'Hallo {field:name}', body: '{all_fields}' }, { to: 'x@example.org' } ] } };
	assert.equal( Core.usedInNotifications( schema, 'name' ), true );
	assert.equal( Core.usedInNotifications( schema, 'email' ), false );
	assert.equal( Core.usedInNotifications( {}, 'name' ), false );
} );

/* ---------- Sprachen und Übersetzungen ---------- */

const DEFAULTS = { submitLabel: 'Senden', successMessage: 'Danke!' };
const ALL_TYPES = Object.assign( {}, TYPES, { email: { key: 'email', label: 'E-Mail', kind: 'text', input: true } } );

function multi() {
	return {
		schema_version: 1,
		languages: [ 'de', 'fr', 'en' ],
		settings: { submit_label: 'Senden', i18n: { fr: { submit_label: 'Envoyer' } } },
		fields: [
			{ id: 'name', type: 'text', label: 'Name', placeholder: 'z. B. Anna', i18n: { fr: { label: 'Nom' } } },
			{ id: 'ort', type: 'select', label: 'Ort', options: [ 'Basel', { value: 'bern', label: 'Bern' } ], i18n: { fr: { options: { Basel: 'Bâle' } } } },
		],
	};
}

test( 'languages: nur gültige, einmalige Codes; Standardsprache ist die erste', () => {
	assert.deepEqual( Core.languages( { languages: [ 'de', 'FR', 'fr', 'fr', 5, 'deutsch' ] } ), [ 'de', 'fr' ] );
	assert.deepEqual( Core.languages( {} ), [] );
	assert.equal( Core.defaultLanguage( multi() ), 'de' );
	assert.equal( Core.defaultLanguage( { languages: [ 'de' ] } ), '' );
	assert.equal( Core.isMultilingual( { languages: [ 'de' ] } ), false );
	assert.equal( Core.isMultilingual( multi() ), true );
} );

test( 'addLanguage: erste Sprache legt die Standardsprache fest, Doppelte und Ungültige werden abgelehnt', () => {
	const schema = { fields: [] };

	assert.equal( Core.addLanguage( schema, 'fr', 'de' ), true );
	assert.deepEqual( schema.languages, [ 'de', 'fr' ] );
	assert.equal( Core.addLanguage( schema, 'en', 'xx' ), true );
	assert.deepEqual( schema.languages, [ 'de', 'fr', 'en' ] );
	assert.equal( Core.addLanguage( schema, 'fr', 'de' ), false );
	assert.equal( Core.addLanguage( schema, 'FR', 'de' ), false );
	assert.equal( Core.addLanguage( schema, '../', 'de' ), false );
	assert.deepEqual( schema.languages, [ 'de', 'fr', 'en' ] );
	assert.equal( Core.addLanguage( { fields: [] }, 'de', 'de' ), false );
	assert.equal( Core.addLanguage( { fields: [] }, 'fr', 'Deutsch' ), false );
} );

test( 'setText: setzt, leert und räumt leere Hüllen auf', () => {
	const field = { id: 'a', label: 'A' };

	Core.setText( field, 'fr', 'label', 'Ah' );
	assert.deepEqual( field.i18n, { fr: { label: 'Ah' } } );
	Core.setText( field, 'fr', 'help', 'Aide' );
	Core.setText( field, 'fr', 'label', '' );
	assert.deepEqual( field.i18n, { fr: { help: 'Aide' } } );
	Core.setText( field, 'fr', 'help', '   ' );
	assert.equal( 'i18n' in field, false );
	Core.setText( field, 'fr', 'label', '' ); // nichts zu löschen, kein Fehler
	assert.equal( 'i18n' in field, false );
	assert.equal( Core.getText( multi().fields[ 0 ], 'fr', 'label' ), 'Nom' );
	assert.equal( Core.getText( multi().fields[ 0 ], 'en', 'label' ), '' );
} );

test( 'setOptionLabel: Beschriftung pro Wert, leer löscht und räumt auf', () => {
	const field = { id: 'o', options: [ 'Basel' ] };

	Core.setOptionLabel( field, 'fr', 'Basel', 'Bâle' );
	assert.deepEqual( field.i18n, { fr: { options: { Basel: 'Bâle' } } } );
	assert.equal( Core.getOptionLabel( field, 'fr', 'Basel' ), 'Bâle' );
	Core.setOptionLabel( field, 'fr', 'Basel', '' );
	assert.equal( 'i18n' in field, false );
} );

test( 'renameOption und moveOptionLabels: Übersetzungen folgen dem Wert erst nach der Eingabe', () => {
	const field = multi().fields[ 1 ];
	let before = Core.renameOption( field, 0, 'Basel-Stadt' );

	assert.equal( before, 'Basel' );
	assert.equal( field.options[ 0 ], 'Basel-Stadt' );
	assert.deepEqual( field.i18n.fr.options, { Basel: 'Bâle' }, 'beim Tippen bleibt alles stehen' );
	assert.equal( Core.moveOptionLabels( field, before, 0 ), true );
	assert.deepEqual( field.i18n.fr.options, { 'Basel-Stadt': 'Bâle' } );

	before = Core.renameOption( field, 1, 'Berne' ); // Objekt mit label: der Wert bleibt
	assert.equal( field.options[ 1 ].value, 'bern' );
	assert.equal( field.options[ 1 ].label, 'Berne' );
	assert.equal( Core.moveOptionLabels( field, before, 1 ), false );
} );

test( 'moveOptionLabels: «AB» → «A» (existiert) → «AB» verliert nichts', () => {
	const field = { id: 'o', type: 'select', options: [ 'A', 'AB' ], i18n: { fr: { options: { A: 'Un', AB: 'Un-B' } } } };
	let before = Core.renameOption( field, 1, 'A' );

	assert.equal( Core.moveOptionLabels( field, before, 1 ), false );
	assert.deepEqual( field.i18n.fr.options, { A: 'Un', AB: 'Un-B' } );
	before = Core.renameOption( field, 1, 'AB' );
	assert.equal( Core.moveOptionLabels( field, before, 1 ), false );
	assert.deepEqual( field.i18n.fr.options, { A: 'Un', AB: 'Un-B' } );
} );

test( 'moveOptionLabels: überschreibt keinen vorhandenen Übersetzungsschlüssel', () => {
	const field = { id: 'o', options: [ 'X' ], i18n: { fr: { options: { X: 'x', Y: 'y' } } } };
	const before = Core.renameOption( field, 0, 'Y' );

	assert.equal( Core.moveOptionLabels( field, before, 0 ), false );
	assert.deepEqual( field.i18n.fr.options, { X: 'x', Y: 'y' } );
} );

test( 'removeOption: löscht auch die Übersetzungen des Werts', () => {
	const field = multi().fields[ 1 ];

	Core.removeOption( field, 0 );
	assert.deepEqual( field.options, [ { value: 'bern', label: 'Bern' } ] );
	assert.equal( 'i18n' in field, false );
} );

test( 'removeLanguage: Standardsprache bleibt, Übersetzungen verschwinden ohne leere Hüllen', () => {
	const schema = multi();

	assert.equal( Core.countTranslations( schema, 'fr' ), 3 );
	assert.equal( Core.countTranslations( schema, 'en' ), 0 );
	assert.equal( Core.removeLanguage( schema, 'de' ), false );
	assert.equal( Core.removeLanguage( schema, 'it' ), false );
	assert.equal( Core.removeLanguage( schema, 'fr' ), true );
	assert.deepEqual( schema.languages, [ 'de', 'en' ] );
	assert.equal( JSON.stringify( schema ).includes( 'i18n' ), false );
	assert.equal( 'i18n' in schema.settings, false );
} );

test( 'removeLanguage: mit nur einer übrigen Sprache wird das Formular wieder einsprachig', () => {
	const schema = multi();

	Core.removeLanguage( schema, 'fr' );
	Core.removeLanguage( schema, 'en' );
	assert.equal( 'languages' in schema, false );
	assert.equal( JSON.stringify( schema ).includes( 'i18n' ), false );
} );

test( 'translationStatus: zählt Beschriftungen, Platzhalter, Einträge und Einstellungen', () => {
	const schema = multi();

	// Einstellungen: 2, name: label + placeholder, ort: label + 2 Einträge = 7.
	assert.deepEqual( Core.translationStatus( schema, 'fr', ALL_TYPES, DEFAULTS ), { done: 3, total: 7 } );
	assert.deepEqual( Core.translationStatus( schema, 'en', ALL_TYPES, DEFAULTS ), { done: 0, total: 7 } );
	Core.setText( schema.fields[ 0 ], 'fr', 'placeholder', 'p. ex. Anne' );
	assert.equal( Core.translationStatus( schema, 'fr', ALL_TYPES, DEFAULTS ).done, 4 );
} );

test( 'translationStatus: Texte ohne Standardtext zählen nicht, versteckte Felder haben keine', () => {
	const schema = { languages: [ 'de', 'fr' ], fields: [
		{ id: 'a', type: 'text', label: 'A' },
		{ id: 'h', type: 'hidden', label: 'Intern', default: 'x' },
	], settings: { submit_label: '', success_message: 'Merci' } };
	const types = Object.assign( {}, ALL_TYPES, { hidden: { key: 'hidden', label: 'Versteckt', kind: 'hidden', input: true } } );

	assert.deepEqual( Core.translationStatus( schema, 'fr', types, DEFAULTS ), { done: 0, total: 3 } );
} );

test( 'missingTranslations und localizedField', () => {
	const field = multi().fields[ 1 ];
	const view = Core.localizedField( field, 'fr' );

	assert.equal( Core.missingTranslations( field, ALL_TYPES.select, 'fr' ), 2 );
	assert.equal( Core.missingTranslations( field, ALL_TYPES.select, 'en' ), 3 );
	assert.equal( Core.optionText( view.options[ 0 ] ), 'Bâle' );
	assert.equal( Core.optionValue( view.options[ 0 ] ), 'Basel' );
	assert.equal( Core.optionText( view.options[ 1 ] ), 'Bern' );
	assert.equal( field.options[ 0 ], 'Basel', 'das Original bleibt unverändert' );
	assert.equal( Core.localizedField( multi().fields[ 0 ], 'fr' ).label, 'Nom' );
	assert.equal( Core.localizedField( multi().fields[ 0 ], 'fr' ).placeholder, 'z. B. Anna' );
} );

test( 'parseAddresses: Trenner, Duplikate, ungültige und zu viele Adressen', () => {
	const parsed = Core.parseAddresses( 'a@firma.ch; B@firma.ch,\nkaputt, a@FIRMA.ch,, x@y' );

	assert.deepEqual( parsed.valid, [ 'a@firma.ch', 'B@firma.ch' ] );
	assert.deepEqual( parsed.invalid, [ 'kaputt', 'x@y' ] );
	assert.equal( parsed.tooMany, false );
	assert.deepEqual( Core.parseAddresses( '' ), { valid: [], invalid: [], tooMany: false } );
	assert.deepEqual( Core.parseAddresses( 'a@firma.ch\nBcc: x@firma.ch' ).invalid, [ 'Bcc: x@firma.ch' ] );

	const many = Array.from( { length: 11 }, ( _, i ) => 'p' + i + '@firma.ch' ).join( ', ' );
	assert.equal( Core.parseAddresses( many ).tooMany, true );
} );

test( 'setRecipients: setzen, leeren ohne leere Hülle', () => {
	const schema = { fields: [] };

	Core.setRecipients( schema, '  verkauf@firma.ch ' );
	assert.deepEqual( schema.settings, { recipients: 'verkauf@firma.ch' } );

	Core.setRecipients( schema, '   ' );
	assert.equal( 'settings' in schema, false );

	const other = { settings: { submit_label: 'Los', recipients: 'a@b.ch' } };
	Core.setRecipients( other, '' );
	assert.deepEqual( other.settings, { submit_label: 'Los' } );
} );

test( 'hasVisitorMail: nur mit Benachrichtigung an eine Feld-Adresse', () => {
	assert.equal( Core.hasVisitorMail( {} ), false );
	assert.equal( Core.hasVisitorMail( { settings: { notifications: [ { to: '{default_recipient}' } ] } } ), false );
	assert.equal( Core.hasVisitorMail( { settings: { notifications: [ { to: '{default_recipient}' }, { to: '{field:email}' } ] } } ), true );
} );
