<?php
/**
 * Tests für Formular-Starts und den WhatsApp-Link.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Tests\Unit;

use MLab_Forms\Entries\Starts;
use MLab_Forms\Frontend\WhatsApp;
use PHPUnit\Framework\TestCase;

/**
 * Starts::prune und WhatsApp::digits.
 */
final class StartsTest extends TestCase {

	public function test_prune_keeps_recent_days_and_drops_empty_forms(): void {
		$data = array(
			5 => array(
				'2025-01-01' => 3,
				'2026-10-20' => 2,
			),
			7 => array( '2024-12-31' => 1 ),
		);

		$this->assertSame( array( 5 => array( '2026-10-20' => 2 ) ), Starts::prune( $data, '2026-10-22' ) );
	}

	public function test_whatsapp_digits(): void {
		$this->assertSame( '41765710532', WhatsApp::digits( '+41 76 571 05 32' ) );
		$this->assertSame( '41765710532', WhatsApp::digits( '0041 76 571 05 32' ) );
		$this->assertSame( '', WhatsApp::digits( 'keine' ) );
	}
}
