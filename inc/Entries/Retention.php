<?php
/**
 * Automatisches Löschen alter Anfragen.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Entries;

use MLab_Forms\Forms\FormRepository;
use MLab_Forms\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Täglicher WP-Cron: Anfragen älter als «retention_days» werden als gelöscht markiert,
 * 30 Tage später endgültig gelöscht.
 */
final class Retention {

	public const HOOK       = 'mlab_forms_daily';
	public const GRACE_DAYS = 30;

	/**
	 * Hängt den Job an.
	 */
	public function register(): void {
		add_action( self::HOOK, array( self::class, 'run' ) );
	}

	/**
	 * Plant den täglichen Lauf, falls noch nicht geplant.
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	/**
	 * Entfernt den geplanten Lauf.
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Markiert alte Anfragen pro Formular und löscht abgelaufene endgültig.
	 */
	public static function run(): void {
		// Aus der Tabelle statt aus den Beiträgen: so laufen auch Anfragen gelöschter Formulare ab.
		foreach ( EntryRepository::form_ids() as $form_id ) {
			EntryRepository::soft_delete_older_than( $form_id, self::retention_days( $form_id ) );
		}
		EntryRepository::purge_deleted( self::GRACE_DAYS );
	}

	/**
	 * Aufbewahrung eines Formulars; Standard aus den Einstellungen.
	 *
	 * @param int $form_id Formular.
	 */
	public static function retention_days( int $form_id ): int {
		$schema = FormRepository::schema( $form_id );
		$days   = (int) ( $schema['settings']['retention_days'] ?? Settings::get( 'retention_days' ) );

		return max( 1, $days );
	}
}
