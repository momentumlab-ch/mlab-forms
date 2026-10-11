<?php
/**
 * Laden und Speichern von Formularen.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Liest und schreibt das Schema in Post-Meta, inklusive Revisionen.
 */
final class FormRepository {

	public const META_SCHEMA    = '_mlab_form_schema';
	public const META_REVISIONS = '_mlab_form_schema_revisions';
	public const MAX_REVISIONS  = 10;

	/**
	 * Formular nach ID.
	 *
	 * @param int  $id                   Post-ID.
	 * @param bool $published_only Nur veröffentlichte Formulare.
	 */
	public static function find( int $id, bool $published_only = true ): ?Form {
		$post = get_post( $id );
		if ( ! $post || FormPostType::POST_TYPE !== $post->post_type ) {
			return null;
		}
		if ( $published_only && 'publish' !== $post->post_status ) {
			return null;
		}

		$schema = self::schema( $id );

		return $schema ? new Form( $id, get_the_title( $post ), $schema ) : null;
	}

	/**
	 * Veröffentlichtes Formular nach Slug.
	 *
	 * @param string $slug Slug.
	 */
	public static function find_by_slug( string $slug ): ?Form {
		$post = get_page_by_path( $slug, OBJECT, FormPostType::POST_TYPE );

		return $post ? self::find( $post->ID ) : null;
	}

	/**
	 * Gespeichertes Schema oder null.
	 *
	 * @param int $id Post-ID.
	 * @return array<string, mixed>|null
	 */
	public static function schema( int $id ): ?array {
		$json   = (string) get_post_meta( $id, self::META_SCHEMA, true );
		$schema = '' === $json ? null : json_decode( $json, true );

		return is_array( $schema ) ? $schema : null;
	}

	/**
	 * Prüft und speichert ein Schema. Das bisherige wird zur Revision.
	 *
	 * @param int                  $id     Post-ID.
	 * @param array<string, mixed> $schema Schema.
	 * @return list<array{path: string, message: string}> Fehler; leer bei Erfolg.
	 */
	public static function save( int $id, array $schema ): array {
		$errors = SchemaValidator::validate( $schema );
		if ( $errors ) {
			return $errors;
		}

		$previous = (string) get_post_meta( $id, self::META_SCHEMA, true );
		$json     = (string) wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( $previous === $json ) {
			return array();
		}

		if ( '' !== $previous ) {
			self::push_revision( $id, $previous );
		}
		// update_post_meta entfernt Backslashes, deshalb vorher maskieren.
		update_post_meta( $id, self::META_SCHEMA, wp_slash( $json ) );

		return array();
	}

	/**
	 * Revisionen, neueste zuerst.
	 *
	 * @param int $id Post-ID.
	 * @return list<array{schema: string, saved_at: int, user_id: int}>
	 */
	public static function revisions( int $id ): array {
		$revisions = get_post_meta( $id, self::META_REVISIONS, true );

		return is_array( $revisions ) ? array_values( $revisions ) : array();
	}

	/**
	 * Stellt eine Revision wieder her. Das aktuelle Schema wird selbst zur Revision.
	 *
	 * @param int $id    Post-ID.
	 * @param int $index Index aus revisions().
	 * @return bool Erfolg.
	 */
	public static function restore( int $id, int $index ): bool {
		$revision = self::revisions( $id )[ $index ] ?? null;
		$schema   = $revision ? json_decode( $revision['schema'], true ) : null;

		return is_array( $schema ) && array() === self::save( $id, $schema );
	}

	/**
	 * Legt das alte Schema vorne in die Revisionen.
	 *
	 * @param int    $id   Post-ID.
	 * @param string $json Bisheriges Schema.
	 */
	private static function push_revision( int $id, string $json ): void {
		$revisions = self::revisions( $id );
		array_unshift(
			$revisions,
			array(
				'schema'   => $json,
				'saved_at' => time(),
				'user_id'  => get_current_user_id(),
			)
		);

		update_post_meta( $id, self::META_REVISIONS, wp_slash( array_slice( $revisions, 0, self::MAX_REVISIONS ) ) );
	}
}
