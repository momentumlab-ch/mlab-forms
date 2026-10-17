<?php
/**
 * Prüfung des Inhalts über Akismet.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Spam;

use MLab_Forms\Forms\Form;
use MLab_Forms\Privacy\IpHasher;

defined( 'ABSPATH' ) || exit;

/**
 * Der Aufruf comment-check geht an {Schlüssel}.rest.akismet.com. Gesendet werden nur Angaben, die Akismet für die
 * Prüfung braucht: Name (nur aus Textfeldern, die als Name gekennzeichnet sind), E-Mail-Felder, Textfelder
 * mit mehreren Zeilen, IP-Adresse, Browser, Referrer und die Adresse der Seite ohne Query-String und Fragment.
 * Andere Textfelder (z. B. Telefon, Firma, Betreff) gehen nicht an Akismet. Die IP-Adresse wird nicht gespeichert.
 */
final class Akismet {

	public const HAM         = 'ham';
	public const SPAM        = 'spam';
	public const UNAVAILABLE = 'unavailable';

	private const KEY_PATTERN = '/^[A-Za-z0-9]{6,40}$/';

	/**
	 * Werte von «autocomplete», die ein Textfeld als Namen kennzeichnen.
	 */
	private const NAME_AUTOCOMPLETE = array( 'name', 'given-name', 'family-name' );

	/**
	 * Prüft eine Absendung.
	 *
	 * @param Form                 $form    Formular.
	 * @param array<string, mixed> $values  Bereinigte Werte.
	 * @param array<string, mixed> $request Rohdaten der Anfrage (mlab_source).
	 * @return string HAM, SPAM oder UNAVAILABLE.
	 */
	public static function check( Form $form, array $values, array $request ): string {
		$key = Services::akismet_key();
		if ( ! preg_match( self::KEY_PATTERN, $key ) ) {
			return self::UNAVAILABLE;
		}

		$response = Services::client()->post( 'https://' . $key . '.rest.akismet.com/1.1/comment-check', self::payload( $form, $values, $request ) );
		if ( null === $response || 200 !== $response['status'] ) {
			return self::UNAVAILABLE;
		}

		$answer = trim( $response['body'] );

		return 'true' === $answer ? self::SPAM : ( 'false' === $answer ? self::HAM : self::UNAVAILABLE );
	}

	/**
	 * Testaufruf für die Einstellungsseite: verify-key.
	 *
	 * @return array{ok: bool, message: string}
	 */
	public static function test(): array {
		$key = Services::akismet_key();
		if ( ! preg_match( self::KEY_PATTERN, $key ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Bitte trage zuerst einen Akismet-Schlüssel ein und speichere.', 'mlab-forms' ),
			);
		}

		$response = Services::client()->post(
			'https://rest.akismet.com/1.1/verify-key',
			array(
				'key'  => $key,
				'blog' => home_url(),
			)
		);
		if ( null === $response || 200 !== $response['status'] ) {
			return array(
				'ok'      => false,
				'message' => __( 'Der Dienst ist gerade nicht erreichbar. Bitte versuche es später nochmals.', 'mlab-forms' ),
			);
		}

		return 'valid' === trim( $response['body'] )
			? array(
				'ok'      => true,
				'message' => __( 'Verbindung funktioniert.', 'mlab-forms' ),
			)
			: array(
				'ok'      => false,
				'message' => __( 'Akismet hat den Schlüssel abgelehnt. Bitte prüfe ihn.', 'mlab-forms' ),
			);
	}

	/**
	 * Die Felder für comment-check.
	 *
	 * @param Form                 $form    Formular.
	 * @param array<string, mixed> $values  Bereinigte Werte.
	 * @param array<string, mixed> $request Rohdaten der Anfrage.
	 * @return array<string, string>
	 */
	public static function payload( Form $form, array $values, array $request ): array {
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		return array(
			'blog'                 => home_url(),
			'user_ip'              => IpHasher::client_ip(),
			'user_agent'           => $agent,
			'permalink'            => self::page_url( (string) ( $request['mlab_source'] ?? '' ) ),
			'referrer'             => esc_url_raw( (string) ( $request['mlab_referrer'] ?? '' ) ),
			'comment_type'         => 'contact-form',
			'comment_author'       => self::author( $form, $values ),
			'comment_author_email' => self::first_of_type( $form, $values, 'email' ),
			'comment_content'      => self::content( $form, $values ),
			'blog_lang'            => get_locale(),
			'blog_charset'         => 'UTF-8',
		);
	}

	/**
	 * Name des Absenders: nur Textfelder mit «autocomplete» name, given-name oder family-name, sonst leer.
	 *
	 * @param Form                 $form   Formular.
	 * @param array<string, mixed> $values Werte.
	 */
	private static function author( Form $form, array $values ): string {
		$names = array();
		foreach ( $form->input_fields() as $field ) {
			if ( 'text' === $field['type'] && in_array( $field['autocomplete'] ?? '', self::NAME_AUTOCOMPLETE, true ) && is_string( $values[ $field['id'] ] ?? null ) ) {
				$names[] = $values[ $field['id'] ];
			}
		}

		return trim( implode( ' ', $names ) );
	}

	/**
	 * Adresse der Seite ohne Query-String und Fragment (dort können Tokens oder Kampagnen-Angaben stehen).
	 *
	 * @param string $url Adresse aus der Anfrage.
	 */
	private static function page_url( string $url ): string {
		$url = esc_url_raw( $url );

		return (string) preg_replace( '/[?#].*$/s', '', $url );
	}

	/**
	 * Wert des ersten Felds eines Typs.
	 *
	 * @param Form                 $form   Formular.
	 * @param array<string, mixed> $values Werte.
	 * @param string               $type   Feldtyp.
	 */
	private static function first_of_type( Form $form, array $values, string $type ): string {
		foreach ( $form->input_fields() as $field ) {
			if ( $type === $field['type'] && is_string( $values[ $field['id'] ] ?? null ) ) {
				return $values[ $field['id'] ];
			}
		}

		return '';
	}

	/**
	 * Inhalt: nur Felder mit mehreren Zeilen (Nachrichten) als «Beschriftung: Wert».
	 *
	 * @param Form                 $form   Formular.
	 * @param array<string, mixed> $values Werte.
	 */
	private static function content( Form $form, array $values ): string {
		$lines = array();
		foreach ( $form->input_fields() as $field ) {
			$value = $values[ $field['id'] ] ?? '';
			if ( 'textarea' === $field['type'] && is_string( $value ) && '' !== $value ) {
				$lines[] = (string) ( $field['label'] ?? $field['id'] ) . ': ' . $value;
			}
		}

		return implode( "\n", $lines );
	}
}
