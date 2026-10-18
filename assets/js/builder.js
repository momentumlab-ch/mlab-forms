/**
 * MomentumLab Forms, Builder: Start und Zusammenspiel.
 *
 * Der Zustand (das Schema) liegt im Speicher. Bei jeder Änderung wird das JSON in das Textfeld des
 * Formulars geschrieben, das WordPress beim Speichern mitsendet; geprüft wird auf dem Server.
 */
( function ( root ) {
	'use strict';

	var ns = root.MLabBuilder;
	var h = ns.Dom.h;
	var Core = ns.Core;
	var NARROW_WIDTH = 760;
	var UNDO_SECONDS = 10;
	var TABS = [ 'fields', 'settings', 'preview' ];

	function typeMap( list ) {
		var map = {};
		list.forEach( function ( type ) {
			map[ type.key ] = type;
		} );
		return map;
	}

	/**
	 * Schema aus dem JSON-Text. Wirft einen Error mit verständlicher Meldung.
	 */
	function parseSchema( text, texts ) {
		var schema;

		try {
			schema = JSON.parse( text );
		} catch ( error ) {
			throw new Error( texts.jsonInvalid.replace( '%s', error.message ) );
		}
		if ( ! schema || 'object' !== typeof schema || Array.isArray( schema ) ) {
			throw new Error( texts.jsonNotObject );
		}
		if ( ! Array.isArray( schema.fields ) ) {
			throw new Error( texts.jsonNeedsFields );
		}
		return schema;
	}

	function starterSchema( config ) {
		var fields = [];

		config.starter.forEach( function ( key ) {
			var preset = config.presets.filter( function ( item ) {
				return item.key === key;
			} )[ 0 ];
			if ( preset ) {
				fields.push( Core.fromPreset( preset, fields ) );
			}
		} );
		return { schema_version: 1, fields: fields };
	}

	function createApp( rootEl, textarea, config ) {
		var texts = config.texts;
		var live = ns.Dom.liveRegion();
		var app = {
			root: rootEl,
			config: config,
			schema: { schema_version: 1, fields: [] },
			selectedId: null,
			expert: false,
			dirty: false,
			memory: {},
			tab: 'fields',
			lang: '',
			switchers: [],
			translating: function () {
				return Core.isMultilingual( app.schema ) && app.lang !== Core.defaultLanguage( app.schema );
			},
			t: function ( key ) {
				return texts[ key ] || key;
			},
			typeHint: function ( type ) {
				return config.typeHints[ type.key ] || '';
			},
			field: function () {
				return ( app.schema.fields || [] ).filter( function ( item ) {
					return item.id === app.selectedId;
				} )[ 0 ] || null;
			},
		};
		var parts = {};

		config.types = typeMap( config.typeList );

		/* ---------- Zustand ---------- */

		function sync() {
			textarea.value = JSON.stringify( app.schema, null, 2 );
			app.dirty = true;
		}

		function renderFields() {
			ns.Fields.renderCanvas( app, parts.canvas );
			ns.Inspector.render( app, parts.inspector );
		}

		/**
		 * Hält die gewählte Sprache gültig, wenn sich die Sprachen des Schemas geändert haben
		 * (Rückgängig, Vorlage, Experten-Ansicht).
		 */
		function normalizeLang() {
			var codes = Core.languages( app.schema );

			if ( codes.length < 2 ) {
				app.lang = '';
			} else if ( -1 === codes.indexOf( app.lang ) ) {
				app.lang = codes[ 0 ];
			}
		}

		function renderLanguages() {
			normalizeLang();
			ns.Languages.renderBar( app, parts.langbar );
			app.switchers.forEach( function ( host ) {
				ns.Languages.renderSwitcher( app, host );
			} );
		}

		function renderAll() {
			renderLanguages();
			renderFields();
			if ( 'settings' === app.tab ) {
				ns.Panels.renderSettings( app, parts.views.settings );
			}
			if ( 'preview' === app.tab ) {
				parts.preview.refresh();
			}
		}

		app.change = function ( level ) {
			hideUndo();
			sync();
			if ( 'structure' === level ) {
				renderLanguages();
			} else {
				ns.Languages.updateCount( app, parts.langbar );
			}
			ns.Fields.renderCanvas( app, parts.canvas );
			if ( 'structure' === level ) {
				ns.Inspector.render( app, parts.inspector );
			}
		};

		app.setLang = function ( code ) {
			app.lang = code;
			renderAll();
			live.say( texts.languageSwitched.replace( '%s', ns.Languages.nameOf( app, code ) ) );
		};

		app.addLanguage = function ( code ) {
			var snapshot = app.snapshot( 'lang-add' );

			if ( Core.addLanguage( app.schema, code, app.config.siteLanguage ) ) {
				app.lang = code;
				sync();
				renderAll();
				app.offerUndo( texts.languageAdded.replace( '%s', ns.Languages.nameOf( app, code ) ), snapshot );
			}
		};

		app.removeLanguage = function ( code ) {
			var count = Core.countTranslations( app.schema, code );
			var name = ns.Languages.nameOf( app, code );
			var text = count ? texts.removeLanguageAsk.replace( '%1$s', name ).replace( '%2$d', count ) : texts.removeLanguageNone.replace( '%s', name );

			ns.Dom.confirmDialog( rootEl, {
				text: text,
				cancelLabel: texts.cancel,
				confirmLabel: texts.removeLanguage,
				onConfirm: function () {
					var snapshot = app.snapshot( 'lang-add' );

					Core.removeLanguage( app.schema, code );
					sync();
					renderAll();
					app.offerUndo( texts.languageRemoved.replace( '%s', name ), snapshot );
				},
			} );
		};

		app.select = function ( id, openSheet ) {
			app.selectedId = id;
			renderFields();
			if ( openSheet && isNarrow() ) {
				setSheet( true );
			}
		};

		app.addField = function ( field ) {
			app.schema.fields.push( field );
			app.selectedId = field.id;
			app.change( 'structure' );
			live.say( texts.added.replace( '%s', field.label || field.id ) );
			if ( isNarrow() ) {
				setSheet( true );
			}
			var row = parts.canvas.querySelector( '[data-id="' + window.CSS.escape( field.id ) + '"]' );
			if ( row && row.scrollIntoView ) {
				row.scrollIntoView( { block: 'nearest' } );
			}
		};

		function indexOf( id ) {
			return app.schema.fields.map( function ( item ) {
				return item.id;
			} ).indexOf( id );
		}

		app.focus = function ( key ) {
			var target = rootEl.querySelector( '[data-focus="' + window.CSS.escape( key ) + '"]' );
			if ( target ) {
				target.focus();
			}
		};

		function removeField( id ) {
			var fields = app.schema.fields;
			var index = indexOf( id );
			var name = fields[ index ].label || id;
			var snapshot = app.snapshot( 'select:' + id );
			var neighbour;

			fields.splice( index, 1 );
			app.selectedId = app.selectedId === id ? null : app.selectedId;
			neighbour = fields[ index ] || fields[ index - 1 ];
			app.change( 'structure' );
			if ( neighbour ) {
				app.focus( 'select:' + neighbour.id );
			} else {
				parts.palette.querySelector( 'button' ).focus();
			}
			app.offerUndo( texts.removed.replace( '%s', name ), snapshot );
		}

		function confirmRemove( id ) {
			if ( ! Core.usedInNotifications( app.schema, id ) ) {
				removeField( id );
				return;
			}
			ns.Dom.confirmDialog( rootEl, {
				text: texts.usedInMail,
				cancelLabel: texts.cancel,
				confirmLabel: texts.removeAnyway,
				onConfirm: function () {
					removeField( id );
				},
			} );
		}

		app.rowAction = function ( action, id ) {
			var fields = app.schema.fields;
			var index = indexOf( id );
			var field = fields[ index ];
			var name = field.label || field.id;
			var copy;

			if ( 'trash' === action ) {
				confirmRemove( id );
				return;
			}
			if ( 'copy' === action ) {
				copy = Core.duplicate( field, fields, texts.copySuffix );
				fields.splice( index + 1, 0, copy );
				app.selectedId = copy.id;
				live.say( texts.duplicated.replace( '%s', name ) );
			} else {
				Core.move( fields, index, 'up' === action ? -1 : 1 );
				live.say( texts.moved.replace( '%1$s', name ).replace( '%2$d', fields.indexOf( field ) + 1 ) );
			}
			app.change( 'structure' );
		};

		/* ---------- Rückgängig ---------- */

		app.snapshot = function ( focusKey ) {
			return { schema: Core.clone( app.schema ), selectedId: app.selectedId, focus: focusKey };
		};

		function hideUndo() {
			clearTimeout( parts.undoTimer );
			parts.undo.hidden = true;
		}

		function armUndoTimer() {
			clearTimeout( parts.undoTimer );
			parts.undoTimer = setTimeout( hideUndo, UNDO_SECONDS * 1000 );
		}

		function undo( snapshot ) {
			app.schema = snapshot.schema;
			app.selectedId = snapshot.selectedId;
			hideUndo();
			sync();
			renderAll();
			live.say( texts.undone );
			app.focus( snapshot.focus );
		}

		/**
		 * Blendet «Rückgängig» ein. Es bleibt sichtbar, solange Maus oder Fokus darauf sind, sonst 10 Sekunden.
		 */
		app.offerUndo = function ( message, snapshot ) {
			ns.Dom.clear( parts.undo );
			parts.undo.appendChild( h( 'span', null, message ) );
			parts.undo.appendChild( h( 'button', {
				type: 'button',
				class: 'mlab-b-btn',
				'aria-keyshortcuts': 'Control+Z Meta+Z',
				onclick: function () {
					undo( snapshot );
				},
			}, texts.undo ) );
			parts.undo.hidden = false;
			live.say( message );
			parts.undoAction = function () {
				undo( snapshot );
			};
			armUndoTimer();
		};

		/* ---------- Layout ---------- */

		function isNarrow() {
			return rootEl.classList.contains( 'is-narrow' );
		}

		function setSheet( open ) {
			rootEl.classList.toggle( 'is-sheet-open', open );
			parts.scrim.hidden = ! open;
			parts.inspector.inert = isNarrow() && ! open;
			if ( open ) {
				var title = parts.inspector.querySelector( 'h2' );
				if ( title ) {
					title.focus();
				}
			}
		}

		app.closeSheet = function () {
			setSheet( false );
			var back = parts.canvas.querySelector( '[data-focus="select:' + window.CSS.escape( app.selectedId || '' ) + '"]' );
			if ( back ) {
				back.focus();
			}
		};

		function watchWidth() {
			var update = function () {
				var narrow = rootEl.clientWidth < NARROW_WIDTH;
				rootEl.classList.toggle( 'is-narrow', narrow );
				if ( ! narrow ) {
					rootEl.classList.remove( 'is-sheet-open' );
					parts.scrim.hidden = true;
				}
				parts.inspector.inert = narrow && ! rootEl.classList.contains( 'is-sheet-open' );
			};

			update();
			if ( root.ResizeObserver ) {
				new root.ResizeObserver( update ).observe( rootEl );
			}
		}

		/* ---------- Reiter und Experten-Ansicht ---------- */

		function showTab( name ) {
			app.tab = name;
			TABS.forEach( function ( tab ) {
				parts.views[ tab ].hidden = tab !== name;
				parts.tabButtons[ tab ].setAttribute( 'aria-pressed', tab === name ? 'true' : 'false' );
			} );
			if ( 'fields' !== name ) {
				setSheet( false );
			}
			if ( 'settings' === name ) {
				ns.Panels.renderSettings( app, parts.views.settings );
			}
			if ( 'preview' === name ) {
				parts.preview.refresh();
			}
		}

		function showError( message ) {
			parts.error.textContent = message || '';
			parts.error.hidden = ! message;
		}

		function setExpert( on ) {
			app.expert = on;
			textarea.hidden = ! on;
			parts.tabs.hidden = on;
			parts.langbar.hidden = on;
			parts.templates.hidden = on;
			TABS.forEach( function ( tab ) {
				parts.views[ tab ].hidden = on || tab !== app.tab;
			} );
			parts.expertButton.textContent = on ? texts.backToBuilder : texts.expert;
			parts.expertButton.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			if ( on ) {
				textarea.focus();
			}
		}

		function toggleExpert() {
			if ( ! app.expert ) {
				sync();
				showError( '' );
				setExpert( true );
				return;
			}
			try {
				app.schema = parseSchema( textarea.value, texts );
			} catch ( error ) {
				showError( error.message );
				textarea.focus();
				return;
			}
			showError( '' );
			if ( ! app.field() ) {
				app.selectedId = null;
			}
			setExpert( false );
			renderLanguages();
			renderFields();
			showTab( app.tab );
		}

		function loadTemplate( select ) {
			var template = config.templates.filter( function ( item ) {
				return item.key === select.value;
			} )[ 0 ];

			if ( template && template.schema && root.confirm( texts.confirmTemplate ) ) {
				app.schema = Core.clone( template.schema );
				app.selectedId = null;
				app.change( 'structure' );
				live.say( texts.templateLoaded );
			}
			select.value = '';
		}

		/* ---------- Aufbau ---------- */

		function build() {
			var tabLabels = { fields: texts.tabFields, settings: texts.tabSettings, preview: texts.tabPreview };
			var templateSelect = h( 'select', { class: 'mlab-b-in mlab-b-template', 'aria-label': texts.loadTemplate, onchange: function () {
				loadTemplate( this );
			} }, [ h( 'option', { value: '' }, texts.loadTemplate ) ].concat( config.templates.map( function ( item ) {
				return h( 'option', { value: item.key }, item.title );
			} ) ) );

			parts.tabButtons = {};
			parts.tabs = h( 'div', { class: 'mlab-b-seg', role: 'group', 'aria-label': texts.area }, TABS.map( function ( tab ) {
				parts.tabButtons[ tab ] = h( 'button', { type: 'button', 'data-tab': tab, onclick: function () {
					showTab( tab );
				} }, tabLabels[ tab ] );
				return parts.tabButtons[ tab ];
			} ) );
			parts.templates = templateSelect;
			parts.expertButton = h( 'button', { type: 'button', class: 'mlab-b-link', 'aria-pressed': 'false', onclick: toggleExpert }, texts.expert );
			parts.langbar = h( 'div', { class: 'mlab-b-langbar' } );
			parts.error = h( 'div', { class: 'mlab-b-alert', role: 'alert', hidden: true } );
			parts.palette = h( 'aside', { class: 'mlab-b-panel mlab-b-pal-panel', 'aria-label': texts.palette } );
			parts.canvas = h( 'div', { class: 'mlab-b-canvas-wrap' } );
			parts.inspector = h( 'aside', { class: 'mlab-b-panel mlab-b-insp', 'aria-labelledby': 'mlab-b-insp-title' } );
			parts.scrim = h( 'div', { class: 'mlab-b-scrim', hidden: true, onclick: function () {
				app.closeSheet();
			} } );
			parts.undo = h( 'div', { class: 'mlab-b-undo', role: 'status', 'aria-live': 'polite', hidden: true } );
			parts.undo.addEventListener( 'mouseenter', function () {
				clearTimeout( parts.undoTimer );
			} );
			parts.undo.addEventListener( 'focusin', function () {
				clearTimeout( parts.undoTimer );
			} );
			parts.undo.addEventListener( 'mouseleave', armUndoTimer );
			parts.undo.addEventListener( 'focusout', armUndoTimer );
			parts.preview = ns.Panels.createPreview( app );
			parts.views = {
				fields: h( 'section', { class: 'mlab-b-grid' }, [ parts.palette, h( 'div', { class: 'mlab-b-main', 'aria-label': texts.formArea, role: 'region' }, parts.canvas ), parts.inspector ] ),
				settings: h( 'section', { hidden: true } ),
				preview: h( 'section', { hidden: true }, parts.preview.element ),
			};

			rootEl.insertBefore( h( 'div', { class: 'mlab-b-bar' }, [ parts.tabs, h( 'span', { class: 'mlab-b-spacer' } ), templateSelect, parts.expertButton ] ), rootEl.firstChild );
			rootEl.insertBefore( parts.error, textarea );
			rootEl.insertBefore( parts.langbar, parts.error );
			TABS.forEach( function ( tab ) {
				rootEl.insertBefore( parts.views[ tab ], textarea );
			} );
			rootEl.appendChild( h( 'p', { class: 'mlab-b-muted mlab-b-small mlab-b-foot' }, texts.saveNote ) );
			rootEl.appendChild( parts.scrim );
			rootEl.appendChild( parts.undo );
			rootEl.appendChild( live.element );
		}

		function bindGlobal() {
			rootEl.addEventListener( 'keydown', function ( event ) {
				var tag = event.target.tagName;
				if ( 'Enter' === event.key && 'INPUT' === tag && 'checkbox' !== event.target.type ) {
					event.preventDefault(); // Enter soll das Formular nicht speichern.
				}
				if ( 'z' === event.key.toLowerCase() && ( event.ctrlKey || event.metaKey ) && ! parts.undo.hidden && 'INPUT' !== tag && 'TEXTAREA' !== tag ) {
					event.preventDefault();
					parts.undoAction();
				}
				if ( 'Escape' === event.key && rootEl.classList.contains( 'is-sheet-open' ) ) {
					app.closeSheet();
				}
			} );
			textarea.addEventListener( 'input', function () {
				app.dirty = true;
			} );
			root.addEventListener( 'beforeunload', function ( event ) {
				if ( app.dirty && ! app.submitting ) {
					event.preventDefault();
					event.returnValue = '';
				}
			} );
			if ( textarea.form ) {
				textarea.form.addEventListener( 'submit', function () {
					app.submitting = true;
				} );
			}
		}

		/**
		 * Startet mit dem gespeicherten Schema, dem Beispiel für neue Formulare oder, bei ungültigem JSON, der Experten-Ansicht.
		 */
		app.start = function () {
			var failure = '';

			build();
			parts.palette.setAttribute( 'data-ready', '1' );
			ns.Fields.renderPalette( app, parts.palette );
			watchWidth();
			bindGlobal();

			if ( config.isNew ) {
				app.schema = starterSchema( config );
			} else {
				try {
					app.schema = parseSchema( textarea.value, texts );
				} catch ( error ) {
					failure = error.message;
				}
			}
			renderLanguages();
			renderFields();
			showTab( 'fields' );
			if ( failure ) {
				showError( failure );
				setExpert( true );
			} else {
				textarea.value = JSON.stringify( app.schema, null, 2 );
				setExpert( false );
			}
			app.dirty = false;
			rootEl.classList.add( 'is-ready' );
			root.requestAnimationFrame( function () {
				rootEl.classList.add( 'is-settled' ); // Erst jetzt animiert das Blatt.
			} );
		};

		return app;
	}

	function init() {
		var config = root.mlabFormsBuilder;
		var rootEl = document.getElementById( 'mlab-builder' );
		var textarea = config && document.getElementById( config.fieldId );

		if ( rootEl && textarea ) {
			createApp( rootEl, textarea, config ).start();
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}( window ) );
