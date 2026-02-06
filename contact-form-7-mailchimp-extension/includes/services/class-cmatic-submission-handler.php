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

		if ( ! self::is_configured( $cf7_mch ) ) {
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

		$list_id = Cmatic_Email_Extractor::replace_tags( $cf7_mch['list'] ?? '', $posted_data );
		$status  = Cmatic_Status_Resolver::resolve( $cf7_mch, $posted_data, $logger );

		if ( null === $status ) {
			return; // Subscription skipped.
		}

		$merge_vars = Cmatic_Merge_Vars_Builder::build( $cf7_mch, $posted_data );

		Cmatic_Mailchimp_Subscriber::subscribe( $cf7_mch['api'], $list_id, $email, $status, $merge_vars, $form_id, $logger );
	}

	private static function is_configured( $cf7_mch ): bool {
		return ! empty( $cf7_mch )
			&& ! empty( $cf7_mch['api-validation'] )
			&& 1 === (int) $cf7_mch['api-validation']
			&& ! empty( $cf7_mch['api'] );
	}

	public static function replace_tags( string $subject, array $posted_data ): string {
		return Cmatic_Email_Extractor::replace_tags( $subject, $posted_data );
	}

	private function __construct() {}
}
