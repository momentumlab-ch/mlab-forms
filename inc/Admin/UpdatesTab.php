<?php
/**
 * Tab «Updates» der Einstellungen.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Admin;

use MLab_Forms\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Zeigt Version und den Auto-Update-Schalter von WordPress.
 *
 * Bewusst ohne eigene Logik: Der Link löst dieselbe Aktion aus wie «Automatische
 * Aktualisierungen aktivieren» in der Plugin-Liste, und die Texte stammen aus
 * WordPress selbst (Textdomain «default»), damit sie überall gleich lauten.
 */
final class UpdatesTab {

	/**
	 * Hängt die Ausgabe an den Tab.
	 */
	public function register(): void {
		add_action( 'mlab_forms_settings_tab_updates', array( $this, 'render' ) );
	}

	/**
	 * Versionen, Auto-Update-Status und Links.
	 */
	public function render(): void {
		// Der Plugin Update Checker trägt ein gefundenes Update in die Update-Liste von WordPress ein.
		$updates = get_site_transient( 'update_plugins' );
		$update  = is_object( $updates ) ? ( $updates->response[ Plugin::basename() ] ?? null ) : null;
		$latest  = is_object( $update ) && isset( $update->new_version ) ? (string) $update->new_version : MLAB_FORMS_VERSION;
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Installierte Version', 'mlab-forms' ); ?></th>
				<td><?php echo esc_html( MLAB_FORMS_VERSION ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Neueste Version', 'mlab-forms' ); ?></th>
				<td>
					<?php echo esc_html( $latest ); ?>
					· <a href="<?php echo esc_url( self_admin_url( 'update-core.php?force-check=1' ) ); ?>"><?php esc_html_e( 'Check again.', 'default' ); ?></a>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Auto-updates', 'default' ); ?></th>
				<td><?php $this->render_auto_update(); ?></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Status und Umschalt-Link wie in der Plugin-Liste.
	 */
	private function render_auto_update(): void {
		// Texte aus der WordPress-Übersetzung (Textdomain default).
		if ( ! wp_is_auto_update_enabled_for_type( 'plugin' ) || ! current_user_can( 'update_plugins' ) ) {
			esc_html_e( 'Auto-updates disabled', 'default' );
			return;
		}

		// Ein Filter (z. B. im Hosting) kann den Zustand festlegen; dann gibt es wie in der Plugin-Liste keinen Schalter.
		/**
		 * Mit null als Vorgabe liefert WordPress null, wenn nichts festgelegt ist.
		 *
		 * @var bool|null $forced
		 */
		$forced = wp_is_auto_update_forced_for_item( 'plugin', null, (object) array( 'plugin' => Plugin::basename() ) );
		if ( null !== $forced ) {
			echo esc_html( $forced ? __( 'Auto-updates enabled', 'default' ) : __( 'Auto-updates disabled', 'default' ) );
			return;
		}

		$this->render_auto_update_toggle();
	}

	/**
	 * Zustand und Knopf, der wie in der Plugin-Liste umschaltet.
	 */
	private function render_auto_update_toggle(): void {
		$enabled = in_array( Plugin::basename(), (array) get_site_option( 'auto_update_plugins', array() ), true );
		$url     = add_query_arg(
			array(
				'action' => ( $enabled ? 'disable' : 'enable' ) . '-auto-update',
				'plugin' => Plugin::basename(),
			),
			self_admin_url( 'plugins.php' )
		);

		printf(
			'<p>%s</p><p><a class="button" href="%s">%s</a></p>',
			esc_html( $enabled ? __( 'Auto-updates enabled', 'default' ) : __( 'Auto-updates disabled', 'default' ) ),
			esc_url( wp_nonce_url( $url, 'updates' ) ),
			esc_html( $enabled ? __( 'Disable auto-updates', 'default' ) : __( 'Enable auto-updates', 'default' ) )
		);
	}
}
