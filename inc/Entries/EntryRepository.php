<?php
/**
 * Zugriff auf die Anfragen-Tabellen.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Entries;

use MLab_Forms\Database\Installer;

defined( 'ABSPATH' ) || exit;

// Eigene Tabellen: direkte Abfragen sind hier gewollt, Tabellennamen sind fest.
// Platzhalter für WHERE und IN entstehen dynamisch, ihre Anzahl passt zu den Werten.
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

/**
 * Speichert, liest und löscht Anfragen und ihre Notizen.
 *
 * Zeiten sind UTC im MySQL-Format.
 */
final class EntryRepository {

	/**
	 * Legt eine Anfrage an.
	 *
	 * @param int                  $form_id Formular.
	 * @param array<string, mixed> $data    Feldwerte.
	 * @param array<string, mixed> $meta    Metadaten.
	 * @return int ID oder 0 bei Fehler.
	 */
	public static function insert( int $form_id, array $data, array $meta ): int {
		global $wpdb;

		$now = current_time( 'mysql', true );
		$ok  = $wpdb->insert(
			Installer::entries_table(),
			array(
				'form_id'    => $form_id,
				'status'     => Status::NEW,
				'data'       => wp_json_encode( $data, JSON_UNESCAPED_UNICODE ),
				'meta'       => wp_json_encode( $meta, JSON_UNESCAPED_UNICODE ),
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Eine Anfrage (auch gelöschte).
	 *
	 * @param int $id ID.
	 * @return array<string, mixed>|null
	 */
	public static function find( int $id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', Installer::entries_table(), $id ), ARRAY_A );

		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Gefilterte, paginierte Liste.
	 *
	 * @param array{form_id?: int, status?: string, search?: string, since?: string, page?: int, per_page?: int} $args Filter.
	 * @return array{items: list<array<string, mixed>>, total: int}
	 */
	public static function query( array $args ): array {
		global $wpdb;

		[ $where, $params ] = self::where( $args );
		$per_page           = max( 1, min( 500, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset             = ( max( 1, (int) ( $args['page'] ?? 1 ) ) - 1 ) * $per_page;
		$params             = array_merge( array( Installer::entries_table() ), $params );

		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$where}", $params ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM %i WHERE {$where} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", array_merge( $params, array( $per_page, $offset ) ) ),
			ARRAY_A
		);

		return array(
			'items' => array_map( array( self::class, 'hydrate' ), (array) $rows ),
			'total' => $total,
		);
	}

	/**
	 * Anzahl pro Status, optional für ein Formular.
	 *
	 * @param int $form_id Formular oder 0 für alle.
	 * @return array<string, int>
	 */
	public static function count_by_status( int $form_id = 0 ): array {
		global $wpdb;

		[ $where, $params ] = self::where( array( 'form_id' => $form_id ) );
		$params             = array_merge( array( Installer::entries_table() ), $params );
		$rows               = $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) AS n FROM %i WHERE {$where} GROUP BY status", $params ), ARRAY_A );

		return array_map( 'intval', array_column( (array) $rows, 'n', 'status' ) );
	}

	/**
	 * Anzahl nicht gelöschter Anfragen pro Formular.
	 *
	 * @return array<int, int> Formular-ID => Anzahl.
	 */
	public static function count_by_form(): array {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT form_id, COUNT(*) AS n FROM %i WHERE deleted_at IS NULL GROUP BY form_id', Installer::entries_table() ), ARRAY_A );

		return array_map( 'intval', array_column( (array) $rows, 'n', 'form_id' ) );
	}

	/**
	 * Formulare, zu denen es nicht gelöschte Anfragen gibt.
	 *
	 * @return list<int>
	 */
	public static function form_ids(): array {
		global $wpdb;

		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT form_id FROM %i WHERE deleted_at IS NULL', Installer::entries_table() ) ) );
	}

	/**
	 * Setzt den Status und hält den Wechsel als Notiz fest.
	 *
	 * @param int    $id     ID.
	 * @param string $status Neuer Status.
	 */
	public static function set_status( int $id, string $status ): bool {
		global $wpdb;

		$entry = self::find( $id );
		if ( ! $entry || ! Status::is_valid( $status ) || $entry['status'] === $status ) {
			return false;
		}

		$wpdb->update(
			Installer::entries_table(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		self::add_note( $id, 'status', Status::label( $entry['status'] ) . ' → ' . Status::label( $status ) );

		return true;
	}

	/**
	 * Hängt eine Notiz oder einen Verlaufseintrag an.
	 *
	 * @param int    $entry_id Anfrage.
	 * @param string $type     note, status oder log.
	 * @param string $content  Text.
	 */
	public static function add_note( int $entry_id, string $type, string $content ): int {
		global $wpdb;

		$wpdb->insert(
			Installer::notes_table(),
			array(
				'entry_id'   => $entry_id,
				'type'       => $type,
				'user_id'    => get_current_user_id(),
				'content'    => $content,
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Notizen einer Anfrage, älteste zuerst.
	 *
	 * @param int $entry_id Anfrage.
	 * @return list<array<string, mixed>>
	 */
	public static function notes( int $entry_id ): array {
		global $wpdb;

		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE entry_id = %d ORDER BY created_at ASC, id ASC', Installer::notes_table(), $entry_id ), ARRAY_A );
	}

	/**
	 * Markiert alte Anfragen eines Formulars als gelöscht.
	 *
	 * @param int $form_id Formular.
	 * @param int $days    Aufbewahrung in Tagen.
	 * @return int Anzahl.
	 */
	public static function soft_delete_older_than( int $form_id, int $days ): int {
		global $wpdb;

		return (int) $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET deleted_at = %s WHERE form_id = %d AND deleted_at IS NULL AND created_at < %s',
				Installer::entries_table(),
				current_time( 'mysql', true ),
				$form_id,
				gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS )
			)
		);
	}

	/**
	 * Löscht Anfragen endgültig, die seit mehr als $days Tagen als gelöscht markiert sind.
	 *
	 * @param int $days Tage.
	 * @return int Anzahl.
	 */
	public static function purge_deleted( int $days ): int {
		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare( 'SELECT id FROM %i WHERE deleted_at IS NOT NULL AND deleted_at < %s', Installer::entries_table(), gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) )
		);

		return self::delete( array_map( 'intval', (array) $ids ) );
	}

	/**
	 * Löscht Anfragen samt Notizen endgültig.
	 *
	 * @param list<int> $ids IDs.
	 * @return int Anzahl.
	 */
	public static function delete( array $ids ): int {
		global $wpdb;

		$ids = array_filter( array_map( 'intval', $ids ) );
		if ( ! $ids ) {
			return 0;
		}

		$in = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE entry_id IN ({$in})", Installer::notes_table(), ...$ids ) );

		$deleted = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE id IN ({$in})", Installer::entries_table(), ...$ids ) );

		return $deleted;
	}

	/**
	 * Anfragen, deren Daten eine E-Mail-Adresse enthalten (für Auskunft und Löschung).
	 *
	 * Die Suche im JSON ist grob; Aufrufer prüfen die Felder danach genau.
	 *
	 * @param string $email Adresse.
	 * @return list<array<string, mixed>>
	 */
	public static function find_by_email( string $email ): array {
		global $wpdb;

		$like = '%' . $wpdb->esc_like( wp_json_encode( $email ) ) . '%';
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE data LIKE %s ORDER BY id ASC LIMIT 500', Installer::entries_table(), $like ), ARRAY_A );

		return array_map( array( self::class, 'hydrate' ), (array) $rows );
	}

	/**
	 * WHERE-Teil und Parameter aus Filtern. Gelöschte sind immer ausgeschlossen.
	 *
	 * @param array<string, mixed> $args Filter.
	 * @return array{0: string, 1: list<scalar>}
	 */
	private static function where( array $args ): array {
		global $wpdb;

		$where  = array( 'deleted_at IS NULL' );
		$params = array();

		if ( ! empty( $args['form_id'] ) ) {
			$where[]  = 'form_id = %d';
			$params[] = (int) $args['form_id'];
		}
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = (string) $args['status'];
		}
		if ( ! empty( $args['since'] ) ) {
			$where[]  = 'created_at >= %s';
			$params[] = (string) $args['since'];
		}
		if ( ! empty( $args['search'] ) ) {
			$where[]  = 'data LIKE %s';
			$params[] = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
		}

		// prepare() braucht mindestens einen Platzhalter.
		$where[]  = '1 = %d';
		$params[] = 1;

		return array( implode( ' AND ', $where ), $params );
	}

	/**
	 * Dekodiert die JSON-Spalten.
	 *
	 * @param array<string, mixed> $row Zeile.
	 * @return array<string, mixed>
	 */
	private static function hydrate( array $row ): array {
		$row['id']      = (int) $row['id'];
		$row['form_id'] = (int) $row['form_id'];
		$row['data']    = (array) json_decode( (string) $row['data'], true );
		$row['meta']    = (array) json_decode( (string) $row['meta'], true );

		return $row;
	}
}
