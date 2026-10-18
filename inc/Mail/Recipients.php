<?php
/**
 * Empfänger der Benachrichtigungen.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Mail;

use MLab_Forms\Forms\Form;
use MLab_Forms\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Löst auf, an wen die Anfragen eines Formulars gehen.
 *
 * Reihenfolge: Adressen des Formulars («settings.recipients»), Standard-Empfänger aus den
 * Einstellungen, Admin-Adresse der Website. Es zählen nur feste Adressen: Platzhalter wie
 * {field:email} werden hier nie aufgelöst, Besucher können den Empfänger also nicht beeinflussen.
 * Jede Adresse wird einzeln mit is_email geprüft, Zeilenumbrüche und Duplikate fallen weg.
 */
final class Recipients {

	public const MAX = 10;

	/**
	 * Zerlegt eine Eingabe in einzelne Angaben (getrennt durch Komma, Semikolon oder Zeilenumbruch).
	 *
	 * @param mixed $raw Rohtext.
	 * @return list<string>
	 */
	public static function split( $raw ): array {
		if ( ! is_string( $raw ) ) {
			return array();
		}
		$parts = array_map( static fn( string $part ) => trim( sanitize_text_field( $part ) ), (array) preg_split( '/[,;\r\n]+/', $raw ) );

		return array_values( array_filter( $parts, static fn( string $part ) => '' !== $part ) );
	}

	/**
	 * Gültige, einmalige Adressen (höchstens MAX).
	 *
	 * @param mixed $raw Rohtext.
	 * @return list<string>
	 */
	public static function valid( $raw ): array {
		return array_slice( self::unique( $raw ), 0, self::MAX );
	}

	/**
	 * Sind es mehr als MAX verschiedene gültige Adressen?
	 *
	 * @param mixed $raw Rohtext.
	 */
	public static function too_many( $raw ): bool {
		return count( self::unique( $raw ) ) > self::MAX;
	}

	/**
	 * Alle gültigen Adressen ohne Duplikate (Gross-/Kleinschreibung zählt nicht).
	 *
	 * @param mixed $raw Rohtext.
	 * @return list<string>
	 */
	private static function unique( $raw ): array {
		$found = array();
		foreach ( self::split( $raw ) as $part ) {
			if ( is_email( $part ) ) {
				$found[ strtolower( $part ) ] ??= $part;
			}
		}

		return array_values( $found );
	}

	/**
	 * Angaben, die keine gültige Adresse sind.
	 *
	 * @param mixed $raw Rohtext.
	 * @return list<string>
	 */
	public static function invalid( $raw ): array {
		return array_values( array_filter( self::split( $raw ), static fn( string $part ) => ! is_email( $part ) ) );
	}

	/**
	 * Bereinigt die Einstellung: nur gültige Adressen, durch Komma getrennt.
	 *
	 * @param mixed $raw Rohtext.
	 */
	public static function clean( $raw ): string {
		return implode( ', ', self::valid( $raw ) );
	}

	/**
	 * Standard-Empfänger: Einstellung, sonst Admin-Adresse der Website.
	 *
	 *
	 * @return list<string>
	 */
	public static function defaults(): array {
		foreach ( array( Settings::get( 'default_recipient' ), get_option( 'admin_email' ) ) as $raw ) {
			$list = self::valid( $raw );
			if ( $list ) {
				return $list;
			}
		}

		return array();
	}

	/**
	 * Eigene Adressen des Formulars, leer wenn es keine hat.
	 *
	 * @param Form|null $form Formular.
	 * @return list<string>
	 */
	public static function of_form( ?Form $form ): array {
		return $form ? self::valid( $form->setting( 'recipients' ) ) : array();
	}

	/**
	 * Empfänger der Seite: Adressen des Formulars, sonst Standard-Empfänger.
	 *
	 * @param Form|null $form Formular.
	 * @return list<string>
	 */
	public static function for_form( ?Form $form ): array {
		$own = self::of_form( $form );

		return $own ? $own : self::defaults();
	}
}
