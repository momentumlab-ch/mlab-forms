<?php
/**
 * Welche Spam-Dienste sind in Gebrauch?
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Spam;

use MLab_Forms\Forms\FormPostType;
use MLab_Forms\Forms\FormRepository;
use MLab_Forms\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Wertet Einstellungen und veröffentlichte Formulare aus. Grundlage für den Datenschutz-Textbaustein:
 * nur Dienste, die tatsächlich Daten erhalten, werden genannt.
 */
final class Usage {

	/**
	 * Dienste, die ein Formular oder die Einstellungen eingeschaltet haben und die Schlüssel haben.
	 *
	 * @return list<string> Schlüssel wie turnstile, recaptcha, hcaptcha, akismet.
	 */
	public static function services(): array {
		$used = array();
		$sets = array( array() );
		$ids  = get_posts(
			array(
				'post_type'   => FormPostType::POST_TYPE,
				'post_status' => 'publish',
				'numberposts' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts -- wenige Formulare.
				'fields'      => 'ids',
			)
		);
		$ids  = array_map( 'intval', $ids );
		update_meta_cache( 'post', $ids );
		foreach ( $ids as $id ) {
			$sets[] = (array) ( FormRepository::schema( $id )['settings']['spam'] ?? array() );
		}

		foreach ( $sets as $spam ) {
			$used = array_merge( $used, self::services_of( $spam ) );
		}

		return array_values( array_unique( $used ) );
	}

	/**
	 * Dienste einer Spam-Einstellung; fehlende Angaben gelten wie in den Plugin-Einstellungen.
	 *
	 * @param array<string, mixed> $spam Einstellung eines Formulars (leer: nur die globale).
	 * @return list<string>
	 */
	public static function services_of( array $spam ): array {
		$services = array();
		$provider = Services::normalize( array_key_exists( 'provider', $spam ) ? $spam['provider'] : Settings::get( 'spam_provider' ) );
		if ( '' !== $provider && Services::configured( $provider ) ) {
			$services[] = $provider;
		}
		$akismet = array_key_exists( 'akismet', $spam ) ? ! empty( $spam['akismet'] ) : (bool) Settings::get( 'spam_akismet' );
		if ( $akismet && '' !== Services::akismet_key() ) {
			$services[] = Services::AKISMET;
		}

		return $services;
	}
}
