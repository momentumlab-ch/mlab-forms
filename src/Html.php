<?php
/**
 * Kleine HTML-Helfer.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Baut escapte HTML-Fragmente.
 */
final class Html {

	/**
	 * Escapte Attribute. true schreibt nur den Namen, false und null lassen das Attribut weg.
	 *
	 * @param array<string, scalar|null> $attrs Name => Wert.
	 */
	public static function attributes( array $attrs ): string {
		$parts = array();
		foreach ( $attrs as $name => $value ) {
			if ( null === $value || false === $value ) {
				continue;
			}
			$parts[] = true === $value
				? esc_attr( $name )
				: sprintf( '%s="%s"', esc_attr( $name ), esc_attr( (string) $value ) );
		}

		return implode( ' ', $parts );
	}

	/**
	 * Ein Element mit Attributen und bereits escaptem Inhalt.
	 *
	 * @param string                     $tag   Tag-Name.
	 * @param array<string, scalar|null> $attrs Attribute.
	 * @param string|null                $inner Escapter Inhalt; null für leere Elemente wie input.
	 */
	public static function tag( string $tag, array $attrs, ?string $inner = '' ): string {
		$open = '<' . $tag . ( $attrs ? ' ' . self::attributes( $attrs ) : '' ) . '>';

		return null === $inner ? $open : $open . $inner . '</' . $tag . '>';
	}
}
