<?php
/**
 * ChimpMatic Lite multi-ESP component.
 *
 * @package contact-form-7-mailchimp-extension
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing -- Typed signatures follow the existing Lite class convention.
abstract class Cmatic_Lite_Esp_Provider implements Cmatic_Lite_Esp_Provider_Interface {
	private const API_TIMEOUT  = 20;
	private const MAX_ATTEMPTS = 3;

	abstract protected function get_base_url(): string;
	abstract protected function get_validation_path(): string;
	abstract protected function get_lists_path(): string;
	abstract protected function get_next_lists_path( array $body, string $current_path ): string;
	abstract protected function get_fields_path( string $list_id ): string;
	abstract protected function get_auth_headers( string $api_key ): array;
	abstract protected function normalize_lists( array $body ): array;
	abstract protected function normalize_fields( array $body ): array;
	abstract protected function perform_subscription( string $api_key, string $list_id, string $email, string $status, array $merge_vars, array $options ): array;

	final public function validate_key( string $api_key, bool $log_enabled = false ): array {
		$logger = new Cmatic_File_Logger( 'API-Validation-' . $this->get_slug(), $log_enabled );
		if ( '' === trim( $api_key ) ) {
			$this->record_validation( false );
			return array( 'api-validation' => 0 );
		}

		$result = $this->request( $api_key, 'GET', $this->get_validation_path() );
		$this->record_validation( $result['success'] );
		$logger->log( $result['success'] ? 'INFO' : 'ERROR', $result['success'] ? 'API key validated successfully.' : 'API key validation failed.' );

		return array( 'api-validation' => $result['success'] ? 1 : 0 );
	}

	final public function get_lists( string $api_key, bool $log_enabled = false ): array {
		$lists = array();
		$path  = $this->get_lists_path();
		for ( $page = 0; '' !== $path && $page < 100; $page++ ) {
			$result = $this->request( $api_key, 'GET', $path );
			if ( ! $result['success'] ) {
				return array(
					'lisdata'       => array( 'lists' => array() ),
					'merge_fields'  => array(),
					'_cmatic_error' => $result['error'],
				);
			}
			$lists = array_merge( $lists, $this->normalize_lists( $result['body'] ) );
			$path  = $this->get_next_lists_path( $result['body'], $path );
		}
		if ( '' !== $path ) {
			return array(
				'lisdata'       => array( 'lists' => array() ),
				'merge_fields'  => array(),
				'_cmatic_error' => 'Provider list pagination exceeded 100 pages.',
			);
		}

		return array(
			'lisdata'      => array( 'lists' => $lists ),
			'merge_fields' => array(),
		);
	}

	final public function get_merge_fields( string $api_key, string $list_id, bool $log_enabled = false ): array {
		$path = $this->get_fields_path( $list_id );
		if ( '' === $path ) {
			return array( 'merge_fields' => array( 'merge_fields' => $this->normalize_fields( array() ) ) );
		}

		$result = $this->request( $api_key, 'GET', $path );
		if ( ! $result['success'] ) {
			return array(
				'merge_fields'  => array( 'merge_fields' => array() ),
				'_cmatic_error' => $result['error'],
			);
		}

		return array( 'merge_fields' => array( 'merge_fields' => $this->normalize_fields( $result['body'] ) ) );
	}

	public function validate_subscription_options( string $api_key, string $list_id, array $options ): array {
		unset( $api_key, $list_id, $options );
		return array(
			'success' => true,
			'error'   => '',
			'data'    => array(),
		);
	}

	final public function subscribe( string $api_key, string $list_id, string $email, string $status, array $merge_vars, int $form_id, array $options, Cmatic_File_Logger $logger ): void {
		try {
			$result = $this->perform_subscription( $api_key, $list_id, $email, $status, $merge_vars, $options );
			$logger->log(
				$result['success'] ? 'INFO' : 'ERROR',
				$this->get_label() . ' subscription result.',
				array(
					'provider' => $this->get_slug(),
					'success'  => $result['success'],
					'reason'   => $result['reason'],
				)
			);
			Cmatic_Response_Handler::handle_provider_result( $result, $email, $status, $merge_vars, $form_id );
		} catch ( Throwable $exception ) {
			Cmatic_Response_Handler::handle_provider_result( $this->failure_result( 'network_error', $exception->getMessage() ), $email, $status, $merge_vars, $form_id );
		}
	}

	final protected function request( string $api_key, string $method, string $path, array $body = array(), bool $retryable = true ): array {
		$key_hash = hash_hmac( 'sha256', $api_key, wp_salt( 'nonce' ) );
		$rate_key = 'cmatic_provider_backoff_' . $this->get_slug() . '_' . $key_hash;
		if ( get_transient( $rate_key ) ) {
			return $this->failure_result( 'api_error', 'Provider rate limit backoff is active.' );
		}

		$args = array(
			'headers'   => array_merge(
				array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
					'User-Agent'   => 'ChimpMaticLite/' . SPARTAN_MCE_VERSION,
				),
				$this->get_auth_headers( $api_key )
			),
			'method'    => strtoupper( $method ),
			'timeout'   => self::API_TIMEOUT,
			'sslverify' => true,
		);
		if ( ! empty( $body ) ) {
			$encoded_body = wp_json_encode( $body );
			if ( false === $encoded_body ) {
				return $this->failure_result( 'configuration_error', 'Provider request body could not be encoded.' );
			}
			$args['body'] = $encoded_body;
		}

		$url = esc_url_raw( rtrim( $this->get_base_url(), '/' ) . '/' . ltrim( $path, '/' ) );
		for ( $attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++ ) {
			$response = wp_remote_request( $url, $args );
			if ( is_wp_error( $response ) ) {
				if ( ! $retryable || self::MAX_ATTEMPTS - 1 === $attempt ) {
					return $this->failure_result( 'network_error', $response->get_error_message() );
				}
				usleep( 250000 * ( 2 ** $attempt ) );
				continue;
			}

			$code     = (int) wp_remote_retrieve_response_code( $response );
			$raw_body = (string) wp_remote_retrieve_body( $response );
			$decoded  = '' === $raw_body ? array() : json_decode( $raw_body, true );
			if ( '' !== $raw_body && ! is_array( $decoded ) ) {
				return $this->failure_result( 'api_error', 'Provider returned invalid JSON.' );
			}
			$decoded = is_array( $decoded ) ? $decoded : array();
			if ( $code >= 200 && $code < 300 ) {
				return array(
					'success' => true,
					'data'    => $decoded,
					'body'    => $decoded,
					'error'   => '',
					'reason'  => '',
				);
			}

			if ( $retryable && ( 429 === $code || $code >= 500 ) && self::MAX_ATTEMPTS - 1 !== $attempt ) {
				usleep( 250000 * ( 2 ** $attempt ) );
				continue;
			}
			if ( 429 === $code ) {
				set_transient( $rate_key, 1, MINUTE_IN_SECONDS );
			}

			return $this->failure_result( 'api_error', $this->extract_error( $decoded, $code ) );
		}

		return $this->failure_result( 'network_error', 'Provider request exhausted all attempts.' );
	}

	final protected function subscription_result( array $response, string $email, array $merge_vars ): array {
		if ( ! $response['success'] ) {
			return $response;
		}

		return array(
			'success' => true,
			'data'    => array(
				'email_address' => $email,
				'merge_fields'  => $merge_vars,
			),
			'body'    => $response['body'],
			'error'   => '',
			'reason'  => '',
		);
	}

	final protected function failure_result( string $reason, string $error ): array {
		return array(
			'success' => false,
			'data'    => array(),
			'body'    => array(),
			'error'   => $error,
			'reason'  => $reason,
		);
	}

	private function extract_error( array $body, int $code ): string {
		if ( isset( $body['errors'][0]['detail'] ) ) {
			return sanitize_text_field( (string) $body['errors'][0]['detail'] );
		}
		foreach ( array( 'detail', 'message', 'error' ) as $key ) {
			if ( isset( $body[ $key ] ) && is_scalar( $body[ $key ] ) ) {
				return sanitize_text_field( (string) $body[ $key ] );
			}
		}
		return 'HTTP ' . $code;
	}

	private function record_validation( bool $success ): void {
		$key = $success ? 'api.setup_first_success' : 'api.setup_first_failure';
		if ( ! Cmatic_Options_Repository::get_option( $key ) ) {
			Cmatic_Options_Repository::set_option( $key, time() );
		}
		if ( ! $success ) {
			Cmatic_Options_Repository::set_option( 'api.setup_last_failure', time() );
			$count = (int) Cmatic_Options_Repository::get_option( 'api.setup_failure_count', 0 );
			Cmatic_Options_Repository::set_option( 'api.setup_failure_count', $count + 1 );
		}
	}
}
