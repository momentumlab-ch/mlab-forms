<?php
/**
 * Tests für Bereinigung und Prüfung der Feldwerte.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Tests\Unit;

use MLab_Forms\Fields\FieldType;
use MLab_Forms\Fields\FieldTypes;
use PHPUnit\Framework\TestCase;

final class FieldTypesTest extends TestCase {

	public function test_pflichtfeld(): void {
		$field = array( 'id' => 'name', 'type' => 'text', 'required' => true );

		$this->assertNotNull( $this->check( $field, '   ' ) );
		$this->assertNull( $this->check( $field, 'Anna' ) );
		$this->assertNull( $this->check( array( 'required' => false ) + $field, '' ) );
	}

	public function test_text_wird_bereinigt(): void {
		$this->assertSame( 'Hallo Welt', $this->type( 'text' )->sanitize( " <b>Hallo</b>\n Welt ", array() ) );
		$this->assertSame( '', $this->type( 'text' )->sanitize( array( 'x' ), array() ) );
	}

	public function test_email(): void {
		$field = array( 'id' => 'email', 'type' => 'email' );

		$this->assertNotNull( $this->check( $field, 'keine-adresse' ) );
		$this->assertNull( $this->check( $field, 'anna@example.org' ) );
	}

	public function test_muster(): void {
		$field = array( 'id' => 'plz', 'type' => 'text', 'pattern' => '[0-9]{4}' );

		$this->assertNull( $this->check( $field, '3800' ) );
		$this->assertNotNull( $this->check( $field, '38000' ) );
	}

	public function test_zahl_mit_komma_und_bereich(): void {
		$field = array( 'id' => 'personen', 'type' => 'number', 'min' => 1, 'max' => 8 );

		$this->assertSame( '2.5', $this->type( 'number' )->sanitize( '2,5', $field ) );
		$this->assertSame( '', $this->type( 'number' )->sanitize( 'viele', $field ) );
		$this->assertNotNull( $this->check( $field, '0' ) );
		$this->assertNotNull( $this->check( $field, '9' ) );
		$this->assertNull( $this->check( $field, '8' ) );
	}

	public function test_datum(): void {
		$field = array( 'id' => 'datum', 'type' => 'date', 'min' => '2026-01-01' );

		$this->assertSame( '', $this->type( 'date' )->sanitize( '31.12.2026', $field ) );
		$this->assertSame( '', $this->type( 'date' )->sanitize( '2026-02-30', $field ) );
		$this->assertNotNull( $this->check( $field, '2025-12-31' ) );
		$this->assertNull( $this->check( $field, '2026-06-01' ) );
	}

	public function test_auswahl_nur_aus_optionen(): void {
		$field = array(
			'id'      => 'angebot',
			'type'    => 'select',
			'choices' => array(
				array( 'value' => 'a', 'label' => 'A', 'group' => '' ),
				array( 'value' => 'b', 'label' => 'B', 'group' => '' ),
			),
		);

		$this->assertNull( $this->check( $field, 'a' ) );
		$this->assertNotNull( $this->check( $field, 'z' ) );
	}

	public function test_checkbox_gruppe_liefert_liste(): void {
		$this->assertSame( array( 'a', 'b' ), $this->type( 'checkbox_group' )->sanitize( array( 'a', '', 'b', array( 'x' ) ), array() ) );
	}

	public function test_inhaltsfeld_ist_keine_eingabe(): void {
		$this->assertFalse( $this->type( 'html' )->is_input() );
		$this->assertTrue( $this->type( 'consent' )->is_input() );
	}

	/**
	 * @param array<string, mixed> $field Feld.
	 * @param mixed                $raw   Rohwert.
	 */
	private function check( array $field, $raw ): ?string {
		$type = $this->type( (string) $field['type'] );

		return $type->validate( $type->sanitize( $raw, $field ), $field );
	}

	private function type( string $name ): FieldType {
		$type = FieldTypes::get( $name );
		$this->assertNotNull( $type, $name );

		return $type;
	}
}
