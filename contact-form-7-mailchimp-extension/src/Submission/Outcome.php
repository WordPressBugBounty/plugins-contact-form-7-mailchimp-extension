<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

namespace Cmatic\Lite\Submission;

use Cmatic\Lite\Settings\FormSettingsRepository;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite class convention.
final class Outcome {

	public static function handle( array $response, array $api_data, string $email, string $status, array $merge_vars, int $form_id, \Cmatic_File_Logger $logger ): void {
		if ( false === $response[0] ) {
			self::record_outcome( $form_id, false, 'transport_connect', isset( $response[1] ) ? $response[1] : '', 'remote_rejected' );
			$logger->log( 'ERROR', 'Network request failed.', array( 'response' => $response[1] ) );
			Feedback::set_result( Feedback::failure( 'network_error', '', $email ) );
			return;
		}

		if ( empty( $api_data ) ) {
			self::record_outcome( $form_id, false, 'remote_rejected', 'Empty provider response.', 'remote_rejected' );
			$logger->log( 'ERROR', 'Empty API response received.' );
			Feedback::set_result( Feedback::failure( 'api_error', 'Empty response from Mailchimp API.', $email ) );
			return;
		}

		if ( ! empty( $api_data['errors'] ) ) {
			self::record_outcome( $form_id, false, self::api_failure_class( $api_data ), $api_data, 'remote_rejected' );
			self::log_api_errors( $api_data['errors'] );
			Feedback::set_result( Feedback::parse_api_error( $api_data, $email ) );
			return;
		}

		if ( isset( $api_data['status'] ) && is_int( $api_data['status'] ) && $api_data['status'] >= 400 ) {
			self::record_outcome( $form_id, false, self::api_failure_class( $api_data ), $api_data, 'remote_rejected' );
			Feedback::set_result( Feedback::parse_api_error( $api_data, $email ) );
			return;
		}

		if ( isset( $api_data['title'] ) && stripos( $api_data['title'], 'error' ) !== false ) {
			self::record_outcome( $form_id, false, self::api_failure_class( $api_data ), $api_data, 'remote_rejected' );
			Feedback::set_result( Feedback::parse_api_error( $api_data, $email ) );
			return;
		}

		self::handle_success( $email, $status, $merge_vars, $form_id, $api_data );
	}

	/**
	 * @param array<string,mixed> $result     Normalized provider result.
	 * @param string              $email      Submitted email.
	 * @param string              $status     Resolved subscription status.
	 * @param array<string,mixed> $merge_vars Submitted provider fields.
	 * @param int                 $form_id    Contact form ID.
	 */
	public static function handle_provider_result( array $result, string $email, string $status, array $merge_vars, int $form_id ): void {
		if ( true === ( $result['unchanged'] ?? false ) && 'already_subscribed' === ( $result['reason'] ?? '' ) ) {
			$feedback            = Feedback::skipped( 'already_subscribed' );
			$feedback['email']   = $email;
			$feedback['message'] = sanitize_text_field( is_string( $result['message'] ?? null ) ? $result['message'] : '' );
			Feedback::set_result( $feedback );
			return;
		}
		if ( ! $result['success'] ) {
			if ( 'network_error' === $result['reason'] ) {
				$reason = 'provider_network_error';
			} elseif ( 'configuration_error' === $result['reason'] ) {
				$reason = 'provider_configuration_error';
			} elseif ( 'already_subscribed' === $result['reason'] ) {
				$reason = 'already_subscribed';
			} else {
				$reason = 'provider_api_error';
			}
			$failure_class = 'network_error' === $result['reason'] ? 'transport_connect' : ( 'configuration_error' === $result['reason'] ? 'configuration' : 'remote_rejected' );
			self::record_outcome( $form_id, false, $failure_class, $result, 'remote_rejected' );
			Feedback::set_result( Feedback::failure( $reason, '', $email ) );
			return;
		}

		$provider_data = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : array();
		if ( 'queued' === ( $provider_data['status'] ?? '' ) ) {
			$status = 'queued';
		}
		self::handle_success( $email, $status, $merge_vars, $form_id, $provider_data );
	}

	private static function log_api_errors( array $errors ): void {
		$php_logger = new \Cmatic_File_Logger( 'php-errors', (bool) \Cmatic_Options_Repository::get_option( 'debug', false ) );
		foreach ( $errors as $error ) {
			$php_logger->log( 'ERROR', 'Mailchimp API Error received.', $error );
		}
	}

	private static function handle_success( string $email, string $status, array $merge_vars, int $form_id, array $api_data ): void {
		self::record_outcome( $form_id, true, 'unknown', '', 'unknown' );
		self::increment_counter( $form_id );
		self::track_test_modal();
		Feedback::set_result( Feedback::success( $email, $status, $merge_vars, $api_data ) );
		do_action( 'cmatic_subscription_success', $form_id, $email );
	}

	private static function record_outcome( int $form_id, bool $success, string $failure_class, $value, string $fallback ): void {
		try {
			$provider = FormSettingsRepository::load( $form_id )->provider();
		} catch ( \Throwable $error ) {
			return;
		}
		\Cmatic_Lite_Signls_Outcome::record( $provider, 'subscribe', $success, $failure_class, $value, $fallback );
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
		$test_modal = isset( $_POST['_cmatic_test_modal'] ) && is_string( $_POST['_cmatic_test_modal'] ) ? sanitize_text_field( wp_unslash( $_POST['_cmatic_test_modal'] ) ) : '';
		if ( '1' === $test_modal ) {
			\Cmatic_Options_Repository::set_option( 'features.test_modal_used', true );
		}
	}

	private static function increment_counter( int $form_id ): void {
		$count = (int) \Cmatic_Options_Repository::get_option( 'stats.sent', 0 );
		\Cmatic_Options_Repository::set_option( 'stats.sent', $count + 1 );
		FormSettingsRepository::increment_sent( $form_id );
	}

	private function __construct() {}
}
