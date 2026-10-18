<?php
/**
 * Ausgabe der Eingabefelder auf der Einstellungsseite.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Admin;

use MLab_Forms\Html;
use MLab_Forms\Mail\Sender;
use MLab_Forms\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Rendert ein Feld aus Settings::fields().
 */
final class FieldRenderer {

	/**
	 * Callback für add_settings_field.
	 *
	 * @param array{key: string, field: array{type: string, label: string, description?: string, notice?: string, test?: string, options?: array<string, string>, min?: int, max?: int}} $args Argumente.
	 */
	public static function render( array $args ): void {
		$field = $args['field'];
		$attrs = array(
			'id'   => 'mlab_forms_' . $args['key'],
			'name' => Settings::OPTION . '[' . $args['key'] . ']',
		);
		$value = Settings::get( $args['key'] );

		switch ( $field['type'] ) {
			case 'checkbox':
				self::checkbox( $attrs, (bool) $value );
				break;
			case 'password':
				self::password( $attrs, (string) $value, $args['key'] );
				break;
			case 'select':
				self::select( $attrs, (string) $value, $field['options'] ?? array() );
				break;
			default:
				$attrs         += array_intersect_key( $field, array_flip( array( 'min', 'max' ) ) );
				$attrs['class'] = 'number' === $field['type'] ? 'small-text' : 'regular-text';
				self::input( 'emails' === $field['type'] ? 'text' : $field['type'], $attrs, (string) $value );
		}

		self::notes( $field );
		if ( 'email_from_address' === $args['key'] && Sender::is_foreign_address( $value ) ) {
			printf( '<div class="notice notice-warning inline"><p>%s</p></div>', esc_html__( 'Diese Adresse gehört nicht zu deiner Website. Mails können im Spam landen.', 'mlab-forms' ) );
		}
	}

	/**
	 * Beschreibung, Datenschutzhinweis (amber) und Knopf «Verbindung testen».
	 *
	 * @param array{description?: string, notice?: string, test?: string} $field Feldbeschreibung.
	 */
	private static function notes( array $field ): void {
		if ( ! empty( $field['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $field['description'] ) );
		}
		if ( ! empty( $field['notice'] ) ) {
			printf( '<div class="notice notice-warning inline"><p>%s</p></div>', esc_html( $field['notice'] ) );
		}
		if ( ! empty( $field['test'] ) ) {
			printf(
				'<p><button type="button" class="button" data-mlab-test="%s">%s</button> <span class="mlab-test-result" role="status" aria-live="polite"></span></p>',
				esc_attr( $field['test'] ),
				esc_html__( 'Verbindung testen', 'mlab-forms' )
			);
		}
	}

	/**
	 * Passwortfeld für geheime Schlüssel: der gespeicherte Wert steht nie im HTML, nur maskiert als Platzhalter.
	 *
	 * @param array<string, string> $attrs  id und name.
	 * @param string                $secret Gespeicherter Schlüssel.
	 * @param string                $key    Schlüssel der Einstellung.
	 */
	private static function password( array $attrs, string $secret, string $key ): void {
		$mask = Settings::mask( $secret );

		self::input(
			'password',
			$attrs + array(
				'class'        => 'regular-text',
				'autocomplete' => 'new-password',
				'placeholder'  => $mask,
			),
			''
		);
		if ( '' !== $mask ) {
			/* translators: %s: maskierter Schlüssel */
			printf( '<p class="description">%s</p>', esc_html( sprintf( __( 'Gespeichert: %s. Leer lassen, damit er unverändert bleibt.', 'mlab-forms' ), $mask ) ) );
			printf(
				'<p><label><input type="checkbox" name="%s[%s]" value="1"> %s</label></p>',
				esc_attr( Settings::OPTION ),
				esc_attr( Settings::remove_key( $key ) ),
				esc_html__( 'Schlüssel entfernen', 'mlab-forms' )
			);
		}
	}

	/**
	 * Checkbox mit Beschriftung «Aktiv».
	 *
	 * @param array<string, string> $attrs   id und name.
	 * @param bool                  $checked Zustand.
	 */
	private static function checkbox( array $attrs, bool $checked ): void {
		printf(
			'<label><input type="checkbox" value="1" %s %s> %s</label>',
			Html::attributes( $attrs ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in Html::attributes() escaped.
			checked( $checked, true, false ),
			esc_html__( 'Aktiv', 'mlab-forms' )
		);
	}

	/**
	 * Auswahlliste.
	 *
	 * @param array<string, string> $attrs   id und name.
	 * @param string                $value   Gewählter Wert.
	 * @param array<string, string> $options Wert => Beschriftung.
	 */
	private static function select( array $attrs, string $value, array $options ): void {
		echo '<select ' . Html::attributes( $attrs ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in Html::attributes() escaped.
		foreach ( $options as $option => $label ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $option ), selected( $value, $option, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	/**
	 * Einzeiliges Eingabefeld.
	 *
	 * @param string                    $type  HTML-Typ.
	 * @param array<string, int|string> $attrs Attribute.
	 * @param string                    $value Wert.
	 */
	private static function input( string $type, array $attrs, string $value ): void {
		printf(
			'<input type="%s" value="%s" %s>',
			esc_attr( $type ),
			esc_attr( $value ),
			Html::attributes( $attrs ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in Html::attributes() escaped.
		);
	}
}
