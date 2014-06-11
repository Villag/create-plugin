<?php
/**
 * Create.
 *
 * An engine for a directory of creative people.
 *
 * @package   Create
 * @author    Patrick <patrick@developdaly.com>
 * @license   GPL-2.0+
 * @link      http://createdenton.com
 * @copyright 2014 CreateDenton
 *
 * @wordpress-plugin
 * Plugin Name:       Create
 * Plugin URI:        https://github.com/Villag/create-plugin
 * Description:       An engine for a directory of creative people.
 * Version:           2.0.0
 * Author:            Patrick Daly
 * Author URI:        http://developdaly.com
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

/*----------------------------------------------------------------------------*
 * Public-Facing Functionality
 *----------------------------------------------------------------------------*/

require_once( plugin_dir_path( __FILE__ ) . 'public/class-create.php' );

/*
 * Register hooks that are fired when the plugin is activated or deactivated.
 * When the plugin is deleted, the uninstall.php file is loaded.
 */
register_activation_hook( __FILE__, array( 'Create', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Create', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Create', 'get_instance' ) );