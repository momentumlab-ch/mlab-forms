<?php
/**
 * Bearbeiten eines Formulars.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Admin;

use MLab_Forms\Capabilities;
use MLab_Forms\Entries\EntryRepository;
use MLab_Forms\Forms\FormPostType;
use MLab_Forms\Forms\FormRepository;
use MLab_Forms\Forms\Templates;
use MLab_Forms\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Metaboxen «Formular» (Builder), «Aufbewahrung», «Einbinden» und «Frühere Versionen».
 *
 * Der Builder schreibt das Schema als JSON in ein Textfeld, das beim Speichern geprüft wird.
 * Ungültige Schemas werden nicht gespeichert, sondern mit Fehlern wieder angezeigt.
 */
final class FormEditor {

	public const NONCE           = 'mlab_forms_schema';
	public const FIELD           = 'mlab_form_schema';
	private const RESTORE_ACTION = 'mlab_forms_restore_revision';

	/**
	 * Hängt Metaboxen, Speichern und Hinweise an.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes_' . FormPostType::POST_TYPE, array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . FormPostType::POST_TYPE, array( $this, 'save' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'render_errors' ) );
		add_action( 'admin_post_' . self::RESTORE_ACTION, array( $this, 'handle_restore' ) );
		add_filter( 'manage_' . FormPostType::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . FormPostType::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
	}

	/**
	 * Registriert die Metaboxen.
	 */
	public function add_meta_boxes(): void {
		add_meta_box( 'mlab-form-schema', __( 'Formular', 'mlab-forms' ), array( $this, 'render_schema_box' ), null, 'normal', 'high' );
		add_meta_box( 'mlab-form-retention', __( 'Aufbewahrung', 'mlab-forms' ), array( new RetentionBox(), 'render' ), null, 'side' );
		add_meta_box( 'mlab-form-embed', __( 'Einbinden', 'mlab-forms' ), array( $this, 'render_embed_box' ), null, 'side' );
		add_meta_box( 'mlab-form-revisions', __( 'Frühere Versionen', 'mlab-forms' ), array( $this, 'render_revisions_box' ), null, 'side' );
	}

	/**
	 * Der Builder samt Schema-Textfeld.
	 *
	 * @param \WP_Post $post Formular.
	 */
	public function render_schema_box( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE );

		$draft  = get_transient( $this->draft_key( $post->ID ) );
		$stored = FormRepository::schema( $post->ID );
		$schema = $stored ?? Templates::get( 'kontakt' );
		$json   = is_string( $draft ) ? $draft : self::pretty( (array) $schema );

