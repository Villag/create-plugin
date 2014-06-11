<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package   Create
 * @author    Patrick Daly <patrick@developdaly.com>
 * @license   GPL-2.0+
 * @link      https://github.com/Villag/create-plugin
 * @copyright 2014 CreateDenton
 */

// If uninstall not called from WordPress, then exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// @TODO: Define uninstall functionality here