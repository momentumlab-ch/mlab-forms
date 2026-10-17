<?php
/**
 * Zugriff auf die Plugin-Einstellungen.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Liest und schreibt die Option «mlab_forms_settings».
 *
 * Alle Einstellungen sind in inc/options.php beschrieben. Standardwerte, Bereinigung und
 * die Einstellungsseite leiten sich daraus ab.
 */
final class Settings {

	public const OPTION = 'mlab_forms_settings';

	/**
	 * Hidden-Feld, das den gespeicherten Tab mitschickt.
	 */
	public const TAB_KEY = '_tab';

	/**
	 * Tabs der Einstellungsseite, in Anzeigereihenfolge.
	 *
	 * @return array<string, string> Schlüssel => Titel.
	 */
	public static function tabs(): array {
		return array(
			'general' => __( 'Allgemein', 'mlab-forms' ),
			'email'   => __( 'E-Mail', 'mlab-forms' ),
			'spam'    => __( 'Spam-Schutz', 'mlab-forms' ),
			'privacy' => __( 'Datenschutz', 'mlab-forms' ),
			'updates' => __( 'Updates', 'mlab-forms' ),
		);
	}

	/**
	 * Beschreibung aller Einstellungen aus inc/options.php.
	 *
	 * @return array<string, array{tab: string, type: string, label: string, default: mixed, description?: string, options?: array<string, string>, min?: int, max?: int}>
	 */
	public static function fields(): array {
		static $fields = null;

		$fields ??= require __DIR__ . '/options.php';

		return $fields;
	}

	/**
	 * Standardwerte aller Einstellungen.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array_map( static fn( array $field ) => $field['default'], self::fields() );
	}

	/**
	 * Alle Einstellungen, mit Standardwerten ergänzt.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );

		return array_merge( self::defaults(), is_array( $stored ) ? array_intersect_key( $stored, self::fields() ) : array() );
	}

	/**
	 * Ein einzelner Wert.
	 *
	 * @param string $key Schlüssel aus fields().
	 * @return mixed
	 */
	public static function get( string $key ) {
		return self::all()[ $key ] ?? null;
	}

	/**
	 * Bereinigt Eingaben der Einstellungsseite.
	 *
	 * Mit Tab-Kennung werden nur die Felder dieses Tabs übernommen, die übrigen bleiben
	 * wie gespeichert. Ohne Kennung (z. B. beim zweiten Durchlauf von add_option) wird
	 * das ganze Array bereinigt.
	 *
	 * @param mixed $input Rohwerte.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ): array {
		$input  = is_array( $input ) ? $input : array();
		$tab    = isset( $input[ self::TAB_KEY ] ) ? (string) $input[ self::TAB_KEY ] : null;
		$fields = null === $tab
			? array_intersect_key( self::fields(), $input )
			: array_filter( self::fields(), static fn( array $field ) => $field['tab'] === $tab );
		$result = self::all();

		foreach ( $fields as $key => $field ) {
			$result[ $key ] = self::sanitize_value( $field, $input[ $key ] ?? null );
		}

		return $result;
	}

	/**
	 * Bereinigt einen Wert nach Feldtyp.
	 *
	 * @param array{type: string, default: mixed, options?: array<string, string>, min?: int, max?: int} $field Feldbeschreibung.
	 * @param mixed                                                                                      $value Rohwert.
	 * @return mixed
	 */
	private static function sanitize_value( array $field, $value ) {
		switch ( $field['type'] ) {
			case 'checkbox':
				return ! empty( $value );
			case 'number':
				$number = is_numeric( $value ) ? (int) $value : (int) $field['default'];
				return max( $field['min'] ?? PHP_INT_MIN, min( $field['max'] ?? PHP_INT_MAX, $number ) );
			case 'email':
				return sanitize_email( (string) $value );
			case 'select':
				return array_key_exists( (string) $value, $field['options'] ?? array() ) ? (string) $value : $field['default'];
			default:
				return sanitize_text_field( (string) $value );
		}
	}
}
