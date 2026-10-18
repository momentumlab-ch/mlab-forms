<?php
/**
 * Tests für mehrsprachige Texte im Formular.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Tests\Unit;

use MLab_Forms\Forms\Form;
use MLab_Forms\Forms\Localizer;
use PHPUnit\Framework\TestCase;

final class LocalizerTest extends TestCase {

	protected function setUp(): void {
		mlab_test_reset();
	}

	public function test_text_nimmt_die_uebersetzung(): void {
		$field = array(
			'label' => 'Name',
			'i18n'  => array( 'fr' => array( 'label' => 'Nom' ) ),
		);

		$this->assertSame( 'Nom', Localizer::text( $field, 'label', 'fr' ) );
	}

	public function test_text_faellt_auf_den_standardtext_zurueck(): void {
		$field = array(
			'label' => 'Name',
			'help'  => 'Hilfe',
			'i18n'  => array(
				'fr' => array(
					'label' => '   ',
					'help'  => 5,
				),
			),
		);

		$this->assertSame( 'Name', Localizer::text( $field, 'label', 'fr' ) );
		$this->assertSame( 'Hilfe', Localizer::text( $field, 'help', 'fr' ) );
		$this->assertSame( 'Name', Localizer::text( $field, 'label', 'en' ) );
		$this->assertSame( '', Localizer::text( $field, 'placeholder', 'fr' ) );
	}

	public function test_formular_in_zweiter_sprache(): void {
		$form = $this->form()->localized( 'fr' );

		$this->assertSame( 'fr', $form->language() );
		$this->assertSame( 'Envoyer', $form->setting( 'submit_label' ) );
		$this->assertSame( 'Merci!', $form->setting( 'success_message' ) );
		$fields = $form->fields();
		$this->assertSame( 'Nom', $fields[0]['label'] );
		$this->assertSame( 'p. ex. Anne', $fields[0]['placeholder'] );
		$this->assertSame( 'Votre nom', $fields[0]['help'] );
		$this->assertSame( '<p>Bonjour</p>', $fields[2]['content'] );
	}

	public function test_standardsprache_und_unbekannte_sprache_nutzen_die_normalen_texte(): void {
		foreach ( array( 'de', 'it', '', '<b>' ) as $lang ) {
			$fields = $this->form()->localized( $lang )->fields();
			$this->assertSame( 'Name', $fields[0]['label'], $lang );
			$this->assertSame( 'de', $this->form()->localized( $lang )->language(), $lang );
		}
		$this->assertSame( 'Senden', $this->form()->localized( 'de' )->setting( 'submit_label' ) );
	}

	public function test_nicht_uebersetzte_texte_bleiben_standardtext(): void {
		$form = $this->form()->localized( 'en' );

		$this->assertSame( 'Senden', $form->setting( 'submit_label' ) );
		$this->assertSame( 'Name', $form->fields()[0]['label'] );
	}

	public function test_optionen_behalten_den_wert_und_wechseln_die_beschriftung(): void {
		$choices = $this->form()->localized( 'fr' )->prepared_fields()[1]['choices'];

		$this->assertSame( 'Basel', $choices[0]['value'] );
		$this->assertSame( 'Bâle', $choices[0]['label'] );
		$this->assertSame( 'Bern', $choices[1]['value'] );
		$this->assertSame( 'Bern', $choices[1]['label'] );

		$default = $this->form()->prepared_fields()[1]['choices'];
		$this->assertSame( 'Basel', $default[0]['label'] );
	}

	public function test_einsprachiges_formular_bleibt_unveraendert(): void {
		$form = new Form( 1, 'X', array( 'schema_version' => 1, 'fields' => array( array( 'id' => 'a', 'type' => 'text', 'label' => 'A' ) ) ) );

		$this->assertSame( $form, $form->localized( 'fr' ) );
		$this->assertSame( '', $form->language() );
		$this->assertSame( '', $form->default_language() );
	}

	public function test_sprachen_werden_bereinigt(): void {
		$form = new Form( 1, 'X', array( 'schema_version' => 1, 'languages' => array( 'de', 'FR', 'de', 'fr' ), 'fields' => array() ) );

		$this->assertSame( array( 'de', 'fr' ), $form->languages() );
		$this->assertSame( 'de', $form->default_language() );
	}

	public function test_apply_aendert_das_schema_nicht_zweimal(): void {
		$schema = $this->schema();
		$once   = Localizer::apply( $schema, 'fr' );

		$this->assertSame( 'Nom', $once['fields'][0]['label'] );
		$this->assertSame( 'Name', $schema['fields'][0]['label'] );
		$this->assertArrayNotHasKey( 'i18n', $once['fields'][0] );
	}

	private function form(): Form {
		return new Form( 4, 'Kontakt', $this->schema() );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function schema(): array {
		return array(
			'schema_version' => 1,
			'languages'      => array( 'de', 'fr', 'en' ),
			'settings'       => array(
				'submit_label'    => 'Senden',
				'success_message' => 'Danke!',
				'i18n'            => array( 'fr' => array( 'submit_label' => 'Envoyer', 'success_message' => 'Merci!' ) ),
			),
			'fields'         => array(
				array(
					'id'          => 'name',
					'type'        => 'text',
					'label'       => 'Name',
					'placeholder' => 'z. B. Anna',
					'help'        => 'Dein Name',
					'i18n'        => array( 'fr' => array( 'label' => 'Nom', 'placeholder' => 'p. ex. Anne', 'help' => 'Votre nom' ) ),
				),
				array(
					'id'      => 'ort',
					'type'    => 'select',
					'label'   => 'Ort',
					'options' => array( 'Basel', 'Bern' ),
					'i18n'    => array( 'fr' => array( 'options' => array( 'Basel' => 'Bâle' ) ) ),
				),
				array(
					'id'      => 'info',
					'type'    => 'html',
					'content' => '<p>Hallo</p>',
					'i18n'    => array( 'fr' => array( 'content' => '<p>Bonjour</p>' ) ),
				),
			),
		);
	}
}
