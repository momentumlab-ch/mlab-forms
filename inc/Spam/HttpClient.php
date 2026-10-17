<?php
/**
 * Schnittstelle für die Netzwerkaufrufe der Spam-Dienste.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Spam;

defined( 'ABSPATH' ) || exit;

/**
 * Ein POST-Aufruf. Tests ersetzen die Umsetzung über Services::set_client().
 */
interface HttpClient {

	/**
	 * Sendet Formulardaten per POST.
	 *
	 * @param string                $url  Adresse (https).
	 * @param array<string, string> $body Formularfelder.
	 * @return array{status: int, body: string}|null Antwort oder null, wenn der Dienst nicht erreichbar war.
	 */
	public function post( string $url, array $body ): ?array;
}
