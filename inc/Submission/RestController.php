<?php
/**
 * REST-Endpunkt für das Absenden mit JavaScript.
 *
 * @package MLab_Forms
 */

namespace MLab_Forms\Submission;

defined( 'ABSPATH' ) || exit;

/**
 * POST mlab-forms/v1/submit
 */
final class RestController {

	public const NAMESPACE = 'mlab-forms/v1';

	/**
	 * Hängt die Route an.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registriert die Route. Sie ist öffentlich: Gäste senden ohne Login, der Schutz läuft über SpamGuard.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/submit',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'submit' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'form_id' => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
				),
			)
		);
	}

	/**
	 * Verarbeitet die Absendung.
	 *
	 * @param \WP_REST_Request $request Anfrage.
	 */
	public function submit( \WP_REST_Request $request ): \WP_REST_Response {
		$result = SubmissionHandler::handle( (int) $request['form_id'], $request->get_params() );

		return new \WP_REST_Response( $result->to_array(), $result->http_status() );
	}
}
