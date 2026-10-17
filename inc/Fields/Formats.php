<?php
/**
 * Formatprüfungen für Textfelder.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Bekannte Formate: «tel» (Telefonfelder) und «postcode_ch» (Schweizer Postleitzahl).
 * form.js prüft dieselben Regeln im Browser (data-format).
 */
final class Formats {

	public const TEL         = 'tel';
	public const POSTCODE_CH = 'postcode_ch';

	private const MIN_PHONE_DIGITS = 6;

	/**
	 * Schlüssel der bekannten Formate.
	 *
	 * @return list<string>
	 */
	public static function known(): array {
		return array( self::TEL, self::POSTCODE_CH );
	}

	/**
	 * Passt der Wert zum Format? Leere Werte prüft nur «required».
	 *
	 * @param string $format Format.
	 * @param string $value  Bereinigter Wert.
	 */
	public static function matches( string $format, string $value ): bool {
		return match ( $format ) {
			self::TEL         => 1 === preg_match( '/^[0-9+\-\/() ]+$/D', $value ) && preg_match_all( '/[0-9]/', $value ) >= self::MIN_PHONE_DIGITS,
			self::POSTCODE_CH => 1 === preg_match( '/^[0-9]{4}$/D', $value ),
			default           => true,
		};
	}

	/**
	 * Fehlermeldung zum Format.
	 *
	 * @param string $format Format.
	 */
	public static function message( string $format ): string {
		return match ( $format ) {
			self::TEL         => __( 'Bitte gib eine gültige Telefonnummer ein.', 'mlab-forms' ),
			self::POSTCODE_CH => __( 'Bitte gib eine gültige Postleitzahl ein.', 'mlab-forms' ),
			default           => __( 'Das Format stimmt nicht.', 'mlab-forms' ),
		};
	}
}
