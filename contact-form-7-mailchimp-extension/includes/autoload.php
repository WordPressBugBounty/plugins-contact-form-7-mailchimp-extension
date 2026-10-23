<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'cmatic_lite_autoload' ) ) {
	function cmatic_lite_autoload( string $class ): void {
		$prefix = 'Cmatic\\Lite\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );
		$file     = dirname( __DIR__ ) . '/src/' . $relative . '.php';
		if ( is_file( $file ) ) {
			require $file;
		}
	}
	spl_autoload_register( 'cmatic_lite_autoload' );
}
