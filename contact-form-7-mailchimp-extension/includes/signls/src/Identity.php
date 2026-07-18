<?php
/**
 * Stable installation and rotating device identity.
 *
 * @package signls-sdk
 * @license GPL-3.0-or-later
 */

namespace Signls\Sdk\V1;

defined( 'ABSPATH' ) || exit;

final class Identity {

	private $state;

	public function __construct( StateStore $state ) {
		$this->state = $state;
	}

	public function device_id(): string {
		$current_origin = $this->origin_fingerprint();
		$stored_origin  = (string) $this->state->get( 'origin_fingerprint', '' );
		$device_id      = (string) $this->state->get( 'device_id', '' );

		if ( ! self::valid_uuid( $device_id ) || ( '' !== $stored_origin && ! hash_equals( $stored_origin, $current_origin ) ) ) {
			$device_id = self::uuid4();
			$this->state->set_many(
				array(
					'device_id'          => $device_id,
					'origin_fingerprint' => $current_origin,
				)
			);
			$this->clear_credential();
		} elseif ( '' === $stored_origin ) {
			$this->state->set( 'origin_fingerprint', $current_origin );
		}

		return $device_id;
	}

	public function rotate_device(): string {
		$device_id = self::uuid4();
		$this->state->set_many(
			array(
				'device_id'          => $device_id,
				'origin_fingerprint' => $this->origin_fingerprint(),
			)
		);
		$this->clear_credential();
		return $device_id;
	}

	public function clear_credential(): void {
		$this->state->delete_keys(
			array(
				'credential_id',
				'credential_secret',
				'credential_key_id',
				'pending_sequence',
				'pending_body',
				'pending_body_hash',
				'pending_sdk_version',
				'pending_product_version',
				'last_acknowledged_sequence',
				'last_acknowledged_at',
				'last_success_at',
				'last_attempt_started_at',
				'last_attempt_finished_at',
				'last_http_status',
				'failure_class',
				'retry_attempt',
				'next_retry_at',
				'clock_offset',
			)
		);
	}

	public function forget_device(): void {
		$this->state->delete_keys( array( 'device_id', 'origin_fingerprint' ) );
		$this->clear_credential();
	}

	private function origin_fingerprint(): string {
		$host = function_exists( 'home_url' ) ? (string) wp_parse_url( home_url(), PHP_URL_HOST ) : 'local';
		$salt = function_exists( 'wp_salt' ) ? (string) wp_salt( 'auth' ) : 'signls-test-salt';
		global $wpdb;
		$prefix = isset( $wpdb->prefix ) ? (string) $wpdb->prefix : 'wp_';
		return hash_hmac( 'sha256', strtolower( $host ) . '|' . $prefix, $salt );
	}

	private static function uuid4(): string {
		$bytes    = random_bytes( 16 );
		$bytes[6] = chr( ( ord( $bytes[6] ) & 0x0f ) | 0x40 );
		$bytes[8] = chr( ( ord( $bytes[8] ) & 0x3f ) | 0x80 );
		$hex      = bin2hex( $bytes );
		return substr( $hex, 0, 8 ) . '-' . substr( $hex, 8, 4 ) . '-' . substr( $hex, 12, 4 ) . '-' . substr( $hex, 16, 4 ) . '-' . substr( $hex, 20 );
	}

	private static function valid_uuid( string $value ): bool {
		return 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $value );
	}
}
