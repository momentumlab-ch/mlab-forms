<?php
/**
 * Tests für den Spamschutz.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Tests\Unit;

use MLab_Forms\Forms\Form;
use MLab_Forms\Settings;
use MLab_Forms\Submission\SpamGuard;
use PHPUnit\Framework\TestCase;

final class SpamGuardTest extends TestCase {

	private const IP = 'abc123';

	protected function setUp(): void {
		mlab_test_reset();
	}

	public function test_gueltiges_token_nach_mindestzeit(): void {
		$token = SpamGuard::token( 7, time() - 10 );

		$this->assertTrue( SpamGuard::token_ok( 7, $token, 3 ) );
	}

	public function test_zu_schnell_abgeschickt(): void {
		$this->assertFalse( SpamGuard::token_ok( 7, SpamGuard::token( 7 ), 3 ) );
	}

	public function test_token_eines_anderen_formulars_oder_manipuliert(): void {
		$token = SpamGuard::token( 7, time() - 10 );

		$this->assertFalse( SpamGuard::token_ok( 8, $token, 0 ) );
		$this->assertFalse( SpamGuard::token_ok( 7, ( time() - 100 ) . substr( $token, strpos( $token, ':' ) ), 0 ) );
		$this->assertFalse( SpamGuard::token_ok( 7, '', 0 ) );
		$this->assertFalse( SpamGuard::token_ok( 7, 'abc:def', 0 ) );
	}

	public function test_zu_altes_token_ist_ungueltig(): void {
		$this->assertTrue( SpamGuard::token_ok( 7, SpamGuard::token( 7, time() - WEEK_IN_SECONDS + 60 ), 3 ) );
		$this->assertFalse( SpamGuard::token_ok( 7, SpamGuard::token( 7, time() - WEEK_IN_SECONDS - 60 ), 3 ) );
	}

	public function test_zu_altes_token_gilt_in_check_als_spam(): void {
		$request = array( SpamGuard::TOKEN_FIELD => SpamGuard::token( 7, time() - 3 * WEEK_IN_SECONDS ) );

		$this->assertSame( SpamGuard::SPAM, SpamGuard::check( $this->form(), $request, self::IP ) );
	}

	public function test_honeypot(): void {
		$form = $this->form();

		$this->assertSame( SpamGuard::SPAM, SpamGuard::check( $form, $this->request( array( SpamGuard::HONEYPOT_FIELD => 'http://spam' ) ), self::IP ) );
		$this->assertSame( SpamGuard::OK, SpamGuard::check( $form, $this->request(), self::IP ) );
	}

	public function test_honeypot_abschaltbar(): void {
		$form = $this->form( array( 'honeypot' => false ) );

		$this->assertSame( SpamGuard::OK, SpamGuard::check( $form, $this->request( array( SpamGuard::HONEYPOT_FIELD => 'x' ) ), self::IP ) );
	}

	public function test_rate_limit(): void {
		update_option( Settings::OPTION, array( 'spam_rate_limit' => 2 ) );
		$form = $this->form();

		SpamGuard::count( self::IP );
		$this->assertSame( SpamGuard::OK, SpamGuard::check( $form, $this->request(), self::IP ) );

		SpamGuard::count( self::IP );
		$this->assertSame( SpamGuard::RATE_LIMITED, SpamGuard::check( $form, $this->request(), self::IP ) );
		$this->assertSame( SpamGuard::OK, SpamGuard::check( $form, $this->request(), 'andere-ip' ) );
	}

	public function test_rate_limit_abgeschaltet(): void {
		update_option( Settings::OPTION, array( 'spam_rate_limit' => 0 ) );

		SpamGuard::count( self::IP );
		$this->assertSame( array(), $GLOBALS['mlab_test']['transients'] );
	}

	/**
	 * @param array<string, mixed> $spam Spam-Einstellungen.
	 */
	private function form( array $spam = array() ): Form {
		return new Form(
			7,
			'Test',
			array(
				'schema_version' => 1,
				'settings'       => array( 'spam' => $spam + array( 'min_seconds' => 3 ) ),
				'fields'         => array( array( 'id' => 'name', 'type' => 'text', 'label' => 'Name' ) ),
			)
		);
	}

	/**
	 * @param array<string, string> $extra Zusätzliche Werte.
	 * @return array<string, string>
	 */
	private function request( array $extra = array() ): array {
		return $extra + array( SpamGuard::TOKEN_FIELD => SpamGuard::token( 7, time() - 10 ) );
	}
}
