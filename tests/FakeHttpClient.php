<?php
/**
 * Ersatz für die Netzwerkaufrufe der Spam-Dienste.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Tests;

use MLab_Forms\Spam\HttpClient;

/**
 * Liefert vorbereitete Antworten und merkt sich die Aufrufe.
 */
final class FakeHttpClient implements HttpClient {

	/**
	 * Aufrufe: Adresse und Daten.
	 *
	 * @var list<array{url: string, body: array<string, string>}>
	 */
	public array $calls = array();

	/**
	 * Konstruktor.
	 *
	 * @param array{status: int, body: string}|null $response Antwort; null simuliert einen Netzwerkfehler.
	 */
	public function __construct( private ?array $response ) {
	}

	/**
	 * Merkt den Aufruf und gibt die Antwort zurück.
	 *
	 * @param string                $url  Adresse.
	 * @param array<string, string> $body Daten.
	 * @return array{status: int, body: string}|null
	 */
	public function post( string $url, array $body ): ?array {
		$this->calls[] = array(
			'url'  => $url,
			'body' => $body,
		);

		return $this->response;
	}

	/**
	 * Antwort mit JSON-Inhalt.
	 *
	 * @param array<string, mixed> $data Inhalt.
	 */
	public static function json( array $data ): self {
		return new self(
			array(
				'status' => 200,
				'body'   => (string) json_encode( $data ),
			)
		);
	}
}
