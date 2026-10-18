/**
 * MomentumLab Forms, Builder: kleine DOM-Helfer. Nutzerdaten gelangen nur als Text oder Attributwert ins DOM,
 * nie als HTML.
 */
( function ( root ) {
	'use strict';

	var SVG_NS = 'http://www.w3.org/2000/svg';
	var ICONS = {
		up: 'M12 19V5M5 12l7-7 7 7',
		down: 'M12 5v14M19 12l-7 7-7-7',
		copy: 'M9 9h11v11H9zM5 15V5a1 1 0 0 1 1-1h10',
		trash: 'M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3',
		star: 'M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z',
		plus: 'M12 5v14M5 12h14',
	};
	var counter = 0;

	function uid( prefix ) {
		counter += 1;
		return ( prefix || 'mlab-b' ) + '-' + counter;
	}

	function append( parent, child ) {
		if ( null === child || undefined === child || false === child ) {
			return;
		}
		if ( Array.isArray( child ) ) {
			child.forEach( function ( item ) {
				append( parent, item );
			} );
		} else if ( 'object' === typeof child ) {
			parent.appendChild( child );
		} else {
			parent.appendChild( document.createTextNode( String( child ) ) );
		}
	}

	function setAttr( el, name, value ) {
		if ( null === value || undefined === value || false === value ) {
			return;
		}
		if ( 'function' === typeof value && 0 === name.indexOf( 'on' ) ) {
			el.addEventListener( name.slice( 2 ), value );
		} else if ( 'value' === name ) {
			el.value = value;
		} else if ( 'checked' === name ) {
			el.checked = true;
		} else {
			el.setAttribute( name, true === value ? '' : String( value ) );
		}
	}

	/**
	 * Erzeugt ein Element. attrs: Attribute oder on<Ereignis>-Funktionen; children: Text, Knoten oder Listen.
	 */
	function h( tag, attrs, children ) {
		var el = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( name ) {
			setAttr( el, name, attrs[ name ] );
		} );
		append( el, children );
		return el;
	}

	function icon( name ) {
		var svg = document.createElementNS( SVG_NS, 'svg' );
		var path = document.createElementNS( SVG_NS, 'path' );

		svg.setAttribute( 'viewBox', '0 0 24 24' );
		svg.setAttribute( 'width', '20' );
		svg.setAttribute( 'height', '20' );
		svg.setAttribute( 'aria-hidden', 'true' );
		svg.setAttribute( 'focusable', 'false' );
		svg.setAttribute( 'class', 'mlab-b-icon' );
		path.setAttribute( 'd', ICONS[ name ] );
		path.setAttribute( 'fill', 'star' === name ? 'currentColor' : 'none' );
		path.setAttribute( 'stroke', 'currentColor' );
		path.setAttribute( 'stroke-width', '2' );
		path.setAttribute( 'stroke-linecap', 'round' );
		path.setAttribute( 'stroke-linejoin', 'round' );
		svg.appendChild( path );
		return svg;
	}

	function clear( el ) {
		while ( el.firstChild ) {
			el.removeChild( el.firstChild );
		}
	}

	/**
	 * Unsichtbarer Bereich, der Änderungen Screenreadern vorliest.
	 */
	function liveRegion() {
		var region = h( 'div', { class: 'mlab-b-sr', role: 'status', 'aria-live': 'polite' } );
		var timer;

		return {
			element: region,
			say: function ( message ) {
				region.textContent = '';
				clearTimeout( timer );
				timer = setTimeout( function () {
					region.textContent = message;
				}, 30 );
			},
		};
	}

	/**
	 * Merkt sich das fokussierte Element (data-focus) und gibt es nach dem Neuzeichnen zurück.
	 * Fehlt es, probiert fallbacks der Reihe nach.
	 */
	function keepFocus( container, redraw, fallbacks ) {
		var active = document.activeElement;
		var key = active && container.contains( active ) ? active.getAttribute( 'data-focus' ) : null;
		var target = null;

		redraw();
		if ( ! key ) {
			return;
		}
		[ key ].concat( 'function' === typeof fallbacks ? fallbacks( key ) : ( fallbacks || [] ) ).some( function ( candidate ) {
			target = container.querySelector( '[data-focus="' + window.CSS.escape( candidate ) + '"]:not([disabled])' );
			return !! target;
		} );
		if ( target ) {
			target.focus();
		}
	}

	var api = { h: h, icon: icon, clear: clear, uid: uid, liveRegion: liveRegion, keepFocus: keepFocus };

	root.MLabBuilder = root.MLabBuilder || {};
	root.MLabBuilder.Dom = api;
}( window ) );
