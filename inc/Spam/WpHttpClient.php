<?php
/**
 * Netzwerkaufrufe über die HTTP-API von WordPress.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Spam;

defined( 'ABSPATH' ) || exit;

/**
 * Nutzt wp_safe_remote_post mit kurzem Timeout. Antworten über 64 kB werden abgelehnt.
 */
final class WpHttpClient implements HttpClient {

	private const TIMEOUT = 5;

	/**
	 * Sendet Formulardaten per POST.
	 *
	 * @param string                $url  Adresse (https).
	 * @param array<string, string> $body Formularfelder.
	 * @return array{status: int, body: string}|null
	 */
	public function post( string $url, array $body ): ?array {
		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout'             => self::TIMEOUT,
				'redirection'         => 0,
				'limit_response_size' => 65536,
				'body'                => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			return null;
		}

		return array(
			'status' => (int) wp_remote_retrieve_response_code( $response ),
			'body'   => (string) wp_remote_retrieve_body( $response ),
		);
	}
}
