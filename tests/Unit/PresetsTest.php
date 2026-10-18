<?php
/**
 * Tests für die Bausteine des Builders.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Tests\Unit;

use MLab_Forms\Forms\Presets;
use MLab_Forms\Forms\SchemaValidator;
use PHPUnit\Framework\TestCase;

final class PresetsTest extends TestCase {

	protected function setUp(): void {
		mlab_test_reset();
	}

	public function test_jeder_baustein_ergibt_ein_gueltiges_feld(): void {
		foreach ( Presets::all() as $preset ) {
			$field  = array( 'id' => 'baustein' ) + $preset['field'];
			$schema = array(
				'schema_version' => 1,
				'fields'         => array( $field ),
			);

			$this->assertSame( array(), SchemaValidator::validate( $schema ), $preset['key'] );
		}
	}

	public function test_schluessel_sind_eindeutig_und_namen_gefuellt(): void {
		$keys = array_column( Presets::all(), 'key' );

		$this->assertSame( $keys, array_values( array_unique( $keys ) ) );
		foreach ( Presets::all() as $preset ) {
			$this->assertNotSame( '', $preset['name'], $preset['key'] );
			$this->assertNotSame( '', $preset['hint'], $preset['key'] );
		}
	}

	public function test_die_geforderten_bausteine_sind_da(): void {
		$keys = array_column( Presets::all(), 'key' );

		foreach ( array( 'first_name', 'last_name', 'email', 'phone', 'company', 'address', 'postcode', 'city', 'country', 'message', 'date', 'select', 'multiselect', 'consent', 'heading' ) as $key ) {
			$this->assertContains( $key, $keys );
		}
	}

	public function test_startformular_nutzt_vorhandene_bausteine(): void {
		$this->assertSame( array(), array_diff( Presets::starter_keys(), array_column( Presets::all(), 'key' ) ) );
	}

	public function test_prueffelder_sind_gesetzt(): void {
		$presets = array_column( Presets::all(), null, 'key' );

		$this->assertSame( 'email', $presets['email']['field']['type'] );
		$this->assertSame( 'tel', $presets['phone']['field']['type'] );
		$this->assertSame( 'postcode_ch', $presets['postcode']['field']['format'] );
		$this->assertSame( 'postal-code', $presets['postcode']['field']['autocomplete'] );
		$this->assertSame( '{privacy_url}', $presets['consent']['field']['link'] );
		$this->assertTrue( $presets['consent']['field']['required'] );
	}

	public function test_land_hat_genau_eine_standardzeile(): void {
		$country  = array_column( Presets::all(), null, 'key' )['country']['field'];
		$defaults = array_filter( $country['options'], static fn( $option ) => is_array( $option ) && ! empty( $option['default'] ) );

		$this->assertCount( 1, $defaults );
		$this->assertFalse( $country['empty_first'] );
	}

	public function test_filter_kann_ergaenzen_und_entfernen(): void {
		add_filter(
			'mlab_forms_presets',
			static function ( array $presets ): array {
				array_shift( $presets );
				$presets[] = array(
					'key'   => 'eigen',
					'name'  => 'Eigen',
					'field' => array(
						'type'  => 'text',
						'label' => 'Eigen',
					),
				);

				return $presets;
			}
		);

		$keys = array_column( Presets::all(), 'key' );

		$this->assertNotContains( 'first_name', $keys );
		$this->assertContains( 'eigen', $keys );
		$this->assertSame( '', array_column( Presets::all(), 'hint', 'key' )['eigen'] );
	}

	public function test_ungueltige_und_doppelte_bausteine_werden_ignoriert(): void {
		add_filter(
			'mlab_forms_presets',
			static fn( array $presets ): array => array_merge(
				$presets,
				array(
					'kein Array',
					array( 'key' => 'ohne_feld', 'name' => 'X' ),
					array( 'key' => 'Gross Schreibung', 'name' => 'X', 'field' => array( 'type' => 'text' ) ),
					array( 'key' => 'email', 'name' => 'Doppelt', 'field' => array( 'type' => 'text' ) ),
				)
			)
		);

		$all  = Presets::all();
		$keys = array_column( $all, 'key' );

		$this->assertNotContains( 'ohne_feld', $keys );
		$this->assertNotContains( 'Gross Schreibung', $keys );
		$this->assertSame( 1, count( array_keys( $keys, 'email', true ) ) );
		$this->assertSame( 'E-Mail', array_column( $all, 'name', 'key' )['email'] );
	}

	public function test_id_wird_entfernt_und_ungueltige_felder_werden_verworfen(): void {
		add_filter(
			'mlab_forms_presets',
			static fn( array $presets ): array => array_merge(
				$presets,
				array(
					array( 'key' => 'mit_id', 'name' => 'Mit ID', 'field' => array( 'id' => 'boese', 'type' => 'text', 'label' => 'X' ) ),
					array( 'key' => 'falsches_format', 'name' => 'Falsch', 'field' => array( 'type' => 'text', 'label' => 'X', 'format' => 'unbekannt' ) ),
					array( 'key' => 'ohne_label', 'name' => 'Ohne', 'field' => array( 'type' => 'text' ) ),
					array( 'key' => 'unbekannter_typ', 'name' => 'Typ', 'field' => array( 'type' => 'gibtsnicht', 'label' => 'X' ) ),
					array( 'key' => 'zwei_standard', 'name' => 'Zwei', 'field' => array( 'type' => 'select', 'label' => 'X', 'options' => array( array( 'value' => 'a', 'default' => true ), array( 'value' => 'b', 'default' => true ) ) ) ),
				)
			)
		);

		$all = array_column( Presets::all(), null, 'key' );

		$this->assertArrayNotHasKey( 'id', $all['mit_id']['field'] );
		foreach ( array( 'falsches_format', 'ohne_label', 'unbekannter_typ', 'zwei_standard' ) as $key ) {
			$this->assertArrayNotHasKey( $key, $all, $key );
		}
	}
}
