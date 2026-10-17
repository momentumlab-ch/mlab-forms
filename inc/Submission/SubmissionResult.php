<?php
/**
 * Ergebnis einer Absendung.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Submission;

defined( 'ABSPATH' ) || exit;

/**
 * Status, Meldung, Feldfehler und Angaben für Weiterleitung und Tracking.
 */
final class SubmissionResult {

	public const SUCCESS      = 'success';
	public const INVALID      = 'invalid';
	public const RATE_LIMITED = 'rate_limited';
	public const NOT_FOUND    = 'not_found';
	public const ERROR        = 'error';

	/**
	 * Konstruktor.
	 *
	 * @param string                $status   Einer der Status oben.
	 * @param string                $message  Meldung für den Gast.
	 * @param array<string, string> $errors   Feld-ID => Fehlermeldung.
	 * @param array<string, mixed>  $values   Bereinigte Werte (zum erneuten Ausfüllen).
	 * @param string                $redirect Weiterleitung nach Erfolg.
	 * @param array<string, mixed>  $tracking GA4-Event und Parameter.
	 */
	public function __construct(
		public readonly string $status,
		public readonly string $message,
		public readonly array $errors = array(),
		public readonly array $values = array(),
		public readonly string $redirect = '',
		public readonly array $tracking = array()
	) {
	}

	/**
	 * War die Absendung erfolgreich?
	 */
	public function ok(): bool {
		return self::SUCCESS === $this->status;
	}

	/**
	 * HTTP-Status für die REST-Antwort.
	 */
	public function http_status(): int {
		$codes = array(
			self::SUCCESS      => 200,
			self::INVALID      => 422,
			self::RATE_LIMITED => 429,
			self::NOT_FOUND    => 404,
		);

		return $codes[ $this->status ] ?? 500;
	}

	/**
	 * Daten für die REST-Antwort.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'status'   => $this->status,
			'message'  => $this->message,
			'errors'   => (object) $this->errors,
			'redirect' => $this->redirect,
			'tracking' => (object) $this->tracking,
		);
	}
}
