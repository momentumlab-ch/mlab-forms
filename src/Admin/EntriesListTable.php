<?php
/**
 * Tabelle der Anfragen.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Admin;

use MLab_Forms\Entries\EntryFormatter;
use MLab_Forms\Entries\EntryRepository;
use MLab_Forms\Entries\Status;
use MLab_Forms\Forms\FormPostType;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Liste mit Status-Ansichten, Formular-Filter, Suche und Sammelaktionen.
 */
final class EntriesListTable extends \WP_List_Table {

	private const PER_PAGE = 20;

	/**
	 * Konstruktor.
	 *
	 * @param array{form_id: int, status: string, search: string} $filters Aktive Filter.
	 */
	public function __construct( private array $filters ) {
		parent::__construct(
			array(
				'singular' => 'entry',
				'plural'   => 'entries',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Spalten.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return array(
			'cb'      => '<input type="checkbox">',
			'summary' => __( 'Anfrage', 'mlab-forms' ),
			'form'    => __( 'Formular', 'mlab-forms' ),
			'status'  => __( 'Status', 'mlab-forms' ),
			'date'    => __( 'Eingegangen', 'mlab-forms' ),
		);
	}

	/**
	 * Lädt die Einträge.
	 */
	public function prepare_items(): void {
		$result = EntryRepository::query(
			$this->filters + array(
				'page'     => $this->get_pagenum(),
				'per_page' => self::PER_PAGE,
			)
		);

		$this->items           = $result['items'];
		$this->_column_headers = array( $this->get_columns(), array(), array(), 'summary' );
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => self::PER_PAGE,
			)
		);
	}

	/**
	 * Ansichten «Alle», «Neu», «In Bearbeitung» …
	 *
	 * @return array<string, string>
	 */
	protected function get_views(): array {
		$counts = EntryRepository::count_by_status( $this->filters['form_id'] );
		$views  = array(
			'all' => $this->view_link( '', __( 'Alle', 'mlab-forms' ), array_sum( $counts ) ),
		);
		foreach ( Status::all() as $key => $label ) {
			$views[ $key ] = $this->view_link( $key, $label, $counts[ $key ] ?? 0 );
		}

		return $views;
	}

	/**
	 * Sammelaktionen: Status setzen und löschen.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions(): array {
		$actions = array();
		foreach ( Status::all() as $key => $label ) {
			/* translators: %s: Status */
			$actions[ 'status_' . $key ] = sprintf( __( 'Status: %s', 'mlab-forms' ), $label );
		}
		$actions['delete'] = __( 'Endgültig löschen', 'mlab-forms' );

		return $actions;
	}

	/**
	 * Formular-Filter über der Tabelle.
	 *
	 * @param string $which top oder bottom.
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		$forms = get_posts(
			array(
				'post_type'              => FormPostType::POST_TYPE,
				'post_status'            => array( 'publish', 'draft', 'private' ),
				'numberposts'            => 100,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		echo '<div class="alignleft actions"><select name="form_id"><option value="0">' . esc_html__( 'Alle Formulare', 'mlab-forms' ) . '</option>';
		foreach ( $forms as $form ) {
			printf( '<option value="%d" %s>%s</option>', (int) $form->ID, selected( $this->filters['form_id'], $form->ID, false ), esc_html( get_the_title( $form ) ) );
		}
		echo '</select>';
		submit_button( __( 'Filtern', 'mlab-forms' ), '', 'filter_action', false );
		printf( ' <a class="button" href="%s">%s</a>', esc_url( EntriesPage::export_url( $this->filters ) ), esc_html__( 'CSV exportieren', 'mlab-forms' ) );
		echo '</div>';
	}

	/**
	 * Checkbox-Spalte.
	 *
	 * @param array<string, mixed> $item Anfrage.
	 */
	protected function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="entry[]" value="%d">', (int) $item['id'] );
	}

	/**
	 * Kurzfassung: die ersten zwei Werte, verlinkt auf die Detailansicht.
	 *
	 * @param array<string, mixed> $item Anfrage.
	 */
	protected function column_summary( array $item ): string {
		// Name und E-Mail zuerst, sonst die ersten ausgefüllten Werte.
		$data   = array_merge( array_intersect_key( $item['data'], array_flip( array( 'name', 'email' ) ) ), $item['data'] );
		$values = array_filter( array_map( array( EntryFormatter::class, 'text' ), $data ), static fn( string $value ) => '' !== $value && '1' !== $value );
		$text   = implode( ' · ', array_slice( $values, 0, 3 ) );
		$text   = '' !== $text ? $text : '#' . $item['id'];
		$weight = Status::NEW === $item['status'] ? 'strong' : 'span';

		return sprintf( '<%1$s><a href="%2$s">%3$s</a></%1$s>', $weight, esc_url( EntriesPage::url( array( 'entry' => $item['id'] ) ) ), esc_html( wp_html_excerpt( $text, 80, '…' ) ) );
	}

	/**
	 * Übrige Spalten.
	 *
	 * @param array<string, mixed> $item        Anfrage.
	 * @param string               $column_name Spalte.
	 */
	protected function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'form':
				return esc_html( get_the_title( $item['form_id'] ) );
			case 'status':
				return esc_html( Status::label( $item['status'] ) );
			case 'date':
				return esc_html( EntriesPage::local_date( $item['created_at'] ) );
		}

		return '';
	}

	/**
	 * Hinweis bei leerer Liste.
	 */
	public function no_items(): void {
		esc_html_e( 'Keine Anfragen gefunden.', 'mlab-forms' );
	}

	/**
	 * Link einer Status-Ansicht.
	 *
	 * @param string $status Status oder leer für alle.
	 * @param string $label  Beschriftung.
	 * @param int    $count  Anzahl.
	 */
	private function view_link( string $status, string $label, int $count ): string {
		return sprintf(
			'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
			esc_url(
				EntriesPage::url(
					array_filter(
						array(
							'form_id' => $this->filters['form_id'],
							'status'  => $status,
						)
					)
				)
			),
			$status === $this->filters['status'] ? ' class="current" aria-current="page"' : '',
			esc_html( $label ),
			$count
		);
	}
}
