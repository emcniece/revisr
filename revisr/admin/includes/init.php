<?php
/**
 * init.php
 *
 * WordPress hooks and functions for the 'wp-admin'.
 *
 * @package   Revisr
 * @author    Matt Shaw <matt@expandedfronts.com>
 * @license   GPL-2.0+
 * @link      https://revisr.io
 * @copyright 2014 Expanded Fronts, LLC
 */

class revisr_init
{
	

	private $dir;
	private $table_name;

	public function __construct()
	{
		global $wpdb;

		$this->wpdb = $wpdb;

		$this->table_name = $wpdb->prefix . "revisr";
		$this->dir = plugin_dir_path( __FILE__ );
		register_activation_hook( __FILE__, 'revisr_install' );

		if ( is_admin() ) {
			add_action( 'init', array($this, 'post_types') );
			add_action( 'load-post.php', array($this, 'meta') );
			add_action( 'load-post-new.php', array($this, 'meta') );
			add_action( 'views_edit-revisr_commits', array($this, 'custom_views') );
			add_action( 'post_row_actions', array($this, 'custom_actions') );
			add_action( 'admin_menu', array($this, 'menus'), 2 );
			add_action( 'manage_edit-revisr_commits_columns', array($this, 'columns') );
			add_action( 'manage_revisr_commits_posts_custom_column', array($this, 'custom_columns') );
			add_action( 'admin_enqueue_scripts', array($this, 'styles') );
			add_filter( 'post_updated_messages', array($this, 'revisr_commits_custom_messages') );
			add_filter( 'bulk_post_updated_messages', array($this, 'revisr_commits_bulk_messages'), 10, 2 );
			add_filter( 'custom_menu_order', array($this, 'revisr_commits_submenu_order') );
		}
	}

