<?php
/**
 * Serverseitige Prüfung von Turnstile, reCAPTCHA v3 und hCaptcha.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Spam;

use MLab_Forms\SiteHost;

defined( 'ABSPATH' ) || exit;

/**
 * Alle drei Dienste prüfen das Token mit demselben Ablauf (siteverify, POST mit secret und response).
 * Die IP-Adresse wird nicht mitgesendet.
 * Nennt die Antwort einen Hostnamen, muss er zur Website gehören.
 */
final class Captcha {

	public const VALID       = 'valid';
	public const INVALID     = 'invalid';
	public const UNAVAILABLE = 'unavailable';

	private const SECRET_ERRORS = array( 'invalid-input-secret', 'missing-input-secret', 'invalid-or-missing-secret', 'invalid-secret' );

	/**
	 * Prüft das Token eines Besuchers.
	 *
	 * @param string $provider Anbieter.
	 * @param string $token    Token aus dem Formular.
	 * @return string VALID, INVALID oder UNAVAILABLE (Dienst nicht erreichbar oder Schlüssel abgelehnt).
	 */
	public static function verify( string $provider, string $token ): string {
		if ( '' === Services::normalize( $provider ) || '' === trim( $token ) ) {
			return self::INVALID;
		}

		$data = self::call( $provider, $token );
		if ( null === $data ) {
			return self::UNAVAILABLE;
		}
		if ( array_intersect( self::SECRET_ERRORS, (array) ( $data['error-codes'] ?? array() ) ) ) {
			return self::UNAVAILABLE;
		}

		return ! empty( $data['success'] ) && self::hostname_ok( $data ) && self::score_ok( $provider, $data ) ? self::VALID : self::INVALID;
	}

	/**
	 * Testaufruf für die Einstellungsseite: prüft, ob der Dienst den geheimen Schlüssel annimmt.
	 *
	 * @param string $provider Anbieter.
	 * @return array{ok: bool, message: string}
	 */
	public static function test( string $provider ): array {
		if ( ! Services::configured( $provider ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Bitte trage zuerst beide Schlüssel ein und speichere.', 'mlab-forms' ),
			);
		}

		$data = self::call( $provider, 'mlab-forms-connection-test' );
		if ( null === $data ) {
			return array(
				'ok'      => false,
				'message' => __( 'Der Dienst ist gerade nicht erreichbar. Bitte versuche es später nochmals.', 'mlab-forms' ),
			);
		}
		if ( array_intersect( self::SECRET_ERRORS, (array) ( $data['error-codes'] ?? array() ) ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Der Dienst hat den geheimen Schlüssel abgelehnt. Bitte prüfe ihn.', 'mlab-forms' ),
			);
		}

		return array(
			'ok'      => true,
			'message' => __( 'Verbindung funktioniert.', 'mlab-forms' ),
		);
	}

	/**
	 * Ruft siteverify auf.
	 *
	 * @param string $provider Anbieter.
	 * @param string $token    Token.
	 * @return array<string, mixed>|null Dekodierte Antwort oder null bei Fehler.
	 */
	private static function call( string $provider, string $token ): ?array {
		$response = Services::client()->post(
			Services::captchas()[ $provider ]['verify'],
			array(
				'secret'   => Services::secret_key( $provider ),
				'response' => $token,
			)
		);
		$data     = null !== $response && 200 === $response['status'] ? json_decode( $response['body'], true ) : null;

		return is_array( $data ) ? $data : null;
	}

	/**
	 * Gehört der in der Antwort genannte Hostname zur Website? Fehlt er, wird nicht abgelehnt.
	 *
	 * @param array<string, mixed> $data Antwort des Dienstes.
	 */
	private static function hostname_ok( array $data ): bool {
		if ( ! isset( $data['hostname'] ) || '' === $data['hostname'] ) {
			return true;
		}

		return is_string( $data['hostname'] ) && SiteHost::matches( $data['hostname'] );
	}

	/**
	 * Bei reCAPTCHA v3 muss der Score über der Schwelle liegen und die Aktion stimmen.
	 *
	 * @param string               $provider Anbieter.
	 * @param array<string, mixed> $data     Antwort des Dienstes.
	 */
	private static function score_ok( string $provider, array $data ): bool {
		if ( Services::RECAPTCHA !== $provider ) {
			return true;
		}

		/**
		 * Mindest-Score für Google reCAPTCHA v3 (0.0 bis 1.0).
		 *
		 * @param float $threshold Standard 0.5.
		 */
		$threshold = (float) apply_filters( 'mlab_forms_recaptcha_threshold', 0.5 );

		return isset( $data['score'] ) && (float) $data['score'] >= $threshold && 'submit' === ( $data['action'] ?? '' );
	}
}
