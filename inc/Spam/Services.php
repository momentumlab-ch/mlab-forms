<?php
/**
 * Verzeichnis der Spam-Dienste und ihrer Schlüssel.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Spam;

use MLab_Forms\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Kennt die drei Captcha-Anbieter (immer nur einer pro Formular) und Akismet.
 *
 * Schlüssel liegen ausschliesslich in den WordPress-Optionen (Settings), nie im Schema.
 */
final class Services {

	public const TURNSTILE = 'turnstile';
	public const RECAPTCHA = 'recaptcha';
	public const HCAPTCHA  = 'hcaptcha';
	public const AKISMET   = 'akismet';

	/**
	 * Austauschbarer Client für Netzwerkaufrufe.
	 *
	 * @var HttpClient|null
	 */
	private static ?HttpClient $client = null;

	/**
	 * Der Client für Netzwerkaufrufe.
	 */
	public static function client(): HttpClient {
		self::$client ??= new WpHttpClient();

		return self::$client;
	}

	/**
	 * Ersetzt den Client (für Tests); null stellt den Standard wieder her.
	 *
	 * @param HttpClient|null $client Client.
	 */
	public static function set_client( ?HttpClient $client ): void {
		self::$client = $client;
	}

	/**
	 * Die Captcha-Anbieter mit Namen, Skript, Prüfadresse und Feldname der Antwort.
	 *
	 * @return array<string, array{label: string, script: string, verify: string, field: string}>
	 */
	public static function captchas(): array {
		return array(
			self::TURNSTILE => array(
				'label'  => 'Cloudflare Turnstile',
				'script' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
				'verify' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
				'field'  => 'cf-turnstile-response',
			),
			self::RECAPTCHA => array(
				'label'  => 'Google reCAPTCHA v3',
				'script' => 'https://www.google.com/recaptcha/api.js',
				'verify' => 'https://www.google.com/recaptcha/api/siteverify',
				'field'  => 'g-recaptcha-response',
			),
			self::HCAPTCHA  => array(
				'label'  => 'hCaptcha',
				'script' => 'https://js.hcaptcha.com/1/api.js',
				'verify' => 'https://api.hcaptcha.com/siteverify',
				'field'  => 'h-captcha-response',
			),
		);
	}

	/**
	 * Ein gültiger Anbieter oder ein leerer Text. Es gibt nie mehr als einen.
	 *
	 * @param mixed $provider Wert aus Schema oder Einstellung.
	 */
	public static function normalize( $provider ): string {
		return is_string( $provider ) && isset( self::captchas()[ $provider ] ) ? $provider : '';
	}

	/**
	 * Öffentlicher Schlüssel des Anbieters.
	 *
	 * @param string $provider Anbieter.
	 */
	public static function site_key( string $provider ): string {
		return trim( (string) Settings::get( 'spam_' . $provider . '_site' ) );
	}

	/**
	 * Geheimer Schlüssel des Anbieters.
	 *
	 * @param string $provider Anbieter.
	 */
	public static function secret_key( string $provider ): string {
		return trim( (string) Settings::get( 'spam_' . $provider . '_secret' ) );
	}

	/**
	 * Sind beide Schlüssel des Anbieters hinterlegt?
	 *
	 * @param string $provider Anbieter.
	 */
	public static function configured( string $provider ): bool {
		return '' !== self::site_key( $provider ) && '' !== self::secret_key( $provider );
	}

	/**
	 * Adresse des Skripts im Frontend; reCAPTCHA v3 braucht den Site-Key in der Adresse.
	 *
	 * @param string $provider Anbieter.
	 */
	public static function script_url( string $provider ): string {
		$url = self::captchas()[ $provider ]['script'] ?? '';

		return self::RECAPTCHA === $provider ? add_query_arg( 'render', rawurlencode( self::site_key( $provider ) ), $url ) : $url;
	}

	/**
	 * Akismet-Schlüssel: der des Akismet-Plugins, sonst der eigene.
	 */
	public static function akismet_key(): string {
		$callback = array( 'Akismet', 'get_api_key' );
		$plugin   = is_callable( $callback ) ? call_user_func( $callback ) : '';

		return is_string( $plugin ) && '' !== trim( $plugin ) ? trim( $plugin ) : trim( (string) Settings::get( 'spam_akismet_key' ) );
	}
}
