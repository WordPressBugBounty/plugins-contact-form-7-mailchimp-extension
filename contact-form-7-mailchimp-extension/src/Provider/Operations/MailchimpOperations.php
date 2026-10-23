<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

declare(strict_types=1);

namespace Cmatic\Lite\Provider\Operations;

use Cmatic\Lite\Provider\Descriptor;
use Cmatic\Lite\Provider\HttpTransport;
use Cmatic\Lite\Submission\Feedback;
use Cmatic\Lite\Submission\Outcome;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing -- Typed signatures follow the existing Lite class convention.
final class MailchimpOperations implements OperationsInterface {
	const MASK_LENGTH = 20;

	/** @var Descriptor */
	private $descriptor;

	/** @var HttpTransport */
	private $transport;

	public function __construct( Descriptor $descriptor, HttpTransport $transport ) {
		$this->descriptor = $descriptor;
		$this->transport  = $transport;
	}

	public static function mask_api_key( string $key ): string {
		if ( empty( $key ) || strlen( $key ) < 12 ) {
			return $key;
		}
		$prefix = substr( $key, 0, 8 );
		$suffix = substr( $key, -4 );
		return $prefix . str_repeat( '•', self::MASK_LENGTH ) . $suffix;
	}

	public function generate_headers( string $token ): array {
		return array(
			'headers'   => $this->descriptor->headers( sanitize_text_field( $token ) ),
			'timeout'   => HttpTransport::TIMEOUT,
			'sslverify' => true,
		);
	}

	public function get( string $token, string $url ): array {
		$args           = $this->generate_headers( $token );
		$send           = $args;
		$send['method'] = 'GET';
		$response       = $this->transport->raw( $url, $send );
		if ( $response instanceof \WP_Error ) {
			return array( false, $args, $response );
		}
		$body = wp_remote_retrieve_body( $response );
		return array( json_decode( $body, true ), $args, $response );
	}

	public function put( string $token, string $url, string $body ): array {
		$args           = $this->generate_headers( $token );
		$args['body']   = $body;
		$args['method'] = 'PUT';
		$response       = $this->transport->raw( $url, $args );
		if ( $response instanceof \WP_Error ) {
			return array( false, $response );
		}
		$response_body = wp_remote_retrieve_body( $response );
		return array( json_decode( $response_body, true ), $response );
	}

	/**
	 * @return array{api-validation: int, account_name?: string}
	 */
	public function validate_key( string $api_key, bool $log_enabled = false ): array {
		$logger = new \Cmatic_File_Logger( 'API-Validation', $log_enabled );

		try {
			if ( empty( $api_key ) || ! preg_match( '/^[a-f0-9]{32}-[a-z]{2,3}\d+$/', $api_key ) ) {
				$logger->log( 'ERROR', 'Invalid API Key format provided.', self::mask_api_key( $api_key ) );
				$this->record_failure();
				return array( 'api-validation' => 0 );
			}

			list( $key, $dc ) = explode( '-', $api_key );
			if ( empty( $key ) || empty( $dc ) ) {
				$this->record_failure();
				return array( 'api-validation' => 0 );
			}

			$response = $this->get( $key, $this->base_url( $api_key ) . '/' );

			if ( ! is_array( $response[2] ) || 200 !== wp_remote_retrieve_response_code( $response[2] ) ) {
				$error = $response[2] instanceof \WP_Error ? $response[2]->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( is_array( $response[2] ) ? $response[2] : array() );
				$logger->log( 'ERROR', 'API Key validation failed.', $error );
				$this->record_failure();
				return array( 'api-validation' => 0 );
			}

			$logger->log( 'INFO', 'API Key validated successfully.' );
			$this->record_success();

			$result = array( 'api-validation' => 1 );

			$body = is_array( $response[0] ) ? $response[0] : array();
			if ( ! empty( $body['account_name'] ) && is_scalar( $body['account_name'] ) ) {
				$result['account_name'] = sanitize_text_field( (string) $body['account_name'] );
			}

			return $result;

		} catch ( \Exception $e ) {
			$logger->log( 'CRITICAL', 'API validation threw an exception.', $e->getMessage() );
			$this->record_failure();
			return array( 'api-validation' => 0 );
		}
	}

