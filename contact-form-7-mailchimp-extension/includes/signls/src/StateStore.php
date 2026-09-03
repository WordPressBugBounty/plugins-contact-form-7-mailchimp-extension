<?php
/**
 * Product-scoped non-autoloaded state.
 *
 * @package signls-sdk
 * @license GPL-3.0-or-later
 */

namespace Signls\Sdk\V1;

defined( 'ABSPATH' ) || exit;

final class StateStore {

	private $product;

	private $option_name;

	private $delivery_intent_option_name;

	/** @var string */
	private $product_version_option_name;

	public function __construct( string $product ) {
		$this->product                     = $product;
		$this->option_name                 = 'signls_sdk_v1_' . substr( hash( 'sha256', $product ), 0, 12 );
		$this->delivery_intent_option_name = $this->option_name . '_delivery_intent';
		$this->product_version_option_name = $this->option_name . '_product_version';
	}

	public function product(): string {
		return $this->product;
	}

	public function option_name(): string {
		return $this->option_name;
	}

	public function delivery_intent_option_name(): string {
		return $this->delivery_intent_option_name;
	}

	public function product_version_option_name(): string {
		return $this->product_version_option_name;
	}

	public function refresh_concurrency_state(): void {
		if ( ! function_exists( 'wp_cache_delete' ) ) {
			return;
		}
		foreach ( array( $this->option_name, $this->delivery_intent_option_name, $this->product_version_option_name ) as $option ) {
			wp_cache_delete( $option, 'options' );
		}
	}

	public function mark_delivery_intent(): string {
		$token = self::new_delivery_intent();
		if ( '' === $token ) {
			return '';
		}

		$current = get_option( $this->delivery_intent_option_name, false );
		$saved   = false === $current
			? add_option( $this->delivery_intent_option_name, $token, '', false )
			: update_option( $this->delivery_intent_option_name, $token, false );
		return $saved ? $token : '';
	}

	public function ensure_delivery_intent(): string {
		$current = get_option( $this->delivery_intent_option_name, false );
		if ( is_string( $current ) && self::valid_delivery_intent( $current ) ) {
			return $current;
		}

		$token = self::new_delivery_intent();
		if ( '' === $token ) {
			return '';
		}
		$saved = false === $current
			? add_option( $this->delivery_intent_option_name, $token, '', false )
			: update_option( $this->delivery_intent_option_name, $token, false );
		if ( $saved ) {
			return $token;
		}
		return $this->delivery_intent();
	}

	public function delivery_intent(): string {
		$value = get_option( $this->delivery_intent_option_name, '' );
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{32}$/D', $value ) ? $value : '';
	}

