'use strict';

const test = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const Core = require( '../../assets/js/builder/core.js' );

const TEXTS = { noFields: 'leer', labelMissing: 'Beschriftung fehlt', optionsMissing: 'Einträge fehlen', fieldPrefix: 'Feld «%s»:', option1: 'Option 1', option2: 'Option 2' };
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
