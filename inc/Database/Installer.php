<?php
/**
 * Tabellen und Migrationen.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Database;

use MLab_Forms\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Legt die Tabellen an und hält sie per dbDelta aktuell.
 */
final class Installer {

	/**
	 * Erhöhen, sobald sich die Tabellen ändern.
	 */
	public const DB_VERSION = 1;

	private const VERSION_OPTION = 'mlab_forms_db_version';

	/**
	 * Führt die Migration aus, wenn die gespeicherte Version älter ist.
	 *
	 * Läuft auch nach Updates, bei denen der Aktivierungs-Hook nicht feuert.
	 */
	public static function maybe_upgrade(): void {
		if ( ! self::needs_upgrade() ) {
			return;
		}

		self::create_tables();
		Capabilities::grant();
		update_option( self::VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Ist die gespeicherte Version älter als DB_VERSION?
	 */
	public static function needs_upgrade(): bool {
		return (int) get_option( self::VERSION_OPTION, 0 ) < self::DB_VERSION;
	}

	/**
	 * Name der Anfragen-Tabelle.
	 */
	public static function entries_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'mlab_form_entries';
	}

	/**
	 * Name der Notizen-Tabelle.
	 */
	public static function notes_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'mlab_form_entry_notes';
	}

	/**
	 * Löscht Tabellen und Versionsoption (nur bei der Deinstallation).
	 */
	public static function drop_tables(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Deinstallation entfernt eigene Tabellen.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i, %i', self::entries_table(), self::notes_table() ) );
		delete_option( self::VERSION_OPTION );
	}

	/**
	 * Erstellt oder aktualisiert die Tabellen.
	 */
	private static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$entries = self::entries_table();
		$notes   = self::notes_table();

		// dbDelta verlangt zwei Leerzeichen nach PRIMARY KEY und eine Spalte pro Zeile.
		dbDelta(
			"CREATE TABLE {$entries} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				form_id bigint(20) unsigned NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'new',
				data longtext NOT NULL,
				meta longtext NOT NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				deleted_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY form_status (form_id,status),
				KEY created_at (created_at),
				KEY deleted_at (deleted_at)
			) {$charset};
			CREATE TABLE {$notes} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				entry_id bigint(20) unsigned NOT NULL,
				type varchar(20) NOT NULL DEFAULT 'note',
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				content longtext NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY entry_id (entry_id)
			) {$charset};"
		);
	}
}
