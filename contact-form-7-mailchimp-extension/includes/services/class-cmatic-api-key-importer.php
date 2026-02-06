<?php
/**
 * Mailchimp API key importer.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Api_Key_Importer {

	public static function detect() {
		$mc4wp = get_option( 'mc4wp' );
		if ( ! empty( $mc4wp['api_key'] ) ) {
			return $mc4wp['api_key'];
		}

		$yikes = get_option( 'yikes-mc-api-key' );
		if ( ! empty( $yikes ) ) {
			return $yikes;
		}

		$easy_forms = get_option( 'yikes-easy-mailchimp-extender-api-key' );
		if ( ! empty( $easy_forms ) ) {
			return $easy_forms;
		}

		$woo_mc = get_option( 'mailchimp-woocommerce' );
		if ( ! empty( $woo_mc['mailchimp_api_key'] ) ) {
			return $woo_mc['mailchimp_api_key'];
		}

		$mc4wp_top_bar = get_option( 'mc4wp_top_bar' );
		if ( ! empty( $mc4wp_top_bar['api_key'] ) ) {
			return $mc4wp_top_bar['api_key'];
		}

		return false;
	}

	public static function import_to_newest_form() {
		$existing_key = self::detect();
		if ( ! $existing_key ) {
			return false;
		}

		$form_id = Cmatic_Utils::get_newest_form_id();
		if ( ! $form_id ) {
			return false;
		}

		$option_name = 'cf7_mch_' . $form_id;
		$cf7_mch     = get_option( $option_name, array() );

		if ( ! empty( $cf7_mch['api'] ) ) {
			return false;
		}

		$cf7_mch['api'] = $existing_key;
		update_option( $option_name, $cf7_mch );

		return true;
	}
}
