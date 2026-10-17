<?php
/**
 * Metabox «Aufbewahrung» im Formular-Editor.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Admin;

use MLab_Forms\Entries\Retention;
use MLab_Forms\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Auswahl der Aufbewahrungsdauer. Sie schreibt beim Speichern «settings.retention_days» ins Schema,
 * aber nur, wenn die Auswahl geändert wurde: sonst gilt, was im JSON steht.
 */
final class RetentionBox {

	public const NONCE = 'mlab_forms_retention';

	private const FIELD   = 'mlab_form_retention';
	private const CUSTOM  = 'mlab_form_retention_custom';
	private const INITIAL = 'mlab_form_retention_initial';
	private const MAX     = 3650;

	/**
	 * Auswahlmöglichkeiten: Wert => Beschriftung.
	 *
	 * @return array<int|string, string>
	 */
	public static function presets(): array {
		return array(
			'30'     => __( '30 Tage', 'mlab-forms' ),
			'90'     => __( '90 Tage', 'mlab-forms' ),
			'365'    => __( '1 Jahr', 'mlab-forms' ),
			'730'    => __( '2 Jahre', 'mlab-forms' ),
			'0'      => __( 'Unbegrenzt', 'mlab-forms' ),
			'custom' => __( 'Eigene Anzahl Tage', 'mlab-forms' ),
		);
	}

	/**
	 * Gibt die Box aus.
	 *
	 * @param \WP_Post $post Formular.
	 */
	public function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE );

		$days   = Retention::retention_days( $post->ID );
		$choice = isset( self::presets()[ $days ] ) ? (string) $days : 'custom';

		echo '<p>' . esc_html__( 'So lange bleiben gesendete Anfragen gespeichert. Danach kommen sie in den Papierkorb und werden nach 30 Tagen endgültig gelöscht.', 'mlab-forms' ) . '</p>';
		$hidden = Html::tag(
			'input',
			array(
				'type'  => 'hidden',
				'name'  => self::INITIAL,
				'value' => (string) $days,
			),
			null
		);
		echo $hidden . '<p>' . $this->select( $choice ) . '</p><p id="mlab-retention-custom">' . $this->custom_input( $days ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Html::tag escapt.
		$this->script();
	}

	/**
	 * Tage aus dem Formular, wenn die Auswahl geändert wurde.
	 *
	 * @param array<string, mixed> $data Abgeschickte Daten (ohne Slashes).
	 * @return int|null Tage (0 für unbegrenzt) oder null bei unveränderter oder ungültiger Auswahl.
	 */
	public static function days_from_request( array $data ): ?int {
		$choice = is_string( $data[ self::FIELD ] ?? null ) ? $data[ self::FIELD ] : '';
		if ( 'custom' === $choice ) {
			$custom = $data[ self::CUSTOM ] ?? '';
			$days   = is_numeric( $custom ) ? max( 1, min( self::MAX, (int) $custom ) ) : null;
		} else {
			$days = isset( self::presets()[ $choice ] ) ? (int) $choice : null;
		}

		$initial = isset( $data[ self::INITIAL ] ) && is_numeric( $data[ self::INITIAL ] ) ? (int) $data[ self::INITIAL ] : null;

		return $days === $initial ? null : $days;
	}

	/**
	 * Übernimmt eine geänderte Auswahl ins Schema.
	 *
	 * @param array<string, mixed> $schema Dekodiertes Schema.
	 * @param array<string, mixed> $data   Abgeschickte Daten.
	 * @return array<string, mixed>
	 */
	public static function merge( array $schema, array $data ): array {
		$days = self::days_from_request( $data );
		if ( null !== $days && ( ! isset( $schema['settings'] ) || is_array( $schema['settings'] ) ) ) {
			$schema['settings']['retention_days'] = $days;
		}

		return $schema;
	}

	/**
	 * Auswahlliste.
	 *
	 * @param string $choice Gewählter Wert.
	 */
	private function select( string $choice ): string {
		$options = '';
		foreach ( self::presets() as $value => $label ) {
			$options .= Html::tag(
				'option',
				array(
					'value'    => (string) $value,
					'selected' => (string) $value === $choice,
				),
				esc_html( $label )
			);
		}

		return Html::tag(
			'select',
			array(
				'id'    => self::FIELD,
				'name'  => self::FIELD,
				'class' => 'widefat',
			),
			$options
		);
	}

	/**
	 * Feld für eigene Anzahl Tage.
	 *
	 * @param int $days Aktuelle Tage.
	 */
	private function custom_input( int $days ): string {
		$label = Html::tag( 'label', array( 'for' => self::CUSTOM ), esc_html__( 'Anzahl Tage', 'mlab-forms' ) );
		$input = Html::tag(
			'input',
			array(
				'type'  => 'number',
				'id'    => self::CUSTOM,
				'name'  => self::CUSTOM,
				'min'   => 1,
				'max'   => self::MAX,
				'value' => $days > 0 ? (string) $days : '',
				'class' => 'small-text',
			),
			null
		);

		return $label . ' ' . $input;
	}

	/**
	 * Blendet das Feld für eigene Tage nur bei «Eigene Anzahl Tage» ein.
	 */
	private function script(): void {
		?>
		<script>
			( function () {
				var select = document.getElementById( <?php echo wp_json_encode( self::FIELD ); ?> );
				var custom = document.getElementById( 'mlab-retention-custom' );
				function toggle() { custom.hidden = select.value !== 'custom'; }
				select.addEventListener( 'change', toggle );
				toggle();
			}() );
		</script>
		<?php
	}
}
