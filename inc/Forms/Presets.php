<?php
/**
 * Bausteine für den Formular-Builder.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Vordefinierte Felder («Vorname», «E-Mail», «PLZ» …) mit fertiger Prüfung und Autofill.
 *
 * Ein Baustein ist ein Array aus:
 * - key:     eindeutiger Schlüssel,
 * - name:    Name in der Palette,
 * - hint:    kurzer Hinweistext darunter,
 * - id_hint: Wunsch für die interne Feld-ID (optional; das System macht sie eindeutig),
 * - field:   Feld-Schema ohne «id» (type, label, autocomplete, format, width, required …).
 *
 * Erweiterbar über den Filter «mlab_forms_presets».
 */
final class Presets {

	/**
	 * Alle Bausteine in der Reihenfolge der Palette.
	 *
	 * @return list<array{key: string, name: string, hint: string, id_hint: string, field: array<string, mixed>}>
	 */
	public static function all(): array {
		/**
		 * Bausteine des Builders ergänzen, ändern oder entfernen.
		 *
		 * Jeder Baustein: key, name, hint, optional id_hint und field (Feld-Schema ohne id).
		 * Ungültige Bausteine (auch mit ungültigem Feld) werden ignoriert, eine «id» im Feld wird entfernt.
		 *
		 * @param array<int, array<string, mixed>> $presets Bausteine.
		 */
		$presets = apply_filters( 'mlab_forms_presets', self::defaults() );

		return self::clean( (array) $presets );
	}

	/**
	 * Schlüssel der Bausteine, mit denen ein neues, leeres Formular beginnt.
	 *
	 * @return list<string>
	 */
	public static function starter_keys(): array {
		return array( 'first_name', 'last_name', 'email', 'message', 'consent' );
	}

	/**
	 * Entfernt unvollständige Bausteine und doppelte Schlüssel.
	 *
	 * @param array<int|string, mixed> $presets Rohliste (z. B. aus dem Filter).
	 * @return list<array{key: string, name: string, hint: string, id_hint: string, field: array<string, mixed>}>
	 */
	private static function clean( array $presets ): array {
		$clean = array();
		$seen  = array();

		foreach ( $presets as $preset ) {
			if ( ! self::is_valid( $preset ) || isset( $seen[ $preset['key'] ] ) ) {
				continue;
			}
			$field = $preset['field'];
			unset( $field['id'] ); // Die ID vergibt das System.
			if ( ! self::is_valid_field( $field ) ) {
				continue;
			}
			$seen[ $preset['key'] ] = true;
			$clean[]                = array(
				'key'     => $preset['key'],
				'name'    => $preset['name'],
				'hint'    => is_string( $preset['hint'] ?? null ) ? $preset['hint'] : '',
				'id_hint' => is_string( $preset['id_hint'] ?? null ) ? $preset['id_hint'] : '',
				'field'   => $field,
			);
		}

		return $clean;
	}

	/**
	 * Besteht das Feld die Schema-Prüfung (mit einer Dummy-ID)?
	 *
	 * @param array<string, mixed> $field Feld ohne ID.
	 */
	private static function is_valid_field( array $field ): bool {
		$schema = array(
			'schema_version' => SchemaValidator::SCHEMA_VERSION,
			'fields'         => array( array( 'id' => 'baustein' ) + $field ),
		);

		return array() === SchemaValidator::validate( $schema );
	}

	/**
	 * Hat der Baustein Schlüssel, Namen und ein Feld mit Typ?
	 *
	 * @param mixed $preset Eintrag.
	 */
	private static function is_valid( $preset ): bool {
		return is_array( $preset )
			&& isset( $preset['key'], $preset['name'], $preset['field'] )
			&& is_string( $preset['key'] ) && 1 === preg_match( '/^[a-z0-9_]{1,64}$/', $preset['key'] )
			&& is_string( $preset['name'] ) && '' !== $preset['name']
			&& is_array( $preset['field'] ) && is_string( $preset['field']['type'] ?? null );
	}

