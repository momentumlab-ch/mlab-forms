<?php
/**
 * Gehashte IP-Adressen.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Privacy;

defined( 'ABSPATH' ) || exit;

/**
 * IP-Adressen werden nie im Klartext gespeichert, sondern nur als HMAC mit dem Salt der Website.
 */
final class IpHasher {

	/**
	 * IP-Adresse der Anfrage.
	 *
	 * Standard ist REMOTE_ADDR, weil Proxy-Header gefälscht werden können. Hinter Cloudflare
	 * o. ä. lässt sich die echte Adresse über den Filter «mlab_forms_client_ip» liefern.
	 */
	public static function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/**
		 * IP-Adresse des Absenders.
		 *
		 * @param string $ip IP aus REMOTE_ADDR.
		 */
		return (string) apply_filters( 'mlab_forms_client_ip', $ip );
	}

	/**
	 * HMAC der IP-Adresse.
	 *
	 * @param string $ip IP-Adresse.
	 */
	public static function hash( string $ip ): string {
		return '' === $ip ? '' : hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) . 'mlab-forms' );
	}
}
