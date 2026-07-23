<?php
/**
 * Acknowledged Signls delivery transport.
 *
 * @package signls-sdk
 * @license GPL-3.0-or-later
 */

namespace Signls\Sdk\V1;

defined( 'ABSPATH' ) || exit;

final class Transport {

	private const DEFAULT_ENDPOINT = 'https://signls.dev';

	private const SDK_VERSION = '1.1.1';

	private $state;

	private $identity;

	private $site_identity;

	public function __construct( StateStore $state, Identity $identity, SiteIdentity $site_identity ) {
		$this->state         = $state;
		$this->identity      = $identity;
		$this->site_identity = $site_identity;
	}

	public function deliver( ProductAdapterInterface $adapter, array $payload ): array {
		$started = time();
		$this->state->set( 'last_attempt_started_at', $started );

		if ( '' === (string) $this->state->get( 'credential_id', '' ) ) {
			$enrollment = $this->enroll( $adapter, false );
			if ( ! $enrollment['ok'] ) {
				return $this->finish( $enrollment );
			}
		}

		$result = $this->snapshot( $adapter, $payload, false, false );
		if ( 'stale_timestamp' === $result['class'] && isset( $result['server_time'] ) ) {
			$this->state->set( 'clock_offset', (int) $result['server_time'] - time() );
			$result = $this->snapshot( $adapter, $payload, true, false );
		}
		return $this->finish( $result );
	}

	public static function retry_delay( int $attempt ): int {
		$delays = array( 900, 3600, 14400, 43200, 86400 );
		return $delays[ min( max( 0, $attempt ), count( $delays ) - 1 ) ];
	}

	private function enroll( ProductAdapterInterface $adapter, bool $clock_retry ): array {
		$body    = array(
			'schema_version' => 1,
			'sdk_version'    => self::SDK_VERSION,
			'product'        => $adapter->product_slug(),
			'install_id'     => $adapter->install_id(),
			'device_id'      => $this->identity->device_id(),
			'timestamp'      => time() + (int) $this->state->get( 'clock_offset', 0 ),
		);
		$encoded = wp_json_encode( $body );
		if ( ! is_string( $encoded ) ) {
			return array(
				'ok'        => false,
				'class'     => 'invalid_payload',
				'status'    => 0,
				'permanent' => true,
			);
		}
		$response = $this->post( '/signals/v2/enroll', $encoded, array() );
		if ( 'stale_timestamp' === $response['class'] && ! $clock_retry && isset( $response['data']['server_time'] ) ) {
			$this->state->set( 'clock_offset', (int) $response['data']['server_time'] - time() );
			return $this->enroll( $adapter, true );
		}
		if ( ! $response['ok'] ) {
			return $response;
		}

		$data = $response['data'];
		if ( ! isset( $data['credential_id'], $data['credential_secret'], $data['key_id'], $data['server_time'] ) || ! is_string( $data['credential_id'] ) || ! is_string( $data['credential_secret'] ) || ! is_string( $data['key_id'] ) ) {
			return array(
				'ok'        => false,
				'class'     => 'invalid_response',
				'status'    => $response['status'],
				'permanent' => true,
			);
		}

		$this->state->set_many(
			array(
				'credential_id'     => substr( $data['credential_id'], 0, 64 ),
				'credential_secret' => substr( $data['credential_secret'], 0, 128 ),
				'credential_key_id' => substr( $data['key_id'], 0, 48 ),
				'clock_offset'      => (int) $data['server_time'] - time(),
			)
		);
		return array(
			'ok'        => true,
			'class'     => 'enrolled',
			'status'    => $response['status'],
			'permanent' => false,
		);
	}

