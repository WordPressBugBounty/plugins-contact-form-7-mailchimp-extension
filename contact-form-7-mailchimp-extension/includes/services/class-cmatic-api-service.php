<?php
/**
 * Mailchimp API service.
 *
 * @package   contact-form-7-mailchimp-extension
 * @author    renzo.johnson@gmail.com
 * @copyright 2014-2026 https://renzojohnson.com
 * @license   GPL-3.0+
 */

defined( 'ABSPATH' ) || exit;

final class Cmatic_Lite_Api_Service implements Cmatic_Api_Client_Interface {

	private const API_TIMEOUT = 20;
	private const MASK_LENGTH = 20;

	public static function mask_api_key( string $key ): string {
		if ( empty( $key ) || strlen( $key ) < 12 ) {
			return $key;
		}
		$prefix = substr( $key, 0, 8 );
		$suffix = substr( $key, -4 );
		return $prefix . str_repeat( '•', self::MASK_LENGTH ) . $suffix;
	}

	public static function generate_headers( string $token ): array {
		$api_key_part = explode( '-', sanitize_text_field( $token ) )[0] ?? '';
		$user_agent   = 'ChimpMaticLite/' . SPARTAN_MCE_VERSION . '; WordPress/' . get_bloginfo( 'version' );

		return array(
			'headers'   => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'apikey ' . $api_key_part,
				'User-Agent'    => $user_agent,
			),
			'timeout'   => self::API_TIMEOUT,
			'sslverify' => true,
		);
	}

	public static function get( string $token, string $url ): array {
		$args     = self::generate_headers( $token );
		$response = wp_remote_get( esc_url_raw( $url ), $args );
		if ( is_wp_error( $response ) ) {
			return array( false, $args, $response );
		}
		$body = wp_remote_retrieve_body( $response );
		return array( json_decode( $body, true ), $args, $response );
	}

	public static function put( string $token, string $url, string $body ): array {
		$args           = self::generate_headers( $token );
		$args['body']   = $body;
		$args['method'] = 'PUT';
		$response       = wp_remote_request( esc_url_raw( $url ), $args );
		if ( is_wp_error( $response ) ) {
			return array( false, $response );
		}
		$response_body = wp_remote_retrieve_body( $response );
		return array( json_decode( $response_body, true ), $response );
	}

	public static function validate_key( string $input, bool $log_enabled = false ): array {
		$logger = new Cmatic_File_Logger( 'API-Validation', $log_enabled );

		try {
			if ( empty( $input ) || ! preg_match( '/^[a-f0-9]{32}-[a-z]{2,3}\d+$/', $input ) ) {
				$logger->log( 'ERROR', 'Invalid API Key format provided.', self::mask_api_key( $input ) );
				self::record_failure();
				return array( 'api-validation' => 0 );
			}

			list( $key, $dc ) = explode( '-', $input );
			if ( empty( $key ) || empty( $dc ) ) {
				self::record_failure();
				return array( 'api-validation' => 0 );
			}

			$url      = "https://{$dc}.api.mailchimp.com/3.0/ping";
			$response = self::get( $key, $url );

			if ( is_wp_error( $response[2] ) || 200 !== wp_remote_retrieve_response_code( $response[2] ) ) {
				$error = is_wp_error( $response[2] ) ? $response[2]->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $response[2] );
				$logger->log( 'ERROR', 'API Key validation ping failed.', $error );
				self::record_failure();
				return array( 'api-validation' => 0 );
			}

			$logger->log( 'INFO', 'API Key validated successfully.' );
			self::record_success();
			return array( 'api-validation' => 1 );

		} catch ( \Exception $e ) {
			$logger->log( 'CRITICAL', 'API validation threw an exception.', $e->getMessage() );
			self::record_failure();
			return array( 'api-validation' => 0 );
		}
	}

	public static function get_lists( string $api_key, bool $log_enabled = false ): array {
		$logger = new Cmatic_File_Logger( 'List-Retrieval', $log_enabled );

		try {
			list( $key, $dc ) = explode( '-', $api_key );
			if ( empty( $key ) || empty( $dc ) ) {
				return array( 'lisdata' => array() );
			}

			$url      = "https://{$dc}.api.mailchimp.com/3.0/lists?count=999";
			$response = self::get( $key, $url );

			if ( is_wp_error( $response[2] ) || 200 !== wp_remote_retrieve_response_code( $response[2] ) ) {
				$error = is_wp_error( $response[2] ) ? $response[2]->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $response[2] );
				$logger->log( 'ERROR', 'Failed to retrieve lists from Mailchimp.', $error );
				return array( 'lisdata' => array() );
			}

			$logger->log( 'INFO', 'Successfully retrieved lists from Mailchimp.', $response[0] );
			return array(
				'lisdata'      => $response[0],
				'merge_fields' => array(),
			);

		} catch ( \Exception $e ) {
			$logger->log( 'CRITICAL', 'List retrieval threw an exception.', $e->getMessage() );
			return array( 'lisdata' => array() );
		}
	}

	public static function get_merge_fields( string $api_key, string $list_id, bool $log_enabled = false ): array {
		$logger = new Cmatic_File_Logger( 'Merge-Fields-Retrieval', $log_enabled );

		if ( empty( $api_key ) || empty( $list_id ) ) {
			return array( 'merge_fields' => array() );
		}

		try {
			list( $key, $dc ) = explode( '-', $api_key );
			if ( empty( $key ) || empty( $dc ) ) {
				return array( 'merge_fields' => array() );
			}

			$url      = "https://{$dc}.api.mailchimp.com/3.0/lists/{$list_id}/merge-fields?count=50";
			$response = self::get( $key, $url );

			if ( is_wp_error( $response[2] ) || 200 !== wp_remote_retrieve_response_code( $response[2] ) ) {
				$error = is_wp_error( $response[2] ) ? $response[2]->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $response[2] );
				$logger->log( 'ERROR', 'Failed to retrieve merge fields from Mailchimp.', $error );
				return array( 'merge_fields' => array() );
			}

			$logger->log( 'INFO', 'Successfully retrieved merge fields from Mailchimp.', $response[0] );
			return array( 'merge_fields' => $response[0] );

		} catch ( \Exception $e ) {
			$logger->log( 'CRITICAL', 'Merge fields retrieval threw an exception.', $e->getMessage() );
			return array( 'merge_fields' => array() );
		}
	}

	private static function record_failure(): void {
		if ( ! Cmatic_Options_Repository::get_option( 'api.setup_first_failure' ) ) {
			Cmatic_Options_Repository::set_option( 'api.setup_first_failure', time() );
		}
		Cmatic_Options_Repository::set_option( 'api.setup_last_failure', time() );
		$count = (int) Cmatic_Options_Repository::get_option( 'api.setup_failure_count', 0 );
		Cmatic_Options_Repository::set_option( 'api.setup_failure_count', $count + 1 );
	}

	private static function record_success(): void {
		if ( ! Cmatic_Options_Repository::get_option( 'api.setup_first_success' ) ) {
			Cmatic_Options_Repository::set_option( 'api.setup_first_success', time() );
		}
	}

	private function __construct() {}
}
