<?php
namespace MLab_Forms\Tests\Unit;
use MLab_Forms\Admin\PluginDetails;
use PHPUnit\Framework\TestCase;
final class PluginDetailsTest extends TestCase {
	public function test_preserves_update_and_license_metadata(): void {
		$result = (object) array( 'version' => '9.9.9', 'download_link' => 'https://example.org/signed.zip', 'sections' => array( 'changelog' => 'Provider changelog' ) );
		$details = ( new PluginDetails() )->enrich( $result, 'plugin_information', (object) array( 'slug' => 'mlab-forms' ) );
		$this->assertSame( '9.9.9', $details->version );
		$this->assertSame( 'https://example.org/signed.zip', $details->download_link );
		$this->assertSame( 'Provider changelog', $details->sections['changelog'] );
		$this->assertStringContainsString( 'Resend', $details->sections['description'] );
		$this->assertStringContainsString( 'Zustelladresse', $details->sections['faq'] );
		$this->assertArrayHasKey( 'installation', $details->sections );
	}
	public function test_other_plugins_and_failures_are_unchanged(): void {
		$result = (object) array( 'version' => '1.0' );
		$this->assertSame( $result, ( new PluginDetails() )->enrich( $result, 'plugin_information', (object) array( 'slug' => 'other' ) ) );
		$this->assertFalse( ( new PluginDetails() )->enrich( false, 'plugin_information', (object) array( 'slug' => 'mlab-forms' ) ) );
	}
}
