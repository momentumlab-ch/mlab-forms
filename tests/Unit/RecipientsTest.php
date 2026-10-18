<?php
/**
 * Tests für die Empfänger der Benachrichtigungen.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Tests\Unit;

use MLab_Forms\Forms\Form;
use MLab_Forms\Forms\SchemaValidator;
use MLab_Forms\Mail\MergeTags;
use MLab_Forms\Mail\Notifier;
use MLab_Forms\Mail\Recipients;
use MLab_Forms\Settings;
use MLab_Forms\Submission\Submission;
use PHPUnit\Framework\TestCase;

final class RecipientsTest extends TestCase {

	protected function setUp(): void {
		mlab_test_reset();
		$GLOBALS['mlab_mails'] = array();
		update_option( 'admin_email', 'admin@example.org' );
	}

	public function test_standard_ist_die_admin_adresse(): void {
		$this->assertSame( array( 'admin@example.org' ), Recipients::defaults() );
		$this->assertSame( 'admin@example.org', MergeTags::replace( '{default_recipient}' ) );
	}

	public function test_globaler_standard_vor_admin_adresse(): void {
		update_option( Settings::OPTION, array( 'default_recipient' => 'info@example.org, verkauf@example.org' ) );

		$this->assertSame( 'info@example.org, verkauf@example.org', MergeTags::replace( '{default_recipient}' ) );
		$this->assertSame( 'info@example.org, verkauf@example.org', MergeTags::replace( '{site_admin_email}' ) );
	}

	public function test_alte_einstellung_gilt_weiter(): void {
		update_option( Settings::OPTION, array( 'email_admin_to' => 'alt@example.org' ) );

		$this->assertSame( array( 'alt@example.org' ), Recipients::defaults() );
	}

	public function test_alte_einstellung_bleibt_beim_speichern_erhalten(): void {
		update_option( Settings::OPTION, array( 'email_admin_to' => 'alt@example.org' ) );

		$saved = Settings::sanitize( array( Settings::TAB_KEY => 'spam' ) );

		$this->assertSame( 'alt@example.org', $saved['default_recipient'] );
	}

	public function test_formular_ueberschreibt_den_standard(): void {
		update_option( Settings::OPTION, array( 'default_recipient' => 'info@example.org' ) );
		$submission = $this->submission( 'verkauf@example.org' );

		$this->assertSame( 'verkauf@example.org', MergeTags::replace( '{default_recipient}', $submission ) );
		$this->assertSame( 'info@example.org', MergeTags::replace( '{default_recipient}' ) );
	}

	public function test_override_gilt_fuer_bestehende_site_admin_email_benachrichtigungen(): void {
		$submission = $this->submission( 'verkauf@example.org', '{site_admin_email}' );

		$this->assertSame( 2, Notifier::send_all( $submission ) );
		$this->assertSame( array( 'verkauf@example.org' ), $GLOBALS['mlab_mails'][0]['to'] );
	}

	public function test_ohne_override_geht_es_an_den_standard(): void {
		$submission = $this->submission( '', '{site_admin_email}' );

		Notifier::send_all( $submission );

		$this->assertSame( array( 'admin@example.org' ), $GLOBALS['mlab_mails'][0]['to'] );
	}

	public function test_bestaetigung_an_den_besucher_bleibt_unveraendert(): void {
		Notifier::send_all( $this->submission( 'verkauf@example.org' ) );

		$this->assertSame( array( 'gast@example.org' ), $GLOBALS['mlab_mails'][1]['to'] );
	}

	public function test_gemischte_angabe_wird_nicht_ueberschrieben(): void {
		$submission = $this->submission( 'verkauf@example.org', '{site_admin_email}, {field:email}' );

		Notifier::send_all( $submission );

		$this->assertSame( array( 'admin@example.org', 'gast@example.org' ), $GLOBALS['mlab_mails'][0]['to'] );
	}

	public function test_ungueltige_doppelte_und_zu_viele_adressen(): void {
		$raw = "a@example.org; A@example.org\nkaputt, b@example.org,,";

		$this->assertSame( array( 'a@example.org', 'b@example.org' ), Recipients::valid( $raw ) );
		$this->assertSame( array( 'kaputt' ), Recipients::invalid( $raw ) );
		$this->assertSame( 'a@example.org, b@example.org', Recipients::clean( $raw ) );

		$many = implode( ',', array_map( static fn( int $i ) => "p{$i}@example.org", range( 1, 12 ) ) );
		$this->assertCount( Recipients::MAX, Recipients::valid( $many ) );
		$this->assertTrue( Recipients::too_many( $many ) );
		$this->assertFalse( Recipients::too_many( 'a@example.org, a@example.org' ) );
	}

	public function test_ungueltige_override_adressen_fallen_auf_den_standard_zurueck(): void {
		Notifier::send_all( $this->submission( 'kaputt', '{site_admin_email}' ) );

		$this->assertSame( array( 'admin@example.org' ), $GLOBALS['mlab_mails'][0]['to'] );
	}

	public function test_field_platzhalter_in_recipients_werden_nicht_aufgeloest(): void {
		foreach ( array( '{field:email}', '{field:email}, verkauf@example.org' ) as $value ) {
			$GLOBALS['mlab_mails'] = array();
			Notifier::send_all( $this->submission( $value, '{site_admin_email}' ) );

			$this->assertNotContains( 'gast@example.org', $GLOBALS['mlab_mails'][0]['to'] );
		}
		$this->assertSame( array(), Recipients::valid( '{field:email}' ) );
	}

	public function test_field_platzhalter_im_globalen_standard_wird_nicht_aufgeloest(): void {
		update_option( Settings::OPTION, array( 'default_recipient' => '{field:email}' ) );

		$this->assertSame( array( 'admin@example.org' ), Recipients::defaults() );
		$this->assertSame( 'info@example.org', Settings::sanitize( array( 'default_recipient' => '{field:email}, info@example.org' ) )['default_recipient'] );
	}

	public function test_header_injection_wird_verworfen(): void {
		$raw = "a@example.org\nBcc: x@example.org";

		$this->assertSame( array( 'a@example.org' ), Recipients::valid( $raw ) );
		$this->assertSame( array( 'Bcc: x@example.org' ), Recipients::invalid( $raw ) );

		Notifier::send_all( $this->submission( $raw, '{site_admin_email}' ) );
		$this->assertSame( array( 'a@example.org' ), $GLOBALS['mlab_mails'][0]['to'] );
		$this->assertStringNotContainsString( 'Bcc', implode( "\n", $GLOBALS['mlab_mails'][0]['headers'] ) );
	}

	public function test_schema_prueft_recipients(): void {
		$this->assertSame( array(), $this->errors( 'a@example.org, b@example.org' ) );
		$this->assertSame( array(), $this->errors( '' ) );
		$this->assertSame( array( 'settings.recipients' ), $this->errors( 'a@example.org, kaputt' ) );
		$this->assertSame( array( 'settings.recipients' ), $this->errors( implode( ',', array_map( static fn( int $i ) => "p{$i}@example.org", range( 1, 11 ) ) ) ) );
		$this->assertSame( array( 'settings.recipients' ), $this->errors( array( 'a@example.org' ) ) );
		$this->assertSame( array( 'settings.recipients' ), $this->errors( "a@example.org\nBcc: x@example.org" ) );
	}

	public function test_fehlermeldung_nennt_die_angabe(): void {
		$errors = SchemaValidator::validate( $this->schema( 'a@example.org, kaputt' ) );

		$this->assertSame( 'Diese Adresse sieht nicht richtig aus: kaputt', $errors[0]['message'] );
	}

	public function test_admin_mail_erscheint_in_der_standardsprache_auch_mit_override(): void {
		$schema                                     = $this->schema( 'verkauf@example.org' );
		$schema['languages']                        = array( 'de', 'fr' );
		$schema['settings']['notifications'][0]['i18n']['fr']['subject'] = 'Nouvelle demande';
		$schema['settings']['notifications'][0]['subject']               = 'Neue Anfrage';
		$form = new Form( 7, 'Kontakt', $schema );

		Notifier::send_all( new Submission( $form->localized( 'fr' ), array( 'name' => 'Anna', 'email' => 'gast@example.org' ) ) );

		$this->assertSame( 'Neue Anfrage', $GLOBALS['mlab_mails'][0]['subject'] );
	}

	/**
	 * @param mixed $recipients Wert für settings.recipients.
	 * @return list<string> Pfade der Fehler.
	 */
	private function errors( $recipients ): array {
		return array_column( SchemaValidator::validate( $this->schema( $recipients ) ), 'path' );
	}

	/**
	 * @param mixed  $recipients Wert für settings.recipients.
	 * @param string $to         «to» der ersten Benachrichtigung.
	 * @return array<string, mixed>
	 */
	private function schema( $recipients, string $to = '{default_recipient}' ): array {
		$settings = array(
			'notifications' => array(
				array(
					'id'      => 'admin',
					'to'      => $to,
					'subject' => 'Neue Anfrage',
					'body'    => '{all_fields}',
				),
				array(
					'id'      => 'guest',
					'to'      => '{field:email}',
					'subject' => 'Danke',
				),
			),
		);
		if ( '' !== $recipients ) {
			$settings['recipients'] = $recipients;
		}

		return array(
			'schema_version' => 1,
			'settings'       => $settings,
			'fields'         => array(
				array( 'id' => 'name', 'type' => 'text', 'label' => 'Name' ),
				array( 'id' => 'email', 'type' => 'email', 'label' => 'E-Mail' ),
			),
		);
	}

	private function submission( string $recipients, string $to = '{default_recipient}' ): Submission {
		$form = new Form( 7, 'Kontakt', $this->schema( $recipients, $to ) );

		return new Submission( $form, array( 'name' => 'Anna', 'email' => 'gast@example.org' ) );
	}
}
