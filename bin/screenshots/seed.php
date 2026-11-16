<?php
/** Reproduzierbare Demodaten ausschliesslich für eine lokale Screenshot-Installation. */
use MLab_Forms\Forms\Templates;
use MLab_Forms\Forms\FormRepository;
use MLab_Forms\Entries\EntryRepository;
use MLab_Forms\Settings;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '/tmp/mlab-wp' !== realpath( ABSPATH ) || 'true' !== getenv( 'GITHUB_ACTIONS' ) ) {
	WP_CLI::error( 'Nur für die kurzlebige Screenshot-Installation auf GitHub Actions.' );
}
wp_set_current_user( 1 );
update_option( 'timezone_string', 'Europe/Zurich' );
update_option( 'WPLANG', 'de_DE' );
update_option( Settings::OPTION, array_merge( Settings::defaults(), array(
	'default_recipient' => 'office@example.org',
	'email_from_name' => 'MomentumLab Demo',
	'email_from_address' => 'formular@example.org',
) ) );
$form = wp_insert_post( array( 'post_type' => 'mlab_form', 'post_title' => 'Eventanfrage', 'post_status' => 'publish' ) );
$schema = Templates::get( 'kontakt' );
$schema['settings']['recipients'] = 'events@example.org';
$errors = FormRepository::save( $form, $schema );
if ( $errors ) { WP_CLI::error( wp_json_encode( $errors ) ); }
$first = 0;
foreach ( array( 'Anna Keller', 'Lukas Meier', 'Nina Berger', 'Jonas Weber', 'Lea Baumann' ) as $index => $name ) {
	$id = EntryRepository::insert( $form, array( 'name' => $name, 'email' => 'demo' . $index . '@example.org', 'nachricht' => 'Guten Tag, wir planen einen Firmenevent mit rund 80 Gästen. Können Sie uns ein Angebot für Licht und Ton erstellen?', 'consent' => true ), array( 'language' => 'de', 'source' => 'https://example.org/kontakt' ) );
	if ( ! $first ) { $first = $id; }
	if ( 1 === $index ) { EntryRepository::set_status( $id, 'in_progress' ); }
	if ( 3 === $index ) { EntryRepository::set_status( $id, 'answered' ); }
}
EntryRepository::add_note( $first, 'note', 'Offerte vorbereiten und Verfügbarkeit für den gewünschten Termin prüfen.' );
file_put_contents( '/tmp/mlab-demo.json', wp_json_encode( array( 'form' => $form, 'entry' => $first ) ) );
