<?php
/**
 * Tests für Auswahllisten: Standardzeile, leere erste Zeile, Vorauswahl.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Tests\Unit;

use MLab_Forms\Fields\FieldTypes;
use MLab_Forms\Forms\Form;
use MLab_Forms\Forms\OptionsResolver;
use MLab_Forms\Forms\SchemaValidator;
use PHPUnit\Framework\TestCase;

final class ChoiceListTest extends TestCase {

	public function test_standardzeile_wird_aufgeloest(): void {
		$choices = OptionsResolver::resolve(
			array(
				'options' => array(
					'A',
					array( 'value' => 'B', 'label' => 'Beta', 'default' => true ),
					array( 'value' => 'C', 'default' => 'ja' ),
				),
			)
		);

		$this->assertSame( array( false, true, false ), array_column( $choices, 'default' ) );
	}

	public function test_reihenfolge_prefill_vor_standardzeile_vor_leer(): void {
		$field = $this->field();
		$type  = Form::type_of( $field );

		$this->assertSame( 'drei', $type->initial_value( $field, 'drei' ), 'Prefill gewinnt.' );
		$this->assertSame( 'zwei', $type->initial_value( $field, null ), 'Dann die Standardzeile.' );
		$this->assertSame( 'zwei', $type->initial_value( $field, '' ), 'Ein leerer Parameter zählt nicht.' );

		unset( $field['choices'][1]['default'] );
		$this->assertSame( '', $type->initial_value( $field, null ), 'Sonst leer.' );
	}

	public function test_standardzeile_bei_mehrfachauswahl_ist_eine_liste(): void {
		$field         = $this->field();
		$field['type'] = 'checkbox_group';

		$this->assertSame( array( 'zwei' ), Form::type_of( $field )->initial_value( $field, null ) );
	}

	public function test_standardzeile_ist_im_html_vorgewaehlt(): void {
		$field = $this->field();
		$value = Form::type_of( $field )->initial_value( $field, null );
		$html  = Form::type_of( $field )->render( $field, $value, null );

		$this->assertMatchesRegularExpression( '/<option value="zwei" selected>/', $html );
		$this->assertDoesNotMatchRegularExpression( '/<option value="eins" selected>/', $html );
	}

	public function test_leere_erste_zeile_ist_bei_select_standard(): void {
		$html = Form::type_of( $this->field() )->render( $this->field(), '', null );

		$this->assertMatchesRegularExpression( '/<select[^>]*><option value="" selected>Bitte wählen<\/option><option value="eins"/', $html );
	}

	public function test_leere_erste_zeile_mit_eigenem_text_oder_ohne(): void {
		$with_text = array( 'placeholder' => 'Angebot wählen …' ) + $this->field();
		$without   = array( 'empty_first' => false ) + $this->field();

		$this->assertStringContainsString( '<option value="" selected>Angebot wählen …</option>', Form::type_of( $with_text )->render( $with_text, '', null ) );
		$this->assertStringNotContainsString( '<option value="">', Form::type_of( $without )->render( $without, '', null ) );
		$this->assertStringContainsString( '<option value="eins"', Form::type_of( $without )->render( $without, '', null ) );
	}

	public function test_leere_erste_zeile_ist_bei_pflicht_nicht_waehlbar(): void {
		$required = array( 'required' => true ) + $this->field();
		$optional = $this->field();

		$this->assertStringContainsString( '<option value="" disabled selected>Bitte wählen</option>', Form::type_of( $required )->render( $required, '', null ) );
		$this->assertStringNotContainsString( 'disabled', Form::type_of( $optional )->render( $optional, '', null ) );
		$this->assertStringNotContainsString( '<option value="" disabled selected>', Form::type_of( $required )->render( $required, 'eins', null ) );
	}

	public function test_radio_hat_keine_leere_zeile(): void {
		$field         = $this->field();
		$field['type'] = 'radio';

		$this->assertStringNotContainsString( 'Bitte wählen', Form::type_of( $field )->render( $field, '', null ) );
	}

	public function test_prefill_schalter(): void {
		$this->assertFalse( Form::prefill_enabled( array() ) );
		$this->assertTrue( Form::prefill_enabled( array( 'prefill' => array( 'param' => 'angebot' ) ) ) );
		$this->assertTrue( Form::prefill_enabled( array( 'prefill' => array( 'param' => 'angebot', 'enabled' => true ) ) ) );
		$this->assertFalse( Form::prefill_enabled( array( 'prefill' => array( 'param' => 'angebot', 'enabled' => false ) ) ) );
	}

	public function test_fremder_wert_nur_bei_eingeschaltetem_prefill(): void {
		$field = $this->field() + array( 'prefill' => array( 'param' => 'angebot' ) );
		$type  = Form::type_of( $field );

		$this->assertNull( $type->validate( 'ausserhalb', $field ) );

		$field['prefill']['enabled'] = false;
		$this->assertNotNull( $type->validate( 'ausserhalb', $field ) );
	}

	public function test_schema_hoechstens_eine_standardzeile(): void {
		$ok = $this->schema_with_options( array( 'A', array( 'value' => 'B', 'default' => true ) ) );
		$two = $this->schema_with_options( array( array( 'value' => 'A', 'default' => true ), array( 'value' => 'B', 'default' => true ) ) );
		$bad = $this->schema_with_options( array( array( 'value' => 'A', 'default' => 'ja' ) ) );

		$this->assertSame( array(), SchemaValidator::validate( $ok ) );
		$this->assertSame( array( 'fields.0' ), array_column( SchemaValidator::validate( $two ), 'path' ) );
		$this->assertSame( array( 'fields.0' ), array_column( SchemaValidator::validate( $bad ), 'path' ) );
	}

	public function test_schema_prueft_empty_first_und_prefill_enabled(): void {
		$schema                          = $this->schema_with_options( array( 'A' ) );
		$schema['fields'][0]['empty_first'] = 'nein';
		$schema['fields'][0]['prefill']     = array( 'param' => 'a', 'enabled' => 'nein' );

		$this->assertSame( array( 'fields.0.empty_first', 'fields.0.prefill.enabled' ), array_column( SchemaValidator::validate( $schema ), 'path' ) );
	}

	public function test_bestehende_schemas_ohne_neue_felder_bleiben_gueltig(): void {
		$schema = $this->schema_with_options( array( 'A', 'B' ) );
		$schema['fields'][0]['prefill'] = array( 'param' => 'angebot', 'data_attribute' => 'data-anfrage' );

		$this->assertSame( array(), SchemaValidator::validate( $schema ) );
		$this->assertSame( array(), SchemaValidator::validate( array( 'schema_version' => 1, 'settings' => array( 'retention_days' => 0, 'spam' => array( 'provider' => 'turnstile', 'akismet' => true ), 'sender' => array( 'reply_to_mode' => 'visitor' ) ), 'fields' => $schema['fields'] ) ) );
	}

	public function test_schema_spam_anbieter_und_absender(): void {
		$fields = $this->schema_with_options( array( 'A' ) )['fields'];
		$check  = static fn( array $settings ): array => array_column( SchemaValidator::validate( array( 'schema_version' => 1, 'settings' => $settings, 'fields' => $fields ) ), 'path' );

		$this->assertSame( array( 'settings.spam.provider' ), $check( array( 'spam' => array( 'provider' => 'turnstile,hcaptcha' ) ) ) );
		$this->assertSame( array( 'settings.spam.provider' ), $check( array( 'spam' => array( 'provider' => array( 'turnstile' ) ) ) ) );
		$this->assertSame( array( 'settings.spam.akismet' ), $check( array( 'spam' => array( 'akismet' => 'ja' ) ) ) );
		$this->assertSame( array( 'settings.sender.email', 'settings.sender.reply_to_mode' ), $check( array( 'sender' => array( 'email' => 'kaputt', 'reply_to_mode' => 'alle' ) ) ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function field(): array {
		return array(
			'id'      => 'angebot',
			'type'    => 'select',
			'label'   => 'Angebot',
			'html_id' => 'x-angebot',
			'choices' => array(
				array( 'value' => 'eins', 'label' => 'Eins', 'group' => '', 'default' => false ),
				array( 'value' => 'zwei', 'label' => 'Zwei', 'group' => '', 'default' => true ),
				array( 'value' => 'drei', 'label' => 'Drei', 'group' => '', 'default' => false ),
			),
		);
	}

	/**
	 * @param list<mixed> $options Optionen.
	 * @return array<string, mixed>
	 */
	private function schema_with_options( array $options ): array {
		return array(
			'schema_version' => 1,
			'fields'         => array( array( 'id' => 'wahl', 'type' => 'select', 'label' => 'Wahl', 'options' => $options ) ),
		);
	}
}
