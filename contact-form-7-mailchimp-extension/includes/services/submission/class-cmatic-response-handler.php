<?php
/**
 * API response handler.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Response_Handler {

	public static function handle( array $response, array $api_data, string $email, string $status, array $merge_vars, int $form_id, Cmatic_File_Logger $logger ): void {
		// Network failure.
		if ( false === $response[0] ) {
			$logger->log( 'ERROR', 'Network request failed.', array( 'response' => $response[1] ) );
			Cmatic_Submission_Feedback::set_result( Cmatic_Submission_Feedback::failure( 'network_error', '', $email ) );
			return;
		}

		// Empty response.
		if ( empty( $api_data ) ) {
			$logger->log( 'ERROR', 'Empty API response received.' );
			Cmatic_Submission_Feedback::set_result( Cmatic_Submission_Feedback::failure( 'api_error', 'Empty response from Mailchimp API.', $email ) );
			return;
		}

		// API errors array.
		if ( ! empty( $api_data['errors'] ) ) {
			self::log_api_errors( $api_data['errors'] );
			Cmatic_Submission_Feedback::set_result( Cmatic_Submission_Feedback::parse_api_error( $api_data, $email ) );
			return;
		}

		// HTTP error status.
		if ( isset( $api_data['status'] ) && is_int( $api_data['status'] ) && $api_data['status'] >= 400 ) {
			Cmatic_Submission_Feedback::set_result( Cmatic_Submission_Feedback::parse_api_error( $api_data, $email ) );
			return;
		}

		// Error in title.
		if ( isset( $api_data['title'] ) && stripos( $api_data['title'], 'error' ) !== false ) {
			Cmatic_Submission_Feedback::set_result( Cmatic_Submission_Feedback::parse_api_error( $api_data, $email ) );
			return;
		}

		// Success!
		self::handle_success( $email, $status, $merge_vars, $form_id, $api_data );
	}

	private static function log_api_errors( array $errors ): void {
		$php_logger = new Cmatic_File_Logger( 'php-errors', (bool) Cmatic_Options_Repository::get_option( 'debug', false ) );
		foreach ( $errors as $error ) {
			$php_logger->log( 'ERROR', 'Mailchimp API Error received.', $error );
		}
	}

	private static function handle_success( string $email, string $status, array $merge_vars, int $form_id, array $api_data ): void {
		self::increment_counter( $form_id );
		self::track_test_modal();
		Cmatic_Submission_Feedback::set_result( Cmatic_Submission_Feedback::success( $email, $status, $merge_vars, $api_data ) );
		do_action( 'cmatic_subscription_success', $form_id, $email );
	}

	private static function track_test_modal(): void {
		if ( isset( $_POST['_cmatic_test_modal'] ) && '1' === $_POST['_cmatic_test_modal'] ) {
			Cmatic_Options_Repository::set_option( 'features.test_modal_used', true );
		}
	}

	private static function increment_counter( int $form_id ): void {
		// Global counter.
		$count = (int) Cmatic_Options_Repository::get_option( 'stats.sent', 0 );
		Cmatic_Options_Repository::set_option( 'stats.sent', $count + 1 );

		// Per-form counter.
		$cf7_mch = get_option( 'cf7_mch_' . $form_id, array() );
		$cf7_mch['stats_sent'] = ( (int) ( $cf7_mch['stats_sent'] ?? 0 ) ) + 1;
		update_option( 'cf7_mch_' . $form_id, $cf7_mch );
	}
}
