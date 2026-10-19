<?php
/**
 * Verzeichnis der Feldtypen.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Liefert die Feldtypen, erweiterbar über den Filter «mlab_forms_field_types».
 */
final class FieldTypes {

	/**
	 * Zwischenspeicher pro Anfrage.
	 *
	 * @var array<string, FieldType>|null
	 */
	private static ?array $types = null;

	/**
	 * Alle Feldtypen.
	 *
	 * @return array<string, FieldType>
	 */
	public static function all(): array {
		if ( null === self::$types ) {
			/**
			 * Feldtypen ergänzen oder ersetzen.
			 *
			 * @param array<string, FieldType> $types Typ-Schlüssel => Feldtyp.
			 */
			$types = apply_filters( 'mlab_forms_field_types', self::defaults() );
			// @phpstan-ignore instanceof.alwaysTrue (Filter können Fremdes liefern.)
			self::$types = array_filter( (array) $types, static fn( $type ) => $type instanceof FieldType );
		}

		return self::$types;
	}

	/**
	 * Ein Feldtyp oder null, wenn unbekannt.
	 *
	 * @param string $type Typ-Schlüssel.
	 */
	public static function get( string $type ): ?FieldType {
		return self::all()[ $type ] ?? null;
	}

	/**
	 * Eingebaute Feldtypen.
	 *
	 * @return array<string, FieldType>
	 */
	private static function defaults(): array {
		return array(
			'text'           => new TextField( 'text', __( 'Text', 'mlab-forms' ) ),
			'email'          => new TextField( 'email', __( 'E-Mail', 'mlab-forms' ) ),
			'tel'            => new TextField( 'tel', __( 'Telefon', 'mlab-forms' ) ),
			'hidden'         => new TextField( 'hidden', __( 'Verstecktes Feld', 'mlab-forms' ) ),
			'textarea'       => new TextareaField(),
			'number'         => new NumberField(),
			'date'           => new DateField(),
			'select'         => new ChoiceField( 'select', __( 'Auswahlliste', 'mlab-forms' ) ),
			'radio'          => new ChoiceField( 'radio', __( 'Optionsfelder', 'mlab-forms' ) ),
			'checkbox_group' => new ChoiceField( 'checkbox_group', __( 'Mehrfachauswahl', 'mlab-forms' ) ),
			'checkbox'       => new CheckboxField( 'checkbox', __( 'Checkbox', 'mlab-forms' ) ),
			'consent'        => new CheckboxField( 'consent', __( 'Einwilligung', 'mlab-forms' ) ),
			'file'           => new FileField(),
			'heading'        => new ContentField( 'heading', __( 'Zwischentitel', 'mlab-forms' ) ),
			'html'           => new ContentField( 'html', __( 'HTML', 'mlab-forms' ) ),
		);
	}
}
