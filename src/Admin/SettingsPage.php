<?php
/**
 * Einstellungsseite im Backend.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Admin;

use MLab_Forms\Plugin;
use MLab_Forms\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Seite «Einstellungen › MomentumLab Forms».
 */
final class SettingsPage {

	private const SLUG  = 'mlab-forms';
	private const GROUP = 'mlab_forms';

	/**
	 * Adresse der Einstellungsseite.
	 */
	public static function url(): string {
		return admin_url( 'options-general.php?page=' . self::SLUG );
	}

	/**
	 * Hängt Menü, Einstellungen und Link in der Plugin-Liste an.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . Plugin::basename(), array( $this, 'add_action_link' ) );
	}

	/**
	 * Fügt die Seite unter «Einstellungen» ein.
	 */
	public function add_page(): void {
		add_options_page(
			__( 'MomentumLab Forms', 'mlab-forms' ),
			__( 'MomentumLab Forms', 'mlab-forms' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Registriert Option, Abschnitt und Feld über die Settings API.
	 */
	public function register_settings(): void {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'object',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);

		add_settings_section( 'mlab_forms_updates', __( 'Updates', 'mlab-forms' ), '__return_null', self::SLUG );

		add_settings_field(
			'mlab_forms_auto_updates',
			__( 'Automatische Updates', 'mlab-forms' ),
			array( $this, 'render_auto_updates_field' ),
			self::SLUG,
			'mlab_forms_updates',
			array( 'label_for' => 'mlab_forms_auto_updates' )
		);
	}

	/**
	 * Checkbox für die automatischen Updates.
	 */
	public function render_auto_updates_field(): void {
		printf(
			'<label><input type="checkbox" id="mlab_forms_auto_updates" name="%s[auto_updates]" value="1" %s> %s</label>',
			esc_attr( Settings::OPTION ),
			checked( Settings::auto_updates_enabled(), true, false ),
			esc_html__( 'Neue Versionen automatisch installieren', 'mlab-forms' )
		);
		echo '<p class="description">';
		esc_html_e( 'Ist der Schalter aus, zeigt WordPress neue Versionen nur an. Du installierst sie dann von Hand.', 'mlab-forms' );
		echo '</p>';
	}

	/**
	 * Gibt die Seite aus.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p>
				<?php
				/* translators: %s: Versionsnummer */
				printf( esc_html__( 'Version %s', 'mlab-forms' ), esc_html( MLAB_FORMS_VERSION ) );
				?>
			</p>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Link «Einstellungen» in der Plugin-Liste.
	 *
	 * @param array<int|string, string> $links Bestehende Links.
	 * @return array<int|string, string>
	 */
	public function add_action_link( array $links ): array {
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( self::url() ),
				esc_html__( 'Einstellungen', 'mlab-forms' )
			)
		);

		return $links;
	}
}
