<?php
/**
 * Optionen für Auswahlfelder.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Löst «options» und «options_source» eines Felds zu einer Liste von Optionen auf.
 *
 * Eine Optionsquelle liefert ihre Optionen, «prepend» kommt davor. Liefert die Quelle
 * nichts (z. B. weil das Angebots-Plugin fehlt), gelten die festen «options».
 */
final class OptionsResolver {

	/**
	 * Optionen des Felds.
	 *
	 * @param array<string, mixed> $field Feld aus dem Schema.
	 * @return list<array{value: string, label: string, group: string}>
	 */
	public static function resolve( array $field ): array {
		$static = self::normalize( $field['options'] ?? array() );
		$source = $field['options_source'] ?? null;

		if ( ! is_array( $source ) ) {
			return $static;
		}

		$from_source = self::from_provider( $source, $field );
		if ( ! $from_source ) {
			return $static;
		}

		return self::unique( array_merge( self::normalize( $source['prepend'] ?? array() ), $from_source ) );
	}

	/**
	 * Registrierte Optionsquellen.
	 *
	 * @return array<string, callable>
	 */
	public static function providers(): array {
		/**
		 * Optionsquellen ergänzen. Eine Quelle bekommt die Angaben aus «options_source» und das Feld
		 * und liefert eine Liste von Optionen (value, label, group).
		 *
		 * @param array<string, callable> $providers Name => Callback.
		 */
		return (array) apply_filters( 'mlab_forms_options_providers', array( 'post_type' => array( self::class, 'post_type_provider' ) ) );
	}

	/**
	 * Eingebaute Quelle: veröffentlichte Beiträge eines Post-Typs, Titel als Wert.
	 *
	 * «groups» gruppiert nach einem Meta-Feld («meta:_afa_season») oder einer Taxonomie («taxonomy:saison»).
	 *
	 * @param array<string, mixed> $source Angaben aus «options_source».
	 * @return list<array{value: string, label: string, group: string}>
	 */
	public static function post_type_provider( array $source ): array {
		$post_type = (string) ( $source['post_type'] ?? '' );
		if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
			return array();
		}

		// Meta und Terme nur vorladen, wenn «groups» sie braucht.
		$groups = (string) ( $source['groups'] ?? '' );
		$posts  = get_posts(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'numberposts'            => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts -- Auswahllisten bleiben klein.
				'orderby'                => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'suppress_filters'       => false,
				'update_post_meta_cache' => str_starts_with( $groups, 'meta:' ),
				'update_post_term_cache' => str_starts_with( $groups, 'taxonomy:' ),
			)
		);

		$options = array();
		foreach ( $posts as $post ) {
			$title     = html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' );
			$options[] = array(
				'value' => $title,
				'label' => $title,
				'group' => self::group_of( $post->ID, $groups ),
			);
		}

		return $options;
	}

	/**
	 * Ruft die Quelle auf.
	 *
	 * @param array<string, mixed> $source Angaben aus «options_source».
	 * @param array<string, mixed> $field  Feld.
	 * @return list<array{value: string, label: string, group: string}>
	 */
	private static function from_provider( array $source, array $field ): array {
		$provider = self::providers()[ (string) ( $source['provider'] ?? '' ) ] ?? null;

		return is_callable( $provider ) ? self::normalize( (array) call_user_func( $provider, $source, $field ) ) : array();
	}

	/**
	 * Gruppe eines Beitrags.
	 *
	 * @param int    $post_id Beitrag.
	 * @param string $spec    «meta:schlüssel» oder «taxonomy:name».
	 */
	private static function group_of( int $post_id, string $spec ): string {
		[ $kind, $key ] = array_pad( explode( ':', $spec, 2 ), 2, '' );

		if ( 'meta' === $kind && '' !== $key ) {
			return (string) get_post_meta( $post_id, $key, true );
		}
		if ( 'taxonomy' === $kind && '' !== $key ) {
			$terms = get_the_terms( $post_id, $key );
			return is_array( $terms ) && $terms ? $terms[0]->name : '';
		}

		return '';
	}

	/**
	 * Vereinheitlicht Optionen: Strings oder Arrays mit value, label, group.
	 *
	 * @param mixed $options Optionen.
	 * @return list<array{value: string, label: string, group: string}>
	 */
	private static function normalize( $options ): array {
		$result = array();
		foreach ( is_array( $options ) ? $options : array() as $option ) {
			$value = is_array( $option ) ? (string) ( $option['value'] ?? '' ) : (string) $option;
			if ( '' === $value ) {
				continue;
			}
			$result[] = array(
				'value' => $value,
				'label' => is_array( $option ) && isset( $option['label'] ) ? (string) $option['label'] : $value,
				'group' => is_array( $option ) ? (string) ( $option['group'] ?? '' ) : '',
			);
		}

		return $result;
	}

	/**
	 * Entfernt doppelte Werte, der erste gewinnt.
	 *
	 * @param list<array{value: string, label: string, group: string}> $options Optionen.
	 * @return list<array{value: string, label: string, group: string}>
	 */
	private static function unique( array $options ): array {
		$seen = array();

		return array_values(
			array_filter(
				$options,
				static function ( array $option ) use ( &$seen ) {
					if ( isset( $seen[ $option['value'] ] ) ) {
						return false;
					}
					$seen[ $option['value'] ] = true;
					return true;
				}
			)
		);
	}
}
