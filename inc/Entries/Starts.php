<?php
/**
 * Formular-Abbrüche: zählt, wie oft ein Formular angefangen wurde.
 *
 * Das Skript meldet einmal pro Seitenaufruf die erste Eingabe. Gespeichert wird nur eine Zahl pro Formular und Tag:
 * kein Cookie, keine IP, kein Inhalt. Angefangen minus gesendet ergibt die Abbrüche.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Entries;

use MLab_Forms\Forms\FormRepository;
use MLab_Forms\Submission\RestController;

defined( 'ABSPATH' ) || exit;

/**
 * POST mlab-forms/v1/started und die Auswertung dazu.
 */
final class Starts {

	public const OPTION = 'mlab_forms_starts';

	/**
	 * Tage, die aufbewahrt werden.
	 */
	public const KEEP_DAYS = 400;

	/**
	 * Höchstens so viele Meldungen pro Formular und Tag (Schutz vor Bots).
	 */
	public const DAY_LIMIT = 1000;

	/**
	 * Hängt die Route an.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Öffentliche Route: Gäste füllen Formulare ohne Login aus.
	 */
	public function register_routes(): void {
		register_rest_route(
			RestController::NAMESPACE,
			'/started',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'started' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'form_id' => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
				),
			)
		);
	}

	/**
	 * Zählt einen Start.
	 *
	 * @param \WP_REST_Request $request Anfrage.
	 */
	public function started( \WP_REST_Request $request ): \WP_REST_Response {
		$form_id = (int) $request['form_id'];
		if ( null === FormRepository::find( $form_id ) ) {
			return new \WP_REST_Response( null, 404 );
		}
		self::add( $form_id, wp_date( 'Y-m-d' ) );

		return new \WP_REST_Response( null, 204 );
	}

	/**
	 * Erhöht den Zähler eines Tages.
	 *
	 * @param int    $form_id Formular.
	 * @param string $day     Datum Y-m-d.
	 */
	public static function add( int $form_id, string $day ): void {
		$data = self::all();
		$now  = (int) ( $data[ $form_id ][ $day ] ?? 0 );
		if ( $now >= self::DAY_LIMIT ) {
			return;
		}
		$data[ $form_id ][ $day ] = $now + 1;
		update_option( self::OPTION, self::prune( $data, $day ), false );
	}

	/**
	 * Entfernt Tage, die älter als KEEP_DAYS sind.
	 *
	 * @param array<int, array<string, int>> $data  Zähler.
	 * @param string                         $today Datum Y-m-d.
	 * @return array<int, array<string, int>>
	 */
	public static function prune( array $data, string $today ): array {
		$limit = gmdate( 'Y-m-d', (int) strtotime( $today . ' -' . self::KEEP_DAYS . ' days' ) );
		foreach ( $data as $form_id => $days ) {
			$data[ $form_id ] = array_filter(
				$days,
				static fn( $day ) => (string) $day >= $limit,
				ARRAY_FILTER_USE_KEY
			);
		}

		return array_filter( $data );
	}

	/**
	 * Alle Zähler.
	 *
	 * @return array<int, array<string, int>> Formular-ID => (Datum => Anzahl).
	 */
	public static function all(): array {
		$data = get_option( self::OPTION, array() );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Starts eines Formulars in einem Zeitraum.
	 *
	 * @param int    $form_id Formular.
	 * @param string $from    Erster Tag Y-m-d.
	 * @param string $to      Letzter Tag Y-m-d.
	 */
	public static function count( int $form_id, string $from, string $to ): int {
		$sum = 0;
		foreach ( self::all()[ $form_id ] ?? array() as $day => $n ) {
			if ( (string) $day >= $from && (string) $day <= $to ) {
				$sum += (int) $n;
			}
		}

		return $sum;
	}

	/**
	 * Angefangen, gesendet und abgebrochen pro Formular für die letzten Tage.
	 *
	 * Gesendet zählt erst ab dem ersten gemessenen Start, sonst wären Anfragen von vor dem Update dabei.
	 *
	 * @param int $days Anzahl Tage bis heute.
	 * @return list<array{id: int, title: string, started: int, sent: int, abandoned: int}>
	 */
	public static function funnel( int $days = 28 ): array {
		$to   = wp_date( 'Y-m-d' );
		$from = wp_date( 'Y-m-d', time() - ( max( 1, $days ) - 1 ) * DAY_IN_SECONDS );
		$rows = array();
		foreach ( self::all() as $form_id => $counts ) {
			$first = $counts ? min( array_map( 'strval', array_keys( $counts ) ) ) : $to;
			$start = max( $from, $first );
			$n     = self::count( (int) $form_id, $start, $to );
			if ( 0 === $n ) {
				continue;
			}
			$since  = get_gmt_from_date( $start . ' 00:00:00' );
			$all    = EntryRepository::query(
				array(
					'form_id'  => (int) $form_id,
					'since'    => $since,
					'per_page' => 1,
				)
			)['total'];
			$spam   = EntryRepository::query(
				array(
					'form_id'  => (int) $form_id,
					'status'   => 'spam',
					'since'    => $since,
					'per_page' => 1,
				)
			)['total'];
			$sent   = max( 0, $all - $spam );
			$rows[] = array(
				'id'        => (int) $form_id,
				'title'     => get_the_title( (int) $form_id ),
				'started'   => $n,
				'sent'      => $sent,
				'abandoned' => max( 0, $n - $sent ),
			);
		}

		return $rows;
	}
}
