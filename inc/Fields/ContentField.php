<?php
/**
 * Reine Anzeige: Zwischentitel und HTML.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Fields;

use MLab_Forms\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Feldtypen heading und html. Sie haben keinen Wert.
 */
class ContentField extends FieldType {

	/**
	 * Konstruktor.
	 *
	 * @param string $type  heading oder html.
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
	 * Kein Wert.
	 */
	public function is_input(): bool {
		return false;
	}

	/**
	 * Titel oder gefiltertes HTML.
	 *
	 * @param array<string, mixed> $field Feld.
	 * @param mixed                $value Ohne Bedeutung.
	 * @param string|null          $error Ohne Bedeutung.
	 */
	public function render( array $field, $value, ?string $error ): string {
		return $this->wrap( $field, $this->control( $field, $value, array() ) );
	}

	/**
	 * Inhalt.
	 *
	 * @param array<string, mixed>       $field Feld.
	 * @param mixed                      $value Ohne Bedeutung.
	 * @param array<string, scalar|null> $aria  Ohne Bedeutung.
	 */
	protected function control( array $field, $value, array $aria ): string {
		if ( 'heading' === $this->type ) {
			return Html::tag( 'h3', array( 'class' => 'mlab-form__heading' ), esc_html( (string) ( $field['label'] ?? '' ) ) );
		}

		return wp_kses_post( (string) ( $field['content'] ?? '' ) );
	}
}
