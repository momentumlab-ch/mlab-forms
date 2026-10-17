<?php
/**
 * Hauptmenü im Backend.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Admin;

use MLab_Forms\Capabilities;
use MLab_Forms\Entries\EntryRepository;
use MLab_Forms\Entries\Status;
use MLab_Forms\Forms\FormPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Menüpunkt «MomentumLab Forms» wie bei WPForms: Alle Formulare, Neues Formular,
 * Anfragen (mit Zähler) und Einstellungen.
 *
 * Wer nur Anfragen sehen darf, bekommt von WordPress automatisch «Anfragen» als Einstieg.
 */
final class Menu {

	public const PARENT = 'edit.php?post_type=' . FormPostType::POST_TYPE;

	/**
	 * Hängt das Menü an.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 9 );
		add_filter( 'parent_file', array( $this, 'highlight_parent' ) );
	}

	/**
	 * Hauptpunkt und die Formular-Unterpunkte. Anfragen und Einstellungen hängen ihre Seiten selbst an.
	 */
	public function add_menu(): void {
		add_menu_page(
			__( 'MomentumLab Forms', 'mlab-forms' ),
			__( 'MomentumLab Forms', 'mlab-forms' ) . self::bubble(),
			Capabilities::MANAGE,
			self::PARENT,
			'',
			'dashicons-feedback',
			57.8
		);
		add_submenu_page( self::PARENT, __( 'Alle Formulare', 'mlab-forms' ), __( 'Alle Formulare', 'mlab-forms' ), Capabilities::MANAGE, self::PARENT );
		add_submenu_page( self::PARENT, __( 'Neues Formular', 'mlab-forms' ), __( 'Neues Formular', 'mlab-forms' ), Capabilities::MANAGE, 'post-new.php?post_type=' . FormPostType::POST_TYPE );
	}

	/**
	 * Zähler neuer Anfragen als HTML für Menütitel.
	 */
	public static function bubble(): string {
		static $count = null;

		if ( ! current_user_can( Capabilities::VIEW_ENTRIES ) ) {
			return '';
		}
		// Hauptmenü und Untermenü zeigen denselben Zähler: nur einmal zählen.
		$count ??= EntryRepository::count_by_status()[ Status::NEW ] ?? 0;

		return $count > 0
			? sprintf( ' <span class="awaiting-mod count-%1$d"><span class="pending-count">%1$d</span></span>', $count )
			: '';
	}

	/**
	 * Hält das Menü beim Bearbeiten eines Formulars offen.
	 *
	 * @param string $parent_file Aktueller Elternpunkt.
	 */
	public function highlight_parent( $parent_file ): string {
		$screen = get_current_screen();

		return $screen && FormPostType::POST_TYPE === $screen->post_type ? self::PARENT : (string) $parent_file;
	}
}