	public function revisr_install()
	{
		
		$sql = "CREATE TABLE IF NOT EXISTS $this->table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			message TEXT,
			event VARCHAR(42) NOT NULL,
			UNIQUE KEY id (id)
			);";
		
	  	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	   	dbDelta( $sql );
	   	add_option( "revisr_db_version", $revisr_db_version );
	}	

	public function post_types()
	{
		$labels = array(
			'name'                => 'Commits',
			'singular_name'       => 'Commit',
			'menu_name'           => 'Commits',
			'parent_item_colon'   => '',
			'all_items'           => 'Commits',
			'view_item'           => 'View Commit',
			'add_new_item'        => 'New Commit',
			'add_new'             => 'New Commit',
			'edit_item'           => 'Edit Commit',
			'update_item'         => 'Update Commit',
			'search_items'        => 'Search Commits',
			'not_found'           => 'No commits found yet, why not create a new one?',
			'not_found_in_trash'  => 'No commits in trash.',
		);
		$capabilities = array(
			'edit_post'           => 'activate_plugins',
			'read_post'           => 'activate_plugins',
			'delete_post'         => 'activate_plugins',
			'edit_posts'          => 'activate_plugins',
			'edit_others_posts'   => 'activate_plugins',
			'publish_posts'       => 'activate_plugins',
			'read_private_posts'  => 'activate_plugins',
		);
		$args = array(
			'label'               => 'revisr_commits',
			'description'         => 'Commits made through Revisr',
			'labels'              => $labels,
			'supports'            => array( 'title', 'author'),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'revisr',
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => true,
			'menu_position'       => 5,
			'menu_icon'           => '',
			'can_export'          => true,
			'has_archive'         => true,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capabilities'        => $capabilities,
		);
		register_post_type( 'revisr_commits', $args );
	}

	public function meta()
	{
		if ($_GET['action'] == 'edit') {
			add_meta_box( 'revisr_committed_files', 'Committed Files', array($this, 'committed_files_meta'), 'revisr_commits' );
		}
		else {
			add_meta_box( 'revisr_pending_files', 'Pending Files', array($this, 'pending_files_meta'), 'revisr_commits' );
		}
	}

	public function menus()
	{
		$menu = add_menu_page( 'Dashboard', 'Revisr', 'manage_options', 'revisr', array($this, 'revisr_dashboard'), plugins_url( 'revisr/img/revisrlogo_small-white.png' ) );
		add_submenu_page( 'revisr', 'Revisr - Dashboard', 'Dashboard', 'manage_options', 'revisr', array($this, 'revisr_dashboard') );
		add_submenu_page( 'revisr', 'Revisr - Settings', 'Settings', 'manage_options', 'revisr_settings', array($this, 'revisr_settings') );
		add_action( 'admin_print_styles-' . $menu, array($this, 'styles') );
		add_action( 'admin_print_scripts-' . $menu, array($this, 'scripts') );
	}

	public function revisr_commits_submenu_order($menu_ord)
	{
		global $submenu;

	    $arr = array();
	    $arr[] = $submenu['revisr'][0];     //my original order was 5,10,15,16,17,18
	    $arr[] = $submenu['revisr'][2];
	    $arr[] = $submenu['revisr'][1];
	    $submenu['revisr'] = $arr;

	    return $menu_ord;
	}

	public function revisr_dashboard()
	{
		include_once $this->dir . "../templates/dashboard.php";
	}

	public function revisr_settings()
	{
		include_once $this->dir . "../templates/settings.php";
	}

	public function custom_actions()
	{
		if (get_post_type() == 'revisr_commits')
			{
				unset( $actions['edit'] );
		        unset( $actions['view'] );
		        unset( $actions['trash'] );
		        unset( $actions['inline hide-if-no-js'] );
		        $actions['view'] = "<a href='#'>View</a>";
		        $commit_meta = get_post_custom_values('commit_hash', get_the_ID());
		        $commit_hash = unserialize($commit_meta[0]);
		        $actions['revert'] = "<a href='" . get_admin_url() . "admin-post.php?action=revert&commit_hash={$commit_hash[0]}'>Revert</a>";
		    	return $actions;
			}
	}

	public function custom_views()
	{
		unset($views);
		return $views;
	}

	public function styles()
	{
		wp_enqueue_style( 'revisr_css', plugins_url() . '/revisr/assets/css/revisr.css' );
	}

	public function scripts()
	{

	}

	public function committed_files_meta()
	{
		echo "<div id='committed_files_result'></div>";
	}

	public function pending_files_meta()
	{
		$current_dir = getcwd();
		chdir(ABSPATH);
		exec("git status --short", $output);
		chdir($current_dir);
		add_post_meta( get_the_ID(), 'committed_files', $output );
		echo "<div id='pending_files_result'></div>";
	}

	public function columns()
	{
		$columns = array (
			'cb' => '<input type="checkbox" />',
			'hash' => __('ID'),
			'title' => __('Commit'),
			'date' => __('Date'));
		return $columns;
	}

	public function custom_columns($column)
	{
		global $post;

		$post_id = get_the_ID();
		switch ($column) {
			case "hash": 
				$commit_meta = get_post_meta($post_id, "commit_hash");
				$commit_hash = $commit_meta[0];
				if (empty($commit_hash)) {
					echo __("Unknown");
				}
				else {
					echo $commit_hash[0];
				}
		}

	}

	public function revisr_commits_custom_messages($messages)
	{
		$post             = get_post();
		$post_type        = get_post_type( $post );
		$post_type_object = get_post_type_object( $post_type );

		$messages['revisr_commits'] = array(
			0  => '', // Unused. Messages start at index 1.
			1  => __( 'Commit updated.', 'revisr_commits' ),
			2  => __( 'Custom field updated.', 'revisr_commits' ),
			3  => __( 'Custom field deleted.', 'revisr_commits' ),
			4  => __( 'Commit updated.', 'revisr_commits' ),
			/* translators: %s: date and time of the revision */
			5  => isset( $_GET['revision'] ) ? sprintf( __( 'Commit restored to revision from %s', 'revisr_commits' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6  => __( 'Commit published.', 'revisr_commits' ),
			7  => __( 'Commit saved.', 'revisr_commits' ),
			8  => __( 'Commit submitted.', 'revisr_commits' ),
			9  => sprintf(
				__( 'Commit scheduled for: <strong>%1$s</strong>.', 'revisr_commits' ),
				// translators: Publish box date format, see http://php.net/date
				date_i18n( __( 'M j, Y @ G:i', 'revisr_commits' ), strtotime( $post->post_date ) )
			),
			10 => __( 'Commit draft updated.', 'revisr_commits' ),
		);

		if ( $post_type_object->publicly_queryable ) {
			$permalink = get_permalink( $post->ID );

			$view_link = sprintf( ' <a href="%s">%s</a>', esc_url( $permalink ), __( 'View Commit', 'revisr_commits' ) );
			$messages[ $post_type ][1] .= $view_link;
			$messages[ $post_type ][6] .= $view_link;
			$messages[ $post_type ][9] .= $view_link;

			$preview_permalink = add_query_arg( 'preview', 'true', $permalink );
			$preview_link = sprintf( ' <a target="_blank" href="%s">%s</a>', esc_url( $preview_permalink ), __( 'Preview Commit', 'revisr_commits' ) );
			$messages[ $post_type ][8]  .= $preview_link;
			$messages[ $post_type ][10] .= $preview_link;
		}

		return $messages;
	}

	public function revisr_commits_bulk_messages($bulk_messages, $bulk_counts)
	{
		$bulk_messages['revisr_commits'] = array(
			'updated' => _n( '%s commit updated.', '%s commits updated.', $bulk_counts['updated'] ),
			'locked'    => _n( '%s commit not updated, somebody is editing it.', '%s commits not updated, somebody is editing them.', $bulk_counts['locked'] ),
			'deleted'   => _n( '%s commit permanently deleted.', '%s commits permanently deleted.', $bulk_counts['deleted'] ),
			'trashed'   => _n( '%s commit moved to the Trash.', '%s commits moved to the Trash.', $bulk_counts['trashed'] ),
        	'untrashed' => _n( '%s commit restored from the Trash.', '%s commits restored from the Trash.', $bulk_counts['untrashed'] )
        	);
		return $bulk_messages;
	}


}