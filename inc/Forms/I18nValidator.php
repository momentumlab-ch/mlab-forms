<?php
/**
 * Prüfung der Sprachen und Übersetzungen im Schema.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Prüft «languages» und alle «i18n»-Angaben (Felder, Einstellungen, Benachrichtigungen).
 *
 * Eine Übersetzung braucht eine Sprache aus «languages», nur bekannte Textschlüssel und nur
 * Texte. Beschriftungen von Optionen gehören zu Werten, die es im Feld wirklich gibt.
 */
final class I18nValidator {

	public const MAX_LANGUAGES = 12;

	private const TEXTS = array(
		'field'        => array( 'label', 'placeholder', 'help', 'content', 'options' ),
		'settings'     => array( 'submit_label', 'success_message' ),
		'notification' => array( 'subject', 'body' ),
	);

	/**
	 * Gesammelte Fehler.
	 *
	 * @var list<array{path: string, message: string}>
	 */
	private array $errors = array();

	/**
	 * Sprachen des Schemas, wie sie gültig sind.
	 *
	 * @var list<string>
	 */
	private array $languages = array();

	/**
	 * Prüft das Schema.
	 *
	 * @param array<string, mixed> $schema Schema (oberste Ebene ist ein Objekt).
	 * @return list<array{path: string, message: string}>
	 */
	public static function validate( array $schema ): array {
		$check = new self();
		$check->check_languages( $schema['languages'] ?? null );
		$check->check_settings( $schema['settings'] ?? null );
		$check->check_fields( $schema['fields'] ?? null );

		return $check->errors;
	}

	/**
	 * Die Liste «languages».
	 *
	 * @param mixed $languages Wert.
	 */
	private function check_languages( $languages ): void {
		if ( null === $languages ) {
			return;
		}
		if ( ! is_array( $languages ) || ! array_is_list( $languages ) || ! $languages || count( $languages ) > self::MAX_LANGUAGES ) {
			/* translators: %d: höchste Anzahl Sprachen */
			$this->error( 'languages', sprintf( __( '«languages» muss eine Liste mit 1 bis %d Sprachcodes sein, z. B. ["de", "fr"].', 'mlab-forms' ), self::MAX_LANGUAGES ) );
			return;
		}
		foreach ( $languages as $index => $code ) {
			if ( ! Language::is_code( $code ) ) {
				$this->error( 'languages.' . $index, __( 'Ein Sprachcode besteht aus zwei oder drei Kleinbuchstaben, z. B. «de».', 'mlab-forms' ) );
			} elseif ( in_array( $code, $this->languages, true ) ) {
				/* translators: %s: Sprachcode */
				$this->error( 'languages.' . $index, sprintf( __( 'Die Sprache «%s» kommt mehrfach vor.', 'mlab-forms' ), $code ) );
			} else {
				$this->languages[] = $code;
			}
		}
	}

	/**
	 * Einstellungen und Benachrichtigungen.
	 *
	 * @param mixed $settings Einstellungen.
	 */
	private function check_settings( $settings ): void {
		if ( ! is_array( $settings ) ) {
			return;
		}
		$this->check_map( $settings, 'settings', 'settings' );
		foreach ( is_array( $settings['notifications'] ?? null ) ? $settings['notifications'] : array() as $index => $notification ) {
			if ( is_array( $notification ) ) {
				$this->check_map( $notification, 'notification', 'settings.notifications.' . $index );
			}
		}
	}

	/**
	 * Felder.
	 *
	 * @param mixed $fields Feldliste.
	 */
	private function check_fields( $fields ): void {
		foreach ( is_array( $fields ) ? $fields : array() as $index => $field ) {
			if ( is_array( $field ) ) {
				$this->check_map( $field, 'field', 'fields.' . $index, $field );
			}
		}
	}

