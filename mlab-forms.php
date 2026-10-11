<?php
/**
 * Plugin Name:       MomentumLab Forms
 * Plugin URI:        https://github.com/momentumlab-ch/mlab-forms
 * Description:       Anfrage- und Kontaktformulare für Kundenseiten von Momentum Lab.
 * Version:           0.1.0
 * Requires at least: 6.9
 * Requires PHP:      8.1
 * Author:            Momentum Lab
 * Author URI:        https://momentumlab.ch
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       mlab-forms
 * Domain Path:       /languages
 *
 * @package MLab_Forms
 */

defined( 'ABSPATH' ) || exit;

define( 'MLAB_FORMS_VERSION', '0.1.0' );
define( 'MLAB_FORMS_FILE', __FILE__ );
define( 'MLAB_FORMS_DIR', plugin_dir_path( __FILE__ ) );

$mlab_forms_autoload = MLAB_FORMS_DIR . 'vendor/autoload.php';

if ( ! is_readable( $mlab_forms_autoload ) ) {
	// Ohne Autoloader (z. B. Checkout ohne «composer install») nicht starten, sondern Hinweis zeigen.
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'MomentumLab Forms: Der Autoloader fehlt. Bitte das Plugin als fertiges ZIP installieren.', 'mlab-forms' );
			echo '</p></div>';
		}
	);
	return;
}

require_once $mlab_forms_autoload;

register_activation_hook( __FILE__, array( MLab_Forms\Activator::class, 'activate' ) );

add_action( 'plugins_loaded', array( MLab_Forms\Plugin::class, 'boot' ) );
