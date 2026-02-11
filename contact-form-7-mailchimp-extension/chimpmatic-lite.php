<?php
/**
 * Plugin Name: Connect Contact Form 7 and Mailchimp
 * Plugin URI: https://renzojohnson.com/contributions/contact-form-7-mailchimp-extension
 * Description: Connect Contact Form 7 to Mailchimp and automatically sync form submissions to your newsletter lists. Streamline your email marketing effortlessly.
 * Version: 0.9.75
 * Author: Renzo Johnson
 * Author URI: https://renzojohnson.com
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: chimpmatic-lite
 * Domain Path: /languages/
 * Requires at least: 6.1
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

if ( ! function_exists( 'add_filter' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'SPARTAN_MCE_VERSION' ) ) {
	define( 'SPARTAN_MCE_VERSION', '0.9.75' );

	define( 'SPARTAN_MCE_PLUGIN_FILE', __FILE__ );
	define( 'SPARTAN_MCE_PLUGIN_BASENAME', plugin_basename( SPARTAN_MCE_PLUGIN_FILE ) );
	define( 'SPARTAN_MCE_PLUGIN_DIR', plugin_dir_path( SPARTAN_MCE_PLUGIN_FILE ) );
	define( 'SPARTAN_MCE_PLUGIN_URL', plugin_dir_url( SPARTAN_MCE_PLUGIN_FILE ) );

	if ( ! defined( 'CMATIC_LOG_OPTION' ) ) {
		define( 'CMATIC_LOG_OPTION', 'cmatic_log_on' );
	}

	if ( ! defined( 'CMATIC_LITE_FIELDS' ) ) {
		define( 'CMATIC_LITE_FIELDS', 4 );
	}
}


require_once SPARTAN_MCE_PLUGIN_DIR . 'includes/bootstrap.php';

if ( ! function_exists( 'mce_get_cmatic' ) ) {
	function mce_get_cmatic( $key, $default = null ) {
		return Cmatic_Options_Repository::get_option( $key, $default );
	}
}
