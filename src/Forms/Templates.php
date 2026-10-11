<?php
/**
 * Mitgelieferte Formular-Vorlagen.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Liest die Vorlagen aus dem Ordner «templates».
 */
final class Templates {

	/**
	 * Verfügbare Vorlagen.
	 *
	 * @return array<string, string> Schlüssel => Titel.
	 */
	public static function all(): array {
		return array(
			'kontakt'   => __( 'Kontakt', 'mlab-forms' ),
			'anfrage'   => __( 'Anfrage mit Angebot und Datum', 'mlab-forms' ),
			'gutschein' => __( 'Gutschein', 'mlab-forms' ),
		);
	}

	/**
	 * Schema einer Vorlage.
	 *
	 * @param string $key Schlüssel aus all().
	 * @return array<string, mixed>|null
	 */
	public static function get( string $key ): ?array {
		if ( ! array_key_exists( $key, self::all() ) ) {
			return null;
		}

		$json   = file_get_contents( MLAB_FORMS_DIR . 'templates/' . $key . '.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- lokale Datei.
		$schema = false === $json ? null : json_decode( $json, true );

		return is_array( $schema ) ? $schema : null;
	}
}
