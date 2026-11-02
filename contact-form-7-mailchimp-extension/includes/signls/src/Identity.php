<?php
/**
 * Stable installation and rotating device identity.
 *
 * The device rotates only when the stored-origin fingerprint changes (a real
 * migration or clone), at most once per 24 hours, or when the server proves a
 * credential is shared. Upgrades re-key the stored fingerprint without rotating.
 *
 * @package signls-sdk
 * @license GPL-3.0-or-later
 */

namespace Signls\Sdk\V1;

defined( 'ABSPATH' ) || exit;

final class Identity {

	public const ORIGIN_VERSION = SiteIdentity::ORIGIN_VERSION;

	private const ROTATION_INTERVAL = DAY_IN_SECONDS;

	private $state;

	public function __construct( StateStore $state ) {
		$this->state = $state;
	}

	public function device_id(): string {
		$current_origin = $this->origin_fingerprint();
		$stored_origin  = (string) $this->state->get( 'origin_fingerprint', '' );
		$stored_version = (int) $this->state->get( 'origin_fingerprint_version', 0 );
		$device_id      = (string) $this->state->get( 'device_id', '' );

		if ( ! self::valid_uuid( $device_id ) ) {
			$device_id = self::uuid4();
			$this->state->set_many(
				array(
					'device_id'                  => $device_id,
					'origin_fingerprint'         => $current_origin,
					'origin_fingerprint_version' => self::ORIGIN_VERSION,
				)
			);
			$this->clear_credential();
			return $device_id;
		}

		if ( '' === $stored_origin ) {
			$this->state->set_many(
				array(
					'origin_fingerprint'         => $current_origin,
					'origin_fingerprint_version' => self::ORIGIN_VERSION,
				)
			);
			return $device_id;
		}

		if ( $stored_version < self::ORIGIN_VERSION ) {
			// Upgrade from an older fingerprint formula: re-key, keep device, credential and sequence.
			// If the old-formula value does not match either, this may be a clone made before the
			// upgrade; flag it and let the server's sequence verdict decide (Transport).
			$values = array(
				'origin_fingerprint'         => $current_origin,
				'origin_fingerprint_version' => self::ORIGIN_VERSION,
			);
			if ( ! hash_equals( $stored_origin, $this->legacy_origin_fingerprint() ) ) {
				$values['rekey_unverified_at'] = time();
			}
			$this->state->set_many( $values );
			return $device_id;
		}

		if ( ! hash_equals( $stored_origin, $current_origin ) ) {
			if ( $this->rotation_allowed() ) {
				return $this->rotate_device( 'origin_changed' );
			}
			$this->state->set_many(
				array(
					'rotation_suppressed_at'     => time(),
					'rotation_suppressed_reason' => 'origin_changed',
				)
			);
			$this->notify( 'signls_sdk_v1_identity_rotation_suppressed', 'origin_changed', $stored_origin, $current_origin );
		}

		return $device_id;
	}

	public function rotation_allowed(): bool {
		return time() - (int) $this->state->get( 'last_device_rotation_at', 0 ) >= self::ROTATION_INTERVAL;
	}

	public function rekey_unverified(): bool {
		return (int) $this->state->get( 'rekey_unverified_at', 0 ) > 0;
	}

	public function rotate_device( string $reason = 'manual' ): string {
		$old_origin = (string) $this->state->get( 'origin_fingerprint', '' );
		$new_origin = $this->origin_fingerprint();
		$device_id  = self::uuid4();
		$reason     = Sanitizer::slug( $reason, 48, 'manual' );
		$this->state->set_many(
			array(
				'device_id'                   => $device_id,
				'origin_fingerprint'          => $new_origin,
				'origin_fingerprint_version'  => self::ORIGIN_VERSION,
				'last_device_rotation_at'     => time(),
				'last_device_rotation_reason' => $reason,
				'device_rotation_count'       => (int) $this->state->get( 'device_rotation_count', 0 ) + 1,
			)
		);
		$this->clear_credential();
		$this->notify( 'signls_sdk_v1_identity_rotated', $reason, $old_origin, $new_origin );
		return $device_id;
	}

	public function clear_credential(): void {
		$this->state->delete_keys(
			array_merge(
				array(
					'credential_id',
					'credential_secret',
					'credential_key_id',
					'last_acknowledged_sequence',
					'last_acknowledged_at',
					'last_success_at',
					'last_attempt_started_at',
					'last_attempt_finished_at',
					'last_http_status',
					'retry_attempt',
					'clock_offset',
					'rekey_unverified_at',
					'sequence_resync_at',
					'rotation_suppressed_at',
					'rotation_suppressed_reason',
				),
				StateStore::pending_keys()
			)
		);
	}

	public function forget_device(): void {
		$this->state->delete_keys( array( 'device_id', 'origin_fingerprint', 'origin_fingerprint_version' ) );
		$this->clear_credential();
	}

	/**
	 * Version-2 fingerprint: site origin (stored home option) plus the table prefix.
	 */
	public function origin_fingerprint(): string {
		return $this->fingerprint( ( new SiteIdentity() )->origin_fingerprint() );
	}

	/**
	 * Version-1 fingerprint as SDK 1.1.11 and 1.1.12 stored it; used only during the upgrade re-key.
	 */
	public function legacy_origin_fingerprint(): string {
		return $this->fingerprint( ( new SiteIdentity() )->legacy_origin_fingerprint() );
	}

	private function fingerprint( string $site_origin ): string {
		$salt = function_exists( 'wp_salt' ) ? (string) wp_salt( 'auth' ) : 'signls-test-salt';
		global $wpdb;
		$prefix = isset( $wpdb->prefix ) ? (string) $wpdb->prefix : 'wp_';
		return hash_hmac( 'sha256', $site_origin . '|' . $prefix, $salt );
	}

	private function notify( string $hook, string $reason, string $old_fingerprint, string $new_fingerprint ): void {
		if ( function_exists( 'do_action' ) ) {
			do_action( $hook, $this->state->product(), $reason, substr( $old_fingerprint, 0, 12 ), substr( $new_fingerprint, 0, 12 ) );
		}
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
