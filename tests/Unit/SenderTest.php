<?php
/**
 * Tests für Absender und Antwort-Adresse.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Tests\Unit;

use MLab_Forms\Forms\Form;
use MLab_Forms\Mail\Notifier;
use MLab_Forms\Mail\Sender;
use MLab_Forms\Settings;
use MLab_Forms\Submission\Submission;
use PHPUnit\Framework\TestCase;

final class SenderTest extends TestCase {

	protected function setUp(): void {
		mlab_test_reset();
		update_option( 'admin_email', 'admin@example.org' );
		update_option( 'blogname', 'Meine Seite' );
	}

	public function test_ohne_angaben_gibt_es_keinen_from_header(): void {
		$this->assertSame( '', Sender::from_header( $this->form() ) );
		$this->assertSame( array( 'Content-Type: text/plain; charset=UTF-8' ), Notifier::headers( array(), $this->submission( $this->form(), array() ) ) );
	}

	public function test_nur_name_nutzt_die_wordpress_standard_adresse(): void {
		update_option( Settings::OPTION, array( 'email_from_name' => 'Praxis Muster' ) );

		$this->assertSame( 'From: Praxis Muster <wordpress@example.org>', Sender::from_header( $this->form() ) );
	}

	public function test_nur_adresse_nutzt_den_seitennamen(): void {
		update_option( Settings::OPTION, array( 'email_from_address' => 'info@example.org' ) );

		$this->assertSame( 'From: Meine Seite <info@example.org>', Sender::from_header( $this->form() ) );
	}

	public function test_fremde_domain_wird_erkannt(): void {
		$this->assertFalse( Sender::is_foreign_address( 'info@example.org' ) );
		$this->assertFalse( Sender::is_foreign_address( 'info@mail.example.org' ) );
		$this->assertFalse( Sender::is_foreign_address( 'info@WWW.Example.org' ) );
		$this->assertFalse( Sender::is_foreign_address( '' ) );
		$this->assertTrue( Sender::is_foreign_address( 'info@gmail.com' ) );
		$this->assertTrue( Sender::is_foreign_address( 'info@notexample.org' ) );
		$this->assertTrue( Sender::is_foreign_address( 'info@example.org.evil.com' ) );
	}

	public function test_einstellungen_ueberschreiben_den_standard(): void {
		update_option( Settings::OPTION, array( 'email_from_name' => 'Praxis Muster', 'email_from_address' => 'info@praxis.ch' ) );

		$this->assertSame( 'From: Praxis Muster <info@praxis.ch>', Sender::from_header( $this->form() ) );
	}

	public function test_formular_ueberschreibt_einstellungen(): void {
		update_option( Settings::OPTION, array( 'email_from_name' => 'Praxis Muster', 'email_from_address' => 'info@praxis.ch' ) );
		$form = $this->form( array( 'sender' => array( 'name' => 'Kurse', 'email' => 'kurse@praxis.ch' ) ) );

		$this->assertSame( 'From: Kurse <kurse@praxis.ch>', Sender::from_header( $form ) );
	}

	public function test_zeilenumbruch_im_namen_schleust_keinen_header_ein(): void {
		$form   = $this->form( array( 'sender' => array( 'name' => "Anna\r\nBcc: evil@example.org" ) ) );
		$header = Sender::from_header( $form );

		$this->assertStringNotContainsString( "\n", $header );
		$this->assertStringNotContainsString( "\r", $header );
		$this->assertSame( 1, preg_match( '/^From: Anna Bcc: evil@example\.org <wordpress@example\.org>$/', $header ) );
	}

	public function test_ungueltige_absenderadresse_faellt_zurueck(): void {
		$form = $this->form( array( 'sender' => array( 'email' => "x@example.org\r\nBcc: evil@example.org" ) ) );

		$this->assertSame( '', Sender::from_header( $form ) );
		$this->assertSame( '', Sender::clean_email( "a@example.org\nBcc: b@example.org" ) );
		$this->assertSame( '', Sender::clean_email( array( 'a@example.org' ) ) );
	}

	public function test_spitze_klammern_und_anfuehrungszeichen_im_namen_fallen_weg(): void {
		$form = $this->form( array( 'sender' => array( 'name' => 'A <b@c.d> "X"' ) ) );

		$this->assertSame( 'From: A b@c.d X <wordpress@example.org>', Sender::from_header( $form ) );
	}

	public function test_antwort_an_standard_ist_die_mail_des_besuchers(): void {
		$submission = $this->submission( $this->form(), array( 'email' => 'gast@example.org' ) );

		$this->assertSame( 'gast@example.org', Sender::reply_to( $submission->form, $submission ) );
	}

	public function test_antwort_an_feste_adresse(): void {
		update_option( Settings::OPTION, array( 'email_reply_to_mode' => 'fixed', 'email_reply_to' => 'team@example.org' ) );
		$submission = $this->submission( $this->form(), array( 'email' => 'gast@example.org' ) );

		$this->assertSame( 'team@example.org', Sender::reply_to( $submission->form, $submission ) );
	}

	public function test_formular_waehlt_den_modus(): void {
		update_option( Settings::OPTION, array( 'email_reply_to_mode' => 'fixed', 'email_reply_to' => 'team@example.org' ) );
		$form       = $this->form( array( 'sender' => array( 'reply_to_mode' => 'visitor' ) ) );
		$submission = $this->submission( $form, array( 'email' => 'gast@example.org' ) );

		$this->assertSame( 'gast@example.org', Sender::reply_to( $form, $submission ) );
	}

	public function test_ungueltige_besucher_mail_gibt_keine_antwort_adresse(): void {
		$submission = $this->submission( $this->form(), array( 'email' => "gast@example.org\r\nBcc: x@example.org" ) );

		$this->assertSame( '', Sender::reply_to( $submission->form, $submission ) );
	}

	public function test_header_der_benachrichtigung(): void {
		$submission = $this->submission( $this->form(), array( 'email' => 'gast@example.org' ) );

		update_option( Settings::OPTION, array( 'email_from_address' => 'info@example.org' ) );

		$this->assertSame(
			array( 'Content-Type: text/plain; charset=UTF-8', 'From: Meine Seite <info@example.org>', 'Reply-To: gast@example.org' ),
			Notifier::headers( array(), $submission )
		);
	}

	public function test_reply_to_der_benachrichtigung_hat_vorrang(): void {
		$submission = $this->submission( $this->form(), array( 'email' => 'gast@example.org' ) );
		$headers    = Notifier::headers( array( 'reply_to' => 'chef@example.org' ), $submission );

		$this->assertContains( 'Reply-To: chef@example.org', $headers );
		$this->assertNotContains( 'Reply-To: gast@example.org', $headers );
	}

	/**
	 * @param array<string, mixed> $settings Formular-Einstellungen.
	 */
	private function form( array $settings = array() ): Form {
		return new Form(
			3,
			'Kontakt',
			array(
				'schema_version' => 1,
				'settings'       => $settings,
				'fields'         => array(
					array( 'id' => 'name', 'type' => 'text', 'label' => 'Name' ),
					array( 'id' => 'email', 'type' => 'email', 'label' => 'E-Mail' ),
				),
			)
		);
	}

	/**
	 * @param Form                 $form   Formular.
	 * @param array<string, mixed> $values Werte.
	 */
	private function submission( Form $form, array $values ): Submission {
		return new Submission( $form, $values );
	}
}
