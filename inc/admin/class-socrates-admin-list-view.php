<?php
/**
 * Class Socrates_Admin_List_View
 *
 * Adds a custom column to the Users admin page and manages the display
 * of user-specific Socratic chats.
 */

namespace UBC\CTLT;

class Socrates_Admin_List_View {

    /**
     * Socrates_Admin_List_View constructor.
     * Hooks into the WordPress ecosystem to modify the Users admin screen
     * and to register a custom admin page for viewing chat details.
     */
    public function __construct() {

        add_filter( 'manage_users_columns', array( $this, 'add_socratic_chats_column' ) );
        add_action( 'manage_users_custom_column', array( $this, 'fill_socratic_chats_column' ), 10, 3 );
        add_action( 'admin_menu', array( $this, 'add_socratic_chat_details_page' ) );

	}//end __construct()

    /**
     * Adds a new column to the Users admin screen.
     *
     * @param array $columns An array of existing columns.
     * @return array The modified array of columns.
     */
    public function add_socratic_chats_column( $columns ) {

        // Add a new column for Socratic Chats with a custom title.
        $columns['socratic_chats'] = 'Socratic Chats';
        return $columns;

	}//end add_socratic_chats_column()

    /**
     * Populates the custom column with the number of Socratic chats for each user.
     *
     * @param string $output The HTML output for the custom column.
     * @param string $column_name The name of the column.
     * @param int $user_id The ID of the currently-listed user.
     * @return string The modified HTML output.
     */
    public function fill_socratic_chats_column( $output, $column_name, $user_id ) {

		if ( $column_name !== 'socratic_chats' ) {
			return $output;
		}

       // Fetch the user meta data for Socratic Chats.
	   $user_chats = get_user_meta( $user_id, 'socratic_chats', true );
	   // If chats exist, create a link to the custom page with chat details.
	   if (  is_array( $user_chats ) ) {
		   $chat_count = absint( count( $user_chats ) );
		   $admin_url = esc_url( admin_url( 'admin.php?page=socratic_chats_details&user_id=' . $user_id ) );
		   $output = "<a href=\"{$admin_url}\">{$chat_count}</a>";
	   } else {
		   // If no chats, output zero.
		   $output = "0";
	   }

        return $output;

    }//end fill_socratic_chats_column()

	/**
	 * Adds a custom page for viewing chat details.
	 *
	 * @return void
	 * @since 3.0.1
	 */
	public function add_socratic_chat_details_page() {

		// The parent_slug is set to null so the page will not appear in the menu.
		add_submenu_page(
			null, // Parent Slug: Setting this to null hides the menu item.
			'Socratic Chat Details', // Page Title
			'Socratic Chat Details', // Menu Title
			'manage_options', // Capability
			'socratic_chats_details', // Menu Slug
			array( $this, 'display_socratic_chats_details' ) // Function
		);

	}//end add_socratic_chat_details_page()

    /**
     * Displays the detailed chat view for the selected user.
     * This callback function renders the chat details page.
     */
    public function display_socratic_chats_details() {

		// Ensure the current user has the capability to view this page.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		// Get the user ID from the URL query string.
		$user_id = isset( $_GET['user_id'] ) ? intval( $_GET['user_id'] ) : 0;
		if ( ! $user_id ) {
			echo '<div class="error"><p>User not specified.</p></div>';
			return;
		}

		// Fetch user meta data for Socratic Chats.
		$user_chats = get_user_meta( $user_id, 'socratic_chats', true );

		// Output the custom styles.
		echo '<style>
			.wp-list-table th, .wp-list-table td {
				width: 12%;
			}
			.wp-list-table td.messages-column, .wp-list-table th.messages-column {
				width: 64% !important;
			}
		</style>';

		// Buffer the output.
		ob_start();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_the_title() ); ?></h1>
			<?php if ( $user_chats && is_array( $user_chats ) ) : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>Chat ID</th>
							<th>Start Date/Time</th>
							<th>Summary</th>
							<th class="messages-column">Messages</th> <!-- Add a class for targeting this th -->
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $user_chats as $chat_id => $chat_data ): ?>
							<tr>
								<td><?php echo esc_html( $chat_id ); ?></td>
								<td><?php echo esc_html( $chat_data['start_date_time'] ); ?></td>
								<td><?php echo esc_html( $chat_data['summary'] ); ?></td>
								<td class="messages-column"> <!-- Add a class for targeting these tds -->
									<?php foreach ( $chat_data['messages'] as $message ): ?>
										<div><strong><?php echo esc_html( ucfirst( $message['role'] ) ); ?>:</strong> <?php echo esc_html( $message['content'] ); ?></div>
									<?php endforeach; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else: ?>
				<p>No Socratic Chats available for this user.</p>
			<?php endif; ?>
		</div>
		<?php
		// Output the contents of the buffer and then clean it.
		echo ob_get_clean();
	}//end display_socratic_chats_details()

}//end class
