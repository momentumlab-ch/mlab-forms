<?php
/**
 * Absenden ohne JavaScript.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Submission;

defined( 'ABSPATH' ) || exit;

/**
 * Normaler POST an admin-post.php. Das Ergebnis wird kurz gespeichert und nach der
 * Weiterleitung zurück auf die Seite vom Renderer angezeigt.
 */
final class PostHandler {

	public const ACTION       = 'mlab_forms_submit';
	public const RESULT_PARAM = 'mlab_result';

	private const RESULT_TTL = 10 * MINUTE_IN_SECONDS;

	/**
	 * Hängt die Aktion für Gäste und angemeldete Benutzer an.
	 */
	public function register(): void {
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Verarbeitet und leitet zurück.
	 *
	 * Ohne Nonce, weil Seiten oft gecacht sind; geschützt wird über SpamGuard.
	 */
	public function handle(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$request = wp_unslash( $_POST );
		$form_id = isset( $request['form_id'] ) ? absint( $request['form_id'] ) : 0;
		// phpcs:enable

		$result = SubmissionHandler::handle( $form_id, (array) $request );
		$back   = wp_validate_redirect( (string) ( $request['mlab_source'] ?? '' ), '' );
		$back   = '' !== $back ? $back : home_url( '/' );

		if ( $result->ok() && '' !== $result->redirect ) {
			wp_safe_redirect( $result->redirect );
			exit;
		}

		wp_safe_redirect( add_query_arg( self::RESULT_PARAM, self::remember( $form_id, $result ), $back ) . '#mlab-form-' . $form_id );
		exit;
	}

	/**
	 * Ergebnis für die Anzeige nach der Weiterleitung, falls vorhanden.
	 *
	 * @param int $form_id Formular.
	 */
	public static function recall( int $form_id ): ?SubmissionResult {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur Anzeige eines zufälligen Schlüssels.
		$key  = isset( $_GET[ self::RESULT_PARAM ] ) ? sanitize_key( wp_unslash( $_GET[ self::RESULT_PARAM ] ) ) : '';
		$data = '' === $key ? null : get_transient( 'mlab_forms_result_' . $key );

		if ( ! is_array( $data ) || $form_id !== $data['form_id'] ) {
			return null;
		}

		return new SubmissionResult( $data['status'], $data['message'], $data['errors'], $data['values'] );
	}

	/**
	 * Speichert das Ergebnis unter einem zufälligen Schlüssel.
	 *
	 * @param int              $form_id Formular.
	 * @param SubmissionResult $result  Ergebnis.
	 */
	private static function remember( int $form_id, SubmissionResult $result ): string {
		$key = strtolower( wp_generate_password( 20, false ) );
		set_transient(
			'mlab_forms_result_' . $key,
			array(
				'form_id' => $form_id,
				'status'  => $result->status,
				'message' => $result->message,
				'errors'  => $result->errors,
				'values'  => $result->ok() ? array() : $result->values,
			),
			self::RESULT_TTL
		);

		return $key;
	}
}
