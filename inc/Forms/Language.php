<?php
/**
 * Sprache der Seite und des Formulars.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Bestimmt die Sprache, in der ein Formular erscheint.
 *
 * Quelle ist die Sprache der Seite: WPML, Polylang oder die Sprache der Website. Ein Formular
 * zeigt diese Sprache nur, wenn es sie pflegt, sonst seine Standardsprache (die erste in «languages»).
 * Es gibt keine Abhängigkeit von WPML oder Polylang; ihre Funktionen werden nur genutzt, wenn sie existieren.
 */
final class Language {

	public const PATTERN = '/^[a-z]{2,3}$/D';

	/**
	 * Ist der Wert ein Sprachcode wie «de» oder «gsw»?
	 *
	 * @param mixed $value Wert.
	 */
	public static function is_code( $value ): bool {
		return is_string( $value ) && 1 === preg_match( self::PATTERN, $value );
	}

	/**
	 * Macht aus «de_CH», «fr-FR» oder «EN» den Code «de», «fr», «en». Leer, wenn es keiner ist.
	 *
	 * @param mixed $raw Wert.
	 */
	public static function normalize( $raw ): string {
		if ( ! is_string( $raw ) ) {
			return '';
		}
		$code = strtolower( (string) preg_split( '/[-_]/', trim( $raw ) )[0] );

		return self::is_code( $code ) ? $code : '';
	}

	/**
	 * Sprache der aktuellen Seite als Code, leer wenn unbekannt.
	 *
	 * Reihenfolge: WPML, Polylang, Sprache der Website.
	 */
	public static function page(): string {
		// Filter von WPML, nicht von diesem Plugin.
		$code = self::normalize( apply_filters( 'wpml_current_language', null ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		if ( '' === $code && function_exists( 'pll_current_language' ) ) {
			$code = self::normalize( pll_current_language() );
		}

		return '' !== $code ? $code : self::normalize( determine_locale() );
	}

	/**
	 * Sprache, in der das Formular jetzt erscheint. Leer, wenn es nur eine Sprache pflegt.
	 *
	 * @param list<string> $languages Sprachen des Formulars, die erste ist die Standardsprache.
	 */
	public static function current( array $languages ): string {
		if ( count( $languages ) < 2 ) {
			return '';
		}

		/**
		 * Sprache der Seite für Formulare überschreiben.
		 *
		 * @param string       $language  Code der Seite, z. B. «fr»; leer, wenn unbekannt.
		 * @param list<string> $languages Sprachen des Formulars.
		 */
		$page = self::normalize( apply_filters( 'mlab_forms_current_language', self::page(), $languages ) );

		return self::resolve( $page, $languages );
	}

	/**
	 * Sprache einer Absendung: der Wert aus dem Formular, sofern das Formular sie pflegt, sonst die Standardsprache.
	 *
	 * @param mixed        $raw       Wert aus der Anfrage.
	 * @param list<string> $languages Sprachen des Formulars.
	 */
	public static function from_request( $raw, array $languages ): string {
		return count( $languages ) < 2 ? '' : self::resolve( self::normalize( $raw ), $languages );
	}

	/**
	 * Der Code, wenn das Formular ihn pflegt, sonst die Standardsprache.
	 *
	 * @param string       $code      Code oder leer.
	 * @param list<string> $languages Sprachen des Formulars.
	 */
	public static function resolve( string $code, array $languages ): string {
		return in_array( $code, $languages, true ) ? $code : (string) ( $languages[0] ?? '' );
	}
}
