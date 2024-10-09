<?php
/**
 * WordPilot Setup Class
 *
 * @package WordPilot
 */

namespace WordPilot;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once 'constants.php';

/**
 * Setup class for WordPilot plugin.
 */
class Setup {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_redirect' ) );
		add_action( 'admin_footer-plugins.php', array( $this, 'deactivation_alert' ) );
		add_action( 'wp_ajax_wordpilot_deactivate', array( $this, 'handle_deactivation' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'localize_script' ) );
	}

	/**
	 * Localize script for AJAX.
	 */
	public function localize_script() {
		wp_localize_script(
			'jquery',
			'wordpilotAjax',
			array(
				'security' => wp_create_nonce( 'wordpilot_deactivate' ),
			)
		);
	}

	/**
	 * Activation hook.
	 */
	public static function activate() {
		register_post_meta(
			'post',
			WORDPILOT_POST_META,
			array(
				'type'         => 'string',
				'description'  => 'Post from WordPilot',
				'single'       => true,
				'show_in_rest' => false,
			)
		);

		self::create_views_table();

		set_transient( 'wordpilot_install_notice', 'Plugin installed successfully', 5 );
		flush_rewrite_rules();
		add_option( 'wordpilot_activation_redirect', true );
	}

	/**
	 * Deactivation hook.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'wordpilot_daily_aggregation' );
		wp_clear_scheduled_hook( 'wordpilot_monthly_aggregation' );
		delete_transient( 'wordpilot_cache_key' );
		self::clear_plugin_cache();
		update_option( 'wordpilot_plugin_active', false );
	}

	/**
	 * Clear plugin cache.
	 */
	private static function clear_plugin_cache() {
		global $wpdb;

		$transient_pattern         = '_transient_wordpilot_%';
		$transient_timeout_pattern = '_transient_timeout_wordpilot_%';

		// Cache the results of queries to avoid direct calls multiple times.
		$transient_cache_key = 'wordpilot_transients_cache';
		$transient_cache     = wp_cache_get( $transient_cache_key, 'options' );

		if ( false === $transient_cache ) {
			$transient_cache = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
					$transient_pattern,
					$transient_timeout_pattern
				)
			);
			wp_cache_set( $transient_cache_key, $transient_cache, 'options' );
		}

		foreach ( $transient_cache as $transient ) {
			delete_option( $transient->option_name );
		}
	}

	/**
	 * Uninstall hook.
	 */
	public static function uninstall() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Remove options.
		if ( get_option( 'wordpilot_auth_key' ) !== false ) {
			delete_option( 'wordpilot_auth_key' );
		}

		if ( registered_meta_key_exists( 'post', WORDPILOT_POST_META ) ) {
			unregister_post_meta( 'post', WORDPILOT_POST_META );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'wordpilot_post_views';

		// Cache the DROP TABLE query.
		$drop_table_cache_key = 'wordpilot_drop_table_cache';
		$drop_table_cache     = wp_cache_get( $drop_table_cache_key, 'options' );

		if ( false === $drop_table_cache ) {
			// Drop the custom table used by the plugin.
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
				$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
			}

			// Set cache for the drop table query.
			wp_cache_set( $drop_table_cache_key, true, 'options', HOUR_IN_SECONDS );
		}

		// Set a transient to display the uninstall notice.
		set_transient( 'wordpilot_uninstall_notice', 'Plugin uninstalled successfully.', 5 );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_menu_page(
			'WordPilot AI - SEO Writing Assistant',
			'WordPilot',
			'manage_options',
			WORDPILOT_PLUGIN_NAME,
			array( $this, 'render_dashboard_page' ),
			'dashicons-admin-generic',
			6
		);
	}

	/**
	 * Render dashboard page.
	 */
	public function render_dashboard_page() {
		$dash = new Misc();
		$dash->wordpilot_dashboard_page();
	}

	/**
	 * Handle redirect after activation.
	 */
	public function handle_redirect() {
		if ( get_option( 'wordpilot_activation_redirect', false ) ) {
			delete_option( 'wordpilot_activation_redirect' );
			wp_safe_redirect( WORDPILOT_PLUGIN_URL );
			exit;
		}
	}

	/**
	 * Create views table.
	 */
	public static function create_views_table() {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'wordpilot_post_views';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            view_date date NOT NULL,
            view_count int NOT NULL DEFAULT 1,
            visitor_hash varchar(64) NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_view (post_id, view_date, visitor_hash),
            KEY post_date (post_id, view_date)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Site authorization.
	 *
	 * @param string $auth_key Authorization key.
	 */
	public function site_authorization( $auth_key ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$auth_key      = sanitize_text_field( $auth_key );
		$option_key    = 'wordpilot_auth_key';
		$current_value = get_option( $option_key );

		if ( $auth_key !== $current_value ) {
			update_option( $option_key, $auth_key );
			set_transient( 'wordpilot_activation_notice', 'WordPilot plugin activated successfully!', 5 );
		} else {
			set_transient( 'wordpilot_activation_notice', 'WordPilot plugin activation failed!', 5 );
		}

		add_action( 'admin_footer', array( $this, 'refresh_dashboard' ) );
	}

	/**
	 * Refresh dashboard.
	 */
	public function refresh_dashboard() {
		?>
		<script>
			location.reload();
		</script>
		<?php
	}

	/**
	 * Deactivation alert.
	 */
	public function deactivation_alert() {
		?>
		<script>
			document.addEventListener('DOMContentLoaded', function() {
				var wordpilotDeactivateLink = document.querySelector('tr[data-plugin="wordpilot/wordpilot.php"] .deactivate a');
				if (wordpilotDeactivateLink) {
					wordpilotDeactivateLink.addEventListener('click', function(e) {
						e.preventDefault();
						if (confirm('Do you really want to deactivate the plugin? This will disconnect your site from WordPilot dashboard')) {
							var xhr = new XMLHttpRequest();
							xhr.open('POST', ajaxurl, true);
							xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
							xhr.onload = function() {
								if (xhr.status === 200) {
									alert('Plugin deactivated successfully.');
									location.reload();
								} else {
									alert('Error deactivating plugin. Please try again.');
								}
							};
							xhr.send('action=wordpilot_deactivate&security=' + wordpilotAjax.nonce);
						}
					});
				}
			});
		</script>
		<?php
	}

	/**
	 * Handle deactivation.
	 */
	public function handle_deactivation() {
		check_ajax_referer( 'wordpilot_deactivate', 'security' );

		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( 'You do not have permission to deactivate plugins.' );
		}

		self::deactivate();
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_send_json_success( 'Plugin deactivated successfully.' );
	}

	/**
	 * Add action links.
	 *
	 * @param array $actions Existing action links.
	 * @return array Modified action links.
	 */
	public static function add_action_links( $actions ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $actions;
		}

		$reconnect_url = add_query_arg(
			array(
				'page'      => WORDPILOT_PLUGIN_NAME,
				'reconnect' => 1,
				'_wpnonce'  => wp_create_nonce( 'wordpilot_reconnect_nonce' ),
			),
			admin_url( 'admin.php' )
		);

		$mylinks = array(
			'<a href="' . esc_url( $reconnect_url ) . '">' . esc_html__( 'Reconnect', 'wordpilot-ai-seo-writing-assistant' ) . '</a>',
		);

		return array_merge( $mylinks, $actions );
	}
}
