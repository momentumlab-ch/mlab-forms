<?php
/**
 * Vorschau im Builder.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Admin;

use MLab_Forms\Capabilities;
use MLab_Forms\Forms\Form;
use MLab_Forms\Forms\Language;
use MLab_Forms\Forms\SchemaValidator;
use MLab_Forms\Frontend\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * Ajax-Aktion, die das aktuelle (noch nicht gespeicherte) Schema mit dem echten Renderer ausgibt.
 *
 * Sie prüft Nonce und Berechtigung, validiert das Schema vorher, liest nur und speichert nichts.
 * Die Antwort ist ein vollständiges Dokument für einen iframe ohne Skripte und ohne Absenden.
 */
final class Preview {

	public const ACTION = 'mlab_forms_preview';

	/**
	 * Grösste angenommene Schema-Grösse in Byte.
	 */
	public const MAX_BYTES = 524288;

	/**
	 * Hängt die Ajax-Aktion an (nur für angemeldete Benutzer).
	 */
	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Beantwortet den Aufruf mit {ok, document} oder {ok: false, errors}.
	 */
	public function handle(): void {
		check_ajax_referer( self::ACTION, 'nonce' );
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_send_json_error( array( 'message' => __( 'Dazu fehlt dir die Berechtigung.', 'mlab-forms' ) ), 403 );
		}

		// JSON wird nicht als Text bereinigt, sondern dekodiert und gegen das Schema geprüft.
		$json = isset( $_POST['schema'] ) ? wp_unslash( $_POST['schema'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( self::is_too_large( $json ) ) {
			wp_send_json_error( array( 'message' => __( 'Das Formular ist zu gross für die Vorschau.', 'mlab-forms' ) ), 413 );
		}
		$schema = is_string( $json ) ? json_decode( $json, true ) : null;
		$lang   = isset( $_POST['lang'] ) ? Language::normalize( sanitize_text_field( wp_unslash( $_POST['lang'] ) ) ) : '';

		wp_send_json_success( self::respond( $schema, $lang ) );
	}

	/**
	 * Ist die Eingabe grösser als erlaubt?
	 *
	 * @param mixed $json Eingabe.
	 */
	public static function is_too_large( $json ): bool {
		return is_string( $json ) && strlen( $json ) > self::MAX_BYTES;
	}

	/**
	 * Prüft das Schema und gibt die Vorschau oder die Fehler zurück.
	 *
	 * @param mixed  $schema Dekodiertes Schema.
	 * @param string $lang   Sprache der Vorschau; leer oder unbekannt: Standardsprache.
	 * @return array{ok: bool, document?: string, errors?: list<array{path: string, message: string}>}
	 */
	public static function respond( $schema, string $lang = '' ): array {
		$errors = SchemaValidator::validate( $schema );
		if ( $errors ) {
			return array(
				'ok'     => false,
				'errors' => $errors,
			);
		}

		return array(
			'ok'       => true,
			'document' => self::document( new Form( 0, '', (array) $schema ), $lang ),
		);
	}

	/**
	 * Vollständiges HTML-Dokument mit dem Formular und dessen Stylesheet.
	 *
	 * @param Form   $form Formular.
	 * @param string $lang Sprache der Vorschau.
	 */
	private static function document( Form $form, string $lang ): string {
		$css  = plugins_url( 'assets/css/form.css', MLAB_FORMS_FILE ) . '?ver=' . rawurlencode( MLAB_FORMS_VERSION );
		$dark = 'dark' === Renderer::style_of( $form );

		return '<!doctype html><html lang="' . esc_attr( '' !== $lang ? $lang : str_replace( '_', '-', get_locale() ) ) . '"><head><meta charset="utf-8">'
			. '<meta name="viewport" content="width=device-width, initial-scale=1">'
			. '<link rel="stylesheet" href="' . esc_url( $css ) . '">' // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- eigenes Dokument für den iframe, nicht die Admin-Seite.
			. '<style>body{margin:0;padding:16px;font-family:system-ui,sans-serif;background:' . ( $dark ? '#1d2327' : '#fff' ) . '}</style>'
			. '</head><body>' . Renderer::preview( $form, $lang ) . '</body></html>';
	}
}
