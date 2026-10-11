<?php
/**
 * Einstellungsseite im Backend.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Admin;

use MLab_Forms\Plugin;
use MLab_Forms\Settings;
use MLab_Forms\Updates\GitHubUpdater;

defined( 'ABSPATH' ) || exit;

/**
 * Hauptmenü «MomentumLab Forms» mit der Seite «Einstellungen» und ihren Tabs.
 */
final class SettingsPage {

	public const SLUG          = 'mlab-forms-settings';
	public const CAPABILITY    = 'manage_options';
	private const GROUP        = 'mlab_forms';
	private const CHECK_ACTION = 'mlab_forms_check_updates';

	/**
	 * Updater für die Anzeige im Tab «Updates».
	 *
	 * @var GitHubUpdater
	 */
	private GitHubUpdater $updater;

	/**
	 * Konstruktor.
	 *
	 * @param GitHubUpdater $updater Updater.
	 */
	public function __construct( GitHubUpdater $updater ) {
		$this->updater = $updater;
	}

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

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Hängt Menü, Einstellungen und Aktionen an.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_' . self::CHECK_ACTION, array( $this, 'handle_check_updates' ) );
		add_action( 'mlab_forms_settings_tab_updates', array( $this, 'render_update_status' ) );
		add_filter( 'plugin_action_links_' . Plugin::basename(), array( $this, 'add_action_link' ) );
	}

	/**
	 * Eigener Menüpunkt in der Seitenleiste, wie bei WPForms.
	 */
	public function add_menu(): void {
		add_menu_page(
			__( 'MomentumLab Forms', 'mlab-forms' ),
			__( 'MomentumLab Forms', 'mlab-forms' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-feedback',
			'57.8'
		);

		add_submenu_page(
			self::SLUG,
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
			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				printf( '<input type="hidden" name="%s[%s]" value="%s">', esc_attr( Settings::OPTION ), esc_attr( Settings::TAB_KEY ), esc_attr( $tab ) );
				do_settings_sections( $this->section_page( $tab ) );
				submit_button();
				?>
			</form>
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
	 * Installierte und neueste Version, mit Knopf «Jetzt nach Updates suchen».
	 */
	public function render_update_status(): void {
		// Nur den Cache lesen: Die Seite soll nicht auf GitHub warten.
		$release = $this->updater->cached_release();
		if ( false === $release ) {
			$latest = __( 'noch nicht geprüft', 'mlab-forms' );
		} else {
			$latest = $release ? $release['version'] : __( 'nicht abrufbar', 'mlab-forms' );
		}
		?>
		<h2><?php esc_html_e( 'Versionen', 'mlab-forms' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Installiert', 'mlab-forms' ); ?></th>
				<td><?php echo esc_html( MLAB_FORMS_VERSION ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Neueste auf GitHub', 'mlab-forms' ); ?></th>
				<td><?php echo esc_html( $latest ); ?></td>
			</tr>
		</table>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::CHECK_ACTION ); ?>">
			<?php
			wp_nonce_field( self::CHECK_ACTION );
			submit_button( __( 'Jetzt nach Updates suchen', 'mlab-forms' ), 'secondary', 'submit', false );
			?>
		</form>
		<?php
	}

	/**
	 * Leert den Cache und lässt WordPress sofort nach Updates suchen.
	 */
	public function handle_check_updates(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Dazu fehlt dir die Berechtigung.', 'mlab-forms' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::CHECK_ACTION );

		// Leert über den Hook auch den GitHub-Cache.
		delete_site_transient( 'update_plugins' );
		// Direkt abfragen, damit die Anzeige auch stimmt, wenn wordpress.org nicht erreichbar ist.
		$this->updater->latest_release();
		wp_update_plugins();

		wp_safe_redirect( add_query_arg( 'mlab_forms_checked', '1', self::url( 'updates' ) ) );
		exit;
	}

	/**
	 * Hinweise nach dem Speichern oder der Update-Suche.
	 */
	private function render_notices(): void {
		// Ausserhalb von «Einstellungen» zeigt WordPress die Meldungen nicht von selbst an.
		settings_errors();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur Anzeige.
		if ( isset( $_GET['mlab_forms_checked'] ) ) {
			wp_admin_notice(
				__( 'Die Suche nach Updates ist abgeschlossen.', 'mlab-forms' ),
				array(
					'type'        => 'success',
					'dismissible' => true,
				)
			);
		}
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
