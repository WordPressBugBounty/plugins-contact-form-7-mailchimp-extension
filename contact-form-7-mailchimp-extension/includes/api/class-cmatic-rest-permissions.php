<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Rest_Permissions {
	public static function admin( $request ) {
		if ( ! current_user_can( 'wpcf7_manage_integration' ) ) {
			return new WP_Error(
				'rest_forbidden',
				esc_html__( 'You do not have permission to access this endpoint.', 'contact-form-7-mailchimp-extension' ),
				array( 'status' => 403 )
			);
		}

		return self::nonce( $request );
	}

	public static function form( $request ) {
		$form_id = $request->get_param( 'form_id' );
		if ( (int) $form_id < 1 || 'wpcf7_contact_form' !== get_post_type( (int) $form_id ) ) {
			return new WP_Error( 'rest_forbidden', esc_html__( 'This contact form is unavailable.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 403 ) );
		}
		if ( ( $request->get_param( 'reveal' ) || $request->get_param( 'api_key' ) ) && ! current_user_can( 'wpcf7_manage_integration' ) ) {
			return new WP_Error( 'rest_forbidden', esc_html__( 'An administrator manages this connection.', 'contact-form-7-mailchimp-extension' ), array( 'status' => 403 ) );
		}

		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Contact Form 7 registers this meta capability.
		if ( ! current_user_can( 'wpcf7_edit_contact_form', $form_id ) ) {
			return new WP_Error(
				'rest_forbidden',
				esc_html__( 'You do not have permission to edit this form.', 'contact-form-7-mailchimp-extension' ),
				array( 'status' => 403 )
			);
		}

		return self::nonce( $request );
	}

	public static function editor( $request ) {
		return self::form( $request );
	}

	public static function reset( $request ) {
		return 'nuclear' === $request->get_param( 'type' ) ? self::admin( $request ) : self::form( $request );
	}

	public static function manage_options(): bool {
		return current_user_can( 'wpcf7_manage_integration' );
	}

	public static function manage_options_or_error() {
		if ( ! current_user_can( 'wpcf7_manage_integration' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this endpoint.', 'contact-form-7-mailchimp-extension' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	private static function nonce( $request ) {
		$nonce = $request->get_param( '_wpnonce' );
		if ( null === $nonce ) {
			$nonce = $request->get_header( 'X-WP-Nonce' );
		}
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_cookie_invalid_nonce',
				esc_html__( 'Cookie nonce is invalid.', 'contact-form-7-mailchimp-extension' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	private function __construct() {}
}
