<?php
/**
 * Serverseitige Ausgabe eines Formulars.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Frontend;

use MLab_Forms\Fields\FileField;
use MLab_Forms\Fields\Formats;
use MLab_Forms\Forms\Form;
use MLab_Forms\Forms\Language;
use MLab_Forms\Html;
use MLab_Forms\Settings;
use MLab_Forms\Spam\Widget;
use MLab_Forms\Submission\PostHandler;
use MLab_Forms\Submission\RestController;
use MLab_Forms\Submission\SpamGuard;
use MLab_Forms\Submission\SubmissionResult;

defined( 'ABSPATH' ) || exit;

/**
 * Gibt ein Formular als HTML aus. Es funktioniert ohne JavaScript; form.js ergänzt
 * Absenden per fetch, Fehleranzeige ohne Neuladen und Vorauswahl per Link.
 */
final class Renderer {

	/**
	 * Zähler für eindeutige IDs, wenn ein Formular mehrfach auf der Seite steht.
	 *
	 * @var int
	 */
	private static int $instance = 0;

	/**
	 * HTML des Formulars.
	 *
	 * @param Form $form Formular.
	 */
	public static function render( Form $form ): string {
		Assets::enqueue();
		++self::$instance;

		$form = $form->localized( Language::current( $form->languages() ) );

		$result = PostHandler::recall( $form->id );
		if ( $result && $result->ok() ) {
			return self::wrap(
				$form,
				Html::tag(
					'div',
					array(
						'class' => 'mlab-form__success',
						'role'  => 'status',
					),
					esc_html( $result->message )
				)
			);
		}

		return self::wrap( $form, self::form_html( $form, $result ) );
	}

	/**
	 * Vorschau für den Builder: Felder und Absende-Knopf ohne technische Felder, Spam-Schutz und Zustand.
	 *
	 * Gibt nichts aus, speichert nichts und lädt keine Assets.
	 *
	 * @param Form   $form Formular (auch ungespeichert, mit ID 0).
	 * @param string $lang Sprache der Vorschau; leer oder unbekannt: Standardsprache.
	 */
	public static function preview( Form $form, string $lang = '' ): string {
		++self::$instance;

		$form = $form->localized( $lang );

		return self::wrap( $form, self::fields_html( $form, null ) . self::submit_button( $form ) );
	}

	/**
	 * Farbschema des Formulars: eigene Angabe, sonst die Einstellung des Plugins.
	 *
	 * @param Form $form Formular.
	 * @return string light, dark oder leer.
	 */
	public static function style_of( Form $form ): string {
		$style = (string) $form->setting( 'style' );

		return '' !== $style ? $style : (string) Settings::get( 'style_preset' );
	}

	/**
	 * Umschliessender Container mit Farbschema.
	 *
	 * @param Form   $form  Formular.
	 * @param string $inner Escapter Inhalt.
	 */
	private static function wrap( Form $form, string $inner ): string {
		return Html::tag(
			'div',
			array(
				'class' => 'mlab-form mlab-form--' . sanitize_html_class( self::style_of( $form ) ),
				'id'    => 1 === self::$instance ? 'mlab-form-' . $form->id : 'mlab-form-' . $form->id . '-' . self::$instance,
				'style' => Assets::style_vars(),
				'lang'  => self::lang_attribute( $form ),
			),
			$inner
		);
	}

	/**
	 * Sprache für das lang-Attribut, wenn sie von der Seite abweicht.
	 *
	 * @param Form $form Formular in seiner Sprache.
	 */
	private static function lang_attribute( Form $form ): ?string {
		return '' !== $form->language() && Language::page() !== $form->language() ? $form->language() : null;
	}

	/**
	 * Das form-Element mit allen Feldern.
	 *
	 * @param Form                  $form   Formular.
	 * @param SubmissionResult|null $result Ergebnis nach Absenden ohne JS.
	 */
	private static function form_html( Form $form, ?SubmissionResult $result ): string {
		Assets::enqueue_captcha( $form );

		$inner = self::summary( $result )
			. self::hidden_inputs( $form )
			. self::required_note( $form )
			. self::fields_html( $form, $result )
			. Widget::html( $form )
			. self::submit_button( $form );

		return Html::tag(
			'form',
			array(
				'method'         => 'post',
				'action'         => admin_url( 'admin-post.php' ),
				'enctype'        => self::has_files( $form ) ? 'multipart/form-data' : null,
				'data-mlab-form' => true,
				'data-rest'      => rest_url( RestController::NAMESPACE . '/submit' ),
				'data-started'   => rest_url( RestController::NAMESPACE . '/started' ),
				'data-prefill'   => wp_json_encode( self::prefill_config( $form ) ),
				'data-messages'  => wp_json_encode( self::client_messages() ),
			) + Widget::form_attributes( $form ),
			$inner
		);
	}

