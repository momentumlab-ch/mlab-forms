<?php
/**
 * Tests für Texte und Vorschau des Builders.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Tests\Unit;

use MLab_Forms\Admin\BuilderTexts;
use MLab_Forms\Admin\Preview;
use PHPUnit\Framework\TestCase;

final class BuilderTest extends TestCase {

	public function test_alle_texte_des_skripts_sind_uebersetzbar_vorhanden(): void {
		$source = '';
		foreach ( array_merge( array( MLAB_FORMS_DIR . 'assets/js/builder.js' ), glob( MLAB_FORMS_DIR . 'assets/js/builder/*.js' ) ) as $file ) {
			$source .= file_get_contents( $file );
		}
		preg_match_all( '/(?:app\.t\(|texts\.|\btext\.)\s*\'?([a-zA-Z0-9]+)/', $source, $used );
		preg_match_all( "/(?:app\.t\( |texts\[ )'([a-zA-Z0-9]+)'/", $source, $quoted );
		$known = array_keys( BuilderTexts::all() );

		$missing = array_diff( array_unique( array_merge( $used[1], $quoted[1] ) ), $known, array( 'replace', 'length', 'test', 'labels', 'labelKey' ) );

		$this->assertSame( array(), array_values( $missing ) );
		foreach ( BuilderTexts::all() as $key => $text ) {
			$this->assertNotSame( '', $text, $key );
		}
	}

	public function test_ungueltiges_schema_liefert_fehler_ohne_vorschau(): void {
		$answer = Preview::respond( array( 'schema_version' => 1 ) );

		$this->assertFalse( $answer['ok'] );
		$this->assertArrayNotHasKey( 'document', $answer );
		$this->assertSame( 'fields', $answer['errors'][0]['path'] );
	}

	public function test_kein_objekt_liefert_fehler(): void {
		$this->assertFalse( Preview::respond( null )['ok'] );
	}
}
