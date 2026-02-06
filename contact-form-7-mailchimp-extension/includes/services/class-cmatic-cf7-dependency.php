<?php
/**
 * Contact Form 7 dependency checker.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_CF7_Dependency {

	const CF7_PLUGIN_FILE = 'contact-form-7/wp-contact-form-7.php';

	const CF7_PLUGIN_DIR = 'contact-form-7';

	public static function init() {
		// Auto-activate CF7 early (before page capability checks).
		add_action( 'admin_init', array( __CLASS__, 'maybe_activate_cf7' ), 1 );
		// Show notice if CF7 not installed.
		add_action( 'admin_notices', array( __CLASS__, 'maybe_show_notice' ) );
	}

	public static function maybe_activate_cf7() {
		if ( self::is_satisfied() ) {
			return;
		}

		if ( self::is_installed() && ! self::is_active() ) {
			self::activate_cf7();
		}
	}

	public static function is_installed() {
		return file_exists( WP_PLUGIN_DIR . '/' . self::CF7_PLUGIN_FILE );
	}

	public static function is_active() {
		return class_exists( 'WPCF7' );
	}

	public static function is_satisfied() {
		return self::is_installed() && self::is_active();
	}

	public static function maybe_show_notice() {
		if ( self::is_satisfied() || self::is_installed() ) {
			return;
		}

		// CF7 not installed - show notice with install link on plugins page.
		$screen = get_current_screen();
		if ( $screen && 'plugins' === $screen->id ) {
			self::render_not_installed_notice();
		}
	}

	public static function activate_cf7() {
		// Must have capability to activate plugins.
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return false;
		}

		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_plugin_active( self::CF7_PLUGIN_FILE ) ) {
			return true;
		}

		// Use silent mode to prevent redirect issues.
		$result = activate_plugin( self::CF7_PLUGIN_FILE, '', false, true );

		return ! is_wp_error( $result );
	}

	private static function render_not_installed_notice() {
		$install_url = wp_nonce_url(
			admin_url( 'update.php?action=install-plugin&plugin=contact-form-7' ),
			'install-plugin_contact-form-7'
		);

		printf(
			'<div class="notice notice-error"><p><strong>Chimpmatic Lite</strong> requires <strong>Contact Form 7</strong> to function. <a href="%s">Install Contact Form 7</a></p></div>',
			esc_url( $install_url )
		);
	}
}
