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
	 * Name des Felds «Schlüssel entfernen» zu einem geheimen Schlüssel.
	 *
	 * @param string $key Schlüssel aus fields().
	 */
	public static function remove_key( string $key ): string {
		return '_remove_' . $key;
	}

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
	 * Abschnitte innerhalb eines Tabs. Felder ohne «section» stehen im ersten Abschnitt des Tabs.
	 *
	 * @return array<string, array{tab: string, title: string, text: string}>
	 */
	public static function sections(): array {
		return array(
			'whatsapp' => array(
				'tab'   => 'general',
				'title' => __( 'WhatsApp', 'mlab-forms' ),
				'text'  => __( 'Ein Knopf, der WhatsApp mit einer vorausgefüllten Nachricht öffnet. Auf einer Seite mit dem Shortcode [mlab_whatsapp], oder als runder Knopf unten rechts auf allen Seiten. Es ist ein normaler Link: Es wird nichts von WhatsApp geladen und kein Cookie gesetzt.', 'mlab-forms' ),
			),
			'sender'   => array(
				'tab'   => 'email',
				'title' => __( 'Absender', 'mlab-forms' ),
				'text'  => __( 'Die Absender-E-Mail sollte zu deiner eigenen Domain passen (zum Beispiel info@deine-firma.ch). Sonst stufen viele Postfächer die Nachrichten als Spam ein.', 'mlab-forms' ),
			),
			'services' => array(
				'tab'   => 'spam',
				'title' => __( 'Spam-Dienste', 'mlab-forms' ),
				'text'  => __( 'Honeypot und Mindestzeit sind immer aktiv. Die folgenden Dienste sind ausgeschaltet, bis du sie hier einrichtest und im Formular wählst. Sie senden Daten an Dritte. Speichere zuerst, dann kannst du die Verbindung testen.', 'mlab-forms' ),
			),
		);
	}

	/**
	 * Beschreibung aller Einstellungen aus inc/options.php.
	 *
	 * @return array<string, array{tab: string, type: string, label: string, default: mixed, section?: string, description?: string, notice?: string, test?: string, options?: array<string, string>, min?: int, max?: int}>
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
		$stored = is_array( $stored ) ? $stored : array();
		$values = array_merge( self::defaults(), array_intersect_key( $stored, self::fields() ) );

		// Frühere Versionen speicherten den Standard-Empfänger als «email_admin_to».
		if ( '' === trim( (string) $values['default_recipient'] ) && ! empty( $stored['email_admin_to'] ) && is_string( $stored['email_admin_to'] ) ) {
			$values['default_recipient'] = $stored['email_admin_to'];
		}

		return $values;
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
			$result[ $key ] = self::sanitize_value( $field, $input[ $key ] ?? null, $result[ $key ] ?? null, ! empty( $input[ self::remove_key( $key ) ] ) );
		}

		return $result;
	}

	/**
	 * Bereinigt einen Wert nach Feldtyp.
	 *
	 * @param array{type: string, default: mixed, options?: array<string, string>, min?: int, max?: int} $field   Feldbeschreibung.
	 * @param mixed                                                                                      $value   Rohwert.
	 * @param mixed                                                                                      $current Bisher gespeicherter Wert.
	 * @param bool                                                                                       $remove  Geheimen Schlüssel löschen («Schlüssel entfernen» angehakt).
	 * @return mixed
	 */
	private static function sanitize_value( array $field, $value, $current = null, bool $remove = false ) {
		switch ( $field['type'] ) {
			case 'password':
				// Geheime Schlüssel werden nie angezeigt: leer gelassen bleibt der gespeicherte Wert, mit «Schlüssel entfernen» wird er gelöscht.
				if ( $remove ) {
					return '';
				}
				$secret = trim( sanitize_text_field( (string) $value ) );
				return '' === $secret ? (string) $current : $secret;
			case 'checkbox':
				return ! empty( $value );
			case 'number':
				$number = is_numeric( $value ) ? (int) $value : (int) $field['default'];
				return max( $field['min'] ?? PHP_INT_MIN, min( $field['max'] ?? PHP_INT_MAX, $number ) );
			case 'email':
				return sanitize_email( (string) $value );
			case 'emails':
				return Mail\Recipients::clean( $value );
			case 'select':
				return array_key_exists( (string) $value, $field['options'] ?? array() ) ? (string) $value : $field['default'];
			default:
				return sanitize_text_field( (string) $value );
		}
	}

	/**
	 * Maskierte Darstellung eines geheimen Werts, z. B. «••••••••a1b2».
	 *
	 * @param string $secret Wert.
	 * @return string Leer, wenn nichts gespeichert ist.
	 */
	public static function mask( string $secret ): string {
		if ( '' === $secret ) {
			return '';
		}

		return str_repeat( '•', 8 ) . ( strlen( $secret ) > 12 ? substr( $secret, -4 ) : '' );
	}
}