	/**
	 * Hat das Formular ein Datei-Feld?
	 *
	 * @param Form $form Formular.
	 */
	private static function has_files( Form $form ): bool {
		foreach ( $form->prepared_fields() as $field ) {
			if ( Form::type_of( $field ) instanceof FileField ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Hinweis «* Pflichtfeld», wenn das Formular Pflichtfelder hat.
	 *
	 * Abschalten pro Formular mit settings.required_note = false.
	 *
	 * @param Form $form Formular.
	 */
	private static function required_note( Form $form ): string {
		if ( false === $form->setting( 'required_note' ) ) {
			return '';
		}
		foreach ( $form->prepared_fields() as $field ) {
			if ( ! empty( $field['required'] ) && Form::type_of( $field )->is_input() ) {
				return Html::tag( 'p', array( 'class' => 'mlab-form__required-note' ), '<span class="mlab-form__required" aria-hidden="true">*</span> ' . esc_html__( 'Pflichtfeld', 'mlab-forms' ) );
			}
		}

		return '';
	}

	/**
	 * Container mit allen Feldern.
	 *
	 * @param Form                  $form   Formular.
	 * @param SubmissionResult|null $result Ergebnis nach Absenden ohne JS.
	 */
	private static function fields_html( Form $form, ?SubmissionResult $result ): string {
		$errors = $result ? $result->errors : array();
		$fields = '';
		foreach ( self::fields_with_values( $form, $result ? $result->values : null ) as [ $field, $value ] ) {
			$fields .= Form::type_of( $field )->render( $field, $value, $errors[ $field['id'] ] ?? null );
		}

		return Html::tag( 'div', array( 'class' => 'mlab-form__fields' ), $fields );
	}

	/**
	 * Absende-Knopf.
	 *
	 * @param Form $form Formular.
	 */
	private static function submit_button( Form $form ): string {
		return Html::tag(
			'button',
			array(
				'type'  => 'submit',
				'class' => 'mlab-form__submit',
			),
			esc_html( (string) $form->setting( 'submit_label' ) )
		);
	}

	/**
	 * Fehlerzusammenfassung (leer, bis es Fehler gibt).
	 *
	 * @param SubmissionResult|null $result Ergebnis.
	 */
	private static function summary( ?SubmissionResult $result ): string {
		$text = $result && ! $result->ok() ? esc_html( $result->message ) : '';

		return Html::tag(
			'div',
			array(
				'class'        => 'mlab-form__summary',
				'role'         => 'alert',
				'tabindex'     => '-1',
				'data-mlab'    => 'summary',
				'data-message' => __( 'Bitte prüfe die markierten Felder.', 'mlab-forms' ),
				'hidden'       => '' === $text,
			),
			$text
		);
	}

	/**
	 * Technische Felder: Aktion, Formular, Zeitfalle, Quelle und Honeypot.
	 *
	 * @param Form $form Formular.
	 */
	private static function hidden_inputs( Form $form ): string {
		$hidden = array(
			'action'               => PostHandler::ACTION,
			'form_id'              => (string) $form->id,
			SpamGuard::TOKEN_FIELD => SpamGuard::token( $form->id ),
			'mlab_source'          => self::current_url(),
			'mlab_referrer'        => '',
		);
		if ( '' !== $form->language() ) {
			$hidden['mlab_lang'] = $form->language();
		}

		$html = '';
		foreach ( $hidden as $name => $value ) {
			$html .= Html::tag(
				'input',
				array(
					'type'  => 'hidden',
					'name'  => $name,
					'value' => $value,
				),
				null
			);
		}

		return $html . self::honeypot();
	}

	/**
	 * Honeypot: für Menschen unsichtbar und nicht per Tab erreichbar.
	 */
	private static function honeypot(): string {
		$input = Html::tag(
			'input',
			array(
				'type'         => 'text',
				'name'         => SpamGuard::HONEYPOT_FIELD,
				'tabindex'     => '-1',
				'autocomplete' => 'off',
				'value'        => '',
			),
			null
		);

		return Html::tag(
			'div',
			array(
				'class'       => 'mlab-form__hp',
				'aria-hidden' => 'true',
			),
			$input
		);
	}

	/**
	 * Felder mit HTML-ID und Startwert.
	 *
	 * Reihenfolge des Werts: Eingabe nach Fehler, URL-Parameter (prefill), Standardzeile einer Liste, Standardwert.
	 *
	 * @param Form                      $form   Formular.
	 * @param array<string, mixed>|null $values Werte nach Fehler.
	 * @return list<array{0: array<string, mixed>, 1: mixed}>
	 */
	private static function fields_with_values( Form $form, ?array $values ): array {
		$prefix = 'mlab-' . $form->id . '-' . self::$instance . '-';
		$result = array();

		foreach ( $form->prepared_fields() as $field ) {
			$field['html_id'] = $prefix . $field['id'];
			$value            = $values[ $field['id'] ] ?? Form::type_of( $field )->initial_value( $field, self::prefill_value( $field ) );
			$result[]         = array( self::ensure_choice( $field, $value ), $value );
		}

		return $result;
	}

	/**
	 * Wert aus dem URL-Parameter des Felds.
	 *
	 * @param array<string, mixed> $field Feld.
	 */
	private static function prefill_value( array $field ): ?string {
		$param = Form::prefill_enabled( $field ) ? (string) ( $field['prefill']['param'] ?? '' ) : '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur Vorauswahl.
		return '' !== $param && isset( $_GET[ $param ] ) ? sanitize_text_field( wp_unslash( $_GET[ $param ] ) ) : null;
	}

	/**
	 * Ergänzt einen vorausgewählten Wert, der noch nicht in den Optionen steht.
	 *
	 * @param array<string, mixed> $field Feld.
	 * @param mixed                $value Wert.
	 * @return array<string, mixed>
	 */
	private static function ensure_choice( array $field, $value ): array {
		if ( ! Form::prefill_enabled( $field ) || ! isset( $field['choices'] ) || ! is_string( $value ) || '' === $value ) {
			return $field;
		}
		if ( ! in_array( $value, array_column( $field['choices'], 'value' ), true ) ) {
			$field['choices'][] = array(
				'value'   => $value,
				'label'   => $value,
				'group'   => '',
				'default' => false,
			);
		}

		return $field;
	}

	/**
	 * Vorauswahl per Link-Attribut für form.js.
	 *
	 * @param Form $form Formular.
	 * @return list<array{field: string, attribute: string}>
	 */
	private static function prefill_config( Form $form ): array {
		$config = array();
		foreach ( $form->fields() as $field ) {
			$attribute = Form::prefill_enabled( $field ) ? (string) ( $field['prefill']['data_attribute'] ?? '' ) : '';
			if ( preg_match( '/^data-[a-z0-9-]+$/', $attribute ) ) {
				$config[] = array(
					'field'     => (string) $field['id'],
					'attribute' => $attribute,
				);
			}
		}

		return $config;
	}

	/**
	 * Meldungen für die Prüfung im Browser, damit sie unabhängig von der Browsersprache deutsch sind.
	 *
	 * @return array<string, string>
	 */
	private static function client_messages(): array {
		return array(
			// Dieselben Texte wie bei der Prüfung auf dem Server (Fields\*::validate).
			'valueMissing'    => __( 'Bitte fülle dieses Feld aus.', 'mlab-forms' ),
			'checkbox'        => __( 'Bitte setze diesen Haken.', 'mlab-forms' ),
			'consent'         => __( 'Bitte bestätige die Einwilligung.', 'mlab-forms' ),
			'typeMismatch'    => __( 'Bitte gib eine gültige E-Mail-Adresse ein.', 'mlab-forms' ),
			'patternMismatch' => __( 'Das Format stimmt nicht.', 'mlab-forms' ),
			'tel'             => Formats::message( Formats::TEL ),
			'postcode_ch'     => Formats::message( Formats::POSTCODE_CH ),
			'captchaPending'  => __( 'Der Spam-Schutz prüft noch. Bitte warte einen Moment und sende dann nochmals.', 'mlab-forms' ),
			'captcha'         => __( 'Der Spam-Schutz konnte nicht geladen werden. Bitte lade die Seite neu und versuche es nochmals.', 'mlab-forms' ),
			'invalid'         => __( 'Bitte prüfe diesen Wert.', 'mlab-forms' ),
			'fileMissing'     => __( 'Bitte wähle eine Datei aus.', 'mlab-forms' ),
			'server'          => __( 'Das hat leider nicht geklappt. Bitte versuche es später nochmals.', 'mlab-forms' ),
		);
	}

	/**
	 * Aktuelle Adresse ohne den Ergebnis-Parameter.
	 */
	private static function current_url(): string {
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$origin = (string) preg_replace( '#^(https?://[^/]+).*$#', '$1', home_url() );

		return remove_query_arg( PostHandler::RESULT_PARAM, $origin . $uri );
	}
}
