<?php
/**
 * Detailansicht einer Anfrage.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Admin;

use MLab_Forms\Entries\EntryFormatter;
use MLab_Forms\Entries\EntryRepository;
use MLab_Forms\Entries\Status;
use MLab_Forms\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Felder, Herkunft, Status und Notizen einer Anfrage. Auf dem Handy bleibt alles einspaltig.
 */
final class EntryView {

	/**
	 * Konstruktor.
	 *
	 * @param array<string, mixed> $entry Anfrage aus dem EntryRepository.
	 */
	public function __construct( private array $entry ) {
	}

	/**
	 * Gibt die Seite aus.
	 */
	public function render(): void {
		$id = (int) $this->entry['id'];
		?>
		<div class="wrap mlab-entry">
			<p><a href="<?php echo esc_url( EntriesPage::url() ); ?>">&larr; <?php esc_html_e( 'Alle Anfragen', 'mlab-forms' ); ?></a></p>
			<h1>
				<?php
				/* translators: 1: Nummer, 2: Formular */
				printf( esc_html__( 'Anfrage #%1$d · %2$s', 'mlab-forms' ), (int) $id, esc_html( get_the_title( $this->entry['form_id'] ) ) );
				?>
			</h1>
			<div id="poststuff">
				<div class="postbox"><div class="inside"><?php $this->render_fields(); ?></div></div>
				<div class="postbox"><div class="inside"><?php $this->render_status_form(); ?></div></div>
				<div class="postbox" id="mlab-notes"><div class="inside"><?php $this->render_notes(); ?></div></div>
				<div class="postbox"><div class="inside"><?php $this->render_meta(); ?></div></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Alle Felder mit Label und Wert.
	 */
	private function render_fields(): void {
		echo '<h2>' . esc_html__( 'Angaben', 'mlab-forms' ) . '</h2>';
		$this->table( EntryFormatter::rows( $this->entry ), true );
	}

	/**
	 * Status ändern.
	 */
	private function render_status_form(): void {
		$options = '';
		foreach ( Status::all() as $key => $label ) {
			$options .= Html::tag(
				'option',
				array(
					'value'    => $key,
					'selected' => $key === $this->entry['status'],
				),
				esc_html( $label )
			);
		}
		?>
		<h2><label for="mlab-status"><?php esc_html_e( 'Status', 'mlab-forms' ); ?></label></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php echo EntriesPage::action_fields( EntriesPage::STATUS_ACTION, (int) $this->entry['id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapt in action_fields. ?>
			<select id="mlab-status" name="status"><?php echo $options; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::tag escapt. ?></select>
			<?php submit_button( __( 'Status speichern', 'mlab-forms' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Notizen und Verlauf, darunter das Feld für eine neue Notiz.
	 */
	private function render_notes(): void {
		echo '<h2>' . esc_html__( 'Notizen und Verlauf', 'mlab-forms' ) . '</h2>';

		$notes = EntryRepository::notes( (int) $this->entry['id'] );
		if ( ! $notes ) {
			echo '<p>' . esc_html__( 'Noch keine Notizen.', 'mlab-forms' ) . '</p>';
		}
		echo '<ul class="mlab-entry__notes">';
		foreach ( $notes as $note ) {
			$user = get_userdata( (int) $note['user_id'] );
			printf(
				'<li><strong>%s</strong> · %s<br>%s</li>',
				esc_html( EntriesPage::local_date( $note['created_at'] ) ),
				esc_html( $user ? $user->display_name : __( 'System', 'mlab-forms' ) ),
				nl2br( esc_html( $note['content'] ) )
			);
		}
		echo '</ul>';
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php echo EntriesPage::action_fields( EntriesPage::NOTE_ACTION, (int) $this->entry['id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapt in action_fields. ?>
			<p><label for="mlab-note"><?php esc_html_e( 'Neue Notiz (nur intern sichtbar)', 'mlab-forms' ); ?></label></p>
			<textarea id="mlab-note" name="note" rows="3" class="large-text"></textarea>
			<?php submit_button( __( 'Notiz speichern', 'mlab-forms' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Herkunft: Zeit, Quelle, Referrer, UTM, Sprache.
	 */
	private function render_meta(): void {
		$meta = $this->entry['meta'];
		$rows = array(
			__( 'Eingegangen', 'mlab-forms' ) => EntriesPage::local_date( $this->entry['created_at'] ),
			__( 'Seite', 'mlab-forms' )       => (string) ( $meta['source'] ?? '' ),
			__( 'Referrer', 'mlab-forms' )    => (string) ( $meta['referrer'] ?? '' ),
			__( 'Sprache', 'mlab-forms' )     => (string) ( $meta['language'] ?? '' ),
		);
		foreach ( (array) ( $meta['utm'] ?? array() ) as $key => $value ) {
			$rows[ $key ] = (string) $value;
		}

		echo '<h2>' . esc_html__( 'Herkunft', 'mlab-forms' ) . '</h2>';
		$this->table( $rows, false );
	}

	/**
	 * Zweispaltige Tabelle.
	 *
	 * @param array<string, string> $rows      Beschriftung => Wert.
	 * @param bool                  $multiline Zeilenumbrüche im Wert erhalten.
	 */
	private function table( array $rows, bool $multiline ): void {
		echo '<table class="widefat striped"><tbody>';
		foreach ( $rows as $label => $value ) {
			$value = '' === $value ? '–' : esc_html( $value );
			printf( '<tr><th scope="row" style="width:30%%">%s</th><td>%s</td></tr>', esc_html( (string) $label ), $multiline ? nl2br( $value ) : $value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- oben escapt.
		}
		echo '</tbody></table>';
	}
}
