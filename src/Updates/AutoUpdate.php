<?php
/**
 * Steuerung der automatischen Updates.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Updates;

use MLab_Forms\Admin\SettingsPage;
use MLab_Forms\Plugin;
use MLab_Forms\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Die Einstellung im Plugin-Backend entscheidet, ob WordPress dieses Plugin automatisch aktualisiert.
 */
final class AutoUpdate {

	/**
	 * Hängt die Filter an.
	 */
	public function register(): void {
		add_filter( 'auto_update_plugin', array( $this, 'filter_auto_update' ), 10, 2 );
		add_filter( 'plugin_auto_update_setting_html', array( $this, 'filter_setting_html' ), 10, 2 );
	}

	/**
	 * Erzwingt die Einstellung aus dem Plugin-Backend.
	 *
	 * @param bool|null $update Bisheriger Entscheid.
	 * @param object    $item   Update-Angebot von WordPress.
	 * @return bool|null
	 */
	public function filter_auto_update( $update, $item ) {
		if ( ! $this->is_this_plugin( $item->plugin ?? '' ) ) {
			return $update;
		}

		return Settings::auto_updates_enabled();
	}

	/**
	 * Ersetzt in der Plugin-Liste den WordPress-Link durch einen Hinweis auf die eigene Einstellung.
	 *
	 * @param string $html        Bisheriges HTML.
	 * @param string $plugin_file Basisname des Plugins in dieser Zeile.
	 */
	public function filter_setting_html( $html, $plugin_file ): string {
		if ( ! $this->is_this_plugin( $plugin_file ) ) {
			return (string) $html;
		}

		$label = Settings::auto_updates_enabled()
			? __( 'Automatische Updates: ein', 'mlab-forms' )
			: __( 'Automatische Updates: aus', 'mlab-forms' );

		return sprintf(
			'<a href="%s">%s</a>',
			esc_url( SettingsPage::url() ),
			esc_html( $label )
		);
	}

	/**
	 * Gehört der Basisname zu diesem Plugin?
	 *
	 * @param string $plugin_file Basisname.
	 */
	private function is_this_plugin( string $plugin_file ): bool {
		return Plugin::basename() === $plugin_file;
	}
}
