<?php
/**
 * Eigene Berechtigungen.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Berechtigungen für Formulare und Anfragen.
 */
final class Capabilities {

	public const MANAGE       = 'mlab_forms_manage';
	public const VIEW_ENTRIES = 'mlab_forms_view_entries';

	/**
	 * Rollen, die beide Berechtigungen bei der Aktivierung bekommen.
	 */
	private const ROLES = array( 'administrator', 'editor' );

	/**
	 * Vergibt die Berechtigungen an Administrator und Redakteur.
	 */
	public static function grant(): void {
		foreach ( self::ROLES as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				$role->add_cap( self::MANAGE );
				$role->add_cap( self::VIEW_ENTRIES );
			}
		}
	}

	/**
	 * Bricht mit «403» ab, wenn die Bedingung nicht erfüllt ist.
	 *
	 * @param bool $allowed Ergebnis der Berechtigungsprüfung.
	 */
	public static function ensure( bool $allowed ): void {
		if ( ! $allowed ) {
			wp_die( esc_html__( 'Dazu fehlt dir die Berechtigung.', 'mlab-forms' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Entfernt die Berechtigungen aus allen Rollen.
	 */
	public static function revoke(): void {
		foreach ( wp_roles()->role_objects as $role ) {
			$role->remove_cap( self::MANAGE );
			$role->remove_cap( self::VIEW_ENTRIES );
		}
	}
}
