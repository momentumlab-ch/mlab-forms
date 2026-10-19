<?php
/**
 * Formular-Builder im Bearbeiten-Bildschirm.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Admin;

use MLab_Forms\Fields\ChoiceField;
use MLab_Forms\Fields\CheckboxField;
use MLab_Forms\Fields\ContentField;
use MLab_Forms\Fields\DateField;
use MLab_Forms\Fields\FieldType;
use MLab_Forms\Fields\FieldTypes;
use MLab_Forms\Fields\NumberField;
use MLab_Forms\Fields\TextareaField;
use MLab_Forms\Fields\TextField;
use MLab_Forms\Forms\FormPostType;
use MLab_Forms\Forms\Language;
use MLab_Forms\Forms\Presets;
use MLab_Forms\Forms\Templates;
use MLab_Forms\Html;
use MLab_Forms\Mail\Recipients;

defined( 'ABSPATH' ) || exit;

/**
 * Lädt Skripte und Styles des Builders und gibt seinen Container aus.
 *
 * Der Builder schreibt das Schema als JSON in das Textfeld «mlab_form_schema». Ohne JavaScript
 * bleibt dieses Textfeld bedienbar; gespeichert und geprüft wird wie bisher in FormEditor::save().
 */
final class Builder {

	private const HANDLE = 'mlab-forms-builder';

	/**
	 * Skriptdateien in der Reihenfolge ihrer Abhängigkeiten: Handle-Teil => Datei.
	 */
	private const SCRIPTS = array(
		'core'      => 'assets/js/builder/core.js',
		'dom'       => 'assets/js/builder/dom.js',
		'languages' => 'assets/js/builder/languages.js',
		'fields'    => 'assets/js/builder/fields.js',
		'inspector' => 'assets/js/builder/inspector.js',
		'panels'    => 'assets/js/builder/panels.js',
	);

	/**
	 * Hängt das Laden der Assets an.
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Lädt Skripte und Styles, nur beim Bearbeiten eines Formulars.
	 *
	 * @param string $hook_suffix Aktuelle Admin-Seite.
	 */
	public function enqueue( string $hook_suffix ): void {
		$screen = get_current_screen();
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) || ! $screen || FormPostType::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style( self::HANDLE, plugins_url( 'assets/css/builder.css', MLAB_FORMS_FILE ), array(), MLAB_FORMS_VERSION );

