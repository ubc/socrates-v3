<?php
/**
 * Socrates Link Manager
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

require_once plugin_dir_path( __FILE__ ) . 'class-socrates-links-list-table.php';

/**
 * Socrates_Link_Manager class.
 *
 * This class is responsible for creating the admin page and handling the
 * integration of the new paginated links table.
 *
 * @since 3.5.0
 */
class Socrates_Link_Manager {
	// We will implement this in a future step.
	/**
	 * Instance of the Socrates_Links_List_Table class.
	 *
	 * @var Socrates_Links_List_Table
	 */
	private $list_table;

	/**
	 * Constructor.
	 *
	 * @since 3.5.0
	 */
	public function __construct() {
		// Use a late priority to ensure the core menu is built before we modify it.
		add_action( 'admin_menu', array( $this, 'setup_admin_menu' ), 999 );
		add_action( 'load-link-manager.php', array( $this, 'redirect_old_link_manager' ) );
	}

	/**
	 * Add the admin menu page and reorder the submenu.
	 *
	 * @since 3.5.0
	 */
	public function setup_admin_menu() {
		// Add our new page.
		add_submenu_page(
			'link-manager.php',
			esc_html__( 'All Links', 'socrates-v3' ),
			esc_html__( 'All Links', 'socrates-v3' ),
			'manage_links',
			'socrates-link-manager',
			array( $this, 'render_page' )
		);

		// Remove the original, non-paginated "All Links" page.
		remove_submenu_page( 'link-manager.php', 'link-manager.php' );

		// Reorder the submenu to make our page the default when clicking "Links".
		global $submenu;
		if ( isset( $submenu['link-manager.php'] ) ) {
			$our_page = null;
			// Find our page and remove it from its current position.
			foreach ( $submenu['link-manager.php'] as $key => $item ) {
				if ( 'socrates-link-manager' === $item[2] ) {
					$our_page = $submenu['link-manager.php'][ $key ];
					unset( $submenu['link-manager.php'][ $key ] );
					break;
				}
			}
			// If we found it, add it to the beginning of the array.
			if ( $our_page ) {
				array_unshift( $submenu['link-manager.php'], $our_page );
			}
		}
	}

	/**
	 * Render the admin page.
	 *
	 * @since 3.5.0
	 */
	public function render_page() {
		$this->list_table = new Socrates_Links_List_Table();
		$this->list_table->prepare_items();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Links', 'socrates-v3' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'link-add.php' ) ); ?>" class="page-title-action"><?php echo esc_html_x( 'Add New', 'link' ); ?></a>
			<hr class="wp-header-end">
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
				<?php
				$this->list_table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Redirect from the old link-manager.php to our new paginated page.
	 *
	 * @since 3.5.0
	 */
	public function redirect_old_link_manager() {
		wp_safe_redirect( admin_url( 'admin.php?page=socrates-link-manager' ) );
		exit;
	}
}
