<?php
/**
 * Serverseitige Ausgabe eines Formulars.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Frontend;

use MLab_Forms\Forms\Form;
use MLab_Forms\Html;
use MLab_Forms\Settings;
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
	 * Umschliessender Container mit Farbschema.
	 *
	 * @param Form   $form  Formular.
	 * @param string $inner Escapter Inhalt.
	 */
	private static function wrap( Form $form, string $inner ): string {
		$style = (string) $form->setting( 'style' );
		$style = '' !== $style ? $style : (string) Settings::get( 'style_preset' );

		return Html::tag(
			'div',
			array(
				'class' => 'mlab-form mlab-form--' . sanitize_html_class( $style ),
				'id'    => 1 === self::$instance ? 'mlab-form-' . $form->id : 'mlab-form-' . $form->id . '-' . self::$instance,
				'style' => Assets::style_vars(),
			),
			$inner
		);
	}

	/**
	 * Das form-Element mit allen Feldern.
	 *
	 * @param Form                  $form   Formular.
	 * @param SubmissionResult|null $result Ergebnis nach Absenden ohne JS.
	 */
	private static function form_html( Form $form, ?SubmissionResult $result ): string {
		$errors = $result ? $result->errors : array();
		$fields = '';
		foreach ( self::fields_with_values( $form, $result ? $result->values : null ) as [ $field, $value ] ) {
			$fields .= Form::type_of( $field )->render( $field, $value, $errors[ $field['id'] ] ?? null );
		}

		$inner = self::summary( $result )
			. self::hidden_inputs( $form )
			. Html::tag( 'div', array( 'class' => 'mlab-form__fields' ), $fields )
			. Html::tag(
				'button',
				array(
					'type'  => 'submit',
					'class' => 'mlab-form__submit',
				),
				esc_html( (string) $form->setting( 'submit_label' ) )
			);

		return Html::tag(
			'form',
			array(
				'method'         => 'post',
				'action'         => admin_url( 'admin-post.php' ),
				'data-mlab-form' => true,
				'data-rest'      => rest_url( RestController::NAMESPACE . '/submit' ),
				'data-prefill'   => wp_json_encode( self::prefill_config( $form ) ),
				'data-messages'  => wp_json_encode( self::client_messages() ),
			),
			$inner
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

		// Honeypot: für Menschen unsichtbar und nicht per Tab erreichbar.
		$honeypot = Html::tag(
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

		return $html . Html::tag(
			'div',
			array(
				'class'       => 'mlab-form__hp',
				'aria-hidden' => 'true',
			),
			$honeypot
		);
	}

	/**
	 * Felder mit HTML-ID und Startwert.
	 *
	 * Reihenfolge des Werts: Eingabe nach Fehler, URL-Parameter (prefill), Standardwert.
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
			$value            = $values[ $field['id'] ] ?? self::prefill_value( $field ) ?? ( $field['default'] ?? '' );
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
		$param = (string) ( $field['prefill']['param'] ?? '' );

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
		if ( empty( $field['prefill'] ) || ! isset( $field['choices'] ) || ! is_string( $value ) || '' === $value ) {
			return $field;
		}
		if ( ! in_array( $value, array_column( $field['choices'], 'value' ), true ) ) {
			$field['choices'][] = array(
				'value' => $value,
				'label' => $value,
				'group' => '',
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
			$attribute = (string) ( $field['prefill']['data_attribute'] ?? '' );
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
			'invalid'         => __( 'Bitte prüfe diesen Wert.', 'mlab-forms' ),
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