	/**
	 * Das «i18n» eines Felds, einer Benachrichtigung oder der Einstellungen.
	 *
	 * @param array<string, mixed>      $item  Träger von «i18n».
	 * @param string                    $kind  field, settings oder notification.
	 * @param string                    $path  Pfad des Trägers.
	 * @param array<string, mixed>|null $field Das Feld, wenn der Träger eines ist.
	 */
	private function check_map( array $item, string $kind, string $path, ?array $field = null ): void {
		if ( ! isset( $item['i18n'] ) ) {
			return;
		}
		$path .= '.i18n';
		if ( ! is_array( $item['i18n'] ) ) {
			$this->error( $path, __( 'Muss ein JSON-Objekt sein.', 'mlab-forms' ) );
			return;
		}
		foreach ( $item['i18n'] as $code => $texts ) {
			$code = (string) $code;
			if ( ! in_array( $code, $this->languages, true ) ) {
				/* translators: %s: Sprachcode */
				$this->error( $path . '.' . $code, sprintf( __( 'Die Sprache «%s» steht nicht in «languages».', 'mlab-forms' ), $code ) );
			} elseif ( ! is_array( $texts ) ) {
				$this->error( $path . '.' . $code, __( 'Muss ein JSON-Objekt sein.', 'mlab-forms' ) );
			} else {
				$this->check_texts( $texts, $kind, $path . '.' . $code, $field );
			}
		}
	}

	/**
	 * Die Texte einer Sprache.
	 *
	 * @param array<string, mixed>      $texts Texte.
	 * @param string                    $kind  field, settings oder notification.
	 * @param string                    $path  Pfad.
	 * @param array<string, mixed>|null $field Das Feld, wenn es eines ist.
	 */
	private function check_texts( array $texts, string $kind, string $path, ?array $field ): void {
		foreach ( $texts as $key => $value ) {
			$key = (string) $key;
			if ( ! in_array( $key, self::TEXTS[ $kind ], true ) ) {
				/* translators: %s: Schlüssel */
				$this->error( $path . '.' . $key, sprintf( __( '«%s» kann nicht übersetzt werden.', 'mlab-forms' ), $key ) );
			} elseif ( 'options' === $key ) {
				$this->check_options( $value, $path . '.options', (array) $field );
			} elseif ( ! is_string( $value ) ) {
				$this->error( $path . '.' . $key, __( 'Muss ein Text sein.', 'mlab-forms' ) );
			}
		}
	}

	/**
	 * Beschriftungen von Optionen: Wert => Text, nur für Werte, die das Feld hat.
	 *
	 * @param mixed                $labels Wert.
	 * @param string               $path   Pfad.
	 * @param array<string, mixed> $field  Feld.
	 */
	private function check_options( $labels, string $path, array $field ): void {
		if ( ! isset( $field['options'] ) && ! isset( $field['options_source'] ) ) {
			$this->error( $path, __( 'Dieses Feld hat keine Optionen.', 'mlab-forms' ) );
			return;
		}
		if ( ! is_array( $labels ) ) {
			$this->error( $path, __( 'Muss ein JSON-Objekt sein.', 'mlab-forms' ) );
			return;
		}
		$known = self::option_values( $field );
		foreach ( $labels as $value => $label ) {
			if ( ! is_string( $label ) ) {
				$this->error( $path . '.' . $value, __( 'Muss ein Text sein.', 'mlab-forms' ) );
			} elseif ( null !== $known && ! isset( $known[ (string) $value ] ) ) {
				/* translators: %s: Wert einer Option */
				$this->error( $path . '.' . $value, sprintf( __( 'Das Feld hat keine Option mit dem Wert «%s».', 'mlab-forms' ), $value ) );
			}
		}
	}

	/**
	 * Werte der festen Optionen. Null, wenn eine Optionsquelle weitere liefert (dann sind sie nicht bekannt).
	 *
	 * @param array<string, mixed> $field Feld.
	 * @return array<string, true>|null Wert => true.
	 */
	private static function option_values( array $field ): ?array {
		if ( isset( $field['options_source'] ) ) {
			return null;
		}
		$values = array();
		foreach ( (array) ( $field['options'] ?? array() ) as $option ) {
			$values[ (string) ( is_array( $option ) ? ( $option['value'] ?? '' ) : $option ) ] = true;
		}

		return $values;
	}

	/**
	 * Merkt einen Fehler.
	 *
	 * @param string $path    Pfad.
	 * @param string $message Meldung.
	 */
	private function error( string $path, string $message ): void {
		$this->errors[] = array(
			'path'    => $path,
			'message' => $message,
		);
	}
}
