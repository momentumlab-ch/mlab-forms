<?php
/**
 * Verarbeitung einer Absendung.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Submission;

use MLab_Forms\Entries\EntryRepository;
use MLab_Forms\Forms\Form;
use MLab_Forms\Forms\FormRepository;
use MLab_Forms\Mail\MergeTags;
use MLab_Forms\Mail\Notifier;
use MLab_Forms\Privacy\IpHasher;

defined( 'ABSPATH' ) || exit;

/**
 * Gemeinsamer Ablauf für REST (mit JS) und normalen POST (ohne JS):
 * Spam prüfen, Werte bereinigen und prüfen, speichern, benachrichtigen.
 */
final class SubmissionHandler {

	private const UTM_KEYS = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' );

	/**
	 * Verarbeitet eine Absendung.
	 *
	 * @param int                  $form_id Formular.
	 * @param array<string, mixed> $request Ungeslashte Daten: mlab (Felder), mlab_hp, mlab_ts, mlab_source, mlab_referrer.
	 */
	public static function handle( int $form_id, array $request ): SubmissionResult {
		$form = FormRepository::find( $form_id );
		if ( null === $form ) {
			return new SubmissionResult( SubmissionResult::NOT_FOUND, __( 'Dieses Formular gibt es nicht mehr.', 'mlab-forms' ) );
		}

		$ip_hash = IpHasher::hash( IpHasher::client_ip() );
		$spam    = SpamGuard::check( $form, $request, $ip_hash );
		if ( SpamGuard::RATE_LIMITED === $spam ) {
			return new SubmissionResult( SubmissionResult::RATE_LIMITED, __( 'Zu viele Anfragen in kurzer Zeit. Bitte versuche es später noch einmal.', 'mlab-forms' ) );
		}
		if ( SpamGuard::SPAM === $spam ) {
			// Bots bekommen eine Erfolgsmeldung, damit sie nichts lernen. Gespeichert und versendet wird nichts.
			return self::success( $form, null );
		}

		[ $values, $errors ] = self::process_fields( $form, (array) ( $request['mlab'] ?? array() ) );
		if ( $errors ) {
			return new SubmissionResult( SubmissionResult::INVALID, __( 'Bitte prüfe die markierten Felder.', 'mlab-forms' ), $errors, $values );
		}

		$blocked = self::service_result( SpamGuard::check_services( $form, $request, $values ), $values );
		if ( null !== $blocked ) {
			return $blocked;
		}

		$submission = new Submission( $form, $values, self::meta( $request, $ip_hash ) );
		if ( ! self::store( $submission ) ) {
			return new SubmissionResult( SubmissionResult::ERROR, __( 'Die Anfrage konnte nicht gespeichert werden. Bitte versuche es noch einmal.', 'mlab-forms' ), array(), $values );
		}

		SpamGuard::count( $ip_hash );
		Notifier::send_all( $submission );

		return self::success( $form, $submission );
	}

	/**
	 * Ergebnis, wenn ein Spam-Dienst die Absendung nicht durchlässt.
	 *
	 * @param string               $verdict Ergebnis von SpamGuard::check_services().
	 * @param array<string, mixed> $values  Bereinigte Werte (zum erneuten Ausfüllen).
	 */
	private static function service_result( string $verdict, array $values ): ?SubmissionResult {
		if ( SpamGuard::UNAVAILABLE === $verdict ) {
			return new SubmissionResult( SubmissionResult::UNAVAILABLE, __( 'Der Spam-Schutz ist gerade nicht erreichbar, bitte versuche es später nochmals.', 'mlab-forms' ), array(), $values );
		}
		if ( SpamGuard::BLOCKED === $verdict ) {
			return new SubmissionResult( SubmissionResult::BLOCKED, __( 'Der Spam-Schutz hat deine Anfrage nicht angenommen. Bitte lade die Seite neu und versuche es nochmals.', 'mlab-forms' ), array(), $values );
		}

		return null;
	}

	/**
	 * Bereinigt und prüft alle Felder.
	 *
	 * @param Form                 $form  Formular.
	 * @param array<string, mixed> $input Feld-ID => Rohwert.
	 * @return array{0: array<string, mixed>, 1: array<string, string>} Werte und Fehler.
	 */
	public static function process_fields( Form $form, array $input ): array {
		$values = array();
		$errors = array();

		foreach ( $form->prepared_fields() as $field ) {
			$type = Form::type_of( $field );
			if ( ! $type->is_input() ) {
				continue;
			}
			$values[ $field['id'] ] = $type->sanitize( $input[ $field['id'] ] ?? null, $field );
			$error                  = $type->validate( $values[ $field['id'] ], $field );
			if ( null !== $error ) {
				$errors[ $field['id'] ] = $error;
			}
		}

		return array( $values, $errors );
	}

	/**
	 * Speichert die Anfrage, falls das Formular das vorsieht.
	 *
	 * @param Submission $submission Absendung.
	 */
	private static function store( Submission $submission ): bool {
		if ( ! $submission->form->setting( 'store_entries' ) ) {
			return true;
		}

		$id = EntryRepository::insert( $submission->form->id, $submission->values, $submission->meta );
		if ( ! $id ) {
			return false;
		}
		$submission->entry_id = $id;

		/**
		 * Eine neue Anfrage wurde gespeichert.
		 *
		 * @param int        $id         Anfrage-ID.
		 * @param Submission $submission Absendung.
		 */
		do_action( 'mlab_forms_entry_created', $id, $submission );

		return true;
	}

	/**
	 * Quelle, UTM, Referrer, Sprache, gehashte IP und gekürzter User-Agent.
	 *
	 * @param array<string, mixed> $request Daten.
	 * @param string               $ip_hash Gehashte IP.
	 * @return array<string, mixed>
	 */
	private static function meta( array $request, string $ip_hash ): array {
		$source = esc_url_raw( (string) ( $request['mlab_source'] ?? '' ) );
		$query  = array();
		wp_parse_str( (string) wp_parse_url( $source, PHP_URL_QUERY ), $query );

		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		return array(
			'source'     => $source,
			'referrer'   => esc_url_raw( (string) ( $request['mlab_referrer'] ?? '' ) ),
			'utm'        => array_map( 'sanitize_text_field', array_intersect_key( $query, array_flip( self::UTM_KEYS ) ) ),
			'language'   => determine_locale(),
			'ip_hash'    => $ip_hash,
			'user_agent' => mb_substr( $agent, 0, 200 ),
		);
	}

	/**
	 * Erfolgsmeldung mit Weiterleitung und Tracking.
	 *
	 * @param Form            $form       Formular.
	 * @param Submission|null $submission Absendung; null bei Spam.
	 */
	private static function success( Form $form, ?Submission $submission ): SubmissionResult {
		$tracking = (array) $form->setting( 'tracking' );
		$params   = array( 'form_id' => $form->id );
		foreach ( (array) ( $tracking['ga4_params'] ?? array() ) as $key => $template ) {
			$params[ sanitize_key( (string) $key ) ] = MergeTags::replace( (string) $template, $submission );
		}

		return new SubmissionResult(
			SubmissionResult::SUCCESS,
			MergeTags::replace( (string) $form->setting( 'success_message' ), $submission ),
			array(),
			array(),
			esc_url_raw( (string) $form->setting( 'redirect_url' ) ),
			empty( $tracking['ga4_event'] ) ? array() : array(
				'event'  => sanitize_key( (string) $tracking['ga4_event'] ),
				'params' => $params,
			)
		);
	}
}
