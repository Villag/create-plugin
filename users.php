<?php

function create_get_user( $user_id ) {

	// Get the site-specific user meta
	$blog_id = get_current_blog_id();
	$blog_details = get_blog_details( $blog_id );

	if ( false === ( $user = get_transient( 'user_meta_'. str_replace( '/', '', $blog_details->path .'_' $user_id ) ) ) {

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

		$userdata						= get_userdata( $user );
		$user_object['ID'] 				= $user;
		$user_object['types'] 			= isset( $types );
		$user_object['primary_jobs'] 	= isset( $primary_jobs );
		$user_object['first_name']		= get_user_meta( $user, 'first_name', true );
		$user_object['last_name']		= get_user_meta( $user, 'last_name', true );
		$user_object['avatar']			= create_get_avatar( $user );

		$user = array_merge( $user_object, $user_meta );
		set_transient( 'user_meta_'. str_replace( '/', '', $blog_details->path .'_' $user_id, $user, 12 * HOUR_IN_SECONDS );

	} else {
		$user = get_transient( 'user_meta_'. str_replace( '/', '', $blog_details->path .'_' $user_id );
	}

	return $user;
}

function create_get_users() {


}


function create_save_profile( $user_id ) {


}