<?php
/**
 * Minimale WordPress-Funktionen für Unit-Tests. Daten liegen in $GLOBALS['mlab_test'].
 *
 * @package MLab_Forms
 */

// phpcs:disable

/** Setzt den Testspeicher zurück. */
function mlab_test_reset(): void {
	$GLOBALS['mlab_test'] = array(
		'options'    => array(),
		'transients' => array(),
		'post_meta'  => array(),
		'posts'      => array(),
		'filters'    => array(),
	);
}
mlab_test_reset();

function __( $text, $domain = 'default' ) {
	return $text;
}

function esc_html__( $text, $domain = 'default' ) {
	return htmlspecialchars( $text, ENT_QUOTES );
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function esc_url( $url ) {
	return (string) $url;
}

function esc_textarea( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function sanitize_text_field( $text ) {
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $text ) ) );
}

function sanitize_textarea_field( $text ) {
	return trim( strip_tags( (string) $text ) );
}

function sanitize_email( $email ) {
	return is_email( $email ) ? $email : '';
}

function sanitize_html_class( $class ) {
	return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
}

function wp_strip_all_tags( $text ) {
	return trim( strip_tags( (string) $text ) );
}

function wp_kses_post( $text ) {
	return (string) $text;
}

function is_email( $email ) {
	return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['mlab_test']['filters'][ $hook ][] = $callback;
	return true;
}

function apply_filters( $hook, $value, ...$args ) {
	foreach ( $GLOBALS['mlab_test']['filters'][ $hook ] ?? array() as $callback ) {
		$value = $callback( $value, ...$args );
	}
	return $value;
}

function wp_salt( $scheme = 'auth' ) {
	return 'test-salt-' . $scheme;
}

function current_time( $type, $gmt = false ) {
	return 'mysql' === $type ? gmdate( 'Y-m-d H:i:s' ) : gmdate( $type );
}

function get_option( $name, $default = false ) {
	return $GLOBALS['mlab_test']['options'][ $name ] ?? $default;
}

function update_option( $name, $value ) {
	$GLOBALS['mlab_test']['options'][ $name ] = $value;
	return true;
}

function get_transient( $key ) {
	return $GLOBALS['mlab_test']['transients'][ $key ] ?? false;
}

function set_transient( $key, $value, $expiration = 0 ) {
	$GLOBALS['mlab_test']['transients'][ $key ] = $value;
	return true;
}

function get_post_meta( $id, $key, $single = false ) {
	return $GLOBALS['mlab_test']['post_meta'][ $id ][ $key ] ?? '';
}

function get_posts( $args ) {
	return array();
}

function post_type_exists( $type ) {
	return false;
}

function get_bloginfo( $show = '' ) {
	return $GLOBALS['mlab_test']['options']['blogname'] ?? 'Testseite';
}

function wp_specialchars_decode( $text, $quote_style = ENT_NOQUOTES ) {
	return htmlspecialchars_decode( (string) $text, $quote_style );
}

function wp_unslash( $value ) {
	return $value;
}

function esc_url_raw( $url ) {
	return (string) $url;
}

function home_url( $path = '' ) {
	return 'https://example.org' . $path;
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( (string) $url, $component );
}

function get_locale() {
	return 'de_CH';
}

function add_query_arg( $key, $value, $url ) {
	return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . $key . '=' . $value;
}

function determine_locale() {
	return $GLOBALS['mlab_test']['locale'] ?? 'de_CH';
}

function pll_current_language() {
	return $GLOBALS['mlab_test']['polylang'] ?? false;
}

function is_admin() {
	return ! empty( $GLOBALS['mlab_test']['is_admin'] );
}

function mlab_ml_current_language() {
	return $GLOBALS['mlab_test']['mlab_ml'] ?? '';
}

function wp_mail( $to, $subject, $message, $headers = '' ) {
	$GLOBALS['mlab_mails'][] = array(
		'to'      => (array) $to,
		'subject' => $subject,
		'body'    => $message,
		'headers' => $headers,
	);
	return true;
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function remove_query_arg( $key, $url ) {
	return $url;
}
