<?php
/**
 * Einzeilige Textfelder: text, email, tel, hidden.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Fields;

use MLab_Forms\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Einzeiliges Feld. Der HTML-Typ entspricht dem Schema-Typ.
 */
class TextField extends FieldType {

	private const MAX_LENGTH = 500;

	/**
	 * Konstruktor.
	 *
	 * @param string $type  text, email, tel oder hidden.
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
	 * Prüft ein gültiges Muster und ein bekanntes Format.
	 *
	 * @param array<string, mixed> $field Feld.
	 * @return list<string>
	 */
	public function schema_errors( array $field ): array {
		if ( isset( $field['format'] ) && ! in_array( $field['format'], Formats::known(), true ) ) {
			return array( __( '«format» muss «postcode_ch» oder «tel» sein.', 'mlab-forms' ) );
		}
		if ( isset( $field['pattern'] ) && false === @preg_match( self::pattern_regex( (string) $field['pattern'] ), '' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- ungültige Muster sollen nur gemeldet werden.
			return array( __( '«pattern» ist kein gültiger regulärer Ausdruck.', 'mlab-forms' ) );
		}

		return array();
	}

	/**
	 * Bereinigt je nach Typ.
	 *
	 * @param mixed                $raw   Rohwert.
	 * @param array<string, mixed> $field Feld.
	 * @return string
	 */
	public function sanitize( $raw, array $field ) {
		// E-Mails nicht mit sanitize_email kürzen: eine ungültige Adresse soll als solche gemeldet werden.
		return mb_substr( sanitize_text_field( is_scalar( $raw ) ? (string) $raw : '' ), 0, self::MAX_LENGTH );
	}

	/**
	 * Pflicht, E-Mail, Telefon, Format und Muster.
	 *
	 * @param mixed                $value Wert.
	 * @param array<string, mixed> $field Feld.
	 */
	public function validate( $value, array $field ): ?string {
		$error = parent::validate( $value, $field );
		if ( $error || '' === $value ) {
			return $error;
		}
		if ( 'email' === $this->type && ! is_email( $value ) ) {
			return __( 'Bitte gib eine gültige E-Mail-Adresse ein.', 'mlab-forms' );
		}
		$format = $this->format_of( $field );
		if ( '' !== $format && ! Formats::matches( $format, (string) $value ) ) {
			return Formats::message( $format );
		}
		if ( isset( $field['pattern'] ) && ! preg_match( self::pattern_regex( (string) $field['pattern'] ), (string) $value ) ) {
			return Formats::message( '' );
		}

		return null;
	}

	/**
	 * Format des Felds: Telefonfelder prüfen immer als Telefonnummer, sonst gilt «format» aus dem Schema.
	 *
	 * @param array<string, mixed> $field Feld.
	 */
	private function format_of( array $field ): string {
		return 'tel' === $this->type ? Formats::TEL : (string) ( $field['format'] ?? '' );
	}

	/**
	 * Versteckte Felder ohne Label.
	 *
	 * @param array<string, mixed> $field Feld.
	 * @param mixed                $value Wert.
	 * @param string|null          $error Fehler.
	 */
	public function render( array $field, $value, ?string $error ): string {
		if ( 'hidden' === $this->type ) {
			return $this->control( $field, $value, array() );
		}

		return parent::render( $field, $value, $error );
	}

	/**
	 * Das input-Element.
	 *
	 * @param array<string, mixed>       $field Feld.
	 * @param mixed                      $value Wert.
	 * @param array<string, scalar|null> $aria  aria-Attribute.
	 */
	protected function control( array $field, $value, array $aria ): string {
		$format = $this->format_of( $field );
		$attrs  = array(
			'type'        => $this->type,
			'value'       => (string) $value,
			'maxlength'   => 'hidden' === $this->type ? null : self::MAX_LENGTH,
			'pattern'     => isset( $field['pattern'] ) ? (string) $field['pattern'] : null,
			'inputmode'   => 'tel' === $this->type ? 'tel' : ( Formats::POSTCODE_CH === $format ? 'numeric' : null ),
			'data-format' => '' === $format ? null : $format,
		) + $this->base_attributes( $field, $aria );

		return Html::tag( 'input', $attrs, null );
	}

	/**
	 * HTML-Muster als PHP-Regex: wie im Browser über den ganzen Wert, mit Unicode.
	 *
	 * @param string $pattern Muster aus dem Schema.
	 */
	private static function pattern_regex( string $pattern ): string {
		return '/^(?:' . str_replace( '/', '\/', $pattern ) . ')$/u';
	}
}
