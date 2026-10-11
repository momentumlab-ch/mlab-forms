<?php
/**
 * Tests für die Aufbewahrungsdauer.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Tests\Unit;

use MLab_Forms\Entries\Retention;
use MLab_Forms\Forms\FormRepository;
use MLab_Forms\Settings;
use PHPUnit\Framework\TestCase;

final class RetentionTest extends TestCase {

	protected function setUp(): void {
		mlab_test_reset();
	}

	public function test_standard_aus_den_einstellungen(): void {
		$this->assertSame( 365, Retention::retention_days( 1 ) );

		update_option( Settings::OPTION, array( 'retention_days' => 90 ) );
		$this->assertSame( 90, Retention::retention_days( 1 ) );
	}

	public function test_formular_ueberschreibt_einstellung(): void {
		update_option( Settings::OPTION, array( 'retention_days' => 90 ) );
		$this->schema( 1, array( 'settings' => array( 'retention_days' => 30 ) ) );

		$this->assertSame( 30, Retention::retention_days( 1 ) );
	}

	public function test_mindestens_ein_tag(): void {
		$this->schema( 1, array( 'settings' => array( 'retention_days' => 0 ) ) );

		$this->assertSame( 1, Retention::retention_days( 1 ) );
	}

	/**
	 * @param int                  $id     Formular.
	 * @param array<string, mixed> $schema Schema.
	 */
	private function schema( int $id, array $schema ): void {
		$GLOBALS['mlab_test']['post_meta'][ $id ][ FormRepository::META_SCHEMA ] = json_encode( $schema );
	}
}
