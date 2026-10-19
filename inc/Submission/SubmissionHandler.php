<?php
/**
 * Verarbeitung einer Absendung.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Submission;

use MLab_Forms\Entries\EntryRepository;
use MLab_Forms\Fields\FileField;
use MLab_Forms\Files\FileStore;
use MLab_Forms\Forms\Form;
use MLab_Forms\Forms\FormRepository;
use MLab_Forms\Forms\Language;
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
	 * @param int                       $form_id Formular.
	 * @param array<string, mixed>      $request Ungeslashte Daten: mlab (Felder), mlab_hp, mlab_ts, mlab_source, mlab_referrer, mlab_lang.
	 * @param array<string, mixed>|null $files   Uploads je Feld-ID; null liest sie aus $_FILES.
	 */
	public static function handle( int $form_id, array $request, ?array $files = null ): SubmissionResult {
		$form = FormRepository::find( $form_id );
		if ( null === $form ) {
			return new SubmissionResult( SubmissionResult::NOT_FOUND, __( 'Dieses Formular gibt es nicht mehr.', 'mlab-forms' ) );
		}

		$form    = self::localize( $form, $request );
		$ip_hash = IpHasher::hash( IpHasher::client_ip() );
		$spam    = SpamGuard::check( $form, $request, $ip_hash );
		if ( SpamGuard::RATE_LIMITED === $spam ) {
			return new SubmissionResult( SubmissionResult::RATE_LIMITED, __( 'Zu viele Anfragen in kurzer Zeit. Bitte versuche es später noch einmal.', 'mlab-forms' ) );
		}
		if ( SpamGuard::SPAM === $spam ) {
			// Bots bekommen eine Erfolgsmeldung, damit sie nichts lernen. Gespeichert und versendet wird nichts.
			return self::success( $form, null );
		}

		$input               = array_merge( self::without_file_fields( $form, (array) ( $request['mlab'] ?? array() ) ), $files ?? self::uploaded_files() );
		[ $values, $errors ] = self::process_fields( $form, $input );
		$plain               = self::without_uploads( $values );
		if ( $errors ) {
			return new SubmissionResult( SubmissionResult::INVALID, __( 'Bitte prüfe die markierten Felder.', 'mlab-forms' ), $errors, $plain );
		}

		$blocked = self::service_result( SpamGuard::check_services( $form, $request, $plain, $ip_hash ), $plain );
		if ( null !== $blocked ) {
			return $blocked;
		}

		$values = FileStore::persist( $values );
		if ( null === $values ) {
			return new SubmissionResult( SubmissionResult::ERROR, __( 'Die Datei konnte nicht gespeichert werden. Bitte versuche es noch einmal.', 'mlab-forms' ), array(), $plain );
		}

		$submission = new Submission( $form, $values, self::meta( $request, $ip_hash, $form->language() ) );
		if ( ! self::store( $submission ) ) {
			FileStore::delete_values( $values );
			return new SubmissionResult( SubmissionResult::ERROR, __( 'Die Anfrage konnte nicht gespeichert werden. Bitte versuche es noch einmal.', 'mlab-forms' ), array(), $plain );
		}

		SpamGuard::count( $ip_hash );
		Notifier::send_all( $submission );

		// Ohne gespeicherte Anfrage braucht es die Dateien nach dem Versand nicht mehr.
		if ( null === $submission->entry_id ) {
			FileStore::delete_values( $values );
		}

		return self::success( $form, $submission );
	}

	/**
	 * Uploads aus $_FILES['mlab'] als Feld-ID => Eintrag (name, type, tmp_name, error, size).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function uploaded_files(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- öffentliches Formular, Schutz über SpamGuard; FileField prüft die Werte.
		$raw = isset( $_FILES['mlab'] ) && is_array( $_FILES['mlab'] ) ? $_FILES['mlab'] : array();
		if ( ! isset( $raw['name'] ) || ! is_array( $raw['name'] ) ) {
			return array();
		}

		$files = array();
		foreach ( array_keys( $raw['name'] ) as $id ) {
			if ( ! is_string( $id ) || is_array( $raw['name'][ $id ] ) ) {
				continue;
			}
			foreach ( array( 'name', 'type', 'tmp_name', 'error', 'size' ) as $key ) {
				$files[ $id ][ $key ] = $raw[ $key ][ $id ] ?? null;
			}
		}

		return $files;
	}

	/**
	 * Entfernt Werte von Datei-Feldern aus den POST-Daten: Dateien kommen nur als echter Upload.
	 *
	 * @param Form                 $form  Formular.
	 * @param array<string, mixed> $input Feld-ID => Rohwert.
	 * @return array<string, mixed>
	 */
	private static function without_file_fields( Form $form, array $input ): array {
		foreach ( $form->prepared_fields() as $field ) {
			if ( Form::type_of( $field ) instanceof FileField ) {
				unset( $input[ $field['id'] ] );
			}
		}

		return $input;
	}

	/**
	 * Werte ohne Uploads, z. B. für Spam-Dienste und zum erneuten Ausfüllen.
	 *
	 * @param array<string, mixed> $values Werte.
	 * @return array<string, mixed>
	 */
	private static function without_uploads( array $values ): array {
		return array_map( static fn( $value ) => is_array( $value ) && ! empty( $value['upload'] ) ? null : $value, $values );
	}

	/**
	 * Das Formular in der Sprache der Absendung. Eine unbekannte Sprache ergibt die Standardsprache.
	 *
	 * @param Form                 $form    Formular.
	 * @param array<string, mixed> $request Daten mit «mlab_lang».
	 */
	public static function localize( Form $form, array $request ): Form {
		return $form->localized( Language::from_request( $request['mlab_lang'] ?? null, $form->languages() ) );
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
	 * @param string               $lang    Sprache des Formulars; leer bei einsprachigen Formularen (dann gilt die Sprache der Website).
	 * @return array<string, mixed>
	 */
	private static function meta( array $request, string $ip_hash, string $lang ): array {
		$source = esc_url_raw( (string) ( $request['mlab_source'] ?? '' ) );
		$query  = array();
		wp_parse_str( (string) wp_parse_url( $source, PHP_URL_QUERY ), $query );

		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		return array(
			'source'     => $source,
			'referrer'   => esc_url_raw( (string) ( $request['mlab_referrer'] ?? '' ) ),
			'utm'        => array_map( 'sanitize_text_field', array_intersect_key( $query, array_flip( self::UTM_KEYS ) ) ),
			'language'   => '' !== $lang ? $lang : determine_locale(),
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
	public static function success( Form $form, ?Submission $submission ): SubmissionResult {
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
