<?php
/**
 * WhatsApp-Knopf: Shortcode [mlab_whatsapp] und optional ein schwebender Knopf auf allen Seiten.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Frontend;

use MLab_Forms\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Öffnet WhatsApp mit vorausgefülltem Text. Ein normaler Link: kein Skript, kein Cookie, nichts von WhatsApp geladen.
 */
final class WhatsApp {

	public const TAG = 'mlab_whatsapp';

	/**
	 * Hängt Shortcode und schwebenden Knopf an.
	 */
	public function register(): void {
		add_shortcode( self::TAG, array( $this, 'shortcode' ) );
		add_action( 'wp_footer', array( $this, 'floating' ) );
	}

	/**
	 * Nummer nur mit Ziffern, international ohne führende 00 oder +, z. B. 41765710532.
	 *
	 * @param string $number Eingabe, z. B. «+41 76 571 05 32».
	 */
	public static function digits( string $number ): string {
		$digits = (string) preg_replace( '/\D+/', '', $number );

		return str_starts_with( $digits, '00' ) ? substr( $digits, 2 ) : $digits;
	}

	/**
	 * Text mit Platzhaltern: {page} Titel der aktuellen Seite, {offer} Angebot aus dem Shortcode.
	 *
	 * @param string $template Vorlage.
	 * @param string $offer    Angebot.
	 */
	public static function text( string $template, string $offer = '' ): string {
		$page = is_singular() ? get_the_title( get_queried_object_id() ) : wp_get_document_title();
		$page = wp_strip_all_tags( html_entity_decode( (string) $page, ENT_QUOTES, 'UTF-8' ) );
		$text = strtr(
			$template,
			array(
				'{page}'  => '' !== $offer ? $offer : $page,
				'{offer}' => '' !== $offer ? $offer : $page,
			)
		);

		return trim( $text );
	}

	/**
	 * Link zu WhatsApp, oder '' ohne Nummer.
	 *
	 * @param string $text Vorausgefüllter Text.
	 */
	public static function url( string $text ): string {
		$number = self::digits( (string) Settings::get( 'whatsapp_number' ) );
		if ( strlen( $number ) < 8 ) {
			return '';
		}

		return 'https://wa.me/' . $number . ( '' !== $text ? '?text=' . rawurlencode( $text ) : '' );
	}

	/**
	 * [mlab_whatsapp label="Per WhatsApp anfragen" offer="Schankanlage" class="btn"]
	 *
	 * @param array<string, string>|string $atts Attribute.
	 */
	public function shortcode( $atts ): string {
		$atts     = shortcode_atts(
			array(
				'label' => __( 'Per WhatsApp anfragen', 'mlab-forms' ),
				'offer' => '',
				'text'  => '',
				'class' => '',
			),
			(array) $atts,
			self::TAG
		);
		$template = '' !== $atts['text'] ? $atts['text'] : (string) Settings::get( 'whatsapp_text' );
		$url      = self::url( self::text( $template, sanitize_text_field( $atts['offer'] ) ) );
		if ( '' === $url ) {
			return current_user_can( 'edit_posts' ) ? '<p class="mlab-form-missing">' . esc_html__( 'MomentumLab Forms: Bitte unter Einstellungen › Allgemein die WhatsApp-Nummer eintragen.', 'mlab-forms' ) . '</p>' : '';
		}

		return sprintf(
			'<a class="%s" href="%s" target="_blank" rel="noopener" data-mlab-track="whatsapp">%s</a>',
			esc_attr( trim( 'mlab-whatsapp ' . $atts['class'] ) ),
			esc_url( $url ),
			esc_html( $atts['label'] )
		);
	}

	/**
	 * Schwebender Knopf unten rechts, wenn eingeschaltet. Stil inline (wenige Zeilen), damit keine Datei mehr lädt.
	 */
	public function floating(): void {
		if ( ! Settings::get( 'whatsapp_float' ) || is_admin() ) {
			return;
		}
		$url = self::url( self::text( (string) Settings::get( 'whatsapp_text' ) ) );
		if ( '' === $url ) {
			return;
		}
		$label = __( 'Per WhatsApp anfragen', 'mlab-forms' );
		$icon  = '<svg viewBox="0 0 32 32" width="28" height="28" aria-hidden="true" focusable="false"><path fill="currentColor" d="M16 3a13 13 0 0 0-11.2 19.6L3 29l6.6-1.7A13 13 0 1 0 16 3Zm0 23.7c-2 0-4-.6-5.7-1.6l-.4-.2-3.9 1 1-3.8-.3-.4A10.7 10.7 0 1 1 16 26.7Zm5.9-8c-.3-.2-1.9-1-2.2-1-.3-.1-.5-.2-.7.1l-1 1.3c-.2.2-.4.2-.7.1a8.8 8.8 0 0 1-4.3-3.8c-.3-.6.3-.5.9-1.7.1-.2 0-.4 0-.5l-1-2.4c-.3-.6-.5-.5-.7-.5h-.6c-.2 0-.6.1-.9.4-.3.3-1.2 1.1-1.2 2.8s1.2 3.2 1.4 3.5c.2.2 2.4 3.6 5.8 5 2.1.9 3 1 4 .8.7-.1 1.9-.8 2.2-1.5.3-.8.3-1.4.2-1.5l-.6-.3Z"/></svg>';

		printf(
			'<style>.mlab-wa{position:fixed;right:16px;bottom:16px;z-index:9990;display:flex;align-items:center;justify-content:center;width:56px;height:56px;border-radius:50%%;background:#25d366;color:#fff;box-shadow:0 4px 14px rgba(0,0,0,.25);transition:transform .15s}.mlab-wa:hover,.mlab-wa:focus-visible{transform:scale(1.06);color:#fff}@media (prefers-reduced-motion:reduce){.mlab-wa{transition:none}}@media print{.mlab-wa{display:none}}</style><a class="mlab-wa" href="%s" target="_blank" rel="noopener" aria-label="%s" title="%s" data-mlab-track="whatsapp">%s</a>',
			esc_url( $url ),
			esc_attr( $label ),
			esc_attr( $label ),
			$icon // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Festes SVG.
		);
	}
}