	/**
	 * @return array{lisdata: array, merge_fields?: array}
	 */
	public function get_lists( string $api_key, bool $log_enabled = false ): array {
		$logger = new \Cmatic_File_Logger( 'List-Retrieval', $log_enabled );

		try {
			$parts = explode( '-', $api_key );
			$key   = $parts[0];
			$dc    = $parts[1] ?? '';
			if ( empty( $key ) || empty( $dc ) ) {
				return array( 'lisdata' => array() );
			}

			$response = $this->get( $key, $this->base_url( $api_key ) . '/lists?count=999' );

			if ( ! is_array( $response[2] ) || 200 !== wp_remote_retrieve_response_code( $response[2] ) ) {
				$error = $response[2] instanceof \WP_Error ? $response[2]->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( is_array( $response[2] ) ? $response[2] : array() );
				$logger->log( 'ERROR', 'Failed to retrieve lists from Mailchimp.', $error );
				return array( 'lisdata' => array() );
			}

			$logger->log( 'INFO', 'Successfully retrieved lists from Mailchimp.', $response[0] );
			return array(
				'lisdata'      => is_array( $response[0] ) ? $response[0] : array(),
				'merge_fields' => array(),
			);

		} catch ( \Exception $e ) {
			$logger->log( 'CRITICAL', 'List retrieval threw an exception.', $e->getMessage() );
			return array( 'lisdata' => array() );
		}
	}

	/**
	 * @return array{merge_fields: array}
	 */
	public function get_merge_fields( string $api_key, string $list_id, bool $log_enabled = false ): array {
		$logger = new \Cmatic_File_Logger( 'Merge-Fields-Retrieval', $log_enabled );

		if ( empty( $api_key ) || empty( $list_id ) ) {
			return array( 'merge_fields' => array() );
		}

		try {
			$parts = explode( '-', $api_key );
			$key   = $parts[0];
			$dc    = $parts[1] ?? '';
			if ( empty( $key ) || empty( $dc ) ) {
				return array( 'merge_fields' => array() );
			}

			$response = $this->get( $key, $this->base_url( $api_key ) . '/' . ltrim( $this->descriptor->fields_path( $list_id ), '/' ) );

			if ( ! is_array( $response[2] ) || 200 !== wp_remote_retrieve_response_code( $response[2] ) ) {
				$error = $response[2] instanceof \WP_Error ? $response[2]->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( is_array( $response[2] ) ? $response[2] : array() );
				$logger->log( 'ERROR', 'Failed to retrieve merge fields from Mailchimp.', $error );
				return array( 'merge_fields' => array() );
			}

			$logger->log( 'INFO', 'Successfully retrieved merge fields from Mailchimp.', $response[0] );
			return array( 'merge_fields' => is_array( $response[0] ) ? $response[0] : array() );

		} catch ( \Exception $e ) {
			$logger->log( 'CRITICAL', 'Merge fields retrieval threw an exception.', $e->getMessage() );
			return array( 'merge_fields' => array() );
		}
	}

	public function validate_subscription_options( string $api_key, string $list_id, array $options ): array {
		unset( $api_key, $list_id, $options );
		return array(
			'success' => true,
			'error'   => '',
			'data'    => array(),
		);
	}

	public function perform_subscription( string $api_key, string $list_id, string $email, string $status, array $merge_vars, array $options ): array {
		unset( $options );
		$response = $this->put( $api_key, $this->member_url( $api_key, $list_id, $email ), (string) wp_json_encode( self::build_payload( $email, $status, $merge_vars ) ) );
		if ( false === $response[0] ) {
			return HttpTransport::failure( 'network_error', $response[1] instanceof \WP_Error ? $response[1]->get_error_message() : '' );
		}
		$decoded = is_array( $response[0] ) ? $response[0] : array();
		return array(
			'success' => true,
			'data'    => $decoded,
			'body'    => $decoded,
			'error'   => '',
			'reason'  => '',
		);
	}

