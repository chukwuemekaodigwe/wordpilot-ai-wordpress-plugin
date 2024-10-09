<?php
/**
 * Route
 * Handles the registration of REST API routes and authentication.
 *
 * @package WordPilot
 * @version 1.0.0
 */

namespace WordPilot;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Route
 */
class Route {

	/**
	 * Holds the posts data.
	 *
	 * @var object
	 */
	private $posts;

	/**
	 * Holds the stats data.
	 *
	 * @var object
	 */
	private $stats;

	/**
	 * Route constructor.
	 */
	public function __construct() {
		$this->posts = new Posts();
		$this->stats = new Stats();
	}

	/**
	 * Initialize the REST API routes.
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Add CORS headers to REST API responses.
	 *
	 * @param mixed $value The response value.
	 * @return mixed The response value.
	 */
	public function add_cors_headers( $value ) {
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS' );
		header( 'Access-Control-Allow-Headers: *' );
		return $value;
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes(): void {
		add_filter( 'rest_pre_serve_request', array( $this, 'add_cors_headers' ) );

		$routes = array(
			'/stats/today'       => array(
				'methods'             => 'GET',
				'callback'            => array( $this->stats, 'get_daily_post_view' ),
				'args'                => array(
					'post_id'   => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'timestamp' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return $this->is_timestamp( $param );
						},
					),
				),
				'permission_callback' => array( $this, 'authenticate_public_request' ),
			),
			'/stats/monthly'     => array(
				'methods'             => 'GET',
				'callback'            => array( $this->stats, 'get_monthly_post_view' ),
				'args'                => array(
					'post_id'   => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'timestamp' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return $this->is_timestamp( $param );
						},
					),
				),
				'permission_callback' => array( $this, 'authenticate_public_request' ),
			),
			'/sites/stats/today' => array(
				'methods'             => 'GET',
				'callback'            => array( $this->stats, 'get_daily_view' ),
				'args'                => array(
					'timestamp' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return $this->is_timestamp( $param );
						},
					),
				),
				'permission_callback' => array( $this, 'authenticate_public_request' ),
			),
			'/sites/stats'       => array(
				'methods'             => 'GET',
				'callback'            => array( $this->stats, 'get_view' ),
				'permission_callback' => array( $this, 'authenticate_public_request' ),
			),
			'/publish'           => array(
				'methods'             => 'POST',
				'callback'            => array( $this->posts, 'create_post' ),
				'args'                => array(
					'post_title'   => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'post_content' => array(
						'required'          => true,
						'sanitize_callback' => 'wp_kses_post',
					),
				),
				'permission_callback' => array( $this, 'authenticate_private_request' ),
			),
			'/update'            => array(
				'methods'             => 'PATCH',
				'callback'            => array( $this->posts, 'update_post' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
				'permission_callback' => array( $this, 'authenticate_private_request' ),
			),
			'/delete'            => array(
				'methods'             => 'DELETE',
				'callback'            => array( $this->posts, 'delete_post' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
				'permission_callback' => array( $this, 'authenticate_private_request' ),
			),
			'/post'              => array(
				'methods'             => 'GET',
				'callback'            => array( $this->posts, 'single_post' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
				'permission_callback' => array( $this, 'authenticate_private_request' ),
			),
			'/categories'        => array(
				'methods'             => 'GET',
				'callback'            => array( $this->posts, 'get_post_categories' ),
				'permission_callback' => array( $this, 'authenticate_public_request' ),
			),
			'/posts'             => array(
				'methods'             => 'GET',
				'callback'            => array( $this->posts, 'get_post_list' ),
				'permission_callback' => array( $this, 'authenticate_public_request' ),
			),
		);

		foreach ( $routes as $route => $args ) {
			register_rest_route( WORDPILOT_REST_URL, $route, $args );
		}
	}

	/**
	 * Authenticate public requests.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response|true Authentication result.
	 */
	public function authenticate_public_request( WP_REST_Request $request ) {
		$auth_header = $request->get_header( 'Wordpilot-Auth' );
		if ( empty( $auth_header ) ) {
			return $this->standard_response( false, 'Authorization header missing', 401 );
		}

		$auth_token   = substr( $auth_header, 7 ); // Remove 'Bearer ' prefix.
		$stored_token = get_option( WORDPILOT_PUBLIC_KEY );

		if ( ! wp_check_password( $auth_token, $stored_token ) ) {
			return $this->standard_response( false, 'Invalid token', 403 );
		}

		return true;
	}

	/**
	 * Authenticate private requests.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response|true Authentication result.
	 */
	public function authenticate_private_request( WP_REST_Request $request ) {
		$auth_header = $request->get_header( 'Wordpilot-Auth' );
		if ( empty( $auth_header ) ) {
			return $this->standard_response( false, 'Authorization header missing', 401 );
		}

		$auth_token   = substr( $auth_header, 7 ); // Remove 'Bearer ' prefix.
		$stored_token = get_option( WORDPILOT_PRIVATE_KEY );

		if ( ! wp_check_password( $auth_token, $stored_token ) ) {
			return $this->standard_response( false, 'Invalid token', 403 );
		}

		return true;
	}

	/**
	 * Display a welcome message.
	 * .
	 *
	 * @return WP_REST_Response The response object.
	 */
	public function welcome_page(): WP_REST_Response {
		return $this->standard_response( true, array( 'message' => 'Hello, Welcome to WordPilot!' ), 200 );
	}

	/**
	 * Generate a standard REST API response.
	 *
	 * @param bool  $status Success status.
	 * @param mixed $data Response data.
	 * @param int   $code HTTP status code.
	 * @return WP_REST_Response The response object.
	 */
	private function standard_response( bool $status, $data, int $code ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'success' => $status,
				'data'    => $data,
			),
			$code
		);
	}

	/**
	 * Validate if a parameter is a timestamp.
	 *
	 * @param mixed $param The parameter to validate.
	 * @return bool True if valid timestamp, otherwise false.
	 */
	private function is_timestamp( $param ): bool {
		return ( (string) (int) $param === (string) $param ) && ( $param <= PHP_INT_MAX ) && ( $param >= ~PHP_INT_MAX );
	}
}
