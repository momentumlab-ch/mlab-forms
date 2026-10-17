<?php
/**
 * Spam-Schutz: eingebaute Prüfungen und optionale Dienste.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Submission;

use MLab_Forms\Forms\Form;
use MLab_Forms\Settings;
use MLab_Forms\Spam\Akismet;
use MLab_Forms\Spam\Captcha;
use MLab_Forms\Spam\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Honeypot, Zeitfalle und Rate-Limit pro gehashter IP, dazu optional ein Captcha-Dienst
 * und Akismet.
 *
 * Die Dienste werden erst nach der Feldprüfung gefragt (check_services): ein Captcha-Token
 * gilt nur einmal und soll bei Tippfehlern nicht verbraucht sein.
 *
 * Die Zeitfalle nutzt ein signiertes Zeitstempel-Token statt eines Nonce. Es funktioniert
 * auch mit Seiten-Cache: ein altes Token ist nur «älter», also nie zu schnell.
 */
final class SpamGuard {

	public const HONEYPOT_FIELD = 'mlab_hp';
	public const TOKEN_FIELD    = 'mlab_ts';

	public const OK           = 'ok';
	public const SPAM         = 'spam';
	public const RATE_LIMITED = 'rate_limited';
	public const BLOCKED      = 'blocked';
	public const UNAVAILABLE  = 'unavailable';

	private const RATE_WINDOW = HOUR_IN_SECONDS;

	/**
	 * Signiertes Token mit dem aktuellen Zeitpunkt.
	 *
	 * @param int      $form_id Formular.
	 * @param int|null $time    Zeitpunkt, Standard jetzt.
	 */
	public static function token( int $form_id, ?int $time = null ): string {
		$time ??= time();

		return $time . ':' . self::sign( $form_id, $time );
	}

	/**
	 * Prüft eine Absendung.
	 *
	 * @param Form                 $form    Formular.
	 * @param array<string, mixed> $request Rohdaten (Honeypot und Token).
	 * @param string               $ip_hash Gehashte IP.
	 * @return string OK, SPAM oder RATE_LIMITED.
	 */
	public static function check( Form $form, array $request, string $ip_hash ): string {
		$spam = (array) $form->setting( 'spam' );

		if ( ! empty( $spam['honeypot'] ) && ! empty( $request[ self::HONEYPOT_FIELD ] ) ) {
			return self::SPAM;
		}
		if ( ! self::token_ok( $form->id, (string) ( $request[ self::TOKEN_FIELD ] ?? '' ), (int) ( $spam['min_seconds'] ?? 0 ) ) ) {
			return self::SPAM;
		}
		if ( self::rate_limited( $ip_hash ) ) {
			return self::RATE_LIMITED;
		}

		return self::OK;
	}

	/**
	 * Der Captcha-Anbieter dieses Formulars: höchstens einer, und nur mit hinterlegten Schlüsseln.
	 *
	 * @param Form $form Formular.
	 * @return string turnstile, recaptcha, hcaptcha oder leer.
	 */
	public static function provider( Form $form ): string {
		$provider = Services::normalize( ( (array) $form->setting( 'spam' ) )['provider'] ?? '' );

		return '' !== $provider && Services::configured( $provider ) ? $provider : '';
	}

	/**
	 * Fragt die eingeschalteten Dienste (Captcha, Akismet) nach einer geprüften Absendung.
	 *
	 * @param Form                 $form    Formular.
	 * @param array<string, mixed> $request Rohdaten (Token des Anbieters).
	 * @param array<string, mixed> $values  Bereinigte Werte.
	 * @return string OK, BLOCKED oder UNAVAILABLE (Dienst nicht erreichbar: nie durchlassen).
	 */
	public static function check_services( Form $form, array $request, array $values ): string {
		$provider = self::provider( $form );
		if ( '' !== $provider ) {
			$field   = Services::captchas()[ $provider ]['field'];
			$verdict = Captcha::verify( $provider, is_string( $request[ $field ] ?? null ) ? $request[ $field ] : '' );
			if ( Captcha::VALID !== $verdict ) {
				return Captcha::UNAVAILABLE === $verdict ? self::UNAVAILABLE : self::BLOCKED;
			}
		}

		return self::akismet_enabled( $form ) ? self::check_akismet( $form, $request, $values ) : self::OK;
	}

	/**
	 * Zählt eine angenommene Absendung für das Rate-Limit.
	 *
	 * @param string $ip_hash Gehashte IP.
	 */
	public static function count( string $ip_hash ): void {
		if ( '' === $ip_hash || self::limit() < 1 ) {
			return;
		}

		$key  = self::rate_key( $ip_hash );
		$hits = (int) get_transient( $key );
		set_transient( $key, $hits + 1, self::RATE_WINDOW );
	}

	/**
	 * Ist Akismet für das Formular eingeschaltet und ein Schlüssel vorhanden?
	 *
	 * @param Form $form Formular.
	 */
	private static function akismet_enabled( Form $form ): bool {
		return ! empty( ( (array) $form->setting( 'spam' ) )['akismet'] ) && '' !== Services::akismet_key();
	}

	/**
	 * Befragt Akismet.
	 *
	 * @param Form                 $form    Formular.
	 * @param array<string, mixed> $request Rohdaten.
	 * @param array<string, mixed> $values  Bereinigte Werte.
	 */
	private static function check_akismet( Form $form, array $request, array $values ): string {
		$verdict = Akismet::check( $form, $values, $request );
		if ( Akismet::UNAVAILABLE === $verdict ) {
			return self::UNAVAILABLE;
		}

		return Akismet::SPAM === $verdict ? self::BLOCKED : self::OK;
	}

	/**
	 * Ist das Token echt und alt genug?
	 *
	 * @param int    $form_id     Formular.
	 * @param string $token       Token aus der Anfrage.
	 * @param int    $min_seconds Mindestzeit.
	 */
	public static function token_ok( int $form_id, string $token, int $min_seconds ): bool {
		[ $time, $signature ] = array_pad( explode( ':', $token, 2 ), 2, '' );

		if ( ! ctype_digit( $time ) || ! hash_equals( self::sign( $form_id, (int) $time ), $signature ) ) {
			return false;
		}

		return time() - (int) $time >= $min_seconds;
	}

	/**
	 * Wurde das Limit erreicht?
	 *
	 * @param string $ip_hash Gehashte IP.
	 */
	private static function rate_limited( string $ip_hash ): bool {
		$limit = self::limit();

		return '' !== $ip_hash && $limit > 0 && (int) get_transient( self::rate_key( $ip_hash ) ) >= $limit;
	}

	/**
	 * Erlaubte Absendungen pro Stunde, 0 für unbegrenzt.
	 */
	private static function limit(): int {
		return (int) Settings::get( 'spam_rate_limit' );
	}

	/**
	 * Transient-Schlüssel (max. 172 Zeichen).
	 *
	 * @param string $ip_hash Gehashte IP.
	 */
	private static function rate_key( string $ip_hash ): string {
		return 'mlab_forms_rl_' . substr( $ip_hash, 0, 40 );
	}

	/**
	 * HMAC über Formular und Zeit.
	 *
	 * @param int $form_id Formular.
	 * @param int $time    Zeitpunkt.
	 */
	private static function sign( int $form_id, int $time ): string {
		return hash_hmac( 'sha256', $form_id . '|' . $time, wp_salt( 'nonce' ) );
	}
}