	public function subscribe( string $api_key, string $list_id, string $email, string $status, array $merge_vars, int $form_id, array $options, \Cmatic_File_Logger $logger ): void {
		unset( $options );
		try {
			$logger->log( 'INFO', 'Starting subscription process.', compact( 'email', 'list_id' ) );

			$payload = self::build_payload( $email, $status, $merge_vars );
			$url     = $this->member_url( $api_key, $list_id, $email );

			$logger->log( 'INFO', 'Sending data to Mailchimp.', compact( 'url', 'payload' ) );

			$response = $this->put( $api_key, $url, (string) wp_json_encode( $payload ) );
			$api_data = isset( $response[0] ) && is_array( $response[0] ) ? $response[0] : array();

			$logger->log( 'INFO', 'Mailchimp API Response.', $api_data );

			Outcome::handle( $response, $api_data, $email, $status, $merge_vars, $form_id, $logger );

		} catch ( \Throwable $e ) {
			$logger->log( 'CRITICAL', 'Subscription process failed with exception.', $e->getMessage() );
			Feedback::set_result( Feedback::failure( 'network_error', $e->getMessage(), $email ) );
		}
	}

	public function create_field( string $api_key, array $spec, bool $log_enabled = false ): array {
		unset( $api_key, $spec, $log_enabled );
		return HttpTransport::failure( 'configuration_error', 'This provider does not support creating fields.' );
	}

	public function lookup( string $api_key, string $email ): array {
		unset( $api_key, $email );
		return HttpTransport::failure( 'configuration_error', 'This provider does not support subscriber lookup.' );
	}

	public function request( string $api_key, string $method, string $path, array $body = array(), bool $retryable = true ): array {
		return $this->transport->request(
			$this->descriptor->slug(),
			$api_key,
			$method,
			$this->base_url( $api_key ) . '/' . ltrim( $path, '/' ),
			$this->descriptor->headers( sanitize_text_field( $api_key ) ),
			$body,
			$retryable
		);
	}

	public function normalize_lists( array $body ): array {
		return isset( $body['lists'] ) && is_array( $body['lists'] ) ? $body['lists'] : array();
	}

	public function normalize_fields( array $body ): array {
		return isset( $body['merge_fields'] ) && is_array( $body['merge_fields'] ) ? $body['merge_fields'] : array();
	}

	private function base_url( string $api_key ): string {
		$parts = explode( '-', $api_key );
		return str_replace( '{dc}', $parts[1] ?? '', $this->descriptor->base_url() );
	}

	private function member_url( string $api_key, string $list_id, string $email ): string {
		return $this->base_url( $api_key ) . '/lists/' . $list_id . '/members/' . md5( strtolower( $email ) ); // nosemgrep: weak-crypto -- non-cryptographic identifier: Mailchimp subscriber hash is md5 of the lowercased email
	}

	/**
	 * @param array<string,mixed> $merge_vars
	 * @return array<string,mixed>
	 */
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

	private function record_failure(): void {
		if ( ! \Cmatic_Options_Repository::get_option( 'api.setup_first_failure' ) ) {
			\Cmatic_Options_Repository::set_option( 'api.setup_first_failure', time() );
		}
		\Cmatic_Options_Repository::set_option( 'api.setup_last_failure', time() );
		$count = \Cmatic_Options_Repository::get_option( 'api.setup_failure_count', 0 );
		\Cmatic_Options_Repository::set_option( 'api.setup_failure_count', ( is_numeric( $count ) ? (int) $count : 0 ) + 1 );
	}

	private function record_success(): void {
		if ( ! \Cmatic_Options_Repository::get_option( 'api.setup_first_success' ) ) {
			\Cmatic_Options_Repository::set_option( 'api.setup_first_success', time() );
		}
	}
}
