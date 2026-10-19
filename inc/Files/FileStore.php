<?php
/**
 * Geschützter Speicher für hochgeladene Dateien.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Files;

use MLab_Forms\Capabilities;
use MLab_Forms\Entries\EntryRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Legt Uploads unter wp-content/uploads/mlab-forms/ ab, mit zufälligem Ordner pro Datei
 * und ohne direkten Zugriff aus dem Web. Herunterladen geht nur angemeldet über admin-post.php.
 */
final class FileStore {

	public const DIR             = 'mlab-forms';
	public const DOWNLOAD_ACTION = 'mlab_forms_file';

	/**
	 * Hängt den Download an.
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::DOWNLOAD_ACTION, array( $this, 'download' ) );
	}

	/**
	 * Hauptordner, legt ihn samt Schutzdateien bei Bedarf an.
	 */
	public static function base_dir(): string {
		$uploads = wp_upload_dir( null, false );
		$dir     = trailingslashit( (string) $uploads['basedir'] ) . self::DIR;

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		self::protect( $dir );

		return $dir;
	}

	/**
	 * Sperrt den Ordner für Apache/LiteSpeed und verhindert Verzeichnislisten.
	 *
	 * @param string $dir Ordner.
	 */
	private static function protect( string $dir ): void {
		$files = array(
			'.htaccess'  => "Require all denied\nDeny from all\n",
			'index.php'  => "<?php\n// Silence is golden.\n",
			'index.html' => '',
		);
		foreach ( $files as $name => $content ) {
			if ( ! file_exists( $dir . '/' . $name ) ) {
				file_put_contents( $dir . '/' . $name, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Schutzdatei im eigenen Ordner.
			}
		}
	}

	/**
	 * Verschiebt alle Uploads einer geprüften Absendung in den Speicher.
	 *
	 * @param array<string, mixed> $values Bereinigte Werte.
	 * @return array<string, mixed>|null Werte mit gespeicherten Angaben statt Uploads; null, wenn eine Datei nicht gespeichert werden konnte.
	 */
	public static function persist( array $values ): ?array {
		$stored = array();
		foreach ( $values as $id => $value ) {
			if ( ! is_array( $value ) || empty( $value['upload'] ) ) {
				continue;
			}
			$file = self::move( $value );
			if ( null === $file ) {
				self::delete_values( $stored );
				return null;
			}
			$values[ $id ] = $file;
			$stored[ $id ] = $file;
		}

		return $values;
	}

	/**
	 * Eine Datei aus dem Upload in einen eigenen, zufälligen Ordner.
	 *
	 * @param array<string, mixed> $upload Bereinigter Upload aus FileField::sanitize().
	 * @return array{name: string, size: int, type: string, path: string}|null
	 */
	private static function move( array $upload ): ?array {
		$folder = strtolower( wp_generate_password( 24, false ) );
		$dir    = self::base_dir() . '/' . $folder;
		if ( ! wp_mkdir_p( $dir ) ) {
			return null;
		}

		$name   = '' !== $upload['name'] ? $upload['name'] : 'datei';
		$target = $dir . '/' . $name;
		if ( ! move_uploaded_file( $upload['tmp_name'], $target ) ) { // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- geprüfter Upload, eigener Speicher.
			return null;
		}

		$check = wp_check_filetype( $name );

		return array(
			'name' => $name,
			'size' => (int) filesize( $target ),
			'type' => (string) ( $check['type'] ?? 'application/octet-stream' ),
			'path' => $folder . '/' . $name,
		);
	}

	/**
	 * Absoluter Pfad einer gespeicherten Datei; null, wenn ungültig oder nicht vorhanden.
	 *
	 * @param mixed $file Gespeicherte Angaben.
	 */
	public static function absolute( $file ): ?string {
		if ( ! is_array( $file ) || empty( $file['path'] ) || ! preg_match( '#^[a-z0-9]{24}/[^/\\\\]+$#', (string) $file['path'] ) ) {
			return null;
		}
		$path = self::base_dir() . '/' . $file['path'];

		return is_file( $path ) ? $path : null;
	}

	/**
	 * Gespeicherte Dateien in den Werten einer Anfrage.
	 *
	 * @param array<string, mixed> $values Werte.
	 * @return array<string, array<string, mixed>> Feld-ID => Angaben.
	 */
	public static function files_in( array $values ): array {
		return array_filter( $values, static fn( $value ) => null !== self::absolute( $value ) );
	}

	/**
	 * Löscht die Dateien in den Werten samt ihren Ordnern.
	 *
	 * @param array<string, mixed> $values Werte.
	 */
	public static function delete_values( array $values ): void {
		foreach ( self::files_in( $values ) as $file ) {
			$path = (string) self::absolute( $file );
			wp_delete_file( $path );
			@rmdir( dirname( $path ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- leerer Zufallsordner.
		}
	}

	/**
	 * Link zum Herunterladen im Backend.
	 *
	 * @param int    $entry_id Anfrage.
	 * @param string $field_id Feld.
	 */
	public static function download_url( int $entry_id, string $field_id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::DOWNLOAD_ACTION,
					'entry'  => $entry_id,
					'field'  => $field_id,
				),
				admin_url( 'admin-post.php' )
			),
			self::DOWNLOAD_ACTION . '_' . $entry_id
		);
	}

	/**
	 * Liefert eine Datei an berechtigte Benutzer aus.
	 */
	public function download(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce wird unten geprüft.
		$entry_id = isset( $_GET['entry'] ) ? absint( $_GET['entry'] ) : 0;
		$field_id = isset( $_GET['field'] ) ? sanitize_key( wp_unslash( $_GET['field'] ) ) : '';
		// phpcs:enable

		check_admin_referer( self::DOWNLOAD_ACTION . '_' . $entry_id );
		if ( ! current_user_can( Capabilities::VIEW_ENTRIES ) ) {
			wp_die( esc_html__( 'Du darfst diese Datei nicht öffnen.', 'mlab-forms' ), '', array( 'response' => 403 ) );
		}

		$entry = EntryRepository::find( $entry_id );
		$file  = $entry['data'][ $field_id ] ?? null;
		$path  = self::absolute( $file );
		if ( null === $path ) {
			wp_die( esc_html__( 'Die Datei gibt es nicht mehr.', 'mlab-forms' ), '', array( 'response' => 404 ) );
		}

		nocache_headers();
		header( 'Content-Type: ' . ( (string) ( $file['type'] ?? '' ) ?: 'application/octet-stream' ) );
		header( 'Content-Disposition: attachment; filename="' . str_replace( array( '"', "\r", "\n" ), '', (string) $file['name'] ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Auslieferung der geprüften Datei.
		exit;
	}
}
