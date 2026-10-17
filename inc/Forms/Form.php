<?php
/**
 * Ein Formular mit geprüftem Schema.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Forms;

use MLab_Forms\Fields\FieldType;
use MLab_Forms\Fields\FieldTypes;
use MLab_Forms\Mail\MergeTags;
use MLab_Forms\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Formular aus dem CPT «mlab_form». Fehlende Einstellungen werden mit
 * Standardwerten aus den globalen Einstellungen ergänzt.
 */
final class Form {

	/**
	 * Einstellungen mit Standardwerten.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings;

	/**
	 * Felder mit bekanntem Typ.
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $fields;

	/**
	 * Zwischenspeicher für prepared_fields().
	 *
	 * @var list<array<string, mixed>>|null
	 */
	private ?array $prepared = null;

	/**
	 * Konstruktor.
	 *
	 * @param int                  $id     Post-ID.
	 * @param string               $title  Titel.
	 * @param array<string, mixed> $schema Geprüftes Schema.
	 */
	public function __construct( public readonly int $id, public readonly string $title, array $schema ) {
		$this->settings = self::with_defaults( (array) ( $schema['settings'] ?? array() ) );
		$this->fields   = array_values( array_filter( (array) ( $schema['fields'] ?? array() ), static fn( $field ) => is_array( $field ) && null !== FieldTypes::get( (string) ( $field['type'] ?? '' ) ) ) );
	}

	/**
	 * Eine Einstellung.
	 *
	 * @param string $key Schlüssel.
	 * @return mixed
	 */
	public function setting( string $key ) {
		return $this->settings[ $key ] ?? null;
	}

	/**
	 * Alle Felder mit bekanntem Typ.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function fields(): array {
		return $this->fields;
	}

	/**
	 * Felder für Ausgabe und Prüfung: Optionen aufgelöst («choices»), Links mit Platzhaltern ersetzt.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function prepared_fields(): array {
		$this->prepared ??= array_map( array( self::class, 'prepare' ), $this->fields );

		return $this->prepared;
	}

	/**
	 * Felder mit Wert (ohne Zwischentitel und HTML).
	 *
	 * @return list<array<string, mixed>>
	 */
	public function input_fields(): array {
		return array_values( array_filter( $this->fields(), static fn( array $field ) => self::type_of( $field )->is_input() ) );
	}

	/**
	 * Ein Feld nach id.
	 *
	 * @param string $id Feld-ID.
	 * @return array<string, mixed>|null
	 */
	public function field( string $id ): ?array {
		foreach ( $this->fields() as $field ) {
			if ( $field['id'] === $id ) {
				return $field;
			}
		}

		return null;
	}

	/**
	 * Feldtyp eines Felds. Nur für Felder aus fields() aufrufen.
	 *
	 * @param array<string, mixed> $field Feld.
	 * @throws \InvalidArgumentException Bei unbekanntem Typ.
	 */
	public static function type_of( array $field ): FieldType {
		$type = FieldTypes::get( (string) $field['type'] );
		if ( null === $type ) {
			throw new \InvalidArgumentException( 'Unbekannter Feldtyp.' );
		}

		return $type;
	}

	/**
	 * Ein Feld für die Ausgabe vorbereiten.
	 *
	 * @param array<string, mixed> $field Feld.
	 * @return array<string, mixed>
	 */
	private static function prepare( array $field ): array {
		if ( isset( $field['options'] ) || isset( $field['options_source'] ) ) {
			$field['choices'] = OptionsResolver::resolve( $field );
		}
		if ( isset( $field['link'] ) ) {
			$field['link'] = MergeTags::replace( (string) $field['link'] );
		}

		return $field;
	}

	/**
	 * Ergänzt Standardwerte. Listen (notifications) werden nicht gemischt, sondern ersetzt.
	 *
	 * @param array<string, mixed> $settings Einstellungen aus dem Schema.
	 * @return array<string, mixed>
	 */
	private static function with_defaults( array $settings ): array {
		$defaults = array(
			'submit_label'    => __( 'Senden', 'mlab-forms' ),
			'success_message' => __( 'Danke! Wir haben deine Nachricht erhalten.', 'mlab-forms' ),
			'store_entries'   => true,
			'retention_days'  => (int) Settings::get( 'retention_days' ),
			'redirect_url'    => '',
			'style'           => '',
			'spam'            => array(
				'honeypot'    => (bool) Settings::get( 'spam_honeypot' ),
				'min_seconds' => (int) Settings::get( 'spam_min_seconds' ),
			),
			'notifications'   => array(
				array(
					'id'       => 'admin',
					'to'       => '{site_admin_email}',
					'subject'  => __( 'Neue Anfrage: {form_title}', 'mlab-forms' ),
					'body'     => '{all_fields}',
					'reply_to' => '',
				),
			),
			'tracking'        => array( 'ga4_event' => 'generate_lead' ),
		);

		$merged         = array_replace( $defaults, $settings );
		$merged['spam'] = array_replace( $defaults['spam'], (array) ( $settings['spam'] ?? array() ) );

		return $merged;
	}
}
