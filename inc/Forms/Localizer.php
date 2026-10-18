<?php
/**
 * Texte eines Formulars in einer Sprache.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Löst die Texte eines Schemas in eine Sprache auf: Übersetzung, sonst Standardtext.
 *
 * Die Standardsprache steht in den normalen Eigenschaften; weitere Sprachen liegen unter «i18n».
 * Eine leere Übersetzung gilt als nicht übersetzt. Die Werte von Optionen bleiben in allen Sprachen
 * gleich, nur ihre Beschriftung wechselt. Die Texte laufen danach durch dieselbe Ausgabe wie die Standardtexte.
 */
final class Localizer {

	private const FIELD_TEXTS        = array( 'label', 'placeholder', 'help', 'content' );
	private const SETTING_TEXTS      = array( 'submit_label', 'success_message' );
	private const NOTIFICATION_TEXTS = array( 'subject', 'body' );

	/**
	 * Schema mit den Texten der Sprache.
	 *
	 * @param array<string, mixed> $schema Geprüftes Schema.
	 * @param string               $lang   Sprachcode.
	 * @return array<string, mixed>
	 */
	public static function apply( array $schema, string $lang ): array {
		if ( isset( $schema['settings'] ) && is_array( $schema['settings'] ) ) {
			$schema['settings'] = self::settings( $schema['settings'], $lang );
		}
		if ( isset( $schema['fields'] ) && is_array( $schema['fields'] ) ) {
			$schema['fields'] = array_map(
				static fn( $field ) => is_array( $field ) ? self::field( $field, $lang ) : $field,
				$schema['fields']
			);
		}

		return $schema;
	}

	/**
	 * Ein Text in der Sprache; ohne Übersetzung der Standardtext.
	 *
	 * @param array<string, mixed> $item Feld, Einstellungen oder Benachrichtigung.
	 * @param string               $key  Schlüssel des Texts.
	 * @param string               $lang Sprachcode.
	 */
	public static function text( array $item, string $key, string $lang ): string {
		$translated = $item['i18n'][ $lang ][ $key ] ?? null;
		if ( is_string( $translated ) && '' !== trim( $translated ) ) {
			return $translated;
		}

		return is_scalar( $item[ $key ] ?? null ) ? (string) $item[ $key ] : '';
	}

	/**
	 * Beschriftungen der Optionen in der Sprache; die Werte bleiben.
	 *
	 * @param list<array<string, mixed>> $choices Aufgelöste Optionen (value, label, group).
	 * @param array<string, mixed>       $labels  Wert => Beschriftung.
	 * @return list<array<string, mixed>>
	 */
	public static function choices( array $choices, array $labels ): array {
		foreach ( $choices as $index => $choice ) {
			$label = $labels[ $choice['value'] ] ?? null;
			if ( is_string( $label ) && '' !== trim( $label ) ) {
				$choices[ $index ]['label'] = $label;
			}
		}

		return $choices;
	}

	/**
	 * Ein Feld mit den Texten der Sprache. Die Beschriftungen der Optionen folgen beim Auflösen («option_labels»).
	 *
	 * @param array<string, mixed> $field Feld.
	 * @param string               $lang  Sprachcode.
	 * @return array<string, mixed>
	 */
	private static function field( array $field, string $lang ): array {
		foreach ( self::FIELD_TEXTS as $key ) {
			$text = self::text( $field, $key, $lang );
			if ( '' !== $text ) {
				$field[ $key ] = $text;
			}
		}
		$labels = $field['i18n'][ $lang ]['options'] ?? null;
		if ( is_array( $labels ) && $labels ) {
			$field['option_labels'] = $labels;
		}
		unset( $field['i18n'] );

		return $field;
	}

	/**
	 * Einstellungen mit Button, Erfolgsmeldung und Benachrichtigungen in der Sprache.
	 *
	 * @param array<string, mixed> $settings Einstellungen.
	 * @param string               $lang     Sprachcode.
	 * @return array<string, mixed>
	 */
	private static function settings( array $settings, string $lang ): array {
		foreach ( self::SETTING_TEXTS as $key ) {
			$text = self::text( $settings, $key, $lang );
			if ( '' !== $text ) {
				$settings[ $key ] = $text;
			}
		}
		if ( isset( $settings['notifications'] ) && is_array( $settings['notifications'] ) ) {
			$settings['notifications'] = array_map(
				static fn( $notification ) => is_array( $notification ) ? self::notification( $notification, $lang ) : $notification,
				$settings['notifications']
			);
		}
		unset( $settings['i18n'] );

		return $settings;
	}

	/**
	 * Betreff und Text einer Benachrichtigung in der Sprache.
	 *
	 * @param array<string, mixed> $notification Benachrichtigung.
	 * @param string               $lang         Sprachcode.
	 * @return array<string, mixed>
	 */
	private static function notification( array $notification, string $lang ): array {
		foreach ( self::NOTIFICATION_TEXTS as $key ) {
			$text = self::text( $notification, $key, $lang );
			if ( '' !== $text ) {
				$notification[ $key ] = $text;
			}
		}
		unset( $notification['i18n'] );

		return $notification;
	}
}
