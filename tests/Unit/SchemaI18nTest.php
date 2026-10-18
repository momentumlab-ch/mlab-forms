<?php
/**
 * Tests für Sprachen und Übersetzungen im Schema.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Tests\Unit;

use MLab_Forms\Forms\SchemaValidator;
use MLab_Forms\Forms\Templates;
use PHPUnit\Framework\TestCase;

final class SchemaI18nTest extends TestCase {

	public function test_gueltiges_mehrsprachiges_schema(): void {
		$this->assertSame( array(), SchemaValidator::validate( $this->schema() ) );
	}

	public function test_bestehende_schemas_ohne_sprachen_bleiben_gueltig(): void {
		foreach ( array( 'kontakt', 'anfrage', 'gutschein' ) as $key ) {
			$this->assertSame( array(), SchemaValidator::validate( Templates::get( $key ) ), $key );
		}
	}

	public function test_languages_muss_eine_liste_gueltiger_codes_sein(): void {
		foreach ( array( 'de', array(), array( 'de' => 'x' ), array( 'DE' ), array( 'deutsch' ), array( 'de', 5 ), array( "de\n" ) ) as $languages ) {
			$schema              = $this->schema();
			$schema['languages'] = $languages;
			$this->assertNotSame( array(), $this->errors_at( $schema, 'languages' ), (string) json_encode( $languages ) );
		}
	}

	public function test_doppelte_sprache_wird_gemeldet(): void {
		$schema              = $this->schema();
		$schema['languages'] = array( 'de', 'fr', 'de' );

		$this->assertContains( 'languages.2', array_column( SchemaValidator::validate( $schema ), 'path' ) );
	}

	public function test_zu_viele_sprachen(): void {
		$schema              = $this->schema();
		$schema['languages'] = array( 'aa', 'ab', 'ac', 'ad', 'ae', 'af', 'ag', 'ah', 'ai', 'aj', 'ak', 'al', 'am' );

		$this->assertNotSame( array(), $this->errors_at( $schema, 'languages' ) );
	}

	public function test_i18n_sprache_muss_in_languages_stehen(): void {
		$schema                                = $this->schema();
		$schema['fields'][0]['i18n']['it']     = array( 'label' => 'Nome' );
		$schema['settings']['i18n']['es']      = array( 'submit_label' => 'Enviar' );
		$schema['settings']['notifications'][0]['i18n']['pt'] = array( 'subject' => 'Olá' );

		$paths = array_column( SchemaValidator::validate( $schema ), 'path' );

		$this->assertContains( 'fields.0.i18n.it', $paths );
		$this->assertContains( 'settings.i18n.es', $paths );
		$this->assertContains( 'settings.notifications.0.i18n.pt', $paths );
	}

	public function test_i18n_ohne_languages_ist_ein_fehler(): void {
		$schema = $this->schema();
		unset( $schema['languages'] );

		$this->assertContains( 'fields.0.i18n.fr', array_column( SchemaValidator::validate( $schema ), 'path' ) );
	}

	public function test_unbekannte_und_falsche_textschluessel(): void {
		$schema                            = $this->schema();
		$schema['fields'][0]['i18n']['fr'] = array(
			'required' => 'oui',
			'label'    => array( 'Nom' ),
			'help'     => 7,
		);

		$paths = array_column( SchemaValidator::validate( $schema ), 'path' );

		$this->assertContains( 'fields.0.i18n.fr.required', $paths );
		$this->assertContains( 'fields.0.i18n.fr.label', $paths );
		$this->assertContains( 'fields.0.i18n.fr.help', $paths );
	}

	public function test_i18n_muss_ein_objekt_sein(): void {
		$schema                      = $this->schema();
		$schema['fields'][0]['i18n'] = 'fr';
		$schema['fields'][1]['i18n'] = array( 'fr' => 'Nom' );

		$paths = array_column( SchemaValidator::validate( $schema ), 'path' );

		$this->assertContains( 'fields.0.i18n', $paths );
		$this->assertContains( 'fields.1.i18n.fr', $paths );
	}

	public function test_beschriftung_einer_option_die_es_nicht_gibt(): void {
		$schema                                         = $this->schema();
		$schema['fields'][1]['i18n']['fr']['options']   = array(
			'Basel' => 'Bâle',
			'Genf'  => 'Genève',
		);

		$paths = array_column( SchemaValidator::validate( $schema ), 'path' );

		$this->assertSame( array( 'fields.1.i18n.fr.options.Genf' ), $paths );
	}

	public function test_optionen_ohne_options_im_feld(): void {
		$schema                                       = $this->schema();
		$schema['fields'][0]['i18n']['fr']['options'] = array( 'a' => 'b' );

		$this->assertContains( 'fields.0.i18n.fr.options', array_column( SchemaValidator::validate( $schema ), 'path' ) );
	}

	public function test_optionen_mit_objekt_und_prepend_werden_erkannt(): void {
		$schema                                       = $this->schema();
		$schema['fields'][1]['options']               = array( array( 'value' => 'Basel', 'label' => 'Basel-Stadt' ), 'Bern' );
		$schema['fields'][1]['i18n']['fr']['options'] = array( 'Basel' => 'Bâle' );

		$this->assertSame( array(), SchemaValidator::validate( $schema ) );
	}

	public function test_optionen_einer_quelle_werden_nicht_gegen_feste_werte_geprueft(): void {
		$schema                                       = $this->schema();
		$schema['fields'][1]['options_source']        = array( 'provider' => 'post_type', 'post_type' => 'angebot' );
		$schema['fields'][1]['i18n']['fr']['options'] = array( 'Ski' => 'Ski alpin' );

		$this->assertSame( array(), SchemaValidator::validate( $schema ) );
	}

	/**
	 * @param array<string, mixed> $schema Schema.
	 * @return list<string>
	 */
	private function errors_at( array $schema, string $path ): array {
		return array_values( array_filter( array_column( SchemaValidator::validate( $schema ), 'path' ), static fn( string $found ) => str_starts_with( $found, $path ) ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function schema(): array {
		return array(
			'schema_version' => 1,
			'languages'      => array( 'de', 'fr' ),
			'settings'       => array(
				'submit_label'  => 'Senden',
				'i18n'          => array( 'fr' => array( 'submit_label' => 'Envoyer' ) ),
				'notifications' => array(
					array(
						'to'      => '{site_admin_email}',
						'subject' => 'Neu',
						'i18n'    => array( 'fr' => array( 'subject' => 'Nouveau' ) ),
					),
				),
			),
			'fields'         => array(
				array(
					'id'    => 'name',
					'type'  => 'text',
					'label' => 'Name',
					'i18n'  => array( 'fr' => array( 'label' => 'Nom' ) ),
				),
				array(
					'id'      => 'ort',
					'type'    => 'select',
					'label'   => 'Ort',
					'options' => array( 'Basel', 'Bern' ),
					'i18n'    => array( 'fr' => array( 'options' => array( 'Basel' => 'Bâle' ) ) ),
				),
			),
		);
	}
}
