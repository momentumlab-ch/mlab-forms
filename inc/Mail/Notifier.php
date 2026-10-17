<?php
/**
 * Versand der Benachrichtigungen.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Mail;

use MLab_Forms\Settings;
use MLab_Forms\Submission\Submission;

defined( 'ABSPATH' ) || exit;

/**
 * Verschickt die Benachrichtigungen eines Formulars per wp_mail als Text-E-Mail.
 *
 * Schutz gegen Header-Injection: Empfänger und Reply-To sind nur gültige Adressen,
 * der Betreff enthält keine Zeilenumbrüche.
 */
final class Notifier {

	private const MAX_RECIPIENTS = 10;

	/**
	 * Sendet alle aktiven Benachrichtigungen.
	 *
	 * @param Submission $submission Absendung.
	 * @return int Anzahl erfolgreich versendeter E-Mails.
	 */
	public static function send_all( Submission $submission ): int {
		$sent = 0;
		foreach ( (array) $submission->form->setting( 'notifications' ) as $notification ) {
			if ( is_array( $notification ) && ( $notification['enabled'] ?? true ) && self::send( $notification, $submission ) ) {
				++$sent;
			}
		}

		return $sent;
	}

	/**
	 * Gültige Adressen aus einem Text mit Platzhaltern.
	 *
	 * @param string     $value      z. B. «{site_admin_email}, {field:email}».
	 * @param Submission $submission Absendung.
	 * @return list<string>
	 */
	public static function addresses( string $value, Submission $submission ): array {
		$parts = array_map( 'trim', explode( ',', MergeTags::replace( $value, $submission ) ) );
		$valid = array_filter( $parts, static fn( string $email ) => (bool) is_email( $email ) );

		return array_slice( array_values( array_unique( $valid ) ), 0, self::MAX_RECIPIENTS );
	}

	/**
	 * Eine Benachrichtigung.
	 *
	 * @param array<string, mixed> $notification Einstellungen der Benachrichtigung.
	 * @param Submission           $submission   Absendung.
	 */
	private static function send( array $notification, Submission $submission ): bool {
		$to = self::addresses( (string) ( $notification['to'] ?? '' ), $submission );
		if ( ! $to ) {
			return false;
		}

		$subject = self::single_line( MergeTags::replace( (string) ( $notification['subject'] ?? '' ), $submission ) );
		$body    = MergeTags::replace( (string) ( $notification['body'] ?? '{all_fields}' ), $submission );

		return wp_mail( $to, $subject, $body, self::headers( $notification, $submission ) );
	}

	/**
	 * Absender und Reply-To.
	 *
	 * @param array<string, mixed> $notification Einstellungen.
	 * @param Submission           $submission   Absendung.
	 * @return list<string>
	 */
	private static function headers( array $notification, Submission $submission ): array {
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		$from_email = (string) Settings::get( 'email_from_address' );
		if ( is_email( $from_email ) ) {
			$from_name = (string) Settings::get( 'email_from_name' );
			$from_name = self::single_line( '' !== $from_name ? $from_name : wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) );
			$headers[] = sprintf( 'From: %s <%s>', str_replace( array( '<', '>', '"' ), '', $from_name ), $from_email );
		}

		$reply_to = self::addresses( (string) ( $notification['reply_to'] ?? '' ), $submission );
		if ( $reply_to ) {
			$headers[] = 'Reply-To: ' . $reply_to[0];
		}

		return $headers;
	}

	/**
	 * Entfernt Zeilenumbrüche und Steuerzeichen.
	 *
	 * @param string $text Text.
	 */
	private static function single_line( string $text ): string {
		return trim( (string) preg_replace( '/[\r\n\t\x00-\x1F\x7F]+/', ' ', $text ) );
	}
}
