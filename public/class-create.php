<?php
/**
 * Create.
 *
 * @package   Create
 * @author    Patrick Daly <patrick@developdaly.com>
 * @license   GPL-2.0+
 * @link      https://github.com/Villag/create-plugin
 * @copyright 2014 CreateDenton
 */

/**
 * Plugin class. This class should ideally be used to work with the
 * public-facing side of the WordPress site.
 *
 * @package Create
 * @author  Patrick Daly <patrick@developdaly.com>
 */
class Create {

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since   2.0.0
	 *
	 * @var     string
	 */
	const VERSION = '2.0.0';

	/**
	 * Unique identifier for the plugin.
	 *
	 *
	 * The variable name is used as the text domain when internationalizing strings
	 * of text. Its value should match the Text Domain file header in the main
	 * plugin file.
	 *
	 * @since    2.0.0
	 *
	 * @var      string
	 */
	protected $plugin_slug = 'create';

	/**
	 * Instance of this class.
	 *
	 * @since    2.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin by setting localization and loading public scripts
	 * and styles.
	 *
	 * @since     2.0.0
	 */
	private function __construct() {

		// Load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Register user taxonomies
		add_action( 'init', array( $this, 'register_user_taxonomy' ) );

		// Run actions after a user updates their avatar.
		add_action( 'init', array( $this, 'on_avatar_save' ) );

		// Insert 'user_category' CSS into HEAD
		add_action( 'wp_head', array( $this, 'user_category_styles' ) );

		// Adds the taxonomy page in the admin.
		add_action( 'admin_menu', array( $this, 'add_user_category_admin_page' ) );

		// Create custom columns for the manage user_category page.
		add_filter( 'manage_edit-user_category_columns', array( $this, 'manage_user_category_user_column' ) );

		// Customize the output of the custom column on the manage categories page.
		add_action( 'manage_user_category_custom_column', array( $this, 'manage_user_category_column' ), 10, 3 );

		// Add section to the edit user page in the admin to select user_category.
		add_action( 'show_user_profile', array( $this, 'edit_user_user_category_section' ) );
		add_action( 'edit_user_profile', array( $this, 'edit_user_user_category_section' ) );

		// Update the user_category terms when the edit user page is updated.
		add_action( 'personal_options_update', array( $this, 'save_user_user_category_terms' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_user_user_category_terms' ) );

		// Hook to 'admin_init' to make sure the class is loaded before
		// (in case using the class in another plugin)
		add_action( 'admin_init', array( $this, 'register_taxonomy_meta_boxes' ) );

		// Activate plugin when new blog is added
		add_action( 'wpmu_new_blog', array( $this, 'activate_new_site' ) );

		// Run actions immediately after a user registers.
		add_action( 'user_register', array( $this, 'user_registered' ), 10, 1 );

		// Allows users to signup with email address on multisite.
		add_filter( 'wpmu_validate_user_signup', array( $this, 'custom_register_with_email' ) );

		// After user registration, login user
		add_action( 'gform_user_registered', array( $this, 'gravity_registration_autologin' ), 10, 4 );

		// Ajax: get users
		add_action( 'wp_ajax_nopriv_create_get_users', array( $this, 'get_users' ) );
		add_action( 'wp_ajax_create_get_users', array( $this, 'get_users' ) );

		// Ajax: email user
		add_action( 'wp_ajax_nopriv_create_email_user', array( $this, 'email_user' ) );
		add_action( 'wp_ajax_create_email_user', array( $this, 'email_user' ) );

		// Ajax: save user profile
		add_action( 'wp_ajax_nopriv_create_save_user_profile', array( $this, 'save_user_profile' ) );
		add_action( 'wp_ajax_create_save_user_profile', array( $this, 'save_user_profile' ) );

		// Set the mail from email address.
		add_filter( 'wp_mail_from', array( $this, 'mail_from' ) );
	}

	/**
	 * Return the plugin slug.
	 *
	 * @since    2.0.0
	 *
	 *@return    Plugin slug variable.
	 */
	public function get_plugin_slug() {
		return $this->plugin_slug;
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     2.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Fired when the plugin is activated.
	 *
	 * @since    2.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Activate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       activated on an individual blog.
	 */
	public static function activate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide  ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_activate();
				}

				restore_current_blog();

			} else {
				self::single_activate();
			}

		} else {
			self::single_activate();
		}

	}

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @since    2.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Deactivate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       deactivated on an individual blog.
	 */
	public static function deactivate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_deactivate();

				}

				restore_current_blog();

			} else {
				self::single_deactivate();
			}

		} else {
			self::single_deactivate();
		}

	}

	/**
	 * Fired when a new site is activated with a WPMU environment.
	 *
	 * @since    2.0.0
	 *
	 * @param    int    $blog_id    ID of the new blog.
	 */
	public function activate_new_site( $blog_id ) {

		if ( 1 !== did_action( 'wpmu_new_blog' ) ) {
			return;
		}

		switch_to_blog( $blog_id );
		self::single_activate();
		restore_current_blog();

	}

	/**
	 * Get all blog ids of blogs in the current network that are:
	 * - not archived
	 * - not spam
	 * - not deleted
	 *
	 * @since    2.0.0
	 *
	 * @return   array|false    The blog ids, false if no matches.
	 */
	private static function get_blog_ids() {

		global $wpdb;

		// get an array of blog ids
		$sql = "SELECT blog_id FROM $wpdb->blogs
			WHERE archived = '0' AND spam = '0'
			AND deleted = '0'";

		return $wpdb->get_col( $sql );

	}

	/**
	 * Fired for each blog when the plugin is activated.
	 *
	 * @since    2.0.0
	 */
	private static function single_activate() {
		// @TODO: Define activation functionality here
	}

	/**
	 * Fired for each blog when the plugin is deactivated.
	 *
	 * @since    2.0.0
	 */
	private static function single_deactivate() {
		// @TODO: Define deactivation functionality here
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    2.0.0
	 */
	public function load_plugin_textdomain() {

		$domain = $this->plugin_slug;
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );

	}

