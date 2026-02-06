<?php
/**
 * Mailchimp subscriber service.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

class Cmatic_Mailchimp_Subscriber {

	public static function subscribe( string $api_key, string $list_id, string $email, string $status, array $merge_vars, int $form_id, Cmatic_File_Logger $logger ): void {
		try {
			$logger->log( 'INFO', 'Starting subscription process.', compact( 'email', 'list_id' ) );

			$payload = self::build_payload( $email, $status, $merge_vars );
			$url     = self::build_url( $api_key, $list_id, $email );

			$logger->log( 'INFO', 'Sending data to Mailchimp.', compact( 'url', 'payload' ) );

			$response = Cmatic_Lite_Api_Service::put( $api_key, $url, wp_json_encode( $payload ) );
			$api_data = $response[0] ?? array();

			$logger->log( 'INFO', 'Mailchimp API Response.', $api_data );

			Cmatic_Response_Handler::handle( $response, $api_data, $email, $status, $merge_vars, $form_id, $logger );

		} catch ( \Exception $e ) {
			$logger->log( 'CRITICAL', 'Subscription process failed with exception.', $e->getMessage() );
			Cmatic_Submission_Feedback::set_result( Cmatic_Submission_Feedback::failure( 'network_error', $e->getMessage(), $email ) );
		}
	}

	private static function build_payload( string $email, string $status, array $merge_vars ): array {
		$payload = array(
			'email_address' => $email,
			'status'        => $status,
		);

		if ( ! empty( $merge_vars ) ) {
			$payload['merge_fields'] = (object) $merge_vars;
		}

		return $payload;
	}

	private static function build_url( string $api_key, string $list_id, string $email ): string {
		list( $key, $dc ) = explode( '-', $api_key );
		return "https://{$dc}.api.mailchimp.com/3.0/lists/{$list_id}/members/" . md5( strtolower( $email ) );
	}
}
