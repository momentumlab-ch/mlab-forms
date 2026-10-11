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
 */
final class Settings {

	public const OPTION = 'mlab_forms_settings';

	/**
	 * Standardwerte. Automatische Updates sind bewusst aus.
	 *
	 * @return array{auto_updates: bool}
	 */
	public static function defaults(): array {
		return array(
			'auto_updates' => false,
		);
	}

	/**
	 * Alle Einstellungen, mit Standardwerten ergänzt.
	 *
	 * @return array{auto_updates: bool}
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );

		return self::sanitize( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Bereinigt Eingaben aus dem Formular oder der Datenbank.
	 *
	 * @param mixed $input Rohwerte.
	 * @return array{auto_updates: bool}
	 */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();

		return array(
			'auto_updates' => ! empty( $input['auto_updates'] ),
		);
	}

	/**
	 * Sind automatische Updates eingeschaltet?
	 */
	public static function auto_updates_enabled(): bool {
		return self::all()['auto_updates'];
	}
}
