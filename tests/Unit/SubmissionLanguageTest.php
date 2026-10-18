<?php
/**
 * Tests für Absendung, Mail und Ausgabe in der Sprache des Formulars.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Tests\Unit;

use MLab_Forms\Forms\Form;
use MLab_Forms\Frontend\Renderer;
use MLab_Forms\Mail\Notifier;
use MLab_Forms\Submission\Submission;
use MLab_Forms\Submission\SubmissionHandler;
use PHPUnit\Framework\TestCase;

final class SubmissionLanguageTest extends TestCase {

	protected function setUp(): void {
		mlab_test_reset();
		$GLOBALS['mlab_mails'] = array();
		update_option( 'admin_email', 'admin@example.org' );
	}

	public function test_erfolgsmeldung_in_der_sprache_der_absendung(): void {
		$form = SubmissionHandler::localize( $this->form(), array( 'mlab_lang' => 'fr' ) );

		$this->assertSame( 'fr', $form->language() );
		$this->assertSame( 'Merci pour votre message!', SubmissionHandler::success( $form, null )->message );
	}

	public function test_ungueltige_sprache_faellt_auf_die_standardsprache_zurueck(): void {
		foreach ( array( 'it', '<b>', '', null, array( 'fr' ), 'fr;drop' ) as $lang ) {
			$form = SubmissionHandler::localize( $this->form(), array( 'mlab_lang' => $lang ) );

			$this->assertSame( 'de', $form->language(), (string) json_encode( $lang ) );
			$this->assertSame( 'Danke für deine Nachricht!', SubmissionHandler::success( $form, null )->message );
		}
		$this->assertSame( 'de', SubmissionHandler::localize( $this->form(), array() )->language() );
	}

	public function test_einsprachiges_formular_ignoriert_mlab_lang(): void {
		$form = new Form( 1, 'X', array( 'schema_version' => 1, 'fields' => array( array( 'id' => 'a', 'type' => 'text', 'label' => 'A' ) ) ) );

		$this->assertSame( '', SubmissionHandler::localize( $form, array( 'mlab_lang' => 'fr' ) )->language() );
	}

	public function test_beschriftung_in_der_mail_an_den_besucher(): void {
		$submission = $this->submission( 'fr' );

		$this->assertSame( 2, Notifier::send_all( $submission ) );
		$guest = $this->mail_to( 'gast@example.org' );

		$this->assertSame( 'Votre demande chez Testseite', $guest['subject'] );
		$this->assertStringContainsString( 'Nom: Anna', $guest['body'] );
		$this->assertStringContainsString( 'Bonjour Anna', $guest['body'] );
	}

	public function test_mail_an_die_admin_adresse_bleibt_in_der_standardsprache(): void {
		Notifier::send_all( $this->submission( 'fr' ) );
		$admin = $this->mail_to( 'admin@example.org' );

		$this->assertSame( 'Neue Anfrage von Anna', $admin['subject'] );
		$this->assertStringContainsString( 'Name: Anna', $admin['body'] );
	}

	public function test_mail_an_besucher_und_admin_gemeinsam_folgt_der_sprache_der_absendung(): void {
		$schema                                         = $this->schema();
		$schema['settings']['notifications'][0]['to']   = '{site_admin_email}, {field:email}';
		$form                                           = new Form( 7, 'Kontakt', $schema );
		$submission                                     = new Submission( $form->localized( 'fr' ), array( 'name' => 'Anna', 'email' => 'gast@example.org' ) );

		Notifier::send_all( $submission );

		$this->assertSame( 'Nouvelle demande de Anna', $GLOBALS['mlab_mails'][0]['subject'] );
	}

	public function test_mail_in_standardsprache_nutzt_die_normalen_texte(): void {
		Notifier::send_all( $this->submission( 'de' ) );

		$this->assertSame( 'Deine Anfrage bei Testseite', $this->mail_to( 'gast@example.org' )['subject'] );
	}

	public function test_formular_enthaelt_verstecktes_sprachfeld(): void {
		$html = $this->hidden_inputs( $this->form()->localized( 'fr' ) );

		$this->assertStringContainsString( 'name="mlab_lang" value="fr"', $html );
		$this->assertStringNotContainsString( 'mlab_lang', $this->hidden_inputs( new Form( 1, 'X', array( 'schema_version' => 1, 'fields' => array() ) ) ) );
	}

	public function test_vorschau_in_zweiter_sprache(): void {
		$fr = Renderer::preview( $this->form(), 'fr' );
		$de = Renderer::preview( $this->form() );

		$this->assertStringContainsString( 'Envoyer', $fr );
		$this->assertStringContainsString( 'Nom', $fr );
		$this->assertStringContainsString( 'lang="fr"', $fr );
		$this->assertStringContainsString( 'Senden', $de );
		$this->assertStringContainsString( 'Basel', $de );
		$this->assertStringContainsString( 'Bâle', $fr );
		$this->assertStringContainsString( 'value="Basel"', $fr );
	}

	public function test_texte_der_uebersetzung_werden_escaped(): void {
		$schema                                            = $this->schema();
		$schema['fields'][0]['i18n']['fr']['label']        = '<script>alert(1)</script>';
		$html = Renderer::preview( new Form( 7, 'Kontakt', $schema ), 'fr' );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	/**
	 * @param Form $form Formular.
	 */
	private function hidden_inputs( Form $form ): string {
		$method = new \ReflectionMethod( Renderer::class, 'hidden_inputs' );

		return $method->invoke( null, $form );
	}

	/**
	 * @param string $to Empfänger.
	 * @return array<string, mixed>
	 */
	private function mail_to( string $to ): array {
		foreach ( $GLOBALS['mlab_mails'] as $mail ) {
			if ( in_array( $to, $mail['to'], true ) ) {
				return $mail;
			}
		}
		$this->fail( 'Keine Mail an ' . $to );
	}

	private function submission( string $lang ): Submission {
		return new Submission( $this->form()->localized( $lang ), array( 'name' => 'Anna', 'email' => 'gast@example.org' ) );
	}

	private function form(): Form {
		return new Form( 7, 'Kontakt', $this->schema() );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function schema(): array {
		return array(
			'schema_version' => 1,
			'languages'      => array( 'de', 'fr' ),
			'settings'       => array(
				'submit_label'    => 'Senden',
				'success_message' => 'Danke für deine Nachricht!',
				'i18n'            => array( 'fr' => array( 'submit_label' => 'Envoyer', 'success_message' => 'Merci pour votre message!' ) ),
				'notifications'   => array(
					array(
						'id'      => 'admin',
						'to'      => '{site_admin_email}',
						'subject' => 'Neue Anfrage von {field:name}',
						'body'    => '{all_fields}',
						'i18n'    => array( 'fr' => array( 'subject' => 'Nouvelle demande de {field:name}' ) ),
					),
					array(
						'id'      => 'guest',
						'to'      => '{field:email}',
						'subject' => 'Deine Anfrage bei {site_name}',
						'body'    => "Hoi {field:name}\n\n{all_fields}",
						'i18n'    => array(
							'fr' => array(
								'subject' => 'Votre demande chez {site_name}',
								'body'    => "Bonjour {field:name}\n\n{all_fields}",
							),
						),
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
				array( 'id' => 'email', 'type' => 'email', 'label' => 'E-Mail' ),
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
