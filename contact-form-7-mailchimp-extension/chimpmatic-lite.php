<?php
/**
 * Plugin Name: Connect Contact Form 7 and Mailchimp
 * Plugin URI: https://chimpmatic.com
 * Description: Connect Contact Form 7 to Mailchimp, Brevo, MailerLite, or Klaviyo with per-form destinations, field mapping, and provider-specific opt-in.
 * Version: 0.9.81.05
 * Author: Renzo Johnson
 * Author URI: https://renzojohnson.com
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: chimpmatic-lite
 * Domain Path: /languages/
 * Requires at least: 6.4
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
	define( 'SPARTAN_MCE_VERSION', '0.9.81.05' );
	define( 'SPARTAN_MCE_RELEASE_TRAIN', '2026.07.18.17' );

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
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy public compatibility alias.
	function mce_get_cmatic( $key, $default = null ) {
		return Cmatic_Options_Repository::get_option( $key, $default );
	}
}

if ( ! function_exists( 'cmatic_get_cmatic' ) ) {
	function cmatic_get_cmatic( $key, $default = null ) {
		return mce_get_cmatic( $key, $default );
	}
}
