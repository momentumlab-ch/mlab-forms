<?php
/**
 * Prüfung des Formular-Schemas.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Forms;

use MLab_Forms\Fields\FieldTypes;
use MLab_Forms\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Prüft ein Schema gegen die Regeln aus schema/form.schema.json.
 *
 * Fehler kommen als Liste mit Pfad (z. B. «fields.2.options») und deutscher Meldung,
 * damit das Backend sie direkt am Feld anzeigen kann.
 */
final class SchemaValidator {

	public const SCHEMA_VERSION = 1;

	private const ID_PATTERN = '/^[a-z0-9_]{1,64}$/';
	private const WIDTHS     = array( 'full', 'half', 'third' );

	/**
	 * Gesammelte Fehler.
	 *
	 * @var list<array{path: string, message: string}>
	 */
	private array $errors = array();

	/**
	 * Prüft das Schema.
	 *
	 * @param mixed $schema Dekodiertes JSON.
	 * @return list<array{path: string, message: string}> Leer, wenn gültig.
	 */
	public static function validate( $schema ): array {
		$validator = new self();
		$validator->check_root( $schema );

		return $validator->errors;
	}

	/**
	 * Oberste Ebene.
	 *
	 * @param mixed $schema Schema.
	 */
	private function check_root( $schema ): void {
		if ( ! is_array( $schema ) || array_is_list( $schema ) ) {
			$this->error( '', __( 'Das Schema muss ein JSON-Objekt sein.', 'mlab-forms' ) );
			return;
		}
		if ( self::SCHEMA_VERSION !== ( $schema['schema_version'] ?? null ) ) {
			/* translators: %d: erwartete Version */
			$this->error( 'schema_version', sprintf( __( '«schema_version» muss %d sein.', 'mlab-forms' ), self::SCHEMA_VERSION ) );
		}
		if ( isset( $schema['settings'] ) ) {
			$this->check_settings( $schema['settings'] );
		}
		$this->check_fields( $schema['fields'] ?? null );
	}

	/**
	 * Formular-Einstellungen.
	 *
	 * @param mixed $settings Einstellungen.
	 */
	private function check_settings( $settings ): void {
		if ( ! $this->is_object( $settings, 'settings' ) ) {
			return;
		}
		foreach ( array( 'submit_label', 'success_message', 'redirect_url' ) as $key ) {
			$this->optional_string( $settings, $key, 'settings.' . $key );
		}
		if ( isset( $settings['store_entries'] ) && ! is_bool( $settings['store_entries'] ) ) {
			$this->error( 'settings.store_entries', __( 'Muss true oder false sein.', 'mlab-forms' ) );
		}
		$this->optional_setting_int( $settings, 'retention_days', 'retention_days', 'settings.retention_days' );
		if ( isset( $settings['style'] ) && '' !== $settings['style'] && ! isset( Settings::fields()['style_preset']['options'][ $settings['style'] ] ) ) {
			$this->error( 'settings.style', __( '«style» muss «light» oder «dark» sein.', 'mlab-forms' ) );
		}
		if ( isset( $settings['spam'] ) && $this->is_object( $settings['spam'], 'settings.spam' ) ) {
			$this->optional_setting_int( $settings['spam'], 'min_seconds', 'spam_min_seconds', 'settings.spam.min_seconds' );
		}
		$this->check_notifications( $settings['notifications'] ?? array() );
	}

	/**
	 * Benachrichtigungen.
	 *
	 * @param mixed $notifications Liste.
	 */
	private function check_notifications( $notifications ): void {
		if ( ! is_array( $notifications ) || ! array_is_list( $notifications ) ) {
			$this->error( 'settings.notifications', __( 'Muss eine Liste sein.', 'mlab-forms' ) );
			return;
		}
		foreach ( $notifications as $index => $notification ) {
			$path = 'settings.notifications.' . $index;
			if ( ! $this->is_object( $notification, $path ) ) {
				continue;
			}
			foreach ( array( 'to', 'subject' ) as $key ) {
				if ( ! isset( $notification[ $key ] ) || ! is_string( $notification[ $key ] ) || '' === trim( $notification[ $key ] ) ) {
					/* translators: %s: Schlüssel */
					$this->error( $path . '.' . $key, sprintf( __( '«%s» fehlt.', 'mlab-forms' ), $key ) );
				}
			}
			$this->optional_string( $notification, 'body', $path . '.body' );
			$this->optional_string( $notification, 'reply_to', $path . '.reply_to' );
		}
	}

