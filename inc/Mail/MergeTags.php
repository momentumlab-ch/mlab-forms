<?php
/**
 * Platzhalter in Texten.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Mail;

use MLab_Forms\Admin\EntriesPage;
use MLab_Forms\Submission\Submission;

defined( 'ABSPATH' ) || exit;

/**
 * Ersetzt {field:id}, {all_fields}, {site_name}, {default_recipient} usw. Unbekannte Platzhalter bleiben stehen.
 */
final class MergeTags {

	/**
	 * Ersetzt alle Platzhalter.
	 *
	 * @param string          $text       Text mit Platzhaltern.
	 * @param Submission|null $submission Absendung, falls vorhanden.
	 */
	public static function replace( string $text, ?Submission $submission = null ): string {
		return (string) preg_replace_callback(
			'/\{([a-z_]+)(?::([a-z0-9_]+))?\}/',
			static fn( array $tag ) => self::resolve( $tag[1], $tag[2] ?? '', $submission ) ?? $tag[0],
			$text
		);
	}

	/**
	 * Wert eines Platzhalters oder null, wenn unbekannt.
	 *
	 * @param string          $tag        Name.
	 * @param string          $arg        Argument nach dem Doppelpunkt.
	 * @param Submission|null $submission Absendung.
	 */
	private static function resolve( string $tag, string $arg, ?Submission $submission ): ?string {
		switch ( $tag ) {
			case 'site_name':
				return wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
			case 'site_url':
				return home_url( '/' );
			case 'site_admin_email':
				return implode( ', ', Recipients::defaults() );
			case 'default_recipient':
				return implode( ', ', Recipients::for_form( $submission ? $submission->form : null ) );
			case 'privacy_url':
				return get_privacy_policy_url();
			case 'date':
				return wp_date( 'd.m.Y H:i' );
		}

		return $submission ? self::resolve_submission( $tag, $arg, $submission ) : null;
	}

	/**
	 * Platzhalter, die eine Absendung brauchen.
	 *
	 * @param string     $tag        Name.
	 * @param string     $arg        Argument.
	 * @param Submission $submission Absendung.
	 */
	private static function resolve_submission( string $tag, string $arg, Submission $submission ): ?string {
		switch ( $tag ) {
			case 'field':
				return $submission->formatted( $arg );
			case 'all_fields':
				return self::all_fields( $submission );
			case 'form_title':
				return $submission->form->title;
			case 'entry_id':
				return (string) $submission->entry_id;
			case 'entry_url':
				return $submission->entry_id ? EntriesPage::url( array( 'entry' => $submission->entry_id ) ) : '';
		}

		return null;
	}

	/**
	 * Alle Felder als «Label: Wert», eine Zeile pro Feld.
	 *
	 * @param Submission $submission Absendung.
	 */
	private static function all_fields( Submission $submission ): string {
		$lines = array();
		foreach ( $submission->form->input_fields() as $field ) {
			if ( 'hidden' === $field['type'] ) {
				continue;
			}
			$value   = $submission->formatted( $field['id'] );
			$lines[] = wp_strip_all_tags( (string) ( $field['label'] ?? $field['id'] ) ) . ': ' . ( '' === $value ? '–' : $value );
		}

		return implode( "\n", $lines );
	}
}
