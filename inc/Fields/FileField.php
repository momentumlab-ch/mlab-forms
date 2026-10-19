<?php
/**
 * Datei-Upload, z. B. für einen Lebenslauf.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Fields;

use MLab_Forms\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Feldtyp file: genau eine Datei mit erlaubten Endungen und Grössenlimit.
 *
 * Der Rohwert ist der Eintrag aus $_FILES. Bis zum Speichern bleibt er ein «Upload»,
 * danach speichert Files\FileStore nur Name, Grösse, Typ und den geschützten Pfad.
 */
class FileField extends FieldType {

	public const DEFAULT_ACCEPT = array( 'pdf', 'doc', 'docx' );
	public const DEFAULT_MAX_MB = 5;
	public const LIMIT_MAX_MB   = 20;

	/**
	 * Name des Typs.
	 */
	public function label(): string {
		return __( 'Datei', 'mlab-forms' );
	}

	/**
	 * Erlaubte Endungen des Felds, klein geschrieben.
	 *
	 * @param array<string, mixed> $field Feld.
	 * @return list<string>
	 */
	public static function accept( array $field ): array {
		$list = isset( $field['accept'] ) && is_array( $field['accept'] ) ? $field['accept'] : self::DEFAULT_ACCEPT;
		$list = array_map( static fn( $ext ) => strtolower( ltrim( (string) $ext, '.' ) ), $list );

		return array_values( array_unique( array_filter( $list, static fn( string $ext ) => (bool) preg_match( '/^[a-z0-9]{1,8}$/', $ext ) ) ) );
	}

	/**
	 * Grössenlimit in Byte.
	 *
	 * @param array<string, mixed> $field Feld.
	 */
	public static function max_bytes( array $field ): int {
		$mb = (float) ( $field['max_size'] ?? self::DEFAULT_MAX_MB );
		$mb = max( 0.1, min( (float) self::LIMIT_MAX_MB, $mb ) );

		return (int) min( $mb * MB_IN_BYTES, wp_max_upload_size() );
	}

	/**
	 * Prüft accept und max_size im Schema.
	 *
	 * @param array<string, mixed> $field Feld.
	 * @return list<string>
	 */
	public function schema_errors( array $field ): array {
		$errors = array();
		if ( isset( $field['accept'] ) && ( ! is_array( $field['accept'] ) || array() === self::accept( $field ) ) ) {
			$errors[] = __( 'accept muss eine Liste von Dateiendungen sein, z. B. ["pdf", "docx"].', 'mlab-forms' );
		}
		if ( isset( $field['max_size'] ) && ( ! is_numeric( $field['max_size'] ) || $field['max_size'] <= 0 || $field['max_size'] > self::LIMIT_MAX_MB ) ) {
			/* translators: %d: Höchstgrösse in MB */
			$errors[] = sprintf( __( 'max_size ist die Grösse in MB, höchstens %d.', 'mlab-forms' ), self::LIMIT_MAX_MB );
		}

		return $errors;
	}

	/**
	 * Behält nur einen echten, hochgeladenen Eintrag aus $_FILES.
	 *
	 * @param mixed                $raw   Eintrag aus $_FILES oder bereits gespeicherte Angaben.
	 * @param array<string, mixed> $field Feld.
	 * @return array<string, mixed>|null
	 */
	public function sanitize( $raw, array $field ) {
		if ( ! is_array( $raw ) || ! isset( $raw['error'] ) ) {
			return null;
		}
		$error = (int) $raw['error'];
		if ( UPLOAD_ERR_NO_FILE === $error ) {
			return null;
		}

		return array(
			'upload'   => true,
			'error'    => $error,
			'name'     => sanitize_file_name( wp_basename( (string) ( $raw['name'] ?? '' ) ) ),
			'size'     => (int) ( $raw['size'] ?? 0 ),
			'tmp_name' => (string) ( $raw['tmp_name'] ?? '' ),
		);
	}

