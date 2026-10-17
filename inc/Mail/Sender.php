<?php
/**
 * Absender und Antwort-Adresse der Benachrichtigungen.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Mail;

use MLab_Forms\Forms\Form;
use MLab_Forms\Settings;
use MLab_Forms\Submission\Submission;

defined( 'ABSPATH' ) || exit;

/**
 * Löst Absendername, Absender-E-Mail und Antwort-an auf.
 *
 * Reihenfolge: Angabe im Formular («settings.sender»), globale Einstellung, Seitenname
 * und Admin-Adresse. Alle Werte werden einzeilig gemacht und Adressen mit is_email
 * geprüft, damit keine Header eingeschleust werden können.
 */
final class Sender {

	public const MODE_VISITOR = 'visitor';
	public const MODE_FIXED   = 'fixed';

	/**
	 * Der From-Header, leer wenn keine gültige Adresse bekannt ist.
	 *
	 * @param Form $form Formular.
	 */
	public static function from_header( Form $form ): string {
		$email = self::from_email( $form );
		if ( '' === $email ) {
			return '';
		}

		$name = str_replace( array( '<', '>', '"', '\\' ), '', self::from_name( $form ) );

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
	 * Absendername: Formular, Einstellung, Name der Website.
	 *
	 * @param Form $form Formular.
	 */
	private static function from_name( Form $form ): string {
		$candidates = array(
			(string) ( self::own( $form )['name'] ?? '' ),
			(string) Settings::get( 'email_from_name' ),
			wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
		);

		foreach ( $candidates as $name ) {
			if ( '' !== self::single_line( $name ) ) {
				return self::single_line( $name );
			}
		}

		return '';
	}

	/**
	 * Absender-Adresse: Formular, Einstellung, Admin-Adresse.
	 *
	 * @param Form $form Formular.
	 */
	private static function from_email( Form $form ): string {
		$candidates = array( self::own( $form )['email'] ?? '', Settings::get( 'email_from_address' ), get_option( 'admin_email' ) );

		foreach ( $candidates as $email ) {
			if ( '' !== self::clean_email( $email ) ) {
				return self::clean_email( $email );
			}
		}

		return '';
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
