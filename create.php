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

// Sets up ajax hooks for calling users
add_action( 'wp_ajax_nopriv_create_get_users',	'create_get_users' );
add_action( 'wp_ajax_create_get_users',			'create_get_users' );

// Email user
add_action( 'wp_ajax_nopriv_create_email_user',	'create_email_user' );
add_action( 'wp_ajax_create_email_user',	'create_email_user' );

// After user registration, login user
add_action( 'gform_user_registered', 'create_gravity_registration_autologin', 10, 4 );

// Change Gravity Forms upload path
add_filter( 'gform_upload_path', 'create_change_upload_path', 10, 2 );

// Update avatar in user meta via Gravity Forms
add_action( 'gform_after_submission', 'create_update_avatar', 10, 2 );

// Clear the user query cache when a user updates their profile
add_action( 'profile_update', 'create_profile_update', 10, 2 );

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

function create_mail_from( $email ) {
	return 'info@createdenton.com';
}
add_filter( 'wp_mail_from', 'create_mail_from' );

/**
 * Gets all users for the current site and returns the data as a JSON
 * encoded object for use by an ajax call from the theme.
 */
function create_get_users() {

	if ( false === ( $user_array = get_transient( 'users_query' ) ) ) {
		$users = get_users( array( 'fields' => 'ID' ) );
		shuffle($users);
		foreach ( $users as $user ) {

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

			$userdata						= get_userdata( $user );
			$user_object['ID'] 				= $user;
			$user_object['primary_job'] 	= $primary_jobs;
			$user_object['type']			= $types;
			$user_object['email']			= $userdata->user_email;
			$user_object['first_name']		= get_user_meta( $user, 'first_name', true );
			$user_object['last_name']		= get_user_meta( $user, 'last_name', true );
			$user_object['website']			= get_user_meta( $user, 'user_website', true );
			$user_object['description']		= get_user_meta( $user, 'description', true );
			$user_object['phone']			= get_user_meta( $user, 'user_phone', true );
			$user_object['zip_code']		= get_user_meta( $user, 'user_zip', true );
			$user_object['twitter']			= get_user_meta( $user, 'user_twitter', true );
			$user_object['linkedin_url']	= get_user_meta( $user, 'user_linkedin', true );
			$user_object['skills']			= unserialize( get_user_meta( $user, 'user_skills', true ) );
			$user_object['avatar']			= create_get_avatar( $user );

			if( empty( $user_object['avatar'] ) ) {
				continue;
			}

			$user_array[] = $user_object;
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
function custom_register_with_email($result) {

	if ( $result['user_name'] != '' && is_email( $result['user_name'] ) ) {

		unset( $result['errors']->errors['user_name'] );

	}

	return $result;
}
add_filter('wpmu_validate_user_signup','custom_register_with_email');

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

	if( create_user_errors( $user_id ) == null )
		return true;
	else
		return false;
}

/**
 * Displays the errors a user has
 * (i.e. missing data required to be a valid user)
 */
function create_user_errors( $user_id ) {

	$user_data		= get_userdata( $user_id );
	$email			= $user_data->user_email;

	$user_meta		= get_user_meta( $user_id );
	$first_name		= isset( $user_meta['first_name'][0] );
	$last_name		= isset( $user_meta['last_name'][0] );
	$zip			= isset( $user_meta['user_zip'][0] );
	$primary_job	= isset( $user_meta['user_primary_job'][0] );
	$avatar			= get_user_meta( $user_id, 'avatar', true );

	$errors = array();

	if ( $email == '' )
		$errors[] = ' email';

	if ( !$first_name )
		$errors[] = ' first name';

	if ( !$last_name )
		$errors[] = ' last name';

	if ( !$zip )
		$errors[] = ' zip code';

	if ( !$primary_job )
		$errors[] = ' primary job';

	if ( ! $avatar )
		$errors[] = ' avatar';

	$output = implode( ',', $errors );

	return $output;
}

/**
 * Gets the user's username and lowers its case and replaces any special
 * characters with hyphens.
 */
function create_clean_username( $user_id ) {
	$user_info = get_userdata( $user_id );

	$username = strtolower( $user_info->user_login );

	$output = preg_replace("![^a-z0-9]+!i", "-", $username );

	return $output;
}

/**
 * Get the user information for a OneAll connected user.
 */
function create_get_oneall_user( $user_id, $attribute = '' ) {

	//Read settings
	$settings = get_option ('oa_social_login_settings');

	//API Settings
	$api_connection_handler = ((!empty ($settings ['api_connection_handler']) AND $settings ['api_connection_handler'] == 'fsockopen') ? 'fsockopen' : 'curl');
	$api_connection_use_https = ((!isset ($settings ['api_connection_use_https']) OR $settings ['api_connection_use_https'] == '1') ? true : false);

	$site_subdomain = (!empty ($settings ['api_subdomain']) ? $settings ['api_subdomain'] : '');
	$site_public_key = (!empty ($settings ['api_key']) ? $settings ['api_key'] : '');
	$site_private_key = (!empty ($settings ['api_secret']) ? $settings ['api_secret'] : '');

	//API Access Domain
	$site_domain = $site_subdomain . '.api.oneall.com';

	$user_token = get_user_meta($user_id, 'oa_social_login_user_token', true);

	//Connection Resource
	$resource_uri = 'https://' . $site_domain . '/users/' . $user_token . '.json';

	// Initializing curl
	$ch = curl_init($resource_uri);

	// Configuring curl options
	$options = array(CURLOPT_URL => $resource_uri, CURLOPT_HEADER => 0, CURLOPT_USERPWD => $site_public_key . ":" . $site_private_key, CURLOPT_TIMEOUT => 15, CURLOPT_VERBOSE => 0, CURLOPT_RETURNTRANSFER => 1, CURLOPT_SSL_VERIFYPEER => 1, CURLOPT_FAILONERROR => 0);

	// Setting curl options
	curl_setopt_array($ch, $options);

	// Getting results
	$result = curl_exec($ch);

	$data = json_decode($result);

	$output = '';

	if( isset( $data->response->result ) ){

		if( $attribute == '' ){
			$output = isset( $data->response->result->data->user->identities );
		}

		if( $attribute == 'thumbnail' && isset( $data->response->result->data->user->identities->identity[0]->thumbnailUrl ) ) {
			$output = $data->response->result->data->user->identities->identity[0]->thumbnailUrl;
		}

		if( $attribute == 'picture' && isset( $data->response->result->data->user->identities->identity[0]->pictureUrl ) ) {
			$output = $data->response->result->data->user->identities->identity[0]->pictureUrl;
		}

	} else {
		$output = create_get_avatar_url( get_avatar( $user_id, 150 ) );
	}

	return $output;

}

/**
 * Parses the user's avatar URL from the <img> element.
 */
function create_get_avatar_url( $get_avatar ) {
    preg_match( "/src='(.*?)'/i", $get_avatar, $matches );
    return $matches[1];
}

/**
 * Adds Timthumb to a given image URL.
 */
function create_timthumbit( $image, $width, $height ) {
	$output = get_stylesheet_directory_uri() . "/timthumb.php?src=". $image ."&w=". $width ."&h=". $height ."&zc=1&a=c&f=2";
	return $output;
}

/**
 * Check if the given URL returns a 404.
 */
function create_is_404( $url ) {
	$handle = curl_init($url);

	curl_setopt($handle,  CURLOPT_RETURNTRANSFER, TRUE);

	/* Get the HTML or whatever is linked in $url. */
	$response = curl_exec($handle);

	/* Check for 404 (file not found). */
	$httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);

	if($httpCode == 404) {
	    return false;
	} else {
		return true;
	}

	curl_close($handle);

}

/**
 * Figure out which avatar to use for a user.
 */
function create_choose_avatar( $user_id ) {

	// Make sure the user can edit this user
	if ( !current_user_can( 'edit_user', $user_id ) )
		return false;

	$user = get_user_by( 'id', $user_id );
	$hash = md5( strtolower( trim( $user->user_email ) ) );

	$avatar_local		= basename( get_user_meta( $user_id, 'avatar_local', true ) );
	$avatar_social		= create_get_oneall_user( $user_id, 'thumbnail' );
	$avatar_gravatar	= 'http://www.gravatar.com/avatar/'. $hash .'?s=200&r=pg&d=404';

	if( isset( $avatar_gravatar ) ) {
		if( create_is_404( $avatar_gravatar ) ){
			$check_gravatar		= file_get_contents( $avatar_gravatar );
		} else {
			unset( $avatar_gravatar );
		}
	}

	if( !empty( $avatar_local ) ) {
		echo '<img id="avatar-local" src="'. get_stylesheet_directory_uri() . '/timthumb.php?src='. get_stylesheet_directory() .'/uploads/avatars/'. $avatar_local .'&w=165&h=165&zc=1&a=c&f=2" class="pull-right" width="100">';
	}
	if( !empty( $avatar_social ) ) {
		echo '<img id="avatar-social" src="'. get_stylesheet_directory_uri() . '/timthumb.php?src='. $avatar_social .'&w=165&h=165&zc=1&a=c&f=2" class="pull-right" width="100">';
	}
	if( !empty( $avatar_gravatar ) ) {
		echo '<img id="avatar-gravatar" src="'. get_stylesheet_directory_uri() . '/timthumb.php?src='. $avatar_gravatar .'&w=165&h=165&zc=1&a=c&f=2" class="pull-right" width="100">';
	}
}

/**
 * Get the user's avatar.
 */
function create_get_avatar( $user_id ) {
	global $blog_id;
	$image = get_user_meta( $user_id, 'avatar', true );

	if( empty( $image ) ) {
		return;
	}

	if( file_exists( get_stylesheet_directory() .'/uploads/avatars/'. basename( $image ) ) ) {
		$image =  get_stylesheet_directory() .'/uploads/avatars/'. basename( $image );
	}

	$output	= get_stylesheet_directory_uri() . "/timthumb.php?src=". $image ."&w=165&h=165&zc=1&a=c&f=2";

	$headers = get_headers( $output, 1 );
	if ( $headers[0] != 'HTTP/1.1 200 OK' ) {
		return;
	}

	return $output;
}

/**
 * Changes the default Gravity Forms uploads path.
 */
function create_change_upload_path( $path_info, $form_id ){
   $path_info["path"] = get_stylesheet_directory() .'/uploads/avatars/';
   $path_info["url"] = get_stylesheet_directory_uri() .'/uploads/avatars/';
   return $path_info;
}

/**
 * When the Gravity Forms Profile is updated ensure the avatar is
 * updated, set the primary job, and delete the cached users_query.
 */
function create_update_avatar( $entry, $form ){

	global $current_user;
	get_currentuserinfo();

	$user = get_user_by( 'id', $current_user->ID );
	$hash = md5( strtolower( trim( $user->user_email ) ) );

	$avatar_type = $entry["11"];
	update_user_meta( $current_user->ID, 'avatar_type', $entry["10"] );

	if( $avatar_type == 'avatar_social'){
		update_user_meta( $current_user->ID, 'avatar', create_get_oneall_user( $current_user->ID, 'picture' ) );
	}
	if( $avatar_type == 'avatar_gravatar'){
		update_user_meta( $current_user->ID, 'avatar', 'http://www.gravatar.com/avatar/'. $hash .'?s=150' );
	}
	if( ( $avatar_type == 'avatar_upload' ) &! empty( $entry["10"] ) ){
		update_user_meta( $current_user->ID, 'avatar', $entry["10"] );
		update_user_meta( $current_user->ID, 'avatar_local', $entry["10"] );
	} elseif( $avatar_type == 'avatar_upload' ) {
		$previous_local = get_user_meta( $current_user->ID, 'avatar_local', true );
		update_user_meta( $current_user->ID, 'avatar', $previous_local );
	}

	// Set the primary job
	$term = get_term_by( 'id', intval( $entry['6'] ), 'user_category', ARRAY_A );
	$return = wp_set_object_terms( $current_user->ID, $term['slug'], $term['taxonomy'], false );

}

/**
 * Clear the cached user query so this new avatar will show up
*/
function create_profile_update() {
	delete_transient( 'users_query' );
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
