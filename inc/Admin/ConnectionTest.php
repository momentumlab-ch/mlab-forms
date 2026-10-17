<?php
/**
 * «Verbindung testen» für die Spam-Dienste.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Admin;

use MLab_Forms\Capabilities;
use MLab_Forms\Spam\Akismet;
use MLab_Forms\Spam\Captcha;
use MLab_Forms\Spam\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Ajax-Aktion der Einstellungsseite. Prüft Nonce und Berechtigung und fragt den Dienst
 * mit den gespeicherten Schlüsseln. Schlüssel werden nie zurückgegeben.
 */
final class ConnectionTest {

	public const ACTION = 'mlab_forms_test_connection';

	/**
	 * Hängt die Ajax-Aktion an (nur für angemeldete Benutzer).
	 */
	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Beantwortet den Aufruf mit {ok, message}.
	 */
	public function handle(): void {
		check_ajax_referer( self::ACTION, 'nonce' );
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_send_json_error( array( 'message' => __( 'Dazu fehlt dir die Berechtigung.', 'mlab-forms' ) ), 403 );
		}

		$service = isset( $_POST['service'] ) ? sanitize_key( wp_unslash( $_POST['service'] ) ) : '';

		wp_send_json_success( self::run( $service ) );
	}

	/**
	 * Testet einen Dienst.
	 *
	 * @param string $service turnstile, recaptcha, hcaptcha oder akismet.
	 * @return array{ok: bool, message: string}
	 */
	public static function run( string $service ): array {
		if ( Services::AKISMET === $service ) {
			return Akismet::test();
		}
		if ( '' !== Services::normalize( $service ) ) {
			return Captcha::test( $service );
		}

		return array(
			'ok'      => false,
			'message' => __( 'Unbekannter Dienst.', 'mlab-forms' ),
		);
	}
}
