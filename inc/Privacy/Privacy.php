<?php
/**
 * Datenschutz-Werkzeuge von WordPress.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Privacy;

use MLab_Forms\Entries\EntryFormatter;
use MLab_Forms\Entries\EntryRepository;
use MLab_Forms\Forms\FormRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Exporter, Eraser und Textvorschlag für die Datenschutzerklärung (revDSG/DSGVO).
 */
final class Privacy {

	/**
	 * Hängt alles an.
	 */
	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'add_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'add_eraser' ) );
		add_action( 'admin_init', array( $this, 'add_policy_text' ) );
	}

	/**
	 * Exporter registrieren.
	 *
	 * @param array<string, array<string, mixed>> $exporters Exporter.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_exporter( array $exporters ): array {
		$exporters['mlab-forms'] = array(
			'exporter_friendly_name' => __( 'MomentumLab Forms: Anfragen', 'mlab-forms' ),
			'callback'               => array( self::class, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Eraser registrieren.
	 *
	 * @param array<string, array<string, mixed>> $erasers Eraser.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_eraser( array $erasers ): array {
		$erasers['mlab-forms'] = array(
			'eraser_friendly_name' => __( 'MomentumLab Forms: Anfragen', 'mlab-forms' ),
			'callback'             => array( self::class, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Alle Anfragen mit dieser E-Mail-Adresse.
	 *
	 * @param string $email Adresse.
	 * @return array{data: list<array<string, mixed>>, done: bool}
	 */
	public static function export( string $email ): array {
		$data = array();
		foreach ( self::entries_for( $email ) as $entry ) {
			$items = array();
			foreach ( EntryFormatter::rows( $entry ) as $label => $value ) {
				$items[] = array(
					'name'  => (string) $label,
					'value' => $value,
				);
			}
			$data[] = array(
				'group_id'    => 'mlab-forms',
				'group_label' => __( 'Anfragen', 'mlab-forms' ),
				'item_id'     => 'mlab-entry-' . $entry['id'],
				'data'        => $items,
			);
		}

		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Löscht alle Anfragen mit dieser E-Mail-Adresse endgültig, inklusive Notizen.
	 *
	 * @param string $email Adresse.
	 * @return array{items_removed: bool, items_retained: bool, messages: list<string>, done: bool}
	 */
	public static function erase( string $email ): array {
		$ids     = array_column( self::entries_for( $email ), 'id' );
		$removed = EntryRepository::delete( $ids );

		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Textvorschlag unter «Einstellungen › Datenschutz».
	 */
	public function add_policy_text(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		wp_add_privacy_policy_content( 'MomentumLab Forms', wp_kses_post( wpautop( self::policy_text() ) ) );
	}

	/**
	 * Anfragen, in denen die Adresse in einem E-Mail-Feld steht.
	 *
	 * @param string $email Adresse.
	 * @return list<array<string, mixed>>
	 */
	private static function entries_for( string $email ): array {
		$email = strtolower( trim( $email ) );

		return array_values(
			array_filter(
				EntryRepository::find_by_email( $email ),
				static fn( array $entry ) => in_array( $email, self::emails_in( $entry ), true )
			)
		);
	}

	/**
	 * E-Mail-Adressen aus den E-Mail-Feldern einer Anfrage.
	 *
	 * @param array<string, mixed> $entry Anfrage.
	 * @return list<string>
	 */
	private static function emails_in( array $entry ): array {
		$form   = EntryFormatter::form( (int) $entry['form_id'] );
		$emails = array();
		foreach ( (array) $entry['data'] as $id => $value ) {
			$field    = $form ? $form->field( (string) $id ) : null;
			$is_email = $field ? 'email' === $field['type'] : is_email( (string) $value );
			if ( $is_email && is_string( $value ) ) {
				$emails[] = strtolower( $value );
			}
		}

		return $emails;
	}

	/**
	 * Vorschlag für die Datenschutzerklärung.
	 */
	private static function policy_text(): string {
		return __(
			"Wenn du uns über ein Formular auf dieser Website eine Anfrage sendest, speichern wir deine Angaben, um die Anfrage zu bearbeiten und dir zu antworten. Dazu gehören die Felder des Formulars, die Seite, von der du sie gesendet hast, die Sprache deines Browsers und eine verschlüsselte Kennung deiner IP-Adresse (keine IP-Adresse im Klartext). Die Kennung dient nur dem Schutz vor Spam.\n\nDu bekommst eine Bestätigung per E-Mail, falls das Formular dies vorsieht. Anfragen werden nach der festgelegten Aufbewahrungsfrist (standardmässig 365 Tage) automatisch gelöscht. Du kannst jederzeit Auskunft über deine gespeicherten Daten verlangen oder ihre Löschung beantragen.\n\nZur Bearbeitung von Anfragen kann ein KI-Assistent eingesetzt werden. Er liest Anfragen, fasst sie zusammen und erstellt Antwortentwürfe. Diese Entwürfe prüfen wir, bevor wir sie senden; die KI versendet selbst keine Nachrichten. Rechtsgrundlage ist in der Schweiz das revDSG, für Gäste aus der EU Art. 6 Abs. 1 lit. b DSGVO (Bearbeitung deiner Anfrage).",
			'mlab-forms'
		);
	}
}