	private function snapshot( ProductAdapterInterface $adapter, array $payload, bool $clock_retry, bool $sequence_retry ): array {
		$contract       = $adapter->contract();
		$schema_version = isset( $contract['snapshot_schema_version'] ) ? (int) $contract['snapshot_schema_version'] : 1;
		$schema_version = 2 === $schema_version ? 2 : 1;
		$profile        = 2 === $schema_version && isset( $contract['observation_profile'] ) ? Sanitizer::slug( $contract['observation_profile'], 48 ) : '';
		$pending        = (string) $this->state->get( 'pending_body', '' );
		if ( '' !== $pending && ( self::SDK_VERSION !== (string) $this->state->get( 'pending_sdk_version', '' ) || $adapter->product_version() !== (string) $this->state->get( 'pending_product_version', '' ) ) ) {
			$this->state->delete_keys( array( 'pending_sequence', 'pending_body', 'pending_body_hash', 'pending_sdk_version', 'pending_product_version' ) );
			$pending = '';
		}
		if ( '' === $pending ) {
			$sequence = max( 1, (int) $this->state->get( 'last_acknowledged_sequence', 0 ) + 1 );
			$body     = array(
				'schema_version' => $schema_version,
				'sdk_version'    => self::SDK_VERSION,
				'product'        => $adapter->product_slug(),
				'device_id'      => $this->identity->device_id(),
				'install_id'     => $adapter->install_id(),
			);
			if ( 2 === $schema_version ) {
				$body['site_id']             = $this->site_identity->site_id();
				$body['observation_profile'] = $profile;
			}
			$body['sequence']    = $sequence;
			$body['observed_at'] = time();
			$body['payload']     = $payload;
			$pending             = (string) wp_json_encode( $body );
			$this->state->set_many(
				array(
					'pending_sequence'        => $sequence,
					'pending_body'            => $pending,
					'pending_body_hash'       => hash( 'sha256', $pending ),
					'pending_sdk_version'     => self::SDK_VERSION,
					'pending_product_version' => $adapter->product_version(),
				)
			);
		}

		$timestamp = time() + (int) $this->state->get( 'clock_offset', 0 );
		$secret    = self::base64url_decode( (string) $this->state->get( 'credential_secret', '' ) );
		if ( false === $secret || 32 !== strlen( $secret ) ) {
			return array(
				'ok'        => false,
				'class'     => 'invalid_credential',
				'status'    => 0,
				'permanent' => false,
			);
		}
		$signature = self::base64url( hash_hmac( 'sha256', $timestamp . "\n" . hash( 'sha256', $pending ), $secret, true ) );
		$response  = $this->post(
			'/signals/v2/snapshot',
			$pending,
			array(
				'X-Signls-Credential' => (string) $this->state->get( 'credential_id', '' ),
				'X-Signls-Timestamp'  => (string) $timestamp,
				'X-Signls-Signature'  => 'v1=' . $signature,
			)
		);

		if ( $response['ok'] ) {
			$data     = $response['data'];
			$sequence = (int) $this->state->get( 'pending_sequence', 0 );
			if ( empty( $data['accepted'] ) || (int) ( isset( $data['sequence'] ) ? $data['sequence'] : 0 ) !== $sequence ) {
				return array(
					'ok'        => false,
					'class'     => 'invalid_response',
					'status'    => $response['status'],
					'permanent' => true,
				);
			}
			$this->state->set_many(
				array(
					'last_acknowledged_sequence' => $sequence,
					'last_acknowledged_at'       => (int) ( isset( $data['received_at'] ) ? $data['received_at'] : time() ),
					'last_success_at'            => time(),
					'retry_attempt'              => 0,
				)
			);
			$this->state->delete_keys( array( 'pending_sequence', 'pending_body', 'pending_body_hash', 'pending_sdk_version', 'pending_product_version', 'failure_class', 'next_retry_at' ) );
			return array(
				'ok'        => true,
				'class'     => ! empty( $data['duplicate'] ) ? 'duplicate' : 'accepted',
				'status'    => $response['status'],
				'permanent' => false,
			);
		}

		if ( 'stale_timestamp' === $response['class'] && ! $clock_retry && isset( $response['data']['server_time'] ) ) {
			$response['server_time'] = (int) $response['data']['server_time'];
		}
		if ( 'sequence_conflict' === $response['class'] ) {
			$response['permanent'] = false;
			$expected              = isset( $response['data']['expected_sequence'] ) && is_int( $response['data']['expected_sequence'] ) ? $response['data']['expected_sequence'] : 0;
			if ( ! $sequence_retry && $expected > 0 ) {
				$this->state->reconcile_sequence( $expected );
				return $this->snapshot( $adapter, $payload, $clock_retry, true );
			}
		}
		return $response;
	}

