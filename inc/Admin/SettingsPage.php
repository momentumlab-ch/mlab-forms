<?php
/**
 * Einstellungsseite im Backend.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Admin;

use MLab_Forms\Plugin;
use MLab_Forms\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Hauptmenü «MomentumLab Forms» mit der Seite «Einstellungen» und ihren Tabs.
 */
final class SettingsPage {

	public const SLUG       = 'mlab-forms-settings';
	public const CAPABILITY = 'manage_options';
	private const GROUP     = 'mlab_forms';

	/**
	 * Hook-Suffix der Seite, um das Skript nur dort zu laden.
	 *
	 * @var string|false
	 */
	private $hook = false;

	/**
	 * Adresse der Einstellungsseite, optional mit Tab.
	 *
	 * @param string $tab Tab-Schlüssel.
	 */
	public static function url( string $tab = '' ): string {
		$args = array( 'page' => self::SLUG );
		if ( '' !== $tab ) {
			$args['tab'] = $tab;
		}

		return add_query_arg( $args, admin_url( Menu::PARENT ) );
	}

	/**
	 * Hängt Menü, Einstellungen und Aktionen an.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 20 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'plugin_action_links_' . Plugin::basename(), array( $this, 'add_action_link' ) );
	}

	/**
	 * Unterpunkt «Einstellungen» im Menü von MomentumLab Forms.
	 */
	public function add_menu(): void {
		$this->hook = add_submenu_page(
			Menu::PARENT,
			__( 'Einstellungen', 'mlab-forms' ),
			__( 'Einstellungen', 'mlab-forms' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Lädt das Skript für «Verbindung testen» auf der Einstellungsseite.
	 *
	 * @param string $hook_suffix Aktuelle Admin-Seite.
	 */
	public function enqueue_scripts( string $hook_suffix ): void {
		if ( $hook_suffix !== $this->hook ) {
			return;
		}

		wp_enqueue_script( 'mlab-forms-admin', plugins_url( 'assets/js/admin.js', MLAB_FORMS_FILE ), array(), MLAB_FORMS_VERSION, array( 'in_footer' => true ) );
		wp_localize_script(
			'mlab-forms-admin',
			'mlabFormsAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => ConnectionTest::ACTION,
				'nonce'   => wp_create_nonce( ConnectionTest::ACTION ),
				'working' => __( 'Teste die Verbindung …', 'mlab-forms' ),
				'failed'  => __( 'Der Test hat nicht geklappt. Bitte versuche es nochmals.', 'mlab-forms' ),
			)
		);
	}

	/**
	 * Registriert Option, einen Abschnitt pro Tab und alle Felder.
	 */
	public function register_settings(): void {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'object',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);

		foreach ( Settings::tabs() as $tab => $title ) {
			add_settings_section( 'mlab_forms_' . $tab, '', '__return_null', $this->section_page( $tab ) );
		}

		foreach ( Settings::sections() as $key => $section ) {
			add_settings_section(
				'mlab_forms_' . $key,
				$section['title'],
				static function () use ( $section ): void {
					printf( '<p>%s</p>', esc_html( $section['text'] ) );
				},
				$this->section_page( $section['tab'] )
			);
		}

		foreach ( Settings::fields() as $key => $field ) {
			add_settings_field(
				'mlab_forms_' . $key,
				$field['label'],
				array( FieldRenderer::class, 'render' ),
				$this->section_page( $field['tab'] ),
				'mlab_forms_' . ( $field['section'] ?? $field['tab'] ),
				array(
					'label_for' => 'mlab_forms_' . $key,
					'key'       => $key,
					'field'     => $field,
				)
			);
		}
	}

	/**
	 * Gibt die Seite mit Tab-Leiste aus.
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$tab = $this->current_tab();
		?>
		<div class="wrap mlab-workspace">
			<?php Workspace::heading( __( 'Einstellungen', 'mlab-forms' ), __( 'Einrichten. Testen. Bereit.', 'mlab-forms' ) ); ?>
			<?php $this->render_notices(); ?>
			<div class="mlab-settings-layout">
				<?php $this->render_tabs( $tab ); ?>
				<main class="mlab-settings-content">
					<?php if ( $this->tab_has_fields( $tab ) ) : ?>
						<form action="options.php" method="post" data-mlab-settings>
							<?php
							settings_fields( self::GROUP );
							printf( '<input type="hidden" name="%s[%s]" value="%s">', esc_attr( Settings::OPTION ), esc_attr( Settings::TAB_KEY ), esc_attr( $tab ) );
							$this->render_cards( $tab );
							?>
							<div class="mlab-savebar"><span data-mlab-save-state role="status"><?php esc_html_e( 'Alle Änderungen gespeichert', 'mlab-forms' ); ?></span><?php submit_button( __( 'Änderungen speichern', 'mlab-forms' ), 'primary', 'submit', false ); ?></div>
						</form>
					<?php endif; ?>
					<?php do_action( "mlab_forms_settings_tab_{$tab}" ); ?>
				</main>
			</div>
		</div>
		<?php
	}

	/**
	 * Tab-Leiste.
	 *
	 * @param string $current Aktiver Tab.
	 */
	private function render_tabs( string $current ): void {
		?>
		<nav class="nav-tab-wrapper mlab-settings-nav" aria-label="<?php esc_attr_e( 'Einstellungsbereiche', 'mlab-forms' ); ?>">
			<?php foreach ( Settings::tabs() as $key => $title ) : ?>
				<a href="<?php echo esc_url( self::url( $key ) ); ?>" class="nav-tab <?php echo $key === $current ? 'nav-tab-active' : ''; ?>" <?php echo $key === $current ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $title ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Fachlich gruppierte Karten statt einer langen Einstellungstabelle.
	 *
	 * @param string $tab Bereich.
	 */
	private function render_cards( string $tab ): void {
		$groups = array();
		foreach ( Settings::fields() as $key => $field ) {
			if ( $tab !== $field['tab'] ) {
				continue;
			}
			$group = $field['section'] ?? $tab;
			if ( 'email' === $tab ) {
				$group = 'default_recipient' === $key ? 'recipients' : 'sender';
			} elseif ( 'spam' === $tab && preg_match( '/^spam_(turnstile|recaptcha|hcaptcha|akismet)_/', $key, $match ) ) {
				$group = $match[1];
			}
			$groups[ $group ][ $key ] = $field;
		}
		$titles = array(
			'general'    => __( 'Darstellung', 'mlab-forms' ),
			'whatsapp'   => 'WhatsApp',
			'recipients' => __( '1 · Zustelladresse', 'mlab-forms' ),
			'sender'     => __( '2 · Absender & Antworten', 'mlab-forms' ),
			'spam'       => __( 'Basisschutz', 'mlab-forms' ),
			'services'   => __( 'Spam-Dienst wählen', 'mlab-forms' ),
			'turnstile'  => 'Cloudflare Turnstile',
			'recaptcha'  => 'Google reCAPTCHA',
			'hcaptcha'   => 'hCaptcha',
			'akismet'    => 'Akismet',
			'privacy'    => __( 'Aufbewahrung & Daten', 'mlab-forms' ),
		);
		if ( 'email' === $tab ) {
			echo '<p class="mlab-workflow">' . esc_html__( '1 Empfänger → 2 Absender → 3 Testversand', 'mlab-forms' ) . '</p>';
		}
		echo '<div class="mlab-settings-cards">';
		foreach ( $groups as $group => $fields ) {
			?>
			<section class="mlab-card">
				<h2><?php echo esc_html( $titles[ $group ] ?? $group ); ?></h2>
				<?php if ( isset( Settings::sections()[ $group ] ) ) : ?>
					<p class="description"><?php echo esc_html( Settings::sections()[ $group ]['text'] ); ?></p>
				<?php endif; ?>
				<?php foreach ( $fields as $key => $field ) : ?>
					<div class="mlab-setting-field">
						<label for="mlab_forms_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
						<?php
						FieldRenderer::render(
							array(
								'key'   => $key,
								'field' => $field,
							)
						);
						?>
					</div>
				<?php endforeach; ?>
			</section>
			<?php
		}
		if ( 'email' === $tab ) {
			$this->render_mail_preview();
		}
		echo '</div>';
	}

	/**
	 * Versandvorschau und Test der gespeicherten Einstellungen.
	 */
	private function render_mail_preview(): void {
		?>
		<section class="mlab-card mlab-mail-preview">
			<h2><?php esc_html_e( '3 · Testversand', 'mlab-forms' ); ?></h2>
			<p><?php esc_html_e( 'So wird deine Anfrage versendet', 'mlab-forms' ); ?></p>
			<dl>
				<dt><?php esc_html_e( 'An', 'mlab-forms' ); ?></dt><dd data-mlab-mail-to data-fallback="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>"><?php echo esc_html( implode( ', ', \MLab_Forms\Mail\Recipients::defaults() ) ); ?></dd>
				<dt><?php esc_html_e( 'Von', 'mlab-forms' ); ?></dt><dd data-mlab-mail-from><?php echo esc_html( (string) Settings::get( 'email_from_name' ) ); ?> · <?php echo esc_html( (string) Settings::get( 'email_from_address' ) ); ?></dd>
				<dt><?php esc_html_e( 'Antwort an', 'mlab-forms' ); ?></dt><dd data-mlab-mail-reply><?php echo esc_html( 'visitor' === Settings::get( 'email_reply_to_mode' ) ? __( 'E-Mail-Adresse des Kunden', 'mlab-forms' ) : (string) Settings::get( 'email_reply_to' ) ); ?></dd>
			</dl>
			<p class="description"><?php esc_html_e( 'Eigene Empfänger und Absender im Formular haben Vorrang. Die Kundenadresse wird nur für Antworten verwendet. SMTP-Plugins können den Absender übersteuern.', 'mlab-forms' ); ?></p>
			<p><?php esc_html_e( 'Zuerst Änderungen speichern. Die Testmail verwendet die gespeicherten Einstellungen und geht an die Standard-Empfänger.', 'mlab-forms' ); ?></p>
			<button type="button" class="button" data-mlab-mail-test><?php esc_html_e( 'Testmail senden', 'mlab-forms' ); ?></button>
			<p role="status" aria-live="polite" data-mlab-mail-result></p>
		</section>
		<?php
	}

	/**
	 * Hinweise nach dem Speichern.
	 */
	private function render_notices(): void {
		// Ausserhalb von «Einstellungen» zeigt WordPress die Meldungen nicht von selbst an.
		settings_errors();
	}

	/**
	 * Aktiver Tab aus der URL, sonst der erste.
	 */
	private function current_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur Navigation.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		return array_key_exists( $tab, Settings::tabs() ) ? $tab : (string) array_key_first( Settings::tabs() );
	}

	/**
	 * Hat der Tab eigene Einstellungen? Sonst entfällt das Formular.
	 *
	 * @param string $tab Tab-Schlüssel.
	 */
	private function tab_has_fields( string $tab ): bool {
		return in_array( $tab, array_column( Settings::fields(), 'tab' ), true );
	}

	/**
	 * Seiten-ID für die Abschnitte eines Tabs.
	 *
	 * @param string $tab Tab-Schlüssel.
	 */
	private function section_page( string $tab ): string {
		return self::SLUG . '-' . $tab;
	}

	/**
	 * Link «Einstellungen» in der Plugin-Liste.
	 *
	 * @param array<int|string, string> $links Bestehende Links.
	 * @return array<int|string, string>
	 */
	public function add_action_link( array $links ): array {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( self::url() ), esc_html__( 'Einstellungen', 'mlab-forms' ) )
		);

		return $links;
	}
}