		$previous = array();
		foreach ( self::SCRIPTS as $part => $file ) {
			$handle = self::HANDLE . '-' . $part;
			wp_enqueue_script( $handle, plugins_url( $file, MLAB_FORMS_FILE ), $previous, MLAB_FORMS_VERSION, array( 'in_footer' => true ) );
			$previous = array( $handle );
		}
		wp_enqueue_script( self::HANDLE, plugins_url( 'assets/js/builder.js', MLAB_FORMS_FILE ), $previous, MLAB_FORMS_VERSION, array( 'in_footer' => true ) );
	}

	/**
	 * Gibt den Container und das Schema-Textfeld aus und übergibt die Konfiguration an das Skript.
	 *
	 * @param string $json   JSON im Textfeld.
	 * @param bool   $is_new Neues Formular ohne gespeichertes Schema und ohne Entwurf.
	 */
	public static function render( string $json, bool $is_new ): void {
		$textarea = Html::tag(
			'textarea',
			array(
				'id'         => FormEditor::FIELD,
				'name'       => FormEditor::FIELD,
				'rows'       => 30,
				'class'      => 'large-text code mlab-b-json',
				'spellcheck' => 'false',
				'aria-label' => __( 'Formular als JSON', 'mlab-forms' ),
			),
			esc_textarea( $json )
		);

		echo '<div id="mlab-builder" class="mlab-b">' . $textarea . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::tag escapt.
		wp_add_inline_script( self::HANDLE . '-core', self::inline_config( $is_new ), 'before' );
	}

	/**
	 * Das Skript, das window.mlabFormsBuilder setzt.
	 *
	 * @param bool $is_new Neues Formular.
	 */
	private static function inline_config( bool $is_new ): string {
		$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;

		return 'window.mlabFormsBuilder = ' . wp_json_encode( self::config( $is_new ), $flags ) . ';';
	}

	/**
	 * Konfiguration für das Skript.
	 *
	 * @param bool $is_new Neues Formular.
	 * @return array<string, mixed>
	 */
	public static function config( bool $is_new ): array {
		return array(
			'fieldId'      => FormEditor::FIELD,
			'isNew'        => $is_new,
			'presets'      => Presets::all(),
			'starter'      => Presets::starter_keys(),
			'typeList'     => self::type_list(),
			'typeHints'    => self::type_hints(),
			'templates'    => self::templates(),
			'languageList' => self::language_list(),
			'siteLanguage' => Language::normalize( determine_locale() ),
			'defaults'     => array(
				'submitLabel'    => __( 'Senden', 'mlab-forms' ),
				'successMessage' => __( 'Danke! Wir haben deine Nachricht erhalten.', 'mlab-forms' ),
				'recipients'     => implode( ', ', Recipients::defaults() ),
			),
			'preview'      => array(
				'url'    => admin_url( 'admin-ajax.php' ),
				'action' => Preview::ACTION,
				'nonce'  => wp_create_nonce( Preview::ACTION ),
			),
			'texts'        => BuilderTexts::all(),
		);
	}

	/**
	 * Wählbare Sprachen für den Builder.
	 *
	 * @return list<array{code: string, name: string}>
	 */
	private static function language_list(): array {
		$list = array();
		foreach ( BuilderTexts::language_names() as $code => $name ) {
			$list[] = array(
				'code' => (string) $code,
				'name' => $name,
			);
		}

		return $list;
	}

	/**
	 * Feldtypen für «Eigenes Feld».
	 *
	 * @return list<array{key: string, label: string, kind: string, input: bool}>
	 */
	private static function type_list(): array {
		$list = array();
		foreach ( FieldTypes::all() as $key => $type ) {
			$list[] = array(
				'key'   => (string) $key,
				'label' => $type->label(),
				'kind'  => self::kind( (string) $key, $type ),
				'input' => $type->is_input(),
			);
		}

		return $list;
	}

	/**
	 * Art des Feldtyps; sie bestimmt, welche Eigenschaften der Builder anbietet.
	 *
	 * @param string    $key  Typ-Schlüssel.
	 * @param FieldType $type Feldtyp.
	 */
	private static function kind( string $key, FieldType $type ): string {
		return match ( true ) {
			'hidden' === $key              => 'hidden',
			$type instanceof ChoiceField   => 'choice',
			$type instanceof TextareaField => 'textarea',
			$type instanceof NumberField   => 'number',
			$type instanceof DateField     => 'date',
			$type instanceof CheckboxField => 'checkbox',
			$type instanceof ContentField  => 'content',
			$type instanceof TextField     => 'text',
			default                        => 'other',
		};
	}

	/**
	 * Kurze Beschreibung der eingebauten Feldtypen.
	 *
	 * @return array<string, string>
	 */
	private static function type_hints(): array {
		return array(
			'text'           => __( 'Kurzer Text', 'mlab-forms' ),
			'email'          => __( 'Prüft das Format', 'mlab-forms' ),
			'tel'            => __( 'Prüft die Nummer', 'mlab-forms' ),
			'hidden'         => __( 'Nicht sichtbar, mit festem Wert', 'mlab-forms' ),
			'textarea'       => __( 'Mehrzeiliger Text', 'mlab-forms' ),
			'number'         => __( 'Nur Zahlen', 'mlab-forms' ),
			'date'           => __( 'Prüft das Datum', 'mlab-forms' ),
			'select'         => __( 'Eine Option aus einer Liste', 'mlab-forms' ),
			'radio'          => __( 'Eine Option, alle sichtbar', 'mlab-forms' ),
			'checkbox_group' => __( 'Mehrere Optionen wählen', 'mlab-forms' ),
			'checkbox'       => __( 'Einzelnes Häkchen', 'mlab-forms' ),
			'consent'        => __( 'Pflicht-Häkchen mit Datenschutz-Link', 'mlab-forms' ),
			'file'           => __( 'Eine Datei, z. B. Lebenslauf', 'mlab-forms' ),
			'heading'        => __( 'Nur eine Überschrift', 'mlab-forms' ),
			'html'           => __( 'Eigener Text mit Formatierung', 'mlab-forms' ),
		);
	}

	/**
	 * Mitgelieferte Vorlagen samt Schema.
	 *
	 * @return list<array{key: string, title: string, schema: array<string, mixed>|null}>
	 */
	private static function templates(): array {
		$list = array();
		foreach ( Templates::all() as $key => $title ) {
			$list[] = array(
				'key'    => (string) $key,
				'title'  => $title,
				'schema' => Templates::get( (string) $key ),
			);
		}

		return $list;
	}
}
