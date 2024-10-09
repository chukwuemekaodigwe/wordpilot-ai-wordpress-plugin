<?php
/**
 * WordPilot Custom Posts Table.
 *
 * This file contains the Posts_Table class for managing posts displayed
 * in the WordPilot admin area.
 *
 * @package WordPilot
 * @since 1.0.0
 */

namespace WordPilot;

use WP_List_Table;
use WP_Query;

require_once 'constants.php';

// Ensure WP_List_Table class is available.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Posts_Table
 *
 * Custom table for managing posts in the WordPilot admin area.
 *
 * @package WordPilot
 */
class WordPilot_Posts_Table extends WP_List_Table {


	/**
	 * Constructor for the class.
	 *
	 * Sets up the table with its various options.
	 */
	public function __construct() {
		add_action( 'wp_ajax_wordpilot_action_handler', array( $this, 'bulk_action_handler' ) );
		add_action( 'wp_ajax_nopriv_wordpilot_action_handler', array( $this, 'bulk_action_handler' ) );
		parent::__construct(
			array(
				'singular' => __( 'WordPilot Post', 'sp' ),
				'plural'   => __( 'WordPilot Posts', 'sp' ),
				'ajax'     => false,
			)
		);
	}

	/**
	 * Define the columns for the table.
	 *
	 * @return array Columns for the table.
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'title'      => __( 'Title' ),
			'author'     => __( 'Author' ),
			'categories' => __( 'Categories' ),
			'tags'       => __( 'Tags' ),
			'status'     => __( 'Status' ),
			'date'       => __( 'Date' ),
		);
	}


	/**
	 * Define the sortable columns for the table.
	 *
	 * @return array Sortable columns for the table.
	 */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'title'  => array( 'title', true ),
			'date'   => array( 'date', false ),
			'status' => array( 'status', false ),
		);
		return $sortable_columns;
	}


	/**
	 * Prepare the items for display.
	 */
	public function prepare_items() {
		if ( isset( $_REQUEST['search_nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['search_nonce'] ) ), 'search_nonce_action' ) ) {
			wp_die( esc_html__( 'Nonce verification failed', 'wordpilot-ai-seo-writing-assistant' ) );
		}

		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Handle sorting.
		$orderby = ! empty( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'date';
		$order   = ! empty( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'desc';

		// Handle search.
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

		$query_args = array(
			'post_type'      => 'post',
			'meta_key'       => WORDPILOT_POST_META, // Meta key to filter AI-generated posts.
			'posts_per_page' => 20,
			'paged'          => $this->get_pagenum(),
			'orderby'        => $orderby,
			'order'          => $order,
			's'              => $search,
		);

		$query = new WP_Query( $query_args );

		$this->items = $query->posts;
		$total_items = $query->found_posts;

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => 20,
			)
		);
	}

	/**
	 * Defining the columns for the post table
	 *
	 * @param object $item The current item.
	 * @param string $column_name The column name.
	 * @return string The content for the column.
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'title':
				return $this->column_title( $item );
			case 'author':
				return get_the_author_meta( 'display_name', $item->post_author );
			case 'date':
				return get_the_date( '', $item );
			case 'status':
				$status = get_post_status( $item->ID );
				if ( 'publish' === $status ) {
					return 'Published';
				} elseif ( 'future' === $status ) {
					return 'Scheduled';
				} else {
					return ucwords( $status );
				}
			case 'categories':
				$categories    = get_the_category( $item->ID );
				$category_list = array();
				foreach ( $categories as $category ) {
					$category_list[] = $category->name;
				}
				return implode( ', ', $category_list );
			case 'tags':
				$tags     = get_the_tags( $item->ID );
				$tag_list = array();
				if ( $tags ) {
					foreach ( $tags as $tag ) {
						$tag_list[] = $tag->name;
					}
				}
				return implode( ', ', $tag_list );
			default:
				return ( $item ); // For debugging.
		}
	}

	/**
	 * Structure of the title column
	 * Defined to have view, edit and delete
	 * sub options
	 *
	 * @param object $item The current item.
	 * @return string The HTML for the title column.
	 */
	public function column_title( $item ) {
		$title           = '<strong style="margin-bottom: 0px;">' . esc_html( $item->post_title ) . '</strong>';
		$edit_link       = get_edit_post_link( $item->ID );
		$delete_link     = get_delete_post_link( $item->ID );
		$view_link       = get_permalink( $item->ID );
		$quick_edit_link = get_edit_post_link( $item->ID, 'quick-edit' );

		$actions = array(
			'view' => sprintf( '<a href="%s">%s</a>', esc_url( $view_link ), __( 'View', 'wordpilot-ai-seo-writing-assistant' ) ),
		);

		if ( current_user_can( 'edit_post', $item->ID ) ) {
			$actions['edit'] = sprintf( '<a href="%s">%s</a>', esc_url( $edit_link ), __( 'Edit', 'wordpilot-ai-seo-writing-assistant' ) );
		}

		if ( current_user_can( 'delete_post', $item->ID ) ) {
			$actions['delete'] = sprintf( '<a href="%s">%s</a>', esc_url( $delete_link ), __( 'Delete', 'wordpilot-ai-seo-writing-assistant' ) );
		}

		return sprintf( '%1$s %2$s', $title, $this->row_actions( $actions ) );
	}

	/**
	 * Define the checkbox for selecting posts
	 * for bulk action
	 *
	 * @param object $item The current item.
	 * @return string The HTML for the checkbox column.
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="post[]" value="%s" />', $item->ID );
	}

	/**
	 * Bulk actions
	 * Bulk actions supported by the plugin
	 */
	public function get_bulk_actions() {
		$actions = array(
			'publish' => __( 'Publish', 'wordpilot-ai-seo-writing-assistant' ),
			'draft'   => __( 'Move to Draft', 'wordpilot-ai-seo-writing-assistant' ),
			'trash'   => __( 'Move to Trash', 'wordpilot-ai-seo-writing-assistant' ),
			'delete'  => __( 'Delete', 'wordpilot-ai-seo-writing-assistant' ),
		);
		return $actions;
	}

	/**
	 * Bulk Action handler
	 * This method handles the bulk actions
	 * It is protected with a nonce
	 */
	public function bulk_action_handler() {
		// Verify nonce.
		if ( isset( $_POST['search_nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['search_nonce'] ) ), 'search_nonce_action' ) ) {
			wp_die( esc_html__( 'Nonce verification failed', 'wordpilot-ai-seo-writing-assistant' ) );
		}

		// Check if user has permission to edit posts.
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		// Get the selected action (either from 'action' or 'action2').
		$action = isset( $_POST['action'] ) && $_POST['action'] !== '-1'
			? sanitize_text_field( wp_unslash( $_POST['action'] ) )
			: ( isset( $_POST['action2'] ) && $_POST['action2'] !== '-1'
				? sanitize_text_field( wp_unslash( $_POST['action2'] ) )
				: null );

		$post_ids = isset( $_POST['post'] ) ? array_map( 'intval', wp_unslash( $_POST['post'] ) ) : array();

		if ( empty( $post_ids ) || empty( $action ) ) {
			return;
		}

		foreach ( $post_ids as $post_id ) {
			switch ( $action ) {
				case 'trash':
					wp_trash_post( $post_id );
					break;
				case 'delete':
					wp_delete_post( $post_id );
					break;
				case 'publish':
					wp_update_post(
						array(
							'ID'          => $post_id,
							'post_status' => 'publish',
						)
					);
					break;
				case 'draft':
					wp_update_post(
						array(
							'ID'          => $post_id,
							'post_status' => 'draft',
						)
					);
					break;
			}
		}

		// wp_send_json_success('Bulk action completed successfully.');
	}

	/**
	 * Render bulk action dropdown.
	 *
	 * @param string $which Position of the dropdown ('top' or 'bottom').
	 */
	public function bulk_actions( $which = '' ) {
		if ( 'top' === $which ) {
			?>
			<div class="alignleft actions bulkactions">
				<?php
				// Generate a nonce.
				wp_nonce_field( 'search_nonce_action', 'search_nonce' );
				?>
				<label for="bulk-action-selector-<?php echo esc_attr( $which ); ?>" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'wordpilot-ai-seo-writing-assistant' ); ?></label>
				<select name="action" id="bulk-action-selector-<?php echo esc_attr( $which ); ?>">
					<option value="-1"><?php esc_html_e( 'Bulk Actions', 'wordpilot-ai-seo-writing-assistant' ); ?></option>
					<?php
					foreach ( $this->get_bulk_actions() as $name => $title ) {
						printf( '<option value="%s">%s</option>', esc_attr( $name ), esc_html( $title ) );
					}
					?>
				</select>
				<?php submit_button( __( 'Apply', 'wordpilot-ai-seo-writing-assistant' ), 'action', '', false, array( 'id' => 'wordpilot-bulk-action' ) ); ?>
				<span id="my-spinner" class="spinner is-active" style="display: none;"></span>
			</div>
			<script>
				document.addEventListener('DOMContentLoaded', function() {
					var applyButtons = document.querySelectorAll('#wordpilot-bulk-action');
					applyButtons.forEach(function(button) {
						button.addEventListener('click', function(e) {
							var form = button.closest('form');
							var bulkAction = form.querySelector('select[name="action"]').value;
							if (bulkAction != '-1') {
								form.querySelector('#my-spinner').style.display = 'inline-block';
							}
						});
					});

					document.addEventListener('ajaxComplete', function() {
						var spinners = document.querySelectorAll('.spinner');
						spinners.forEach(function(spinner) {
							spinner.style.display = 'none';
						});
					});
				});
			</script>
			<?php
		}
	}

	/**
	 * Add a search box to the table.
	 *
	 * @param string $text     The button text.
	 * @param string $input_id The input field ID.
	 */
	public function search_box( $text, $input_id ) {
		if ( isset( $_REQUEST['search_nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['search_nonce'] ) ), 'search_nonce_action' ) ) {
			wp_die( esc_html__( 'Nonce verification failed', 'wordpilot-ai-seo-writing-assistant' ) );
		}

		if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) {
			return;
		}

		$input_id = $input_id . '-search-input';
		if ( ! empty( $_REQUEST['orderby'] ) ) {
			echo '<input type="hidden" name="orderby" value="' . esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) ) . '" />';
		}
		if ( ! empty( $_REQUEST['order'] ) ) {
			echo '<input type="hidden" name="order" value="' . esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) ) . '" />';
		}
		if ( ! empty( $_REQUEST['post_mime_type'] ) ) {
			echo '<input type="hidden" name="post_mime_type" value="' . esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['post_mime_type'] ) ) ) . '" />';
		}
		if ( ! empty( $_REQUEST['detached'] ) ) {
			echo '<input type="hidden" name="detached" value="' . esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['detached'] ) ) ) . '" />';
		}
		?>
		<p class="search-box">
			<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $text ); ?>:</label>
			<input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="s" value="<?php _admin_search_query(); ?>" />
			<?php submit_button( esc_html( $text ), 'button', false, false, array( 'id' => 'search-submit' ) ); ?>
		</p>
		<?php
	}


	/**
	 * Display the custom posts table.
	 * Wordpilot posts table
	 */
	public function display() {
		$this->search_box( 'Search Posts', 'wordpilot_search' );
		parent::display();
	}
}
