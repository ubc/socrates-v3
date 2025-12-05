<?php
/**
 * Socrates Links List Table
 *
 * @package UBC\CTLT
 * @since 3.5.0
 */

namespace UBC\CTLT;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// WP_List_Table is not loaded automatically so we need to load it.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Socrates_Links_List_Table class.
 *
 * This class is responsible for displaying the links in a paginated table.
 *
 * @since 3.5.0
 */
class Socrates_Links_List_Table extends \WP_List_Table {

	/**
	 * Constructor.
	 *
	 * @since 3.5.0
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'link',
				'plural'   => 'links',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get a list of columns.
	 *
	 * @since 3.5.0
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'cb'           => '<input type="checkbox" />',
			'link_name'    => esc_html__( 'Name', 'socrates-v3' ),
			'link_url'     => esc_html__( 'URL', 'socrates-v3' ),
			'categories'   => esc_html__( 'Categories', 'socrates-v3' ),
			'link_rating'  => esc_html__( 'Rating', 'socrates-v3' ),
			'link_visible' => esc_html__( 'Visible', 'socrates-v3' ),
		);

		/**
		 * Filters the columns shown in the links list table.
		 *
		 * Allows plugins to add custom columns to the links table.
		 *
		 * @since 3.5.0
		 *
		 * @param array $columns An array of column names.
		 */
		return apply_filters( 'socrates_links_table_columns', $columns );
	}

	/**
	 * Get a list of sortable columns.
	 *
	 * @since 3.5.0
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'link_name'    => array( 'name', false ),
			'link_url'     => array( 'url', false ),
			'link_rating'  => array( 'rating', false ),
			'link_visible' => array( 'visible', false ),
		);
	}

	/**
	 * Get a list of bulk actions.
	 *
	 * @since 3.5.0
	 * @return array
	 */
	protected function get_bulk_actions() {
		$actions = array();

		/**
		 * Filters the bulk actions available for links in the list table.
		 *
		 * Allows plugins to add custom bulk actions (e.g., "Index with RAG").
		 *
		 * @since 3.5.0
		 *
		 * @param array $actions An array of bulk action key => label pairs.
		 */
		return apply_filters( 'socrates_links_table_bulk_actions', $actions );
	}

	/**
	 * Default column rendering.
	 *
	 * @param object $item The item being rendered.
	 * @param string $column_name The name of the column.
	 * @return mixed
	 */
	protected function column_default( $item, $column_name ) {
		/**
		 * Filters the output of custom columns in the links list table.
		 *
		 * Allows plugins to render their own custom column content.
		 * If the filter returns a non-null value, that value will be used
		 * and the default column rendering will be bypassed.
		 *
		 * @since 3.5.0
		 *
		 * @param mixed  $output      The column output. Default null.
		 * @param object $item        The current link item (bookmark object).
		 * @param string $column_name The name of the column.
		 */
		$output = apply_filters( 'socrates_links_table_column_' . $column_name, null, $item, $column_name );

		if ( null !== $output ) {
			return $output;
		}

		// Default column rendering.
		switch ( $column_name ) {
			case 'link_url':
				return esc_html( $item->link_url );
			case 'link_rating':
				return absint( $item->link_rating );
			case 'link_visible':
				return 'Y' === $item->link_visible ? esc_html__( 'Yes', 'socrates-v3' ) : esc_html__( 'No', 'socrates-v3' );
			default:
				return print_r( $item, true ); // Should not happen.
		}
	}

	/**
	 * Render the checkbox column.
	 *
	 * @param object $item The item being rendered.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="link_id[]" value="%s" />',
			absint( $item->link_id )
		);
	}

	/**
	 * Render the name column with actions.
	 *
	 * @param object $item The item being rendered.
	 * @return string
	 */
	protected function column_link_name( $item ) {
		$edit_link = esc_url( get_edit_bookmark_link( $item ) );
		$actions   = array(
			'edit'   => sprintf( '<a href="%s">%s</a>', $edit_link, esc_html__( 'Edit', 'socrates-v3' ) ),
			'delete' => sprintf( '<a href="%s" class="delete-link">%s</a>', esc_url( wp_nonce_url( "link.php?action=delete&amp;link_id={$item->link_id}", "delete-bookmark_{$item->link_id}" ) ), esc_html__( 'Delete', 'socrates-v3' ) ),
		);

		/**
		 * Filters the row actions shown for each link in the list table.
		 *
		 * Allows plugins to add custom row actions (e.g., "Index with RAG").
		 *
		 * @since 3.5.0
		 *
		 * @param array  $actions An array of row action links.
		 * @param object $item    The current link item (bookmark object).
		 */
		$actions = apply_filters( 'socrates_links_table_row_actions', $actions, $item );

		$title = sprintf(
			'<a href="%s" class="row-title">%s</a>',
			$edit_link,
			esc_html( $item->link_name )
		);

		return $title . $this->row_actions( $actions );
	}

	/**
	 * Render the categories column.
	 *
	 * @param object $item The item being rendered.
	 * @return string
	 */
	protected function column_categories( $item ) {
		$cat_names = array();
		$terms     = wp_get_object_terms( $item->link_id, 'link_category' );

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$cat_names[] = $term->name;
			}
		}

		if ( empty( $cat_names ) ) {
			return esc_html__( 'None', 'socrates-v3' );
		}

		return esc_html( implode( ', ', $cat_names ) );
	}

	/**
	 * Display extra table navigation.
	 *
	 * @since 3.5.0
	 * @param string $which The location of the extra table nav ('top' or 'bottom').
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$categories = get_terms(
			array(
				'taxonomy'   => 'link_category',
				'hide_empty' => false,
			)
		);

		if ( empty( $categories ) || is_wp_error( $categories ) ) {
			return;
		}

		$current_cat_slug = ! empty( $_REQUEST['link_cat'] ) ? sanitize_key( $_REQUEST['link_cat'] ) : '';

		echo '<div class="alignleft actions">';
		echo '<label for="filter-by-category" class="screen-reader-text">' . esc_html__( 'Filter by category', 'socrates-v3' ) . '</label>';
		echo '<select name="link_cat" id="filter-by-category">';
		echo '<option value="">' . esc_html__( 'All Categories', 'socrates-v3' ) . '</option>';

		foreach ( $categories as $category ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $category->slug ),
				selected( $current_cat_slug, $category->slug, false ),
				esc_html( $category->name )
			);
		}

		echo '</select>';
		submit_button( __( 'Filter' ), 'button', 'filter_action', false, array( 'id' => 'post-query-submit' ) );
		echo '</div>';
	}

	/**
	 * Prepare the items for the table.
	 *
	 * @since 3.5.0
	 */
	public function prepare_items() {
		$per_page = 10;

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		global $wpdb;

		$where_clause = '';
		$join_clause  = '';

		$current_cat_slug = ! empty( $_REQUEST['link_cat'] ) ? sanitize_key( $_REQUEST['link_cat'] ) : '';

		if ( $current_cat_slug ) {
			$term = get_term_by( 'slug', $current_cat_slug, 'link_category' );
			if ( $term ) {
				// The table name is static and safe.
				$join_clause  = "INNER JOIN {$wpdb->term_relationships} AS tr ON ({$wpdb->links}.link_id = tr.object_id)";
				$where_clause = $wpdb->prepare( 'WHERE tr.term_taxonomy_id = %d', $term->term_taxonomy_id );
			}
		}

		// We need to get the total number of items to calculate pagination.
		$total_items = $this->get_total_links_count( $join_clause, $where_clause );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);

		$orderby_req = ( ! empty( $_REQUEST['orderby'] ) ) ? sanitize_key( $_REQUEST['orderby'] ) : 'name';
		$order_req   = ( ! empty( $_REQUEST['order'] ) ) ? strtoupper( sanitize_key( $_REQUEST['order'] ) ) : 'ASC';

		// Whitelist for sorting params to ensure security.
		$valid_orderby = array( 'name', 'url', 'rating', 'visible' );
		$valid_order   = array( 'ASC', 'DESC' );

		$orderby = in_array( $orderby_req, $valid_orderby, true ) ? $orderby_req : 'name';
		$order   = in_array( $order_req, $valid_order, true ) ? $order_req : 'ASC';

		$orderby_col_map = array(
			'name'    => 'link_name',
			'url'     => 'link_url',
			'rating'  => 'link_rating',
			'visible' => 'link_visible',
		);
		$orderby_col     = $orderby_col_map[ $orderby ];

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = "SELECT * FROM {$wpdb->links} {$join_clause} {$where_clause} ORDER BY {$orderby_col} {$order} LIMIT %d OFFSET %d";
		$query = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$query,
			$per_page,
			$offset
		);
		// phpcs:enable
		$this->items = $wpdb->get_results( $query );
	}

	/**
	 * Helper function to get the total number of links.
	 *
	 * @global \wpdb $wpdb
	 * @return int
	 */
	private function get_total_links_count( $join_clause = '', $where_clause = '' ) {
		global $wpdb;
		// The clauses are constructed safely in prepare_items.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(link_id) FROM {$wpdb->links} {$join_clause} {$where_clause}" );
		// phpcs:enable
	}
}
