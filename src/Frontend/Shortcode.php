<?php
/**
 * Shortcode [mlab_form].
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Frontend;

use MLab_Forms\Forms\FormRepository;

defined( 'ABSPATH' ) || exit;

/**
 * [mlab_form id="123"] oder [mlab_form slug="anfrage"]
 */
final class Shortcode {

	public const TAG = 'mlab_form';

	/**
	 * Registriert den Shortcode.
	 */
	public function register(): void {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * Gibt das Formular aus. Ein fehlendes Formular sehen nur Redakteure als Hinweis.
	 *
	 * @param array<string, string>|string $atts Attribute.
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id'   => '',
				'slug' => '',
			),
			(array) $atts,
			self::TAG
		);

		$form = '' !== $atts['slug']
			? FormRepository::find_by_slug( sanitize_title( $atts['slug'] ) )
			: FormRepository::find( max( 0, (int) $atts['id'] ) );

		if ( null === $form ) {
			return current_user_can( 'edit_posts' )
				? '<p class="mlab-form-missing">' . esc_html__( 'MomentumLab Forms: Formular nicht gefunden oder nicht veröffentlicht.', 'mlab-forms' ) . '</p>'
				: '';
		}

		return Renderer::render( $form );
	}
}
