<?php
/**
 * Tests für die Aufbewahrungsdauer.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Tests\Unit;

use MLab_Forms\Admin\RetentionBox;
use MLab_Forms\Entries\EntryRepository;
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

	public function test_null_heisst_unbegrenzt(): void {
		$this->schema( 1, array( 'settings' => array( 'retention_days' => 0 ) ) );

		$this->assertSame( 0, Retention::retention_days( 1 ) );
	}

	public function test_negative_werte_werden_zu_null(): void {
		$this->schema( 1, array( 'settings' => array( 'retention_days' => -5 ) ) );

		$this->assertSame( 0, Retention::retention_days( 1 ) );
	}

	public function test_unbegrenzt_markiert_nichts_als_geloescht(): void {
		// Ohne Datenbank: der Aufruf darf gar nicht erst abfragen.
		$this->assertSame( 0, EntryRepository::soft_delete_older_than( 1, 0 ) );
	}

	public function test_auswahl_schreibt_tage_ins_schema(): void {
		$schema = array( 'settings' => array( 'submit_label' => 'Los' ) );
		$data   = array( 'mlab_form_retention' => '90', 'mlab_form_retention_initial' => '365' );

		$this->assertSame( 90, RetentionBox::merge( $schema, $data )['settings']['retention_days'] );
		$this->assertSame( 'Los', RetentionBox::merge( $schema, $data )['settings']['submit_label'] );
		$this->assertSame( 0, RetentionBox::merge( $schema, array( 'mlab_form_retention' => '0', 'mlab_form_retention_initial' => '365' ) )['settings']['retention_days'] );
	}

	public function test_eigene_anzahl_tage(): void {
		$data = array( 'mlab_form_retention' => 'custom', 'mlab_form_retention_custom' => '45', 'mlab_form_retention_initial' => '365' );

		$this->assertSame( 45, RetentionBox::days_from_request( $data ) );
		$this->assertSame( 3650, RetentionBox::days_from_request( array( 'mlab_form_retention_custom' => '99999' ) + $data ) );
		$this->assertNull( RetentionBox::days_from_request( array( 'mlab_form_retention_custom' => 'viele' ) + $data ) );
	}

	public function test_unveraenderte_auswahl_laesst_das_json_gelten(): void {
		$schema = array( 'settings' => array( 'retention_days' => 14 ) );
		$data   = array( 'mlab_form_retention' => '365', 'mlab_form_retention_initial' => '365' );

		$this->assertSame( $schema, RetentionBox::merge( $schema, $data ) );
		$this->assertSame( $schema, RetentionBox::merge( $schema, array( 'mlab_form_retention' => 'unsinn' ) ) );
	}

	/**
	 * @param int                  $id     Formular.
	 * @param array<string, mixed> $schema Schema.
	 */
	private function schema( int $id, array $schema ): void {
		$GLOBALS['mlab_test']['post_meta'][ $id ][ FormRepository::META_SCHEMA ] = json_encode( $schema );
	}
}
