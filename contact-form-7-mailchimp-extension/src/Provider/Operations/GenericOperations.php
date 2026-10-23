<?php
/**
 * @package   contact-form-7-mailchimp-extension
 * @author    hello@chimpmatic.com
 * @copyright 2014-2026 Contact Form 7 Extension
 * @license   GPL-3.0+
 */

declare(strict_types=1);

namespace Cmatic\Lite\Provider\Operations;

use Cmatic\Lite\Provider\Descriptor;
use Cmatic\Lite\Provider\HttpTransport;
use Cmatic\Lite\Provider\Projection;
use Cmatic\Lite\Provider\Protocol;
use Cmatic\Lite\Provider\Collection;
use Cmatic\Lite\Provider\Payload;
use Cmatic\Lite\Submission\Outcome;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing -- Typed signatures follow the existing Lite class convention.
class GenericOperations implements OperationsInterface {
	/** @var Descriptor */
	protected $descriptor;

	/** @var HttpTransport */
	protected $transport;

	public function __construct( Descriptor $descriptor, HttpTransport $transport ) {
		$this->descriptor = $descriptor;
		$this->transport  = $transport;
	}

	public function perform_subscription( string $api_key, string $list_id, string $email, string $status, array $merge_vars, array $options ): array {
		$lists = array();
		foreach ( isset( $options['groups'] ) && is_array( $options['groups'] ) ? $options['groups'] : array( $list_id ) as $group ) {
			if ( is_scalar( $group ) && '' !== (string) $group ) {
				$lists[] = (string) $group;
			}
		}
		$lists    = array_values( array_unique( $lists ) );
		$lists    = array() === $lists ? array( $list_id ) : $lists;
		$accepted = null;
		$result   = array();
		foreach ( $lists as $target ) {
			$result = $this->subscribe_to_list( $api_key, $target, $email, $status, $merge_vars );
			if ( ! self::succeeded( $result ) && 'already_subscribed' !== ( $result['reason'] ?? '' ) && true !== ( $result['unchanged'] ?? false ) ) {
				return $result;
			}
			$data = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : array();
			if ( self::succeeded( $result ) && ( null === $accepted || 'pending' === ( $data['status'] ?? '' ) ) ) {
				$accepted = $result;
			}
		}
		return $accepted ?? $result;
	}

	/**
	 * @param array<string,mixed> $merge_vars
	 * @return array<string,mixed>
	 */
	private function subscribe_to_list( string $api_key, string $list_id, string $email, string $status, array $merge_vars ): array {
		$protocol = $this->descriptor->protocol();
		$spec     = isset( $protocol['subscribe'] ) && is_array( $protocol['subscribe'] ) ? $protocol['subscribe'] : array();
		if ( array() === $spec || '' === $list_id || 'subscribed' !== $status ) {
			return $this->failure_result( 'configuration_error', 'A destination and destination-managed subscription status are required.' );
		}
		if ( ! empty( $spec['create_only'] ) ) {
			$lookup = $this->lookup_in_list( $api_key, $list_id, $email );
			if ( ! self::succeeded( $lookup ) ) {
				return $lookup;
			}
			$data = isset( $lookup['data'] ) && is_array( $lookup['data'] ) ? $lookup['data'] : array();
			if ( true === ( $data['found'] ?? null ) ) {
				return $this->failure_result( 'already_subscribed', '' ) + array(
					'unchanged' => true,
					/* translators: %s: email provider name */
					'message'   => sprintf( __( 'Already in this %s list; Contact Form 7 Extension did not change it.', 'contact-form-7-mailchimp-extension' ), $this->descriptor->label() ),
				);
			}
			if ( false !== ( $data['found'] ?? null ) ) {
				return $this->failure_result( 'api_error', 'Provider did not establish whether this contact exists.' );
			}
		}
		$body   = Payload::build( $spec, $list_id, $email, $merge_vars );
		$path   = str_replace( '{list_id}', rawurlencode( $list_id ), Protocol::text( $spec['path'] ) );
		$result = Payload::answer( $spec, $this->request( $api_key, Protocol::text( $spec['method'] ?? 'POST' ), $path, $body, false ), $email );
		return ! empty( $spec['queued'] ) ? $result : $this->subscription_result( $result, $email, $merge_vars );
	}

