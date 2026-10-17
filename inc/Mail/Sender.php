<?php
/**
 * Absender und Antwort-Adresse der Benachrichtigungen.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Mail;

use MLab_Forms\Forms\Form;
use MLab_Forms\Settings;
use MLab_Forms\SiteHost;
use MLab_Forms\Submission\Submission;

defined( 'ABSPATH' ) || exit;

/**
 * Löst Absendername, Absender-E-Mail und Antwort-an auf.
 *
 * Reihenfolge: Angabe im Formular («settings.sender»), globale Einstellung. Ist weder Name
 * noch Adresse gesetzt, gibt es keinen From-Header, und WordPress verwendet seinen Standard
 * (wordpress@domain). Ist nur der Name gesetzt, bleibt die Standard-Adresse von WordPress.
 * Ist nur die Adresse gesetzt, ist der Seitenname der Absendername. Alle Werte werden einzeilig gemacht und Adressen mit is_email
 * geprüft, damit keine Header eingeschleust werden können.
 */
final class Sender {

	public const MODE_VISITOR = 'visitor';
	public const MODE_FIXED   = 'fixed';

	/**
	 * Der From-Header, leer wenn weder Name noch Adresse eingestellt sind (dann gilt der WordPress-Standard).
	 *
	 * @param Form $form Formular.
	 */
	public static function from_header( Form $form ): string {
		$email = self::from_email( $form );
		$name  = self::from_name( $form );
		if ( '' === $email && '' === $name ) {
			return '';
		}

		$email = '' !== $email ? $email : self::default_address();
		if ( '' === $email ) {
			return '';
		}

		$name = str_replace( array( '<', '>', '"', '\\' ), '', '' !== $name ? $name : self::site_name() );

		return '' === $name ? 'From: ' . $email : sprintf( 'From: %s <%s>', $name, $email );
	}

	/**
	 * Antwort-Adresse nach Modus: E-Mail des Besuchers oder feste Adresse.
	 *
	 * @param Form       $form       Formular.
	 * @param Submission $submission Absendung.
	 * @return string Gültige Adresse oder leer.
	 */
	public static function reply_to( Form $form, Submission $submission ): string {
		[ $mode, $fixed ] = self::reply_to_config( $form );

		return self::MODE_FIXED === $mode ? $fixed : self::visitor_email( $submission );
	}

	/**
	 * Ist die Adresse gültig, gehört aber nicht zur Domain der Website? Dann landen Mails eher im Spam.
	 *
	 * @param mixed $email Adresse.
	 */
	public static function is_foreign_address( $email ): bool {
		$email = self::clean_email( $email );

		return '' !== $email && ! SiteHost::email_matches( $email );
	}

	/**
	 * Entfernt Zeilenumbrüche und Steuerzeichen.
	 *
	 * @param string $text Text.
	 */
	public static function single_line( string $text ): string {
		return trim( (string) preg_replace( '/[\r\n\t\x00-\x1F\x7F]+/', ' ', $text ) );
	}

	/**
	 * Eine gültige, einzeilige Adresse oder ein leerer Text.
	 *
	 * @param mixed $email Rohwert.
	 */
	public static function clean_email( $email ): string {
		$email = is_string( $email ) ? self::single_line( $email ) : '';

		return is_email( $email ) ? $email : '';
	}

	/**
	 * Absendername aus Formular oder Einstellung, sonst leer.
	 *
	 * @param Form $form Formular.
	 */
	private static function from_name( Form $form ): string {
		foreach ( array( self::own( $form )['name'] ?? '', Settings::get( 'email_from_name' ) ) as $name ) {
			$name = is_string( $name ) ? self::single_line( $name ) : '';
			if ( '' !== $name ) {
				return $name;
			}
		}

		return '';
	}

	/**
	 * Absender-Adresse aus Formular oder Einstellung, sonst leer.
	 *
	 * @param Form $form Formular.
	 */
	private static function from_email( Form $form ): string {
		foreach ( array( self::own( $form )['email'] ?? '', Settings::get( 'email_from_address' ) ) as $email ) {
			if ( '' !== self::clean_email( $email ) ) {
				return self::clean_email( $email );
			}
		}

		return '';
	}

	/**
	 * Name der Website, wenn nur eine Adresse eingestellt ist.
	 */
	private static function site_name(): string {
		return self::single_line( wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) );
	}

	/**
	 * Die Standard-Adresse von WordPress (wordpress@domain), nötig nur wenn ein Name ohne Adresse gesetzt ist.
	 */
	private static function default_address(): string {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$host = str_starts_with( $host, 'www.' ) ? substr( $host, 4 ) : $host;

		return self::clean_email( 'wordpress@' . $host );
	}

	/**
	 * Modus und feste Adresse: Formular vor Einstellung.
	 *
	 * @param Form $form Formular.
	 * @return array{0: string, 1: string}
	 */
	private static function reply_to_config( Form $form ): array {
		$own = self::own( $form );
		$set = in_array( $own['reply_to_mode'] ?? '', array( self::MODE_VISITOR, self::MODE_FIXED ), true );

		$mode  = $set ? (string) $own['reply_to_mode'] : (string) Settings::get( 'email_reply_to_mode' );
		$fixed = $set ? ( $own['reply_to'] ?? '' ) : Settings::get( 'email_reply_to' );

		return array( $mode, self::clean_email( $fixed ) );
	}

	/**
	 * Erste gültige Adresse aus einem E-Mail-Feld der Absendung.
	 *
	 * @param Submission $submission Absendung.
	 */
	private static function visitor_email( Submission $submission ): string {
		foreach ( $submission->form->input_fields() as $field ) {
			if ( 'email' === $field['type'] && '' !== self::clean_email( $submission->values[ $field['id'] ] ?? '' ) ) {
				return self::clean_email( $submission->values[ $field['id'] ] );
			}
		}

		return '';
	}

	/**
	 * Absender-Angaben aus dem Schema des Formulars.
	 *
	 * @param Form $form Formular.
	 * @return array<string, mixed>
	 */
	private static function own( Form $form ): array {
		return (array) $form->setting( 'sender' );
	}
}
