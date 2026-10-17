<?php
/**
 * Tests für Captcha-Dienste und Akismet.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Tests\Unit;

use MLab_Forms\Admin\ConnectionTest;
use MLab_Forms\Forms\Form;
use MLab_Forms\Privacy\Privacy;
use MLab_Forms\Settings;
use MLab_Forms\Spam\Services;
use MLab_Forms\Spam\Usage;
use MLab_Forms\Spam\Widget;
use MLab_Forms\Submission\SpamGuard;
use MLab_Forms\Tests\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class SpamServicesTest extends TestCase {

	protected function setUp(): void {
		mlab_test_reset();
		update_option(
			Settings::OPTION,
			array(
				'spam_turnstile_site'   => 'ts-site',
				'spam_turnstile_secret' => 'ts-secret',
				'spam_recaptcha_site'   => 'rc-site',
				'spam_recaptcha_secret' => 'rc-secret',
				'spam_hcaptcha_site'    => 'hc-site',
				'spam_hcaptcha_secret'  => 'hc-secret',
				'spam_akismet_key'      => 'abcdef123456',
			)
		);
	}

	protected function tearDown(): void {
		Services::set_client( null );
	}

	public function test_standard_ist_kein_dienst(): void {
		$this->assertSame( '', SpamGuard::provider( $this->form( array() ) ) );
	}

	public function test_formular_waehlt_genau_einen_anbieter(): void {
		$this->assertSame( 'turnstile', SpamGuard::provider( $this->form( array( 'provider' => 'turnstile' ) ) ) );
		$this->assertSame( 'recaptcha', SpamGuard::provider( $this->form( array( 'provider' => 'recaptcha' ) ) ) );
		$this->assertSame( 'hcaptcha', SpamGuard::provider( $this->form( array( 'provider' => 'hcaptcha' ) ) ) );
	}

	public function test_mehrere_oder_unbekannte_anbieter_werden_nicht_akzeptiert(): void {
		foreach ( array( 'turnstile,recaptcha', array( 'turnstile', 'recaptcha' ), true, 'recaptcha ', 'Turnstile', 'akismet' ) as $provider ) {
			$this->assertSame( '', SpamGuard::provider( $this->form( array( 'provider' => $provider ) ) ), print_r( $provider, true ) );
		}
	}

	public function test_ohne_schluessel_ist_der_anbieter_nicht_aktiv(): void {
		update_option( Settings::OPTION, array( 'spam_turnstile_site' => 'nur-site' ) );

		$this->assertSame( '', SpamGuard::provider( $this->form( array( 'provider' => 'turnstile' ) ) ) );
	}

	public function test_globaler_standard_und_ausschalten_im_formular(): void {
		update_option( Settings::OPTION, array( 'spam_provider' => 'hcaptcha', 'spam_hcaptcha_site' => 'a', 'spam_hcaptcha_secret' => 'b' ) );

		$this->assertSame( 'hcaptcha', SpamGuard::provider( $this->form( array() ) ) );
		$this->assertSame( '', SpamGuard::provider( $this->form( array( 'provider' => '' ) ) ) );
	}

	public function test_gueltiges_token_laesst_durch(): void {
		$client = FakeHttpClient::json( array( 'success' => true ) );
		Services::set_client( $client );

		$verdict = SpamGuard::check_services( $this->form( array( 'provider' => 'turnstile' ) ), array( 'cf-turnstile-response' => 'tok' ), array() );

		$this->assertSame( SpamGuard::OK, $verdict );
		$this->assertSame( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', $client->calls[0]['url'] );
		$this->assertSame( array( 'secret' => 'ts-secret', 'response' => 'tok' ), $client->calls[0]['body'] );
	}

	public function test_ungueltiges_oder_fehlendes_token_blockiert(): void {
		Services::set_client( FakeHttpClient::json( array( 'success' => false, 'error-codes' => array( 'invalid-input-response' ) ) ) );
		$form = $this->form( array( 'provider' => 'hcaptcha' ) );

		$this->assertSame( SpamGuard::BLOCKED, SpamGuard::check_services( $form, array( 'h-captcha-response' => 'x' ), array() ) );

		$client = FakeHttpClient::json( array( 'success' => true ) );
		Services::set_client( $client );
		$this->assertSame( SpamGuard::BLOCKED, SpamGuard::check_services( $form, array(), array() ) );
		$this->assertSame( array(), $client->calls, 'Ohne Token wird der Dienst nicht gefragt.' );
	}

	public function test_dienst_nicht_erreichbar_laesst_nie_durch(): void {
		$form = $this->form( array( 'provider' => 'turnstile' ) );
		$req  = array( 'cf-turnstile-response' => 'tok' );

		Services::set_client( new FakeHttpClient( null ) );
		$this->assertSame( SpamGuard::UNAVAILABLE, SpamGuard::check_services( $form, $req, array() ) );

		Services::set_client( new FakeHttpClient( array( 'status' => 502, 'body' => 'Bad Gateway' ) ) );
		$this->assertSame( SpamGuard::UNAVAILABLE, SpamGuard::check_services( $form, $req, array() ) );

		Services::set_client( new FakeHttpClient( array( 'status' => 200, 'body' => 'kein json' ) ) );
		$this->assertSame( SpamGuard::UNAVAILABLE, SpamGuard::check_services( $form, $req, array() ) );
	}

	public function test_abgelehnter_geheimer_schluessel_ist_kein_besucherfehler(): void {
		Services::set_client( FakeHttpClient::json( array( 'success' => false, 'error-codes' => array( 'invalid-input-secret' ) ) ) );

		$verdict = SpamGuard::check_services( $this->form( array( 'provider' => 'turnstile' ) ), array( 'cf-turnstile-response' => 'tok' ), array() );

		$this->assertSame( SpamGuard::UNAVAILABLE, $verdict );
	}

	public function test_recaptcha_score_schwelle(): void {
		$form = $this->form( array( 'provider' => 'recaptcha' ) );
		$req  = array( 'g-recaptcha-response' => 'tok' );

		Services::set_client( FakeHttpClient::json( array( 'success' => true, 'score' => 0.9, 'action' => 'submit' ) ) );
		$this->assertSame( SpamGuard::OK, SpamGuard::check_services( $form, $req, array() ) );

		Services::set_client( FakeHttpClient::json( array( 'success' => true, 'score' => 0.3, 'action' => 'submit' ) ) );
		$this->assertSame( SpamGuard::BLOCKED, SpamGuard::check_services( $form, $req, array() ) );

		Services::set_client( FakeHttpClient::json( array( 'success' => true, 'action' => 'submit' ) ) );
		$this->assertSame( SpamGuard::BLOCKED, SpamGuard::check_services( $form, $req, array() ), 'Ohne Score gilt die Anfrage nicht als geprüft.' );

		Services::set_client( FakeHttpClient::json( array( 'success' => true, 'score' => 0.9, 'action' => 'login' ) ) );
		$this->assertSame( SpamGuard::BLOCKED, SpamGuard::check_services( $form, $req, array() ) );
	}

	public function test_akismet_urteile(): void {
		$form = $this->form( array( 'akismet' => true ) );

		Services::set_client( new FakeHttpClient( array( 'status' => 200, 'body' => 'true' ) ) );
		$this->assertSame( SpamGuard::BLOCKED, SpamGuard::check_services( $form, array(), array( 'name' => 'A' ) ) );

		$client = new FakeHttpClient( array( 'status' => 200, 'body' => 'false' ) );
		Services::set_client( $client );
		$this->assertSame( SpamGuard::OK, SpamGuard::check_services( $form, array(), array( 'name' => 'Anna', 'email' => 'a@example.org', 'nachricht' => 'Hallo' ) ) );
		$this->assertSame( 'https://abcdef123456.rest.akismet.com/1.1/comment-check', $client->calls[0]['url'] );
		$this->assertSame( 'a@example.org', $client->calls[0]['body']['comment_author_email'] );
		$this->assertSame( 'Anna', $client->calls[0]['body']['comment_author'] );
		$this->assertStringContainsString( 'Nachricht: Hallo', $client->calls[0]['body']['comment_content'] );

		Services::set_client( new FakeHttpClient( array( 'status' => 200, 'body' => 'invalid' ) ) );
		$this->assertSame( SpamGuard::UNAVAILABLE, SpamGuard::check_services( $form, array(), array() ) );

		Services::set_client( new FakeHttpClient( null ) );
		$this->assertSame( SpamGuard::UNAVAILABLE, SpamGuard::check_services( $form, array(), array() ) );
	}

	public function test_akismet_nur_wenn_eingeschaltet(): void {
		$client = new FakeHttpClient( array( 'status' => 200, 'body' => 'true' ) );
		Services::set_client( $client );

		$this->assertSame( SpamGuard::OK, SpamGuard::check_services( $this->form( array() ), array(), array() ) );
		$this->assertSame( array(), $client->calls );
	}

	public function test_akismet_schluessel_wird_nicht_in_den_host_eingeschleust(): void {
		update_option( Settings::OPTION, array( 'spam_akismet_key' => 'evil.example/x' ) );
		$client = new FakeHttpClient( array( 'status' => 200, 'body' => 'false' ) );
		Services::set_client( $client );

		$this->assertSame( SpamGuard::UNAVAILABLE, SpamGuard::check_services( $this->form( array( 'akismet' => true ) ), array(), array() ) );
		$this->assertSame( array(), $client->calls );
	}

	public function test_verbindung_testen(): void {
		Services::set_client( FakeHttpClient::json( array( 'success' => false, 'error-codes' => array( 'invalid-input-response' ) ) ) );
		$this->assertTrue( ConnectionTest::run( 'turnstile' )['ok'] );

		Services::set_client( FakeHttpClient::json( array( 'success' => false, 'error-codes' => array( 'invalid-input-secret' ) ) ) );
		$this->assertFalse( ConnectionTest::run( 'recaptcha' )['ok'] );

		Services::set_client( new FakeHttpClient( null ) );
		$this->assertFalse( ConnectionTest::run( 'hcaptcha' )['ok'] );

		Services::set_client( new FakeHttpClient( array( 'status' => 200, 'body' => 'valid' ) ) );
		$this->assertTrue( ConnectionTest::run( 'akismet' )['ok'] );

		Services::set_client( new FakeHttpClient( array( 'status' => 200, 'body' => 'invalid' ) ) );
		$this->assertFalse( ConnectionTest::run( 'akismet' )['ok'] );

		$this->assertFalse( ConnectionTest::run( 'unbekannt' )['ok'] );
	}

	public function test_verbindung_testen_ohne_schluessel_ruft_den_dienst_nicht(): void {
		update_option( Settings::OPTION, array() );
		$client = FakeHttpClient::json( array( 'success' => true ) );
		Services::set_client( $client );

		$this->assertFalse( ConnectionTest::run( 'turnstile' )['ok'] );
		$this->assertFalse( ConnectionTest::run( 'akismet' )['ok'] );
		$this->assertSame( array(), $client->calls );
	}

	public function test_geheime_schluessel_bleiben_bei_leerem_feld_erhalten(): void {
		$saved = Settings::sanitize( array( Settings::TAB_KEY => 'spam', 'spam_turnstile_secret' => '', 'spam_hcaptcha_secret' => ' neu ' ) );

		$this->assertSame( 'ts-secret', $saved['spam_turnstile_secret'] );
		$this->assertSame( 'neu', $saved['spam_hcaptcha_secret'] );
	}

	public function test_maskierung_zeigt_hoechstens_die_letzten_vier_zeichen(): void {
		$this->assertSame( '', Settings::mask( '' ) );
		$this->assertSame( '••••••••', Settings::mask( 'kurz' ) );
		$this->assertSame( '••••••••wxyz', Settings::mask( 'abcdefghijklmnopwxyz' ) );
		$this->assertStringNotContainsString( 'abcdefghijkl', Settings::mask( 'abcdefghijklmnopwxyz' ) );
	}

	public function test_widget_pro_anbieter(): void {
		$this->assertSame( '', Widget::html( $this->form( array() ) ) );
		$this->assertSame( array( 'data-captcha' => null, 'data-captcha-key' => null ), Widget::form_attributes( $this->form( array() ) ) );

		$turnstile = Widget::html( $this->form( array( 'provider' => 'turnstile' ) ) );
		$this->assertStringContainsString( 'class="cf-turnstile" data-sitekey="ts-site"', $turnstile );
		$this->assertStringNotContainsString( 'ts-secret', $turnstile );

		$this->assertStringContainsString( 'class="h-captcha" data-sitekey="hc-site"', Widget::html( $this->form( array( 'provider' => 'hcaptcha' ) ) ) );

		$recaptcha = $this->form( array( 'provider' => 'recaptcha' ) );
		$this->assertStringContainsString( 'name="g-recaptcha-response"', Widget::html( $recaptcha ) );
		$this->assertSame( array( 'data-captcha' => 'recaptcha', 'data-captcha-key' => 'rc-site' ), Widget::form_attributes( $recaptcha ) );
		$this->assertStringNotContainsString( 'rc-secret', Widget::html( $recaptcha ) );
	}

	public function test_skript_adressen(): void {
		$this->assertSame( 'https://www.google.com/recaptcha/api.js?render=rc-site', Services::script_url( 'recaptcha' ) );
		$this->assertSame( 'https://challenges.cloudflare.com/turnstile/v0/api.js', Services::script_url( 'turnstile' ) );
	}

	public function test_datenschutztext_nennt_nur_aktive_dienste(): void {
		$this->assertSame( '', Privacy::services_text( array() ) );

		$text = Privacy::services_text( array( 'recaptcha', 'akismet' ) );
		$this->assertStringContainsString( 'Google reCAPTCHA', $text );
		$this->assertStringContainsString( 'Akismet', $text );
		$this->assertStringNotContainsString( 'Turnstile', $text );
		$this->assertStringNotContainsString( 'hCaptcha', $text );
	}

	public function test_nutzung_aus_formular_und_einstellungen(): void {
		$this->assertSame( array(), Usage::services_of( array() ) );
		$this->assertSame( array( 'turnstile', 'akismet' ), Usage::services_of( array( 'provider' => 'turnstile', 'akismet' => true ) ) );
		$this->assertSame( array(), Usage::services_of( array( 'provider' => 'a,b' ) ) );
	}

	/**
	 * @param array<string, mixed> $spam Spam-Einstellungen des Formulars.
	 */
	private function form( array $spam ): Form {
		return new Form(
			9,
			'Test',
			array(
				'schema_version' => 1,
				'settings'       => array( 'spam' => $spam ),
				'fields'         => array(
					array( 'id' => 'name', 'type' => 'text', 'label' => 'Name' ),
					array( 'id' => 'email', 'type' => 'email', 'label' => 'E-Mail' ),
					array( 'id' => 'nachricht', 'type' => 'textarea', 'label' => 'Nachricht' ),
				),
			)
		);
	}
}