	/**
	 * Eingebaute Bausteine.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function defaults(): array {
		return array_merge( self::person(), self::address(), self::content() );
	}

	/**
	 * Angaben zur Person.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function person(): array {
		return array(
			self::preset( 'first_name', __( 'Vorname', 'mlab-forms' ), __( 'Kurzer Text', 'mlab-forms' ), 'vorname', 'text', __( 'Vorname', 'mlab-forms' ), 'given-name', array( 'width' => 'half' ) ),
			self::preset( 'last_name', __( 'Nachname', 'mlab-forms' ), __( 'Kurzer Text', 'mlab-forms' ), 'nachname', 'text', __( 'Nachname', 'mlab-forms' ), 'family-name', array( 'width' => 'half' ) ),
			self::preset(
				'email',
				__( 'E-Mail', 'mlab-forms' ),
				__( 'Prüft das Format', 'mlab-forms' ),
				'email',
				'email',
				__( 'E-Mail', 'mlab-forms' ),
				'email',
				array(
					'placeholder' => 'name@beispiel.ch',
					'required'    => true,
				)
			),
			self::preset(
				'phone',
				__( 'Telefon', 'mlab-forms' ),
				__( 'Prüft die Nummer', 'mlab-forms' ),
				'telefon',
				'tel',
				__( 'Telefon', 'mlab-forms' ),
				'tel',
				array( 'placeholder' => '+41 79 123 45 67' )
			),
			self::preset( 'company', __( 'Firma', 'mlab-forms' ), __( 'Kurzer Text', 'mlab-forms' ), 'firma', 'text', __( 'Firma', 'mlab-forms' ), 'organization' ),
		);
	}

	/**
	 * Adresse, Postleitzahl, Ort, Land.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function address(): array {
		return array(
			self::preset( 'address', __( 'Adresse', 'mlab-forms' ), __( 'Strasse und Nummer', 'mlab-forms' ), 'strasse', 'text', __( 'Strasse und Nummer', 'mlab-forms' ), 'street-address' ),
			self::preset(
				'postcode',
				__( 'PLZ', 'mlab-forms' ),
				__( 'Prüft die Postleitzahl', 'mlab-forms' ),
				'plz',
				'text',
				__( 'PLZ', 'mlab-forms' ),
				'postal-code',
				array(
					'format' => 'postcode_ch',
					'width'  => 'half',
				)
			),
			self::preset( 'city', __( 'Ort', 'mlab-forms' ), __( 'Kurzer Text', 'mlab-forms' ), 'ort', 'text', __( 'Ort', 'mlab-forms' ), 'address-level2', array( 'width' => 'half' ) ),
			self::preset(
				'country',
				__( 'Land', 'mlab-forms' ),
				__( 'Auswahl der Länder', 'mlab-forms' ),
				'land',
				'select',
				__( 'Land', 'mlab-forms' ),
				'country-name',
				array(
					'empty_first' => false,
					'options'     => array(
						array(
							'value'   => __( 'Schweiz', 'mlab-forms' ),
							'default' => true,
						),
						__( 'Deutschland', 'mlab-forms' ),
						__( 'Österreich', 'mlab-forms' ),
						__( 'Liechtenstein', 'mlab-forms' ),
						__( 'Frankreich', 'mlab-forms' ),
						__( 'Italien', 'mlab-forms' ),
					),
				)
			),
		);
	}

	/**
	 * Freie Eingaben, Auswahl, Einwilligung und Titel.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function content(): array {
		return array(
			self::preset( 'message', __( 'Nachricht', 'mlab-forms' ), __( 'Mehrzeiliger Text', 'mlab-forms' ), 'nachricht', 'textarea', __( 'Nachricht', 'mlab-forms' ), '', array( 'rows' => 5 ) ),
			self::preset( 'date', __( 'Datum', 'mlab-forms' ), __( 'Prüft das Datum', 'mlab-forms' ), 'datum', 'date', __( 'Datum', 'mlab-forms' ) ),
			self::preset(
				'select',
				__( 'Auswahlliste', 'mlab-forms' ),
				__( 'Eine Option wählen', 'mlab-forms' ),
				'auswahl',
				'select',
				__( 'Auswahl', 'mlab-forms' ),
				'',
				array( 'options' => array( __( 'Option 1', 'mlab-forms' ), __( 'Option 2', 'mlab-forms' ) ) )
			),
			self::preset(
				'multiselect',
				__( 'Mehrfachauswahl', 'mlab-forms' ),
				__( 'Mehrere Optionen wählen', 'mlab-forms' ),
				'mehrfachauswahl',
				'checkbox_group',
				__( 'Mehrfachauswahl', 'mlab-forms' ),
				'',
				array( 'options' => array( __( 'Option 1', 'mlab-forms' ), __( 'Option 2', 'mlab-forms' ) ) )
			),
			self::preset(
				'consent',
				__( 'Einwilligung', 'mlab-forms' ),
				__( 'Pflicht-Häkchen mit Link zur Datenschutzerklärung', 'mlab-forms' ),
				'einwilligung',
				'consent',
				__( 'Ich bin mit der Verarbeitung meiner Angaben einverstanden.', 'mlab-forms' ),
				'',
				array(
					'link'     => '{privacy_url}',
					'required' => true,
				)
			),
			self::preset( 'heading', __( 'Zwischentitel', 'mlab-forms' ), __( 'Nur eine Überschrift', 'mlab-forms' ), 'zwischentitel', 'heading', __( 'Zwischentitel', 'mlab-forms' ) ),
		);
	}

	/**
	 * Baut einen Baustein.
	 *
	 * @param string               $key          Schlüssel.
	 * @param string               $name         Name in der Palette.
	 * @param string               $hint         Hinweistext.
	 * @param string               $id_hint      Wunsch für die Feld-ID.
	 * @param string               $type         Feldtyp.
	 * @param string               $label        Beschriftung des Felds.
	 * @param string               $autocomplete Autofill-Wert (leer: keiner).
	 * @param array<string, mixed> $extra        Weitere Eigenschaften des Felds.
	 * @return array<string, mixed>
	 */
	private static function preset( string $key, string $name, string $hint, string $id_hint, string $type, string $label, string $autocomplete = '', array $extra = array() ): array {
		$field = array(
			'type'  => $type,
			'label' => $label,
		);
		if ( '' !== $autocomplete ) {
			$field['autocomplete'] = $autocomplete;
		}

		return array(
			'key'     => $key,
			'name'    => $name,
			'hint'    => $hint,
			'id_hint' => $id_hint,
			'field'   => $field + $extra,
		);
	}
}
