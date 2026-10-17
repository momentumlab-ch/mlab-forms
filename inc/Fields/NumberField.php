<?php
/**
 * Zahlenfeld.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Fields;

use MLab_Forms\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Feldtyp number mit optionalem min und max.
 */
class NumberField extends FieldType {

	/**
	 * Name des Typs.
	 */
	public function label(): string {
		return __( 'Zahl', 'mlab-forms' );
	}

	/**
	 * Die Werte min und max müssen Zahlen sein.
	 *
	 * @param array<string, mixed> $field Feld.
	 * @return list<string>
	 */
	public function schema_errors( array $field ): array {
		foreach ( array( 'min', 'max' ) as $key ) {
			if ( isset( $field[ $key ] ) && ! is_numeric( $field[ $key ] ) ) {
				/* translators: %s: min oder max */
				return array( sprintf( __( '«%s» muss eine Zahl sein.', 'mlab-forms' ), $key ) );
			}
		}

		return array();
	}

	/**
	 * Leere Eingabe bleibt leer, sonst eine Zahl.
	 *
	 * @param mixed                $raw   Rohwert.
	 * @param array<string, mixed> $field Feld.
	 * @return string
	 */
	public function sanitize( $raw, array $field ) {
		$value = is_scalar( $raw ) ? str_replace( ',', '.', trim( (string) $raw ) ) : '';

		return is_numeric( $value ) ? (string) ( 0 + $value ) : '';
	}

	/**
	 * Pflicht und Bereich.
	 *
	 * @param mixed                $value Wert.
	 * @param array<string, mixed> $field Feld.
	 */
	public function validate( $value, array $field ): ?string {
		$error = parent::validate( $value, $field );
		if ( $error || '' === $value ) {
			return $error;
		}
		if ( isset( $field['min'] ) && (float) $value < (float) $field['min'] ) {
			/* translators: %s: kleinster Wert */
			return sprintf( __( 'Der Wert muss mindestens %s sein.', 'mlab-forms' ), $field['min'] );
		}
		if ( isset( $field['max'] ) && (float) $value > (float) $field['max'] ) {
			/* translators: %s: grösster Wert */
			return sprintf( __( 'Der Wert darf höchstens %s sein.', 'mlab-forms' ), $field['max'] );
		}

		return null;
	}

	/**
	 * Das input-Element.
	 *
	 * @param array<string, mixed>       $field Feld.
	 * @param mixed                      $value Wert.
	 * @param array<string, scalar|null> $aria  aria-Attribute.
	 */
	protected function control( array $field, $value, array $aria ): string {
		$attrs = array(
			'type'      => 'number',
			'value'     => (string) $value,
			'min'       => $field['min'] ?? null,
			'max'       => $field['max'] ?? null,
			'step'      => $field['step'] ?? null,
			'inputmode' => 'numeric',
		) + $this->base_attributes( $field, $aria );

		return Html::tag( 'input', $attrs, null );
	}
}
