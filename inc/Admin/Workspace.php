<?php
/**
 * Gemeinsamer Markenauftritt für Anfragen und Einstellungen.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Lädt die Oberfläche ausschliesslich auf den beiden Plugin-Seiten.
 */
final class Workspace {

	/**
	 * Registriert die Assets.
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Lokale Schrift, Styles und progressive Interaktionen.
	 */
	public function enqueue(): void {
		$screen = get_current_screen();
		if ( ! $screen || ( ! str_contains( $screen->id, EntriesPage::SLUG ) && ! str_contains( $screen->id, SettingsPage::SLUG ) ) ) {
			return;
		}
		wp_enqueue_style( 'mlab-forms-workspace', plugins_url( 'assets/css/workspace.css', MLAB_FORMS_FILE ), array(), MLAB_FORMS_VERSION );
		wp_enqueue_script( 'mlab-forms-workspace', plugins_url( 'assets/js/workspace.js', MLAB_FORMS_FILE ), array(), MLAB_FORMS_VERSION, array( 'in_footer' => true ) );
		wp_localize_script(
			'mlab-forms-workspace',
			'mlabFormsWorkspace',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'mailAction' => MailTest::ACTION,
				'mailNonce'  => wp_create_nonce( MailTest::ACTION ),
				'working'    => __( 'Testmail wird gesendet …', 'mlab-forms' ),
				'failed'     => __( 'Der Testversand konnte nicht abgeschlossen werden.', 'mlab-forms' ),
				'wordpress'  => __( 'WordPress-Standard', 'mlab-forms' ),
				'visitor'    => __( 'E-Mail-Adresse des Kunden', 'mlab-forms' ),
				'unsaved'    => __( 'Ungespeicherte Änderungen', 'mlab-forms' ),
				'saved'      => __( 'Alle Änderungen gespeichert', 'mlab-forms' ),
			)
		);
	}

	/**
	 * Kopfbereich mit skalierbarem Logo.
	 *
	 * @param string $title    Titel.
	 * @param string $subtitle Erklärung.
	 */
	public static function heading( string $title, string $subtitle ): void {
		?>
		<header class="mlab-workspace__header">
			<img class="mlab-workspace__brand" src="<?php echo esc_url( plugins_url( 'assets/images/momentumlab-forms.svg', MLAB_FORMS_FILE ) ); ?>" alt="MomentumLab Forms" width="230" height="52">
			<div><h1><?php echo esc_html( $title ); ?></h1><p><?php echo esc_html( $subtitle ); ?></p></div>
		</header>
		<?php
	}
}
