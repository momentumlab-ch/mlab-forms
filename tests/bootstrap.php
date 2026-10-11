<?php
/**
 * PHPUnit-Bootstrap: lädt das Plugin ohne WordPress, mit schlanken Ersatzfunktionen.
 *
 * @package MLab_Forms
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MLAB_FORMS_VERSION', '0.0.0-test' );
define( 'MLAB_FORMS_FILE', dirname( __DIR__ ) . '/mlab-forms.php' );
define( 'MLAB_FORMS_DIR', dirname( __DIR__ ) . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

require dirname( __DIR__ ) . '/vendor/autoload.php';
require __DIR__ . '/wp-functions.php';
