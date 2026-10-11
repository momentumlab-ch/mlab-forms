<?php
/**
 * Aktivierung und Deaktivierung des Plugins.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms;

use MLab_Forms\Database\Installer;
use MLab_Forms\Entries\Retention;

defined( 'ABSPATH' ) || exit;

/**
 * Legt Einstellungen, Tabellen und Berechtigungen an und plant den täglichen Job.
 */
final class Activator {

	/**
	 * Option mit der Plugin-Version, für die activate() zuletzt lief.
	 */
	public const VERSION_OPTION = 'mlab_forms_version';

	/**
	 * Wird von register_activation_hook aufgerufen.
	 */
	public static function activate(): void {
		// add_option überschreibt keine bestehenden Werte, eine Reaktivierung behält also die Einstellungen.
		self::migrate_auto_update_setting();
		add_option( Settings::OPTION, Settings::defaults() );
		Installer::maybe_upgrade();
		Retention::schedule();
		update_option( self::VERSION_OPTION, MLAB_FORMS_VERSION );
	}

	/**
	 * Lief activate() für diese Version noch nicht (z. B. nach einem Update)?
	 */
	public static function needs_run(): bool {
		return MLAB_FORMS_VERSION !== get_option( self::VERSION_OPTION );
	}

	/**
	 * Bis 0.2 gab es einen eigenen Schalter «auto_updates». Seit 0.3 gilt der WordPress-Schalter
	 * in der Plugin-Liste; ein eingeschalteter alter Schalter wird dorthin übernommen.
	 */
	private static function migrate_auto_update_setting(): void {
		$stored = get_option( Settings::OPTION );
		if ( ! is_array( $stored ) || ! array_key_exists( 'auto_updates', $stored ) ) {
			return;
		}

		if ( $stored['auto_updates'] ) {
			$plugins = (array) get_site_option( 'auto_update_plugins', array() );
			update_site_option( 'auto_update_plugins', array_values( array_unique( array_merge( $plugins, array( Plugin::basename() ) ) ) ) );
		}
		unset( $stored['auto_updates'] );
		update_option( Settings::OPTION, $stored );
	}

	/**
	 * Wird von register_deactivation_hook aufgerufen.
	 */
	public static function deactivate(): void {
		Retention::unschedule();
	}
}
