<?php
/*
Plugin Name: Easy Digital Downloads - Braintree
Plugin URI: https://easydigitaldownloads.com/extensions/braintree-gateway/
Description: Accept credit card payments in EDD using your Braintree merchant account.
Author: Pippin Williamson
Author URI: https://easydigitaldownloads.com
Version: 1.1.4
Text Domain: edd-braintree
Domain Path: languages

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

/* License and Update from EDD */
if ( class_exists( 'EDD_License' ) && is_admin() ) {
	$edd_bt_license = new EDD_License( __FILE__, 'Braintree', '1.1.4', 'Pippin Williamson', 'braintree_license_key' );
}

/* CONSTANTS
------------------------------------------ */

/* Get Plugin Path */
define( 'EDDBTGW_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );


/* LOAD
------------------------------------------ */

/* Load Utility Functions */
require_once( EDDBTGW_PATH . 'includes/functions.php' );

/* Laod Setup */
require_once( EDDBTGW_PATH . 'includes/setup.php' );

/* Load Setting */
require_once( EDDBTGW_PATH . 'includes/settings.php' );

/* Load Gateway Processing */
require_once( EDDBTGW_PATH . 'includes/gateway.php' );

/* Load Payment */
require_once( EDDBTGW_PATH . 'includes/payment.php' );



/* LANGUAGE SETUP
------------------------------------------ */

/* Load textdomain on init */
add_action( 'init', 'edd_braintree_textdomain' );

/**
 * Textdomain
 */
function edd_braintree_textdomain() {

	// Set filter for plugin's languages directory
	$edd_lang_dir = dirname( plugin_basename( __FILE__ ) ) . '/languages/';
	$edd_lang_dir = apply_filters( 'edd_braintree_languages_directory', $edd_lang_dir );

	// Load the translations
	load_plugin_textdomain( 'edd-braintree', false, $edd_lang_dir );
}

