<?php
/**
 * Startpunkt des Plugins.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms;

use MLab_Forms\Admin\SettingsPage;
use MLab_Forms\Updates\AutoUpdate;

defined( 'ABSPATH' ) || exit;

/**
 * Registriert alle Module.
 */
final class Plugin {

	/**
	 * Hängt die Module an WordPress an.
	 */
	public static function boot(): void {
		add_action( 'init', array( self::class, 'load_textdomain' ) );

		( new AutoUpdate() )->register();

		if ( is_admin() ) {
			( new SettingsPage() )->register();
		}
	}

	/**
	 * Lädt die Übersetzungen.
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain( 'mlab-forms', false, dirname( plugin_basename( MLAB_FORMS_FILE ) ) . '/languages' );
	}

	/**
	 * Basisname des Plugins, z. B. «mlab-forms/mlab-forms.php».
	 */
	public static function basename(): string {
		return plugin_basename( MLAB_FORMS_FILE );
	}
}
