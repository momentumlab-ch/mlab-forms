<?php
/**
 * Einzelne Checkbox und Einwilligung.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Fields;

use MLab_Forms\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Feldtypen checkbox und consent. Der Wert ist «1» oder leer.
 */
class CheckboxField extends FieldType {

	/**
	 * Konstruktor.
	 *
	 * @param string $type  checkbox oder consent.
	 * @param string $label Name des Typs.
	 */
	public function __construct( private string $type, private string $label ) {
	}

	/**
	 * Name des Typs.
	 */
	public function label(): string {
		return $this->label;
	}

	/**
	 * «1» bei Haken, sonst leer.
	 *
	 * @param mixed                $raw   Rohwert.
	 * @param array<string, mixed> $field Feld.
	 * @return string
	 */
	public function sanitize( $raw, array $field ) {
		return empty( $raw ) ? '' : '1';
	}

	/**
	 * Eigene Meldung für die Einwilligung.
	 *
	 * @param mixed                $value Wert.
	 * @param array<string, mixed> $field Feld.
	 */
	public function validate( $value, array $field ): ?string {
		if ( ! empty( $field['required'] ) && '1' !== $value ) {
			return 'consent' === $this->type
				? __( 'Bitte bestätige die Einwilligung.', 'mlab-forms' )
				: __( 'Bitte setze diesen Haken.', 'mlab-forms' );
		}

		return null;
	}

	/**
	 * Ja oder Nein.
	 *
	 * @param mixed                $value Wert.
	 * @param array<string, mixed> $field Feld.
	 */
	public function format( $value, array $field ): string {
		return '1' === $value ? __( 'Ja', 'mlab-forms' ) : __( 'Nein', 'mlab-forms' );
	}

	/**
	 * Checkbox mit dem Label rechts davon.
	 *
	 * @param array<string, mixed> $field Feld.
	 * @param mixed                $value Wert.
	 * @param string|null          $error Fehler.
	 */
	public function render( array $field, $value, ?string $error ): string {
		[ $notes, $aria ] = $this->notes( $field, $error );

		$label = Html::tag( 'label', array( 'class' => 'mlab-form__choice' ), $this->control( $field, $value, $aria ) . ' ' . $this->label_text( $field ) );

		return $this->wrap( $field, $label . $notes, (bool) $error );
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
			'type'    => 'checkbox',
			'value'   => '1',
			'checked' => '1' === $value,
		) + $this->base_attributes( $field, $aria );

		return Html::tag( 'input', $attrs, null );
	}

	/**
	 * Labeltext, bei der Einwilligung mit Link zur Datenschutzerklärung.
	 *
	 * @param array<string, mixed> $field Feld (mit aufgelöstem «link»).
	 */
	private function label_text( array $field ): string {
		$text = esc_html( (string) ( $field['label'] ?? '' ) );
		if ( ! empty( $field['link'] ) ) {
			$text .= ' ' . Html::tag(
				'a',
				array(
					'href'   => esc_url( (string) $field['link'] ),
					'target' => '_blank',
					'rel'    => 'noopener',
				),
				esc_html__( 'Datenschutzerklärung', 'mlab-forms' )
			);
		}

		return '<span>' . $text . $this->required_marker( $field ) . '</span>';
	}
}