	/**
	 * Pflicht, Upload-Fehler, Grösse und Dateityp (Endung und Inhalt).
	 *
	 * @param mixed                $value Bereinigter Wert.
	 * @param array<string, mixed> $field Feld.
	 */
	public function validate( $value, array $field ): ?string {
		if ( null === $value ) {
			return empty( $field['required'] ) ? null : __( 'Bitte wähle eine Datei aus.', 'mlab-forms' );
		}
		if ( UPLOAD_ERR_INI_SIZE === $value['error'] || UPLOAD_ERR_FORM_SIZE === $value['error'] || $value['size'] > self::max_bytes( $field ) ) {
			return $this->size_message( $field );
		}
		if ( UPLOAD_ERR_OK !== $value['error'] || '' === $value['tmp_name'] || ! is_uploaded_file( $value['tmp_name'] ) ) {
			return __( 'Die Datei konnte nicht hochgeladen werden. Bitte versuche es nochmals.', 'mlab-forms' );
		}

		$check = wp_check_filetype_and_ext( $value['tmp_name'], $value['name'] );
		$ext   = strtolower( (string) ( $check['ext'] ?? '' ) );
		if ( '' === $ext || ! in_array( $ext, self::accept( $field ), true ) ) {
			return $this->type_message( $field );
		}

		return null;
	}

	/**
	 * Hinweis zu erlaubten Endungen.
	 *
	 * @param array<string, mixed> $field Feld.
	 */
	public function type_message( array $field ): string {
		/* translators: %s: erlaubte Endungen, z. B. «PDF, DOC, DOCX» */
		return sprintf( __( 'Bitte lade eine Datei im Format %s hoch.', 'mlab-forms' ), strtoupper( implode( ', ', self::accept( $field ) ) ) );
	}

	/**
	 * Hinweis zur Grösse.
	 *
	 * @param array<string, mixed> $field Feld.
	 */
	public function size_message( array $field ): string {
		/* translators: %s: Höchstgrösse, z. B. «5 MB» */
		return sprintf( __( 'Die Datei ist zu gross. Erlaubt sind höchstens %s.', 'mlab-forms' ), size_format( self::max_bytes( $field ) ) );
	}

	/**
	 * Eine Datei wird nie vorausgefüllt.
	 *
	 * @param array<string, mixed> $field   Feld.
	 * @param string|null          $prefill Wert aus der URL.
	 * @return null
	 */
	public function initial_value( array $field, ?string $prefill ) {
		return null;
	}

	/**
	 * Dateiname und Grösse.
	 *
	 * @param mixed                $value Gespeicherte Angaben.
	 * @param array<string, mixed> $field Feld.
	 */
	public function format( $value, array $field ): string {
		if ( ! is_array( $value ) || empty( $value['name'] ) ) {
			return '';
		}

		return (string) $value['name'] . ( empty( $value['size'] ) ? '' : ' (' . size_format( (int) $value['size'] ) . ')' );
	}

	/**
	 * Das input-Element mit accept und Hinweis auf Format und Grösse.
	 *
	 * @param array<string, mixed>       $field Feld.
	 * @param mixed                      $value Wert (wird nie gesetzt).
	 * @param array<string, scalar|null> $aria  aria-Attribute.
	 */
	protected function control( array $field, $value, array $aria ): string {
		$accept = implode( ',', array_map( static fn( string $ext ) => '.' . $ext, self::accept( $field ) ) );

		return Html::tag(
			'input',
			array(
				'type'              => 'file',
				'accept'            => $accept,
				'data-max-size'     => self::max_bytes( $field ),
				'data-size-message' => $this->size_message( $field ),
				'data-type-message' => $this->type_message( $field ),
				'placeholder'       => null,
				'autocomplete'      => null,
			) + $this->base_attributes( $field, $aria ),
			null
		);
	}

	/**
	 * Hilfetext: eigener Text oder automatisch Format und Grösse.
	 *
	 * @param array<string, mixed> $field Feld.
	 * @param string|null          $error Fehlermeldung.
	 * @return array{0: string, 1: array<string, scalar|null>}
	 */
	protected function notes( array $field, ?string $error ): array {
		if ( '' === (string) ( $field['help'] ?? '' ) ) {
			/* translators: 1: Endungen, z. B. «PDF, DOCX», 2: Höchstgrösse, z. B. «5 MB» */
			$field['help'] = sprintf( __( '%1$s, höchstens %2$s', 'mlab-forms' ), strtoupper( implode( ', ', self::accept( $field ) ) ), size_format( self::max_bytes( $field ) ) );
		}

		return parent::notes( $field, $error );
	}
}
