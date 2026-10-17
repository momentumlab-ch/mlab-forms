<?php
/**
 * Auswahlfelder: select, radio, checkbox_group.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Fields;

use MLab_Forms\Forms\Form;
use MLab_Forms\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Feld mit Optionen. Die Optionen stehen nach dem Auflösen in «choices»
 * (siehe Forms\OptionsResolver), jeweils mit value, label und group.
 */
class ChoiceField extends FieldType {

	/**
	 * Konstruktor.
	 *
	 * @param string $type  select, radio oder checkbox_group.
	 * @param string $label Name des Typs.
	 */
	public function __construct( private string $type, private string $label ) {
	}

	/**
	 * Name des Typs.
	 */
	public function label(): string {
		return $this->label;
	}

	/**
	 * Optionen oder eine Optionsquelle sind Pflicht.
	 *
	 * @param array<string, mixed> $field Feld.
	 * @return list<string>
	 */
	public function schema_errors( array $field ): array {
		if ( empty( $field['options'] ) && empty( $field['options_source'] ) ) {
			return array( __( 'Das Feld braucht «options» oder «options_source».', 'mlab-forms' ) );
		}

		return self::default_errors( $field );
	}

	/**
	 * Startwert: Wert aus der URL, sonst die Standardzeile, sonst «default» des Felds, sonst leer.
	 *
	 * @param array<string, mixed> $field   Feld mit aufgelösten «choices».
	 * @param string|null          $prefill Wert aus der URL.
	 * @return string|list<string>
	 */
	public function initial_value( array $field, ?string $prefill ) {
		if ( null !== $prefill && '' !== $prefill ) {
			return $prefill;
		}
		foreach ( $field['choices'] ?? array() as $choice ) {
			if ( ! empty( $choice['default'] ) ) {
				return 'checkbox_group' === $this->type ? array( $choice['value'] ) : $choice['value'];
			}
		}

		return parent::initial_value( $field, null );
	}

	/**
	 * Höchstens eine Zeile darf «default: true» tragen.
	 *
	 * @param array<string, mixed> $field Feld.
	 * @return list<string>
	 */
	private static function default_errors( array $field ): array {
		$options = array_merge( (array) ( $field['options'] ?? array() ), (array) ( $field['options_source']['prepend'] ?? array() ) );
		$count   = 0;
		foreach ( $options as $option ) {
			if ( is_array( $option ) && isset( $option['default'] ) ) {
				if ( ! is_bool( $option['default'] ) ) {
					return array( __( '«default» einer Option muss true oder false sein.', 'mlab-forms' ) );
				}
				$count += (int) $option['default'];
			}
		}

		return $count > 1 ? array( __( 'Nur eine Option darf «default» sein.', 'mlab-forms' ) ) : array();
	}

	/**
	 * Einzelwert oder Liste bei checkbox_group.
	 *
	 * @param mixed                $raw   Rohwert.
	 * @param array<string, mixed> $field Feld.
	 * @return string|list<string>
	 */
	public function sanitize( $raw, array $field ) {
		if ( 'checkbox_group' === $this->type ) {
			return array_values( array_filter( array_map( 'sanitize_text_field', array_filter( (array) $raw, 'is_scalar' ) ), static fn( string $value ) => '' !== $value ) );
		}

		return is_scalar( $raw ) ? sanitize_text_field( (string) $raw ) : '';
	}

	/**
	 * Nur angebotene Werte. Mit «prefill» ist auch ein vorausgewählter fremder Wert erlaubt.
	 *
	 * @param mixed                $value Wert.
	 * @param array<string, mixed> $field Feld.
	 */
	public function validate( $value, array $field ): ?string {
		$error = parent::validate( $value, $field );
		if ( $error || $this->is_empty( $value ) || Form::prefill_enabled( $field ) ) {
			return $error;
		}

		$allowed = array_column( $field['choices'] ?? array(), 'value' );
		if ( array_diff( (array) $value, $allowed ) ) {
			return __( 'Bitte wähle eine der angebotenen Möglichkeiten.', 'mlab-forms' );
		}

		return null;
	}

