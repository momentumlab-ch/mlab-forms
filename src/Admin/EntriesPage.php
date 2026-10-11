<?php
/**
 * Backend-Seite «Anfragen».
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Admin;

use MLab_Forms\Capabilities;
use MLab_Forms\Entries\EntryRepository;
use MLab_Forms\Entries\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Liste und Detailansicht der Anfragen mit Status, Notizen und CSV-Export.
 */
final class EntriesPage {

	public const SLUG = 'mlab-forms-entries';

	public const STATUS_ACTION  = 'mlab_forms_entry_status';
	public const NOTE_ACTION    = 'mlab_forms_entry_note';
	private const EXPORT_ACTION = 'mlab_forms_export';

	/**
	 * Hook-Suffix der Seite.
	 *
	 * @var string
	 */
	private string $hook = '';

	/**
	 * Hängt Seite und Aktionen an.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ), 15 );
		add_action( 'admin_post_' . self::STATUS_ACTION, array( $this, 'handle_status' ) );
		add_action( 'admin_post_' . self::NOTE_ACTION, array( $this, 'handle_note' ) );
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( CsvExport::class, 'handle' ) );
	}

	/**
	 * Adresse der Seite mit Parametern.
	 *
	 * @param array<string, scalar> $args z. B. entry, form_id, status.
	 */
	public static function url( array $args = array() ): string {
		return add_query_arg( array( 'page' => self::SLUG ) + $args, admin_url( Menu::PARENT ) );
	}

	/**
	 * Adresse des CSV-Exports mit den aktiven Filtern.
	 *
	 * @param array<string, scalar> $filters Filter.
	 */
	public static function export_url( array $filters ): string {
		$args = array(
			'action'  => self::EXPORT_ACTION,
			'form_id' => $filters['form_id'] ?? 0,
			'status'  => $filters['status'] ?? '',
			's'       => $filters['search'] ?? '',
		);

		return wp_nonce_url( add_query_arg( array_filter( $args ), admin_url( 'admin-post.php' ) ), self::EXPORT_ACTION );
	}

	/**
	 * Filter der Liste aus der Adresse (gilt auch für den Export).
	 *
	 * @return array{form_id: int, status: string, search: string}
	 */
	public static function request_filters(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- nur Filter; der Export prüft seinen Nonce selbst.
		$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';

		return array(
			'form_id' => isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0,
			'status'  => Status::is_valid( $status ) ? $status : '',
			'search'  => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
		);
		// phpcs:enable
	}

	/**
	 * UTC-Zeit aus der Datenbank in Ortszeit.
	 *
	 * @param string $mysql_gmt Zeit im MySQL-Format (UTC).
	 */
	public static function local_date( string $mysql_gmt ): string {
		return get_date_from_gmt( $mysql_gmt, 'd.m.Y H:i' );
	}

	/**
	 * Unterpunkt «Anfragen» mit Zähler.
	 */
	public function add_page(): void {
		$this->hook = (string) add_submenu_page(
			Menu::PARENT,
			__( 'Anfragen', 'mlab-forms' ),
			__( 'Anfragen', 'mlab-forms' ) . Menu::bubble(),
			Capabilities::VIEW_ENTRIES,
			self::SLUG,
			array( $this, 'render' )
		);
		add_action( 'load-' . $this->hook, array( $this, 'handle_bulk' ) );
	}

	/**
	 * Liste oder Detailansicht.
	 */
	public function render(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- nur Anzeige und Filter.
		$entry_id = isset( $_GET['entry'] ) ? absint( $_GET['entry'] ) : 0;
		$entry    = $entry_id ? EntryRepository::find( $entry_id ) : null;

		if ( $entry && null === $entry['deleted_at'] ) {
			( new EntryView( $entry ) )->render();
			return;
		}

		// phpcs:enable
		$table = new EntriesListTable( self::request_filters() );
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Anfragen', 'mlab-forms' ); ?></h1>
			<hr class="wp-header-end">
			<?php $table->views(); ?>
			<form method="get" action="<?php echo esc_url( admin_url( Menu::PARENT ) ); ?>">
				<input type="hidden" name="post_type" value="mlab_form">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>">
				<?php
				$table->search_box( __( 'Anfragen durchsuchen', 'mlab-forms' ), 'mlab-entries' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Sammelaktionen aus der Liste.
	 */
	public function handle_bulk(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce wird geprüft, sobald eine Aktion vorliegt.
		$action = sanitize_key( wp_unslash( $_REQUEST['action'] ?? '-1' ) );
		if ( '-1' === $action ) {
			$action = sanitize_key( wp_unslash( $_REQUEST['action2'] ?? '-1' ) );
		}
		if ( '-1' === $action || '' === $action || empty( $_REQUEST['entry'] ) || ! is_array( $_REQUEST['entry'] ) ) {
			return;
		}
		// phpcs:enable

		check_admin_referer( 'bulk-entries' );
		Capabilities::ensure( current_user_can( Capabilities::VIEW_ENTRIES ) );
		$ids = array_map( 'absint', wp_unslash( $_REQUEST['entry'] ) );

		if ( 'delete' === $action ) {
			EntryRepository::delete( $ids );
		} elseif ( str_starts_with( $action, 'status_' ) ) {
			foreach ( $ids as $id ) {
				EntryRepository::set_status( $id, substr( $action, 7 ) );
			}
		}

		$referer = wp_get_referer();
		wp_safe_redirect( remove_query_arg( array( 'action', 'action2', 'entry', '_wpnonce', '_wp_http_referer' ), $referer ? $referer : self::url() ) );
		exit;
	}

	/**
	 * Status aus der Detailansicht.
	 */
	public function handle_status(): void {
		$id = $this->verify_entry_action( self::STATUS_ACTION );
		EntryRepository::set_status( $id, isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- in verify_entry_action geprüft.
		wp_safe_redirect( self::url( array( 'entry' => $id ) ) );
		exit;
	}

	/**
	 * Notiz aus der Detailansicht.
	 */
	public function handle_note(): void {
		$id   = $this->verify_entry_action( self::NOTE_ACTION );
		$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- in verify_entry_action geprüft.
		if ( '' !== trim( $note ) ) {
			EntryRepository::add_note( $id, 'note', $note );
		}
		wp_safe_redirect( self::url( array( 'entry' => $id ) ) . '#mlab-notes' );
		exit;
	}

	/**
	 * Formular-Felder für eine Aktion auf einer Anfrage.
	 *
	 * @param string $action Aktion.
	 * @param int    $id     Anfrage.
	 */
	public static function action_fields( string $action, int $id ): string {
		return sprintf( '<input type="hidden" name="action" value="%s"><input type="hidden" name="entry" value="%d">', esc_attr( $action ), $id )
			. wp_nonce_field( $action . $id, '_wpnonce', true, false );
	}

	/**
	 * Prüft Nonce und Berechtigung und liefert die Anfrage-ID.
	 *
	 * @param string $action Aktion.
	 */
	private function verify_entry_action( string $action ): int {
		$id = isset( $_POST['entry'] ) ? absint( $_POST['entry'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- wird direkt danach geprüft.
		check_admin_referer( $action . $id );
		Capabilities::ensure( current_user_can( Capabilities::VIEW_ENTRIES ) && null !== EntryRepository::find( $id ) );

		return $id;
	}
}
