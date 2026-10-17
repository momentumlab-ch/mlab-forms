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
 * Hauptmenü «MomentumLab Forms» mit der Seite «Einstellungen» und ihren Tabs.
 */
final class SettingsPage {

	public const SLUG       = 'mlab-forms-settings';
	public const CAPABILITY = 'manage_options';
	private const GROUP     = 'mlab_forms';

	/**
	 * Adresse der Einstellungsseite, optional mit Tab.
	 *
	 * @param string $tab Tab-Schlüssel.
	 */
	public static function url( string $tab = '' ): string {
		$args = array( 'page' => self::SLUG );
		if ( '' !== $tab ) {
			$args['tab'] = $tab;
		}

		return add_query_arg( $args, admin_url( Menu::PARENT ) );
	}

	/**
	 * Hängt Menü, Einstellungen und Aktionen an.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 20 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . Plugin::basename(), array( $this, 'add_action_link' ) );
	}

	/**
	 * Unterpunkt «Einstellungen» im Menü von MomentumLab Forms.
	 */
	public function add_menu(): void {
		add_submenu_page(
			Menu::PARENT,
			__( 'Einstellungen', 'mlab-forms' ),
			__( 'Einstellungen', 'mlab-forms' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Registriert Option, einen Abschnitt pro Tab und alle Felder.
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

		foreach ( Settings::tabs() as $tab => $title ) {
			add_settings_section( 'mlab_forms_' . $tab, '', '__return_null', $this->section_page( $tab ) );
		}

		foreach ( Settings::fields() as $key => $field ) {
			add_settings_field(
				'mlab_forms_' . $key,
				$field['label'],
				array( FieldRenderer::class, 'render' ),
				$this->section_page( $field['tab'] ),
				'mlab_forms_' . $field['tab'],
				array(
					'label_for' => 'mlab_forms_' . $key,
					'key'       => $key,
					'field'     => $field,
				)
			);
		}
	}

	/**
	 * Gibt die Seite mit Tab-Leiste aus.
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$tab = $this->current_tab();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Einstellungen', 'mlab-forms' ); ?></h1>
			<?php $this->render_notices(); ?>
			<nav class="nav-tab-wrapper">
				<?php foreach ( Settings::tabs() as $key => $title ) : ?>
					<a href="<?php echo esc_url( self::url( $key ) ); ?>" class="nav-tab <?php echo $key === $tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $title ); ?></a>
				<?php endforeach; ?>
			</nav>
			<?php if ( $this->tab_has_fields( $tab ) ) : ?>
				<form action="options.php" method="post">
					<?php
					settings_fields( self::GROUP );
					printf( '<input type="hidden" name="%s[%s]" value="%s">', esc_attr( Settings::OPTION ), esc_attr( Settings::TAB_KEY ), esc_attr( $tab ) );
					do_settings_sections( $this->section_page( $tab ) );
					submit_button();
					?>
				</form>
			<?php endif; ?>
			<?php
			/**
			 * Zusätzlicher Inhalt unter dem Formular eines Tabs.
			 */
			do_action( "mlab_forms_settings_tab_{$tab}" );
			?>
		</div>
		<?php
	}

	/**
	 * Hinweise nach dem Speichern.
	 */
	private function render_notices(): void {
		// Ausserhalb von «Einstellungen» zeigt WordPress die Meldungen nicht von selbst an.
		settings_errors();
	}

	/**
	 * Aktiver Tab aus der URL, sonst der erste.
	 */
	private function current_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur Navigation.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		return array_key_exists( $tab, Settings::tabs() ) ? $tab : (string) array_key_first( Settings::tabs() );
	}

	/**
	 * Hat der Tab eigene Einstellungen? Sonst entfällt das Formular.
	 *
	 * @param string $tab Tab-Schlüssel.
	 */
	private function tab_has_fields( string $tab ): bool {
		return in_array( $tab, array_column( Settings::fields(), 'tab' ), true );
	}

	/**
	 * Seiten-ID für die Abschnitte eines Tabs.
	 *
	 * @param string $tab Tab-Schlüssel.
	 */
	private function section_page( string $tab ): string {
		return self::SLUG . '-' . $tab;
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
			sprintf( '<a href="%s">%s</a>', esc_url( self::url() ), esc_html__( 'Einstellungen', 'mlab-forms' ) )
		);

		return $links;
	}
}
