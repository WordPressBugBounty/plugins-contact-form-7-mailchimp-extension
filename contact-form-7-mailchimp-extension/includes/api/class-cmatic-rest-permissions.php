<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Rest_Permissions {
	private const NAMESPACES = array( '/chimpmatic-lite/v1/', '/cmatic/v1/', '/cmatic/' );

	public const FORM_ROUTES = array(
		'form/field',
		'form/setting',
		'merge-fields',
		'notices/act',
		'providers/consent/validate',
		'providers/fields',
		'providers/reset',
		'settings/reset',
	);

	public static function init(): void {
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'guard' ), 5, 3 );
	}

	/**
	 * @param mixed $response
	 * @param mixed $handler
	 * @param mixed $request
	 * @return mixed
	 */
	public static function guard( $response, $handler, $request ) {
		unset( $handler );
		if ( is_wp_error( $response ) || ! $request instanceof WP_REST_Request ) {
			return $response;
		}
		$tier = self::tier( (string) $request->get_route() );
		if ( '' === $tier ) {
			return $response;
		}
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- Contact Form 7 registers these capabilities.
		$allowed = 'form' === $tier ? ( current_user_can( 'wpcf7_edit_contact_forms' ) || current_user_can( 'wpcf7_manage_integration' ) ) : current_user_can( 'wpcf7_manage_integration' );
		if ( $allowed ) {
			return $response;
		}
		return new WP_Error(
			'rest_forbidden',
			esc_html__( 'You do not have permission to access this endpoint.', 'contact-form-7-mailchimp-extension' ),
			array( 'status' => is_user_logged_in() ? 403 : 401 )
		);
	}

	public static function tier( string $route ): string {
		foreach ( self::NAMESPACES as $namespace ) {
			if ( 0 === strpos( $route, $namespace ) ) {
				return in_array( trim( substr( $route, strlen( $namespace ) ), '/' ), self::FORM_ROUTES, true ) ? 'form' : 'admin';
			}
		}
		return '';
	}

	public static function lookup_limit( string $bucket, int $form_id, string $email ): bool {
		$base        = 'cmatic_' . sanitize_key( $bucket ) . '_lookup_' . get_current_user_id() . '_' . $form_id;
		$target      = $base . '_' . substr( hash_hmac( 'sha256', strtolower( $email ), wp_salt( 'nonce' ) ), 0, 24 );
		$total_value = get_transient( $base );
		$same_value  = get_transient( $target );
		$total       = is_numeric( $total_value ) ? (int) $total_value : 0;
		$same        = is_numeric( $same_value ) ? (int) $same_value : 0;
		if ( $total >= 10 || $same >= 3 ) {
			return false;
		}
		set_transient( $base, $total + 1, 10 * MINUTE_IN_SECONDS );
		set_transient( $target, $same + 1, 10 * MINUTE_IN_SECONDS );
		return true;
	}

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
