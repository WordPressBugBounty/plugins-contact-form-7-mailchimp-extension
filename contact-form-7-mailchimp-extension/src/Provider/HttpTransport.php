<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 ChimpMatic
 * @license   GPL-3.0+
 */

declare(strict_types=1);

namespace Cmatic\Lite\Provider;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing -- Typed signatures follow the existing Lite class convention.
final class HttpTransport {
	const TIMEOUT      = 20;
	const MAX_ATTEMPTS = 3;

	/** @var callable */
	private $sender;

	/** @var callable */
	private $sleeper;

	public function __construct( ?callable $sender = null, ?callable $sleeper = null ) {
		$this->sender  = null === $sender ? 'wp_remote_request' : $sender;
		$this->sleeper = null === $sleeper ? 'usleep' : $sleeper;
	}

	/**
	 * @param array<string,string> $headers
	 * @param array<string,mixed>  $body
	 * @param array<string,string> $response_format
	 * @return array<string,mixed>
	 */
	public function request( string $slug, string $api_key, string $method, string $url, array $headers, array $body = array(), bool $retryable = true, array $response_format = array(), bool $private_url = false, bool $preserve_errors = false ): array {
		$key_hash = hash_hmac( 'sha256', $api_key, wp_salt( 'nonce' ) );
		$rate_key = 'cmatic_provider_backoff_' . $slug . '_' . $key_hash;
		if ( get_transient( $rate_key ) ) {
			return self::failure( 'api_error', 'Provider rate limit backoff is active.' );
		}

		$args = array(
			'headers'   => $headers,
			'method'    => strtoupper( $method ),
			'timeout'   => self::TIMEOUT,
			'sslverify' => true,
		);
		if ( $private_url ) {
			$args['redirection'] = 0;
		}
		if ( ! empty( $body ) ) {
			$encoded_body = wp_json_encode( $body );
			if ( false === $encoded_body ) {
				return self::failure( 'configuration_error', 'Provider request body could not be encoded.' );
			}
			$args['body'] = $encoded_body;
		}

		$url = esc_url_raw( $url );
		for ( $attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++ ) {
			try {
				$response = $this->send( $url, $args );
			} catch ( \Throwable $exception ) {
				if ( ! $private_url ) {
					throw $exception;
				}
				return self::failure( 'network_error', Protocol::text( Protocol::redact( $exception->getMessage(), $api_key ) ) );
			}
			if ( $response instanceof \WP_Error ) {
				if ( ! $retryable || self::MAX_ATTEMPTS - 1 === $attempt ) {
					return self::failure( 'network_error', $private_url ? Protocol::text( Protocol::redact( $response->get_error_message(), $api_key ) ) : $response->get_error_message() );
				}
				$this->sleep( 250000 * ( 2 ** $attempt ) );
				continue;
			}

			$code     = (int) wp_remote_retrieve_response_code( $response );
			$raw_body = wp_remote_retrieve_body( $response );
			$decoded  = '' === $raw_body ? array() : json_decode( $raw_body, true );
			if ( $code >= 200 && $code < 300 && is_string( $decoded ) && ! empty( $response_format['string_key'] ) ) {
				$decoded = array( $response_format['string_key'] => $decoded );
			}
			if ( '' !== $raw_body && ! is_array( $decoded ) ) {
				return self::failure( 'api_error', 'Provider returned invalid JSON.' );
			}
			$decoded = is_array( $decoded ) ? $decoded : array();
			if ( $private_url ) {
				$decoded = Protocol::redact( $decoded, $api_key );
				$decoded = is_array( $decoded ) ? $decoded : array();
			}
			if ( $code >= 200 && $code < 300 ) {
				$result = array(
					'success' => true,
					'data'    => $decoded,
					'body'    => $decoded,
					'error'   => '',
					'reason'  => '',
					'status'  => $code,
				);
				foreach ( array( 'total_pages', 'current_page' ) as $key ) {
					if ( isset( $response_format[ $key . '_header' ] ) ) {
						$result[ $key ] = wp_remote_retrieve_header( $response, strtolower( $response_format[ $key . '_header' ] ) );
					}
				}
				return $result;
			}

			if ( $retryable && ( 429 === $code || $code >= 500 ) && self::MAX_ATTEMPTS - 1 !== $attempt ) {
				$retry_after = 429 === $code ? min( 10, max( 0, self::header_int( $response, 'retry-after' ) ) ) : 0;
				$this->sleep( $retry_after > 0 ? $retry_after * 1000000 : 250000 * ( 2 ** $attempt ) );
				continue;
			}
			if ( 429 === $code ) {
				$retry_after = min( 600, max( 1, self::header_int( $response, 'retry-after' ) ) );
				set_transient( $rate_key, 1, $retry_after );
			}

			$error_key         = $response_format['error_key'] ?? '';
			$error             = '' !== $error_key && isset( $decoded[ $error_key ] ) && is_scalar( $decoded[ $error_key ] ) ? sanitize_text_field( (string) $decoded[ $error_key ] ) : self::extract_error( $decoded, $code );
			$failure           = self::failure( 'api_error', $error );
			$failure['status'] = $code;
			if ( $preserve_errors ) {
				$failure['body'] = $decoded;
			}
			return $failure;
		}

		return self::failure( 'network_error', 'Provider request exhausted all attempts.' );
	}

	/**
	 * @param array<mixed> $args
	 * @return array<mixed>|\WP_Error
	 */
	public function raw( string $url, array $args ) {
		return $this->send( esc_url_raw( $url ), $args );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function failure( string $reason, string $error ): array {
		return array(
			'success' => false,
			'data'    => array(),
			'body'    => array(),
			'error'   => $error,
			'reason'  => $reason,
		);
	}

	/**
	 * @param array<mixed> $args
	 * @return array<mixed>|\WP_Error
	 */
	private function send( string $url, array $args ) {
		$sender   = $this->sender;
		$response = $sender( $url, $args );
		if ( $response instanceof \WP_Error ) {
			return $response;
		}
		return is_array( $response ) ? $response : new \WP_Error( 'http_request_failed', 'Provider transport returned nothing.' );
	}

	private function sleep( int $microseconds ): void {
		$sleeper = $this->sleeper;
		$sleeper( $microseconds );
	}

	/**
	 * @param array<string,mixed> $response
	 */
	private static function header_int( array $response, string $name ): int {
		$value = wp_remote_retrieve_header( $response, $name );
		if ( is_array( $value ) ) {
			return array() === $value ? 0 : 1;
		}
		return is_scalar( $value ) ? (int) $value : 0;
	}

	/**
	 * @param array<string,mixed> $body
	 */
	private static function extract_error( array $body, int $code ): string {
		$errors = isset( $body['errors'] ) && is_array( $body['errors'] ) ? $body['errors'] : array();
		$first  = isset( $errors[0] ) && is_array( $errors[0] ) ? $errors[0] : array();
		if ( isset( $first['detail'] ) && is_scalar( $first['detail'] ) ) {
			return sanitize_text_field( (string) $first['detail'] );
		}
		foreach ( array( 'detail', 'message', 'error' ) as $key ) {
			if ( isset( $body[ $key ] ) && is_scalar( $body[ $key ] ) ) {
				return sanitize_text_field( (string) $body[ $key ] );
			}
		}
		return 'HTTP ' . $code;
	}
}
