<?php
/**
 * Form submission handler.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Submission_Handler {

	public static function init(): void {
		if ( ! defined( 'CMATIC_VERSION' ) ) {
			add_action( 'wpcf7_before_send_mail', array( __CLASS__, 'process_submission' ) );
		}
	}

	public static function process_submission( $contact_form ): void {
		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return;
		}

		$form_id = $contact_form->id();
		$cf7_mch = get_option( 'cf7_mch_' . $form_id );

		if ( ! self::is_configured( $cf7_mch, $form_id ) ) {
			return;
		}

		$log_enabled = (bool) Cmatic_Options_Repository::get_option( 'debug', false );
		$logger      = new Cmatic_File_Logger( 'api-events', $log_enabled );
		$posted_data = $submission->get_posted_data();

		$email = Cmatic_Email_Extractor::extract( $cf7_mch, $posted_data );
		if ( ! is_email( $email ) ) {
			$logger->log( 'WARNING', 'Subscription attempt with invalid email address.', $email );
			Cmatic_Submission_Feedback::set_result( Cmatic_Submission_Feedback::failure( 'invalid_email', '', $email ) );
			return;
		}

		// New-schema configs store 'list' as an array of ids; passing it raw into the
		// string-typed replace_tags() fataled every submission on such forms (TypeError).
		$list_value = $cf7_mch['list'] ?? '';
		if ( is_array( $list_value ) ) {
			$list_value = (string) ( reset( $list_value ) ?: '' );
		}
		$list_id = Cmatic_Email_Extractor::replace_tags( $list_value, $posted_data );
		$status  = Cmatic_Status_Resolver::resolve( $cf7_mch, $posted_data, $logger );

		if ( null === $status ) {
			return; // Subscription skipped.
		}

		$merge_vars = Cmatic_Merge_Vars_Builder::build( $cf7_mch, $posted_data );

		$api_key = self::resolve_api_key( $form_id, $cf7_mch );

		Cmatic_Mailchimp_Subscriber::subscribe( $api_key, $list_id, $email, $status, $merge_vars, $form_id, $logger );
	}

	private static function is_configured( $cf7_mch, int $form_id = 0 ): bool {
		if ( empty( $cf7_mch ) ) {
			return false;
		}

		// Standard path: has API key + validation.
		if ( ! empty( $cf7_mch['api-validation'] )
			&& 1 === (int) $cf7_mch['api-validation']
			&& ! empty( $cf7_mch['api'] ) ) {
			return true;
		}

		// OAuth path: has auth_type=oauth + validation + encrypted credentials.
		if ( isset( $cf7_mch['auth_type'] ) && 'oauth' === $cf7_mch['auth_type']
			&& ! empty( $cf7_mch['api-validation'] )
			&& 1 === (int) $cf7_mch['api-validation'] ) {
			$auth_manager = Cmatic_Lite_Container::get( 'auth.manager' );
			if ( $auth_manager && $auth_manager->has_oauth( $form_id ) ) {
				return true;
			}
		}

		if ( $form_id && empty( $cf7_mch['api'] ) ) {
			$auth_manager = Cmatic_Lite_Container::get( 'auth.manager' );
			if ( $auth_manager && $auth_manager->has_oauth( $form_id ) ) {
				return true;
			}
		}

		return false;
	}

	private static function resolve_api_key( int $form_id, array $cf7_mch ): string {
		$auth_manager = Cmatic_Lite_Container::get( 'auth.manager' );
		if ( $auth_manager ) {
			return $auth_manager->resolve_api_key( $form_id, '', $cf7_mch );
		}
		return $cf7_mch['api'] ?? '';
	}

	public static function replace_tags( string $subject, array $posted_data ): string {
		return Cmatic_Email_Extractor::replace_tags( $subject, $posted_data );
	}

	private function __construct() {}
}