	public function clear_delivery_intent( string $token ): bool {
		if ( 1 !== preg_match( '/^[a-f0-9]{32}$/D', $token ) ) {
			return false;
		}

		global $wpdb;
		if ( ! is_object( $wpdb ) || ! isset( $wpdb->options ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'query' ) ) {
			return false;
		}

		try {
			$deleted = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Atomic compare-and-delete prevents an older delivery from erasing a newer intent.
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
					$this->delivery_intent_option_name,
					$token
				)
			);
			if ( function_exists( 'wp_cache_delete' ) ) {
				wp_cache_delete( $this->delivery_intent_option_name, 'options' );
			}
			return 1 === (int) $deleted;
		} catch ( \Throwable $error ) {
			return false;
		}
	}

	public function clear_delivery_intent_unconditionally(): bool {
		return delete_option( $this->delivery_intent_option_name );
	}

	/**
	 * @return array{observed?: string, pending?: string, intent?: string, settled_intent?: string}
	 */
	public function product_version_observation(): array {
		$value = get_option( $this->product_version_option_name, array() );
		if ( ! is_array( $value ) ) {
			return array();
		}

		$observation = array();
		foreach ( array( 'observed', 'pending' ) as $key ) {
			if ( isset( $value[ $key ] ) && is_string( $value[ $key ] ) && self::valid_product_version( $value[ $key ] ) ) {
				$observation[ $key ] = $value[ $key ];
			}
		}
		foreach ( array( 'intent', 'settled_intent' ) as $key ) {
			if ( isset( $value[ $key ] ) && is_string( $value[ $key ] ) && self::valid_delivery_intent( $value[ $key ] ) ) {
				$observation[ $key ] = $value[ $key ];
			}
		}
		return $observation;
	}

	public function baseline_product_version( string $version ): bool {
		if ( ! self::valid_product_version( $version ) ) {
			return false;
		}
		$current = get_option( $this->product_version_option_name, false );
		if ( false === $current ) {
			return add_option(
				$this->product_version_option_name,
				array( 'observed' => $version ),
				'',
				false
			);
		}
		$observation = $this->product_version_observation();
		return isset( $observation['observed'] ) && hash_equals( $observation['observed'], $version );
	}

	public function begin_product_version_transition( string $version, string $intent ): bool {
		if ( ! self::valid_product_version( $version ) || ! self::valid_delivery_intent( $intent ) ) {
			return false;
		}
		$observation            = $this->product_version_observation();
		$observation['pending'] = $version;
		$observation['intent']  = $intent;
		unset( $observation['settled_intent'] );
		return $this->replace_product_version_observation( $observation );
	}

	public function rebind_product_version_transition( string $intent ): bool {
		if ( ! self::valid_delivery_intent( $intent ) ) {
			return false;
		}
		$observation = $this->product_version_observation();
		if ( ! isset( $observation['pending'] ) ) {
			return true;
		}
		$observation['intent'] = $intent;
		return $this->replace_product_version_observation( $observation );
	}

	public function settle_product_version_transition( string $version, string $intent ): bool {
		if ( ! self::valid_product_version( $version ) || ! self::valid_delivery_intent( $intent ) ) {
			return false;
		}
		$observation = $this->product_version_observation();
		if (
			! isset( $observation['pending'], $observation['intent'] )
			|| ! hash_equals( $observation['pending'], $version )
			|| ! hash_equals( $observation['intent'], $intent )
		) {
			return false;
		}

		$observation['observed']       = $version;
		$observation['settled_intent'] = $intent;
		unset( $observation['pending'], $observation['intent'] );
		return $this->replace_product_version_observation( $observation );
	}

	public function clear_settled_product_version_intent( string $intent ): bool {
		if ( ! self::valid_delivery_intent( $intent ) ) {
			return false;
		}
		$observation = $this->product_version_observation();
		if ( ! isset( $observation['settled_intent'] ) || ! hash_equals( $observation['settled_intent'], $intent ) ) {
			return false;
		}
		unset( $observation['settled_intent'] );
		return $this->replace_product_version_observation( $observation );
	}

	public function all(): array {
		$value = get_option( $this->option_name, array() );
		return is_array( $value ) ? $value : array();
	}

	public function get( string $key, $default = null ) {
		$state = $this->all();
		return array_key_exists( $key, $state ) ? $state[ $key ] : $default;
	}

	public function set( string $key, $value ): bool {
		$state         = $this->all();
		$state[ $key ] = $value;
		return $this->replace( $state );
	}

	public function set_many( array $values ): bool {
		return $this->replace( array_merge( $this->all(), $values ) );
	}

	public function delete_keys( array $keys ): bool {
		$state = $this->all();
		foreach ( $keys as $key ) {
			unset( $state[ $key ] );
		}
		return $this->replace( $state );
	}

	public static function pending_keys(): array {
		return array(
			'pending_sequence',
			'pending_body',
			'pending_body_hash',
			'pending_sdk_version',
			'pending_product_version',
			'pending_payload_revision',
			'pending_quarantine_class',
			'pending_quarantine_status',
			'pending_quarantined_at',
			'pending_quarantine_probe_at',
			'failure_class',
			'next_retry_at',
		);
	}

	public function clear_pending(): bool {
		return $this->delete_keys( self::pending_keys() );
	}

	public function apply_clock_correction( int $offset, bool $clear_pending ): bool {
		$state                 = $this->all();
		$state['clock_offset'] = $offset;
		if ( $clear_pending ) {
			foreach ( self::pending_keys() as $key ) {
				unset( $state[ $key ] );
			}
		}
		return $this->replace( $state );
	}

	public function reconcile_sequence( int $expected_sequence ): bool {
		$state                               = $this->all();
		$state['last_acknowledged_sequence'] = max( 0, $expected_sequence - 1 );
		foreach ( self::pending_keys() as $key ) {
			unset( $state[ $key ] );
		}
		return $this->replace( $state );
	}

	public function delete(): bool {
		$state_deleted   = delete_option( $this->option_name );
		$intent_deleted  = delete_option( $this->delivery_intent_option_name );
		$version_deleted = delete_option( $this->product_version_option_name );
		return $state_deleted || $intent_deleted || $version_deleted;
	}

	private function replace( array $state ): bool {
		if ( false === get_option( $this->option_name, false ) ) {
			return add_option( $this->option_name, $state, '', false );
		}
		return update_option( $this->option_name, $state, false );
	}

	/**
	 * @param array{observed?: string, pending?: string, intent?: string, settled_intent?: string} $observation Version observation.
	 */
	private function replace_product_version_observation( array $observation ): bool {
		if ( false === get_option( $this->product_version_option_name, false ) ) {
			return add_option( $this->product_version_option_name, $observation, '', false );
		}
		return update_option( $this->product_version_option_name, $observation, false )
			|| get_option( $this->product_version_option_name, array() ) === $observation;
	}

	private static function new_delivery_intent(): string {
		try {
			return bin2hex( random_bytes( 16 ) );
		} catch ( \Throwable $error ) {
			return '';
		}
	}

	/** @param mixed $intent Candidate intent. */
	private static function valid_delivery_intent( $intent ): bool {
		return is_string( $intent ) && 1 === preg_match( '/^[a-f0-9]{32}$/D', $intent );
	}

	/** @param mixed $version Candidate product version. */
	private static function valid_product_version( $version ): bool {
		return is_string( $version )
			&& '' !== $version
			&& strlen( $version ) <= 64
			&& 1 === preg_match( '/^[\x20-\x7e]+$/D', $version );
	}
}
