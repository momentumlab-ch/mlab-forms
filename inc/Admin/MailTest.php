<?php
/**
 * Testmail an die gespeicherten Standard-Empfänger.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Admin;

use MLab_Forms\Forms\Form;
use MLab_Forms\Mail\Notifier;
use MLab_Forms\Mail\Recipients;
use MLab_Forms\Submission\Submission;

defined( 'ABSPATH' ) || exit;

/**
 * Berechtigter, begrenzter Testversand ohne frei übergebbare Empfänger.
 */
final class MailTest {

	public const ACTION = 'mlab_forms_test_mail';

	/**
	 * Registriert den angemeldeten Ajax-Endpunkt.
	 */
	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Testet wp_mail mit den gespeicherten globalen Einstellungen.
	 */
	public function handle(): void {
		check_ajax_referer( self::ACTION, 'nonce' );
		if ( ! current_user_can( SettingsPage::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Dazu fehlt dir die Berechtigung.', 'mlab-forms' ) ), 403 );
			return;
		}
		$key = 'mlab_forms_mail_test_' . get_current_user_id();
		if ( get_transient( $key ) ) {
			wp_send_json_error( array( 'message' => __( 'Bitte warte eine Minute vor dem nächsten Testversand.', 'mlab-forms' ) ), 429 );
			return;
		}
		$to = Recipients::defaults();
		if ( ! $to ) {
			wp_send_json_error( array( 'message' => __( 'Bitte zuerst eine gültige Zustelladresse speichern.', 'mlab-forms' ) ), 400 );
			return;
		}
		set_transient( $key, true, MINUTE_IN_SECONDS );
		$submission = new Submission(
			new Form(
				0,
				'MomentumLab Forms',
				array(
					'schema_version' => 1,
					'fields'         => array(),
				)
			),
			array()
		);
		$sent       = wp_mail( $to, __( 'MomentumLab Forms · Testmail', 'mlab-forms' ), __( 'Der Testversand von MomentumLab Forms funktioniert. Diese Mail enthält keine Kundendaten.', 'mlab-forms' ), Notifier::headers( array(), $submission ) );
		wp_send_json_success(
			array(
				'ok'      => $sent,
				'message' => $sent ? __( 'Testmail an den Mailversand übergeben. Bitte prüfe den Posteingang und Spam-Ordner.', 'mlab-forms' ) : __( 'Testversand fehlgeschlagen. Bitte prüfe deine Mail- oder SMTP-Konfiguration.', 'mlab-forms' ),
			)
		);
	}
}
