<?php
/**
 * Versand der Benachrichtigungen.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Mail;

use MLab_Forms\Submission\Submission;

defined( 'ABSPATH' ) || exit;

/**
 * Verschickt die Benachrichtigungen eines Formulars per wp_mail als Text-E-Mail.
 *
 * Schutz gegen Header-Injection: Empfänger und Reply-To sind nur einzeln geprüfte, gültige Adressen,
 * der Betreff enthält keine Zeilenumbrüche. Absender und Antwort-Adresse löst Sender auf.
 */
final class Notifier {

	/**
	 * Sendet alle aktiven Benachrichtigungen.
	 *
	 * @param Submission $submission Absendung.
	 * @return int Anzahl erfolgreich versendeter E-Mails.
	 */
	public static function send_all( Submission $submission ): int {
		$sent    = 0;
		$default = $submission->in_language( $submission->form->default_language() );
		foreach ( (array) $submission->form->setting( 'notifications' ) as $index => $notification ) {
			// Mails an die Admin-Adresse erscheinen in der Standardsprache: die Sprache, in der das Formular eingerichtet wurde.
			$source = self::is_for_admin( (array) $notification, $submission ) ? $default : $submission;
			$notice = (array) ( ( (array) $source->form->setting( 'notifications' ) )[ $index ] ?? $notification );
			if ( ( $notice['enabled'] ?? true ) && self::send( $notice, $source ) ) {
				++$sent;
			}
		}

		return $sent;
	}

	/**
	 * Geht die Benachrichtigung nur an die Empfänger der Seite (und nicht an den Besucher oder andere)?
	 *
	 * @param array<string, mixed> $notification Benachrichtigung.
	 * @param Submission           $submission   Absendung.
	 */
	private static function is_for_admin( array $notification, Submission $submission ): bool {
		$to    = self::recipients( $notification, $submission );
		$known = array_map( 'strtolower', array_merge( Recipients::for_form( $submission->form ), Recipients::defaults() ) );

		return array() !== $to && array() === array_filter( $to, static fn( string $email ) => ! in_array( strtolower( $email ), $known, true ) );
	}

	/**
	 * Gültige Adressen aus einem Text mit Platzhaltern.
	 *
	 * @param string     $value      z. B. «{default_recipient}, {field:email}».
	 * @param Submission $submission Absendung.
	 * @return list<string>
	 */
	public static function addresses( string $value, Submission $submission ): array {
		return Recipients::valid( MergeTags::replace( $value, $submission ) );
	}

	/**
	 * Empfänger einer Benachrichtigung.
	 *
	 * Hat das Formular eigene Empfänger, ersetzen sie «{site_admin_email}», wenn das allein im Feld «to» steht.
	 * Andere Angaben (z. B. {field:email} für die Bestätigung an den Besucher) bleiben unverändert.
	 *
	 * @param array<string, mixed> $notification Einstellungen der Benachrichtigung.
	 * @param Submission           $submission   Absendung.
	 * @return list<string>
	 */
	private static function recipients( array $notification, Submission $submission ): array {
		$to = trim( (string) ( $notification['to'] ?? '' ) );
		if ( '{site_admin_email}' === $to && Recipients::of_form( $submission->form ) ) {
			$to = '{default_recipient}';
		}

		return self::addresses( $to, $submission );
	}

	/**
	 * Eine Benachrichtigung.
	 *
	 * @param array<string, mixed> $notification Einstellungen der Benachrichtigung.
	 * @param Submission           $submission   Absendung.
	 */
	private static function send( array $notification, Submission $submission ): bool {
		$to = self::recipients( $notification, $submission );
		if ( ! $to ) {
			return false;
		}

		$subject = Sender::single_line( MergeTags::replace( (string) ( $notification['subject'] ?? '' ), $submission ) );
		$body    = MergeTags::replace( (string) ( $notification['body'] ?? '{all_fields}' ), $submission );

		return wp_mail( $to, $subject, $body, self::headers( $notification, $submission ) );
	}

	/**
	 * Absender und Reply-To.
	 *
	 * Reply-To der Benachrichtigung hat Vorrang, sonst gilt der Modus aus den Absender-Einstellungen.
	 *
	 * @param array<string, mixed> $notification Einstellungen.
	 * @param Submission           $submission   Absendung.
	 * @return list<string>
	 */
	public static function headers( array $notification, Submission $submission ): array {
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		$from = Sender::from_header( $submission->form );
		if ( '' !== $from ) {
			$headers[] = $from;
		}

		$reply_to = self::addresses( (string) ( $notification['reply_to'] ?? '' ), $submission )[0] ?? Sender::reply_to( $submission->form, $submission );
		if ( '' !== $reply_to ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		return $headers;
	}
}
