<?php
/**
 * Beitragstyp für Formulare.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Forms;

use MLab_Forms\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * CPT «mlab_form»: nicht öffentlich, nur im Backend.
 */
final class FormPostType {

	public const POST_TYPE = 'mlab_form';

	/**
	 * Hängt die Registrierung an.
	 */
	public function register(): void {
		add_action( 'init', array( self::class, 'register_post_type' ) );
	}

	/**
	 * Registriert den Beitragstyp. Alle Rechte hängen an «mlab_forms_manage».
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => self::labels(),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => false,
				'show_in_rest'    => false,
				'supports'        => array( 'title' ),
				'rewrite'         => false,
				'query_var'       => false,
				'map_meta_cap'    => true,
				'capability_type' => 'mlab_form',
				'capabilities'    => self::capabilities(),
			)
		);
	}

	/**
	 * Beschriftungen im Backend.
	 *
	 * @return array<string, string>
	 */
	private static function labels(): array {
		return array(
			'name'               => __( 'Formulare', 'mlab-forms' ),
			'singular_name'      => __( 'Formular', 'mlab-forms' ),
			'add_new'            => __( 'Neues Formular', 'mlab-forms' ),
			'add_new_item'       => __( 'Neues Formular erstellen', 'mlab-forms' ),
			'edit_item'          => __( 'Formular bearbeiten', 'mlab-forms' ),
			'all_items'          => __( 'Alle Formulare', 'mlab-forms' ),
			'search_items'       => __( 'Formulare durchsuchen', 'mlab-forms' ),
			'not_found'          => __( 'Keine Formulare gefunden.', 'mlab-forms' ),
			'not_found_in_trash' => __( 'Keine Formulare im Papierkorb.', 'mlab-forms' ),
		);
	}

	/**
	 * Jede Beitragstyp-Berechtigung zeigt auf «mlab_forms_manage».
	 *
	 * @return array<string, string>
	 */
	private static function capabilities(): array {
		$primitive = array(
			'edit_posts',
			'edit_others_posts',
			'edit_published_posts',
			'edit_private_posts',
			'publish_posts',
			'read_private_posts',
			'delete_posts',
			'delete_others_posts',
			'delete_published_posts',
			'delete_private_posts',
			'create_posts',
		);

		return array_fill_keys( $primitive, Capabilities::MANAGE );
	}
}
