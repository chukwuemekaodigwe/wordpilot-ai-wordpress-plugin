<?php
/**
 * This file handles post-related functionalities
 * for the WordPilot plugin.
 *
 * @package WordPilot
 * @since 1.0.0
 */

namespace WordPilot;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Posts
 *
 * Handles post-related functionality for WordPilot.
 *
 * @package WordPilot
 */
class Posts {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'add_meta_tags_to_head' ) );
	}

	/**
	 * Create a new post.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function create_post( WP_REST_Request $request ): WP_REST_Response {
		$post_data = $this->sanitize_post_data( $request->get_json_params() );

		if ( empty( $post_data['post_title'] ) || empty( $post_data['post_content'] ) ) {
			return $this->standard_response( 'invalid_input', 'Post title and content are required', 400 );
		}

		$this->load_wp_media_files();

		$post_args = array(
			'post_title'   => $post_data['post_title'],
			'post_content' => $post_data['post_content'],
			'post_status'  => ! empty( $post_data['post_status'] ) ? $post_data['post_status'] : 'draft',
			'post_type'    => 'post',
			'post_name'    => $this->generate_slug( $post_data['post_title'] ),
			'post_date'    => ( isset( $post_data['post_status'] ) && $post_data['post_status'] == 'future' ) ? gmdate( 'Y-m-d H:i:s', strtotime( $post_data['date_of_publish'] ) ) : '',
			'post_excerpt' => isset( $post_data['meta_desc'] ) ? $post_data['meta_desc'] : '',
		);

		$post_id = wp_insert_post( $post_args, true );

		if ( is_wp_error( $post_id ) ) {
			return $this->standard_response( 'post_creation_failed', $post_id->get_error_message(), 500 );
		}

		$this->set_post_metadata( $post_id, $post_data );

		return $this->standard_response( true, array( 'post_id' => $post_id ), 201 );
	}

	/**
	 * Update an existing post.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function update_post( WP_REST_Request $request ): WP_REST_Response {
		$post_id   = (int) $request->get_param( 'post_id' );
		$post_data = $this->sanitize_post_data( $request->get_json_params() );

		if ( ! $post_id || empty( $post_data['post_title'] ) || empty( $post_data['post_content'] ) ) {
			return $this->standard_response( 'invalid_input', 'Post ID, title, and content are required', 400 );
		}

		$post_args = array(
			'ID'           => $post_id,
			'post_title'   => $post_data['post_title'],
			'post_content' => $post_data['post_content'],
			'post_status'  => $post_data['post_status'] ?? get_post_status( $post_id ),
			'post_name'    => $this->generate_slug( $post_data['post_title'] ),
		);

		$updated = wp_update_post( $post_args, true );

		if ( is_wp_error( $updated ) ) {
			return $this->standard_response( 'post_update_failed', $updated->get_error_message(), 500 );
		}

		$this->set_post_metadata( $post_id, $post_data );

		return $this->standard_response( true, array( 'post_id' => $updated ), 200 );
	}

	/**
	 * Get a single post.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function single_post( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request->get_param( 'post_id' );

		if ( ! $post_id ) {
			return $this->standard_response( 'invalid_input', 'Post ID is required', 400 );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return $this->standard_response( 'post_not_found', 'Post not found', 404 );
		}

		return $this->standard_response( true, array( 'post' => $post ), 200 );
	}

	/**
	 * Delete a post.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function delete_post( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request->get_param( 'post_id' );

		if ( ! $post_id ) {
			return $this->standard_response( 'invalid_input', 'Post ID is required', 400 );
		}

		$deleted = wp_delete_post( $post_id, true );

		if ( ! $deleted ) {
			return $this->standard_response( 'post_deletion_failed', 'Failed to delete the post', 500 );
		}

		return $this->standard_response( true, 'Post deleted successfully', 200 );
	}

	/**
	 * Get post categories.
	 *
	 * @return WP_REST_Response The response object.
	 */
	public function get_post_categories(): WP_REST_Response {
		$categories = get_categories( array( 'hide_empty' => false ) );
		return $this->standard_response( true, $categories, 200 );
	}

	/**
	 * Sanitize post data.
	 *
	 * @param array $data The post data to sanitize.
	 * @return array The sanitized post data.
	 */
	private function sanitize_post_data( array $data ): array {
		$sanitized = array();
		foreach ( $data as $key => $value ) {
			switch ( $key ) {
				case 'post_title':
				case 'meta_desc':
					$sanitized[ $key ] = sanitize_text_field( $value );
					break;
				case 'post_content':
					$sanitized[ $key ] = $this->allow_youtube_iframes( $value );
					break;
				case 'post_status':
					$sanitized[ $key ] = in_array( $value, array( 'publish', 'draft', 'pending', 'future' ), true ) ? $value : 'draft';
					break;
				case 'post_categories':
					$sanitized[ $key ] = array_map( 'intval', (array) $value );
					break;
				case 'tags':
					$sanitized[ $key ] = array_map( 'sanitize_text_field', (array) $value );
					break;
				default:
					$sanitized[ $key ] = $value;
			}
		}
		return $sanitized;
	}

	/**
	 * Generate a unique slug for a post.
	 *
	 * @param string $title The post title.
	 * @return string The generated slug.
	 */
	private function generate_slug( string $title ): string {
		$slug          = sanitize_title( $title );
		$original_slug = $slug;
		$count         = 1;

		while ( get_page_by_path( $slug, OBJECT, 'post' ) ) {
			$slug = $original_slug . '-' . ( $count++ );
		}
		return $slug;
	}

	/**
	 * Set post metadata.
	 *
	 * @param int   $post_id   The post ID.
	 * @param array $post_data The post data.
	 */
	private function set_post_metadata( int $post_id, array $post_data ): void {
		update_post_meta( $post_id, WORDPILOT_POST_META, time() );

		if ( ! empty( $post_data['post_categories'] ) ) {
			wp_set_post_categories( $post_id, $post_data['post_categories'] );
		}

		if ( ! empty( $post_data['meta_desc'] ) ) {
			update_post_meta( $post_id, 'wordpilot_meta_desc', sanitize_text_field( $post_data['meta_desc'] ) );
		}

		if ( ! empty( $post_data['post_additional_keyword'] ) ) {
			update_post_meta( $post_id, 'wordpilot_focus_keyword', sanitize_text_field( $post_data['post_additional_keyword'] ) );
		}

		if ( ! empty( $post_data['tags'] ) ) {
			wp_set_post_tags( $post_id, $post_data['tags'] );
		}

		if ( ! empty( $post_data['feature_image'] ) ) {
			$this->set_featured_image( $post_id, $post_data['feature_image'] );
		}

		if ( ! empty( $post_data['post_url_style'] ) ) {
			update_post_meta( $post_id, 'wordpilot_custom_url_type', $this->set_custom_post_permalink( $post_id, $post_data['post_url_style'] ) );
		}

		if ( ! empty( $post_data['post_author'] ) ) {
			$this->set_post_author( $post_id, $post_data['post_author'] );
		}
	}

	/**
	 * Get URL format for a post.
	 *
	 * @param string  $post_link The post link.
	 * @param WP_Post $post      The post object.
	 * @return string The formatted URL.
	 */
	public function get_url_format( $post_link, $post ) {
		$url_type = get_post_meta( $post->ID, 'wordpilot_custom_url_type', true );
		if ( ! $url_type ) {
			return $post_link;
		}
		return home_url( '/wordpilot/' . $url_type );
	}

	/**
	 * Set custom post permalink.
	 *
	 * @param int    $post_id    The post ID.
	 * @param string $post_style The post URL style.
	 * @return string The custom permalink.
	 */
	public function set_custom_post_permalink( $post_id, $post_style ) {
		$post = get_post( $post_id );

		switch ( $post_style ) {
			case 'plain':
				return '/?p=' . $post->ID;
			case 'post_title':
				return $post->post_name;
			case 'date_and_post_title':
				return get_the_date( 'Y/m/d', $post ) . '/' . $post->post_name;
			case 'month_and_post_title':
				return get_the_date( 'Y/m', $post ) . '/' . $post->post_name;
			case 'category_and_post_title':
				$categories = get_the_category( $post->ID );
				$category   = ! empty( $categories ) ? $categories[0]->slug : '';
				return $category ? $category . '/' . $post->post_name : $post->post_name;
			default:
				return get_permalink( $post->ID );
		}
	}

	/**
	 * Set post author.
	 *
	 * @param int $post_id   The post ID.
	 * @param int $author_id The author ID.
	 * @return bool True if successful, false otherwise.
	 */
	private function set_post_author( $post_id, $author_id ) {
		$post   = get_post( $post_id );
		$author = get_user_by( 'ID', $author_id );

		if ( $post && $author ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_author' => $author_id,
				)
			);
			return true;
		}

		return false;
	}

	/**
	 * Set featured image for a post.
	 *
	 * @param int    $post_id   The post ID.
	 * @param string $image_url The image URL.
	 * @return WP_REST_Response|null The response object or null.
	 */
	private function set_featured_image( int $post_id, string $image_url ) {
		$this->load_wp_media_files();

		$image_id = media_sideload_image( $image_url, $post_id, '', 'id' );

		if ( ! is_wp_error( $image_id ) ) {
			set_post_thumbnail( $post_id, $image_id );
			return null;
		}

		return $this->standard_response( 'image_upload_error', $image_id->get_error_message(), 400 );
	}

	/**
	 * Add meta tags to head.
	 */
	public function add_meta_tags_to_head(): void {
		if ( is_single() ) {
			global $post;
			$meta_desc    = get_post_meta( $post->ID, 'wordpilot_meta_desc', true );
			$meta_keyword = get_post_meta( $post->ID, 'wordpilot_focus_keyword', true );

			if ( $meta_desc ) {
				echo '<meta name="description" content="' . esc_attr( $meta_desc ) . '">' . "\n";
			}
			if ( $meta_keyword ) {
				echo '<meta name="keywords" content="' . esc_attr( $meta_keyword ) . '">' . "\n";
			}
		}
	}

	/**
	 * Allow YouTube iframe embeds in post content.
	 *
	 * @param string $content The post content.
	 * @return string The filtered content with YouTube iframes allowed.
	 */
	public function allow_youtube_iframes( string $content ): string {
		// Define allowed HTML tags and attributes.
		$allowed_tags = wp_kses_allowed_html( 'post' );

		// Add iframe to allowed tags.
		$allowed_tags['iframe'] = array(
			'src'             => true,
			'width'           => true,
			'height'          => true,
			'frameborder'     => true,
			'allow'           => true,
			'allowfullscreen' => true,
			'class'           => true,
		);

		// Apply kses with the new allowed tags.
		return wp_kses( $content, $allowed_tags );
	}
	/**
	 * Create a standard response.
	 *
	 * @param bool  $status Whether the request was successful.
	 * @param mixed $data The data to return.
	 * @param int   $code The HTTP response code.
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

	private function load_wp_media_files() {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
	}
}