		Builder::render( $json, null === $stored && ! is_string( $draft ) );
	}

	/**
	 * Shortcode zum Kopieren.
	 *
	 * @param \WP_Post $post Formular.
	 */
	public function render_embed_box( \WP_Post $post ): void {
		if ( 'publish' !== $post->post_status ) {
			echo '<p>' . esc_html__( 'Nach dem Veröffentlichen kannst du das Formular mit diesem Shortcode einbinden:', 'mlab-forms' ) . '</p>';
		}
		printf( '<p><input type="text" readonly class="widefat code" onfocus="this.select()" value="%s"></p>', esc_attr( self::shortcode( $post ) ) );
	}

	/**
	 * Liste der Revisionen mit «Wiederherstellen».
	 *
	 * @param \WP_Post $post Formular.
	 */
	public function render_revisions_box( \WP_Post $post ): void {
		$revisions = FormRepository::revisions( $post->ID );
		if ( ! $revisions ) {
			echo '<p>' . esc_html__( 'Noch keine früheren Versionen.', 'mlab-forms' ) . '</p>';
			return;
		}

		echo '<ul>';
		foreach ( $revisions as $index => $revision ) {
			$url  = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::RESTORE_ACTION . '&post=' . $post->ID . '&revision=' . $index ), self::RESTORE_ACTION . $post->ID );
			$user = get_userdata( (int) $revision['user_id'] );
			printf(
				'<li>%s, %s · <a href="%s">%s</a></li>',
				esc_html( wp_date( 'd.m.Y H:i', (int) $revision['saved_at'] ) ),
				esc_html( $user ? $user->display_name : '–' ),
				esc_url( $url ),
				esc_html__( 'Wiederherstellen', 'mlab-forms' )
			);
		}
		echo '</ul>';
	}

	/**
	 * Prüft und speichert das Schema.
	 *
	 * @param int      $post_id Formular.
	 * @param \WP_Post $post    Beitrag.
	 */
	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE ], $_POST[ self::FIELD ] ) || wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// JSON wird nicht als Text bereinigt, sondern dekodiert und gegen das Schema geprüft.
		$json   = trim( wp_unslash( $_POST[ self::FIELD ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$schema = json_decode( $json, true );
		$schema = is_array( $schema ) ? $this->with_retention( $schema ) : $schema;
		$errors = is_array( $schema )
			? FormRepository::save( $post_id, $schema )
			: array(
				array(
					'path'    => '',
					/* translators: %s: Fehlermeldung des JSON-Parsers */
					'message' => sprintf( __( 'Kein gültiges JSON: %s', 'mlab-forms' ), json_last_error_msg() ),
				),
			);

		if ( $errors ) {
			set_transient( $this->draft_key( $post_id ), $json, HOUR_IN_SECONDS );
			set_transient( $this->errors_key( $post_id ), $errors, HOUR_IN_SECONDS );
			return;
		}
		delete_transient( $this->draft_key( $post_id ) );
	}

	/**
	 * Übernimmt die Auswahl der Box «Aufbewahrung» ins Schema, wenn sie geändert wurde.
	 *
	 * @param array<string, mixed> $schema Dekodiertes Schema.
	 * @return array<string, mixed>
	 */
	private function with_retention( array $schema ): array {
		if ( ! isset( $_POST[ RetentionBox::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ RetentionBox::NONCE ] ) ), RetentionBox::NONCE ) ) {
			return $schema;
		}

		// Nur Zahlen und feste Auswahlwerte werden gelesen (siehe RetentionBox::days_from_request).
		return RetentionBox::merge( $schema, wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	/**
	 * Zeigt Fehler des letzten Speicherns an.
	 */
	public function render_errors(): void {
		$screen = get_current_screen();
		$post   = get_post();
		if ( ! $screen || FormPostType::POST_TYPE !== $screen->post_type || ! $post ) {
			return;
		}

		$errors = get_transient( $this->errors_key( $post->ID ) );
		if ( ! is_array( $errors ) ) {
			return;
		}
		delete_transient( $this->errors_key( $post->ID ) );

		$items = '';
		foreach ( $errors as $error ) {
			$items .= Html::tag( 'li', array(), ( '' !== $error['path'] ? Html::tag( 'code', array(), esc_html( $error['path'] ) ) . ' ' : '' ) . esc_html( $error['message'] ) );
		}
		printf(
			'<div class="notice notice-error"><p><strong>%s</strong></p><ul>%s</ul></div>',
			esc_html__( 'Das Schema wurde nicht gespeichert. Bitte korrigiere diese Punkte:', 'mlab-forms' ),
			$items // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- oben escapt.
		);
	}

	/**
	 * Stellt eine Revision wieder her.
	 */
	public function handle_restore(): void {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$index   = isset( $_GET['revision'] ) ? absint( $_GET['revision'] ) : -1;

		check_admin_referer( self::RESTORE_ACTION . $post_id );
		Capabilities::ensure( current_user_can( 'edit_post', $post_id ) );

		FormRepository::restore( $post_id, $index );
		wp_safe_redirect( (string) get_edit_post_link( $post_id, 'url' ) );
		exit;
	}

	/**
	 * Spalten der Formularliste.
	 *
	 * @param array<string, string> $columns Spalten.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		$date = $columns['date'] ?? null;
		unset( $columns['date'] );
		$columns['mlab_shortcode'] = __( 'Shortcode', 'mlab-forms' );
		if ( current_user_can( Capabilities::VIEW_ENTRIES ) ) {
			$columns['mlab_entries'] = __( 'Anfragen', 'mlab-forms' );
		}
		if ( $date ) {
			$columns['date'] = $date;
		}

		return $columns;
	}

	/**
	 * Inhalt der eigenen Spalten.
	 *
	 * @param string $column  Spalte.
	 * @param int    $post_id Formular.
	 */
	public function column_content( string $column, int $post_id ): void {
		if ( 'mlab_shortcode' === $column ) {
			echo '<code>' . esc_html( self::shortcode( get_post( $post_id ) ) ) . '</code>';
		} elseif ( 'mlab_entries' === $column ) {
			static $counts = null;
			$counts      ??= EntryRepository::count_by_form();
			printf( '<a href="%s">%d</a>', esc_url( EntriesPage::url( array( 'form_id' => $post_id ) ) ), (int) ( $counts[ $post_id ] ?? 0 ) );
		}
	}

	/**
	 * Shortcode eines Formulars.
	 *
	 * @param \WP_Post|null $post Formular.
	 */
	private static function shortcode( ?\WP_Post $post ): string {
		return $post ? sprintf( '[mlab_form id="%d"]', $post->ID ) : '';
	}

	/**
	 * JSON hübsch formatiert.
	 *
	 * @param array<string, mixed> $schema Schema.
	 */
	private static function pretty( array $schema ): string {
		return (string) wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Transient für ungültiges JSON pro Benutzer und Formular.
	 *
	 * @param int $post_id Formular.
	 */
	private function draft_key( int $post_id ): string {
		return 'mlab_forms_draft_' . get_current_user_id() . '_' . $post_id;
	}

	/**
	 * Transient für Fehler pro Benutzer und Formular.
	 *
	 * @param int $post_id Formular.
	 */
	private function errors_key( int $post_id ): string {
		return 'mlab_forms_errors_' . get_current_user_id() . '_' . $post_id;
	}
}
