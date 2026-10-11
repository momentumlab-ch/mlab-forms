<?php
/**
 * Aufräumen beim Löschen des Plugins.
 *
 * @package MLab_Forms
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Im Gerüst gibt es nur die Einstellungen. Formulare und Anfragen folgen später, mit der Option «Daten behalten».
delete_option( 'mlab_forms_settings' );
