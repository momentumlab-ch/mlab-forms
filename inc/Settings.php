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
 * Alle Einstellungen sind in fields() beschrieben. Standardwerte, Bereinigung und
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
	 * Beschreibung aller Einstellungen.
	 *
	 * @return array<string, array{tab: string, type: string, label: string, default: mixed, description?: string, options?: array<string, string>, min?: int, max?: int}>
	 */
	public static function fields(): array {
		static $fields = null;

		$fields ??= array(
			'load_css'               => array(
				'tab'         => 'general',
				'type'        => 'checkbox',
				'label'       => __( 'Formular-Styling laden', 'mlab-forms' ),
				'default'     => true,
				'description' => __( 'Ausschalten, wenn das Theme die Formulare selbst gestaltet.', 'mlab-forms' ),
			),
			'style_preset'           => array(
				'tab'     => 'general',
				'type'    => 'select',
				'label'   => __( 'Farbschema', 'mlab-forms' ),
				'default' => 'light',
				'options' => array(
					'light' => __( 'Hell', 'mlab-forms' ),
					'dark'  => __( 'Dunkel', 'mlab-forms' ),
				),
			),
			'email_from_name'        => array(
				'tab'         => 'email',
				'type'        => 'text',
				'label'       => __( 'Absendername', 'mlab-forms' ),
				'default'     => '',
				'description' => __( 'Leer lassen für den Namen der Website.', 'mlab-forms' ),
			),
			'email_from_address'     => array(
				'tab'         => 'email',
				'type'        => 'email',
				'label'       => __( 'Absenderadresse', 'mlab-forms' ),
				'default'     => '',
				'description' => __( 'Leer lassen für die Admin-Adresse. WP Mail SMTP kann diesen Wert übersteuern.', 'mlab-forms' ),
			),
			'email_admin_to'         => array(
				'tab'         => 'email',
				'type'        => 'email',
				'label'       => __( 'Standard-Empfänger', 'mlab-forms' ),
				'default'     => '',
				'description' => __( 'Empfänger für neue Anfragen, wenn das Formular nichts anderes festlegt. Leer lassen für die Admin-Adresse.', 'mlab-forms' ),
			),
			'spam_honeypot'          => array(
				'tab'     => 'spam',
				'type'    => 'checkbox',
				'label'   => __( 'Honeypot verwenden', 'mlab-forms' ),
				'default' => true,
			),
			'spam_min_seconds'       => array(
				'tab'         => 'spam',
				'type'        => 'number',
				'label'       => __( 'Mindestzeit zum Ausfüllen (Sekunden)', 'mlab-forms' ),
				'default'     => 3,
				'min'         => 0,
				'max'         => 60,
				'description' => __( 'Schnellere Absendungen gelten als Spam. 0 schaltet die Prüfung aus.', 'mlab-forms' ),
			),
			'spam_rate_limit'        => array(
				'tab'         => 'spam',
				'type'        => 'number',
				'label'       => __( 'Maximal Anfragen pro Stunde und Absender', 'mlab-forms' ),
				'default'     => 5,
				'min'         => 0,
				'max'         => 100,
				'description' => __( 'Gezählt wird pro gehashter IP-Adresse. 0 schaltet die Begrenzung aus.', 'mlab-forms' ),
			),
			'retention_days'         => array(
				'tab'         => 'privacy',
				'type'        => 'number',
				'label'       => __( 'Anfragen aufbewahren (Tage)', 'mlab-forms' ),
				'default'     => 365,
				'min'         => 1,
				'max'         => 3650,
				'description' => __( 'Standard für neue Formulare. Ältere Anfragen werden automatisch gelöscht.', 'mlab-forms' ),
			),
			'keep_data_on_uninstall' => array(
				'tab'         => 'privacy',
				'type'        => 'checkbox',
				'label'       => __( 'Daten beim Löschen des Plugins behalten', 'mlab-forms' ),
				'default'     => true,
				'description' => __( 'Ausschalten, damit beim Löschen des Plugins alle Formulare, Anfragen und Einstellungen entfernt werden.', 'mlab-forms' ),
			),
		);

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
