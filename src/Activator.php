<?php
/**
 * Aktivierung des Plugins.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Legt bei der Aktivierung die Standardeinstellungen an.
 */
final class Activator {

	/**
	 * Wird von register_activation_hook aufgerufen.
	 */
	public static function activate(): void {
		// add_option überschreibt keine bestehenden Werte, eine Reaktivierung behält also die Einstellungen.
		add_option( Settings::OPTION, Settings::defaults() );
	}
}
