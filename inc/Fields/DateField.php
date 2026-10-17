<?php
/**
 * Datumsfeld.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Fields;

use MLab_Forms\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Feldtyp date. min und max sind ein Datum (JJJJ-MM-TT) oder «today».
 */
class DateField extends FieldType {

	/**
	 * Name des Typs.
	 */
	public function label(): string {
		return __( 'Datum', 'mlab-forms' );
	}

	/**
	 * Die Werte min und max müssen auflösbar sein.
	 *
	 * @param array<string, mixed> $field Feld.
	 * @return list<string>
	 */
	public function schema_errors( array $field ): array {
		foreach ( array( 'min', 'max' ) as $key ) {
			if ( isset( $field[ $key ] ) && null === self::resolve( (string) $field[ $key ] ) ) {
				/* translators: %s: min oder max */
				return array( sprintf( __( '«%s» muss ein Datum (JJJJ-MM-TT) oder «today» sein.', 'mlab-forms' ), $key ) );
			}
		}

		return array();
	}

	/**
	 * Nur gültige Daten im Format JJJJ-MM-TT.
	 *
	 * @param mixed                $raw   Rohwert.
	 * @param array<string, mixed> $field Feld.
	 * @return string
	 */
	public function sanitize( $raw, array $field ) {
		$value = is_scalar( $raw ) ? trim( (string) $raw ) : '';

		return null !== self::resolve( $value ) && 'today' !== $value ? $value : '';
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
		$min = isset( $field['min'] ) ? self::resolve( (string) $field['min'] ) : null;
		$max = isset( $field['max'] ) ? self::resolve( (string) $field['max'] ) : null;
		if ( $min && $value < $min ) {
			return __( 'Bitte wähle ein späteres Datum.', 'mlab-forms' );
		}
		if ( $max && $value > $max ) {
			return __( 'Bitte wähle ein früheres Datum.', 'mlab-forms' );
		}

		return null;
	}

	/**
	 * Datum im Schweizer Format.
	 *
	 * @param mixed                $value Wert.
	 * @param array<string, mixed> $field Feld.
	 */
	public function format( $value, array $field ): string {
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $value );

		return $date ? $date->format( 'd.m.Y' ) : '';
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
			'type'  => 'date',
			'value' => (string) $value,
			'min'   => isset( $field['min'] ) ? self::resolve( (string) $field['min'] ) : null,
			'max'   => isset( $field['max'] ) ? self::resolve( (string) $field['max'] ) : null,
		) + $this->base_attributes( $field, $aria );

		return Html::tag( 'input', $attrs, null );
	}

	/**
	 * «today» als heutiges Datum der Website, sonst ein gültiges JJJJ-MM-TT.
	 *
	 * @param string $value Wert.
	 */
	private static function resolve( string $value ): ?string {
		if ( 'today' === $value ) {
			return current_time( 'Y-m-d' );
		}
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );

		return $date && $date->format( 'Y-m-d' ) === $value ? $value : null;
	}
}