	/** @return array<string,mixed> */
	public function lookup_in_list( string $api_key, string $list_id, string $email ): array {
		$protocol = $this->descriptor->protocol();
		$spec     = isset( $protocol['lookup'] ) && is_array( $protocol['lookup'] ) ? $protocol['lookup'] : array();
		if ( empty( $spec['path'] ) ) {
			return $this->failure_result( 'configuration_error', 'No list-scoped lookup is declared.' );
		}
		$path   = strtr(
			Protocol::text( $spec['path'] ),
			array(
				'{list_id}'    => rawurlencode( $list_id ),
				'{email}'      => rawurlencode( $email ),
				// nosemgrep: weak-crypto -- non-cryptographic identifier: EmailOctopus contact ID is md5 of the lowercased email
				'{email_hash}' => md5( strtolower( $email ) ),
			)
		);
		$result = $this->request( $api_key, 'GET', $path, array(), false );
		$body   = self::body_of( $result );
		$absent = ( isset( $spec['absent_status'] ) && self::status_of( $result ) === $spec['absent_status'] ) || ( isset( $spec['absent_path'], $spec['absent_value'] ) && Protocol::read( $body, Protocol::text( $spec['absent_path'] ) ) === $spec['absent_value'] );
		if ( isset( $spec['absent_error_prefix'] ) ) {
			$absent = $absent && 0 === strpos( self::error_of( $result ), Protocol::text( $spec['absent_error_prefix'] ) );
		}
		if ( $absent ) {
			return array(
				'success' => true,
				'data'    => array( 'found' => false ),
				'body'    => array(),
				'error'   => '',
				'reason'  => '',
			);
		}
		if ( self::succeeded( $result ) ) {
			$record = Protocol::read( $body, Protocol::text( $spec['root'] ?? '' ) );
			if ( ! is_array( $record ) || 0 !== strcasecmp( $email, Protocol::text( Protocol::read( $record, Protocol::text( $spec['email'] ?? 'email' ) ) ) ) ) {
				return $this->failure_result( 'api_error', 'Provider returned an invalid contact lookup.' );
			}
			$result['data'] = array(
				'found'      => true,
				'subscriber' => $record,
			);
		}
		return $result;
	}

	public function validate_key( string $api_key, bool $log_enabled = false ): array {
		$logger = new \Cmatic_File_Logger( 'API-Validation-' . $this->descriptor->slug(), $log_enabled );
		if ( '' === trim( $api_key ) ) {
			$this->record_validation( false );
			return array( 'api-validation' => 0 );
		}

		$result  = $this->request( $api_key, 'GET', $this->descriptor->validation_path() );
		$success = self::succeeded( $result );
		$this->record_validation( $success );
		$logger->log( $success ? 'INFO' : 'ERROR', $success ? 'API key validated successfully.' : 'API key validation failed.' );

		$output = array( 'api-validation' => $success ? 1 : 0 );
		if ( ! $success && array() !== $this->descriptor->protocol() ) {
			$output['_cmatic_error'] = sanitize_text_field( Protocol::text( Protocol::redact( self::error_of( $result ), $api_key ) ) );
		}
		return $output;
	}

