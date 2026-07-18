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
		if ( false === $response[0] ) {
			self::record_outcome( $form_id, false, 'transport_connect' );
			$logger->log( 'ERROR', 'Network request failed.', array( 'response' => $response[1] ) );
			Cmatic_Submission_Feedback::set_result( Cmatic_Submission_Feedback::failure( 'network_error', '', $email ) );
			return;
		}

		if ( empty( $api_data ) ) {
			self::record_outcome( $form_id, false, 'remote_rejected' );
			$logger->log( 'ERROR', 'Empty API response received.' );
			Cmatic_Submission_Feedback::set_result( Cmatic_Submission_Feedback::failure( 'api_error', 'Empty response from Mailchimp API.', $email ) );
			return;
		}

		if ( ! empty( $api_data['errors'] ) ) {
			self::record_outcome( $form_id, false, self::api_failure_class( $api_data ) );
			self::log_api_errors( $api_data['errors'] );
			Cmatic_Submission_Feedback::set_result( Cmatic_Submission_Feedback::parse_api_error( $api_data, $email ) );
			return;
		}

		if ( isset( $api_data['status'] ) && is_int( $api_data['status'] ) && $api_data['status'] >= 400 ) {
			self::record_outcome( $form_id, false, self::api_failure_class( $api_data ) );
			Cmatic_Submission_Feedback::set_result( Cmatic_Submission_Feedback::parse_api_error( $api_data, $email ) );
			return;
		}

		if ( isset( $api_data['title'] ) && stripos( $api_data['title'], 'error' ) !== false ) {
			self::record_outcome( $form_id, false, self::api_failure_class( $api_data ) );
			Cmatic_Submission_Feedback::set_result( Cmatic_Submission_Feedback::parse_api_error( $api_data, $email ) );
			return;
		}

		self::handle_success( $email, $status, $merge_vars, $form_id, $api_data );
	}

	/**
	 * Convert a provider-neutral result into the released feedback/bookkeeping path.
	 *
	 * @param array<string,mixed> $result     Normalized provider result.
	 * @param string              $email      Submitted email.
	 * @param string              $status     Resolved subscription status.
	 * @param array<string,mixed> $merge_vars Submitted provider fields.
	 * @param int                 $form_id    Contact form ID.
	 */
	public static function handle_provider_result( array $result, string $email, string $status, array $merge_vars, int $form_id ): void {
		if ( ! $result['success'] ) {
			if ( 'network_error' === $result['reason'] ) {
				$reason = 'provider_network_error';
			} elseif ( 'configuration_error' === $result['reason'] ) {
				$reason = 'provider_configuration_error';
			} else {
				$reason = 'provider_api_error';
			}
			$failure_class = 'network_error' === $result['reason'] ? 'transport_connect' : ( 'configuration_error' === $result['reason'] ? 'configuration' : 'remote_rejected' );
			self::record_outcome( $form_id, false, $failure_class );
			Cmatic_Submission_Feedback::set_result( Cmatic_Submission_Feedback::failure( $reason, '', $email ) );
			return;
		}

		$provider_data = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : array();
		self::handle_success( $email, $status, $merge_vars, $form_id, $provider_data );
	}

	private static function log_api_errors( array $errors ): void {
		$php_logger = new Cmatic_File_Logger( 'php-errors', (bool) Cmatic_Options_Repository::get_option( 'debug', false ) );
		foreach ( $errors as $error ) {
			$php_logger->log( 'ERROR', 'Mailchimp API Error received.', $error );
		}
	}

	private static function handle_success( string $email, string $status, array $merge_vars, int $form_id, array $api_data ): void {
		self::record_outcome( $form_id, true, 'unknown' );
		self::increment_counter( $form_id );
		self::track_test_modal();
		Cmatic_Submission_Feedback::set_result( Cmatic_Submission_Feedback::success( $email, $status, $merge_vars, $api_data ) );
		do_action( 'cmatic_subscription_success', $form_id, $email );
	}

	private static function record_outcome( int $form_id, bool $success, string $failure_class ): void {
		$config   = get_option( 'cf7_mch_' . $form_id, array() );
		$config   = is_array( $config ) ? $config : array();
		$provider = Cmatic_Lite_Esp_Registry::get_selected( $config );
		\Signls\Sdk\V1\CounterStore::record_outcome( 'contact-form-7-mailchimp-extension', $provider, 'subscribe', $success, $failure_class );
	}

	private static function api_failure_class( array $api_data ): string {
		$status = isset( $api_data['status'] ) && is_numeric( $api_data['status'] ) ? (int) $api_data['status'] : 0;
		if ( 401 === $status || 403 === $status ) {
			return 'auth';
		}
		if ( 429 === $status ) {
			return 'http_429';
		}
		if ( $status >= 500 ) {
			return 'http_5xx';
		}
		if ( $status >= 400 ) {
			return 'http_4xx';
		}
		return 'remote_rejected';
	}

	private static function track_test_modal(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$test_modal = isset( $_POST['_cmatic_test_modal'] ) ? sanitize_text_field( wp_unslash( $_POST['_cmatic_test_modal'] ) ) : '';
		if ( '1' === $test_modal ) {
			Cmatic_Options_Repository::set_option( 'features.test_modal_used', true );
		}
	}

	private static function increment_counter( int $form_id ): void {
		$count = (int) Cmatic_Options_Repository::get_option( 'stats.sent', 0 );
		Cmatic_Options_Repository::set_option( 'stats.sent', $count + 1 );

		$cf7_mch               = get_option( 'cf7_mch_' . $form_id, array() );
		$cf7_mch               = is_array( $cf7_mch ) ? $cf7_mch : array();
		$cf7_mch['stats_sent'] = ( (int) ( $cf7_mch['stats_sent'] ?? 0 ) ) + 1;
		update_option( 'cf7_mch_' . $form_id, $cf7_mch );
	}
}
