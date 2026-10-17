<?php
/**
 * CSV-Export der Anfragen.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Admin;

use MLab_Forms\Capabilities;
use MLab_Forms\Entries\EntryFormatter;
use MLab_Forms\Entries\EntryRepository;
use MLab_Forms\Entries\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Exportiert die gefilterten Anfragen als CSV (Semikolon, UTF-8 mit BOM, damit Excel in der Schweiz es direkt öffnet).
 */
final class CsvExport {

	private const BATCH = 500;

	/**
	 * Handler für admin-post.php.
	 */
	public static function handle(): void {
		check_admin_referer( 'mlab_forms_export' );
		Capabilities::ensure( current_user_can( Capabilities::VIEW_ENTRIES ) );

		$entries = self::entries( EntriesPage::request_filters() );
		$rows    = array_map( array( self::class, 'row' ), $entries );
		$columns = self::columns( $rows );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="anfragen-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fputcsv( $out, $columns, ';' );
		foreach ( $rows as $row ) {
			fputcsv( $out, array_map( static fn( string $column ) => self::cell( $row[ $column ] ?? '' ), $columns ), ';' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Alle Anfragen zu den Filtern, seitenweise geladen.
	 *
	 * @param array<string, mixed> $filters Filter.
	 * @return list<array<string, mixed>>
	 */
	private static function entries( array $filters ): array {
		$all  = array();
		$page = 1;
		do {
			$result = EntryRepository::query(
				$filters + array(
					'page'     => $page++,
					'per_page' => self::BATCH,
				)
			);
			$all    = array_merge( $all, $result['items'] );
			$more   = $result['items'] && count( $all ) < $result['total'];
		} while ( $more );

		return $all;
	}

	/**
	 * Eine Zeile: feste Spalten und alle Felder.
	 *
	 * @param array<string, mixed> $entry Anfrage.
	 * @return array<string, string>
	 */
	private static function row( array $entry ): array {
		return array(
			'ID'          => (string) $entry['id'],
			'Formular'    => get_the_title( $entry['form_id'] ),
			'Status'      => Status::label( $entry['status'] ),
			'Eingegangen' => EntriesPage::local_date( $entry['created_at'] ),
		) + EntryFormatter::rows( $entry );
	}

	/**
	 * Alle Spalten in der Reihenfolge ihres ersten Auftretens.
	 *
	 * @param list<array<string, string>> $rows Zeilen.
	 * @return list<string>
	 */
	private static function columns( array $rows ): array {
		$columns = array( 'ID', 'Formular', 'Status', 'Eingegangen' );
		foreach ( $rows as $row ) {
			$columns = array_merge( $columns, array_diff( array_map( 'strval', array_keys( $row ) ), $columns ) );
		}

		return array_values( array_unique( $columns ) );
	}

	/**
	 * Schützt vor Formeln in Tabellenprogrammen (CSV-Injection).
	 *
	 * @param string $value Zellwert.
	 */
	private static function cell( string $value ): string {
		return preg_match( '/^[=+\-@\t\r]/', $value ) ? "'" . $value : $value;
	}
}