	private function post( string $path, string $body, array $headers ): array {
		$endpoint = $this->endpoint();
		if ( '' === $endpoint ) {
			return array(
				'ok'        => false,
				'class'     => 'invalid_endpoint',
				'status'    => 0,
				'permanent' => true,
			);
		}

		$headers['Content-Type'] = 'application/json';
		$response                = wp_remote_post(
			$endpoint . $path,
			array(
				'body'        => $body,
				'headers'     => $headers,
				'timeout'     => 10,
				'redirection' => 0,
				'blocking'    => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			$error_code = $response->get_error_code();
			$error_code = is_scalar( $error_code ) ? substr( (string) $error_code, 0, 191 ) : '';
			return array(
				'ok'        => false,
				'class'     => self::transport_class( $error_code ),
				'status'    => 0,
				'permanent' => false,
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = (string) wp_remote_retrieve_body( $response );
		$data   = json_decode( $raw, true );
		$data   = is_array( $data ) ? $data : array();
		if ( in_array( $status, array( 200, 201, 202 ), true ) ) {
			return array(
				'ok'        => true,
				'class'     => 'success',
				'status'    => $status,
				'data'      => $data,
				'permanent' => false,
			);
		}

		$code      = Sanitizer::slug( isset( $data['code'] ) ? $data['code'] : '', 48, 'http_' . $status );
		$transient = in_array( $status, array( 408, 425, 429 ), true ) || $status >= 500;
		return array(
			'ok'        => false,
			'class'     => $code,
			'status'    => $status,
			'data'      => $data,
			'permanent' => ! $transient,
		);
	}

	private function endpoint(): string {
		$endpoint = self::DEFAULT_ENDPOINT;
		if ( function_exists( 'apply_filters' ) ) {
			$endpoint = (string) apply_filters( 'signls_sdk_v1_endpoint', $endpoint, $this->state->product() );
		}
		$endpoint = untrailingslashit( $endpoint );
		$parts    = wp_parse_url( $endpoint );
		$host     = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
		$scheme   = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '';
		$local    = 'localhost' === $host || '.test' === substr( $host, -5 );
		return ( 'https' === $scheme || ( 'http' === $scheme && $local ) ) ? $endpoint : '';
	}

	private function finish( array $result ): array {
		$values = array(
			'last_attempt_finished_at' => time(),
			'last_http_status'         => (int) ( isset( $result['status'] ) ? $result['status'] : 0 ),
			'failure_class'            => $result['ok'] ? '' : Sanitizer::slug( isset( $result['class'] ) ? $result['class'] : '', 48, 'unknown' ),
		);
		if ( ! $result['ok'] ) {
			$attempt                 = (int) $this->state->get( 'retry_attempt', 0 );
			$values['retry_attempt'] = $attempt + 1;
			if ( in_array( $values['failure_class'], array( 'invalid_credential', 'device_already_enrolled' ), true ) ) {
				$last_rotation = (int) $this->state->get( 'last_device_rotation_at', 0 );
				if ( time() - $last_rotation >= DAY_IN_SECONDS ) {
					$this->identity->rotate_device();
					$values['last_device_rotation_at'] = time();
				}
				$result['permanent'] = false;
			}
			if ( empty( $result['permanent'] ) ) {
				$values['next_retry_at'] = time() + self::retry_delay( $attempt ) + random_int( 0, 300 );
			}
		}
		$this->state->set_many( $values );
		return $result;
	}

	private static function transport_class( string $code ): string {
		$code = strtolower( $code );
		if ( false !== strpos( $code, 'resolve' ) || false !== strpos( $code, 'dns' ) ) {
			return 'transport_dns';
		}
		if ( false !== strpos( $code, 'ssl' ) || false !== strpos( $code, 'tls' ) ) {
			return 'transport_tls';
		}
		if ( false !== strpos( $code, 'timeout' ) ) {
			return 'transport_timeout';
		}
		return 'transport_connect';
	}

	private static function base64url( string $value ): string {
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
	}

	private static function base64url_decode( string $value ) {
		if ( '' === $value || 1 !== preg_match( '/^[A-Za-z0-9_-]+$/', $value ) ) {
			return false;
		}
		$padding = ( 4 - strlen( $value ) % 4 ) % 4;
		return base64_decode( strtr( $value, '-_', '+/' ) . str_repeat( '=', $padding ), true );
	}
}