	/**
	 * Typen radio und checkbox_group als fieldset mit legend.
	 *
	 * @param array<string, mixed> $field Feld.
	 * @param mixed                $value Wert.
	 * @param string|null          $error Fehler.
	 */
	public function render( array $field, $value, ?string $error ): string {
		if ( 'select' === $this->type ) {
			return parent::render( $field, $value, $error );
		}

		[ $notes, $aria ] = $this->notes( $field, $error );

		$fieldset = Html::tag(
			'fieldset',
			array(
				'class' => 'mlab-form__group',
				'id'    => (string) $field['html_id'],
			) + $aria,
			$this->label_html( $field, 'legend' ) . $this->control( $field, $value, array() ) . $notes
		);

		return $this->wrap( $field, $fieldset, (bool) $error );
	}

	/**
	 * Typ select mit optgroups oder eine Liste von radio/checkbox.
	 *
	 * @param array<string, mixed>       $field Feld.
	 * @param mixed                      $value Wert.
	 * @param array<string, scalar|null> $aria  aria-Attribute.
	 */
	protected function control( array $field, $value, array $aria ): string {
		$choices = $field['choices'] ?? array();

		return 'select' === $this->type
			? Html::tag( 'select', $this->base_attributes( $field, $aria ) + array( 'placeholder' => null ), $this->options_html( $field, $choices, (string) $value ) )
			: $this->inputs_html( $field, $choices, (array) $value );
	}

	/**
	 * Die option-Elemente, nach «group» in optgroups.
	 *
	 * @param array<string, mixed>                                                     $field   Feld.
	 * @param list<array{value: string, label: string, group: string, default?: bool}> $choices Optionen.
	 * @param string                                                                   $value   Gewählter Wert.
	 */
	private function options_html( array $field, array $choices, string $value ): string {
		$html  = '';
		$empty = self::empty_label( $field );
		if ( null !== $empty ) {
			$html .= Html::tag( 'option', array( 'value' => '' ), esc_html( $empty ) );
		}

		$groups = array();
		foreach ( $choices as $choice ) {
			$groups[ $choice['group'] ][] = Html::tag(
				'option',
				array(
					'value'    => $choice['value'],
					'selected' => $choice['value'] === $value,
				),
				esc_html( $choice['label'] )
			);
		}

		foreach ( $groups as $group => $options ) {
			$html .= '' === $group
				? implode( '', $options )
				: Html::tag( 'optgroup', array( 'label' => (string) $group ), implode( '', $options ) );
		}

		return $html;
	}

	/**
	 * Text der leeren ersten Zeile; null, wenn «empty_first» ausgeschaltet ist.
	 *
	 * @param array<string, mixed> $field Feld.
	 */
	private static function empty_label( array $field ): ?string {
		if ( false === ( $field['empty_first'] ?? true ) ) {
			return null;
		}

		return '' !== (string) ( $field['placeholder'] ?? '' ) ? (string) $field['placeholder'] : __( 'Bitte wählen', 'mlab-forms' );
	}

	/**
	 * Liste aus radio- oder checkbox-Elementen.
	 *
	 * @param array<string, mixed>                                                     $field   Feld.
	 * @param list<array{value: string, label: string, group: string, default?: bool}> $choices Optionen.
	 * @param list<string>                                                             $values  Gewählte Werte.
	 */
	private function inputs_html( array $field, array $choices, array $values ): string {
		$type = 'radio' === $this->type ? 'radio' : 'checkbox';
		$name = 'mlab[' . $field['id'] . ']' . ( 'checkbox' === $type ? '[]' : '' );
		$html = '';

		foreach ( $choices as $index => $choice ) {
			$input = Html::tag(
				'input',
				array(
					'type'     => $type,
					'id'       => $field['html_id'] . '-' . $index,
					'name'     => $name,
					'value'    => $choice['value'],
					'checked'  => in_array( $choice['value'], $values, true ),
					'required' => 'radio' === $type && ! empty( $field['required'] ),
				),
				null
			);
			$html .= Html::tag( 'label', array( 'class' => 'mlab-form__choice' ), $input . ' ' . esc_html( $choice['label'] ) );
		}

		return $html;
	}
}
