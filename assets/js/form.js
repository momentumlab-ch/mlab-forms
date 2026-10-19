/* MomentumLab Forms: Absenden per fetch, Fehleranzeige und Vorauswahl. Ohne JS funktioniert das Formular als normaler POST. */
( function () {
	'use strict';

	function fieldId( name ) {
		var match = /^mlab\[([a-z0-9_]+)\]/.exec( name || '' );
		return match ? match[ 1 ] : null;
	}

	function fieldBox( form, id ) {
		return form.querySelector( '[data-field="' + id + '"]' );
	}

	function focusTarget( box ) {
		return box && box.querySelector( 'input:not([type=hidden]), select, textarea' );
	}

	function clearErrors( form ) {
		form.querySelectorAll( '.mlab-form__field--invalid' ).forEach( function ( box ) {
			box.classList.remove( 'mlab-form__field--invalid' );
			box.querySelectorAll( '[aria-invalid]' ).forEach( function ( el ) {
				el.removeAttribute( 'aria-invalid' );
			} );
			var error = box.querySelector( '.mlab-form__error' );
			if ( error ) {
				error.textContent = '';
			}
		} );
		var summary = form.querySelector( '[data-mlab=summary]' );
		summary.hidden = true;
		summary.textContent = '';
	}

	function showErrors( form, errors, message ) {
		var summary = form.querySelector( '[data-mlab=summary]' );
		var list = document.createElement( 'ul' );
		var first = null;

		Object.keys( errors ).forEach( function ( id ) {
			var box = fieldBox( form, id );
			if ( ! box ) {
				return;
			}
			box.classList.add( 'mlab-form__field--invalid' );
			box.querySelector( '.mlab-form__error' ).textContent = errors[ id ];
			var control = focusTarget( box );
			if ( control ) {
				control.setAttribute( 'aria-invalid', 'true' );
				first = first || control;
				var item = document.createElement( 'li' );
				var link = document.createElement( 'a' );
				var label = box.getAttribute( 'data-label' );
				link.href = '#' + control.id;
				link.textContent = ( label ? label + ': ' : '' ) + errors[ id ];
				item.appendChild( link );
				list.appendChild( item );
			}
		} );

		summary.textContent = message;
		summary.appendChild( list );
		summary.hidden = false;
		( first || summary ).focus();
	}

	function message( el, messages ) {
		if ( el.validity.valueMissing && el.type === 'file' ) {
			return messages.fileMissing || messages.valueMissing;
		}
		if ( el.validity.valueMissing && el.type === 'checkbox' ) {
			return el.closest( '.mlab-form__field--consent' ) ? messages.consent : messages.checkbox;
		}
		if ( el.validity.valueMissing ) {
			return messages.valueMissing;
		}
		if ( el.validity.typeMismatch ) {
			return messages.typeMismatch;
		}
		return el.validity.patternMismatch ? messages.patternMismatch : messages.invalid;
	}

	/* Dieselben Regeln wie Fields\Formats auf dem Server. */
	var formats = {
		tel: function ( value ) {
			return /^[0-9+\-\/() ]+$/.test( value ) && ( value.match( /[0-9]/g ) || [] ).length >= 6;
		},
		postcode_ch: function ( value ) {
			return /^[0-9]{4}$/.test( value );
		}
	};

	/* Grösse und Endung wie Fields\FileField; den Inhalt prüft nur der Server. */
	function fileMessage( el ) {
		var file = el.files && el.files[ 0 ];
		if ( ! file ) {
			return '';
		}
		var max = parseInt( el.getAttribute( 'data-max-size' ), 10 );
		if ( max && file.size > max ) {
			return el.getAttribute( 'data-size-message' ) || '';
		}
		var accept = ( el.getAttribute( 'accept' ) || '' ).toLowerCase().split( ',' ).filter( Boolean );
		var name = file.name.toLowerCase();
		var ok = ! accept.length || accept.some( function ( ext ) {
			return name.slice( -ext.length ) === ext;
		} );
		return ok ? '' : el.getAttribute( 'data-type-message' ) || '';
	}

	function formatMessage( el, messages ) {
		if ( el.type === 'file' ) {
			return fileMessage( el );
		}
		var format = el.getAttribute( 'data-format' );
		var value = el.value.trim();
		return format && formats[ format ] && value !== '' && ! formats[ format ]( value ) ? messages[ format ] : '';
	}

	function clientErrors( form ) {
		var errors = {};
		var messages = JSON.parse( form.getAttribute( 'data-messages' ) || '{}' );
		Array.prototype.forEach.call( form.elements, function ( el ) {
			var id = fieldId( el.name );
			if ( ! id || ! el.willValidate || errors[ id ] ) {
				return;
			}
			var text = el.validity.valid ? formatMessage( el, messages ) : message( el, messages );
			if ( text ) {
				errors[ id ] = text;
			}
		} );
		return errors;
	}

	function formMessage( form, key ) {
		return JSON.parse( form.getAttribute( 'data-messages' ) || '{}' )[ key ] || '';
	}

	var captchaFields = { turnstile: 'cf-turnstile-response', hcaptcha: 'h-captcha-response' };

	/* reCAPTCHA v3 holt das Token beim Absenden. Turnstile und hCaptcha tragen es selbst ins Formular ein. */
	function captchaToken( form ) {
		var provider = form.getAttribute( 'data-captcha' );
		if ( captchaFields[ provider ] ) {
			var field = form.querySelector( '[name="' + captchaFields[ provider ] + '"]' );
			return field && field.value ? Promise.resolve() : Promise.reject( new Error( 'captchaPending' ) );
		}
		if ( provider !== 'recaptcha' ) {
			return Promise.resolve();
		}
		var api = window.grecaptcha;
		if ( ! api || typeof api.ready !== 'function' ) {
			return Promise.reject( new Error( 'captcha' ) );
		}
		return new Promise( function ( resolve, reject ) {
			api.ready( function () {
				api.execute( form.getAttribute( 'data-captcha-key' ), { action: 'submit' } ).then( function ( token ) {
					form.querySelector( '[name="g-recaptcha-response"]' ).value = token;
					resolve();
				}, reject );
			} );
		} );
	}

	/* Ein Token gilt nur einmal: nach einer Antwort ohne Erfolg ein neues anfordern. */
	function resetCaptcha( form ) {
		var provider = form.getAttribute( 'data-captcha' );
		if ( provider === 'turnstile' && window.turnstile ) {
			window.turnstile.reset( form.querySelector( '.cf-turnstile' ) );
		} else if ( provider === 'hcaptcha' && window.hcaptcha ) {
			window.hcaptcha.reset();
		}
	}

	function showSuccess( form, data ) {
		var tracking = data.tracking || {};
		if ( tracking.event && typeof window.gtag === 'function' ) {
			window.gtag( 'event', tracking.event, tracking.params || {} );
		}
		if ( data.redirect ) {
			window.location.assign( data.redirect );
			return;
		}
		var box = document.createElement( 'div' );
		box.className = 'mlab-form__success';
		box.setAttribute( 'role', 'status' );
		box.setAttribute( 'tabindex', '-1' );
		box.textContent = data.message;
		form.replaceWith( box );
		box.focus();
	}

	function submit( form ) {
		clearErrors( form );
		var errors = clientErrors( form );
		var summary = form.querySelector( '[data-mlab=summary]' );
		if ( Object.keys( errors ).length ) {
			showErrors( form, errors, summary.getAttribute( 'data-message' ) || '' );
			return;
		}

		var button = form.querySelector( '.mlab-form__submit' );
		button.disabled = true;

		captchaToken( form ).then( function () {
			send( form, button );
		}, function ( error ) {
			button.disabled = false;
			showErrors( form, {}, formMessage( form, error && error.message === 'captchaPending' ? 'captchaPending' : 'captcha' ) );
		} );
	}

	function send( form, button ) {
		window.fetch( form.getAttribute( 'data-rest' ), { method: 'POST', body: new window.FormData( form ), credentials: 'same-origin' } )
			.then( function ( response ) {
				// Antwortet der Server ohne JSON, ist die Anfrage evtl. schon gespeichert: nicht nochmals senden.
				return response.json().then( function ( data ) {
					button.disabled = false;
					if ( data.status === 'success' ) {
						showSuccess( form, data );
					} else {
						resetCaptcha( form );
						showErrors( form, data.errors || {}, data.message || '' );
					}
				}, function () {
					button.disabled = false;
					showErrors( form, {}, formMessage( form, 'server' ) );
				} );
			}, function () {
				// Keine Verbindung zur REST-API: als normaler POST versuchen.
				HTMLFormElement.prototype.submit.call( form );
			} );
	}

	function setValue( form, id, value ) {
		var box = fieldBox( form, id );
		var control = focusTarget( box );
		if ( ! control || ! value ) {
			return;
		}
		if ( control.tagName === 'SELECT' && ! Array.prototype.some.call( control.options, function ( o ) {
			return o.value === value;
		} ) ) {
			control.add( new window.Option( value, value ) );
		}
		control.value = value;
	}

	function setupPrefill( form ) {
		var config = [];
		try {
			config = JSON.parse( form.getAttribute( 'data-prefill' ) || '[]' );
		} catch ( e ) {}

		config.forEach( function ( rule ) {
			document.addEventListener( 'click', function ( event ) {
				var link = event.target.closest && event.target.closest( '[' + rule.attribute + ']' );
				if ( link ) {
					setValue( form, rule.field, link.getAttribute( rule.attribute ) );
				}
			} );
		} );
	}

	function init( form ) {
		form.noValidate = true;
		form.querySelector( '[name=mlab_referrer]' ).value = document.referrer;
		form.querySelector( '[name=mlab_source]' ).value = window.location.href.split( '#' )[ 0 ];
		setupPrefill( form );
		var summary = form.querySelector( '[data-mlab=summary]' );
		if ( ! summary.hidden ) {
			summary.focus();
		}
		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			submit( form );
		} );
	}

	document.querySelectorAll( 'form[data-mlab-form]' ).forEach( init );
}() );
