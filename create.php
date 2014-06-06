<?php
/**
 * @package   Create
 * @author    Villag
 * @license   GPL-2.0+
 * @link      https://github.com/Villag/create-plugin
 * @copyright 2014 Villag
 *
 * @wordpress-plugin
 * Plugin Name:       Create
 * Plugin URI:        https://github.com/Villag/create-plugin
 * Description:       Creative directory
 * Version:           1.1
 * Author:            Villag
 * Author URI:        https://github.com/Villag
 * Text Domain:       create
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/Villag/create-plugin
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Register user taxonomies
add_action( 'init', 'create_register_user_taxonomy' );

// Insert 'user_category' CSS into HEAD
add_action( 'wp_head', 'create_user_category_styles' );

// Ajax: get users
add_action( 'wp_ajax_nopriv_create_get_users',	'create_get_users' );
add_action( 'wp_ajax_create_get_users',			'create_get_users' );

// Ajax: email user
add_action( 'wp_ajax_nopriv_create_email_user',	'create_email_user' );
add_action( 'wp_ajax_create_email_user',	'create_email_user' );

// Ajax: save user profile
add_action( 'wp_ajax_nopriv_create_save_user_profile',	'create_save_user_profile' );
add_action( 'wp_ajax_create_save_user_profile',	'create_save_user_profile' );

// After user registration, login user
add_action( 'gform_user_registered', 'create_gravity_registration_autologin', 10, 4 );

// Clear the user query cache when a user updates their profile
add_action( 'init', 'create_profile_update' );

/**
 * Via Ajax, sends the given user an email. This avoids exposing the user's
 * email address to anyone.
 */
