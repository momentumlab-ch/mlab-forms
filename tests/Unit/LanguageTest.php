<?php
/**
 * Tests für die Spracherkennung.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Tests\Unit;

use MLab_Forms\Forms\Language;
use PHPUnit\Framework\TestCase;

final class LanguageTest extends TestCase {

	protected function setUp(): void {
		mlab_test_reset();
	}

	public function test_codes_werden_normalisiert(): void {
		$this->assertSame( 'de', Language::normalize( 'de_CH' ) );
		$this->assertSame( 'fr', Language::normalize( 'FR-fr' ) );
		$this->assertSame( 'gsw', Language::normalize( ' gsw ' ) );
		$this->assertSame( 'zh', Language::normalize( 'zh-hans' ) );
		$this->assertSame( '', Language::normalize( '' ) );
		$this->assertSame( '', Language::normalize( null ) );
		$this->assertSame( '', Language::normalize( array( 'de' ) ) );
		$this->assertSame( '', Language::normalize( '../etc' ) );
		$this->assertSame( '', Language::normalize( 'deutsch' ) );
	}

	public function test_nur_sprachcodes_sind_gueltig(): void {
		$this->assertTrue( Language::is_code( 'de' ) );
		$this->assertTrue( Language::is_code( 'rm' ) );
		$this->assertFalse( Language::is_code( 'DE' ) );
		$this->assertFalse( Language::is_code( 'd' ) );
		$this->assertFalse( Language::is_code( 'de_CH' ) );
		$this->assertFalse( Language::is_code( "de\n" ) );
		$this->assertFalse( Language::is_code( 5 ) );
	}

	public function test_seitensprache_aus_der_sprache_der_website(): void {
		$GLOBALS['mlab_test']['locale'] = 'fr_CH';

		$this->assertSame( 'fr', Language::page() );
	}

	public function test_wpml_hat_vorrang(): void {
		$GLOBALS['mlab_test']['locale']   = 'de_CH';
		$GLOBALS['mlab_test']['polylang'] = 'it';
		add_filter( 'wpml_current_language', static fn() => 'fr' );

		$this->assertSame( 'fr', Language::page() );
	}

	public function test_polylang_vor_der_sprache_der_website(): void {
		$GLOBALS['mlab_test']['polylang'] = 'it';

		$this->assertSame( 'it', Language::page() );
	}

	public function test_polylang_ohne_ergebnis_faellt_auf_die_website_zurueck(): void {
		$GLOBALS['mlab_test']['polylang'] = false;
		$GLOBALS['mlab_test']['locale']   = 'en_US';

		$this->assertSame( 'en', Language::page() );
	}

	public function test_formular_zeigt_die_seitensprache_wenn_es_sie_kennt(): void {
		$GLOBALS['mlab_test']['locale'] = 'fr_CH';

		$this->assertSame( 'fr', Language::current( array( 'de', 'fr', 'en' ) ) );
	}

	public function test_unbekannte_seitensprache_ergibt_die_standardsprache(): void {
		$GLOBALS['mlab_test']['locale'] = 'it_CH';

		$this->assertSame( 'de', Language::current( array( 'de', 'fr' ) ) );
	}

	public function test_einsprachiges_formular_hat_keine_sprache(): void {
		$this->assertSame( '', Language::current( array() ) );
		$this->assertSame( '', Language::current( array( 'de' ) ) );
		$this->assertSame( '', Language::from_request( 'de', array( 'de' ) ) );
	}

	public function test_filter_ueberschreibt_die_sprache(): void {
		$GLOBALS['mlab_test']['locale'] = 'de_CH';
		add_filter( 'mlab_forms_current_language', static fn( string $lang, array $languages ) => in_array( 'en', $languages, true ) ? 'en' : $lang, 10, 2 );

		$this->assertSame( 'en', Language::current( array( 'de', 'en' ) ) );
		$this->assertSame( 'de', Language::current( array( 'de', 'fr' ) ) );
	}

	public function test_filter_mit_ungueltigem_wert_faellt_auf_standard_zurueck(): void {
		add_filter( 'mlab_forms_current_language', static fn() => 'xx-bogus-evil' );

		$this->assertSame( 'de', Language::current( array( 'de', 'fr' ) ) );
	}

	public function test_sprache_der_absendung_wird_geprueft(): void {
		$languages = array( 'de', 'fr' );

		$this->assertSame( 'fr', Language::from_request( 'fr', $languages ) );
		$this->assertSame( 'fr', Language::from_request( 'FR_ch', $languages ) );
		$this->assertSame( 'de', Language::from_request( 'it', $languages ) );
		$this->assertSame( 'de', Language::from_request( '<script>', $languages ) );
		$this->assertSame( 'de', Language::from_request( null, $languages ) );
		$this->assertSame( 'de', Language::from_request( array( 'fr' ), $languages ) );
	}

	public function test_mlab_multilingual_vor_der_sprache_der_website(): void {
		$GLOBALS['mlab_test']['locale'] = 'de_CH';
		$GLOBALS['mlab_test']['mlab_ml'] = 'fr';

		$this->assertSame( 'fr', Language::page() );
	}

	public function test_mlab_multilingual_im_backend_ignoriert(): void {
		$GLOBALS['mlab_test']['locale']   = 'it_CH';
		$GLOBALS['mlab_test']['mlab_ml']  = 'fr';
		$GLOBALS['mlab_test']['is_admin'] = true;

		$this->assertSame( 'it', Language::page() );
	}

	public function test_spam_schutz_bekommt_die_seitensprache(): void {
		$GLOBALS['mlab_test']['mlab_ml'] = 'fr';
		$this->assertSame( 'fr', Language::captcha() );

		$GLOBALS['mlab_test']['mlab_ml'] = 'rm';
		$this->assertSame( '', Language::captcha(), 'Unbekannte Sprache: der Anbieter wählt selbst.' );
	}
}
