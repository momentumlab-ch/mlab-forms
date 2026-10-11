<?php
/**
 * Basis aller Feldtypen.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Fields;

use MLab_Forms\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Ein Feldtyp weiss, wie sein Feld geprüft, bereinigt, ausgegeben und angezeigt wird.
 *
 * Das Feld selbst ist immer ein Array aus dem Schema. Der Renderer ergänzt «html_id».
 */
abstract class FieldType {

	/**
	 * Name des Typs für Builder und Fehlermeldungen.
	 */
	abstract public function label(): string;

	/**
	 * Das Eingabeelement ohne Label, Hilfe und Fehler.
	 *
	 * @param array<string, mixed>       $field Feld.
	 * @param mixed                      $value Aktueller Wert.
	 * @param array<string, scalar|null> $aria  aria-Attribute für das Element.
	 */
	abstract protected function control( array $field, $value, array $aria ): string;

	/**
	 * Hat das Feld einen Wert, der übermittelt wird?
	 */
	public function is_input(): bool {
		return true;
	}

	/**
	 * Zusätzliche Prüfungen des Schemas für diesen Typ.
	 *
	 * @param array<string, mixed> $field Feld.
	 * @return list<string> Fehlermeldungen.
	 */
	public function schema_errors( array $field ): array {
		return array();
	}

	/**
	 * Bereinigt den übermittelten Wert.
	 *
	 * @param mixed                $raw   Rohwert aus der Anfrage.
	 * @param array<string, mixed> $field Feld.
	 * @return mixed
	 */
	public function sanitize( $raw, array $field ) {
		return is_scalar( $raw ) ? sanitize_text_field( (string) $raw ) : '';
	}

	/**
	 * Prüft den bereinigten Wert.
	 *
	 * @param mixed                $value Bereinigter Wert.
	 * @param array<string, mixed> $field Feld.
	 * @return string|null Fehlermeldung oder null.
	 */
	public function validate( $value, array $field ): ?string {
		if ( ! empty( $field['required'] ) && $this->is_empty( $value ) ) {
			return __( 'Bitte fülle dieses Feld aus.', 'mlab-forms' );
		}

		return null;
	}

	/**
	 * Wert als Text für E-Mails, Liste und Export.
	 *
	 * @param mixed                $value Gespeicherter Wert.
	 * @param array<string, mixed> $field Feld.
	 */
	public function format( $value, array $field ): string {
		return is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;
	}

	/**
	 * Ganzes Feld mit Label, Hilfetext und Fehler.
	 *
	 * @param array<string, mixed> $field Feld.
	 * @param mixed                $value Aktueller Wert.
	 * @param string|null          $error Fehlermeldung.
	 */
	public function render( array $field, $value, ?string $error ): string {
		[ $notes, $aria ] = $this->notes( $field, $error );

		return $this->wrap( $field, $this->label_html( $field ) . $this->control( $field, $value, $aria ) . $notes, (bool) $error );
	}

	/**
	 * Hilfetext und Fehlerbereich samt passenden aria-Attributen.
	 *
	 * @param array<string, mixed> $field Feld.
	 * @param string|null          $error Fehlermeldung.
	 * @return array{0: string, 1: array<string, scalar|null>} Escaptes HTML und aria-Attribute.
	 */
	protected function notes( array $field, ?string $error ): array {
		$id    = (string) $field['html_id'];
		$texts = array(
			'help'  => (string) ( $field['help'] ?? '' ),
			'error' => (string) $error,
		);
		$html  = '';
		$ids   = array();

		foreach ( $texts as $kind => $text ) {
			// Der Fehlerbereich steht immer im Markup, damit das Skript ihn füllen kann.
			if ( 'help' === $kind && '' === $text ) {
				continue;
			}
			$ids[] = $id . '-' . $kind;
			$html .= Html::tag(
				'p',
				array(
					'class' => 'mlab-form__' . $kind,
					'id'    => $id . '-' . $kind,
				),
				esc_html( $text )
			);
		}

		return array(
			$html,
			array(
				'aria-describedby' => implode( ' ', $ids ),
				'aria-invalid'     => $error ? 'true' : null,
			),
		);
	}

	/**
	 * Sternchen für Pflichtfelder (leer bei optionalen Feldern).
	 *
	 * @param array<string, mixed> $field Feld.
	 */
	protected function required_marker( array $field ): string {
		return empty( $field['required'] ) ? '' : ' <span class="mlab-form__required" aria-hidden="true">*</span>';
	}

	/**
	 * Umschliessendes div mit Breite und Typ.
	 *
	 * @param array<string, mixed> $field     Feld.
	 * @param string               $inner     Escapter Inhalt.
	 * @param bool                 $has_error Fehlerzustand.
	 */
	protected function wrap( array $field, string $inner, bool $has_error = false ): string {
		$classes = array(
			'mlab-form__field',
			'mlab-form__field--' . sanitize_html_class( (string) $field['type'] ),
			'mlab-form__field--' . sanitize_html_class( (string) ( $field['width'] ?? 'full' ) ),
		);
		if ( $has_error ) {
			$classes[] = 'mlab-form__field--invalid';
		}

		return Html::tag(
			'div',
			array(
				'class'      => implode( ' ', $classes ),
				'data-field' => (string) $field['id'],
				'data-label' => wp_strip_all_tags( (string) ( $field['label'] ?? '' ) ),
			),
			$inner
		);
	}

	/**
	 * Sichtbares Label mit Pflicht-Markierung.
	 *
	 * @param array<string, mixed> $field Feld.
	 * @param string               $tag   label oder legend.
	 */
	protected function label_html( array $field, string $tag = 'label' ): string {
		$text = esc_html( (string) ( $field['label'] ?? '' ) ) . $this->required_marker( $field );

		return Html::tag(
			$tag,
			array(
				'class' => 'mlab-form__label',
				'for'   => 'label' === $tag ? (string) $field['html_id'] : null,
			),
			$text
		);
	}

	/**
	 * Gemeinsame Attribute aller Eingabeelemente.
	 *
	 * @param array<string, mixed>       $field Feld.
	 * @param array<string, scalar|null> $aria  aria-Attribute.
	 * @return array<string, scalar|null>
	 */
	protected function base_attributes( array $field, array $aria ): array {
		return array(
			'id'           => (string) $field['html_id'],
			'name'         => 'mlab[' . $field['id'] . ']',
			'required'     => ! empty( $field['required'] ),
			'placeholder'  => isset( $field['placeholder'] ) ? (string) $field['placeholder'] : null,
			'autocomplete' => isset( $field['autocomplete'] ) ? (string) $field['autocomplete'] : null,
		) + $aria;
	}

	/**
	 * Gilt der Wert als leer?
	 *
	 * @param mixed $value Wert.
	 */
	protected function is_empty( $value ): bool {
		return null === $value || '' === $value || array() === $value || false === $value;
	}
}
