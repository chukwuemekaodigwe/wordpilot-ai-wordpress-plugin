<?php
/**
 * WordPilot Plugin
 *
 * Miscellaneous functions.
 * These are miscellaneous functions working with other important classes of the plugin
 * namely: setup, and posts.
 * It houses the dashboard presentation, plugin connection, and reconnection to
 * WordPilot AI.
 *
 * @package WordPilot
 * @version 1.0.0
 * @since 1.0.0
 */

namespace WordPilot;

require_once 'constants.php';
/**
 * Class Misc
 *
 * @package WordPilot
 */
class Misc {

	/**
	 * Constructor.
	 * This registers the AJAX callback for verifying
	 * the WordPilot API key for the app integration.
	 */
	public function __construct() {
		add_action( 'wp_ajax_wordpilot_verify_key', array( $this, 'wordpilot_verify_key' ) );
		add_action( 'wp_ajax_nopriv_wordpilot_verify_key', array( $this, 'wordpilot_verify_key' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'wordpilot_auth_scripts' ), 10, 2 );
		add_action(
			'admin_init',
			function () {
				if ( isset( $_POST['action'] ) && -1 !== $_POST['action'] ) {
					// check_admin_referer('search_nonce_action');
					$post = new WordPilot_Posts_Table();
					$post->bulk_action_handler();
				}
			}
		);
	}

