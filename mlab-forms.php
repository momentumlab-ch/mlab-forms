<?php
/**
 * Plugin Name:       MomentumLab Forms
 * Plugin URI:        https://github.com/momentumlab-ch/mlab-forms
 * Description:       Anfrage- und Kontaktformulare für Kundenseiten von Momentum Lab.
 * Version:           0.5.0
 * Requires at least: 6.9
 * Requires PHP:      8.1
 * Author:            Momentum Lab
 * Author URI:        https://momentumlab.ch
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://github.com/momentumlab-ch/mlab-forms
 * Text Domain:       mlab-forms
 * Domain Path:       /languages
 *
 * @package MLab_Forms
 */

defined( 'ABSPATH' ) || exit;

define( 'MLAB_FORMS_VERSION', '0.5.0' );
define( 'MLAB_FORMS_FILE', __FILE__ );
define( 'MLAB_FORMS_DIR', plugin_dir_path( __FILE__ ) );

require_once MLAB_FORMS_DIR . 'inc/autoload.php';
require_once MLAB_FORMS_DIR . 'inc/updater.php';

register_activation_hook( __FILE__, array( MLab_Forms\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( MLab_Forms\Activator::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( MLab_Forms\Plugin::class, 'boot' ) );
