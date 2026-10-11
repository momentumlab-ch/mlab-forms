<?php
/**
 * Ausgabe der Eingabefelder auf der Einstellungsseite.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Admin;

use MLab_Forms\Html;
use MLab_Forms\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Rendert ein Feld aus Settings::fields().
 */
final class FieldRenderer {

	/**
	 * Callback für add_settings_field.
	 *
	 * @param array{key: string, field: array{type: string, label: string, description?: string, options?: array<string, string>, min?: int, max?: int}} $args Argumente.
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
			case 'select':
				self::select( $attrs, (string) $value, $field['options'] ?? array() );
				break;
			default:
				$attrs         += array_intersect_key( $field, array_flip( array( 'min', 'max' ) ) );
				$attrs['class'] = 'number' === $field['type'] ? 'small-text' : 'regular-text';
				self::input( $field['type'], $attrs, (string) $value );
		}

		if ( ! empty( $field['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $field['description'] ) );
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
