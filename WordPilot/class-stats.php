<?php
/**
 * WordPilot Stats Class
 *
 * This file contains the code for the page view and visit counting
 * and reporting on wordpilot dashboard.
 * It initializes hooks, actions, and other plugin components.
 *
 * @package WordPilot
 * @version 1.0.0
 * @since 1.0.0
 */

namespace WordPilot;

use WP_REST_Request;
use WP_REST_Response;

require_once 'constants.php';

/**
 * Class Stats
 *
 * Handles tracking and aggregating post view statistics.
 */
class Stats {

	/**
	 * Table name for storing post views.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Constructor to initialize class and set up hooks.
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'wordpilot_post_views';

		add_action( 'wp_head', array( $this, 'track_post_views' ) );
		add_action( 'wp_loaded', array( $this, 'schedule_aggregation_tasks' ) );
		add_action( 'wordpilot_daily_aggregation', array( $this, 'daily_aggregation' ) );
		add_action( 'wordpilot_monthly_aggregation', array( $this, 'monthly_aggregation' ) );
	}

	/**
	 * Track post views and store them in the database.
	 */
	public function track_post_views() {
		if ( ! is_singular() || ! get_post_meta( get_the_ID(), 'wordpilot_post', true ) ) {
			return;
		}

		if ( $this->is_bot() ) {
			return;
		}

		global $wpdb;
		$post_id      = get_the_ID();
		$today        = current_time( 'Y-m-d' );
		$visitor_hash = $this->get_visitor_hash();

		// Cache key for existing view check.
		$cache_key     = "post_view_{$post_id}_{$today}_{$visitor_hash}";
		$existing_view = wp_cache_get( $cache_key, 'post_views' );

		// If it's not in the cache, query the database.
		if ( false === $existing_view ) {

			// Use $wpdb->prepare to safely query the database.
			$existing_view = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}wordpilot_post_views WHERE post_id = %d AND view_date = %s AND visitor_hash = %s",
					$post_id,
					$today,
					$visitor_hash
				)
			);
			// Set the cache for the next time.
			wp_cache_set( $cache_key, $existing_view, 'post_views', HOUR_IN_SECONDS );
		}

		if ( ! $existing_view ) {
			$wpdb->insert(
				$this->table_name,
				array(
					'post_id'      => $post_id,
					'view_date'    => $today,
					'view_count'   => 1,
					'visitor_hash' => $visitor_hash,
				),
				array( '%d', '%s', '%d', '%s' )
			);

			// Clear cache after the insert.
			wp_cache_delete( $cache_key, 'post_views' );
		}
	}

	/**
	 * Get a hash representing the visitor to track unique views.
	 *
	 * @return string Hash representing the visitor.
	 */
	private function get_visitor_hash() {
		$ip         = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		return hash( 'sha256', $ip . $user_agent . wp_salt() );
	}

	/**
	 * Check if the user agent belongs to a bot.
	 *
	 * @return bool
	 */
	private function is_bot() {
		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return true;
		}

		$user_agent  = strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) );
		$bot_strings = array( 'bot', 'crawler', 'spider', 'slurp', 'googlebot' );

		foreach ( $bot_strings as $bot ) {
			if ( strpos( $user_agent, $bot ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Schedule daily and monthly aggregation tasks.
	 */
	public function schedule_aggregation_tasks() {
		if ( ! wp_next_scheduled( 'wordpilot_daily_aggregation' ) ) {
			wp_schedule_event( time(), 'daily', 'wordpilot_daily_aggregation' );
		}
		if ( ! wp_next_scheduled( 'wordpilot_monthly_aggregation' ) ) {
			wp_schedule_event( time(), 'monthly', 'wordpilot_monthly_aggregation' );
		}
	}

	/**
	 * Perform daily aggregation of post views.
	 */
	public function daily_aggregation() {
		global $wpdb;
		$yesterday = gmdate( 'Y-m-d', strtotime( '-1 day' ) );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} pm
				JOIN {$wpdb->prefix}wordpilot_post_views wv ON pm.post_id = wv.post_id
				SET pm.meta_value = wv.view_count
				WHERE pm.meta_key = 'post_views_daily'
				AND wv.view_date = %s",
				$yesterday
			)
		);

		// Clear the cache for daily views after aggregation.
		wp_cache_delete( 'daily_views', 'post_views' );
	}

	/**
	 * Perform monthly aggregation of post views.
	 */
	public function monthly_aggregation() {
		global $wpdb;
		$last_month = gmdate( 'Y-m', strtotime( '-1 month' ) );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} pm
				JOIN (
					SELECT post_id, SUM(view_count) as monthly_views
					FROM {$wpdb->prefix}wordpilot_post_views
					WHERE view_date LIKE %s
					GROUP BY post_id
				) wv ON pm.post_id = wv.post_id
				SET pm.meta_value = wv.monthly_views
				WHERE pm.meta_key = 'post_views_monthly'",
				$last_month . '%'
			)
		);

		$three_months_ago = gmdate( 'Y-m-d', strtotime( '-3 months' ) );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}wordpilot_post_views WHERE view_date < %s",
				$three_months_ago
			)
		);

		// Clear the cache for monthly views after aggregation.
		wp_cache_delete( 'monthly_views', 'post_views' );
	}

	/**
	 * Get unique views for a post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return int
	 */
	public function get_unique_views( $post_id, $start_date, $end_date ) {
		global $wpdb;

		$cache_key    = "unique_views_{$post_id}_{$start_date}_{$end_date}";
		$unique_views = wp_cache_get( $cache_key, 'post_views' );

		if ( false === $unique_views ) {
			$unique_views = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT visitor_hash) FROM {$wpdb->prefix}wordpilot_post_views 
					WHERE post_id = %d AND view_date BETWEEN %s AND %s",
					$post_id,
					$start_date,
					$end_date
				)
			);

			wp_cache_set( $cache_key, $unique_views, 'post_views', HOUR_IN_SECONDS );
		}

		return (int) $unique_views;
	}

	/**
	 * Get monthly post view data.
	 *
	 * @param WP_REST_Request $request REST API request object.
	 * @return WP_REST_Response
	 */
	public function get_monthly_post_view( WP_REST_Request $request ) {
		$post_data = $request->get_params();

		if ( empty( $post_data['post_id'] ) ) {
			return $this->standard_response( false, 'Post ID is required', 400 );
		}

		if ( empty( $post_data['timestamp'] ) ) {
			return $this->standard_response( false, 'Timestamp is required', 400 );
		}

		$post_id   = (int) $post_data['post_id'];
		$timestamp = (int) $post_data['timestamp'];

		if ( ! is_numeric( $timestamp ) || $timestamp <= 0 ) {
			return $this->standard_response( false, 'Invalid timestamp provided', 400 );
		}

		$month_start = gmdate( 'Y-m-01', $timestamp );
		$month_end   = gmdate( 'Y-m-t', strtotime( $month_start ) );

		$result = $this->get_unique_views( $post_id, $month_start, $month_end );

		return $this->standard_response( true, array( 'result' => $result ), 200 );
	}

	/**
	 * Get daily post view data.
	 *
	 * @param WP_REST_Request $request REST API request object.
	 * @return WP_REST_Response
	 */
	public function get_daily_post_view( WP_REST_Request $request ) {
		$post_data = $request->get_params();

		if ( empty( $post_data['post_id'] ) ) {
			return $this->standard_response( false, 'Post ID is required', 400 );
		}

		if ( empty( $post_data['timestamp'] ) ) {
			return $this->standard_response( false, 'Timestamp is required', 400 );
		}

		$post_id   = (int) $post_data['post_id'];
		$timestamp = (int) $post_data['timestamp'];

		if ( ! is_numeric( $timestamp ) || $timestamp <= 0 ) {
			return $this->standard_response( false, 'Invalid timestamp provided', 400 );
		}

		$start_date = gmdate( 'Y-m-d', $timestamp );
		$end_date   = $start_date;

		$result = $this->get_unique_views( $post_id, $start_date, $end_date );

		return $this->standard_response( true, array( 'result' => $result ), 200 );
	}

	/**
	 * Get unique views for the entire site.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return int
	 */
	public function get_universal_unique_views( $start_date, $end_date ) {
		global $wpdb;

		$cache_key = "universal_views_{$start_date}_{$end_date}";
		$views     = wp_cache_get( $cache_key, 'post_views' );

		if ( false === $views ) {
			$views = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT visitor_hash) FROM {$wpdb->prefix}wordpilot_post_views 
					WHERE view_date BETWEEN %s AND %s",
					$start_date,
					$end_date
				)
			);

			wp_cache_set( $cache_key, $views, 'post_views', HOUR_IN_SECONDS );
		}

		return (int) $views;
	}

	/**
	 * Get single month record.
	 *
	 * @return int
	 */
	public function get_monthly_view() {
		$timestamp   = time();
		$month_start = gmdate( 'Y-m-01', $timestamp );
		$month_end   = gmdate( 'Y-m-t', strtotime( $month_start ) );

		$result = $this->get_universal_unique_views( $month_start, $month_end );

		return $result;
	}

	/**
	 * Get single day records.
	 *
	 * @return int
	 */
	public function get_daily_view() {
		$timestamp  = time();
		$date       = gmdate( 'Y-m-d', $timestamp );
		$date_start = $date . ' 00:00:00';
		$date_end   = $date . ' 23:59:59';

		$result = $this->get_universal_unique_views( $date_start, $date_end );

		return $result;
	}

	/**
	 * Get daily and monthly view data.
	 *
	 * @return WP_REST_Response
	 */
	public function get_view() {
		$result = array(
			'today'   => $this->get_daily_view(),
			'monthly' => $this->get_monthly_view(),
		);

		return $this->standard_response( true, $result, 200 );
	}

	/**
	 * Standard REST API response format.
	 *
	 * @param bool  $success Success flag.
	 * @param mixed $data Data to return.
	 * @param int   $status HTTP status code.
	 * @return WP_REST_Response
	 */
	private function standard_response( $success, $data, $status ) {
		return new WP_REST_Response(
			array(
				'success' => $success,
				'data'    => $data,
			),
			$status
		);
	}
}
