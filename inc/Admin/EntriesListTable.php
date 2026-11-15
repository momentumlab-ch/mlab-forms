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
use MLab_Forms\Mail\Recipients;

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
			'resend'  => __( 'Aktion', 'mlab-forms' ),
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

		return sprintf( '<%1$s><a href="%2$s" data-mlab-preview="mlab-preview-%4$d">%3$s</a></%1$s><div class="mlab-entry-id">#%4$d</div>', $weight, esc_url( EntriesPage::url( array( 'entry' => $item['id'] ) ) ), esc_html( wp_html_excerpt( $text, 80, '…' ) ), (int) $item['id'] );
	}

	/**
	 * Übrige Spalten.
	 *
	 * @param array<string, mixed> $item        Anfrage.
	 * @param string               $column_name Spalte.
	 */
	protected function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'resend':
				$form = EntryFormatter::form( (int) $item['form_id'] );
				if ( ! $form || ! Recipients::for_form( $form ) ) {
					return '–';
				}
				return sprintf( '<button type="submit" class="button button-small" form="mlab-resend-%d" title="%s">%s</button>', (int) $item['id'], esc_attr( implode( ', ', Recipients::for_form( $form ) ) ), esc_html__( 'Resend', 'mlab-forms' ) );
			case 'form':
				return esc_html( get_the_title( $item['form_id'] ) );
			case 'status':
				return sprintf( '<span class="mlab-status mlab-status--%s">%s</span>', esc_attr( $item['status'] ), esc_html( Status::label( $item['status'] ) ) );
			case 'date':
				return esc_html( EntriesPage::local_date( $item['created_at'] ) );
		}

		return '';
	}

	/**
	 * Separate POST-Formulare ausserhalb des Suchformulars, keine verschachtelten Formulare.
	 */
	public function render_resend_forms(): void {
		foreach ( $this->items as $item ) {
			printf( '<form id="mlab-resend-%d" method="post" action="%s">', (int) $item['id'], esc_url( admin_url( 'admin-post.php' ) ) );
			echo EntriesPage::action_fields( EntriesPage::RESEND_ACTION, (int) $item['id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapt in action_fields.
			echo '<input type="hidden" name="resend_list" value="1">';
			foreach ( $this->filters + array( 'paged' => $this->get_pagenum() ) as $key => $value ) {
				printf( '<input type="hidden" name="return_%s" value="%s">', esc_attr( $key ), esc_attr( (string) $value ) );
			}
			echo '</form>';
			$this->render_preview( $item );
		}
	}

	/**
	 * Sichere Vorschau als inertes Template; ohne JavaScript bleibt der Detail-Link aktiv.
	 *
	 * @param array<string, mixed> $item Anfrage.
	 */
	private function render_preview( array $item ): void {
		$form = EntryFormatter::form( (int) $item['form_id'] );
		?>
		<template id="mlab-preview-<?php echo (int) $item['id']; ?>">
			<p class="mlab-eyebrow"><?php echo esc_html( get_the_title( $item['form_id'] ) ); ?></p>
			<h2 id="mlab-preview-title"><?php echo esc_html( '#' . $item['id'] ); ?></h2>
			<p><span class="mlab-status"><?php echo esc_html( Status::label( $item['status'] ) ); ?></span> · <?php echo esc_html( EntriesPage::local_date( $item['created_at'] ) ); ?></p>
			<dl class="mlab-preview-fields">
				<?php foreach ( EntryFormatter::rows( $item ) as $label => $value ) : ?>
					<dt><?php echo esc_html( $label ); ?></dt><dd><?php echo nl2br( esc_html( $value ) ); ?></dd>
				<?php endforeach; ?>
			</dl>
			<?php if ( $form && Recipients::for_form( $form ) ) : ?>
				<div class="mlab-card"><h3><?php esc_html_e( 'Zustelladresse', 'mlab-forms' ); ?></h3><p><?php echo esc_html( implode( ', ', Recipients::for_form( $form ) ) ); ?></p><p class="description"><?php esc_html_e( 'Keine weitere Bestätigung an den Kunden.', 'mlab-forms' ); ?></p></div>
				<button type="submit" class="button" form="mlab-resend-<?php echo (int) $item['id']; ?>"><?php esc_html_e( 'Resend', 'mlab-forms' ); ?></button>
			<?php endif; ?>
			<a class="button button-primary" href="<?php echo esc_url( EntriesPage::url( array( 'entry' => $item['id'] ) ) ); ?>"><?php esc_html_e( 'Anfrage öffnen', 'mlab-forms' ); ?></a>
		</template>
		<?php
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