function create_email_user() {
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
 */
function create_save_user_profile() {
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
	$zip			= $_POST['zip'];
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
		'zip'			=> intval( $zip ),
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
		delete_transient( 'users_query' );
		die(
			json_encode(
				array(
					'success' => true,
					'message' => __( 'Your profile has been updated.', 'create' )
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
 * Gets all users for the current site and returns the data as a JSON
 * encoded object for use by an ajax call from the theme.
 */
function create_get_users() {
	if ( false === ( $user_array = get_transient( 'users_query' ) ) ) {
		$users = get_users( array( 'fields' => 'ID' ) );
		shuffle( $users );
		foreach ( $users as $user ) {

			// Get the site-specific user meta
			$blog_id = get_current_blog_id();
			$blog_details = get_blog_details( $blog_id );
			$user_meta = get_user_meta( $user, 'user_meta_'. str_replace( '/', '', $blog_details->path ), true );

			// Get the user category
			$user_categories = wp_get_object_terms( $user, 'user_category' );
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

			//if( create_is_valid_user( $user ) !== true ) {
			//	continue;
			//}

			$userdata						= get_userdata( $user );
			$user_object['ID'] 				= $user;
			$user_object['types'] 			= $types;
			$user_object['primary_jobs'] 	= $primary_jobs;
			$user_object['first_name']		= get_user_meta( $user, 'first_name', true );
			$user_object['last_name']		= get_user_meta( $user, 'last_name', true );
			$user_object['avatar']			= create_get_avatar( $user );

			$user_array[] = array_merge( $user_object, $user_meta );

		}

		set_transient( 'users_query', $user_array, 12 * HOUR_IN_SECONDS );
	} else {
		$user_array = get_transient( 'users_query' );
	}

	$result = array( 'users' => $user_array );

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

function create_mail_from( $email ) {
	return 'info@createdenton.com';
}
add_filter( 'wp_mail_from', 'create_mail_from' );

/**
 * Auto login after registration.
 */
function create_gravity_registration_autologin( $user_id, $user_config, $entry, $password ) {
	$user = get_userdata( $user_id );
	$user_login = $user->user_login;
	$user_password = $password;

	wp_signon( array(
		'user_login'	=> $user_login,
		'user_password'	=> $user_password,
		'remember'		=> false
	) );
}

/**
 * WordPress register with email only, make it possible to register with email
 * as username in a multisite installation
 * @param  Array $result Result array of the wpmu_validate_user_signup-function
 * @return Array         Altered result array
 */
function custom_register_with_email( $result ) {

	if ( $result['user_name'] != '' && is_email( $result['user_name'] ) ) {

		unset( $result['errors']->errors['user_name'] );

	}

	return $result;
}
add_filter( 'wpmu_validate_user_signup','custom_register_with_email' );

/**
 * Get the 'user_category' terms and colors and create a <style> block
 */
function create_user_category_styles() {
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
 * Checks if the user is valid (has all the right info) and returns boolean.
 *
 */
function create_is_valid_user( $user_id ) {

	if( create_user_errors( $user_id ) == null ) {
		return true;
	} else {
		return false;
	}
}

/**
 * Displays the errors a user has
 * (i.e. missing data required to be a valid user)
 */
function create_user_errors( $user_id ) {

	$blog_id		= get_current_blog_id();
	$blog_details	= get_blog_details( $blog_id );
	$user_meta		= get_user_meta( $user_id, 'user_meta_'. str_replace( '/', '', $blog_details->path ), true );

	$first_name		= get_user_meta( $user_id, 'first_name', true );
	$last_name		= get_user_meta( $user_id, 'first_name', true );
	$zip			= isset( $user_meta['zip'] );
	$email			= isset( $user_meta['email'] );
	$primary_job	= isset( $user_meta['primary_jobs'] );
	$avatar			= isset( $user_meta['avatar'] );

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

	if ( empty( $avatar ) )
		$errors[] = ' avatar';

	$output = implode( ',', $errors );

	return $output;
}

/**
 * Get the user's avatar.
 */
function create_get_avatar( $user_id ) {

	$user = get_userdata( $user_id );
	$avatar = get_user_meta( $user_id, 'avatar', true );

	if( isset( $avatar ) ) {
		if( strpos( $avatar, 'http:') !== false ) {
			$image = $avatar;
		} else {
			$image = get_stylesheet_directory_uri(). '/uploads/avatars/'. get_user_meta( $user_id, 'avatar', true );

		}
	} elseif( validate_gravatar( $user->user_email ) ) {
		$image = get_wp_user_avatar_src( $user_id, 150 );
	}

	if( empty( $image ) ) {
		return;
	}

	$output	= get_stylesheet_directory_uri() . "/timthumb.php?src=". $image ."&w=150&h=150&zc=1&a=c&f=2";

	$headers = get_headers( $output, 1 );
	if ( $headers[0] != 'HTTP/1.1 200 OK' ) {
		return;
	}

	return $output;
}

function validate_gravatar( $email ) {
	// Craft a potential url and test its headers
	$hash = md5(strtolower(trim($email)));
	$uri = 'http://www.gravatar.com/avatar/' . $hash . '?d=404';
	$headers = @get_headers($uri);
	if (!preg_match("|200|", $headers[0])) {
		$has_valid_avatar = FALSE;
	} else {
		$has_valid_avatar = TRUE;
	}
	return $has_valid_avatar;
}

/**
 * Clear the cached user query so this new avatar will show up
*/
function create_profile_update() {
	if( ! empty( $_POST['wp-user-avatar'] ) ) {
		delete_transient( 'users_query' );
	}
}

/**
 * Registers the 'user_category' taxonomy for users.  This is a taxonomy for the 'user' object type rather than a
 * post being the object type.
 */
function create_register_user_taxonomy() {
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
			'update_count_callback' => 'create_update_user_category_count' // Use a custom function to update the count.
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
function create_update_user_category_count( $terms, $taxonomy ) {
	global $wpdb;

	foreach ( (array) $terms as $term ) {

		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships WHERE term_taxonomy_id = %d", $term ) );

		do_action( 'edit_term_taxonomy', $term, $taxonomy );
		$wpdb->update( $wpdb->term_taxonomy, compact( 'count' ), array( 'term_taxonomy_id' => $term ) );
		do_action( 'edited_term_taxonomy', $term, $taxonomy );
	}
}

/* Adds the taxonomy page in the admin. */
add_action( 'admin_menu', 'create_add_user_category_admin_page' );

/**
 * Creates the admin page for the 'user_category' taxonomy under the 'Users' menu.  It works the same as any
 * other taxonomy page in the admin.  However, this is kind of hacky and is meant as a quick solution.  When
 * clicking on the menu item in the admin, WordPress' menu system thinks you're viewing something under 'Posts'
 * instead of 'Users'.  We really need WP core support for this.
 */
function create_add_user_category_admin_page() {

	$tax = get_taxonomy( 'user_category' );

	add_users_page(
		esc_attr( $tax->labels->menu_name ),
		esc_attr( $tax->labels->menu_name ),
		$tax->cap->manage_terms,
		'edit-tags.php?taxonomy=' . $tax->name
	);
}

/* Create custom columns for the manage user_category page. */
add_filter( 'manage_edit-user_category_columns', 'create_manage_user_category_user_column' );

/**
 * Unsets the 'posts' column and adds a 'users' column on the manage user_category admin page.
 *
 * @param array $columns An array of columns to be shown in the manage terms table.
 */
function create_manage_user_category_user_column( $columns ) {

	unset( $columns['posts'] );

	$columns['users'] = __( 'Users' );

	return $columns;
}

/* Customize the output of the custom column on the manage categories page. */
add_action( 'manage_user_category_custom_column', 'create_manage_user_category_column', 10, 3 );

/**
 * Displays content for custom columns on the manage categories page in the admin.
 *
 * @param string $display WP just passes an empty string here.
 * @param string $column The name of the custom column.
 * @param int $term_id The ID of the term being displayed in the table.
 */
function create_manage_user_category_column( $display, $column, $term_id ) {

	if ( 'users' === $column ) {
		$term = get_term( $term_id, 'user_category' );
		echo $term->count;
	}
}

/* Add section to the edit user page in the admin to select user_category. */
add_action( 'show_user_profile', 'create_edit_user_user_category_section' );
add_action( 'edit_user_profile', 'create_edit_user_user_category_section' );

/**
 * Adds an additional settings section on the edit user/profile page in the admin.  This section allows users to
 * select a user_category from a checkbox of terms from the user_category taxonomy.  This is just one example of
 * many ways this can be handled.
 *
 * @param object $user The user object currently being edited.
 */
function create_edit_user_user_category_section( $user ) {

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

/* Update the user_category terms when the edit user page is updated. */
add_action( 'personal_options_update', 'create_save_user_user_category_terms' );
add_action( 'edit_user_profile_update', 'create_save_user_user_category_terms' );

/**
 * Saves the term selected on the edit user/profile page in the admin. This function is triggered when the page
 * is updated.  We just grab the posted data and use wp_set_object_terms() to save it.
 *
 * @param int $user_id The ID of the user to save the terms for.
 */
function create_save_user_user_category_terms( $user_id ) {

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

// Hook to 'admin_init' to make sure the class is loaded before
// (in case using the class in another plugin)
add_action( 'admin_init', 'create_register_taxonomy_meta_boxes' );

/**
 * Register meta boxes
 *
 * @return void
 */
function create_register_taxonomy_meta_boxes() {
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
 * Temporary function to migrate users from old data structure
 */
function create_migrate_users() {

	$users = get_users( array( 'fields' => 'ID' ) );
	foreach ( $users as $user ) {

		// Get the user category
		$user_categories = wp_get_object_terms( $user, 'user_category' );
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

		$user_meta['types'] 			= $types;
		$user_meta['primary_jobs'] 		= $primary_jobs;
		$user_meta['website']			= get_user_meta( $user, 'user_website', true );
		$user_meta['bio']				= get_the_author_meta( 'description', $user );
		$user_meta['phone']				= get_user_meta( $user, 'user_phone', true );
		$user_meta['zip_code']			= get_user_meta( $user, 'user_zip', true );
		$user_meta['twitter']			= get_user_meta( $user, 'user_twitter', true );
		$user_meta['linkedin_url']		= get_user_meta( $user, 'user_linkedin', true );
		$user_meta['skills']			= unserialize( get_user_meta( $user, 'user_skills', true ) );

		// Get the site-specific user meta
		$blog_id = get_current_blog_id();
		$blog_details = get_blog_details( $blog_id );
		update_user_meta( $user, 'user_meta_'. str_replace( '/', '', $blog_details->path ), $user_meta );

	}

}
//add_action( 'init', 'create_migrate_users' );