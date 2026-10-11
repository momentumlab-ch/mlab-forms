<?php
/**
 * Aufräumen beim Löschen des Plugins.
 *
 * @package MLab_Forms
 */

use MLab_Forms\Settings;
use MLab_Forms\Updates\GitHubUpdater;

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';

// Standard ist «Daten behalten». Gelöscht wird nur, wenn das in den Einstellungen ausgeschaltet ist.
if ( Settings::get( 'keep_data_on_uninstall' ) ) {
	return;
}

delete_option( Settings::OPTION );
delete_site_transient( GitHubUpdater::CACHE_KEY );
