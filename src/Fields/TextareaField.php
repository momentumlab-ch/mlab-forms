<?php
/**
 * Mehrzeiliges Textfeld.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Fields;

use MLab_Forms\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Feldtyp textarea.
 */
class TextareaField extends FieldType {

	private const MAX_LENGTH = 5000;

	/**
	 * Name des Typs.
	 */
	public function label(): string {
		return __( 'Mehrzeiliger Text', 'mlab-forms' );
	}

	/**
	 * Behält Zeilenumbrüche.
	 *
	 * @param mixed                $raw   Rohwert.
	 * @param array<string, mixed> $field Feld.
	 * @return string
	 */
	public function sanitize( $raw, array $field ) {
		return mb_substr( sanitize_textarea_field( is_scalar( $raw ) ? (string) $raw : '' ), 0, self::MAX_LENGTH );
	}

	/**
	 * Das textarea-Element.
	 *
	 * @param array<string, mixed>       $field Feld.
	 * @param mixed                      $value Wert.
	 * @param array<string, scalar|null> $aria  aria-Attribute.
	 */
	protected function control( array $field, $value, array $aria ): string {
		$attrs = array(
			'rows'      => (int) ( $field['rows'] ?? 4 ),
			'maxlength' => self::MAX_LENGTH,
		) + $this->base_attributes( $field, $aria );

		return Html::tag( 'textarea', $attrs, esc_textarea( (string) $value ) );
	}
}
