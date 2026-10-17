<?php
/**
 * CSS und JavaScript im Frontend.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Frontend;

use MLab_Forms\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Lädt die Assets nur auf Seiten mit Formular.
 */
final class Assets {

	public const HANDLE = 'mlab-forms';

	/**
	 * Hängt die Registrierung an.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Registriert die Dateien und lädt sie früh, wenn der Beitrag den Shortcode enthält.
	 *
	 * Sonst lädt der Renderer sie beim Ausgeben (Styles landen dann im Footer).
	 */
	public function register_assets(): void {
		wp_register_style( self::HANDLE, plugins_url( 'assets/css/form.css', MLAB_FORMS_FILE ), array(), MLAB_FORMS_VERSION );
		wp_register_script(
			self::HANDLE,
			plugins_url( 'assets/js/form.js', MLAB_FORMS_FILE ),
			array(),
			MLAB_FORMS_VERSION,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);

		$post = get_post();
		if ( is_singular() && $post && has_shortcode( $post->post_content, Shortcode::TAG ) ) {
			self::enqueue();
		}
	}

	/**
	 * Lädt CSS (falls eingeschaltet) und JavaScript.
	 */
	public static function enqueue(): void {
		if ( Settings::get( 'load_css' ) ) {
			wp_enqueue_style( self::HANDLE );
		}
		wp_enqueue_script( self::HANDLE );
	}

	/**
	 * CSS-Variablen für den Formular-Container, z. B. aus Theme-Farben.
	 *
	 * @return string|null Inline-Style oder null.
	 */
	public static function style_vars(): ?string {
		/**
		 * CSS-Variablen der Formulare, z. B. array( '--mlab-form-accent' => '#e30613' ).
		 *
		 * @param array<string, string> $vars Name => Wert.
		 */
		$vars = (array) apply_filters( 'mlab_forms_style_vars', array() );

		$css = '';
		foreach ( $vars as $name => $value ) {
			if ( preg_match( '/^--mlab-form-[a-z0-9-]+$/', (string) $name ) ) {
				$css .= $name . ':' . str_replace( array( ';', '{', '}', '<', '>' ), '', (string) $value ) . ';';
			}
		}

		return '' === $css ? null : $css;
	}
}
