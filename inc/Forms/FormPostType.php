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
		$cap = Capabilities::MANAGE;

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'               => __( 'Formulare', 'mlab-forms' ),
					'singular_name'      => __( 'Formular', 'mlab-forms' ),
					'add_new'            => __( 'Neues Formular', 'mlab-forms' ),
					'add_new_item'       => __( 'Neues Formular erstellen', 'mlab-forms' ),
					'edit_item'          => __( 'Formular bearbeiten', 'mlab-forms' ),
					'all_items'          => __( 'Alle Formulare', 'mlab-forms' ),
					'search_items'       => __( 'Formulare durchsuchen', 'mlab-forms' ),
					'not_found'          => __( 'Keine Formulare gefunden.', 'mlab-forms' ),
					'not_found_in_trash' => __( 'Keine Formulare im Papierkorb.', 'mlab-forms' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => false,
				'show_in_rest'    => false,
				'supports'        => array( 'title' ),
				'rewrite'         => false,
				'query_var'       => false,
				'map_meta_cap'    => true,
				'capability_type' => 'mlab_form',
				'capabilities'    => array(
					'edit_posts'             => $cap,
					'edit_others_posts'      => $cap,
					'edit_published_posts'   => $cap,
					'edit_private_posts'     => $cap,
					'publish_posts'          => $cap,
					'read_private_posts'     => $cap,
					'delete_posts'           => $cap,
					'delete_others_posts'    => $cap,
					'delete_published_posts' => $cap,
					'delete_private_posts'   => $cap,
					'create_posts'           => $cap,
				),
			)
		);
	}
}