	public function get_lists( string $api_key, bool $log_enabled = false ): array {
		$protocol = $this->descriptor->protocol();
		if ( isset( $protocol['lists'] ) && is_array( $protocol['lists'] ) ) {
			$result = $this->collection( $api_key, $protocol['lists'], $this->descriptor->lists_path() );
			$output = array(
				'lisdata'      => array( 'lists' => array() ),
				'merge_fields' => array(),
			);
			if ( self::succeeded( $result ) ) {
				$output['lisdata']['lists'] = $this->normalize_lists( self::body_of( $result ) );
			} else {
				$output['_cmatic_error'] = self::error_of( $result );
			}
			return $output;
		}
		$lists = array();
		$path  = $this->descriptor->lists_path();
		for ( $page = 0; '' !== $path && $page < 100; $page++ ) {
			$result = $this->request( $api_key, 'GET', $path );
			if ( ! self::succeeded( $result ) ) {
				return array(
					'lisdata'       => array( 'lists' => array() ),
					'merge_fields'  => array(),
					'_cmatic_error' => self::error_of( $result ),
				);
			}
			$body  = self::body_of( $result );
			$lists = array_merge( $lists, $this->normalize_lists( $body ) );
			$path  = $this->descriptor->next_lists_path( $body, $path );
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

	public function get_merge_fields( string $api_key, string $list_id, bool $log_enabled = false ): array {
		$protocol = $this->descriptor->protocol();
		if ( isset( $protocol['fields'] ) && is_array( $protocol['fields'] ) ) {
			$result = $this->collection( $api_key, $protocol['fields'], $this->descriptor->fields_path( $list_id ) );
			return self::succeeded( $result ) ? array( 'merge_fields' => array( 'merge_fields' => $this->normalize_fields( self::body_of( $result ) ) ) ) : array(
				'merge_fields'  => array( 'merge_fields' => array() ),
				'_cmatic_error' => self::error_of( $result ),
			);
		}
		$path = $this->descriptor->fields_path( $list_id );
		if ( '' === $path ) {
			return array( 'merge_fields' => array( 'merge_fields' => $this->normalize_fields( array() ) ) );
		}

		$result = $this->request( $api_key, 'GET', $path );
		if ( ! self::succeeded( $result ) ) {
			return array(
				'merge_fields'  => array( 'merge_fields' => array() ),
				'_cmatic_error' => self::error_of( $result ),
			);
		}

		return array( 'merge_fields' => array( 'merge_fields' => $this->normalize_fields( self::body_of( $result ) ) ) );
	}

	public function validate_subscription_options( string $api_key, string $list_id, array $options ): array {
		unset( $api_key, $list_id, $options );
		return array(
			'success' => true,
			'error'   => '',
			'data'    => array(),
		);
	}

	public function subscribe( string $api_key, string $list_id, string $email, string $status, array $merge_vars, int $form_id, array $options, \Cmatic_File_Logger $logger ): void {
		try {
			$result   = $this->perform_subscription( $api_key, $list_id, $email, $status, $merge_vars, $options );
			$protocol = $this->descriptor->protocol();
			$spec     = isset( $protocol['subscribe'] ) && is_array( $protocol['subscribe'] ) ? $protocol['subscribe'] : array();
			if ( isset( $spec['status_map'] ) && isset( $result['data']['status'] ) && in_array( $result['data']['status'], array( 'subscribed', 'pending' ), true ) ) {
				$status = $result['data']['status'];
			}
			$logger->log(
				self::succeeded( $result ) || ! empty( $result['unchanged'] ) ? 'INFO' : 'ERROR',
				$this->descriptor->label() . ' subscription result.',
				array(
					'provider' => $this->descriptor->slug(),
					'success'  => self::succeeded( $result ),
					'reason'   => self::str( $result['reason'] ?? '' ),
				)
			);
			Outcome::handle_provider_result( $result, $email, $status, $merge_vars, $form_id );
		} catch ( \Throwable $exception ) {
			Outcome::handle_provider_result( $this->failure_result( 'network_error', isset( $this->descriptor->protocol()['query_key'] ) ? Protocol::text( Protocol::redact( $exception->getMessage(), $api_key ) ) : $exception->getMessage() ), $email, $status, $merge_vars, $form_id );
		}
	}

	public function create_field( string $api_key, array $spec, bool $log_enabled = false ): array {
		unset( $api_key, $spec, $log_enabled );
		return $this->failure_result( 'configuration_error', 'This provider does not support creating fields.' );
	}

	public function lookup( string $api_key, string $email ): array {
		unset( $api_key, $email );
		return $this->failure_result( 'configuration_error', 'This provider does not support subscriber lookup.' );
	}

	public function request( string $api_key, string $method, string $path, array $body = array(), bool $retryable = true ): array {
		$protocol = $this->descriptor->protocol();
		$private  = isset( $protocol['query_key'] );
		$spec     = isset( $protocol['subscribe'] ) && is_array( $protocol['subscribe'] ) ? $protocol['subscribe'] : array();
		if ( $private ) {
			$path .= ( false === strpos( $path, '?' ) ? '?' : '&' ) . rawurlencode( Protocol::text( $protocol['query_key'] ) ) . '=' . rawurlencode( $api_key );
		}
		$result = $this->transport->request(
			$this->descriptor->slug(),
			$api_key,
			$method,
			rtrim( $this->descriptor->base_url(), '/' ) . '/' . ltrim( $path, '/' ),
			$this->descriptor->headers( $api_key ),
			$body,
			$retryable,
			$this->descriptor->response_format(),
			$private,
			! empty( $spec['preserve_errors'] )
		);
		if ( self::succeeded( $result ) && isset( $protocol['envelope'] ) && is_array( $protocol['envelope'] ) ) {
			$envelope = $protocol['envelope'];
			if ( Protocol::read( self::body_of( $result ), Protocol::text( $envelope['code'] ?? '' ) ) !== ( $envelope['success'] ?? null ) ) {
				$result['success'] = false;
				$result['reason']  = 'api_error';
				$result['data']    = array();
				$result['error']   = Protocol::text( Protocol::read( self::body_of( $result ), Protocol::text( $envelope['error'] ?? '' ) ) );
				if ( '' === $result['error'] ) {
					$result['error'] = 'Provider returned an invalid response envelope.';
				}
			}
		}
		return $result;
	}

	public function normalize_lists( array $body ): array {
		if ( isset( $this->descriptor->protocol()['lists'] ) ) {
			$body = array( Protocol::text( $this->descriptor->lists_spec()['root'] ?? 'items' ) => $body );
		}
		return Projection::lists( $this->descriptor->lists_spec(), $body );
	}

	public function normalize_fields( array $body ): array {
		if ( isset( $this->descriptor->protocol()['fields'] ) ) {
			$body = array( Protocol::text( $this->descriptor->fields_spec()['root'] ?? 'items' ) => $body );
		}
		return Projection::fields( $this->descriptor->fields_spec(), $body );
	}

	/**
	 * @param array<mixed> $spec
	 * @return array<string,mixed>
	 */
	private function collection( string $api_key, array $spec, string $path ): array {
		return Collection::fetch(
			$spec,
			Protocol::text( $spec['path'] ?? $path ),
			function ( string $endpoint ) use ( $api_key ): array {
				return $this->request( $api_key, 'GET', $endpoint );
			}
		);
	}

	/**
	 * @param array<string,mixed> $response
	 * @param array<string,mixed> $merge_vars
	 * @return array<string,mixed>
	 */
	protected function subscription_result( array $response, string $email, array $merge_vars ): array {
		if ( ! self::succeeded( $response ) ) {
			return $response;
		}

		$output = array(
			'success' => true,
			'data'    => array(
				'email_address' => $email,
				'merge_fields'  => $merge_vars,
			),
			'body'    => self::body_of( $response ),
			'error'   => '',
			'reason'  => '',
		);
		if ( isset( $response['subscription_status'] ) ) {
			$output['data']['status'] = $response['subscription_status'];
		}
		return $output;
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function failure_result( string $reason, string $error ): array {
		return HttpTransport::failure( $reason, $error );
	}

	/**
	 * @param array<string,mixed> $result
	 */
	protected static function succeeded( array $result ): bool {
		return true === ( $result['success'] ?? false );
	}

	/**
	 * @param array<string,mixed> $result
	 * @return array<string,mixed>
	 */
	protected static function body_of( array $result ): array {
		return isset( $result['body'] ) && is_array( $result['body'] ) ? $result['body'] : array();
	}

	/**
	 * @param array<string,mixed> $result
	 */
	protected static function error_of( array $result ): string {
		return self::str( $result['error'] ?? '' );
	}

	/**
	 * @param array<string,mixed> $result
	 */
	protected static function status_of( array $result ): int {
		return isset( $result['status'] ) && is_numeric( $result['status'] ) ? (int) $result['status'] : 0;
	}

	/**
	 * @param mixed $value
	 */
	protected static function str( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * @param mixed $value
	 */
	protected static function int( $value ): int {
		return is_numeric( $value ) ? (int) $value : 0;
	}

	private function record_validation( bool $success ): void {
		$key = $success ? 'api.setup_first_success' : 'api.setup_first_failure';
		if ( ! \Cmatic_Options_Repository::get_option( $key ) ) {
			\Cmatic_Options_Repository::set_option( $key, time() );
		}
		if ( ! $success ) {
			\Cmatic_Options_Repository::set_option( 'api.setup_last_failure', time() );
			$count = \Cmatic_Options_Repository::get_option( 'api.setup_failure_count', 0 );
			\Cmatic_Options_Repository::set_option( 'api.setup_failure_count', ( is_numeric( $count ) ? (int) $count : 0 ) + 1 );
		}
	}
}