/**
	 * Registers the 'user_category' taxonomy for users.  This is a taxonomy for the 'user' object type rather than a
	 * post being the object type.
	 */
	public function register_user_taxonomy() {

		register_taxonomy(
			'user_category',
			'user',
			array(
				'public' => true,
				'labels' => array(
					'name' => __( 'Users Categories' ),
					'singular_name' => __( 'Category' ),
					'menu_name' => __( 'Categories' ),
					'search_items' => __( 'Search Categories' ),
					'popular_items' => __( 'Popular Categories' ),
					'all_items' => __( 'All Categories' ),
					'edit_item' => __( 'Edit Category' ),
					'update_item' => __( 'Update Category' ),
					'add_new_item' => __( 'Add New Category' ),
					'new_item_name' => __( 'New Category Name' ),
					'separate_items_with_commas' => __( 'Separate categories with commas' ),
					'add_or_remove_items' => __( 'Add or remove categories' ),
					'choose_from_most_used' => __( 'Choose from the most popular categories' ),
				),
				'rewrite' => array(
					'with_front' => true,
					'slug' => 'author/user_category' // Use 'author' (default WP user slug).
				),
				'capabilities' => array(
					'manage_terms' => 'edit_users', // Using 'edit_users' cap to keep this simple.
					'edit_terms'   => 'edit_users',
					'delete_terms' => 'edit_users',
					'assign_terms' => 'read',
				),
				'update_count_callback' => 'update_user_category_count' // Use a custom function to update the count.
			)
		);
	}

	/**
	 * Function for updating the 'user_category' taxonomy count.  What this does is update the count of a specific term
	 * by the number of users that have been given the term.  We're not doing any checks for users specifically here.
	 * We're just updating the count with no specifics for simplicity.
	 *
	 * See the _update_post_term_count() function in WordPress for more info.
	 *
	 * @param array $terms List of Term taxonomy IDs
	 * @param object $taxonomy Current taxonomy object of terms
	 */
	public function update_user_category_count( $terms, $taxonomy ) {
		global $wpdb;

		foreach ( (array) $terms as $term ) {

			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships WHERE term_taxonomy_id = %d", $term ) );

			do_action( 'edit_term_taxonomy', $term, $taxonomy );
			$wpdb->update( $wpdb->term_taxonomy, compact( 'count' ), array( 'term_taxonomy_id' => $term ) );
			do_action( 'edited_term_taxonomy', $term, $taxonomy );
		}
	}

	/**
	 * Creates the admin page for the 'user_category' taxonomy under the 'Users' menu.  It works the same as any
	 * other taxonomy page in the admin.  However, this is kind of hacky and is meant as a quick solution.  When
	 * clicking on the menu item in the admin, WordPress' menu system thinks you're viewing something under 'Posts'
	 * instead of 'Users'.  We really need WP core support for this.
	 */
	public function add_user_category_admin_page() {

		$tax = get_taxonomy( 'user_category' );

		add_users_page(
			esc_attr( $tax->labels->menu_name ),
			esc_attr( $tax->labels->menu_name ),
			$tax->cap->manage_terms,
			'edit-tags.php?taxonomy=' . $tax->name
		);
	}

	/**
	 * Unsets the 'posts' column and adds a 'users' column on the manage user_category admin page.
	 *
	 * @param array $columns An array of columns to be shown in the manage terms table.
	 */
	public function manage_user_category_user_column( $columns ) {

		unset( $columns['posts'] );

		$columns['users'] = __( 'Users' );

		return $columns;
	}

	/**
	 * Displays content for custom columns on the manage categories page in the admin.
	 *
	 * @param string $display WP just passes an empty string here.
	 * @param string $column The name of the custom column.
	 * @param int $term_id The ID of the term being displayed in the table.
	 */
	public function manage_user_category_column( $display, $column, $term_id ) {

		if ( 'users' === $column ) {
			$term = get_term( $term_id, 'user_category' );
			echo $term->count;
		}
	}

	/**
	 * Adds an additional settings section on the edit user/profile page in the admin.  This section allows users to
	 * select a user_category from a checkbox of terms from the user_category taxonomy.  This is just one example of
	 * many ways this can be handled.
	 *
	 * @param object $user The user object currently being edited.
	 */
	public function edit_user_user_category_section( $user ) {

		$tax = get_taxonomy( 'user_category' );

		/* Make sure the user can assign terms of the user_category taxonomy before proceeding. */
		if ( !current_user_can( $tax->cap->assign_terms ) )
			return;

		/* Get the terms of the 'user_category' taxonomy. */
		$terms = get_terms( 'user_category', array( 'hide_empty' => false ) ); ?>

		<h3><?php _e( 'Category' ); ?></h3>

		<table class="form-table">

			<tr>
				<th><label for="user_category"><?php _e( 'Select Category' ); ?></label></th>

				<td><?php

				/* If there are any user_category terms, loop through them and display checkboxes. */
				if ( !empty( $terms ) ) {

					foreach ( $terms as $term ) { ?>
						<input type="radio" name="user_category" id="user_category-<?php echo esc_attr( $term->slug ); ?>" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( true, is_object_in_term( $user->ID, 'user_category', $term ) ); ?> /> <label for="user_category-<?php echo esc_attr( $term->slug ); ?>"><?php echo $term->name; ?></label> <br />
					<?php }
				}

				/* If there are no user_category terms, display a message. */
				else {
					_e( 'There are no categories available.' );
				}

				?></td>
			</tr>

		</table>
	<?php }

	/**
	 * Saves the term selected on the edit user/profile page in the admin. This function is triggered when the page
	 * is updated.  We just grab the posted data and use wp_set_object_terms() to save it.
	 *
	 * @param int $user_id The ID of the user to save the terms for.
	 */
	public function save_user_user_category_terms( $user_id ) {

		$tax = get_taxonomy( 'user_category' );

		/* Make sure the current user can edit the user and assign terms before proceeding. */
		if ( !current_user_can( 'edit_user', $user_id ) && current_user_can( $tax->cap->assign_terms ) )
			return false;

		$term = esc_attr( $_POST['user_category'] );

		/* Sets the terms (we're just using a single term) for the user. */
		wp_set_object_terms( $user_id, array( $term ), 'user_category', false);

		clean_object_term_cache( $user_id, 'user_category' );
	}

	/**
	 * Registering meta sections for taxonomies
	 *
	 * All the definitions of meta sections are listed below with comments, please read them carefully.
	 * Note that each validation method of the Validation Class MUST return value.
	 *
	 * You also should read the changelog to know what has been changed
	 *
	 */

	/**
	 * Register meta boxes
	 *
	 * @return void
	 */
	public function register_taxonomy_meta_boxes() {
		// Make sure there's no errors when the plugin is deactivated or during upgrade
		if ( !class_exists( 'RW_Taxonomy_Meta' ) )
			return;

		$meta_sections = array();

		$meta_sections[] = array(
			'title'      => 'Standard Fields',		// section title
			'taxonomies' => array( 'user_category' ),	// list of taxonomies. Default is array('category', 'post_tag'). Optional
			'id'         => 'user_category_options',	// ID of each section, will be the option name
			'fields' => array(
				array(
					'name' => 'Color Picker',
					'id'   => 'color',
					'type' => 'color'
				)
			)
		);

		foreach ( $meta_sections as $meta_section )
		{
			new RW_Taxonomy_Meta( $meta_section );
		}
	}

	/**
	 * Get the 'user_category' terms and colors and create a <style> block
	 *
	 * @since    1.1.0
	 */
	public function user_category_styles() {
		$terms = get_terms( array( 'user_category' ), array( 'hide_empty' => false ) );
		if( $terms ) {
			echo "<style id='job_manager_colors'>\n";
			$user_category_options = get_option( 'user_category_options' );
			foreach ( $terms as $term ) {
				if( ! array_key_exists( $term->term_id, $user_category_options) ) {
					continue;
				}
				foreach( $user_category_options[$term->term_id] as $term_meta ) {
					$color = $term_meta;
					echo '.item.'. $term->slug .', .'. $term->slug .' .modal-header { background: '. $color .'}';
					echo '.card-back.'. $term->slug .' a { color: '. $color .'}';
					echo '[data-filter=".'. $term->slug .'"] { border-color: '. $color .'}';
					echo '[data-filter=".'. $term->slug .'"].selected { background: '. $color .'; color: #fff; }';
				}
			}
			echo "</style>\n";
		}
	}

	/**
	 * Via Ajax, sends the given user an email. This avoids exposing the user's
	 * email address to anyone.
	 *
	 * @since    1.1.0
	 */
	function email_user() {
		check_ajax_referer( 'create_email_user_ajax_nonce', 'security' );

		$subject			= $_POST['subject'];
		$message			= $_POST['message'];
		$user_object_to		= get_userdata( $_POST['user_id_to'] );
		$user_object_from	= get_userdata( $_POST['user_id_from'] );

		$to					= $user_object_to->user_email;
		$headers[]			= 'From: '. $user_object_from->first_name .' '. $user_object_from->last_name .' <'. $user_object_from->user_email .'>';
		$headers[]			= 'Reply-To: ' . $user_object_from->user_email;

		$result				= wp_mail( sanitize_email( $to ), esc_html( $subject ), $message, $headers );

		if ( isset( $result ) && ( $result == 1 ) ) {
			die(
				json_encode(
					array(
						'success' => true,
						'message' => __( 'Your email was successfully sent to '. $user_object_to->first_name .'.', 'create' )
					)
				)
			);
		} else {
			die(
				json_encode(
					array(
						'success' => false,
						'message' => __( 'An error occured. Please refresh the page and try again.', 'create' )
					)
				)
			);
		}
	}

	/**
	 * Via Ajax, sends the given user an email. This avoids exposing the user's
	 * email address to anyone.
	 *
	 * @since    1.1.0
	 */
	function save_user_profile() {
		check_ajax_referer( 'create_save_user_profile_ajax_nonce', 'security' );

		$first_name		= $_POST['first_name'];
		$last_name		= $_POST['last_name'];
		$email			= $_POST['email'];
		$user_id		= $_POST['user_id'];
		$blog_id		= $_POST['blog_id'];
		$twitter		= $_POST['twitter'];
		$website		= $_POST['website'];
		$linkedin		= $_POST['linkedin'];
		$phone			= $_POST['phone'];
		$zip			= $_POST['zip_code'];
		$skills			= $_POST['skills'];
		$user_category	= $_POST['user_category'];
		$bio			= $_POST['bio'];

		$allowed_html = array(
			'a' => array(
			'href' => array(),
			'title' => array()
		),
			'br' => array(),
			'em' => array(),
			'strong' => array()
		);

		$meta_value = array(
			'twitter'		=> sanitize_text_field( $twitter ),
			'website'		=> esc_url( $website ),
			'linkedin'		=> esc_url( $linkedin ),
			'email'			=> sanitize_email( $email ),
			'phone'			=> sanitize_text_field( $phone ),
			'zip_code'		=> intval( $zip ),
			'skills'		=> $skills,
			'bio'			=> wp_kses( $bio, $allowed_html )
		);

		// Global user meta
		update_user_meta( $user_id, 'first_name', sanitize_text_field( $first_name ) );
		update_user_meta( $user_id, 'last_name', sanitize_text_field( $last_name ) );

		// Save user_category to taxonomy
		wp_set_object_terms( $user_id, intval( $user_category ), 'user_category' );

		$blog_details = get_blog_details( $blog_id );

		// Blog-specific user meta
		$result = update_user_meta( $user_id, 'user_meta_'. str_replace( '/', '', $blog_details->path ), $meta_value );

		if ( isset( $result ) ) {

			add_existing_user_to_blog( array( 'user_id' => $user_id, 'role' => 'subscriber' ) );

			$cache = self::clear_cache( array( 'user_id' => $user_id ) );

			$errors = self::user_errors( $user_id );

			if( empty( $errors ) ) {
				self::clear_cache( 'all' );
			}

			die(
				json_encode(
					array(
						'success' => true,
						'message' => __( 'Your profile has been updated.', 'create' ),
						'cache' => $cache
					)
				)
			);
		} else {
			die(
				json_encode(
					array(
						'success' => false,
						'message' => __( 'An error occured. Please refresh the page and try again.', 'create' ),
						'cache' => $cache
					)
				)
			);
		}
	}

	/**
	 * Gets all users for the current site and returns the data as a JSON
	 * encoded object for use by an ajax call from the theme.
	 *
	 * @since    1.1.0
	 */
	public function get_users() {

		if ( false === ( $users = get_transient( 'users_query' ) ) ) {
			$users = get_users( array( 'fields' => 'ID' ) );
			foreach ( $users as $user_id ) {
				$errors = self::user_errors( $user_id );

				if( ! empty( $errors ) ) {
					continue;
				} else {
					$valid_users[] = $user_id;
				}
			}
			shuffle( $valid_users );
			set_transient( 'users_query', $valid_users, 12 * HOUR_IN_SECONDS );
		} else {
			$valid_users = get_transient( 'users_query' );
		}

		if( ! empty( $valid_users ) ) {

			foreach ( $valid_users as $user_id ) {
				$user_objects[] = self::get_user( $user_id );
			}

			$result = array( 'users' => $user_objects );

		}

		if ( isset( $result ) ) {
			die( json_encode( $result ) );
		} else {
			die(
				json_encode(
					array(
						'success' => false,
						'message' => __( 'An error occured. Please refresh the page and try again.', 'nervetask' )
					)
				)
			);
		}
	}

	/**
	 * Create/get the user object needed for directory display.
	 *
	 * @since    1.1.0
	 */
	public function get_user( $user_id ) {

		// Get the site-specific user meta
		$blog_id = get_current_blog_id();
		$blog_details = get_blog_details( $blog_id );
		$blog_slug = str_replace( '/', '', $blog_details->path );

		if ( false === ( $user = get_transient( 'user_meta_'. $blog_slug .'_'. $user_id ) ) ) {

			$user_meta = get_user_meta( $user_id, 'user_meta_'. str_replace( '/', '', $blog_details->path ), true );

			// Get the user category
			$user_categories = wp_get_object_terms( $user_id, 'user_category' );
			if ( $user_categories && ! is_wp_error( $user_categories ) ) :

				$user_category_slugs = array();
				$user_category_names = array();

				foreach ( $user_categories as $user_category ) {
					$user_category_slugs[] = $user_category->slug;
					$user_category_names[] = $user_category->name;
				}

				$types			= join( ' ', $user_category_slugs );
				$primary_jobs	= join( ' ', $user_category_names );

			endif;

			$userdata						= get_userdata( $user_id );
			$user_object['email']			= $userdata->user_email;
			$user_object['ID'] 				= $user_id;
			$user_object['types'] 			= $types;
			$user_object['primary_jobs'] 	= $primary_jobs;
			$user_object['first_name']		= get_user_meta( $user_id, 'first_name', true );
			$user_object['last_name']		= get_user_meta( $user_id, 'last_name', true );
			$user_object['avatar']			= get_stylesheet_directory_uri() . "/timthumb.php?src=". get_wp_user_avatar_src( $user_id, 150 ) ."&w=150&h=150&zc=1&a=c&f=2";

			$user = array_merge( $user_object, $user_meta );
			set_transient( 'user_meta_'. $blog_slug .'_'. $user_id, $user, 12 * HOUR_IN_SECONDS );

		} else {
			$user = get_transient( 'user_meta_'. $blog_slug .'_'. $user_id );
		}

		return $user;
	}

	/**
	 * Define the mail from address.
	 *
	 * @since    1.1.0
	 */
	public function mail_from( $email ) {
		return 'info@createdenton.com';
	}

	/**
	 * Displays the errors a user has
	 * (i.e. missing data required to be a valid user)
	 *
	 * @since    1.1.0
	 */
	public function user_errors( $user_id ) {

		$blog_id		= get_current_blog_id();
		$blog_details	= get_blog_details( $blog_id );
		$user_info		= get_userdata( $user_id );
		$user_meta		= get_user_meta( $user_id, 'user_meta_'. str_replace( '/', '', $blog_details->path ), true );

		$first_name		= get_user_meta( $user_id, 'first_name', true );
		$last_name		= get_user_meta( $user_id, 'first_name', true );
		$email			= $user_info->user_email;
		$zip			= isset( $user_meta['zip_code'] );
		$primary_job	= isset( $user_meta['primary_jobs'] );
		$avatar			= get_wp_user_avatar_src( $user_id, 150 );

		// Get the user category
		$user_categories = wp_get_object_terms( $user_id, 'user_category' );
		if ( $user_categories && ! is_wp_error( $user_categories ) ) :

			$user_category_slugs = array();
			$user_category_names = array();

			foreach ( $user_categories as $user_category ) {
				$user_category_slugs[] = $user_category->slug;
				$user_category_names[] = $user_category->name;
			}

			$types			= join( ' ', $user_category_slugs );
			$primary_jobs	= join( ' ', $user_category_names );

		endif;

		$errors = array();

		if ( empty( $email ) )
			$errors[] = ' email';

		if ( empty( $first_name ) )
			$errors[] = ' first name';

		if ( empty( $last_name ) )
			$errors[] = ' last name';

		if ( empty( $zip ) )
			$errors[] = ' zip code';

		if ( empty( $primary_jobs ) )
			$errors[] = ' talent';

		if ( ! isset( $avatar ) )
			$errors[] = ' avatar';

		$output = implode( ',', $errors );

		return $output;
	}

	/**
	 * Get the URL of the user's avatar.
	 *
	 * @since    1.1.0
	 */
	public function get_wp_user_avatar_src( $user_id, $size = 150 ) {
		$original = get_wp_user_avatar_src( $user_id, $size );
		$home_url = get_bloginfo( 'url' );
		$output = str_replace( $home_url, '', $original );
		return $output;
	}

	/**
	 * Checks if the user is valid (has all the right info) and returns boolean.
	 *
	 * @since    1.1.0
	 */
	public function is_valid_user( $user_id ) {

		$errors = user_errors( $user_id );
		if( ! empty( $errors ) ) {
			return false;
		} else {
			return true;
		}

	}

	/**
	 * Get the user's avatar.
	 *
	 * @since    1.1.0
	 */
	public function get_avatar( $user_id, $size = 150 ) {

		$image = get_wp_user_avatar_src( $user_id, $size );

		if( strpos( $image, 'wpua') !== false ) {
			$image = get_user_meta( $user_id, 'avatar', true );

			if( strpos( $image, 'http') !== false ) {
				$image = $image;
			} else {
				$image = get_stylesheet_directory_uri() .'/uploads/avatars/'. $image;
			}
		}

		return $image;
	}

	/**
	 * Clears the user's cache when their avatar is updated.
	 *
	 * @since    1.1.0
	 */
	public function on_avatar_save() {
		if( isset( $_POST['wp-user-avatar'] ) ) {
			self::clear_cache( array( 'user_id' => $_POST['user_id'] ) );
		}
	}

	/**
	 * Clear the cached user query so this new avatar will show up.
	 *
	 * @since    1.1.0
	 */
	public function clear_cache( $args ) {

		if( empty( $args ) ) {
			return;
		}

		if ( is_array( $args ) && array_key_exists( 'user_id', $args ) ) {
			if( ! empty( $args['user_id'] ) ) {
				// Delete the user's object cache
				$blog_id = get_current_blog_id();
				$blog_details = get_blog_details( $blog_id );
				$blog_slug = str_replace( '/', '', $blog_details->path );
				delete_transient( 'user_meta_'. $blog_slug .'_'. $args['user_id'] );

				return 'User '. $args['user_id'] . ' cleared';
			}
		} elseif( $args == 'all' ) {
			delete_transient( 'users_query' );
			if( class_exists('W3_Plugin_TotalCacheAdmin') ) {
				w3tc_flush_all();
			}
			return 'Cleared users_query cache';
		}

		return 'Did not clear any caches';
	}

	/**
	 * Clear the cache when a user registers.
	 *
	 * @since    2.0.0
	 */
	public function user_registered( $user_id ) {
		self::clear_cache( 'all' );
	}

	/**
	 * WordPress register with email only, make it possible to register with email
	 * as username in a multisite installation
	 * @param  Array $result Result array of the wpmu_validate_user_signup-function
	 * @return Array         Altered result array
	 */
	public function custom_register_with_email( $result ) {

		if ( $result['user_name'] != '' && is_email( $result['user_name'] ) ) {

			unset( $result['errors']->errors['user_name'] );

		}

		return $result;
	}

	/**
	 * Auto login after registration.
	 *
	 * @since    1.1.0
	 */
	function gravity_registration_autologin( $user_id, $user_config, $entry, $password ) {
		$user = get_userdata( $user_id );
		$user_login = $user->user_login;
		$user_password = $password;

		wp_signon( array(
			'user_login'	=> $user_login,
			'user_password'	=> $user_password,
			'remember'		=> false
		) );
	}
}
