<?php
/**
 * Aufräumen beim Löschen des Plugins.
 *
 * @package MLab_Forms
 */

use MLab_Forms\Activator;
use MLab_Forms\Capabilities;
use MLab_Forms\Database\Installer;
use MLab_Forms\Entries\Retention;
use MLab_Forms\Forms\FormPostType;
use MLab_Forms\Settings;

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/inc/autoload.php';

Retention::unschedule();

// Standard ist «Daten behalten». Gelöscht wird nur, wenn das in den Einstellungen ausgeschaltet ist.
if ( Settings::get( 'keep_data_on_uninstall' ) ) {
	return;
}

$mlab_forms_ids = get_posts(
	array(
		'post_type'   => FormPostType::POST_TYPE,
		'post_status' => array( 'any', 'trash' ),
		'numberposts' => -1,
		'fields'      => 'ids',
	)
);
foreach ( $mlab_forms_ids as $mlab_forms_id ) {
	wp_delete_post( $mlab_forms_id, true );
}

Installer::drop_tables();
Capabilities::revoke();
delete_option( Settings::OPTION );
delete_option( Activator::VERSION_OPTION );
