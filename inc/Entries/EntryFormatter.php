<?php
/**
 * Lesbare Werte einer Anfrage.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Entries;

use MLab_Forms\Forms\Form;
use MLab_Forms\Forms\FormRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Wandelt gespeicherte Werte mithilfe des Formular-Schemas in «Label => Text» um.
 * Felder, die es im Schema nicht mehr gibt, erscheinen mit ihrer ID.
 */
final class EntryFormatter {

	/**
	 * Zwischenspeicher der Formulare pro Anfrage.
	 *
	 * @var array<int, Form|null>
	 */
	private static array $forms = array();

	/**
	 * Label => Text für alle gespeicherten Werte.
	 *
	 * @param array<string, mixed> $entry Anfrage.
	 * @return array<string, string>
	 */
	public static function rows( array $entry ): array {
		$form = self::form( (int) $entry['form_id'] );
		$rows = array();

		foreach ( (array) $entry['data'] as $id => $value ) {
			$field = $form ? $form->field( (string) $id ) : null;
			$label = $field ? wp_strip_all_tags( (string) ( $field['label'] ?? $id ) ) : (string) $id;
			$text  = $field ? Form::type_of( $field )->format( $value, $field ) : self::text( $value );

			$rows[ self::unique_label( $rows, $label ) ] = $text;
		}

		return $rows;
	}

	/**
	 * Wert ohne Formulardefinition als Text.
	 *
	 * @param mixed $value Gespeicherter Wert.
	 */
	public static function text( $value ): string {
		return is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;
	}

	/**
	 * Formular inklusive Entwürfe, einmal pro Anfrage geladen.
	 *
	 * @param int $form_id Formular.
	 */
	public static function form( int $form_id ): ?Form {
		if ( ! array_key_exists( $form_id, self::$forms ) ) {
			self::$forms[ $form_id ] = FormRepository::find( $form_id, false );
		}

		return self::$forms[ $form_id ];
	}

	/**
	 * Hängt bei doppelten Labels eine Nummer an.
	 *
	 * @param array<string, string> $rows  Bisherige Zeilen.
	 * @param string                $label Label.
	 */
	private static function unique_label( array $rows, string $label ): string {
		$unique = $label;
		$i      = 2;
		while ( array_key_exists( $unique, $rows ) ) {
			$unique = $label . ' (' . ( $i++ ) . ')';
		}

		return $unique;
	}
}
