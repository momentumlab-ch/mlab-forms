<?php
/**
 * Status einer Anfrage.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Entries;

defined( 'ABSPATH' ) || exit;

/**
 * Erlaubte Status mit Beschriftung.
 */
final class Status {

	public const NEW = 'new';

	/**
	 * Alle Status in Anzeigereihenfolge.
	 *
	 * @return array<string, string> Schlüssel => Beschriftung.
	 */
	public static function all(): array {
		return array(
			self::NEW     => __( 'Neu', 'mlab-forms' ),
			'in_progress' => __( 'In Bearbeitung', 'mlab-forms' ),
			'answered'    => __( 'Beantwortet', 'mlab-forms' ),
			'booked'      => __( 'Gebucht', 'mlab-forms' ),
			'declined'    => __( 'Abgelehnt', 'mlab-forms' ),
			'spam'        => __( 'Spam', 'mlab-forms' ),
		);
	}

	/**
	 * Ist der Status gültig?
	 *
	 * @param string $status Schlüssel.
	 */
	public static function is_valid( string $status ): bool {
		return array_key_exists( $status, self::all() );
	}

	/**
	 * Beschriftung eines Status.
	 *
	 * @param string $status Schlüssel.
	 */
	public static function label( string $status ): string {
		return self::all()[ $status ] ?? $status;
	}
}
