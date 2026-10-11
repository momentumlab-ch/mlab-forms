<?php
/**
 * Updates aus den GitHub-Releases.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Updates;

use MLab_Forms\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Meldet neue Versionen aus github.com/momentumlab-ch/mlab-forms an WordPress.
 *
 * Der Plugin-Header «Update URI» zeigt auf github.com. WordPress fragt deshalb nicht
 * wordpress.org, sondern den Filter «update_plugins_github.com».
 */
final class GitHubUpdater {

	public const REPOSITORY = 'momentumlab-ch/mlab-forms';

	public const CACHE_KEY    = 'mlab_forms_latest_release';
	private const CACHE_TTL   = 6 * HOUR_IN_SECONDS;
	private const ERROR_TTL   = HOUR_IN_SECONDS;
	private const ASSET_REGEX = '/^mlab-forms-[\w.\-]+\.zip$/';

	/**
	 * Hängt die Filter an.
	 */
	public function register(): void {
		add_filter( 'update_plugins_github.com', array( $this, 'filter_update' ), 10, 3 );
		add_filter( 'plugins_api', array( $this, 'filter_plugin_info' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache_after_upgrade' ), 10, 2 );
		// Wer die Update-Daten von WordPress verwirft («Erneut prüfen», WP-CLI), will auch frische GitHub-Daten.
		add_action( 'delete_site_transient_update_plugins', array( $this, 'clear_cache' ) );
		add_action( 'load-update-core.php', array( $this, 'clear_cache_on_force_check' ) );
	}

	/**
	 * Liefert WordPress die Update-Daten für dieses Plugin.
	 *
	 * @param array<string, mixed>|false $update      Bisherige Daten.
	 * @param array<string, string>      $plugin_data Plugin-Header.
	 * @param string                     $plugin_file Basisname.
	 * @return array<string, mixed>|false
	 */
	public function filter_update( $update, $plugin_data, $plugin_file ) {
		if ( Plugin::basename() !== $plugin_file ) {
			return $update;
		}

		$release = $this->latest_release();
		if ( null === $release ) {
			return $update;
		}

		// Ist die Version nicht neuer, führt WordPress das Plugin unter «aktuell».
		return array(
			'id'           => 'github.com/' . self::REPOSITORY,
			'slug'         => 'mlab-forms',
			'plugin'       => $plugin_file,
			'version'      => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['package'],
			'requires_php' => $plugin_data['RequiresPHP'] ?? '',
		);
	}

	/**
	 * Inhalt des Fensters «Details ansehen».
	 *
	 * @param false|object|array<string, mixed> $result Bisheriges Ergebnis.
	 * @param string                            $action API-Aktion.
	 * @param object                            $args   Anfrage.
	 * @return false|object|array<string, mixed>
	 */
	public function filter_plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || 'mlab-forms' !== ( $args->slug ?? '' ) ) {
			return $result;
		}

		$release = $this->latest_release();
		if ( null === $release ) {
			return $result;
		}

		$header = get_plugin_data( MLAB_FORMS_FILE, false, false );

		return (object) array(
			'name'          => $header['Name'],
			'slug'          => 'mlab-forms',
			'version'       => $release['version'],
			'author'        => sprintf( '<a href="%s">%s</a>', esc_url( $header['AuthorURI'] ), esc_html( $header['Author'] ) ),
			'homepage'      => $header['PluginURI'],
			'download_link' => $release['package'],
			'last_updated'  => $release['published'],
			'sections'      => array(
				'changelog' => wpautop( esc_html( $release['notes'] ) ),
			),
		);
	}

	/**
	 * Neuestes Release mit ZIP, aus dem Cache oder von GitHub.
	 *
	 * @return array{version: string, url: string, package: string, published: string, notes: string}|null
	 */
	public function latest_release(): ?array {
		$cached = $this->cached_release();
		if ( false !== $cached ) {
			return $cached;
		}

		$release = $this->fetch_latest_release();
		// Auch Fehler kurz cachen, damit eine nicht erreichbare API nicht jeden Seitenaufruf bremst.
		set_site_transient( self::CACHE_KEY, array( 'release' => $release ), $release ? self::CACHE_TTL : self::ERROR_TTL );

		return $release;
	}

	/**
	 * Release aus dem Cache, ohne GitHub zu fragen.
	 *
	 * @return array{version: string, url: string, package: string, published: string, notes: string}|null|false
	 *         false, wenn noch nicht geprüft wurde.
	 */
	public function cached_release() {
		$cached = get_site_transient( self::CACHE_KEY );

		return is_array( $cached ) ? ( $cached['release'] ?? null ) : false;
	}

	/**
	 * Leert den Cache.
	 */
	public function clear_cache(): void {
		delete_site_transient( self::CACHE_KEY );
	}

	/**
	 * «Erneut prüfen» unter «Dashboard › Aktualisierungen» fragt auch GitHub neu an.
	 */
	public function clear_cache_on_force_check(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WordPress prüft hier selbst keinen Nonce, es wird nur der Cache geleert.
		if ( ! empty( $_GET['force-check'] ) && current_user_can( 'update_plugins' ) ) {
			$this->clear_cache();
		}
	}

	/**
	 * Leert den Cache nur, wenn dieses Plugin aktualisiert wurde.
	 *
	 * @param object               $upgrader   Upgrader.
	 * @param array<string, mixed> $hook_extra Angaben zum Update.
	 */
	public function clear_cache_after_upgrade( $upgrader, $hook_extra ): void {
		$plugins = (array) ( $hook_extra['plugins'] ?? $hook_extra['plugin'] ?? array() );

		if ( 'plugin' === ( $hook_extra['type'] ?? '' ) && in_array( Plugin::basename(), $plugins, true ) ) {
			$this->clear_cache();
		}
	}

	/**
	 * Fragt die GitHub-API ab.
	 *
	 * @return array{version: string, url: string, package: string, published: string, notes: string}|null
	 */
	private function fetch_latest_release(): ?array {
		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPOSITORY . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'mlab-forms/' . MLAB_FORMS_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		return is_array( $data ) ? self::parse_release( $data ) : null;
	}

	/**
	 * Wandelt die API-Antwort in die benötigten Felder um.
	 *
	 * @param array<string, mixed> $data Release aus der GitHub-API.
	 * @return array{version: string, url: string, package: string, published: string, notes: string}|null
	 */
	private static function parse_release( array $data ): ?array {
		$version = ltrim( (string) ( $data['tag_name'] ?? '' ), 'vV' );
		$package = self::find_package( $data['assets'] ?? array() );

		if ( '' === $version || null === $package ) {
			return null;
		}

		return array(
			'version'   => $version,
			'url'       => (string) ( $data['html_url'] ?? '' ),
			'package'   => $package,
			'published' => (string) ( $data['published_at'] ?? '' ),
			'notes'     => (string) ( $data['body'] ?? '' ),
		);
	}

	/**
	 * Download-Adresse des Plugin-ZIPs unter den Release-Dateien.
	 *
	 * @param mixed $assets Release-Dateien.
	 */
	private static function find_package( $assets ): ?string {
		foreach ( is_array( $assets ) ? $assets : array() as $asset ) {
			$name = (string) ( $asset['name'] ?? '' );
			if ( preg_match( self::ASSET_REGEX, $name ) && ! empty( $asset['browser_download_url'] ) ) {
				return (string) $asset['browser_download_url'];
			}
		}

		return null;
	}
}