	/**
	 * Feldliste.
	 *
	 * @param mixed $fields Felder.
	 */
	private function check_fields( $fields ): void {
		if ( ! is_array( $fields ) || ! array_is_list( $fields ) || ! $fields ) {
			$this->error( 'fields', __( '«fields» muss eine Liste mit mindestens einem Feld sein.', 'mlab-forms' ) );
			return;
		}

		$ids = array();
		foreach ( $fields as $index => $field ) {
			$path = 'fields.' . $index;
			if ( ! $this->is_object( $field, $path ) ) {
				continue;
			}
			$id = $field['id'] ?? null;
			if ( ! is_string( $id ) || ! preg_match( self::ID_PATTERN, $id ) ) {
				$this->error( $path . '.id', __( '«id» muss aus Kleinbuchstaben, Ziffern und _ bestehen.', 'mlab-forms' ) );
			} elseif ( isset( $ids[ $id ] ) ) {
				/* translators: %s: Feld-ID */
				$this->error( $path . '.id', sprintf( __( 'Die id «%s» kommt mehrfach vor.', 'mlab-forms' ), $id ) );
			}
			$ids[ (string) $id ] = true;
			$this->check_field( $field, $path );
		}
	}

	/**
	 * Ein Feld.
	 *
	 * @param array<string, mixed> $field Feld.
	 * @param string               $path  Pfad.
	 */
	private function check_field( array $field, string $path ): void {
		$type = FieldTypes::get( (string) ( $field['type'] ?? '' ) );
		if ( null === $type ) {
			/* translators: %s: Typ */
			$this->error( $path . '.type', sprintf( __( 'Unbekannter Feldtyp «%s».', 'mlab-forms' ), (string) ( $field['type'] ?? '' ) ) );
			return;
		}
		if ( $type->is_input() && 'hidden' !== $field['type'] && ( ! isset( $field['label'] ) || ! is_string( $field['label'] ) || '' === trim( $field['label'] ) ) ) {
			$this->error( $path . '.label', __( '«label» fehlt.', 'mlab-forms' ) );
		}
		if ( isset( $field['width'] ) && ! in_array( $field['width'], self::WIDTHS, true ) ) {
			$this->error( $path . '.width', __( '«width» muss full, half oder third sein.', 'mlab-forms' ) );
		}
		foreach ( array( 'placeholder', 'help', 'autocomplete', 'link' ) as $key ) {
			$this->optional_string( $field, $key, $path . '.' . $key );
		}
		if ( isset( $field['prefill'] ) ) {
			$this->is_object( $field['prefill'], $path . '.prefill' );
		}
		foreach ( $type->schema_errors( $field ) as $message ) {
			$this->error( $path, $message );
		}
	}

	/**
	 * Prüft, ob der Wert ein Objekt ist, und meldet sonst einen Fehler.
	 *
	 * @param mixed  $value Wert.
	 * @param string $path  Pfad.
	 */
	private function is_object( $value, string $path ): bool {
		if ( is_array( $value ) && ( ! array_is_list( $value ) || array() === $value ) ) {
			return true;
		}
		$this->error( $path, __( 'Muss ein JSON-Objekt sein.', 'mlab-forms' ) );

		return false;
	}

	/**
	 * Optionaler String.
	 *
	 * @param array<string, mixed> $data Daten.
	 * @param string               $key  Schlüssel.
	 * @param string               $path Pfad.
	 */
	private function optional_string( array $data, string $key, string $path ): void {
		if ( isset( $data[ $key ] ) && ! is_string( $data[ $key ] ) ) {
			$this->error( $path, __( 'Muss ein Text sein.', 'mlab-forms' ) );
		}
	}

	/**
	 * Optionale Ganzzahl im Bereich der gleichnamigen globalen Einstellung.
	 *
	 * @param array<string, mixed> $data    Daten.
	 * @param string               $key     Schlüssel.
	 * @param string               $setting Einstellung mit min und max (siehe Settings::fields()).
	 * @param string               $path    Pfad.
	 */
	private function optional_setting_int( array $data, string $key, string $setting, string $path ): void {
		$min = (int) Settings::fields()[ $setting ]['min'];
		$max = (int) Settings::fields()[ $setting ]['max'];
		if ( isset( $data[ $key ] ) && ( ! is_int( $data[ $key ] ) || $data[ $key ] < $min || $data[ $key ] > $max ) ) {
			/* translators: 1: Minimum, 2: Maximum */
			$this->error( $path, sprintf( __( 'Muss eine ganze Zahl von %1$d bis %2$d sein.', 'mlab-forms' ), $min, $max ) );
		}
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
