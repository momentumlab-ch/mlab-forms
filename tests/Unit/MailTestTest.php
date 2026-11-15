<?php
/**
 * Tests des geschützten Testversands.
 *
 * @package MLab_Forms
 */
namespace MLab_Forms\Tests\Unit;

use MLab_Forms\Admin\MailTest;
use MLab_Forms\Settings;
use PHPUnit\Framework\TestCase;

final class MailTestTest extends TestCase {
	protected function setUp(): void {
		mlab_test_reset();
		$GLOBALS['mlab_mails'] = array();
		unset( $GLOBALS['mlab_mail_result'] );
		$_POST = array( 'nonce' => 'valid', 'to' => 'attacker@example.org' );
		update_option( 'admin_email', 'admin@example.org' );
		update_option( Settings::OPTION, array( 'default_recipient' => 'office@example.org' ) );
	}
	protected function tearDown(): void {
		$_POST = array();
		unset( $GLOBALS['mlab_mail_result'] );
	}
	private function run_test(): array {
		try {
			( new MailTest() )->handle();
		} catch ( \RuntimeException $response ) {
			return (array) json_decode( $response->getMessage(), true );
		}
		$this->fail( 'Kein JSON-Ergebnis.' );
	}
	public function test_verwendet_nur_gespeicherte_empfaenger(): void {
		$this->assertTrue( $this->run_test()['data']['ok'] );
		$this->assertSame( array( 'office@example.org' ), $GLOBALS['mlab_mails'][0]['to'] );
	}
	public function test_ohne_berechtigung_kein_versand(): void {
		$GLOBALS['mlab_test']['allowed'] = false;
		$this->assertSame( 403, $this->run_test()['status'] );
		$this->assertSame( array(), $GLOBALS['mlab_mails'] );
	}
	public function test_nonce_wird_vor_versand_geprueft(): void {
		$_POST['nonce'] = 'invalid';
		$this->expectExceptionMessage( 'invalid_nonce' );
		( new MailTest() )->handle();
	}
	public function test_begrenzt_wiederholte_tests(): void {
		$this->run_test();
		$this->assertSame( 429, $this->run_test()['status'] );
		$this->assertCount( 1, $GLOBALS['mlab_mails'] );
	}
	public function test_mailfehler_wird_angezeigt(): void {
		$GLOBALS['mlab_mail_result'] = false;
		$this->assertFalse( $this->run_test()['data']['ok'] );
	}
	public function test_ohne_empfaenger_kein_versand(): void {
		update_option( 'admin_email', '' );
		update_option( Settings::OPTION, array() );
		$this->assertSame( 400, $this->run_test()['status'] );
		$this->assertSame( array(), $GLOBALS['mlab_mails'] );
	}
}
