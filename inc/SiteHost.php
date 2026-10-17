<?php
/**
 * Vergleich von Hostnamen und Adressen mit dem Host der Website.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Subdomains gelten als gleiche Website: «www.firma.ch», «firma.ch» und «mail.firma.ch» passen zueinander.
 */
final class SiteHost {

	/**
	 * Gehört der Hostname zur Website?
	 *
	 * @param string $host Hostname, z. B. aus einer Captcha-Antwort.
	 */
	public static function matches( string $host ): bool {
		$host = self::normalize( $host );
		$site = self::normalize( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

		if ( '' === $host || '' === $site ) {
			return false;
		}

		return $host === $site || str_ends_with( $host, '.' . $site ) || str_ends_with( $site, '.' . $host );
	}

	/**
	 * Gehört die Domain der E-Mail-Adresse zur Website?
	 *
	 * @param string $email Adresse.
	 */
	public static function email_matches( string $email ): bool {
		$at = strrpos( $email, '@' );

		return false !== $at && self::matches( substr( $email, $at + 1 ) );
	}

	/**
	 * Kleingeschrieben, ohne «www.» und ohne abschliessenden Punkt.
	 *
	 * @param string $host Hostname.
	 */
	private static function normalize( string $host ): string {
		$host = strtolower( trim( $host, " \t\n\r\0\x0B." ) );

		return str_starts_with( $host, 'www.' ) ? substr( $host, 4 ) : $host;
	}
}