	/**
	 * Display the WordPilot dashboard page.
	 * toogling between the auth form and the posts table.
	 */
	public function wordpilot_dashboard_page() {
		// Check if the reconnect action is set and verify the nonce for security.
		if ( isset( $_GET['reconnect'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'wordpilot_reconnect_nonce' ) && current_user_can( 'manage_options' ) ) {
			$this->sanitize_content( $this->wordpilot_auth_form( 'reconnecting' ) );
			return;
		}

		// Check if the auth key exists, if not display the auth form.
		if ( get_option( 'wordpilot_auth_key' ) !== false ) {
			$this->sanitize_content( $this->display_ai_posts_page() );
		} else {
			$this->sanitize_content( $this->wordpilot_auth_form() );
		}
	}

	/**
	 * Sanitizes WordPilot Plugin outputs
	 *
	 * @param string $content The content to be sanitized.
	 */
	private function sanitize_content( $content ) {
		/**
		 * These are the main codes of the plugin
		 * and sanitzation at this level breaks the functionality of the code
		 * Therefore they have been sanitize at the individual methods and elements
		 * Sanitizing them to avoid XSS attacks
		 * and other security issues
		 * has been done on the individual methods and elements,
		 */
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.XSS.EscapeOutput
		echo $content;
	}

	/**
	 * Generate a WordPress admin notice.
	 *
	 * @param string $msg    The message to display.
	 * @param string $status The status of the notice (success, error, warning, info).
	 * @return string The HTML for the admin notice.
	 */
	public function wordpilot_notice( $msg, $status ) {
		ob_start();
		?>
		<div class="notice notice-<?php echo esc_attr( $status ); ?>">
			<p><?php echo esc_html( $msg ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Display the post Table.
	 * This is post sent into this website
	 * from Wordpilot dashboard
	 *
	 * @return string The HTML for the AI posts page.
	 */
	public function display_ai_posts_page() {
		$wordpilot_post_table = new WordPilot_Posts_Table();
		$wordpilot_post_table->prepare_items();
		ob_start();
		?>
		<div class="wrap">
			<h3 class="wp-heading-inline"><?php esc_html( 'WordPilot AI - SEO Writing Assistant'); ?></h3>
			<form method="post" action="" id="bulk_wordpilot_post_table">
			<!--	<input type="hidden" name="action" value="bulk_wordpilot_post_table"> - Set the bulk action handler -->
				<input type="hidden" name="page" value="ai_posts_list_table"> <!-- Keep track of the page -->
				<?php
				// Display the table.
				$wordpilot_post_table->display();
				?>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}


	/**
	 * Display the auth form
	 * this is form used to activate this plugin
	 * It accepts the verification code from Wordpilot Dashboard
	 * then queries and connects the two dashboards
	 * used also for reconnection if link is broken
	 *
	 * @param string $reconnect this is an optional parameter.
	 * used to initize reconnection.
	 * @return string $html
	 */
	public function wordpilot_auth_form( $reconnect = '' ) {
		ob_start();
		?>
		<div class="wrap" style="padding: 10px">
			<h3 style="font-size: large; text-transform: capitalize">
			<img src="" />
				<?php echo ! empty( $reconnect ) ? esc_html__( 'Reconnect to WordPilot', 'wordpilot-ai-seo-writing-assistant' ) : esc_html__( 'Activate Plugin', 'wordpilot-ai-seo-writing-assistant' ); ?>
			</h3>

			<div class="notice notice-info">
				<p>
					<strong><?php esc_html_e( 'Thanks for using WordPilot', 'wordpilot-ai-seo-writing-assistant' ); ?></strong>
				</p>
			</div>
			<div id="comparison_result" class="notice" hidden>
				<p></p>
			</div>
			<form method="post" action="" id="wordpilot-auth-form" style="display:inline-block !important;">
				<label for="field_value"><?php esc_html_e( 'WordPilot Activation Key:', 'wordpilot-ai-seo-writing-assistant' ); ?></label>
				<input style="width:500px; display: block; margin: 7px 0px" type="text" id="field_value" name="field_value" value="" placeholder="<?php esc_attr_e( 'Enter the site verification key from WordPilot', 'wordpilot-ai-seo-writing-assistant' ); ?>">
				<span>
					<input type="submit" name="submit" class="button-primary" value="<?php esc_attr_e( 'Activate', 'wordpilot-ai-seo-writing-assistant' ); ?>">
					<span id="my-spinner" class="spinner is-active" style="display:none;"></span>
					<?php if ( ! empty( $reconnect ) ) : ?>
						<input type="hidden" name="reconnect" value="1">
					<?php endif; ?>
				</span>
			</form>
			<div style="font-style: italic">
				<p><?php esc_html_e( 'Proceed to', 'wordpilot-ai-seo-writing-assistant' ); ?>
					<a href="<?php echo esc_url( 'https://wordpilot.ai' ); ?>" target="_blank">
						<?php esc_html_e( 'WordPilot Dashboard', 'wordpilot-ai-seo-writing-assistant' ); ?>
					</a>
					<?php esc_html_e( 'if you have no verification key', 'wordpilot-ai-seo-writing-assistant' ); ?>
				</p>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}


	/**
	 * Verifying the WordPilot API key.
	 * Processes the Auth form
	 * Submitted through Ajax
	 */
	public function wordpilot_verify_key() {
		check_ajax_referer( 'wordpilot_verify_key', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'wordpilot-ai-seo-writing-assistant' ) ) );
		}
		// Sanitize input.
		$field_value = isset( $_POST['field_value'] ) ? sanitize_text_field( wp_unslash( $_POST['field_value'] ) ) : '';

		if ( empty( $field_value ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Verification key is missing or invalid', 'wordpilot-ai-seo-writing-assistant' ) ) );
		}

		$current_user = wp_get_current_user();

		if ( 0 === $current_user->ID ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No user found', 'wordpilot-ai-seo-writing-assistant' ) ) );
		}
		$user_id  = $current_user->ID;
		$response = wp_remote_post(
			esc_url_raw( WORDPILOT_BASE_URL . '/wordpress/verify-key' ),
			array(
				'body'    => wp_json_encode(
					array(
						'verification_key' => $field_value,
						'site'             => site_url(),
						'user_id'          => $user_id,
					)
				),
				'headers' => array( 'Content-Type' => 'application/json' ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$body   = wp_remote_retrieve_body( $response );
		$result = json_decode( $body, true );

		if ( isset( $result['success'] ) && true === $result['success'] ) {
			$result = isset( $result['data'] ) ? $result['data'] : null;
			$auth   = strrev( $field_value );
			if ( isset( $result['comparison'] ) && $result['comparison'] === $auth ) {
				if ( isset( $result['keys']['public'] ) && isset( $result['keys']['private'] ) ) {
					$public_key  = sanitize_text_field( $result['keys']['public'] );
					$private_key = sanitize_text_field( $result['keys']['private'] );
					update_option( WORDPILOT_PUBLIC_KEY, wp_hash_password( $public_key ) );
					update_option( WORDPILOT_PRIVATE_KEY, wp_hash_password( $private_key ) );
				} else {
					wp_send_json_error( array( 'message' => esc_html__( 'Key data is missing or invalid.', 'wordpilot-ai-seo-writing-assistant' ) ) );
					return;
				}

				wp_send_json_success(
					array(
						'message' => ( isset( $_POST['reconnect'] ) && ! empty( $_POST['reconnect'] ) )
							? esc_html__( 'Reconnected successfully! You will be redirected to the dashboard shortly.', 'wordpilot-ai-seo-writing-assistant' )
							: esc_html__( 'Activation successful! You will be redirected to the dashboard shortly.', 'wordpilot-ai-seo-writing-assistant' ),
					)
				);
			} else {
				wp_send_json_error( array( 'message' => esc_html__( 'Invalid activation key.', 'wordpilot-ai-seo-writing-assistant' ) ) );
			}
		} else {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid Token, token has been used or expired', 'wordpilot-ai-seo-writing-assistant' ) ) );
		}
	}


	/**
	 * Enqueue plugin scripts and styles.
	 */
	public function wordpilot_auth_scripts() {
		// Enqueue the auth script.
		$enque = wp_enqueue_script(
			'wordpilot-auth-script',
			plugins_url( 'js/wordpilot-auth.js', __FILE__ ),
			array(), // Dependencies.
			WORDPILOT_VERSION, // Version number.
			true // Load in footer.
		);

		// Localize the script with new data.
		$script_data = array(
			'ajax_url'      => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'wordpilot_verify_key' ),
			'dashboard_url' => WORDPILOT_PLUGIN_URL,
		);
		wp_localize_script( 'wordpilot-auth-script', 'wordpilotData', $script_data );

		/**
		 * Enqueue styles if needed.
		 * wp_enqueue_style(
		 * 'wordpilot-style',
		 * plugins_url( 'css/wordpilot-style.css', WORDPILOT_PLUGIN_DIR ),
		 * array(),
		 * WORDPILOT_VERSION
		 * );
		 */
	}
}
?>