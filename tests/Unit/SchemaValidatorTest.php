<?php
/**
 * Tests für die Schema-Prüfung.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Tests\Unit;

use MLab_Forms\Forms\SchemaValidator;
use MLab_Forms\Forms\Templates;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SchemaValidatorTest extends TestCase {

	/**
	 * @return array<string, array{string}>
	 */
	public static function templates(): array {
		return array(
			'kontakt'   => array( 'kontakt' ),
			'anfrage'   => array( 'anfrage' ),
			'gutschein' => array( 'gutschein' ),
		);
	}

	#[DataProvider( 'templates' )]
	public function test_mitgelieferte_vorlagen_sind_gueltig( string $key ): void {
		$schema = Templates::get( $key );

		$this->assertIsArray( $schema );
		$this->assertSame( array(), SchemaValidator::validate( $schema ) );
	}

	public function test_kein_objekt(): void {
		$this->assertSame( array( '' ), $this->paths( array( 1, 2 ) ) );
		$this->assertSame( array( '' ), $this->paths( 'text' ) );
	}

	public function test_falsche_version_und_leere_felder(): void {
		$this->assertSame(
			array( 'schema_version', 'fields' ),
			$this->paths(
				array(
					'schema_version' => 2,
					'fields'         => array(),
				)
			)
		);
	}

	public function test_doppelte_und_ungueltige_ids(): void {
		$paths = $this->paths(
			$this->schema(
				array(
					array( 'id' => 'name', 'type' => 'text', 'label' => 'Name' ),
					array( 'id' => 'name', 'type' => 'text', 'label' => 'Name 2' ),
					array( 'id' => 'Grossbuchstaben', 'type' => 'text', 'label' => 'X' ),
				)
			)
		);

		$this->assertSame( array( 'fields.1.id', 'fields.2.id' ), $paths );
	}

	public function test_unbekannter_typ_fehlendes_label_und_breite(): void {
		$paths = $this->paths(
			$this->schema(
				array(
					array( 'id' => 'a', 'type' => 'magic', 'label' => 'A' ),
					array( 'id' => 'b', 'type' => 'text' ),
					array( 'id' => 'c', 'type' => 'text', 'label' => 'C', 'width' => 'quarter' ),
					array( 'id' => 'd', 'type' => 'hidden' ),
				)
			)
		);

		$this->assertSame( array( 'fields.0.type', 'fields.1.label', 'fields.2.width' ), $paths );
	}

	public function test_typspezifische_pruefungen(): void {
		$paths = $this->paths(
			$this->schema(
				array(
					array( 'id' => 'wahl', 'type' => 'select', 'label' => 'Wahl' ),
					array( 'id' => 'datum', 'type' => 'date', 'label' => 'Datum', 'min' => 'morgen' ),
					array( 'id' => 'zahl', 'type' => 'number', 'label' => 'Zahl', 'min' => 'eins' ),
				)
			)
		);

		$this->assertSame( array( 'fields.0', 'fields.1', 'fields.2' ), $paths );
	}

	public function test_einstellungen_und_benachrichtigungen(): void {
		$schema             = $this->schema( array( array( 'id' => 'a', 'type' => 'text', 'label' => 'A' ) ) );
		$schema['settings'] = array(
			'store_entries'  => 'ja',
			'retention_days' => -1,
			'style'          => 'pink',
			'spam'           => array( 'min_seconds' => 120 ),
			'notifications'  => array( array( 'to' => 'a@example.org' ) ),
		);

		$this->assertSame(
			array(
				'settings.store_entries',
				'settings.retention_days',
				'settings.style',
				'settings.spam.min_seconds',
				'settings.notifications.0.subject',
			),
			$this->paths( $schema )
		);
	}

	/**
	 * @param list<array<string, mixed>> $fields Felder.
	 * @return array<string, mixed>
	 */
	private function schema( array $fields ): array {
		return array(
			'schema_version' => SchemaValidator::SCHEMA_VERSION,
			'fields'         => $fields,
		);
	}

	/**
	 * @param mixed $schema Schema.
	 * @return list<string>
	 */
	private function paths( $schema ): array {
		return array_column( SchemaValidator::validate( $schema ), 'path' );
	}
}
