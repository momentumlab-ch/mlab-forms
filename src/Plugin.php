<?php
/**
 * Startpunkt des Plugins.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms;

use MLab_Forms\Admin\EntriesPage;
use MLab_Forms\Admin\FormEditor;
use MLab_Forms\Admin\Menu;
use MLab_Forms\Admin\SettingsPage;
use MLab_Forms\Admin\UpdatesTab;
use MLab_Forms\Entries\Retention;
use MLab_Forms\Forms\FormPostType;
use MLab_Forms\Frontend\Assets;
use MLab_Forms\Frontend\Shortcode;
use MLab_Forms\Privacy\Privacy;
use MLab_Forms\Submission\PostHandler;
use MLab_Forms\Submission\RestController;
use MLab_Forms\Updates\GitHubUpdater;

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

		// Updates lösen keine Aktivierung aus: bei neuer Version die Schritte von activate() nachholen.
		if ( Activator::needs_run() ) {
			Activator::activate();
		}

		$updater = new GitHubUpdater();
		$modules = array(
			$updater,
			new FormPostType(),
			new Shortcode(),
			new Assets(),
			new RestController(),
			new PostHandler(),
			new Retention(),
			new Privacy(),
		);
		if ( is_admin() ) {
			array_push( $modules, new Menu(), new FormEditor(), new EntriesPage(), new SettingsPage(), new UpdatesTab( $updater ) );
		}

		foreach ( $modules as $module ) {
			$module->register();
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
