<?php
/**
 * Eine geprüfte Absendung.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Submission;

use MLab_Forms\Forms\Form;

defined( 'ABSPATH' ) || exit;

/**
 * Formular, bereinigte Werte, Metadaten und (nach dem Speichern) die Anfrage-ID.
 */
final class Submission {

	/**
	 * ID der gespeicherten Anfrage.
	 *
	 * @var int|null
	 */
	public ?int $entry_id = null;

	/**
	 * Konstruktor.
	 *
	 * @param Form                 $form   Formular.
	 * @param array<string, mixed> $values Feld-ID => bereinigter Wert.
	 * @param array<string, mixed> $meta   Quelle, UTM, Sprache usw.
	 */
	public function __construct( public readonly Form $form, public readonly array $values, public readonly array $meta = array() ) {
	}

	/**
	 * Wert eines Felds als Text.
	 *
	 * @param string $id Feld-ID.
	 */
	public function formatted( string $id ): string {
		$field = $this->form->field( $id );
		if ( null === $field || ! array_key_exists( $id, $this->values ) ) {
			return '';
		}

		return Form::type_of( $field )->format( $this->values[ $id ], $field );
	}
}
